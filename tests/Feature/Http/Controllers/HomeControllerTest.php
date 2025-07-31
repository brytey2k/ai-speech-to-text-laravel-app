<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\ProcessAudioTranscription;
use App\Models\AudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
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

        // Verify that only completed transcriptions are returned
        $transcriptions = $response->viewData('transcriptions');
        $this->assertCount(3, $transcriptions);
        $this->assertInstanceOf(AudioTranscription::class, $transcriptions->first());
        $this->assertNotNull($transcriptions->first()->transcription);
    }

    public function test_handle_speech_segment_stores_file_and_dispatches_job(): void
    {
        $uuid = 'test-uuid-1234-5678-90ab-cdef12345678';
        $mock = \Mockery::mock('alias:' . Uuid::class);
        $mock->shouldReceive('uuid4')->once()->andReturn($uuid);

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
        Storage::disk('public')->assertExists('speech_segments/speech_segment_' . $uuid . '.mp3');

        // Assert a record was created in the database
        $this->assertDatabaseCount('audio_transcriptions', 1);
        $audioTranscription = AudioTranscription::first();
        $this->assertNotNull($audioTranscription);
        $this->assertNull($audioTranscription->transcription);

        // Assert the job was dispatched
        Queue::assertPushed(ProcessAudioTranscription::class, function ($job) use ($audioTranscription) {
            return $job->audioTranscriptionId === $audioTranscription->id;
        });
    }

    public function test_handle_speech_segment_returns_error_on_exception(): void
    {
        // Mock the repository to throw an exception
        $mockRepository = $this->createMock(AudioTranscriptionRepository::class);
        $mockRepository->method('create')->willThrowException(new \Exception('Test exception'));
        $this->app->instance(AudioTranscriptionRepository::class, $mockRepository);

        // Create a fake audio file
        $file = UploadedFile::fake()->create('audio.mp3', 100);

        $response = $this->postJson('/api/speech-segments', [
            'audio' => $file,
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Error processing speech segment: Test exception',
        ]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
