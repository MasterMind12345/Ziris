
// pwa-install.js - Version corrigée
class PWAInstall {
    constructor() {
        this.deferredPrompt = null;
        this.installButton = null;
        this.installBanner = null;
        this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        this.isAndroid = /Android/.test(navigator.userAgent);
        this.isStandalone = this.checkIfStandalone();
        this.init();
    }

    init() {
        console.log('[PWA Install] Initialisation');
        
        // Ne pas afficher sur les pages admin/login
        if (window.location.pathname.includes('/admin/') || 
            window.location.pathname.includes('/login.php') ||
            window.location.pathname.includes('/register.php')) {
            return;
        }
        
        // Si déjà en mode standalone, ne rien faire
        if (this.isStandalone) {
            console.log('[PWA Install] Déjà en mode standalone');
            return;
        }
        
        this.createInstallBanner();
        this.setupEventListeners();
        this.checkPWAEligibility();
        this.loadCSS();
    }

    createInstallBanner() {
        const bannerHTML = `
<div id="pwa-install-banner" class="pwa-install-banner">
    <div class="pwa-banner-content">
        <div class="pwa-banner-header">
            <div class="pwa-banner-icon">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="pwa-banner-text">
                <h4>📱 Installer l'application Ziris</h4>
                <p>Accédez à votre tableau de bord rapidement, même hors ligne !</p>
                <small>Recevez des rappels de pointage automatiques</small>
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
    <button id="pwa-close-btn" class="btn-close">&times;</button>
</div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', bannerHTML);
        
        this.installBanner = document.getElementById('pwa-install-banner');
        this.installButton = document.getElementById('pwa-install-btn');
    }

    setupEventListeners() {
        // Événement standard d'installation
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[PWA Install] beforeinstallprompt déclenché');
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

        // Fermeture avec le bouton X
        document.getElementById('pwa-close-btn').addEventListener('click', () => {
            this.hideBanner();
            localStorage.setItem('pwa-dismissed', Date.now().toString());
        });

        // Confirmation d'installation
        window.addEventListener('appinstalled', (evt) => {
            console.log('[PWA Install] PWA installée avec succès');
            this.hideBanner();
            this.showSuccessMessage();
            localStorage.setItem('pwa-installed', 'true');
            
            // Rediriger vers la page de dashboard après installation
            setTimeout(() => {
                if (!window.location.pathname.includes('dashboard.php')) {
                    window.location.href = 'https://ziris.global-logistique.com/employee/dashboard.php';
                }
            }, 2000);
        });
    }

    async installApp() {
        console.log('[PWA Install] Tentative d\'installation');
        
        if (this.deferredPrompt) {
            // Méthode standard pour Chrome/Edge
            try {
                this.deferredPrompt.prompt();
                const { outcome } = await this.deferredPrompt.userChoice;
                
                console.log('[PWA Install] Résultat prompt:', outcome);
                
                if (outcome === 'accepted') {
                    console.log('[PWA Install] PWA installée via prompt standard');
                    this.showMessage('Installation en cours...', 'info');
                }
                
                this.deferredPrompt = null;
            } catch (error) {
                console.error('[PWA Install] Erreur installation standard:', error);
                this.showManualInstallInstructions();
            }
        } else {
            // Fallback pour les autres navigateurs
            this.showManualInstallInstructions();
        }
    }

    checkPWAEligibility() {
        console.log('[PWA Install] Vérification éligibilité');
        
        // Vérifier si l'app n'est pas déjà installée
        if (localStorage.getItem('pwa-installed') === 'true') {
            console.log('[PWA Install] Déjà installée selon localStorage');
            return;
        }

        // Afficher la bannière même sans beforeinstallprompt (après un délai)
        setTimeout(() => {
            if (!this.isStandalone && !this.deferredPrompt) {
                console.log('[PWA Install] Affichage forcé de la bannière');
                this.showBanner();
            }
        }, 5000); // Afficher après 5 secondes
    }

    showBanner() {
        console.log('[PWA Install] Affichage bannière');
        
        // Vérifier si déjà rejeté récemment (moins de 7 jours)
        const lastDismissed = localStorage.getItem('pwa-dismissed');
        if (lastDismissed) {
            const daysSinceDismiss = (Date.now() - parseInt(lastDismissed)) / (1000 * 60 * 60 * 24);
            if (daysSinceDismiss < 7) {
                console.log('[PWA Install] Bannière récemment rejetée');
                return;
            }
        }

        // Vérifier si déjà installée
        if (localStorage.getItem('pwa-installed') === 'true') {
            console.log('[PWA Install] Déjà installée');
            return;
        }

        // Afficher la bannière
        setTimeout(() => {
            this.installBanner.style.display = 'flex';
            setTimeout(() => {
                this.installBanner.classList.add('show');
            }, 10);
        }, 2000);
    }

    hideBanner() {
        console.log('[PWA Install] Masquage bannière');
        this.installBanner.classList.remove('show');
        setTimeout(() => {
            this.installBanner.style.display = 'none';
        }, 300);
    }

    showManualInstallInstructions() {
        console.log('[PWA Install] Affichage instructions manuelles');
        
        let message = '';
        
        if (this.isIOS) {
            message = `
                <strong>Pour installer sur iPhone/iPad :</strong>
                <ol>
                    <li>Appuyez sur le bouton <strong>Partager</strong> <span style="font-size: 1.2em;">📱</span></li>
                    <li>Faites défiler vers le bas</li>
                    <li>Appuyez sur <strong>"Sur l'écran d'accueil"</strong></li>
                    <li>Confirmez avec <strong>"Ajouter"</strong></li>
                </ol>
            `;
        } else if (this.isAndroid) {
            message = `
                <strong>Pour installer sur Android :</strong>
                <ol>
                    <li>Appuyez sur les 3 points en haut à droite <span style="font-size: 1.2em;">⋮</span></li>
                    <li>Sélectionnez <strong>"Ajouter à l'écran d'accueil"</strong></li>
                    <li>Confirmez l'installation</li>
                </ol>
            `;
        } else {
            message = `
                <strong>Pour installer sur ordinateur :</strong>
                <ol>
                    <li>Cliquez sur l'icône <span style="font-size: 1.2em;">📥</span> dans la barre d'adresse</li>
                    <li>Ou allez dans le menu → <strong>"Installer l'application"</strong></li>
                    <li>Suivez les instructions à l'écran</li>
                </ol>
            `;
        }
        
        const modal = document.createElement('div');
        modal.className = 'pwa-install-modal';
        modal.innerHTML = `
            <div class="pwa-modal-content">
                <div class="pwa-modal-header">
                    <h3><i class="fas fa-mobile-alt"></i> Installer Ziris</h3>
                    <button class="pwa-modal-close">&times;</button>
                </div>
                <div class="pwa-modal-body">
                    <div class="pwa-modal-icon">
                        <img src="https://ziris.global-logistique.com/icons/icon-192x192.png" 
                             alt="Ziris" 
                             style="width: 80px; height: 80px; border-radius: 16px;">
                    </div>
                    <div class="pwa-modal-instructions">
                        ${message}
                    </div>
                </div>
                <div class="pwa-modal-footer">
                    <button class="pwa-modal-ok">
                        <i class="fas fa-check"></i> Compris !
                    </button>
                </div>
            </div>
        `;
        
        modal.querySelector('.pwa-modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('.pwa-modal-ok').addEventListener('click', () => {
            modal.remove();
        });
        
        document.body.appendChild(modal);
        
        // Fermer en cliquant à l'extérieur
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Style pour le modal
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        `;
        
        const modalContent = modal.querySelector('.pwa-modal-content');
        modalContent.style.cssText = `
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        `;
        
        modal.querySelector('.pwa-modal-header').style.cssText = `
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        `;
        
        modal.querySelector('.pwa-modal-body').style.cssText = `
            text-align: center;
            margin: 20px 0;
        `;
        
        modal.querySelector('.pwa-modal-footer').style.cssText = `
            margin-top: 20px;
            text-align: center;
        `;
        
        modal.querySelector('.pwa-modal-ok').style.cssText = `
            background: #4361ee;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        `;
        
        modal.querySelector('.pwa-modal-close').style.cssText = `
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        `;
        
        // Ajouter les animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideUp {
                from {
                    transform: translateY(50px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }

    showSuccessMessage() {
        const message = document.createElement('div');
        message.className = 'pwa-success-message';
        message.innerHTML = `
            <div class="pwa-success-content">
                <i class="fas fa-check-circle"></i>
                <div class="pwa-success-text">
                    <strong>🎉 Application installée !</strong>
                    <p>Ziris est maintenant installé sur votre appareil</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(message);
        
        // Style du message de succès
        message.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        `;
        
        setTimeout(() => {
            message.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (message.parentNode) {
                    message.remove();
                }
            }, 300);
        }, 5000);
        
        // Ajouter l'animation si elle n'existe pas
        if (!document.getElementById('pwa-animations')) {
            const style = document.createElement('style');
            style.id = 'pwa-animations';
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    showMessage(text, type = 'info') {
        const message = document.createElement('div');
        message.className = 'pwa-toast-message';
        message.innerHTML = `
            <div class="pwa-toast-content">
                <i class="fas fa-${type === 'info' ? 'info-circle' : 'check-circle'}"></i>
                <span>${text}</span>
            </div>
        `;
        
        document.body.appendChild(message);
        
        message.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'info' ? '#2196F3' : '#4CAF50'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        setTimeout(() => {
            message.remove();
        }, 3000);
    }

    checkIfStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.navigator.standalone === true;
    }

    loadCSS() {
        // Charger le CSS pour la bannière
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://ziris.global-logistique.com/pwa-install.css';
        document.head.appendChild(link);
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    // Attendre un peu avant d'initialiser pour ne pas surcharger le chargement
    setTimeout(() => {
        new PWAInstall();
    }, 1000);
});
