@extends('app')

@section('title', 'Audio Transcriber')

@use(App\Enums\TranscriptionStatus;use Illuminate\Support\Facades\Storage)

@section('content')
    @php
        $hasTranscriptions = count($transcriptions) > 0;
    @endphp
    <div class="flex flex-col w-full">
        <!-- Main content area (100% when no transcripts, 50% on md/lg screens, 75% on xl screens when transcripts exist) -->
        <div id="main-content-area"
             class="w-full {{ $hasTranscriptions ? 'md:w-1/2 lg:w-1/2 xl:w-3/4' : 'md:w-full' }} overflow-y-auto p-4 pt-24 md:pt-4 flex flex-col items-center justify-center {{ $hasTranscriptions ? 'h-1/2 md:h-full md:flex md:items-center md:justify-center' : 'h-full' }} space-y-8 transition-all duration-500 ease-in-out">
            <h1 class="text-center mt-6 md:mt-0">Welcome to Darli</h1>

            <div id="wavesurfer-container" class="w-full flex flex-col items-center space-y-8 relative z-10 mt-6">
                <!-- Waveform visualization -->
                <div id="waveform" class="w-1/2 mx-auto relative mt-4"></div>

                <!-- VAD status message -->
                <div id="vad-status" class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 hidden">
                    Listening for speech...
                </div>

                <!-- Recording controls -->
                <div id="record-controls" class="flex flex-col sm:flex-row justify-center items-center w-full">
                    <div class="flex items-center">
                        <button id="record-start" class="hover:bg-gray-800 text-white font-bold py-2 px-4 rounded mr-2"
                                style="background-color: #0D0E10;">Start Transcription
                        </button>
                        <span id="record-time" class="ml-2 self-center">00:00:00</span>
                    </div>
                    <button id="record-stop"
                            class="bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-4 rounded mr-2 hidden mt-4 md:mt-0 md:fixed md:bottom-8 md:left-1/4 md:transform md:-translate-x-1/2 xl:left-[37.5%]"
                            style="color: #616161;" disabled>X
                    </button>
                </div>

            </div>
        </div>

        <!-- Transcripts area (full width on small screens, 50% width on md/lg screens, 25% width on xl and above, hidden when no transcripts) -->
        <div id="transcript-area"
             class="w-full {{ $hasTranscriptions ? 'h-1/2' : 'h-0' }} md:w-1/2 lg:w-1/2 xl:w-1/4 md:h-full p-4 pt-20 md:pt-4 pb-16 {{ !$hasTranscriptions ? 'hidden md:hidden' : '' }} transition-all duration-500 ease-in-out md:fixed md:right-0 md:top-14 md:bottom-8">
            <div id="audio-boxes-container" class="bg-white p-4 rounded-lg shadow-md h-full overflow-y-auto">
                <h3 class="mb-3">Transcripts</h3>
                <div id="audio-boxes-wrapper" class="space-y-4 pt-2">
                    @forelse($transcriptions as $transcription)
                        <div class="w-full mb-3" data-segment-id="{{ $transcription->id }}">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="p-4 relative">
                                    <div class="absolute top-2 right-2 text-xs text-gray-500">
                                        {{ $transcription->created_at->format('n/j/Y g:i A') }}
                                    </div>
                                    <div class="absolute top-2 left-2 text-xs px-2 py-1 rounded-full
                                        @if($transcription->status?->value === 'S')
                                            bg-green-100 text-green-800
                                        @elseif($transcription->status?->value === 'F')
                                            bg-red-100 text-red-800
                                        @elseif($transcription->status?->value === 'I')
                                            bg-blue-100 text-blue-800
                                        @else
                                            bg-gray-100 text-gray-800
                                        @endif
                                    ">
                                        {{ $transcription->status?->label() ?? TranscriptionStatus::PENDING->label() }}
                                    </div>
                                    <div class="mb-4 text-gray-800 mt-6">
                                        @if($transcription->transcription)
                                            {{ $transcription->transcription }}
                                        @elseif($transcription->status?->value === TranscriptionStatus::IN_PROGRESS->value)
                                            <em>Transcription in progress...</em>
                                        @elseif($transcription->status?->value === TranscriptionStatus::FAILED->value)
                                            <em>Transcription failed</em>
                                        @else
                                            <em>Waiting to be processed...</em>
                                        @endif
                                    </div>
                                    @if(Storage::disk('public')->exists($transcription->file_path))
                                        <audio controls
                                               class="w-full rounded-md h-[25px] focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               src="{{ asset('storage/' . str_replace('public/', '', $transcription->file_path)) }}"></audio>
                                    @else
                                        <div class="text-red-500" style="color: #0D0E10">Audio file not available</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-500 text-center py-4">No transcriptions available yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page-scripts')
    <script>

        document.addEventListener('DOMContentLoaded', async () => {
            // Initialize the audio transcriber application
            initAudioTranscriberLib().catch(err => console.error('Error initializing audio transcriber:', err));
        });
    </script>
@endpush
