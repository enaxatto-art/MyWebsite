// Service Worker for PWA
const CACHE_NAME = 'a-tech-portal-v1';
const urlsToCache = [
  '/taangi/',
  '/taangi/assets/css/style.css',
  '/taangi/assets/css/mobile-app.css',
  '/taangi/assets/js/main.js',
  '/taangi/assets/js/mobile-app.js',
  '/taangi/manifest.json'
];

// Install Service Worker
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch from Cache
self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // Return cached version or fetch from network
        return response || fetch(event.request);
      })
  );
});

// Activate Service Worker
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

