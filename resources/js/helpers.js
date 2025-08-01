/**
 * Format time in HH:MM:SS format
 * @param {number} seconds - The time in seconds
 * @returns {string} - Formatted time string in HH:MM:SS format
 */

/**
 * Helper function to write a string to a DataView
 * @param {DataView} view - The DataView to write to
 * @param {number} offset - The offset to start writing at
 * @param {string} string - The string to write
 */
export const writeString = (view, offset, string) => {
    for (let i = 0; i < string.length; i++) {
        view.setUint8(offset + i, string.charCodeAt(i));
    }
};

/**
 * Helper function to convert Float32Array to 16-bit PCM
 * @param {DataView} output - The output DataView
 * @param {number} offset - The offset to start writing at
 * @param {Float32Array} input - The input Float32Array
 */
export const floatTo16BitPCM = (output, offset, input) => {
    for (let i = 0; i < input.length; i++, offset += 2) {
        const s = Math.max(-1, Math.min(1, input[i]));
        output.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
    }
};

/**
 * Function to convert Float32Array to WAV format
 * @param {Float32Array} audioData - The audio data to convert
 * @param {number} sampleRate - The sample rate of the audio data (default: 16000)
 * @returns {Blob} - A Blob containing the WAV data
 */
export const float32ArrayToWav = (audioData, sampleRate = 16000) => {
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
