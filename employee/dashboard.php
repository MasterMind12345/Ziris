<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les préférences utilisateur (comme dans param.php)
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        // Créer des préférences par défaut
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, font_size, notifications, accessibility_mode) VALUES (?, 'light', 'medium', 1, 0)");
        $stmt->execute([$user_id]);
        // Re-récupérer les préférences après insertion
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch();
    }
} catch(PDOException $e) {
    // Si la table n'existe pas, utiliser des valeurs par défaut
    $preferences = ['theme' => 'light', 'font_size' => 'medium', 'notifications' => 1, 'accessibility_mode' => 0];
}

// Déterminer le thème actuel pour l'affichage
$currentTheme = $preferences['theme'] ?? 'light';

try {
    $stmt = $pdo->prepare("
        SELECT u.*, p.nom as poste_nom, p.description as poste_description 
        FROM users u 
        LEFT JOIN postes p ON u.poste_id = p.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM presences WHERE user_id = ? AND date_presence = CURDATE()");
    $stmt->execute([$user_id]);
    $presence_aujourdhui = $stmt->fetch();
} catch(PDOException $e) {
    $presence_aujourdhui = null;
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM presences 
        WHERE user_id = ? 
        AND MONTH(date_presence) = MONTH(CURDATE()) 
        AND YEAR(date_presence) = YEAR(CURDATE())
    ");
    $stmt->execute([$user_id]);
    $presences_mois = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM presences 
        WHERE user_id = ? 
        AND MONTH(date_presence) = MONTH(CURDATE()) 
        AND YEAR(date_presence) = YEAR(CURDATE())
        AND retard_minutes > 0
    ");
    $stmt->execute([$user_id]);
    $retards_mois = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("
        SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(heure_fin_reel, heure_debut_reel)))) as total_heures
        FROM presences 
        WHERE user_id = ? 
        AND MONTH(date_presence) = MONTH(CURDATE()) 
        AND YEAR(date_presence) = YEAR(CURDATE())
        AND heure_fin_reel IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $heures_mois = $stmt->fetch()['total_heures'] ?? '00:00:00';

} catch(PDOException $e) {
    $presences_mois = 0;
    $retards_mois = 0;
    $heures_mois = '00:00:00';
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Ziris</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    
    <meta name="theme-color" content="#4361ee"/>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="employee-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-fingerprint"></i>Ziris</h1>
                <span class="user-role">Espace Employé</span>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php
                        $initials = '';
                        $names = explode(' ', $user['nom']);
                        foreach ($names as $name) {
                            $initials .= strtoupper(substr($name, 0, 1));
                        }
                        $initials = substr($initials, 0, 2);
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($user['nom']); ?></span>
                        <span class="user-poste"><?php echo htmlspecialchars($user['poste_nom']); ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="employee-nav">
        <div class="nav-content">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de Bord</span>
            </a>
            <a href="presences.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>Mes Présences</span>
            </a>
            <a href="pointage.php" class="nav-item">
                <i class="fas fa-qrcode"></i>
                <span>Pointer</span>
            </a>

            <a href="aide.php" class="nav-item">
                <i class="fas fa-question-circle"></i>
                <span>Aide</span>
            </a>
             <a href="param.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
        </div>
    </nav>

    <main class="employee-main">
        <!-- En-tête avec informations personnelles -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h1>Bonjour, <?php echo htmlspecialchars($user['nom']); ?> !</h1>
                <p>Bienvenue sur votre tableau de bord Ziris</p>
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="liveClock"><?php echo date('H:i:s'); ?></span>
                    <span class="date"><?php echo date('d/m/Y'); ?></span>
                </div>
            </div>
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php echo $initials; ?>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($user['nom']); ?></h3>
                    <p class="profile-poste"><?php echo htmlspecialchars($user['poste_nom']); ?></p>
                    <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
        </div>

        <!-- Statistiques du mois -->
        <div class="stats-section">
            <h2>Statistiques du Mois</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $presences_mois; ?></h3>
                        <p>Jours Présents</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $retards_mois; ?></h3>
                        <p>Retards</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-business-time"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo substr($heures_mois, 0, 5); ?></h3>
                        <p>Heures Travaillées</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php 
                            $jours_ouvres = date('t') - 8; // Approximation
                            $taux = $jours_ouvres > 0 ? round(($presences_mois / $jours_ouvres) * 100) : 0;
                            echo $taux; 
                        ?>%</h3>
                        <p>Taux de Présence</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pointage du jour -->
        <div class="pointage-section">
            <div class="pointage-header">
                <h2>Pointage du Jour</h2>
                <span class="pointage-date"><?php echo date('d/m/Y'); ?></span>
            </div>
            <div class="pointage-status">
                <?php if ($presence_aujourdhui): ?>
                    <?php if ($presence_aujourdhui['heure_fin_reel']): ?>
                        <div class="status-complete">
                            <i class="fas fa-check-circle"></i>
                            <div class="status-info">
                                <h3>Pointage Complet</h3>
                                <p>Début: <?php echo $presence_aujourdhui['heure_debut_reel']; ?> | Fin: <?php echo $presence_aujourdhui['heure_fin_reel']; ?></p>
                                <?php if ($presence_aujourdhui['retard_minutes'] > 0): ?>
                                    <span class="retard-badge">Retard: <?php echo $presence_aujourdhui['retard_minutes']; ?> min</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="status-in-progress">
                            <i class="fas fa-clock"></i>
                            <div class="status-info">
                                <h3>En Cours</h3>
                                <p>Début: <?php echo $presence_aujourdhui['heure_debut_reel']; ?></p>
                                <a href="pointage.php" class="btn-pointage">Pointer la Fin</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="status-pending">
                        <i class="fas fa-hourglass-start"></i>
                        <div class="status-info">
                            <h3>En Attente</h3>
                            <p>Aucun pointage aujourd'hui</p>
                            <a href="pointage.php" class="btn-pointage">Commencer le Pointage</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historique récent -->
        <div class="history-section">
            <div class="section-header">
                <h2>Historique Récent</h2>
                <a href="presences.php" class="view-all">Voir tout <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="history-grid">
                <div class="history-card">
                    <h3><i class="fas fa-calendar-day"></i> Aujourd'hui</h3>
                    <div class="history-content">
                        <?php
                        try {
                            $stmt = $pdo->prepare("
                                SELECT * FROM presences 
                                WHERE user_id = ? AND date_presence = CURDATE()
                                ORDER BY created_at DESC LIMIT 5
                            ");
                            $stmt->execute([$user_id]);
                            $presences_aujourdhui = $stmt->fetchAll();
                            
                            if ($presences_aujourdhui) {
                                foreach ($presences_aujourdhui as $presence) {
                                    echo '<div class="history-item">';
                                    echo '<div class="history-time">' . $presence['heure_debut_reel'] . '</div>';
                                    echo '<div class="history-type">Début</div>';
                                    if ($presence['retard_minutes'] > 0) {
                                        echo '<div class="history-retard">+' . $presence['retard_minutes'] . 'min</div>';
                                    }
                                    echo '</div>';
                                    
                                    if ($presence['heure_fin_reel']) {
                                        echo '<div class="history-item">';
                                        echo '<div class="history-time">' . $presence['heure_fin_reel'] . '</div>';
                                        echo '<div class="history-type">Fin</div>';
                                        echo '</div>';
                                    }
                                }
                            } else {
                                echo '<p class="no-data">Aucun pointage aujourd\'hui</p>';
                            }
                        } catch(PDOException $e) {
                            echo '<p class="no-data">Erreur de chargement</p>';
                        }
                        ?>
                    </div>
                </div>

                <div class="history-card">
                    <h3><i class="fas fa-calendar-week"></i> Cette Semaine</h3>
                    <div class="history-content">
                        <?php
                        try {
                            $debut_semaine = date('Y-m-d', strtotime('monday this week'));
                            $fin_semaine = date('Y-m-d', strtotime('sunday this week'));
                            
                            $stmt = $pdo->prepare("
                                SELECT date_presence, COUNT(*) as nb_pointages 
                                FROM presences 
                                WHERE user_id = ? AND date_presence BETWEEN ? AND ?
                                GROUP BY date_presence 
                                ORDER BY date_presence DESC 
                                LIMIT 5
                            ");
                            $stmt->execute([$user_id, $debut_semaine, $fin_semaine]);
                            $semaine = $stmt->fetchAll();
                            
                            if ($semaine) {
                                foreach ($semaine as $jour) {
                                    echo '<div class="history-item">';
                                    echo '<div class="history-date">' . date('d/m', strtotime($jour['date_presence'])) . '</div>';
                                    echo '<div class="history-count">' . $jour['nb_pointages'] . ' pointage(s)</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p class="no-data">Aucun pointage cette semaine</p>';
                            }
                        } catch(PDOException $e) {
                            echo '<p class="no-data">Erreur de chargement</p>';
                        }
                        ?>
                    </div>
                </div>

                <div class="history-card">
                    <h3><i class="fas fa-calendar-alt"></i> Ce Mois</h3>
                    <div class="history-content">
                        <?php
                        try {
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as total_jours 
                                FROM (
                                    SELECT DISTINCT date_presence 
                                    FROM presences 
                                    WHERE user_id = ? 
                                    AND MONTH(date_presence) = MONTH(CURDATE()) 
                                    AND YEAR(date_presence) = YEAR(CURDATE())
                                ) as jours
                            ");
                            $stmt->execute([$user_id]);
                            $jours_presents = $stmt->fetch()['total_jours'];
                            
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as total_retards 
                                FROM presences 
                                WHERE user_id = ? 
                                AND MONTH(date_presence) = MONTH(CURDATE()) 
                                AND YEAR(date_presence) = YEAR(CURDATE())
                                AND retard_minutes > 0
                            ");
                            $stmt->execute([$user_id]);
                            $total_retards = $stmt->fetch()['total_retards'];
                            
                            echo '<div class="stats-mini">';
                            echo '<div class="stat-mini"><span>' . $jours_presents . '</span> Jours</div>';
                            echo '<div class="stat-mini"><span>' . $total_retards . '</span> Retards</div>';
                            echo '<div class="stat-mini"><span>' . substr($heures_mois, 0, 5) . '</span> Heures</div>';
                            echo '</div>';
                            
                        } catch(PDOException $e) {
                            echo '<p class="no-data">Erreur de chargement</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>




<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableBtn = document.getElementById('enable-notifications');
    const testBtn = document.getElementById('test-notification');
    const statusDiv = document.getElementById('notification-status');
    
    if (enableBtn) {
        enableBtn.onclick = async function() {
            if (window.pwaNotifications) {
                const enabled = await window.pwaNotifications.requestPermission();
                if (enabled) {
                    statusDiv.innerHTML = '<div class="alert alert-success">✅ Notifications activées!</div>';
                } else {
                    statusDiv.innerHTML = '<div class="alert alert-warning">❌ Permission refusée</div>';
                }
            }
        };
    }
    
    if (testBtn) {
        testBtn.onclick = function() {
            if (window.pwaNotifications) {
                window.pwaNotifications.testNotification();
            }
        };
    }
});
</script>
    <!-- Après les autres scripts -->
<script>
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
</script>


    <script>
        // Horloge en temps réel
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR');
            document.getElementById('liveClock').textContent = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .history-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
        });
    </script>

    <style>
        /* Variables CSS pour les thèmes */
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border-radius: 12px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            
            /* Variables pour le thème clair */
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-card: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
        }

        /* Thème sombre */
        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-card: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --border-color: #404040;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Header */
        .employee-header {
            background: var(--bg-card);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-left h1 {
            color: var(--primary);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-role {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            margin-left: 10px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .user-poste {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .logout-btn {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 18px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        /* Navigation */
        .employee-nav {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
        }

        .nav-content {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Main Content */
        .employee-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background: var(--bg-primary);
        }

        /* Welcome Section */
        .welcome-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .welcome-content h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .welcome-content p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .current-time {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            color: var(--primary);
            font-weight: 600;
        }

        .current-time .date {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: normal;
        }

        .profile-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .profile-info h3 {
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .profile-poste {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-email {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 30px;
        }

        .stats-section h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: var(--text-primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            background: var(--primary);
        }

        .stat-icon.warning {
            background: var(--warning);
        }

        .stat-icon.success {
            background: var(--success);
        }

        .stat-icon.info {
            background: var(--secondary);
        }

        .stat-info h3 {
            font-size: 28px;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .stat-info p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Pointage Section */
        .pointage-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .pointage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .pointage-header h2 {
            font-size: 24px;
            color: var(--text-primary);
        }

        .pointage-date {
            color: var(--text-secondary);
            font-weight: 600;
        }

        .pointage-status {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .status-complete, .status-in-progress, .status-pending {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
        }

        .status-complete {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-in-progress {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-pending {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        .status-info h3 {
            margin-bottom: 5px;
        }

        .btn-pointage {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-block;
            margin-top: 10px;
        }

        .btn-pointage:hover {
            background: var(--primary-dark);
        }

        .retard-badge {
            background: var(--warning);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-top: 5px;
            display: inline-block;
        }

        /* History Section */
        .history-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 24px;
            color: var(--text-primary);
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .history-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .history-card {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 20px;
        }

        .history-card h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .history-time, .history-date {
            font-weight: 600;
            color: var(--text-primary);
        }

        .history-type {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .history-retard {
            background: var(--warning);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .history-count {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .no-data {
            text-align: center;
            color: var(--text-secondary);
            font-style: italic;
            padding: 20px 0;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .stat-mini {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--bg-card);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .stat-mini span {
            font-weight: 600;
            color: var(--primary);
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 15px 20px;
            }

            .employee-main {
                padding: 20px;
            }

            .welcome-section {
                grid-template-columns: 1fr;
            }

            .nav-content {
                overflow-x: auto;
                padding: 0 20px;
            }

            .history-grid {
                grid-template-columns: 1fr;
            }

            .pointage-status {
                flex-direction: column;
            }
        }
    </style>

</body>
</html>