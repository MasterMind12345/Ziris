
// pwa-notifications.js - Version corrigée
class PWANotifications {
    constructor() {
        this.notificationsEnabled = false;
        this.serviceWorkerRegistered = false;
        this.init();
    }

    async init() {
        console.log('[PWA Notifications] Initialisation');
        
        // Vérifier si les notifications sont supportées
        if (!('Notification' in window)) {
            console.warn('[PWA Notifications] Notifications non supportées par le navigateur');
            this.updateUIStatus('unsupported');
            return;
        }

        // Enregistrer le Service Worker d'abord
        await this.registerServiceWorker();
        
        // Vérifier les permissions
        await this.checkPermission();
        
        // Mettre à jour l'interface
        this.updateUI();
    }

    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.warn('[PWA Notifications] Service Worker non supporté');
            return false;
        }

        try {
            // CORRIGÉ : Chemin correct du Service Worker
            const registration = await navigator.serviceWorker.register('https://ziris.global-logistique.com/sw.js');
            console.log('[PWA Notifications] Service Worker enregistré:', registration);
            
            // Attendre que le Service Worker soit actif
            if (registration.installing) {
                await new Promise(resolve => {
                    registration.installing.addEventListener('statechange', () => {
                        if (registration.active) resolve();
                    });
                });
            }
            
            this.serviceWorkerRegistered = true;
            
            // Écouter les messages du Service Worker
            navigator.serviceWorker.addEventListener('message', event => {
                console.log('[PWA Notifications] Message du Service Worker:', event.data);
            });
            
            return true;
        } catch (error) {
            console.error('[PWA Notifications] Erreur enregistrement Service Worker:', error);
            return false;
        }
    }

    async checkPermission() {
        const permission = Notification.permission;
        console.log('[PWA Notifications] Permission actuelle:', permission);
        
        this.notificationsEnabled = permission === 'granted';
        
        // Si la permission n'a jamais été demandée, la demander automatiquement
        if (permission === 'default') {
            // Attendre un peu avant de demander pour ne pas être trop intrusif
            setTimeout(async () => {
                await this.requestPermission();
            }, 3000);
        }
        
        // Sauvegarder la préférence
        this.savePreference(this.notificationsEnabled);
        
        return this.notificationsEnabled;
    }

    async requestPermission() {
        console.log('[PWA Notifications] Demande de permission');
        
        if (!('Notification' in window)) {
            alert('Votre navigateur ne supporte pas les notifications');
            return false;
        }
        
        try {
            const permission = await Notification.requestPermission();
            console.log('[PWA Notifications] Permission résultat:', permission);
            
            this.notificationsEnabled = permission === 'granted';
            this.savePreference(this.notificationsEnabled);
            
            if (this.notificationsEnabled) {
                // Envoyer un message au Service Worker pour activer les rappels
                await this.syncWithServiceWorker();
                this.showWelcomeNotification();
                
                // Afficher un message de confirmation
                this.showMessage('success', '✅ Notifications activées avec succès !');
            } else {
                this.showMessage('warning', '🔕 Vous avez refusé les notifications');
            }
            
            this.updateUI();
            return this.notificationsEnabled;
        } catch (error) {
            console.error('[PWA Notifications] Erreur demande permission:', error);
            return false;
        }
    }

    async syncWithServiceWorker() {
        if (!this.serviceWorkerRegistered || !this.notificationsEnabled) return;
        
        try {
            const registration = await navigator.serviceWorker.ready;
            
            // Envoyer un message pour activer les rappels
            registration.active.postMessage({
                type: 'TOGGLE_REMINDERS',
                enabled: this.notificationsEnabled
            });
            
            console.log('[PWA Notifications] Synchronisation avec Service Worker réussie');
        } catch (error) {
            console.error('[PWA Notifications] Erreur synchronisation:', error);
        }
    }

    savePreference(enabled) {
        localStorage.setItem('pwa-notifications-enabled', enabled ? 'true' : 'false');
        console.log('[PWA Notifications] Préférence sauvegardée:', enabled);
    }

    loadPreference() {
        const saved = localStorage.getItem('pwa-notifications-enabled');
        this.notificationsEnabled = saved === 'true';
        return this.notificationsEnabled;
    }

    async showWelcomeNotification() {
        if (!this.serviceWorkerRegistered) return;
        
        try {
            const registration = await navigator.serviceWorker.ready;
            
            registration.showNotification('Bienvenue sur Ziris !', {
                body: 'Les notifications de rappel sont maintenant activées 🎉',
                icon: 'https://ziris.global-logistique.com/icons/icon-192x192.png',
                badge: 'https://ziris.global-logistique.com/icons/icon-72x72.png',
                tag: 'welcome-' + Date.now(),
                vibrate: [200, 100, 200],
                actions: [
                    {
                        action: 'dashboard',
                        title: '📊 Tableau de bord'
                    }
                ],
                data: {
                    url: 'https://ziris.global-logistique.com/employee/dashboard.php',
                    type: 'welcome'
                }
            });
        } catch (error) {
            console.error('[PWA Notifications] Erreur notification bienvenue:', error);
        }
    }

    async testNotification() {
        console.log('[PWA Notifications] Test de notification');
        
        // Vérifier et demander la permission si nécessaire
        if (!this.notificationsEnabled) {
            const granted = await this.requestPermission();
            if (!granted) return;
        }
        
        if (!this.serviceWorkerRegistered) {
            console.warn('[PWA Notifications] Service Worker non enregistré');
            return;
        }
        
        try {
            const registration = await navigator.serviceWorker.ready;
            
            await registration.showNotification('Test de notification Ziris', {
                body: 'Ceci est une notification de test. Les rappels de pointage fonctionnent correctement !',
                icon: 'https://ziris.global-logistique.com/icons/icon-192x192.png',
                badge: 'https://ziris.global-logistique.com/icons/icon-72x72.png',
                tag: 'test-' + Date.now(),
                vibrate: [200, 100, 200],
                requireInteraction: true,
                actions: [
                    {
                        action: 'test-ok',
                        title: '👍 OK'
                    }
                ],
                data: {
                    url: window.location.href,
                    type: 'test'
                }
            });
            
            console.log('[PWA Notifications] Notification de test envoyée');
            this.showMessage('success', '🔔 Notification de test envoyée !');
        } catch (error) {
            console.error('[PWA Notifications] Erreur notification test:', error);
            this.showMessage('error', '❌ Erreur lors de l\'envoi de la notification');
        }
    }

    getReminderTimes() {
        return [
            { time: '08:00', label: 'Début de présence', emoji: '🕗' },
            { time: '12:00', label: 'Début de pause', emoji: '🍽️' },
            { time: '13:30', label: 'Fin de pause', emoji: '↩️' },
            { time: '17:30', label: 'Fin de journée', emoji: '🏁' }
        ];
    }

    showMessage(type, text) {
        // Créer un élément de message temporaire
        const message = document.createElement('div');
        message.className = `pwa-message pwa-message-${type}`;
        message.innerHTML = `
            <div class="pwa-message-content">
                <span>${text}</span>
                <button class="pwa-message-close">&times;</button>
            </div>
        `;
        
        // Style du message
        message.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#ff9800'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        `;
        
        message.querySelector('.pwa-message-content').style.cssText = `
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        `;
        
        message.querySelector('.pwa-message-close').style.cssText = `
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            margin: 0;
        `;
        
        // Ajouter au body
        document.body.appendChild(message);
        
        // Fermer au clic sur le bouton
        message.querySelector('.pwa-message-close').addEventListener('click', () => {
            message.remove();
        });
        
        // Fermer automatiquement après 5 secondes
        setTimeout(() => {
            if (message.parentNode) {
                message.remove();
            }
        }, 5000);
    }

    updateUI() {
        const enableBtn = document.getElementById('enable-notifications');
        const testBtn = document.getElementById('test-notification');
        const statusDiv = document.getElementById('notification-status');
        
        if (!enableBtn || !statusDiv) return;
        
        if (this.notificationsEnabled) {
            enableBtn.innerHTML = '<i class="fas fa-bell-slash"></i> Désactiver les notifications';
            enableBtn.classList.remove('btn-primary');
            enableBtn.classList.add('btn-warning');
            
            if (statusDiv) {
                statusDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Notifications activées</strong>
                        <p>Vous recevrez des rappels aux heures suivantes:</p>
                        <ul>
                            ${this.getReminderTimes().map(r => 
                                `<li>${r.emoji} ${r.time} - ${r.label}</li>`
                            ).join('')}
                        </ul>
                    </div>
                `;
            }
        } else {
            enableBtn.innerHTML = '<i class="fas fa-bell"></i> Activer les notifications';
            enableBtn.classList.remove('btn-warning');
            enableBtn.classList.add('btn-primary');
            
            if (statusDiv) {
                statusDiv.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Notifications désactivées</strong>
                        <p>Activez-les pour recevoir des rappels de pointage automatiques.</p>
                    </div>
                `;
            }
        }
    }

    updateUIStatus(status) {
        const statusDiv = document.getElementById('notification-status');
        if (!statusDiv) return;
        
        switch(status) {
            case 'unsupported':
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Navigateur non compatible</strong>
                        <p>Votre navigateur ne supporte pas les notifications.</p>
                    </div>
                `;
                break;
        }
    }
}

// Fonction pour ajouter les contrôles de notification dans l'interface
function addNotificationControls() {
    // Vérifier si nous sommes sur une page employé
    if (!window.location.pathname.includes('/employee/')) return;
    
    // Attendre que le DOM soit chargé
    setTimeout(() => {
        // Chercher un endroit où ajouter les contrôles
        const sidebar = document.querySelector('.employee-nav') || 
                       document.querySelector('.nav-content') ||
                       document.querySelector('.header-right');
        
        if (sidebar && !document.getElementById('notification-controls')) {
            const controls = document.createElement('div');
            controls.id = 'notification-controls';
            controls.style.cssText = `
                display: flex;
                gap: 10px;
                align-items: center;
                margin-left: auto;
                padding: 0 15px;
            `;
            
            controls.innerHTML = `
                <button id="test-notification-btn" class="btn btn-sm btn-info">
                    <i class="fas fa-bell"></i> Tester
                </button>
                <div class="notification-status-indicator">
                    <i class="fas fa-circle" id="notification-indicator"></i>
                </div>
            `;
            
            sidebar.appendChild(controls);
            
            // Ajouter les écouteurs d'événements
            document.getElementById('test-notification-btn').addEventListener('click', () => {
                if (window.pwaNotifications) {
                    window.pwaNotifications.testNotification();
                }
            });
            
            // Mettre à jour l'indicateur
            updateNotificationIndicator();
        }
    }, 1000);
}

function updateNotificationIndicator() {
    const indicator = document.getElementById('notification-indicator');
    if (!indicator) return;
    
    if (Notification.permission === 'granted') {
        indicator.style.color = '#4CAF50';
        indicator.title = 'Notifications activées';
    } else if (Notification.permission === 'denied') {
        indicator.style.color = '#f44336';
        indicator.title = 'Notifications refusées';
    } else {
        indicator.style.color = '#ff9800';
        indicator.title = 'Permission en attente';
    }
}

// Initialisation globale
document.addEventListener('DOMContentLoaded', () => {
    // Créer l'instance globale
    window.pwaNotifications = new PWANotifications();
    
    // Ajouter les contrôles d'interface
    addNotificationControls();
    
    // Ajouter un écouteur pour les messages du Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', event => {
            console.log('[Page] Message du Service Worker:', event.data);
            
            // Traiter les messages si nécessaire
            if (event.data && event.data.type === 'NAVIGATE_TO_POINTAGE') {
                window.location.href = 'https://ziris.global-logistique.com/employee/pointage.php';
            }
        });
    }
    
    // Mettre à jour l'indicateur périodiquement
    setInterval(updateNotificationIndicator, 5000);
});

// Style CSS pour les messages
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .pwa-message {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .notification-status-indicator {
        font-size: 10px;
        margin-left: 5px;
    }
    
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid;
    }
    
    .alert-success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    
    .alert-info {
        background-color: #d1ecf1;
        border-color: #bee5eb;
        color: #0c5460;
    }
    
    .alert-warning {
        background-color: #fff3cd;
        border-color: #ffeaa7;
        color: #856404;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }
    
    .alert i {
        margin-right: 10px;
        font-size: 1.2em;
    }
    
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background-color: #4361ee;
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #3a56d4;
    }
    
    .btn-warning {
        background-color: #f72585;
        color: white;
    }
    
    .btn-warning:hover {
        background-color: #e11575;
    }
    
    .btn-info {
        background-color: #4cc9f0;
        color: white;
    }
    
    .btn-info:hover {
        background-color: #3bb9e0;
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 0.9em;
    }
`;
document.head.appendChild(style);
