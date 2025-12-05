// pwa-notifications.js
class PWANotifications {
    constructor() {
        this.notificationsEnabled = false;
        this.init();
    }

    async init() {
        // Vérifier si les notifications sont supportées
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            console.log('Notifications non supportées');
            return;
        }

        // Vérifier l'état actuel des permissions
        await this.checkPermission();
        
        // Synchroniser avec le service worker
        await this.syncWithServiceWorker();
    }

    async checkPermission() {
        // Demander la permission si pas encore accordée
        if (Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            this.notificationsEnabled = permission === 'granted';
            
            if (this.notificationsEnabled) {
                this.savePreference(true);
                this.showWelcomeNotification();
            }
        } else if (Notification.permission === 'granted') {
            this.notificationsEnabled = true;
            this.loadPreference();
        }
    }

    async syncWithServiceWorker() {
        if (!this.notificationsEnabled) return;
        
        // Vérifier si le service worker est actif
        if (navigator.serviceWorker.controller) {
            // Envoyer un message pour activer les rappels
            navigator.serviceWorker.controller.postMessage({
                type: 'TOGGLE_REMINDERS',
                enabled: this.notificationsEnabled
            });
        } else {
            // Attendre que le service worker soit prêt
            navigator.serviceWorker.ready.then(registration => {
                registration.active.postMessage({
                    type: 'TOGGLE_REMINDERS',
                    enabled: this.notificationsEnabled
                });
            });
        }
    }

    savePreference(enabled) {
        localStorage.setItem('pwa-notifications-enabled', enabled ? 'true' : 'false');
    }

    loadPreference() {
        const saved = localStorage.getItem('pwa-notifications-enabled');
        this.notificationsEnabled = saved !== 'false'; // Par défaut true
    }

    showWelcomeNotification() {
        // Envoyer une notification de bienvenue
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'TEST_NOTIFICATION'
            });
        }
    }

    // Méthode pour tester les notifications
    async testNotification() {
        if (!this.notificationsEnabled) {
            await this.requestPermission();
        }
        
        if (this.notificationsEnabled && 'serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(registration => {
                registration.showNotification('Test de rappel Ziris', {
                    body: 'Les notifications de rappel sont activées! 🎉',
                    icon: '/icons/icon-192x192.png',
                    vibrate: [200, 100, 200],
                    tag: 'test-notification'
                });
            });
        }
    }

    // Demander explicitement la permission
    async requestPermission() {
        if (!('Notification' in window)) {
            alert('Votre navigateur ne supporte pas les notifications');
            return false;
        }
        
        const permission = await Notification.requestPermission();
        this.notificationsEnabled = permission === 'granted';
        this.savePreference(this.notificationsEnabled);
        
        if (this.notificationsEnabled) {
            await this.syncWithServiceWorker();
        }
        
        return this.notificationsEnabled;
    }

    // Vérifier les heures de rappel configurées
    getReminderTimes() {
        return [
            { time: '08:00', label: 'Début de présence' },
            { time: '12:00', label: 'Début de pause' },
            { time: '13:30', label: 'Fin de pause' },
            { time: '17:30', label: 'Fin de journée' }
        ];
    }
}

// Initialisation
let pwaNotifications;

document.addEventListener('DOMContentLoaded', () => {
    pwaNotifications = new PWANotifications();
    
    // Ajouter un bouton de test dans l'interface employé si désiré
    if (window.location.pathname.includes('/employee/')) {
        addNotificationControls();
    }
});

function addNotificationControls() {
    // Créer un bouton dans la sidebar ou dashboard
    setTimeout(() => {
        const sidebar = document.querySelector('.sidebar') || document.querySelector('.navbar');
        if (sidebar) {
            const notificationBtn = document.createElement('button');
            notificationBtn.className = 'btn btn-info btn-sm';
            notificationBtn.innerHTML = '<i class="fas fa-bell"></i> Test Notifications';
            notificationBtn.onclick = () => {
                if (pwaNotifications) {
                    pwaNotifications.testNotification();
                }
            };
            sidebar.appendChild(notificationBtn);
        }
    }, 2000);
}