<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\HandleSpeechSegment;
use App\Http\Requests\SpeechSegmentRequest;
use App\Repositories\AudioTranscriptionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected AudioTranscriptionRepository $audioTranscriptionRepository,
        protected HandleSpeechSegment $handleSpeechSegment,
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
     * @param HandleSpeechSegment $handleSpeechSegment
     */
    public function handleSpeechSegment(SpeechSegmentRequest $request, HandleSpeechSegment $handleSpeechSegment): JsonResponse
    {
        $result = $handleSpeechSegment->execute($request);

        /** @var int $statusCode */
        $statusCode = $result['status'] ?? 200;
        unset($result['status']);

        return response()->json($result, $statusCode);
    }
}
