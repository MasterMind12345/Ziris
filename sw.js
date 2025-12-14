// sw.js - Version corrigée avec notifications de rappel
const CACHE_NAME = 'ziris-v2.0.0';
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

const RAPPELS = [
  { heure: '08:00', titre: '🕗 Début de présence', message: 'N\'oubliez pas de pointer votre arrivée !' },
  { heure: '12:00', titre: '🍽️ Début de pause', message: 'Cliquez pour pointer le début de votre pause' },
  { heure: '13:30', titre: '↩️ Fin de pause', message: 'Reprise du travail - pointez votre retour' },
  { heure: '17:30', titre: '🏁 Fin de journée', message: 'Pointez votre départ pour la journée' }
];

let notificationsEnvoyees = {};
let intervalCheck;

// Installation du Service Worker
self.addEventListener('install', function(event) {
  console.log('[Service Worker] Installation - Version:', CACHE_NAME);
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('[Service Worker] Mise en cache des ressources');
        return cache.addAll(urlsToCache).catch(error => {
          console.error('[Service Worker] Erreur lors de la mise en cache:', error);
        });
      })
      .then(function() {
        console.log('[Service Worker] Installation terminée');
        return self.skipWaiting();
      })
  );
});

// Activation du Service Worker
self.addEventListener('activate', function(event) {
  console.log('[Service Worker] Activation');
  
  event.waitUntil(
    Promise.all([
      // Nettoyer les anciens caches
      caches.keys().then(function(cacheNames) {
        return Promise.all(
          cacheNames.map(function(cacheName) {
            if (cacheName !== CACHE_NAME) {
              console.log('[Service Worker] Suppression ancien cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      }),
      // Prendre le contrôle immédiatement
      self.clients.claim()
    ]).then(function() {
      console.log('[Service Worker] Activation terminée');
      initialiserRappels();
    })
  );
});

// Initialisation des rappels
function initialiserRappels() {
  console.log('[Service Worker] Initialisation des rappels de pointage');
  
  const aujourdhui = new Date().toDateString();
  if (!notificationsEnvoyees[aujourdhui]) {
    notificationsEnvoyees = { [aujourdhui]: {} };
  }
  
  // Arrêter l'intervalle précédent s'il existe
  if (intervalCheck) {
    clearInterval(intervalCheck);
  }
  
  // Vérifier toutes les minutes
  intervalCheck = setInterval(() => {
    verifierRappels();
  }, 60000);
  
  // Vérifier immédiatement
  verifierRappels();
}

// Vérification des rappels
function verifierRappels() {
  const maintenant = new Date();
  const heureActuelle = maintenant.getHours().toString().padStart(2, '0') + ':' + 
                        maintenant.getMinutes().toString().padStart(2, '0');
  const aujourdhui = maintenant.toDateString();
  
  console.log('[Service Worker] Vérification rappels à:', heureActuelle);
  
  RAPPELS.forEach(rappel => {
    const cleNotification = aujourdhui + '-' + rappel.heure;
    
    if (heureActuelle === rappel.heure && 
        !notificationsEnvoyees[aujourdhui]?.[rappel.heure]) {
      
      console.log(`[Service Worker] Envoi notification: ${rappel.titre} à ${rappel.heure}`);
      
      self.registration.showNotification(rappel.titre, {
        body: rappel.message,
        icon: 'https://ziris.global-logistique.com/icons/icon-192x192.png',
        badge: 'https://ziris.global-logistique.com/icons/icon-72x72.png',
        tag: rappel.heure, // Pour éviter les doublons
        renotify: false,
        vibrate: [200, 100, 200],
        requireInteraction: true,
        actions: [
          {
            action: 'pointer',
            title: '📱 Pointer'
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
      }).then(() => {
        console.log('[Service Worker] Notification envoyée avec succès');
        
        if (!notificationsEnvoyees[aujourdhui]) {
          notificationsEnvoyees[aujourdhui] = {};
        }
        notificationsEnvoyees[aujourdhui][rappel.heure] = true;
        
        nettoyerAnciennesNotifications();
      }).catch(error => {
        console.error('[Service Worker] Erreur lors de l\'envoi de la notification:', error);
      });
    }
  });
}

// Nettoyage des anciennes notifications
function nettoyerAnciennesNotifications() {
  const aujourdhui = new Date();
  const troisJours = 3 * 24 * 60 * 60 * 1000;
  
  Object.keys(notificationsEnvoyees).forEach(dateStr => {
    const date = new Date(dateStr);
    if (aujourdhui - date > troisJours) {
      delete notificationsEnvoyees[dateStr];
    }
  });
}

// Gestion des clics sur les notifications
self.addEventListener('notificationclick', function(event) {
  console.log('[Service Worker] Notification cliquée:', event.notification.tag);
  event.notification.close();
  
  const urlToOpen = 'https://ziris.global-logistique.com/employee/pointage.php';
  
  if (event.action === 'pointer') {
    event.waitUntil(
      clients.matchAll({
        type: 'window',
        includeUncontrolled: true
      }).then(function(windowClients) {
        // Chercher une fenêtre ouverte sur l'application
        for (let client of windowClients) {
          if (client.url.includes('ziris.global-logistique.com') && 'focus' in client) {
            return client.focus().then(() => {
              client.postMessage({
                type: 'NAVIGATE_TO_POINTAGE',
                time: new Date().toISOString()
              });
            });
          }
        }
        // Ouvrir une nouvelle fenêtre
        return clients.openWindow(urlToOpen);
      })
    );
  } else if (event.action === 'ignorer') {
    console.log('[Service Worker] Notification ignorée');
  } else {
    // Clic sur le corps de la notification
    event.waitUntil(
      clients.openWindow(urlToOpen)
    );
  }
});

// Gestion de la fermeture des notifications
self.addEventListener('notificationclose', function(event) {
  console.log('[Service Worker] Notification fermée:', event.notification.tag);
});

// Gestion des requêtes réseau (cache) - CORRIGÉ
self.addEventListener('fetch', function(event) {
  // Ignorer les requêtes non-GET et certaines extensions
  if (event.request.method !== 'GET') {
    return;
  }
  
  // 🔥 CORRECTION CRITIQUE : Jamais mettre en cache la page de pointage
  if (event.request.url.includes('/employee/pointage.php')) {
    console.log('[Service Worker] 🔥 Bypass cache pour pointage.php - Toujours réseau');
    event.respondWith(fetch(event.request));
    return;
  }
  
  // Pour les requêtes API, toujours aller au réseau
  if (event.request.url.includes('/api/') || event.request.url.includes('?ajax=')) {
    return;
  }
  
  // Pour les autres pages dynamiques, éviter le cache si elles contiennent des paramètres
  if (event.request.url.includes('?') || event.request.url.includes('success=') || event.request.url.includes('t=')) {
    console.log('[Service Worker] URL avec paramètres - réseau uniquement');
    event.respondWith(fetch(event.request));
    return;
  }
  
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // Retourner depuis le cache si disponible
        if (response) {
          console.log('[Service Worker] ✅ Servi depuis le cache:', event.request.url);
          return response;
        }
        
        // Sinon aller au réseau
        console.log('[Service Worker] 📡 Aller au réseau pour:', event.request.url);
        return fetch(event.request)
          .then(function(networkResponse) {
            // Mettre en cache si réussite (sauf pour pointage.php qui est déjà exclue)
            if (networkResponse && networkResponse.status === 200) {
              const responseToCache = networkResponse.clone();
              caches.open(CACHE_NAME)
                .then(function(cache) {
                  cache.put(event.request, responseToCache);
                  console.log('[Service Worker] 💾 Mis en cache:', event.request.url);
                });
            }
            return networkResponse;
          })
          .catch(function(error) {
            console.log('[Service Worker] Hors ligne, retour page offline:', error);
            
            // Pour les pages HTML, retourner une page offline
            if (event.request.headers.get('accept')?.includes('text/html')) {
              return new Response(
                `
                <!DOCTYPE html>
                <html>
                  <head>
                    <title>Ziris - Hors ligne</title>
                    <meta charset="UTF-8">
                    <style>
                      body { 
                        font-family: Arial, sans-serif; 
                        padding: 40px; 
                        text-align: center; 
                        background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
                        color: white;
                        min-height: 100vh;
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                      }
                      .offline { 
                        background: rgba(255, 255, 255, 0.1);
                        backdrop-filter: blur(10px);
                        padding: 40px;
                        border-radius: 20px;
                        max-width: 500px;
                        width: 90%;
                      }
                      h1 { font-size: 2.5em; margin-bottom: 20px; }
                      p { font-size: 1.2em; margin-bottom: 15px; opacity: 0.9; }
                      .icon { font-size: 4em; margin-bottom: 20px; }
                      .btn {
                        background: white;
                        color: #4361ee;
                        border: none;
                        padding: 12px 30px;
                        border-radius: 50px;
                        font-weight: bold;
                        cursor: pointer;
                        margin-top: 20px;
                        text-decoration: none;
                        display: inline-block;
                      }
                    </style>
                  </head>
                  <body>
                    <div class="offline">
                      <div class="icon">📱</div>
                      <h1>Ziris</h1>
                      <p>Vous êtes actuellement hors ligne</p>
                      <p>Les fonctionnalités de pointage ne sont pas disponibles</p>
                      <p>Reconnectez-vous pour accéder à toutes les fonctionnalités</p>
                      <button class="btn" onclick="location.reload()">Réessayer</button>
                    </div>
                  </body>
                </html>
                `,
                { 
                  headers: { 
                    'Content-Type': 'text/html',
                    'Cache-Control': 'no-cache'
                  } 
                }
              );
            }
            
            // Pour les autres types de ressources, laisser échouer
            throw error;
          });
      })
  );
});

// Gestion des messages depuis la page web
self.addEventListener('message', function(event) {
  console.log('[Service Worker] Message reçu:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'TEST_NOTIFICATION') {
    self.registration.showNotification('Test Ziris', {
      body: 'Ceci est une notification de test',
      icon: 'https://ziris.global-logistique.com/icons/icon-192x192.png',
      badge: 'https://ziris.global-logistique.com/icons/icon-72x72.png',
      tag: 'test-' + Date.now()
    }).then(() => {
      event.ports[0]?.postMessage({ success: true });
    });
  }
  
  if (event.data && event.data.type === 'TOGGLE_REMINDERS') {
    if (event.data.enabled) {
      console.log('[Service Worker] Activation des rappels');
      initialiserRappels();
      event.ports[0]?.postMessage({ success: true, message: 'Rappels activés' });
    } else {
      console.log('[Service Worker] Désactivation des rappels');
      if (intervalCheck) {
        clearInterval(intervalCheck);
        intervalCheck = null;
      }
      event.ports[0]?.postMessage({ success: true, message: 'Rappels désactivés' });
    }
  }
  
  if (event.data && event.data.type === 'CHECK_NOTIFICATIONS') {
    event.ports[0]?.postMessage({
      notificationsEnabled: intervalCheck !== null,
      nextCheck: intervalCheck ? 'Actif' : 'Inactif'
    });
  }
  
  // 🔥 NOUVEAU : Effacer le cache pour une page spécifique
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    console.log('[Service Worker] Nettoyage cache pour:', event.data.url);
    
    event.waitUntil(
      caches.open(CACHE_NAME).then(cache => {
        return cache.keys().then(requests => {
          requests.forEach(request => {
            if (request.url.includes(event.data.url) || 
                request.url.includes('/employee/pointage.php')) {
              cache.delete(request);
              console.log('[Service Worker] Cache supprimé pour:', request.url);
            }
          });
        });
      }).then(() => {
        event.ports[0]?.postMessage({ success: true, message: 'Cache nettoyé' });
      })
    );
  }
  
  // 🔥 NOUVEAU : Mettre à jour le cache après un pointage
  if (event.data && event.data.type === 'UPDATE_AFTER_POINTAGE') {
    console.log('[Service Worker] Mise à jour après pointage');
    
    // Effacer toutes les versions mises en cache de pointage.php
    event.waitUntil(
      caches.open(CACHE_NAME).then(cache => {
        return cache.keys().then(requests => {
          const deletePromises = requests.map(request => {
            if (request.url.includes('/employee/pointage.php')) {
              console.log('[Service Worker] Suppression du cache:', request.url);
              return cache.delete(request);
            }
            return Promise.resolve();
          });
          return Promise.all(deletePromises);
        });
      })
    );
  }
});

// Gestion des erreurs
self.addEventListener('error', function(event) {
  console.error('[Service Worker] Erreur:', event.error);
});

// Synchronisation en arrière-plan (si supporté)
self.addEventListener('sync', function(event) {
  console.log('[Service Worker] Synchronisation:', event.tag);
});