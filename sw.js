// sw.js
const CACHE_NAME = 'batobaye-v1.0.0';
const urlsToCache = [
  '/batobaye/',
  '/batobaye/index.php',
  '/batobaye/login.php',
  '/batobaye/employee/dashboard.php',
  '/batobaye/employee/pointage.php', 
  '/batobaye/employee/presences.php',
  '/batobaye/employee/aide.php',
  '/batobaye/css/employee.css',
  '/batobaye/pwa-install.css',
  '/batobaye/pwa-install.js',
  '/batobaye/manifest.json',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        if (response) {
          return response;
        }
        return fetch(event.request);
      }
    )
  );
});