<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TranscriptionStatus;
use App\Events\TranscriptionCompleted;
use App\Events\TranscriptionFailed;
use App\Events\TranscriptionInProgress;
use App\Repositories\AudioTranscriptionRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessAudioTranscription implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param int $audioTranscriptionId
     */
    public function __construct(
        public int $audioTranscriptionId,
    ) {}

    /**
     * Execute the job.
     *
     * @param AudioTranscriptionRepository $audioTranscriptionRepository
     */
    public function handle(AudioTranscriptionRepository $audioTranscriptionRepository): void
    {
        // Retrieve the audio transcription record
        $audioTranscription = $audioTranscriptionRepository->findById($this->audioTranscriptionId);

        if (!$audioTranscription) {
            Log::error('Audio transcription not found', ['id' => $this->audioTranscriptionId]);
            return;
        }

        // Update status to in progress
        $audioTranscriptionRepository->update($audioTranscription, [
            'status' => TranscriptionStatus::IN_PROGRESS,
        ]);

        // Dispatch the transcription in progress event
        event(new TranscriptionInProgress(
            segmentId: $this->audioTranscriptionId,
        ));

        // Get the file path
        $filePath = $audioTranscription->file_path;

        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('Audio file not found', ['path' => $filePath]);
            return;
        }

        // Get the full path to the file
        $fullPath = Storage::disk('public')->path($filePath);

        try {
            $audioContent = file_get_contents($fullPath);
            if ($audioContent === false) {
                Log::error('Failed to read audio file', ['path' => $fullPath]);
                $this->fail('Failed to read audio file');
                return;
            }

            // Send the file to OpenAI's Whisper API
            $response = Http::withToken(config()->string('services.openai.api_key'))
                ->attach('file', $audioContent, basename($fullPath))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                ]);

            if ($response->successful()) {
                $transcription = $response->json('text');

                // Update the transcription field and set status to success
                $audioTranscriptionRepository->update($audioTranscription, [
                    'transcription' => $transcription,
                    'status' => TranscriptionStatus::SUCCESS,
                ]);

                // Broadcast the transcription completed event
                event(new TranscriptionCompleted(
                    segmentId: $this->audioTranscriptionId,
                    transcription: $transcription, // @phpstan-ignore-line
                ));

                Log::info('Audio transcription completed', ['id' => $this->audioTranscriptionId]);
            } else {
                // Update status to failed
                $audioTranscriptionRepository->update($audioTranscription, [
                    'status' => TranscriptionStatus::FAILED,
                ]);

                // Dispatch the transcription failed event
                event(new TranscriptionFailed(
                    segmentId: $this->audioTranscriptionId,
                ));

                Log::error('Failed to transcribe audio', [
                    'id' => $this->audioTranscriptionId,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            // Update status to failed
            $audioTranscriptionRepository->update($audioTranscription, [
                'status' => TranscriptionStatus::FAILED,
            ]);

            // Dispatch the transcription failed event
            event(new TranscriptionFailed(
                segmentId: $this->audioTranscriptionId,
            ));

            Log::error('Exception while transcribing audio', [
                'id' => $this->audioTranscriptionId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        $audioTranscriptionRepository = app(AudioTranscriptionRepository::class);
        $audioTranscription = $audioTranscriptionRepository->findById($this->audioTranscriptionId);

        if ($audioTranscription) {
            // Update status to failed
            $audioTranscriptionRepository->update($audioTranscription, [
                'status' => TranscriptionStatus::FAILED,
            ]);

            // Dispatch the transcription failed event
            event(new TranscriptionFailed(
                segmentId: $this->audioTranscriptionId,
            ));

            Log::error('Job failed while processing audio transcription', [
                'id' => $this->audioTranscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
