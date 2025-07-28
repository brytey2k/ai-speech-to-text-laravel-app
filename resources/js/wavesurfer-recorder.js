import WaveSurfer from 'wavesurfer.js';
import RecordPlugin from 'wavesurfer.js/dist/plugins/record.js';
import * as vad from '@ricky0123/vad-web';

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

    // Function to convert Float32Array to WAV format
    const float32ArrayToWav = (audioData, sampleRate = 16000) => {
        // Create buffer with WAV header
        const buffer = new ArrayBuffer(44 + audioData.length * 2);
        const view = new DataView(buffer);

        // Write WAV header
        // "RIFF" chunk descriptor
        writeString(view, 0, 'RIFF');
        view.setUint32(4, 36 + audioData.length * 2, true);
        writeString(view, 8, 'WAVE');

        // "fmt " sub-chunk
        writeString(view, 12, 'fmt ');
        view.setUint32(16, 16, true); // subchunk1size (16 for PCM)
        view.setUint16(20, 1, true); // audio format (1 for PCM)
        view.setUint16(22, 1, true); // num channels (1 for mono)
        view.setUint32(24, sampleRate, true); // sample rate
        view.setUint32(28, sampleRate * 2, true); // byte rate (sample rate * num channels * bytes per sample)
        view.setUint16(32, 2, true); // block align (num channels * bytes per sample)
        view.setUint16(34, 16, true); // bits per sample

        // "data" sub-chunk
        writeString(view, 36, 'data');
        view.setUint32(40, audioData.length * 2, true); // subchunk2size

        // Write audio data
        floatTo16BitPCM(view, 44, audioData);

        return new Blob([view], { type: 'audio/wav' });
    };

    // Helper function to write a string to a DataView
    const writeString = (view, offset, string) => {
        for (let i = 0; i < string.length; i++) {
            view.setUint8(offset + i, string.charCodeAt(i));
        }
    };

    // Helper function to convert Float32Array to 16-bit PCM
    const floatTo16BitPCM = (output, offset, input) => {
        for (let i = 0; i < input.length; i++, offset += 2) {
            const s = Math.max(-1, Math.min(1, input[i]));
            output.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
        }
    };

    // Function to update the VAD status message
    const updateVADStatus = (message, type = 'info') => {
        const statusEl = document.getElementById('vad-status');
        if (!statusEl) return;

        // Update message and status type
        statusEl.textContent = message;

        // Remove all alert classes and add the new one
        statusEl.className = statusEl.className.replace(/alert-\w+/g, '');
        statusEl.className += ` alert-${type}`;

        // Make sure the element is visible
        statusEl.classList.remove('d-none');
    };

    // Function to create an audio box and add it to the container
    const createAudioBox = (segmentId, audioBlob) => {
        const boxesWrapper = document.getElementById('audio-boxes-wrapper');
        if (!boxesWrapper) return null;

        // Create a column for the audio box
        const col = document.createElement('div');
        col.className = 'col-md-4 mb-3';
        col.dataset.segmentId = segmentId;

        // Create the audio box
        const box = document.createElement('div');
        box.className = 'card';

        // Create card body
        const cardBody = document.createElement('div');
        cardBody.className = 'card-body';

        // Add audio element
        const audio = document.createElement('audio');
        audio.controls = true;
        audio.className = 'w-100 mb-2';
        audio.src = URL.createObjectURL(audioBlob);

        // Add status indicator
        const status = document.createElement('div');
        status.className = 'alert alert-info mb-2';
        status.textContent = 'Processing...';

        // Add transcription container (initially empty)
        const transcription = document.createElement('div');
        transcription.className = 'transcription mt-2';
        transcription.innerHTML = '<em>Waiting for transcription...</em>';

        // Assemble the box
        cardBody.appendChild(audio);
        cardBody.appendChild(status);
        cardBody.appendChild(transcription);
        box.appendChild(cardBody);
        col.appendChild(box);

        // Add to the container
        boxesWrapper.prepend(col); // Add to the beginning so newest is first

        return col;
    };

    // Function to update an audio box with transcription
    const updateAudioBoxWithTranscription = (segmentId, transcriptionText) => {
        const audioBox = document.querySelector(`#audio-boxes-wrapper [data-segment-id="${segmentId}"]`);
        if (!audioBox) return;

        // Update status
        const status = audioBox.querySelector('.alert');
        if (status) {
            status.className = 'alert alert-success mb-2';
            status.textContent = 'Transcription complete';
        }

        // Update transcription
        const transcription = audioBox.querySelector('.transcription');
        if (transcription) {
            transcription.innerHTML = `<strong>Transcription:</strong> ${transcriptionText}`;
        }
    };

    // Function to send audio data to API endpoint
    const sendAudioToAPI = async (audioData) => {
        try {
            // Update status to show we're sending data
            updateVADStatus('Sending audio segment to API...', 'warning');

            // Convert Float32Array to WAV format
            const wavBlob = float32ArrayToWav(audioData);

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

            // Reset status after a delay
            setTimeout(() => {
                updateVADStatus('Listening for speech...', 'info');
            }, 2000);
        } catch (error) {
            console.error('Error sending audio to API:', error);

            // Update status to show error
            updateVADStatus('Error sending audio segment: ' + error.message, 'danger');

            // Reset status after a delay
            setTimeout(() => {
                updateVADStatus('Listening for speech...', 'info');
            }, 3000);
        }
    };

    // Initialize WaveSurfer
    const initWaveSurfer = () => {
        // Create WaveSurfer instance
        const wavesurfer = WaveSurfer.create({
            container: '#waveform',
            waveColor: '#4F4A85',
            progressColor: '#383351',
            height: 100,
            cursorWidth: 1,
            cursorColor: 'lightgray',
            normalize: true,
            minPxPerSec: 100,
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

    // Format time in MM:SS format
    const formatTime = (seconds) => {
        const minutes = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
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
                                updateVADStatus('Listening for speech...', 'info');
                            }, 2000);
                        }
                    });

                    // Start the VAD
                    vadInstance.start();
                    console.log('VAD started successfully');
                    updateVADStatus('Voice activity detection started. Listening for speech...', 'success');

                    // Reset status after a delay
                    setTimeout(() => {
                        updateVADStatus('Listening for speech...', 'info');
                    }, 2000);
                } catch (vadErr) {
                    console.error('Error initializing VAD:', vadErr);
                    updateVADStatus('Error initializing voice activity detection: ' + vadErr.message, 'danger');
                }

                startBtn.classList.add('d-none');
                stopBtn.classList.remove('d-none');
                stopBtn.disabled = false;
                micSelector.disabled = true;
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

                // Hide status after a delay
                setTimeout(() => {
                    const statusEl = document.getElementById('vad-status');
                    if (statusEl) {
                        statusEl.classList.add('d-none');
                    }
                }, 3000);
            }

            // Remove any existing audio element
            const audioEl = document.getElementById('recorded-audio');
            if (audioEl) {
                audioEl.parentNode.removeChild(audioEl);
            }

            startBtn.classList.remove('d-none');
            stopBtn.classList.add('d-none');
            stopBtn.disabled = true;
            micSelector.disabled = false;
        });

        // Update time display
        record.on('record-progress', (duration) => {
            timeDisplay.textContent = formatTime(duration);
        });

        // Handle recording end
        record.on('record-end', (blob) => {
            console.log('Recording finished');
            timeDisplay.textContent = formatTime(record.getDuration());
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
