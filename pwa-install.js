// pwa-install.js - Version avec installation forcée
class PWAInstall {
    constructor() {
        this.deferredPrompt = null;
        this.installButton = null;
        this.installBanner = null;
        this.init();
    }

    init() {
        // Ne pas afficher sur les pages admin
        if (window.location.pathname.includes('/admin/') || 
            window.location.pathname.includes('/login.php') ||
            window.location.pathname.includes('/register.php')) {
            return;
        }
        
        this.createInstallBanner();
        this.setupEventListeners();
        this.registerServiceWorker();
        this.checkPWAEligibility();
    }

    createInstallBanner() {
        const bannerHTML = `
                <div id="pwa-install-banner" class="pwa-install-banner" style="display: none;">
    <div class="pwa-banner-content">
        <div class="pwa-banner-header">
            <div class="pwa-banner-icon">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="pwa-banner-text">
                <h4>📱 Installer l'application Batobaye</h4>
                <p>Accédez à votre tableau de bord rapidement, même hors ligne !</p>
            </div>
        </div>
        <div class="pwa-banner-actions">
            <button id="pwa-install-btn" class="btn-install">
                <i class="fas fa-download"></i>
                Installer l'App
            </button>
            <button id="pwa-dismiss-btn" class="btn-dismiss">
                Plus tard
            </button>
        </div>
    </div>
</div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', bannerHTML);
        
        this.installBanner = document.getElementById('pwa-install-banner');
        this.installButton = document.getElementById('pwa-install-btn');
    }

    setupEventListeners() {
        // Événement standard d'installation
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('beforeinstallprompt déclenché');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showBanner();
        });

        // Installation manuelle
        this.installButton.addEventListener('click', () => {
            this.installApp();
        });

        // Fermeture de la bannière
        document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
            this.hideBanner();
            localStorage.setItem('pwa-dismissed', Date.now().toString());
        });

        // Confirmation d'installation
        window.addEventListener('appinstalled', () => {
            console.log('PWA installée avec succès');
            this.hideBanner();
            this.showSuccessMessage();
            localStorage.setItem('pwa-installed', 'true');
        });
    }

    async installApp() {
        if (this.deferredPrompt) {
            // Méthode standard
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('PWA installée via prompt standard');
            }
            
            this.deferredPrompt = null;
        } else {
            // Fallback pour les navigateurs qui ne supportent pas beforeinstallprompt
            this.showManualInstallInstructions();
        }
    }

    checkPWAEligibility() {
        // Vérifier si l'app n'est pas déjà installée
        if (localStorage.getItem('pwa-installed') === 'true') {
            return;
        }

        // Vérifier les critères PWA
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);

        if (isStandalone) {
            console.log('Déjà en mode standalone');
            return;
        }

        // Afficher la bannière même sans beforeinstallprompt
        setTimeout(() => {
            if (!this.deferredPrompt) {
                console.log('Affichage forcé de la bannière');
                this.showBanner();
            }
        }, 3000);
    }

    showBanner() {
        // Vérifier si déjà rejeté récemment (moins de 24h)
        const lastDismissed = localStorage.getItem('pwa-dismissed');
        if (lastDismissed) {
            const daysSinceDismiss = (Date.now() - parseInt(lastDismissed)) / (1000 * 60 * 60 * 24);
            if (daysSinceDismiss < 1) { // 24 heures
                return;
            }
        }

        // Vérifier si déjà installée
        if (localStorage.getItem('pwa-installed') === 'true') {
            return;
        }

        // Afficher la bannière
        setTimeout(() => {
            this.installBanner.style.display = 'block';
            setTimeout(() => {
                this.installBanner.classList.add('show');
            }, 100);
        }, 2000); // Afficher après 2 secondes
    }

    hideBanner() {
        this.installBanner.classList.remove('show');
        setTimeout(() => {
            this.installBanner.style.display = 'none';
        }, 300);
    }

    showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);
        
        let message = '';
        
        if (isIOS) {
            message = `
                <strong>Pour installer sur iOS :</strong><br>
                1. Appuyez sur le bouton "Partager" 📱<br>
                2. Faites défiler vers le bas<br>
                3. Appuyez sur "Sur l'écran d'accueil"<br>
                4. Confirmez avec "Ajouter"
            `;
        } else if (isAndroid) {
            message = `
                <strong>Pour installer sur Android :</strong><br>
                1. Appuyez sur les 3 points en haut à droite ⋮<br>
                2. Sélectionnez "Ajouter à l'écran d'accueil"<br>
                3. Confirmez l'installation
            `;
        } else {
            message = `
                <strong>Pour installer sur ordinateur :</strong><br>
                1. Cliquez sur l'icône d'installation dans la barre d'adresse<br>
                2. Ou allez dans le menu → "Installer l'application"
            `;
        }
        
        const modal = document.createElement('div');
        modal.className = 'pwa-install-modal';
        modal.innerHTML = `
            <div class="pwa-modal-content">
                <div class="pwa-modal-header">
                    <h3>📲 Installer Batobaye</h3>
                    <button class="pwa-modal-close">&times;</button>
                </div>
                <div class="pwa-modal-body">
                    <div class="pwa-modal-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <p>${message}</p>
                </div>
                <div class="pwa-modal-footer">
                    <button class="btn-primary" onclick="this.closest('.pwa-install-modal').remove()">
                        Compris !
                    </button>
                </div>
            </div>
        `;
        
        modal.querySelector('.pwa-modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        document.body.appendChild(modal);
        
        // Fermer en cliquant à l'extérieur
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    showSuccessMessage() {
        const message = document.createElement('div');
        message.className = 'pwa-success-message';
        message.innerHTML = `
            <div class="pwa-success-content">
                <i class="fas fa-check-circle"></i>
                <span>🎉 Application installée avec succès !</span>
            </div>
        `;
        document.body.appendChild(message);
        
        setTimeout(() => {
            message.remove();
        }, 5000);
    }

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/batobaye/sw.js')
                .then(function(registration) {
                    console.log('ServiceWorker enregistré avec succès');
                })
                .catch(function(error) {
                    console.log('Échec enregistrement ServiceWorker: ', error);
                });
        }
    }
}

// Détection améliorée du mode standalone
function isPWAInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches || 
           window.navigator.standalone === true;
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    // Ne pas initialiser si déjà en mode PWA
    if (!isPWAInstalled()) {
        new PWAInstall();
    }
});