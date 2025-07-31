/**
 * Main application logic for the audio transcription app
 */

import { setupWebSocketListener } from './websocket';
import { createVADController } from './vad';
import { updateVADStatus, createAudioBox, updateAudioBoxWithTranscription } from './ui';
import { initWaveSurfer, checkMicrophoneAvailability, sendAudioToAPI } from './recorder';

/**
 * Initializes the audio transcription application
 */
export const initAudioTranscriber = async () => {
    // Initialize WaveSurfer and Record plugin
    const { wavesurfer, record } = initWaveSurfer('#waveform');

    // Check if a microphone is available
    const hasMicrophone = await checkMicrophoneAvailability(record);
    if (!hasMicrophone) {
        alert('No microphone was found. Please connect a microphone and try again.');
    }

    // Variable to store the VAD controller
    let vadController = null;

    // Set up WebSocket listener for transcription results
    setupWebSocketListener(updateAudioBoxWithTranscription);

    // Setup event handlers
    const setupEventHandlers = () => {
        const startBtn = document.getElementById('record-start');
        const stopBtn = document.getElementById('record-stop');
        const timeDisplay = document.getElementById('record-time');

        if (!startBtn || !stopBtn || !timeDisplay) return;

        // Start recording
        startBtn.addEventListener('click', async () => {
            try {
                // Use default microphone constraints
                const constraints = {};

                await record.startRecording(constraints);

                // Initialize VAD for pause detection
                try {
                    console.log('Initializing VAD for pause detection...');

                    // Create VAD controller with callbacks
                    vadController = createVADController({
                        updateStatus: updateVADStatus,
                        onSpeechEnd: (audio) => {
                            console.log('Speech pause detected, sending audio segment...');
                            updateVADStatus('Pause detected! Processing segment...', 'warning');

                            // Send the audio segment to the API endpoint
                            sendAudioToAPI(audio, updateVADStatus, createAudioBox);

                            // Automatically stop recording after sending the segment
                            record.stopRecording();

                            // Clean up VAD instance
                            if (vadController) {
                                vadController.cleanup();
                                vadController = null;
                                console.log('VAD stopped automatically after pause detection');

                                // Update UI to show recording has stopped
                                startBtn.classList.remove('hidden');
                                stopBtn.classList.add('hidden');
                                stopBtn.disabled = true;

                                // Update status
                                updateVADStatus('Recording stopped automatically after pause detection. Click Start Transcription to record again.', 'info');

                                // Hide status after 2 seconds
                                setTimeout(() => {
                                    updateVADStatus(null);
                                }, 2000);
                            }
                        }
                    });

                    // Initialize the VAD
                    const success = await vadController.initialize();

                    if (!success) {
                        throw new Error('Failed to initialize VAD');
                    }

                    startBtn.classList.add('hidden');
                    stopBtn.classList.remove('hidden');
                    stopBtn.disabled = false;
                } catch (vadErr) {
                    console.error('Error initializing VAD:', vadErr);
                    updateVADStatus('Error initializing voice activity detection: ' + vadErr.message, 'danger');

                    // Hide status after 2 seconds
                    setTimeout(() => {
                        updateVADStatus(null);
                    }, 2000);
                }
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

            // Clean up VAD controller if it exists
            if (vadController) {
                vadController.cleanup();
                vadController = null;
            }

            // Remove any existing audio element
            const audioEl = document.getElementById('recorded-audio');
            if (audioEl) {
                audioEl.parentNode.removeChild(audioEl);
            }

            startBtn.classList.remove('hidden');
            stopBtn.classList.add('hidden');
            stopBtn.disabled = true;
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
        });
    };

    // Set up event handlers
    setupEventHandlers();

    console.log('Audio transcriber initialized');
};
