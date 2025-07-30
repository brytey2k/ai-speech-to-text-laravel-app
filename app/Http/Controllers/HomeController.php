<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SpeechSegmentRequest;
use App\Jobs\ProcessAudioTranscription;
use App\Repositories\AudioTranscriptionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The audio transcription repository instance.
     */
    protected $audioTranscriptionRepository;

    /**
     * Create a new controller instance.
     *
     * @param AudioTranscriptionRepository $audioTranscriptionRepository
     */
    public function __construct(AudioTranscriptionRepository $audioTranscriptionRepository)
    {
        $this->audioTranscriptionRepository = $audioTranscriptionRepository;
    }

    public function index(Request $request): View
    {
        // Fetch existing transcriptions from the database
        $transcriptions = $this->audioTranscriptionRepository->getCompletedTranscriptions();

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
            $filename = 'speech_segment_' . Str::uuid() . '.' . $audioFile->getClientOriginalExtension();

            // Store the file
            $path = $audioFile->storeAs('speech_segments', $filename, 'public');

            // Create a record in the database
            $audioTranscription = $this->audioTranscriptionRepository->create([
                'file_path' => $path,
                // transcription will be null initially
            ]);

            // Dispatch the job to process the audio file
            ProcessAudioTranscription::dispatch($audioTranscription->id);

            return response()->json([
                'success' => true,
                'message' => 'Speech segment received successfully',
                'path' => $path,
                'id' => $audioTranscription->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing speech segment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
