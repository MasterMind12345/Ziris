// sw.js - Version avec notifications de rappel
const CACHE_NAME = 'ziris-v1.1.0';
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

// Configuration des heures de rappel
const RAPPELS = [
  { heure: '08:00', titre: '🕗 Début de présence', message: 'N\'oubliez pas de pointer votre arrivée !' },
  { heure: '12:00', titre: '🍽️ Début de pause', message: 'Cliquez pour pointer le début de votre pause' },
  { heure: '13:30', titre: '↩️ Fin de pause', message: 'Reprise du travail - pointez votre retour' },
  { heure: '17:30', titre: '🏁 Fin de journée', message: 'Pointez votre départ pour la journée' }
];

// Variables pour gérer les notifications
let notificationsEnvoyees = {};
let intervalCheck;

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
    }).then(() => {
      // Démarrer la vérification des rappels
      initialiserRappels();
    })
  );
  self.clients.claim();
});

// Fonction pour initialiser les rappels
function initialiserRappels() {
  console.log('Initialisation des rappels de pointage...');
  
  // Réinitialiser les notifications envoyées chaque jour
  const aujourdhui = new Date().toDateString();
  if (!notificationsEnvoyees[aujourdhui]) {
    notificationsEnvoyees = { [aujourdhui]: {} };
  }
  
  // Vérifier toutes les minutes si une notification doit être envoyée
  if (intervalCheck) clearInterval(intervalCheck);
  
  intervalCheck = setInterval(() => {
    verifierRappels();
  }, 60000); // Vérifier toutes les minutes
  
  // Vérifier immédiatement au démarrage
  verifierRappels();
}

// Fonction pour vérifier et envoyer les rappels
function verifierRappels() {
  const maintenant = new Date();
  const heureActuelle = maintenant.getHours().toString().padStart(2, '0') + ':' + 
                        maintenant.getMinutes().toString().padStart(2, '0');
  const aujourdhui = maintenant.toDateString();
  
  RAPPELS.forEach(rappel => {
    const cleNotification = aujourdhui + '-' + rappel.heure;
    
    // Vérifier si l'heure actuelle correspond à un rappel
    if (heureActuelle === rappel.heure && 
        !notificationsEnvoyees[aujourdhui]?.[rappel.heure]) {
      
      console.log(`Envoi notification: ${rappel.titre} à ${rappel.heure}`);
      
      // Envoyer la notification
      self.registration.showNotification(rappel.titre, {
        body: rappel.message,
        icon: 'https://ziris.global-logistique.com/icons/icon-192x192.png',
        badge: 'https://ziris.global-logistique.com/icons/icon-72x72.png',
        tag: rappel.heure, // Pour éviter les doublons
        renotify: false,
        vibrate: [200, 100, 200],
        actions: [
          {
            action: 'pointer',
            title: '📱 Pointer maintenant'
          },
          {
            action: 'ignorer',
            title: 'Ignorer'
          }
        ],
        data: {
          url: 'https://ziris.global-logistique.com/employee/pointage.php',
          heure: rappel.heure,
          type: 'rappel-pointage'
        }
      });
      
      // Marquer comme envoyée
      if (!notificationsEnvoyees[aujourdhui]) {
        notificationsEnvoyees[aujourdhui] = {};
      }
      notificationsEnvoyees[aujourdhui][rappel.heure] = true;
      
      // Nettoyer les anciennes entrées (plus de 7 jours)
      nettoyerAnciennesNotifications();
    }
  });
}

// Nettoyer les notifications anciennes
function nettoyerAnciennesNotifications() {
  const aujourdhui = new Date();
  const septJours = 7 * 24 * 60 * 60 * 1000;
  
  Object.keys(notificationsEnvoyees).forEach(dateStr => {
    const date = new Date(dateStr);
    if (aujourdhui - date > septJours) {
      delete notificationsEnvoyees[dateStr];
    }
  });
}

// Gérer les clics sur les notifications
self.addEventListener('notificationclick', function(event) {
  console.log('Notification cliquée:', event.notification.tag);
  event.notification.close();
  
  if (event.action === 'pointer') {
    // Ouvrir la page de pointage
    event.waitUntil(
      clients.matchAll({type: 'window'}).then(windowClients => {
        // Vérifier si une fenêtre est déjà ouverte
        for (let client of windowClients) {
          if (client.url.includes('pointage.php') && 'focus' in client) {
            return client.focus();
          }
        }
        // Sinon ouvrir une nouvelle fenêtre
        if (clients.openWindow) {
          return clients.openWindow('https://ziris.global-logistique.com/employee/pointage.php');
        }
      })
    );
  } else if (event.action === 'ignorer') {
    // Rien à faire, juste fermer
    console.log('Notification ignorée');
  } else {
    // Clic sur le corps de la notification
    event.waitUntil(
      clients.openWindow('https://ziris.global-logistique.com/employee/dashboard.php')
    );
  }
});

// Gérer la fermeture des notifications
self.addEventListener('notificationclose', function(event) {
  console.log('Notification fermée:', event.notification.tag);
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
  
  // Message pour demander l'envoi d'une notification de test
  if (event.data && event.data.type === 'TEST_NOTIFICATION') {
    self.registration.showNotification('Test Ziris', {
      body: 'Ceci est une notification de test',
      icon: 'https://ziris.global-logistique.com/icons/icon-192x192.png'
    });
  }
  
  // Message pour activer/désactiver les rappels
  if (event.data && event.data.type === 'TOGGLE_REMINDERS') {
    if (event.data.enabled) {
      initialiserRappels();
    } else {
      if (intervalCheck) {
        clearInterval(intervalCheck);
        intervalCheck = null;
      }
    }
  }
});