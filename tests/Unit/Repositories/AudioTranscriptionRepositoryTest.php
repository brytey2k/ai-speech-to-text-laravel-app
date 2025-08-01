<?php

declare(strict_types=1);

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
