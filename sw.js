const CACHE_NAME = 'picoyplaca-v2'; // Incrementamos versión para forzar actualización
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/styles.css?v=6.2',
  '/manifest.json',
  '/favicons/favicon-32x32.png',
  '/favicons/favicon-192x192.png',
  '/favicons/favicon-512x512.png'
];

// 1. Instalación: Guardar archivos estáticos en caché
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Caching assets');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  // Forzar al SW a activarse inmediatamente
  self.skipWaiting();
});

// 2. Activación: Limpiar cachés antiguas
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keyList) => {
      return Promise.all(
        keyList.map((key) => {
          if (key !== CACHE_NAME) {
            console.log('[Service Worker] Removing old cache', key);
            return caches.delete(key);
          }
        })
      );
    })
  );
  // Tomar control de los clientes inmediatamente
  self.clients.claim();
});

// 3. Fetch: Estrategia "Network First, falling back to Cache" para contenido dinámico
// Esto es mejor para tu app porque los datos del Pico y Placa cambian.
self.addEventListener('fetch', (event) => {
  // Solo interceptar peticiones GET
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // Si la red responde bien, actualizamos la caché y devolvemos el dato fresco
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
                cache.put(event.request, responseToCache);
            });
        }
        return networkResponse;
      })
      .catch(() => {
        // Si la red falla (Offline), servimos desde caché
        console.log('[Service Worker] Network fail, serving cache');
        return caches.match(event.request);
      })
  );
});