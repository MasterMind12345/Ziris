<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les préférences utilisateur (admin)
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        // Créer des préférences par défaut pour l'admin
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, font_size, notifications, accessibility_mode) VALUES (?, 'light', 'medium', 1, 0)");
        $stmt->execute([$_SESSION['user_id']]);
        // Re-récupérer les préférences après insertion
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $preferences = $stmt->fetch();
    }
} catch(PDOException $e) {
    // Si la table n'existe pas, utiliser des valeurs par défaut
    $preferences = ['theme' => 'light', 'font_size' => 'medium', 'notifications' => 1, 'accessibility_mode' => 0];
}

// Déterminer le thème actuel pour l'affichage
$currentTheme = $preferences['theme'] ?? 'light';

$missing_tables = checkDatabaseTables();
if (!empty($missing_tables)) {
    $error_message = "Tables manquantes dans la base de données: " . implode(', ', $missing_tables);
}

$stats = getDashboardStats();
$recentActivities = getRecentActivities();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ziris Admin</title>
    
    <!-- Style CSS principal avec support du thème -->
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="/manifest.json">

    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/pwa-install.css">
    <script src="/pwa-install.js" defer></script>
    <meta name="theme-color" content="#4361ee"/>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">

    <!-- CSS supplémentaire pour le thème -->
    <style>
        /* Variables CSS pour les thèmes - alignées avec pointage.php */
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-card: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-card: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --border-color: #404040;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        /* Appliquer les variables CSS au body */
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .main-content {
            background-color: var(--bg-primary);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 40px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 36px;
            margin-bottom: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Alert Messages */
        .alert {
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            animation: slideIn 0.5s ease;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fca5a5);
            color: #991b1b;
            border-color: #ef4444;
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.2);
        }

        .alert i {
            font-size: 22px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--primary);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: var(--transition);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon i {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .stat-icon.present {
            background: linear-gradient(135deg, var(--success), #0da271);
        }

        .stat-icon.late {
            background: linear-gradient(135deg, var(--warning), #d97706);
        }

        .stat-icon.absent {
            background: linear-gradient(135deg, var(--danger), #dc2626);
        }

        .stat-icon:not(.present):not(.late):not(.absent) {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-info h3 {
            font-size: 36px;
            font-weight: 800;
            margin: 0 0 5px 0;
            color: var(--text-primary);
            line-height: 1;
        }

        .stat-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Chart Container */
        .chart-container {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .chart-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .chart-container h2 {
            color: var(--text-primary);
            margin-bottom: 25px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }

        .chart-container h2 i {
            color: var(--primary);
        }

        /* Recent Activity */
        .recent-activity {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .recent-activity:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .recent-activity h2 {
            color: var(--text-primary);
            margin-bottom: 25px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }

        .recent-activity h2 i {
            color: var(--primary);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 16px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            animation: fadeIn 0.6s ease forwards;
            opacity: 0;
            transform: translateY(10px);
        }

        .activity-item:hover {
            background: var(--bg-card);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .activity-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .activity-details {
            flex: 1;
        }

        .activity-details p {
            margin: 0 0 8px 0;
            color: var(--text-primary);
            font-weight: 600;
            line-height: 1.4;
        }

        .activity-details p strong {
            color: var(--primary);
        }

        .activity-time {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .activity-time::before {
            content: '🕒';
        }

        /* Welcome Message */
        .welcome-message {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
        }

        .welcome-message::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s linear infinite;
            opacity: 0.3;
        }

        .welcome-message h2 {
            font-size: 28px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .welcome-message p {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .quick-action-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            color: var(--primary);
        }

        .quick-action-card i {
            font-size: 32px;
            color: var(--primary);
            transition: var(--transition);
        }

        .quick-action-card:hover i {
            transform: scale(1.1);
        }

        .quick-action-card span {
            font-weight: 600;
            font-size: 14px;
        }

        /* Animations */
        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }
            100% {
                transform: translate(100px, 100px) rotate(360deg);
            }
        }

        /* Stats Grid Animation */
        .stat-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Activity Items Animation */
        .activity-item:nth-child(1) { animation-delay: 0.1s; }
        .activity-item:nth-child(2) { animation-delay: 0.2s; }
        .activity-item:nth-child(3) { animation-delay: 0.3s; }
        .activity-item:nth-child(4) { animation-delay: 0.4s; }
        .activity-item:nth-child(5) { animation-delay: 0.5s; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 28px;
            }
            
            .page-header p {
                font-size: 16px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .stat-info h3 {
                font-size: 28px;
            }
            
            .welcome-message {
                padding: 20px;
            }
            
            .welcome-message h2 {
                font-size: 24px;
            }
            
            .chart-container,
            .recent-activity {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                gap: 20px;
            }
            
            .activity-item {
                padding: 15px;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .activity-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
            margin: 0;
        }

        /* System Status */
        .system-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid var(--border-color);
        }

        .status-item {
            text-align: center;
        }

        .status-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .status-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .status-value.online {
            color: var(--success);
        }

        .status-value.offline {
            color: var(--danger);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Welcome Message -->
        <div class="welcome-message">
            <h2>Bienvenue dans l'interface d'administration Ziris</h2>
            <p>Gérez efficacement les présences et les employés de votre entreprise</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="users.php" class="quick-action-card">
                <i class="fas fa-users"></i>
                <span>Employés</span>
            </a>
            <a href="presences.php" class="quick-action-card">
                <i class="fas fa-clipboard-check"></i>
                <span>Présences</span>
            </a>
            <a href="postes.php" class="quick-action-card">
                <i class="fas fa-briefcase"></i>
                <span>Postes</span>
            </a>
            <a href="qr_code.php" class="quick-action-card">
                <i class="fas fa-qrcode"></i>
                <span>QR Code</span>
            </a>
            <a href="parametres.php" class="quick-action-card">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p>Employés</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon present">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['present_today']; ?></h3>
                    <p>Présents aujourd'hui</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon late">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['late_today']; ?></h3>
                    <p>Retards aujourd'hui</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon absent">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['absent_today']; ?></h3>
                    <p>Absents aujourd'hui</p>
                </div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Chart Container -->
            <div class="chart-container">
                <h2><i class="fas fa-chart-line"></i> Présences cette semaine</h2>
                <canvas id="weeklyChart" style="width: 100%; height: 300px;"></canvas>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
                <h2><i class="fas fa-history"></i> Activité récente</h2>
                <div class="activity-list">
                    <?php if (empty($recentActivities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>Aucune activité récente</p>
                            <p style="font-size: 14px; margin-top: 10px;">Les pointages apparaîtront ici</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                                <div class="activity-details">
                                    <p><strong><?php echo htmlspecialchars($activity['user_name']); ?></strong> a pointé à <?php echo substr($activity['check_in_time'], 0, 5); ?></p>
                                    <span class="activity-time"><?php echo date('d/m/Y', strtotime($activity['date_presence'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="system-status">
            <div class="status-item">
                <div class="status-label">Base de données</div>
                <div class="status-value online">En ligne</div>
            </div>
            <div class="status-item">
                <div class="status-label">Système</div>
                <div class="status-value online">Opérationnel</div>
            </div>
            <div class="status-item">
                <div class="status-label">Dernière MAJ</div>
                <div class="status-value"><?php echo date('d/m/Y H:i'); ?></div>
            </div>
            <div class="status-item">
                <div class="status-label">Version</div>
                <div class="status-value">Ziris 2.0</div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/script.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Données pour le graphique (exemple)
            const ctx = document.getElementById('weeklyChart').getContext('2d');
            
            // Déterminer les couleurs en fonction du thème
            const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
            
            const gridColor = isDarkTheme ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            const textColor = isDarkTheme ? '#f8f9fa' : '#212529';
            const primaryColor = '#4361ee';
            const secondaryColor = '#7209b7';
            
            // Données de la semaine (exemple)
            const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            const presenceData = [45, 52, 48, 55, 53, 30, 15]; // Exemple de données
            
            const weeklyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: days,
                    datasets: [{
                        label: 'Présences',
                        data: presenceData,
                        borderColor: primaryColor,
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: primaryColor,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: textColor,
                                font: {
                                    size: 14,
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: isDarkTheme ? '#2d2d2d' : '#ffffff',
                            titleColor: textColor,
                            bodyColor: textColor,
                            borderColor: primaryColor,
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: gridColor,
                                drawBorder: false
                            },
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 12,
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor,
                                drawBorder: false
                            },
                            ticks: {
                                color: textColor,
                                font: {
                                    size: 12,
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                },
                                callback: function(value) {
                                    return value + ' pers.';
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });

            // Animation des cartes de stats
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });

            // Animation des activités récentes
            const activityItems = document.querySelectorAll('.activity-item');
            activityItems.forEach((item, index) => {
                item.style.animationDelay = (index * 0.1) + 's';
            });

            // Mettre à jour les données en temps réel
            function updateDashboardData() {
                // Ici, vous pourriez faire une requête AJAX pour récupérer les données mises à jour
                console.log('Mise à jour des données du dashboard...');
            }

            // Mettre à jour toutes les 5 minutes
            setInterval(updateDashboardData, 300000);

            // Ajouter un effet de pulsation aux icônes de stats
            statCards.forEach(card => {
                const icon = card.querySelector('.stat-icon i');
                setInterval(() => {
                    icon.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        icon.style.transform = 'scale(1)';
                    }, 300);
                }, 3000);
            });
        });

        // Animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.1);
                }
            }
            
            .stat-icon i {
                transition: transform 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>