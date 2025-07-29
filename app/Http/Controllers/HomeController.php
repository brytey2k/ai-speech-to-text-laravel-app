<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAudioTranscription;
use App\Models\AudioTranscription;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        // Fetch existing transcriptions from the database
        $transcriptions = AudioTranscription::whereNotNull('transcription')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('index', compact('transcriptions'));
    }

    /**
     * Handle audio segments from VAD pause detection
     */
    public function handleSpeechSegment(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'audio' => 'required|file|mimes:wav,mp3,ogg,webm',
            ]);

            // Get the audio file from the request
            $audioFile = $request->file('audio');

            // Generate a unique filename
            $filename = 'speech_segment_' . Str::uuid() . '.' . $audioFile->getClientOriginalExtension();

            // Store the file
            $path = $audioFile->storeAs('speech_segments', $filename, 'public');

            // Create a record in the database
            $audioTranscription = AudioTranscription::create([
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
