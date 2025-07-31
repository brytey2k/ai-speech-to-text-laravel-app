<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Events\TranscriptionCompleted;
use App\Events\TranscriptionFailed;
use App\Events\TranscriptionInProgress;
use App\Jobs\ProcessAudioTranscription;
use App\Models\AudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessAudioTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Event::fake();
        Http::preventStrayRequests();
    }

    public function test_handle_processes_audio_file_successfully(): void
    {
        // Create a test audio file
        $filePath = 'test_audio.mp3';
        Storage::disk('public')->put($filePath, 'fake audio content');

        // Create a test audio transcription record
        $audioTranscription = AudioTranscription::factory()->create([
            'file_path' => $filePath,
            'transcription' => null,
        ]);

        // Mock the HTTP response from OpenAI
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'This is a test transcription',
            ], 200),
        ]);

        // Execute the job
        $job = new ProcessAudioTranscription($audioTranscription->id);
        $job->handle(app(AudioTranscriptionRepository::class));

        // Assert the transcription was updated
        $this->assertDatabaseHas('audio_transcriptions', [
            'id' => $audioTranscription->id,
            'transcription' => 'This is a test transcription',
        ]);

        // Assert the events were dispatched
        Event::assertDispatched(TranscriptionInProgress::class, static fn($event) => $event->segmentId === $audioTranscription->id);

        Event::assertDispatched(TranscriptionCompleted::class, static fn($event) => $event->segmentId === $audioTranscription->id
                   && $event->transcription === 'This is a test transcription');
    }

    public function test_handle_logs_error_when_audio_transcription_not_found(): void
    {
        // Mock the Log facade
        Log::shouldReceive('error')
            ->once()
            ->with('Audio transcription not found', ['id' => 999]);

        // Execute the job with a non-existent ID
        $job = new ProcessAudioTranscription(999);
        $job->handle(app(AudioTranscriptionRepository::class));
    }

    public function test_handle_logs_error_when_audio_file_not_found(): void
    {
        // Create a test audio transcription record with a non-existent file
        $audioTranscription = AudioTranscription::factory()->create([
            'file_path' => 'non_existent_file.mp3',
            'transcription' => null,
        ]);

        // Mock the Log facade
        Log::shouldReceive('error')
            ->once()
            ->with('Audio file not found', ['path' => 'non_existent_file.mp3']);

        // Execute the job
        $job = new ProcessAudioTranscription($audioTranscription->id);
        $job->handle(app(AudioTranscriptionRepository::class));
    }

    public function test_handle_logs_error_when_api_request_fails(): void
    {
        // Create a test audio file
        $filePath = 'test_audio.mp3';
        Storage::disk('public')->put($filePath, 'fake audio content');

        // Create a test audio transcription record
        $audioTranscription = AudioTranscription::factory()->create([
            'file_path' => $filePath,
            'transcription' => null,
        ]);

        // Mock the HTTP response from OpenAI to fail
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'error' => 'Invalid API key',
            ], 401),
        ]);

        // Mock the Log facade
        Log::shouldReceive('error')
            ->once()
            ->with('Failed to transcribe audio', [
                'id' => $audioTranscription->id,
                'status' => 401,
                'response' => ['error' => 'Invalid API key'],
            ]);

        // Execute the job
        $job = new ProcessAudioTranscription($audioTranscription->id);
        $job->handle(app(AudioTranscriptionRepository::class));

        // Assert the TranscriptionInProgress event was dispatched
        Event::assertDispatched(TranscriptionInProgress::class, static fn($event) => $event->segmentId === $audioTranscription->id);

        // Assert the TranscriptionFailed event was dispatched
        Event::assertDispatched(TranscriptionFailed::class, static fn($event) => $event->segmentId === $audioTranscription->id);
    }

    public function test_failed_method_updates_status_to_failed(): void
    {
        // Create a test audio file
        $filePath = 'test_audio.mp3';
        Storage::disk('public')->put($filePath, 'fake audio content');

        // Create a test audio transcription record
        $audioTranscription = AudioTranscription::factory()->create([
            'file_path' => $filePath,
            'transcription' => null,
        ]);

        // Create the job
        $job = new ProcessAudioTranscription($audioTranscription->id);

        // Mock the Log facade
        Log::shouldReceive('error')
            ->once()
            ->with('Job failed while processing audio transcription', [
                'id' => $audioTranscription->id,
                'message' => 'Test exception',
            ]);

        // Call the failed method directly
        $job->failed(new \Exception('Test exception'));

        // Assert the status was updated to FAILED
        $this->assertDatabaseHas('audio_transcriptions', [
            'id' => $audioTranscription->id,
            'status' => 'F', // FAILED status
        ]);

        // Assert the TranscriptionFailed event was dispatched
        Event::assertDispatched(TranscriptionFailed::class, static fn($event) => $event->segmentId === $audioTranscription->id);
    }
}
