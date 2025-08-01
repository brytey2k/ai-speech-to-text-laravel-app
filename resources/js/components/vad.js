/**
 * Voice Activity Detection (VAD) functionality
 */

// Constants
const MAX_CHUNK_DURATION = import.meta.env.VITE_MAX_CHUNK_DURATION ? parseInt(import.meta.env.VITE_MAX_CHUNK_DURATION) : 15000; // Default: 15 seconds in milliseconds

/**
 * Creates and initializes a Voice Activity Detection instance
 * @param {Object} options - Configuration options
 * @param {Function} options.onSpeechStart - Callback when speech starts
 * @param {Function} options.onSpeechEnd - Callback when speech ends
 * @param {Function} options.onFrameProcessed - Callback when a frame is processed
 * @param {Function} options.onVADMisfire - Callback when VAD misfires
 * @param {Function} options.updateStatus - Function to update VAD status in UI
 * @returns {Object} - VAD controller with methods to manage the VAD instance
 */
export const createVADController = (options) => {
    // Variable to store the VAD instance
    let vadInstance = null;

    // Variables for speech chunking
    let isSpeechActive = false;
    let speechStartTime = 0;
    let chunkingTimer = null;
    let isCollectingAudioFrames = false;
    let currentAudioFrames = [];

    // Function to check speech duration and force chunking if needed
    const checkSpeechDuration = () => {
        if (!isSpeechActive || !vadInstance) return;

        const currentTime = Date.now();
        const speechDuration = currentTime - speechStartTime;

        if (speechDuration >= MAX_CHUNK_DURATION) {
            console.log(`Speech duration reached ${MAX_CHUNK_DURATION}ms, forcing chunk...`);
            options.updateStatus(`Max duration (${MAX_CHUNK_DURATION/1000}s) reached. Chunking audio...`, 'warning');

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
                options.onSpeechEnd(combinedAudio);

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
                    options.updateStatus('Continuing to listen...', 'info');
                }
            }, 100);
        }
    };

    // Initialize VAD
    const initialize = async () => {
        try {
            console.log('Initializing VAD for pause detection...');
            options.updateStatus('Initializing voice activity detection...', 'info');

            vadInstance = await window.vad.MicVAD.new({
                onFrameProcessed: (probabilities, frame) => {
                    // If speech is active, collect audio frames
                    if (isSpeechActive && isCollectingAudioFrames) {
                        currentAudioFrames.push(frame);
                    }

                    if (options.onFrameProcessed) {
                        options.onFrameProcessed(probabilities, frame);
                    }
                },
                onSpeechStart: () => {
                    console.log('Speech started');
                    options.updateStatus('Speech detected! Recording...', 'primary');

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

                    console.log(`Speech tracking started, will chunk at ${MAX_CHUNK_DURATION/1000} second(s) if needed`);

                    if (options.onSpeechStart) {
                        options.onSpeechStart();
                    }
                },
                onSpeechEnd: (audio) => {
                    console.log('Speech pause detected, sending audio segment...');
                    options.updateStatus('Pause detected! Processing segment...', 'warning');

                    // Reset speech tracking variables
                    isSpeechActive = false;
                    isCollectingAudioFrames = false;
                    currentAudioFrames = [];

                    // Clear the chunking timer
                    if (chunkingTimer) {
                        clearInterval(chunkingTimer);
                        chunkingTimer = null;
                    }

                    // Call the onSpeechEnd callback with the audio data
                    if (options.onSpeechEnd) {
                        options.onSpeechEnd(audio);
                    }
                },
                onVADMisfire: () => {
                    console.log('VAD misfire detected');
                    options.updateStatus('VAD misfire detected. Continuing to listen...', 'warning');

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
                            options.updateStatus('Listening for speech...', 'info');
                        } else {
                            options.updateStatus(null); // Hide status
                        }
                    }, 2000);

                    if (options.onVADMisfire) {
                        options.onVADMisfire();
                    }
                }
            });

            // Start the VAD
            vadInstance.start();
            console.log('VAD started successfully');
            options.updateStatus('Voice activity detection started. Listening for speech...', 'success');

            // Reset status after a delay
            setTimeout(() => {
                if (vadInstance) {
                    options.updateStatus('Listening for speech...', 'info');
                } else {
                    options.updateStatus(null); // Hide status
                }
            }, 2000);

            return true;
        } catch (error) {
            console.error('Error initializing VAD:', error);
            options.updateStatus('Error initializing voice activity detection: ' + error.message, 'danger');

            // Hide status after 2 seconds
            setTimeout(() => {
                options.updateStatus(null); // Hide status
            }, 2000);

            return false;
        }
    };

    // Clean up VAD resources
    const cleanup = () => {
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

        if (vadInstance) {
            vadInstance.destroy();
            vadInstance = null;
            console.log('VAD stopped');
        }

        options.updateStatus('Recording stopped', 'danger');

        // Hide status after 2 seconds
        setTimeout(() => {
            options.updateStatus(null); // Hide status
        }, 2000);
    };

    // Return controller object with public methods
    return {
        initialize,
        cleanup,
        isActive: () => !!vadInstance
    };
};
