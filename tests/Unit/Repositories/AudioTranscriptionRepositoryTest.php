<?php

namespace Tests\Unit\Repositories;

use App\Models\AudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudioTranscriptionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AudioTranscriptionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AudioTranscriptionRepository();
    }

    public function test_get_completed_transcriptions_returns_only_completed_transcriptions(): void
    {
        // Create completed transcriptions
        AudioTranscription::factory()->count(3)->create([
            'transcription' => 'Test transcription',
        ]);

        // Create incomplete transcriptions
        AudioTranscription::factory()->count(2)->create([
            'transcription' => null,
        ]);

        $completedTranscriptions = $this->repository->getCompletedTranscriptions();

        $this->assertCount(3, $completedTranscriptions);
        foreach ($completedTranscriptions as $transcription) {
            $this->assertNotNull($transcription->transcription);
        }
    }

    public function test_get_completed_transcriptions_returns_in_descending_order(): void
    {
        // Create transcriptions with different timestamps
        AudioTranscription::factory()->create([
            'transcription' => 'First transcription',
            'created_at' => now()->subDays(2),
        ]);

        AudioTranscription::factory()->create([
            'transcription' => 'Second transcription',
            'created_at' => now()->subDay(),
        ]);

        AudioTranscription::factory()->create([
            'transcription' => 'Third transcription',
            'created_at' => now(),
        ]);

        $completedTranscriptions = $this->repository->getCompletedTranscriptions();

        $this->assertCount(3, $completedTranscriptions);
        $this->assertEquals('Third transcription', $completedTranscriptions[0]->transcription);
        $this->assertEquals('Second transcription', $completedTranscriptions[1]->transcription);
        $this->assertEquals('First transcription', $completedTranscriptions[2]->transcription);
    }

    public function test_find_by_id_returns_correct_transcription(): void
    {
        $audioTranscription = AudioTranscription::factory()->create([
            'transcription' => 'Test transcription',
        ]);

        $foundTranscription = $this->repository->findById($audioTranscription->id);

        $this->assertNotNull($foundTranscription);
        $this->assertEquals($audioTranscription->id, $foundTranscription->id);
        $this->assertEquals('Test transcription', $foundTranscription->transcription);
    }

    public function test_find_by_id_returns_null_for_nonexistent_id(): void
    {
        $foundTranscription = $this->repository->findById(999);

        $this->assertNull($foundTranscription);
    }

    public function test_create_creates_new_transcription(): void
    {
        $data = [
            'file_path' => 'test/path/audio.mp3',
            'transcription' => null,
        ];

        $audioTranscription = $this->repository->create($data);

        $this->assertInstanceOf(AudioTranscription::class, $audioTranscription);
        $this->assertEquals('test/path/audio.mp3', $audioTranscription->file_path);
        $this->assertNull($audioTranscription->transcription);
        $this->assertDatabaseHas('audio_transcriptions', $data);
    }

    public function test_update_updates_existing_transcription(): void
    {
        $audioTranscription = AudioTranscription::factory()->create([
            'file_path' => 'test/path/audio.mp3',
            'transcription' => null,
        ]);

        $updateData = [
            'transcription' => 'Updated transcription',
        ];

        $result = $this->repository->update($audioTranscription, $updateData);

        $this->assertTrue($result);
        $this->assertDatabaseHas('audio_transcriptions', [
            'id' => $audioTranscription->id,
            'file_path' => 'test/path/audio.mp3',
            'transcription' => 'Updated transcription',
        ]);
    }
}
