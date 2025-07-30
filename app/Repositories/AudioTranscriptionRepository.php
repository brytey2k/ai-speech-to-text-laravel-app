<?php

namespace App\Repositories;

use App\Models\AudioTranscription;
use Illuminate\Database\Eloquent\Collection;

class AudioTranscriptionRepository
{
    /**
     * Get all transcriptions that have been completed
     *
     * @return Collection
     */
    public function getCompletedTranscriptions(): Collection
    {
        return AudioTranscription::whereNotNull('transcription')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find an audio transcription by ID
     *
     * @param int $id
     * @return AudioTranscription|null
     */
    public function findById(int $id): ?AudioTranscription
    {
        return AudioTranscription::find($id);
    }

    /**
     * Create a new audio transcription record
     *
     * @param array $data
     * @return AudioTranscription
     */
    public function create(array $data): AudioTranscription
    {
        return AudioTranscription::create($data);
    }

    /**
     * Update an audio transcription record
     *
     * @param AudioTranscription $audioTranscription
     * @param array $data
     * @return bool
     */
    public function update(AudioTranscription $audioTranscription, array $data): bool
    {
        return $audioTranscription->update($data);
    }
}
