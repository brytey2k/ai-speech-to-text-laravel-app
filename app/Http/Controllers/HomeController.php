<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TranscriptionStatus;
use App\Http\Requests\SpeechSegmentRequest;
use App\Jobs\ProcessAudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use App\Services\UuidGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected AudioTranscriptionRepository $audioTranscriptionRepository,
        protected UuidGenerator $uuidGenerator,
    ) {}

    public function index(): View
    {
        // Fetch all transcriptions from the database
        $transcriptions = $this->audioTranscriptionRepository->getAllTranscriptions();

        return view('index', compact('transcriptions'));
    }

    /**
     * Handle audio segments from VAD pause detection
     *
     * @param SpeechSegmentRequest $request
     */
    public function handleSpeechSegment(SpeechSegmentRequest $request): JsonResponse
    {
        try {
            // Get the audio file from the request
            $audioFile = $request->file('audio');

            // Generate a unique filename
            $filename = 'speech_segment_' . $this->uuidGenerator->generate() . '.' . $audioFile->getClientOriginalExtension();

            // Store the file
            $path = $audioFile->storeAs('speech_segments', $filename, 'public');

            // Create a record in the database
            $audioTranscription = $this->audioTranscriptionRepository->create([
                'file_path' => $path,
                'status' => TranscriptionStatus::PENDING,
                // transcription will be null initially
            ]);

            // Dispatch the job to process the audio file
            ProcessAudioTranscription::dispatch($audioTranscription->id);

            return response()->json([
                'success' => true,
                'message' => 'Speech segment received successfully',
                'id' => $audioTranscription->id,
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing speech segment.',
            ], 500);
        }
    }
}
