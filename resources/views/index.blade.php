@extends('app')

@section('title', 'Audio Recorder')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>Audio Recorder</h1>
            <p class="lead">Record audio from your microphone and visualize the waveform.</p>

            <div id="wavesurfer-container" class="mt-4">
                <!-- WaveSurfer elements will be created here by JavaScript -->
            </div>
        </div>
    </div>
@endsection
