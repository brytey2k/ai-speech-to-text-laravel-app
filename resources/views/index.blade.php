@extends('app')

@section('title', 'Audio Recorder')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>Audio Recorder</h1>
            <p class="lead">Record audio from your microphone and visualize the waveform.</p>

            <div id="wavesurfer-container" class="mt-4">
                <!-- Microphone selector -->
                <select id="mic-selector" class="form-select mb-3"></select>

                <!-- Waveform visualization -->
                <div id="waveform"></div>

                <!-- VAD status message -->
                <div id="vad-status" class="alert alert-info mt-3 d-none">Listening for speech...</div>

                <!-- Recording controls -->
                <div id="record-controls" class="mt-3">
                    <button id="record-start" class="btn btn-primary me-2">Start Transcription</button>
                    <button id="record-stop" class="btn btn-danger me-2 d-none" disabled>X</button>
                    <span id="record-time" class="ms-2">00:00</span>
                </div>

                <!-- Audio boxes container -->
                <div id="audio-boxes-container" class="mt-5">
                    <h3 class="mb-3">Recorded Audio Segments</h3>
                    <div id="audio-boxes-wrapper" class="row"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
