<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TranscriptionStatus;
use App\Http\Requests\SpeechSegmentRequest;
use App\Jobs\ProcessAudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use App\Services\UuidGenerator;

class HandleSpeechSegment
{
    public function __construct(
        protected AudioTranscriptionRepository $audioTranscriptionRepository,
        protected UuidGenerator $uuidGenerator,
    ) {}

    /**
     * Execute the action to handle a speech segment
     *
     * @param SpeechSegmentRequest $request
     *
     * @return array<string, string|int|bool>
     */
    public function execute(SpeechSegmentRequest $request): array
    {
        try {
            $audioFile = $request->file('audio');

            $filename = 'speech_segment_' . $this->uuidGenerator->generate() . '.' . $audioFile->getClientOriginalExtension();
            $folderPath = now()->format('Y/m/d/H');

            $path = $audioFile->storeAs("speech_segments/{$folderPath}", $filename, 'public');

            $audioTranscription = $this->audioTranscriptionRepository->create([
                'file_path' => $path,
                'status' => TranscriptionStatus::PENDING,
            ]);

            // Dispatch the job to process the audio file
            ProcessAudioTranscription::dispatch($audioTranscription->id);

            return [
                'success' => true,
                'message' => 'Speech segment received successfully',
                'id' => $audioTranscription->id,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'An error occurred while processing speech segment.',
                'status' => 500,
            ];
        }
    }
}
