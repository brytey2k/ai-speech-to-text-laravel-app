<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TranscriptionStatus;
use App\Events\TranscriptionCompleted;
use App\Events\TranscriptionFailed;
use App\Events\TranscriptionInProgress;
use App\Exceptions\AudioFileNotFoundException;
use App\Exceptions\AudioTranscriptionNotFoundException;
use App\Exceptions\AudioTranscriptionNotInFailedStateException;
use App\Repositories\AudioTranscriptionRepository;
use App\Traits\SendAudioForTranscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResubmitFailedTranscription implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SendAudioForTranscription;

    /**
     * The unique ID of the job.
     *
     * @return int
     */
    public function uniqueId(): int
    {
        return $this->audioTranscriptionId;
    }

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
     * @throws AudioTranscriptionNotFoundException|AudioTranscriptionNotInFailedStateException|AudioFileNotFoundException
     */
    public function handle(AudioTranscriptionRepository $audioTranscriptionRepository): void
    {
        // TODO: REFACTOR THIS JOB IN ADDITION TO THE PROCESSING JOB

        // Retrieve the audio transcription record
        $audioTranscription = $audioTranscriptionRepository->findById($this->audioTranscriptionId);

        if (!$audioTranscription) {
            Log::error('Audio transcription not found for resubmission', ['id' => $this->audioTranscriptionId]);
            throw new AudioTranscriptionNotFoundException('Audio transcription not found for resubmission');
        }

        // Verify that the transcription is in a failed state
        if (!$audioTranscription->status->canBeResubmitted()) {
            Log::info('Skipping resubmission as transcription is not in failed state', [
                'id' => $this->audioTranscriptionId,
                'status' => $audioTranscription->status->value,
            ]);
            throw new AudioTranscriptionNotInFailedStateException('Audio transcription is not in a failed state');
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
            Log::error('Audio file not found for resubmission', ['path' => $filePath]);

            // Update status back to failed
            $audioTranscriptionRepository->update($audioTranscription, [
                'status' => TranscriptionStatus::FAILED,
            ]);

            // Dispatch the transcription failed event
            event(new TranscriptionFailed(
                segmentId: $this->audioTranscriptionId,
            ));

            throw new AudioFileNotFoundException('Audio file not found for resubmission');
        }

        // Get the full path to the file
        $fullPath = Storage::disk('public')->path($filePath);

        try {
            $response = $this->sendFileToTranscriptionService($fullPath);

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

                Log::info('Audio transcription resubmission completed successfully', ['id' => $this->audioTranscriptionId]);
            } else {
                // Update status to failed
                $audioTranscriptionRepository->update($audioTranscription, [
                    'status' => TranscriptionStatus::FAILED,
                ]);

                // Dispatch the transcription failed event
                event(new TranscriptionFailed(
                    segmentId: $this->audioTranscriptionId,
                ));

                Log::error('Failed to resubmit audio transcription', [
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

            Log::error('Exception while resubmitting audio transcription', [
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

            Log::error('Job failed while resubmitting audio transcription', [
                'id' => $this->audioTranscriptionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
