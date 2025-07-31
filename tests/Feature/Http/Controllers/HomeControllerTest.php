<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Jobs\ProcessAudioTranscription;
use App\Models\AudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use App\Services\UuidGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
    }

    public function test_index_displays_completed_transcriptions(): void
    {
        // Create some completed transcriptions
        AudioTranscription::factory()->count(3)->create([
            'transcription' => 'Test transcription',
        ]);

        // Create some incomplete transcriptions
        AudioTranscription::factory()->count(2)->create([
            'transcription' => null,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('index');
        $response->assertViewHas('transcriptions');

        // Verify that all transcriptions are returned
        $transcriptions = $response->viewData('transcriptions');
        $this->assertCount(5, $transcriptions);
        $this->assertInstanceOf(AudioTranscription::class, $transcriptions->first());
    }

    public function test_handle_speech_segment_stores_file_and_dispatches_job(): void
    {
        $uuidGeneratorMock = $this->createMock(UuidGenerator::class);
        $uuidGeneratorMock->expects($this->once())
            ->method('generate')
            ->willReturn('test-uuid-1234-5678-90ab-cdef12345678');
        $this->app->instance(UuidGenerator::class, $uuidGeneratorMock);

        // Create a fake audio file
        $file = UploadedFile::fake()->create('audio.mp3', 100);

        $response = $this->postJson('/api/speech-segments', [
            'audio' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Speech segment received successfully',
        ]);

        // Assert the file was stored
        Storage::disk('public')
            ->assertExists('speech_segments/speech_segment_test-uuid-1234-5678-90ab-cdef12345678.mp3');

        // Assert a record was created in the database
        $this->assertDatabaseCount('audio_transcriptions', 1);
        $audioTranscription = AudioTranscription::first();
        $this->assertNotNull($audioTranscription);
        $this->assertNull($audioTranscription->transcription);

        // Assert the job was dispatched
        Queue::assertPushed(ProcessAudioTranscription::class, static fn($job) => $job->audioTranscriptionId === $audioTranscription->id);
    }

    public function test_handle_speech_segment_returns_error_on_exception(): void
    {
        // Mock the repository to throw an exception
        $mockRepository = $this->createMock(AudioTranscriptionRepository::class);
        $mockRepository->method('create')->willThrowException(new \Exception('Test exception'));
        $this->app->instance(AudioTranscriptionRepository::class, $mockRepository);

        // Create a fake audio file
        $file = UploadedFile::fake()->create('audio.mp3', 100);

        // Make the request
        $response = $this->postJson('/api/speech-segments', [
            'audio' => $file,
        ]);

        // Just assert the status code
        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'An error occurred while processing speech segment.',
        ]);
    }
}
