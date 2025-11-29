// sw.js - Version avec URLs absolues pour Ziris
const CACHE_NAME = 'ziris-v1.0.1';
const urlsToCache = [
  'https://ziris.global-logistique.com/',
  'https://ziris.global-logistique.com/index.php',
  'https://ziris.global-logistique.com/login.php',
  'https://ziris.global-logistique.com/employee/dashboard.php',
  'https://ziris.global-logistique.com/employee/pointage.php',
  'https://ziris.global-logistique.com/employee/presences.php',
  'https://ziris.global-logistique.com/employee/aide.php',
  'https://ziris.global-logistique.com/css/employee.css',
  'https://ziris.global-logistique.com/pwa-install.css',
  'https://ziris.global-logistique.com/pwa-install.js',
  'https://ziris.global-logistique.com/manifest.json',
  'https://ziris.global-logistique.com/icons/icon-72x72.png',
  'https://ziris.global-logistique.com/icons/icon-192x192.png',
  'https://ziris.global-logistique.com/icons/icon-512x512.png',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', function(event) {
  console.log('Service Worker installé - Version:', CACHE_NAME);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Cache ouvert, ajout des URLs...');
        return cache.addAll(urlsToCache).catch(error => {g
          console.log('Erreur cache.addAll:', error);
        });
      })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  console.log('Service Worker activé');
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('Suppression ancien cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function(event) {
  // Ignorer les requêtes non-GET et les requêtes chrome-extension
  if (event.request.method !== 'GET' || event.request.url.startsWith('chrome-extension://')) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // Retourner la réponse en cache si disponible
        if (response) {
          return response;
        }

        // Sinon, faire la requête réseau
        return fetch(event.request)
          .then(function(networkResponse) {
            // Mettre en cache la nouvelle ressource
            if (networkResponse && networkResponse.status === 200) {
              const responseToCache = networkResponse.clone();
              caches.open(CACHE_NAME)
                .then(function(cache) {
                  cache.put(event.request, responseToCache);
                });
            }
            return networkResponse;
          })
          .catch(function(error) {
            console.log('Échec fetch, retour page offline:', error);
            // Retourner une page offline basique si en échec
            if (event.request.destination === 'document') {
              return new Response(
                `
                <!DOCTYPE html>
                <html>
                  <head>
                    <title>Ziris - Hors ligne</title>
                    <meta charset="UTF-8">
                    <style>
                      body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                      .offline { color: #666; margin-top: 50px; }
                    </style>
                  </head>
                  <body>
                    <div class="offline">
                      <h1>📱 Ziris</h1>
                      <p>Vous êtes actuellement hors ligne</p>
                      <p>Certaines fonctionnalités ne sont pas disponibles</p>
                    </div>
                  </body>
                </html>
                `,
                { headers: { 'Content-Type': 'text/html' } }
              );
            }
          });
      })
  );
});

// Gérer les messages depuis la page
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});