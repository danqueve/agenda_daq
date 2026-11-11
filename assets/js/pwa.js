(() => {
    // La página carga este script con ?v=APP_VERSION; el service worker usa la misma versión para nombrar su caché.
    const version = new URL(document.currentScript.src).searchParams.get('v') || '';
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
        window.addEventListener('load', () => navigator.serviceWorker.register(`sw.js?v=${encodeURIComponent(version)}`).catch(() => {}));
    }
})();
