// sw.js - Version avec URLs absolues
const CACHE_NAME = 'batobaye-v1.0.1';
const urlsToCache = [
  'https://batobaye.global-logistique.com/batobaye/',
  'https://batobaye.global-logistique.com/batobaye/index.php',
  'https://batobaye.global-logistique.com/batobaye/login.php',
  'https://batobaye.global-logistique.com/batobaye/employee/dashboard.php',
  'https://batobaye.global-logistique.com/batobaye/employee/pointage.php',
  'https://batobaye.global-logistique.com/batobaye/employee/presences.php',
  'https://batobaye.global-logistique.com/batobaye/employee/aide.php',
  'https://batobaye.global-logistique.com/batobaye/css/employee.css',
  'https://batobaye.global-logistique.com/batobaye/pwa-install.css',
  'https://batobaye.global-logistique.com/batobaye/pwa-install.js',
  'https://batobaye.global-logistique.com/batobaye/manifest.json',
  'https://batobaye.global-logistique.com/batobaye/icons/icon-72x72.png',
  'https://batobaye.global-logistique.com/batobaye/icons/icon-192x192.png',
  'https://batobaye.global-logistique.com/batobaye/icons/icon-512x512.png',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', function(event) {
  console.log('Service Worker installé - Version:', CACHE_NAME);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Cache ouvert, ajout des URLs...');
        return cache.addAll(urlsToCache).catch(error => {
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
                    <title>Batobaye - Hors ligne</title>
                    <meta charset="UTF-8">
                    <style>
                      body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                      .offline { color: #666; margin-top: 50px; }
                    </style>
                  </head>
                  <body>
                    <div class="offline">
                      <h1>📱 Batobaye</h1>
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