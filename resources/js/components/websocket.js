/**
 * WebSocket functionality for handling transcription results
 */

/**
 * Sets up WebSocket listener for transcription results
 * @param {Function} updateCallback - Callback function to update UI with transcription
 */
export const setupWebSocketListener = (updateCallback) => {
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
                updateCallback(event.segmentId, event.transcription);
            }
        });

    console.log('WebSocket listener for transcription results set up');
};
