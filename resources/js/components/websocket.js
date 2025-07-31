/**
 * WebSocket functionality for handling transcription results
 */

/**
 * Sets up WebSocket listener for transcription results
 * @param {Object} callbacks - Object containing callback functions
 * @param {Function} callbacks.updateCallback - Callback function to update UI with transcription
 * @param {Function} callbacks.inProgressCallback - Callback function to update UI with in-progress status
 * @param {Function} callbacks.failedCallback - Callback function to update UI with failed status
 */
export const setupWebSocketListener = ({ updateCallback, inProgressCallback, failedCallback }) => {
    // Check if Echo is available
    if (typeof window.Echo === 'undefined') {
        console.error('Laravel Echo is not available');
        return;
    }

    // Listen for the transcription.completed event on the public channel
    window.Echo.channel('public')
        .listen('.TranscriptionCompleted', (event) => {
            console.log('Received transcription completed event:', event);

            if (event.segmentId && event.transcription) {
                // Update the corresponding audio box with the transcription
                updateCallback(event.segmentId, event.transcription);
            }
        })
        .listen('.TranscriptionInProgress', (event) => {
            console.log('Received transcription in progress event:', event);

            if (event.segmentId) {
                // Update the corresponding audio box with in-progress status
                inProgressCallback(event.segmentId);
            }
        })
        .listen('.TranscriptionFailed', (event) => {
            console.log('Received transcription failed event:', event);

            if (event.segmentId) {
                // Update the corresponding audio box with failed status
                failedCallback(event.segmentId);
            }
        });

    console.log('WebSocket listeners for transcription events set up');
};
