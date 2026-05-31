import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const configuredReverbHost = import.meta.env.VITE_REVERB_HOST;
const shouldUseCurrentHost = ! configuredReverbHost
    || (
        configuredReverbHost.endsWith('.test')
        && configuredReverbHost !== window.location.hostname
    );

const reverbScheme = import.meta.env.VITE_REVERB_SCHEME
    ?? (window.location.protocol === 'https:' ? 'https' : 'http');
const isSecureReverb = reverbScheme === 'https';
const reverbHost = shouldUseCurrentHost ? window.location.hostname : configuredReverbHost;
const reverbPort = shouldUseCurrentHost
    ? (isSecureReverb ? 443 : 80)
    : (import.meta.env.VITE_REVERB_PORT ?? (isSecureReverb ? 443 : 80));

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: isSecureReverb,
    enabledTransports: ['ws'],
});
