import './bootstrap';

import WaveSurfer from 'wavesurfer.js';
import RecordPlugin from 'wavesurfer.js/dist/plugins/record.js';
import * as vad from '@ricky0123/vad-web';
import * as helpers from './helpers';
import { initAudioTranscriber } from './components/main';

// Create a global AudioHelpers object with all helper functions
window.AudioHelpers = helpers;

// Make libraries globally available
window.WaveSurfer = WaveSurfer;
window.RecordPlugin = RecordPlugin;
window.vad = vad;

window.initAudioTranscriberLib = initAudioTranscriber;
