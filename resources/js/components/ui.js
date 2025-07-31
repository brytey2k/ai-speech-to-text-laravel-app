/**
 * UI-related functions for the audio transcription application
 */

/**
 * Updates the VAD status message
 * @param {string|null} message - The message to display (null to hide)
 * @param {string} type - The type of message ('info', 'success', 'warning', 'danger', 'primary')
 */
export const updateVADStatus = (message, type = 'info') => {
    const statusEl = document.getElementById('vad-status');
    if (!statusEl) return;

    // If message is null, hide the status element
    if (message === null) {
        statusEl.classList.add('hidden');
        return;
    }

    // Update message
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

/**
 * Creates an audio box and adds it to the container
 * @param {string} segmentId - The ID of the audio segment
 * @param {Blob} audioBlob - The audio blob to display
 * @returns {HTMLElement|null} - The created audio box element or null if creation failed
 */
export const createAudioBox = (segmentId, audioBlob) => {
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
        updateLayoutForTranscripts();
    }

    return col;
};

/**
 * Updates an audio box with transcription text
 * @param {string} segmentId - The ID of the audio segment
 * @param {string} transcriptionText - The transcription text
 */
export const updateAudioBoxWithTranscription = (segmentId, transcriptionText) => {
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

/**
 * Updates the layout when transcripts are available
 */
export const updateLayoutForTranscripts = () => {
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
};
