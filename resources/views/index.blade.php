@extends('app')

@section('title', 'Audio Transcriber')

@php
use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
    @php
        $hasTranscriptions = count($transcriptions) > 0;
    @endphp
    <div class="flex flex-col w-full">
        <!-- Main content area (100% when no transcripts, 50% on md/lg screens, 75% on xl screens when transcripts exist) -->
        <div id="main-content-area" class="w-full {{ $hasTranscriptions ? 'md:w-1/2 lg:w-1/2 xl:w-3/4' : 'md:w-full' }} overflow-y-auto p-4 pt-24 md:pt-4 flex flex-col items-center justify-center {{ $hasTranscriptions ? 'h-1/2 md:h-full md:flex md:items-center md:justify-center' : 'h-full' }} space-y-8 transition-all duration-500 ease-in-out">
            <h1 class="text-center mt-6 md:mt-0">Welcome to Darli</h1>

            <div id="wavesurfer-container" class="w-full flex flex-col items-center space-y-8 relative z-10 mt-6">
                <!-- Waveform visualization -->
                <div id="waveform" class="w-1/2 mx-auto relative mt-4"></div>

                <!-- VAD status message -->
                <div id="vad-status" class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 hidden">Listening for speech...</div>

                <!-- Recording controls -->
                <div id="record-controls" class="flex flex-col sm:flex-row justify-center items-center w-full">
                    <div class="flex items-center">
                        <button id="record-start" class="hover:bg-gray-800 text-white font-bold py-2 px-4 rounded mr-2" style="background-color: #0D0E10;">Start Transcription</button>
                        <span id="record-time" class="ml-2 self-center">00:00:00</span>
                    </div>
                    <button id="record-stop" class="bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-4 rounded mr-2 hidden mt-4 md:mt-0 md:fixed md:bottom-8 md:left-1/4 md:transform md:-translate-x-1/2 xl:left-[37.5%]" style="color: #616161;" disabled>X</button>
                </div>

                <!-- Microphone selector -->
                <div class="flex justify-center">
                    <select id="mic-selector" class="w-auto min-w-fit px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></select>
                </div>
            </div>
        </div>

        <!-- Transcripts area (full width on small screens, 50% width on md/lg screens, 25% width on xl and above, hidden when no transcripts) -->
        <div id="transcript-area" class="w-full {{ $hasTranscriptions ? 'h-1/2' : 'h-0' }} md:w-1/2 lg:w-1/2 xl:w-1/4 md:h-full p-4 pt-20 md:pt-4 pb-16 {{ !$hasTranscriptions ? 'hidden md:hidden' : '' }} transition-all duration-500 ease-in-out md:fixed md:right-0 md:top-14 md:bottom-8">
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
                                    <div class="mb-4 text-gray-800">
                                        {{ $transcription->transcription }}
                                    </div>
                                    @if(Storage::disk('public')->exists($transcription->file_path))
                                        <audio controls class="w-full rounded-md h-[25px] focus:outline-none focus:ring-2 focus:ring-blue-500" src="{{ asset('storage/' . str_replace('public/', '', $transcription->file_path)) }}"></audio>
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
            // Variable to store the VAD instance
            let vadInstance = null;

            // Variables for speech chunking
            let isSpeechActive = false;
            let speechStartTime = 0;
            let chunkingTimer = null;
            const MAX_CHUNK_DURATION = 15000; // 15 seconds in milliseconds // todo: should be in .env file

            // Variables for tracking audio data
            let currentAudioFrames = [];
            let isCollectingAudioFrames = false;

            // Set up WebSocket listener for transcription results
            const setupWebSocketListener = () => {
                // Check if Echo is available
                if (typeof window.Echo === 'undefined') {
                    console.error('Laravel Echo is not available');
                    return;
                }

                // Listen for the transcription.completed event on the public channel
                window.Echo.channel('public')
                    .listen('.TranscriptionCompleted', (event) => {
                        console.log('Received transcription result:', event);

                        if (event.segmentId && event.transcription) {
                            // Update the corresponding audio box with the transcription
                            updateAudioBoxWithTranscription(event.segmentId, event.transcription);
                        }
                    });

                console.log('WebSocket listener for transcription results set up');
            };

            // Function to check speech duration and force chunking if needed
            const checkSpeechDuration = () => {
                if (!isSpeechActive || !vadInstance) return;

                const currentTime = Date.now();
                const speechDuration = currentTime - speechStartTime;

                if (speechDuration >= MAX_CHUNK_DURATION) {
                    console.log(`Speech duration reached ${MAX_CHUNK_DURATION}ms, forcing chunk...`);
                    updateVADStatus(`Max duration (15s) reached. Chunking audio...`, 'warning');

                    // Stop collecting audio frames
                    isCollectingAudioFrames = false;

                    // Combine collected audio frames into a single Float32Array
                    if (currentAudioFrames.length > 0) {
                        // Calculate total length of all frames
                        const totalLength = currentAudioFrames.reduce((sum, frame) => sum + frame.length, 0);

                        // Create a new Float32Array to hold all the audio data
                        const combinedAudio = new Float32Array(totalLength);

                        // Copy each frame into the combined array
                        let offset = 0;
                        for (const frame of currentAudioFrames) {
                            combinedAudio.set(frame, offset);
                            offset += frame.length;
                        }

                        // Send the combined audio data to the API
                        console.log('Sending forced chunk audio data to API...');
                        sendAudioToAPI(combinedAudio);

                        // Clear the audio frames array
                        currentAudioFrames = [];
                    }

                    // Force a chunk by pausing and restarting the VAD
                    vadInstance.pause();

                    // Small delay before restarting to ensure the chunk is processed
                    setTimeout(() => {
                        if (vadInstance) {
                            vadInstance.start();

                            // Reset speech tracking
                            speechStartTime = Date.now();
                            isCollectingAudioFrames = true;

                            console.log('VAD restarted after forced chunking');
                            updateVADStatus('Continuing to listen...', 'info');
                        }
                    }, 100);
                }
            };

            // Function to update the VAD status message
            const updateVADStatus = (message, type = 'info') => {
                const statusEl = document.getElementById('vad-status');
                if (!statusEl) return;

                // Update message and status type
                statusEl.textContent = message;

                // Remove all alert classes and add the new one based on type
                statusEl.className = ''; // Clear all classes

                // Add appropriate Tailwind classes based on alert type
                if (type === 'info') {
                    statusEl.className = 'bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mt-3';
                } else if (type === 'success') {
                    statusEl.className = 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mt-3';
                } else if (type === 'warning') {
                    statusEl.className = 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mt-3';
                } else if (type === 'danger') {
                    statusEl.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mt-3';
                } else if (type === 'primary') {
                    statusEl.className = 'bg-indigo-100 border-l-4 border-indigo-500 text-indigo-700 p-4 mt-3';
                }

                // Make sure the element is visible
                statusEl.classList.remove('hidden');
            };

            // Function to create an audio box and add it to the container
            const createAudioBox = (segmentId, audioBlob) => {
                const boxesWrapper = document.getElementById('audio-boxes-wrapper');
                if (!boxesWrapper) return null;

                // Check if this is the first transcript
                const isFirstTranscript = boxesWrapper.querySelector('.w-full.mb-3') === null &&
                                         boxesWrapper.textContent.trim() === 'No transcriptions available yet.';

                // Create a column for the audio box
                const col = document.createElement('div');
                col.className = 'w-full mb-3';
                col.dataset.segmentId = segmentId;

                // Create the audio box
                const box = document.createElement('div');
                box.className = 'bg-white rounded-lg shadow-md overflow-hidden';

                // Create card body
                const cardBody = document.createElement('div');
                cardBody.className = 'p-4 relative'; // Added relative positioning for date placement

                // Add date element in the top right corner
                const dateElement = document.createElement('div');
                dateElement.className = 'absolute top-2 right-2 text-xs text-gray-500';
                const currentDate = new Date();
                dateElement.textContent = currentDate.toLocaleDateString() + ' ' +
                                         currentDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                // Add status indicator
                const status = document.createElement('div');
                status.className = 'bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-2 mb-3';
                status.textContent = 'Processing...';

                // Add transcription container (initially empty)
                const transcription = document.createElement('div');
                transcription.className = 'mb-4 text-gray-800';
                transcription.innerHTML = '<em>Waiting for transcription...</em>';

                // Add audio element (now below the transcription)
                const audio = document.createElement('audio');
                audio.controls = true;
                audio.className = 'w-full rounded-md h-[25px] focus:outline-none focus:ring-2 focus:ring-blue-500';
                audio.src = URL.createObjectURL(audioBlob);

                // Assemble the box
                cardBody.appendChild(dateElement);
                cardBody.appendChild(status);
                cardBody.appendChild(transcription);
                cardBody.appendChild(audio);
                box.appendChild(cardBody);
                col.appendChild(box);

                // Clear "No transcriptions available yet" message if it exists
                if (isFirstTranscript) {
                    boxesWrapper.innerHTML = '';
                }

                // Add to the container
                boxesWrapper.prepend(col); // Add to the beginning so newest is first

                // If this is the first transcript, update the layout
                if (isFirstTranscript) {
                    // Get the main content and transcript areas
                    const mainContentArea = document.getElementById('main-content-area');
                    const transcriptArea = document.getElementById('transcript-area');

                    if (mainContentArea && transcriptArea) {
                        // Small delay to ensure DOM is updated before animation starts
                        setTimeout(() => {
                            // Update classes for main content area
                            mainContentArea.classList.remove('md:w-full');
                            mainContentArea.classList.add('md:w-1/2', 'lg:w-1/2', 'xl:w-3/4');

                            // Add height classes for responsive layout
                            mainContentArea.classList.add('h-1/2', 'md:h-full', 'md:flex', 'md:items-center', 'md:justify-center');

                            // Ensure top padding is maintained for navbar
                            if (!mainContentArea.classList.contains('pt-24')) {
                                mainContentArea.classList.add('pt-24');
                            }
                            if (!mainContentArea.classList.contains('md:pt-4')) {
                                mainContentArea.classList.add('md:pt-4');
                            }

                            // Show transcript area and set its height
                            transcriptArea.classList.remove('md:hidden', 'hidden');
                            transcriptArea.classList.add('h-1/2', 'pb-16', 'pt-20', 'md:pt-4', 'md:bottom-8', 'md:top-14', 'md:fixed', 'md:w-1/2', 'lg:w-1/2', 'xl:w-1/4');
                            transcriptArea.classList.remove('h-0', 'md:absolute');

                            // Update the audio boxes container to match the static HTML
                            const audioBoxesContainer = document.getElementById('audio-boxes-container');
                            if (audioBoxesContainer) {
                                // Ensure consistent overflow behavior with the static HTML
                                audioBoxesContainer.classList.remove('md:overflow-auto', 'overflow-auto');
                                audioBoxesContainer.classList.add('overflow-y-auto');
                            }

                            // Ensure the page scrolls to show the transcript area on mobile
                            if (window.innerWidth < 768) {
                                setTimeout(() => {
                                    window.scrollTo({
                                        top: document.body.scrollHeight,
                                        behavior: 'smooth'
                                    });
                                }, 100);
                            }

                            console.log('Layout updated: Responsive layout applied for transcripts');
                        }, 50);
                    }
                }

                return col;
            };

            // Function to update an audio box with transcription
            const updateAudioBoxWithTranscription = (segmentId, transcriptionText) => {
                const audioBox = document.querySelector(`#audio-boxes-wrapper [data-segment-id="${segmentId}"]`);
                if (!audioBox) return;

                // Update status
                const status = audioBox.querySelector('[class^="bg-blue-100"]');
                if (status) {
                    status.className = 'bg-green-100 border-l-4 border-green-500 text-green-700 p-2 mb-3';
                    status.textContent = 'Transcription complete';
                }

                // Update transcription
                const transcription = audioBox.querySelector('.mb-4');
                if (transcription) {
                    transcription.textContent = transcriptionText;
                }
            };

            // Function to send audio data to API endpoint
            const sendAudioToAPI = async (audioData) => {
                try {
                    // Update status to show we're sending data
                    updateVADStatus('Sending audio segment to API...', 'warning');

                    // Convert Float32Array to WAV format
                    const wavBlob = AudioHelpers.float32ArrayToWav(audioData);

                    // Create FormData to send the blob
                    const formData = new FormData();
                    formData.append('audio', wavBlob, 'recording.wav');

                    // Send the audio data to the API endpoint
                    const response = await axios.post('/api/speech-segments', formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    });

                    console.log('Audio segment sent to API:', response.data);

                    // Create an audio box for this segment
                    if (response.data && response.data.id) {
                        createAudioBox(response.data.id, wavBlob);
                    }

                    // Update status to show success
                    updateVADStatus('Audio segment sent successfully!', 'success');

                    // Hide status after 2 seconds
                    setTimeout(() => {
                        // Only update to "Listening for speech..." if VAD is still active
                        if (vadInstance) {
                            updateVADStatus('Listening for speech...', 'info');
                        } else {
                            // Hide the status element if VAD is not active
                            const statusEl = document.getElementById('vad-status');
                            if (statusEl) {
                                statusEl.classList.add('hidden');
                            }
                        }
                    }, 2000);
                } catch (error) {
                    console.error('Error sending audio to API:', error);

                    // Update status to show error
                    updateVADStatus('Error sending audio segment: ' + error.message, 'danger');

                    // Hide status after 2 seconds
                    setTimeout(() => {
                        // Only update to "Listening for speech..." if VAD is still active
                        if (vadInstance) {
                            updateVADStatus('Listening for speech...', 'info');
                        } else {
                            // Hide the status element if VAD is not active
                            const statusEl = document.getElementById('vad-status');
                            if (statusEl) {
                                statusEl.classList.add('hidden');
                            }
                        }
                    }, 2000);
                }
            };

            // Initialize WaveSurfer
            const initWaveSurfer = () => {
                // Create WaveSurfer instance
                const wavesurfer = WaveSurfer.create({
                    container: '#waveform',
                    waveColor: '#030303',
                    progressColor: '#030303',
                    height: 64,
                    cursorWidth: 1,
                    cursorColor: 'lightgray',
                    normalize: true,
                    minPxPerSec: 30,
                    barWidth: 3,
                });

                // Create and register the Record plugin
                const record = wavesurfer.registerPlugin(
                    RecordPlugin.create({
                        scrollingWaveform: true,
                        renderRecordedAudio: false, // Disable rendering recorded audio
                    })
                );

                return { wavesurfer, record };
            };

            // Populate microphone selector dropdown
            const populateMicSelector = async (record) => {
                try {
                    const micSelector = document.getElementById('mic-selector');
                    if (!micSelector) return;

                    // Clear existing options
                    micSelector.innerHTML = '';

                    // Add default option
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select microphone...';
                    micSelector.appendChild(defaultOption);

                    // Get available audio devices
                    // Use the instance method instead of the static method
                    let devices = [];
                    try {
                        devices = await record.getAvailableAudioDevices();
                    } catch (err) {
                        console.warn('Could not enumerate audio devices, trying to get microphone access first:', err);
                        // Try to get microphone access first, which might help with device enumeration
                        try {
                            await record.startMic();
                            record.stopMic();
                            // Try again after getting permission
                            devices = await record.getAvailableAudioDevices();
                        } catch (micErr) {
                            console.error('Error accessing microphone:', micErr);
                            // Add a default option if we can't get the device list
                            const option = document.createElement('option');
                            option.value = 'default';
                            option.textContent = 'Default Microphone';
                            micSelector.appendChild(option);
                            return;
                        }
                    }

                    // Add options for each device
                    devices
                        .filter(device => device.kind === 'audioinput')
                        .forEach(device => {
                            const option = document.createElement('option');
                            option.value = device.deviceId;
                            option.textContent = device.label || `Microphone ${micSelector.options.length}`;
                            micSelector.appendChild(option);
                        });

                    // If no devices found or no labels available, request microphone access
                    if (devices.length === 0 || devices.some(device => !device.label)) {
                        try {
                            await record.startMic();
                            record.stopMic();
                            // Try again after getting permission
                            return await populateMicSelector(record);
                        } catch (err) {
                            console.error('Error accessing microphone:', err);
                        }
                    }
                } catch (err) {
                    console.error('Error populating microphone selector:', err);
                }
            };


            // Setup event handlers
            const setupEventHandlers = (wavesurfer, record) => {
                const startBtn = document.getElementById('record-start');
                const stopBtn = document.getElementById('record-stop');
                const timeDisplay = document.getElementById('record-time');
                const micSelector = document.getElementById('mic-selector');

                if (!startBtn || !stopBtn || !timeDisplay || !micSelector) return;

                // Start recording
                startBtn.addEventListener('click', async () => {
                    try {
                        const deviceId = micSelector.value;
                        if (!deviceId) {
                            alert('Please select a microphone');
                            return;
                        }

                        // If using default microphone, don't specify deviceId constraints
                        const constraints = deviceId === 'default'
                            ? {}
                            : { deviceId: { exact: deviceId } };

                        await record.startRecording(constraints);

                        // Initialize VAD for pause detection
                        try {
                            console.log('Initializing VAD for pause detection...');

                            // Show initializing status
                            updateVADStatus('Initializing voice activity detection...', 'info');

                            vadInstance = await vad.MicVAD.new({
                                onFrameProcessed: (probabilities, frame) => {
                                    // If speech is active, collect audio frames
                                    if (isSpeechActive && isCollectingAudioFrames) {
                                        currentAudioFrames.push(frame);
                                    }
                                },
                                onSpeechStart: () => {
                                    console.log('Speech started');
                                    updateVADStatus('Speech detected! Recording...', 'primary');

                                    // Set speech tracking variables
                                    isSpeechActive = true;
                                    speechStartTime = Date.now();

                                    // Start collecting audio frames
                                    currentAudioFrames = [];
                                    isCollectingAudioFrames = true;

                                    // Start the chunking timer to check duration every second
                                    if (chunkingTimer) {
                                        clearInterval(chunkingTimer);
                                    }
                                    chunkingTimer = setInterval(checkSpeechDuration, 1000);

                                    console.log('Speech tracking started, will chunk at 15 seconds if needed');
                                },
                                onSpeechEnd: (audio) => {
                                    console.log('Speech pause detected, sending audio segment...');
                                    updateVADStatus('Pause detected! Processing segment...', 'warning');

                                    // Reset speech tracking variables
                                    isSpeechActive = false;
                                    isCollectingAudioFrames = false;
                                    currentAudioFrames = [];

                                    // Clear the chunking timer
                                    if (chunkingTimer) {
                                        clearInterval(chunkingTimer);
                                        chunkingTimer = null;
                                    }

                                    // Send the audio segment to the API endpoint
                                    sendAudioToAPI(audio);

                                    // Automatically stop recording after sending the segment
                                    record.stopRecording();

                                    // Clean up VAD instance
                                    if (vadInstance) {
                                        vadInstance.destroy();
                                        vadInstance = null;
                                        console.log('VAD stopped automatically after pause detection');

                                        // Update UI to show recording has stopped
                                        startBtn.classList.remove('hidden');
                                        stopBtn.classList.add('hidden');
                                        stopBtn.disabled = true;
                                        micSelector.classList.remove('hidden');

                                        // Update status
                                        updateVADStatus('Recording stopped automatically after pause detection. Click Start Transcription to record again.', 'info');

                                        // Hide status after 2 seconds
                                        setTimeout(() => {
                                            const statusEl = document.getElementById('vad-status');
                                            if (statusEl) {
                                                statusEl.classList.add('hidden');
                                            }
                                        }, 2000);
                                    }
                                },
                                onVADMisfire: () => {
                                    console.log('VAD misfire detected');
                                    updateVADStatus('VAD misfire detected. Continuing to listen...', 'warning');

                                    // Reset speech tracking variables
                                    isSpeechActive = false;
                                    isCollectingAudioFrames = false;
                                    currentAudioFrames = [];

                                    // Clear the chunking timer
                                    if (chunkingTimer) {
                                        clearInterval(chunkingTimer);
                                        chunkingTimer = null;
                                    }

                                    // Reset status after a delay
                                    setTimeout(() => {
                                        if (vadInstance) {
                                            updateVADStatus('Listening for speech...', 'info');
                                        } else {
                                            // Hide the status element if VAD is not active
                                            const statusEl = document.getElementById('vad-status');
                                            if (statusEl) {
                                                statusEl.classList.add('hidden');
                                            }
                                        }
                                    }, 2000);
                                }
                            });

                            // Start the VAD
                            vadInstance.start();
                            console.log('VAD started successfully');
                            updateVADStatus('Voice activity detection started. Listening for speech...', 'success');

                            // Reset status after a delay
                            setTimeout(() => {
                                if (vadInstance) {
                                    updateVADStatus('Listening for speech...', 'info');
                                } else {
                                    // Hide the status element if VAD is not active
                                    const statusEl = document.getElementById('vad-status');
                                    if (statusEl) {
                                        statusEl.classList.add('hidden');
                                    }
                                }
                            }, 2000);
                        } catch (vadErr) {
                            console.error('Error initializing VAD:', vadErr);
                            updateVADStatus('Error initializing voice activity detection: ' + vadErr.message, 'danger');

                            // Hide status after 2 seconds
                            setTimeout(() => {
                                const statusEl = document.getElementById('vad-status');
                                if (statusEl) {
                                    statusEl.classList.add('hidden');
                                }
                            }, 2000);
                        }

                        startBtn.classList.add('hidden');
                        stopBtn.classList.remove('hidden');
                        stopBtn.disabled = false;
                        micSelector.classList.add('hidden');
                    } catch (err) {
                        console.error('Error starting recording:', err);

                        // Provide more specific error messages based on the error
                        if (err.name === 'NotAllowedError') {
                            alert('Microphone access denied. Please allow microphone access in your browser settings.');
                        } else if (err.name === 'NotFoundError') {
                            alert('No microphone found. Please connect a microphone and try again.');
                        } else if (err.name === 'NotReadableError') {
                            alert('Microphone is already in use by another application. Please close other applications using the microphone and try again.');
                        } else {
                            alert('Error starting recording. Please check microphone permissions and try again.');
                        }

                        // Re-enable the start button so the user can try again
                        startBtn.disabled = false;
                    }
                });

                // Stop recording
                stopBtn.addEventListener('click', () => {
                    record.stopRecording();

                    // Clean up VAD instance if it exists
                    if (vadInstance) {
                        console.log('Stopping VAD...');

                        // Reset speech tracking variables
                        isSpeechActive = false;
                        isCollectingAudioFrames = false;
                        currentAudioFrames = [];

                        // Clear the chunking timer
                        if (chunkingTimer) {
                            clearInterval(chunkingTimer);
                            chunkingTimer = null;
                            console.log('Chunking timer cleared');
                        }

                        vadInstance.destroy();
                        vadInstance = null;
                        console.log('VAD stopped');

                        // Update status
                        updateVADStatus('Recording stopped', 'danger');

                        // Hide status after 2 seconds
                        setTimeout(() => {
                            const statusEl = document.getElementById('vad-status');
                            if (statusEl) {
                                statusEl.classList.add('hidden');
                            }
                        }, 2000);
                    }

                    // Remove any existing audio element
                    const audioEl = document.getElementById('recorded-audio');
                    if (audioEl) {
                        audioEl.parentNode.removeChild(audioEl);
                    }

                    startBtn.classList.remove('hidden');
                    stopBtn.classList.add('hidden');
                    stopBtn.disabled = true;
                    micSelector.classList.remove('hidden');
                });

                // Update time display
                record.on('record-progress', (duration) => {
                    // Convert duration to seconds if it's in milliseconds
                    const durationInSeconds = duration > 1000 ? duration / 1000 : duration;
                    timeDisplay.textContent = AudioHelpers.formatTime(durationInSeconds);
                });

                // Handle recording end
                record.on('record-end', (blob) => {
                    console.log('Recording finished');
                    // Get duration and convert to seconds if it's in milliseconds
                    const duration = record.getDuration();
                    const durationInSeconds = duration > 1000 ? duration / 1000 : duration;
                    timeDisplay.textContent = AudioHelpers.formatTime(durationInSeconds);
                    // No longer creating or displaying the audio element
                });
            };

            // Main initialization
            const init = async () => {
                const { wavesurfer, record } = initWaveSurfer();
                await populateMicSelector(record);
                setupEventHandlers(wavesurfer, record);

                // Set up WebSocket listener for transcription results
                setupWebSocketListener();
            };

            // Initialize the application
            init().catch(err => console.error('Error initializing wavesurfer recorder:', err));
        });
    </script>
@endpush
