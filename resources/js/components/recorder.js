/**
 * Audio recording functionality for the transcription application
 */

/**
 * Initializes WaveSurfer and its Record plugin
 * @param {string} container - The container selector for WaveSurfer
 * @returns {Object} - Object containing wavesurfer and record instances
 */
export const initWaveSurfer = (container = '#waveform') => {
    // Create WaveSurfer instance
    const wavesurfer = WaveSurfer.create({
        container: container,
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

/**
 * Checks if a microphone is available
 * @param {Object} record - The WaveSurfer Record plugin instance
 * @returns {Promise<boolean>} - True if a microphone is available, false otherwise
 */
export const checkMicrophoneAvailability = async (record) => {

    try {
        // Try to get available audio devices
        let devices = [];
        try {
            devices = await RecordPlugin.getAvailableAudioDevices();
        } catch (err) {
            console.warn('Could not enumerate audio devices, trying to get microphone access first:', err);
            // Try to get microphone access first, which might help with device enumeration
            try {
                await record.startMic();
                record.stopMic();
                // Try again after getting permission
                devices = await RecordPlugin.getAvailableAudioDevices();
            } catch (micErr) {
                console.error('Error accessing microphone:', micErr);
                return false;
            }
        }

        // Check if there are any audio input devices
        const hasAudioInputs = devices.some(device => device.kind === 'audioinput');

        // If no devices found or no labels available, request microphone access
        if (!hasAudioInputs || devices.some(device => !device.label)) {
            try {
                await record.startMic();
                record.stopMic();
                // Try again after getting permission
                devices = await RecordPlugin.getAvailableAudioDevices();
                return devices.some(device => device.kind === 'audioinput');
            } catch (err) {
                console.error('Error accessing microphone:', err);
                return false;
            }
        }

        return hasAudioInputs;
    } catch (err) {
        console.error('Error checking microphone availability:', err);
        return false;
    }
};

/**
 * Sends audio data to the API endpoint
 * @param {Float32Array} audioData - The audio data to send
 * @param {Function} updateStatus - Function to update status in UI
 * @param {Function} createAudioBox - Function to create audio box in UI
 * @returns {Promise<Object|null>} - Response data or null if error
 */
export const sendAudioToAPI = async (audioData, updateStatus, createAudioBox) => {
    try {
        // Update status to show we're sending data
        updateStatus('Sending audio segment to API...', 'warning');

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
        updateStatus('Audio segment sent successfully!', 'success');

        // Hide status after 2 seconds
        setTimeout(() => {
            updateStatus(null);
        }, 2000);

        return response.data;
    } catch (error) {
        console.error('Error sending audio to API:', error);

        // Update status to show error
        updateStatus('Error sending audio segment: ' + error.message, 'danger');

        // Hide status after 2 seconds
        setTimeout(() => {
            updateStatus(null);
        }, 2000);

        return null;
    }
};
