<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\TranscriptionStatus;
use App\Jobs\ResubmitFailedTranscription;
use App\Models\AudioTranscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResubmitFailedTranscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_jobs_for_failed_transcriptions(): void
    {
        // Arrange
        Queue::fake();

        // Create some failed transcriptions
        $failedTranscriptions = AudioTranscription::factory()->count(3)->create([
            'status' => TranscriptionStatus::FAILED,
        ]);

        // Create a successful transcription (should be ignored)
        AudioTranscription::factory()->create([
            'status' => TranscriptionStatus::SUCCESS,
        ]);

        // Act
        $this->artisan('transcriptions:resubmit-failed') // @phpstan-ignore-line
            ->assertSuccessful();

        // Assert
        // Verify that a job was dispatched for each failed transcription
        foreach ($failedTranscriptions as $transcription) {
            Queue::assertPushed(ResubmitFailedTranscription::class, static fn($job) => $job->audioTranscriptionId === $transcription->id);
        }

        // Verify that only the expected number of jobs were dispatched
        Queue::assertPushed(ResubmitFailedTranscription::class, $failedTranscriptions->count());
    }

    public function test_command_handles_empty_failed_transcriptions(): void
    {
        // Arrange
        Queue::fake();

        // No failed transcriptions in the database

        // Act
        $this->artisan('transcriptions:resubmit-failed') // @phpstan-ignore-line
            ->assertSuccessful();

        // Assert
        Queue::assertNothingPushed();
    }

    public function test_job_uniqueness_prevents_duplicate_jobs(): void
    {
        // Arrange
        Queue::fake();

        // Create a failed transcription
        $transcription = AudioTranscription::factory()->create([
            'status' => TranscriptionStatus::FAILED,
        ]);

        // Act - Dispatch the same job twice
        ResubmitFailedTranscription::dispatch($transcription->id);
        ResubmitFailedTranscription::dispatch($transcription->id);

        // Assert - Only one job should be pushed to the queue
        Queue::assertPushed(ResubmitFailedTranscription::class, 1);

        // Verify the job has the correct ID
        Queue::assertPushed(ResubmitFailedTranscription::class, static fn($job) => $job->audioTranscriptionId === $transcription->id);
    }
}
