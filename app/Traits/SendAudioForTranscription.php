<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

trait SendAudioForTranscription
{
    /**
     * @param string $fullPath
     *
     * @throws ConnectionException
     *
     * @return Response
     */
    private function sendFileToTranscriptionService(string $fullPath): Response
    {
        return Http::withToken(config()->string('services.openai.api_key'))
            ->attach('file', file_get_contents($fullPath), basename($fullPath)) // @phpstan-ignore-line
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
            ]);
    }
}
