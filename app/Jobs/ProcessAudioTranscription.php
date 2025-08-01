<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TranscriptionStatus;
use App\Events\TranscriptionCompleted;
use App\Events\TranscriptionFailed;
use App\Events\TranscriptionInProgress;
use App\Exceptions\AudioTranscriptionNotFoundException;
use App\Models\AudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use App\Traits\SendAudioForTranscription;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessAudioTranscription implements ShouldQueue
{
    use Queueable;
    use SendAudioForTranscription;

    private AudioTranscriptionRepository $audioTranscriptionRepository;

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
     *
     * @throws AudioTranscriptionNotFoundException|AudioTranscriptionNotFoundException
     */
    public function handle(AudioTranscriptionRepository $audioTranscriptionRepository): void
    {
        $this->setAudioTranscriptionRepository($audioTranscriptionRepository);

        $audioTranscription = $this->getAudioTranscriptionRecord();

        // Update status to in progress
        $this->audioTranscriptionRepository->update($audioTranscription, [
            'status' => TranscriptionStatus::IN_PROGRESS,
        ]);

        // Dispatch the transcription in progress event
        event(new TranscriptionInProgress(
            segmentId: $this->audioTranscriptionId,
        ));

        // Get the file path
        $filePath = $audioTranscription->file_path;
        $this->ensureAudioFileExists($filePath);
        $fullPath = Storage::disk('public')->path($filePath);

        try {
            $response = $this->sendFileToTranscriptionService($fullPath);

            if (!$response->successful()) {
                $this->handleError($audioTranscription, 'Failed to transcribe audio', $response, null);

                return;
            }

            /** @var string $transcriptionText */
            $transcriptionText = $response->json('text');
            $this->handleSuccess($audioTranscription, $transcriptionText);
        } catch (\Exception $e) {
            $this->handleError($audioTranscription, 'Exception while transcribing audio', null, $e);
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

    /**
     * @param string $filePath
     *
     * @throws AudioTranscriptionNotFoundException
     */
    private function ensureAudioFileExists(string $filePath): void
    {
        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('Audio file not found', ['path' => $filePath]);
            throw new AudioTranscriptionNotFoundException('Audio file not found at path: ' . $filePath);
        }
    }

    private function handleSuccess(
        AudioTranscription $audioTranscription,
        string $transcriptionText,
    ): void {
        // Update the transcription field and set status to success
        $this->audioTranscriptionRepository->update($audioTranscription, [
            'transcription' => $transcriptionText,
            'status' => TranscriptionStatus::SUCCESS,
        ]);

        // Broadcast the transcription completed event
        event(new TranscriptionCompleted(
            segmentId: $this->audioTranscriptionId,
            transcription: $transcriptionText,
        ));

        Log::info('Audio transcription completed', ['id' => $this->audioTranscriptionId]);
    }

    private function handleError(
        AudioTranscription $audioTranscription,
        string $errorLogMessage,
        Response|null $response,
        \Throwable|null $throwable,
    ): void {
        $this->audioTranscriptionRepository->update($audioTranscription, [
            'status' => TranscriptionStatus::FAILED,
        ]);

        event(new TranscriptionFailed(
            segmentId: $this->audioTranscriptionId,
        ));

        $context = [
            'id' => $this->audioTranscriptionId,
        ];
        if ($throwable) {
            $context['message'] = $throwable->getMessage();
        }
        if ($response) {
            $context['status'] = $response->status();
            $context['response'] = $response->json();
        }

        Log::error($errorLogMessage, $context);
    }

    private function setAudioTranscriptionRepository(AudioTranscriptionRepository $audioTranscriptionRepository): void
    {
        $this->audioTranscriptionRepository = $audioTranscriptionRepository;
    }

    /**
     * @throws AudioTranscriptionNotFoundException
     */
    private function getAudioTranscriptionRecord(): AudioTranscription
    {
        $audioTranscription = $this->audioTranscriptionRepository->findById($this->audioTranscriptionId);

        if (!$audioTranscription) {
            Log::error('Audio transcription not found', ['id' => $this->audioTranscriptionId]);
            throw new AudioTranscriptionNotFoundException('Audio transcription not found with ID: ' . $this->audioTranscriptionId);
        }

        return $audioTranscription;
    }
}
