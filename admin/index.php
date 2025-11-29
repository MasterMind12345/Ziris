<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$missing_tables = checkDatabaseTables();
if (!empty($missing_tables)) {
    $error_message = "Tables manquantes dans la base de données: " . implode(', ', $missing_tables);
}

$stats = getDashboardStats();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ziris Admin</title>
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

<!-- CSS existant -->
<link rel="stylesheet" href="../css/employee.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Tableau de Bord</h1>
            <p>Bienvenue dans l'interface d'administration Ziris</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error" style="background: #fee; color: #c33; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
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
        
        <div class="content-grid">
            <div class="chart-container">
                <h2>Présences cette semaine</h2>
                <canvas id="weeklyChart"></canvas>
            </div>
            
            <div class="recent-activity">
                <h2>Activité récente</h2>
                <div class="activity-list">
                    <?php
                    $recentActivities = getRecentActivities();
                    if (empty($recentActivities)) {
                        echo '<div class="activity-item">';
                        echo '<div class="activity-icon"><i class="fas fa-info-circle"></i></div>';
                        echo '<div class="activity-details">';
                        echo '<p>Aucune activité récente</p>';
                        echo '<span class="activity-time">Les pointages apparaitront ici</span>';
                        echo '</div></div>';
                    } else {
                        foreach ($recentActivities as $activity) {
                            echo '<div class="activity-item">';
                            echo '<div class="activity-icon"><i class="fas fa-user-clock"></i></div>';
                            echo '<div class="activity-details">';
                            echo '<p><strong>' . htmlspecialchars($activity['user_name']) . '</strong> a pointé à ' . $activity['check_in_time'] . '</p>';
                            echo '<span class="activity-time">' . date('d/m/Y', strtotime($activity['date_presence'])) . '</span>';
                            echo '</div></div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/script.js"></script>
</body>
</html>