/**
 * Service Worker de Finlia (Épica 10 — PWA).
 *
 * Mínimo viable: permite que el navegador ofrezca el banner de
 * "Agregar a pantalla de inicio" (criterio de instalabilidad) sin
 * implementar caché offline compleja (ver epic: "No implementar offline
 * complejo inicialmente").
 *
 * En producción (HTTPS) el SW se registra automáticamente desde app.js.
 * En desarrollo (localhost http) el navegador lo acepta igualmente.
 */
const CACHE_VERSION = 'finlia-v1';

self.addEventListener('install', function (event) {
    // Activa el SW sin esperar a que se cierre la pestaña anterior.
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys
                    .filter(function (key) { return key !== CACHE_VERSION; })
                    .map(function (key) { return caches.delete(key); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

/**
 * Estrategia: network-first (sin cache de contenido).
 *
 * Toda petición va a la red. Si falla (offline), el usuario ve el error
 * nativo del navegador — preferible a responder con datos caducados en
 * una app financiera donde la actualidad es crítica.
 *
 * Cuando en el futuro se implemente un fallback offline (página "sin
 * conexión" estática), se añade aquí el cache de assets estáticos y la
 * página de fallback, sin tocar el resto de la app.
 */
self.addEventListener('fetch', function (event) {
    // Solo interceptamos peticiones de navegación GET al mismo origen.
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith(self.location.origin)) return;

    // Dejamos pasar normalmente (network first implícito).
});
