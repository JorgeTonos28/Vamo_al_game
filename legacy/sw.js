// THE G.O.A.T Liga — Service Worker
const CACHE_NAME = 'goat-liga-v' + Date.now();
const CACHE_STATIC = 'goat-static-v1';

// Files to cache
const FILES_TO_CACHE = [
  '/',
  '/index.html',
  '/vote.html',
];

// Install — cache files
self.addEventListener('install', event => {
  self.skipWaiting(); // Activate immediately
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(FILES_TO_CACHE))
  );
});

// Activate — delete old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(key => key !== CACHE_NAME && key !== CACHE_STATIC)
          .map(key => caches.delete(key))
      )
    ).then(() => self.clients.claim()) // Take control immediately
  );
});

// Fetch — network first, fallback to cache
self.addEventListener('fetch', event => {
  // Only handle GET requests
  if(event.request.method !== 'GET') {
return;
}
  
  // Skip Firebase and external requests
  const url = new URL(event.request.url);

  if(url.hostname !== self.location.hostname) {
return;
}

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Save fresh copy to cache
        if(response.ok){
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }

        return response;
      })
      .catch(() => {
        // Network failed — use cache
        return caches.match(event.request);
      })
  );
});

// Listen for skip waiting message
self.addEventListener('message', event => {
  if(event.data === 'skipWaiting') {
self.skipWaiting();
}
});
