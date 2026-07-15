import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || (window.reverbConfig ? window.reverbConfig.key : null),
    wsHost: import.meta.env.VITE_REVERB_HOST || (window.reverbConfig ? window.reverbConfig.host : window.location.hostname),
    wsPort: import.meta.env.VITE_REVERB_PORT ?? (window.reverbConfig ? window.reverbConfig.port : 80),
    wssPort: import.meta.env.VITE_REVERB_PORT ?? (window.reverbConfig ? window.reverbConfig.port : 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? (window.reverbConfig ? window.reverbConfig.scheme : 'https')) === 'https',
    enabledTransports: ['ws', 'wss'],
    wsPath: '',
});
