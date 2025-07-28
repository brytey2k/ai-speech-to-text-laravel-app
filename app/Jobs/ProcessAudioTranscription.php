<?php

namespace App\Jobs;

use App\Events\TranscriptionCompleted;
use App\Models\AudioTranscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessAudioTranscription implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $audioTranscriptionId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Retrieve the audio transcription record
        $audioTranscription = AudioTranscription::find($this->audioTranscriptionId);

//        event(new TranscriptionCompleted(
//            segmentId: $this->audioTranscriptionId,
//            transcription: 'everything is fine, we are still waiting for the transcription to complete'
//        ));
//        return;

        if (!$audioTranscription) {
            Log::error('Audio transcription not found', ['id' => $this->audioTranscriptionId]);
            return;
        }

        // Get the file path
        $filePath = $audioTranscription->file_path;

        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('Audio file not found', ['path' => $filePath]);
            return;
        }

        // Get the full path to the file
        $fullPath = Storage::disk('public')->path($filePath);

        try {
            // Send the file to OpenAI's Whisper API
            $response = Http::withToken(config('services.openai.api_key'))
                ->attach('file', file_get_contents($fullPath), basename($fullPath))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                ]);

            if ($response->successful()) {
                $transcription = $response->json('text');

                // Update the transcription field
                $audioTranscription->update([
                    'transcription' => $transcription,
                ]);

                // Broadcast the transcription completed event
                event(new TranscriptionCompleted(
                    segmentId: $this->audioTranscriptionId,
                    transcription: $transcription
                ));

                Log::info('Audio transcription completed', ['id' => $this->audioTranscriptionId]);
            } else {
                Log::error('Failed to transcribe audio', [
                    'id' => $this->audioTranscriptionId,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while transcribing audio', [
                'id' => $this->audioTranscriptionId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
