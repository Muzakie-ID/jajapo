import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} | Jajapo` : 'Jajapo'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        return pages[`./Pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2F7A3E',
    },
});

// Kunci zoom di HP. Meta viewport sudah melarang zoom, tapi Safari iPhone tetap mengizinkan
// cubit, jadi gerakan cubit dan dua jari dicegah di sini.
const blockZoom = (e) => e.preventDefault();
document.addEventListener('gesturestart', blockZoom, { passive: false });
document.addEventListener('gesturechange', blockZoom, { passive: false });
document.addEventListener(
    'touchmove',
    (e) => {
        if (e.touches.length > 1) e.preventDefault();
    },
    { passive: false },
);

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
