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

$absences_du_jour = getAbsencesDuJour();
$stats_absences = getStatsAbsences();
$employes_ponctuels = getEmployesPonctuels();
$presences_calendrier = getPresencesPourCalendrier();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Absences - Ziris Admin</title>
    
    <!-- Style CSS principal avec support du thème -->
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="/manifest.json">

    <!-- CSS supplémentaire pour le thème -->
    <style>
        /* Variables CSS pour les thèmes - alignées avec les autres pages */
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
            --border-radius: 12px;
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
            margin-bottom: 30px;
            padding: 25px;
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 16px;
            margin: 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition);
            animation: fadeIn 0.6s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--primary);
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .stat-icon.absent {
            background: linear-gradient(135deg, var(--danger), #dc2626);
        }

        .stat-icon.late {
            background: linear-gradient(135deg, var(--warning), #d97706);
        }

        .stat-icon.present {
            background: linear-gradient(135deg, var(--success), #0da271);
        }

        .stat-icon:not(.absent):not(.late):not(.present) {
            background: linear-gradient(135deg, var(--secondary), #8a2be2);
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
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
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Table Container */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            animation: fadeIn 0.8s ease;
        }

        .table-header {
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .table-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h2 i {
            color: var(--primary);
        }

        .table-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .form-control {
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .table-search {
            width: 250px;
            padding-left: 40px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 12px center;
            background-size: 16px;
        }

        .table-search:focus {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%234361ee' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
            border-color: var(--primary);
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        thead th {
            padding: 16px 12px;
            text-align: left;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        tbody tr {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        tbody tr:hover {
            background: var(--bg-secondary);
        }

        tbody td {
            padding: 16px 12px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .btn-icon:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 16px;
            margin: 0 0 20px 0;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Ranking List */
        .ranking-list {
            padding: 20px 0;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
            animation: fadeIn 0.6s ease forwards;
            opacity: 0;
            transform: translateY(10px);
        }

        .ranking-item:hover {
            background: var(--bg-secondary);
        }

        .ranking-item:last-child {
            border-bottom: none;
        }

        .ranking-item:nth-child(1) { animation-delay: 0.1s; }
        .ranking-item:nth-child(2) { animation-delay: 0.2s; }
        .ranking-item:nth-child(3) { animation-delay: 0.3s; }
        .ranking-item:nth-child(4) { animation-delay: 0.4s; }
        .ranking-item:nth-child(5) { animation-delay: 0.5s; }

        .rank-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-secondary);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 15px;
            flex-shrink: 0;
            border: 2px solid var(--border-color);
        }

        .ranking-item:nth-child(1) .rank-number {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #333;
            border-color: #FFD700;
        }

        .ranking-item:nth-child(2) .rank-number {
            background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
            color: white;
            border-color: #C0C0C0;
        }

        .ranking-item:nth-child(3) .rank-number {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            color: white;
            border-color: #CD7F32;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--text-primary);
        }

        .user-poste {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .ranking-stats {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            display: block;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-primary);
        }

        .stat-label {
            display: block;
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* Calendar Container */
        #calendar-container {
            padding: 20px;
        }

        /* Chart Container */
        .chart-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            height: 350px;
            position: relative;
            animation: fadeIn 0.8s ease;
        }

        .chart-container h2 {
            color: var(--text-primary);
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container h2 i {
            color: var(--primary);
        }

        /* Calendar Events */
        .fc-event {
            border: none !important;
            border-radius: 6px !important;
        }

        .fc-event-content {
            font-size: 12px;
            font-weight: 500;
            padding: 2px 4px;
        }

        .event-type-presence {
            color: #155724;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }

        .event-type-absence {
            color: #721c24;
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        }

        .event-type-retard {
            color: #856404;
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: var(--warning);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.2));
            color: var(--info);
            border-color: rgba(59, 130, 246, 0.3);
        }

        /* Animations */
        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Responsive Design */
        @media (max-width: 768px) {
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
                font-size: 24px;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .table-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .table-search {
                width: 100%;
            }
            
            .ranking-stats {
                flex-direction: column;
                gap: 8px;
            }
            
            .chart-container {
                height: 300px;
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .content-grid {
                gap: 20px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .ranking-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .rank-number {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .ranking-stats {
                flex-direction: row;
                justify-content: center;
                width: 100%;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Print Styles */
        @media print {
            .table-actions,
            .btn,
            .btn-icon {
                display: none !important;
            }
            
            .table-container {
                box-shadow: none;
                border: 1px solid #000;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
            
            [data-theme] {
                --bg-primary: white !important;
                --bg-secondary: #f8f9fa !important;
                --bg-card: white !important;
                --text-primary: black !important;
                --text-secondary: #666 !important;
                --border-color: #ddd !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-user-times"></i> Gestion des Absences</h1>
            <p>Surveillez et analysez les absences des employés</p>
        </div>
        
        <!-- Statistiques des absences -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon absent">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['absences_aujourdhui']; ?></h3>
                    <p>Absences aujourd'hui</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon late">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['absences_semaine']; ?></h3>
                    <p>Absences cette semaine</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon present">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['taux_absence']; ?>%</h3>
                    <p>Taux d'absence mensuel</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--secondary), #8a2be2);">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_absences['retard_moyen_heures']; ?>h</h3>
                    <p>Retard moyen</p>
                </div>
            </div>
        </div>
        
        <!-- Content Grid: Absences du jour + Top employés -->
        <div class="content-grid">
            <!-- Section des absences du jour -->
            <div class="table-container">
                <div class="table-header">
                    <h2><i class="fas fa-calendar-day"></i> Absences du jour (<?php echo date('d/m/Y'); ?>)</h2>
                    <div class="table-actions">
                        <input type="text" class="form-control table-search" placeholder="Rechercher un employé..." id="searchAbsences">
                        <div style="display: flex; gap: 10px;">
                            <button class="btn btn-secondary" onclick="clearSearch()">
                                <i class="fas fa-times"></i> Effacer
                            </button>
                            <button class="btn btn-primary" onclick="exportAbsencesCSV()">
                                <i class="fas fa-file-export"></i> Exporter
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (count($absences_du_jour) > 0): ?>
                <table id="absencesTable">
                    <thead>
                        <tr>
                            <th style=" background: black; "><i class="fas fa-user"></i> Employé</th>
                            <th style=" background: black; "><i class="fas fa-briefcase"></i> Poste</th>
                            <th style=" background: black; "><i class="fas fa-envelope"></i> Email</th>
                            <th style=" background: black; "><i class="fas fa-calendar-check"></i> Dernière présence</th>
                            <th style=" background: black; "><i class="fas fa-clock"></i> Retard moyen</th>
                            <th style=" background: black; "><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences_du_jour as $index => $absence): ?>
                        <tr class="fade-in" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                            <td>
                                <div class="user-info">
                                    <div class="avatar-small" style="background: <?php echo getRandomColor($absence['id']); ?>">
                                        <?php echo getInitials($absence['nom']); ?>
                                    </div>
                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($absence['nom']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--bg-secondary); color: var(--text-primary);">
                                    <?php echo htmlspecialchars($absence['poste'] ?? 'Non défini'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--text-secondary);"><?php echo htmlspecialchars($absence['email']); ?></span>
                            </td>
                            <td>
                                <?php if ($absence['derniere_presence']): ?>
                                    <div style="font-weight: 600; color: var(--primary);">
                                        <?php echo date('d/m/Y', strtotime($absence['derniere_presence'])); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Jamais
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($absence['retard_moyen_heures'] > 0): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock"></i> <?php echo $absence['retard_moyen_heures']; ?>h
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Ponctuel
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon" title="Voir profil" onclick="voirProfil(<?php echo $absence['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" title="Envoyer rappel" onclick="envoyerRappel(<?php echo $absence['id']; ?>)">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                    <button class="btn-icon" title="Historique" onclick="voirHistorique(<?php echo $absence['id']; ?>)">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Table Footer -->
                <div style="padding: 15px 25px; border-top: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); font-size: 14px;">
                    <i class="fas fa-info-circle"></i> 
                    <?php echo count($absences_du_jour); ?> absence(s) aujourd'hui
                </div>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                    <h3>Aucune absence aujourd'hui</h3>
                    <p>Tous les employés sont présents. Excellent travail !</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Section des employés ponctuels -->
            <div class="table-container">
                <div class="table-header">
                    <h2><i class="fas fa-trophy"></i> Top 5 des employés ponctuels</h2>
                    <div class="table-actions">
                        <select class="form-control" id="periodePonctualite" onchange="changerPeriodePonctualite(this.value)">
                            <option value="mois">Ce mois</option>
                            <option value="semaine">Cette semaine</option>
                            <option value="trimestre">Ce trimestre</option>
                        </select>
                    </div>
                </div>
                
                <?php if (count($employes_ponctuels) > 0): ?>
                <div class="ranking-list">
                    <?php $rank = 1; ?>
                    <?php foreach ($employes_ponctuels as $employe): ?>
                    <div class="ranking-item">
                        <div class="rank-number"><?php echo $rank; ?></div>
                        <div class="user-info">
                            <div class="avatar-small" style="background: <?php echo getRandomColor($employe['id']); ?>">
                                <?php echo getInitials($employe['nom']); ?>
                            </div>
                            <div class="user-details">
                                <div class="user-name"><?php echo htmlspecialchars($employe['nom']); ?></div>
                                <div class="user-poste"><?php echo htmlspecialchars($employe['poste'] ?? 'Non défini'); ?></div>
                            </div>
                        </div>
                        <div class="ranking-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $employe['taux_presence']; ?>%</span>
                                <span class="stat-label">Présence</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $employe['retard_moyen_heures']; ?>h</span>
                                <span class="stat-label">Retard moyen</span>
                            </div>
                        </div>
                    </div>
                    <?php $rank++; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>Aucune donnée de ponctualité</h3>
                    <p>Les statistiques seront disponibles après quelques jours</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Calendrier des présences/absences -->
        <div class="table-container" style="margin-top: 30px;">
            <div class="table-header">
                <h2><i class="fas fa-calendar-alt"></i> Calendrier des Présences et Absences</h2>
                <div class="table-actions">
                    <select class="form-control" id="selectEmploye" onchange="changerEmployeCalendrier(this.value)">
                        <option value="tous">Tous les employés</option>
                        <?php 
                        $employes = getAllUsers();
                        foreach ($employes as $employe): 
                        ?>
                        <option value="<?php echo $employe['id']; ?>"><?php echo htmlspecialchars($employe['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div id="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
        
        <!-- Statistiques détaillées -->
        <div class="content-grid" style="margin-top: 30px;">
            <!-- Graphique des tendances d'absences -->
            <div class="chart-container">
                <h2><i class="fas fa-chart-line"></i> Tendances des Absences (30 derniers jours)</h2>
                <canvas id="tendanceAbsencesChart"></canvas>
            </div>
            
            <!-- Graphique des motifs d'absence -->
            <div class="chart-container">
                <h2><i class="fas fa-chart-pie"></i> Répartition des Absences</h2>
                <canvas id="repartitionAbsencesChart"></canvas>
            </div>
        </div>
        
        <!-- Graphique des retards par département -->
        <div class="table-container" style="margin-top: 30px;">
            <div class="table-header">
                <h2><i class="fas fa-chart-bar"></i> Retards par Département</h2>
            </div>
            <div class="chart-container" style="height: 300px;">
                <canvas id="retardsDepartementChart"></canvas>
            </div>
        </div>
    </main>
    
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js'></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Appliquer le thème au chargement
        applyThemeStyles();
        
        // Configuration du calendrier avec support du thème
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'fr',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: <?php echo json_encode($presences_calendrier); ?>,
            eventClick: function(info) {
                afficherDetailsEvenement(info.event);
            },
            eventContent: function(arg) {
                let element = document.createElement('div');
                element.className = 'fc-event-content';
                element.innerHTML = `
                    <div class="event-type-${arg.event.extendedProps.type}">
                        <i class="${arg.event.extendedProps.icon}"></i>
                        ${arg.event.title}
                    </div>
                `;
                return { domNodes: [element] };
            },
            themeSystem: 'standard',
            eventColor: getThemeEventColor()
        });
        calendar.render();
        
        // Initialiser les graphiques avec support du thème
        initialiserGraphiques();
        
        // Recherche en temps réel dans le tableau des absences
        document.getElementById('searchAbsences').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#absencesTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateAbsencesCount(visibleCount);
        });
        
        // Animation des cartes de stats
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.1) + 's';
        });
        
        // Animation des éléments du classement
        const rankingItems = document.querySelectorAll('.ranking-item');
        rankingItems.forEach((item, index) => {
            item.style.animationDelay = (index * 0.1) + 's';
        });
    });
    
    // Fonction pour appliquer les styles du thème
    function applyThemeStyles() {
        const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
        
        // Mettre à jour les couleurs pour FullCalendar si en mode sombre
        if (isDarkTheme) {
            // Ajuster le calendrier pour le thème sombre
            const calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                calendarEl.classList.add('fc-theme-dark');
            }
        }
        
        // Mettre à jour les couleurs des graphiques
        updateChartColors();
    }
    
    // Fonction pour obtenir les couleurs des événements selon le thème
    function getThemeEventColor() {
        const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
        
        if (isDarkTheme) {
            return {
                presence: '#155724',
                absence: '#721c24',
                retard: '#856404'
            };
        }
        
        return {
            presence: '#d4edda',
            absence: '#f8d7da',
            retard: '#fff3cd'
        };
    }
    
    // Fonction pour afficher les détails d'un événement du calendrier
    function afficherDetailsEvenement(event) {
        const type = event.extendedProps.type;
        const employe = event.title;
        const date = event.start.toLocaleDateString('fr-FR');
        const heure = event.extendedProps.heure || '';
        const retard = event.extendedProps.retard || 0;
        
        let message = '';
        let icon = '';
        
        if (type === 'presence') {
            message = `${employe} était présent le ${date}`;
            if (heure) {
                message += ` à ${heure}`;
            }
            icon = 'success';
        } else if (type === 'absence') {
            message = `${employe} était absent le ${date}`;
            icon = 'error';
        } else if (type === 'retard') {
            const heuresRetard = Math.floor(retard / 60);
            const minutesRetard = retard % 60;
            const retardFormate = heuresRetard > 0 ? 
                `${heuresRetard}h${minutesRetard.toString().padStart(2, '0')}min` : 
                `${minutesRetard}min`;
                
            message = `${employe} était en retard le ${date} (${retardFormate})`;
            icon = 'warning';
        }
        
        showNotification(message, icon);
    }
    
    // Fonction pour changer l'employé affiché dans le calendrier
    function changerEmployeCalendrier(employeId) {
        // Recharger les données du calendrier pour cet employé
        console.log('Changement d\'employé:', employeId);
        showNotification(`Chargement des données pour l'employé sélectionné...`, 'info');
    }
    
    // Fonction pour changer la période des statistiques de ponctualité
    function changerPeriodePonctualite(periode) {
        // Recharger les données de ponctualité
        console.log('Changement de période:', periode);
        showNotification(`Chargement des données pour ${periode === 'mois' ? 'ce mois' : periode === 'semaine' ? 'cette semaine' : 'ce trimestre'}...`, 'info');
    }
    
    // Fonction pour initialiser les graphiques avec support du thème
    function initialiserGraphiques() {
        const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
        const gridColor = isDarkTheme ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';
        const textColor = isDarkTheme ? '#f8f9fa' : '#212529';
        
        // Graphique de tendance des absences (ligne)
        const ctx1 = document.getElementById('tendanceAbsencesChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: ['J-30', 'J-25', 'J-20', 'J-15', 'J-10', 'J-5', 'Aujourd\'hui'],
                datasets: [{
                    label: 'Nombre d\'absences',
                    data: [8, 12, 6, 15, 9, 11, <?php echo $stats_absences['absences_aujourdhui']; ?>],
                    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--danger'),
                    backgroundColor: isDarkTheme ? 'rgba(239, 68, 68, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--danger'),
                    pointBorderColor: isDarkTheme ? '#2d2d2d' : '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    },
                    x: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    }
                }
            }
        });
        
        // Graphique de répartition des absences (doughnut)
        const ctx2 = document.getElementById('repartitionAbsencesChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Maladie', 'Congés', 'Formation', 'Personnel', 'Autre'],
                datasets: [{
                    data: [45, 25, 15, 10, 5],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(76, 201, 240, 0.8)',
                        'rgba(247, 37, 133, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(67, 97, 238, 1)',
                        'rgba(76, 201, 240, 1)',
                        'rgba(247, 37, 133, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                }
            }
        });
        
        // Graphique des retards par département (barres horizontales)
        const ctx3 = document.getElementById('retardsDepartementChart').getContext('2d');
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: ['Commercial', 'Développement', 'Design', 'RH', 'Administration'],
                datasets: [{
                    label: 'Retard moyen (heures)',
                    data: [1.2, 0.8, 0.5, 0.3, 0.1],
                    backgroundColor: [
                        'rgba(247, 37, 133, 0.8)',
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(76, 201, 240, 0.8)',
                        'rgba(42, 157, 143, 0.8)',
                        'rgba(233, 196, 106, 0.8)'
                    ],
                    borderColor: [
                        'rgba(247, 37, 133, 1)',
                        'rgba(67, 97, 238, 1)',
                        'rgba(76, 201, 240, 1)',
                        'rgba(42, 157, 143, 1)',
                        'rgba(233, 196, 106, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Heures de retard moyen',
                            color: textColor
                        },
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    },
                    y: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    }
                }
            }
        });
    }
    
    // Fonction pour mettre à jour les couleurs des graphiques selon le thème
    function updateChartColors() {
        // Cette fonction pourrait être utilisée pour mettre à jour les graphiques
        // lorsqu'on change de thème dynamiquement
        console.log('Mise à jour des couleurs des graphiques pour le thème');
    }
    
    // Fonction pour voir le profil d'un employé
    function voirProfil(employeId) {
        // Rediriger vers la page de profil de l'employé
        window.location.href = `profile_employe.php?id=${employeId}`;
    }
    
    // Fonction pour voir l'historique d'un employé
    function voirHistorique(employeId) {
        // Rediriger vers la page d'historique de l'employé
        window.location.href = `historique_employe.php?id=${employeId}`;
    }
    
    // Fonction pour envoyer un rappel à un employé absent
    function envoyerRappel(employeId) {
        if (confirm('Voulez-vous envoyer un rappel à cet employé ?')) {
            // Simulation d'envoi de rappel
            setTimeout(() => {
                showNotification('Rappel envoyé avec succès', 'success');
            }, 500);
            
            // Version réelle avec AJAX:
            /*
            fetch('ajax/envoyer_rappel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `employe_id=${employeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Rappel envoyé avec succès', 'success');
                } else {
                    showNotification('Erreur lors de l\'envoi du rappel', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de l\'envoi du rappel', 'error');
            });
            */
        }
    }
    
    // Fonction pour effacer la recherche
    function clearSearch() {
        document.getElementById('searchAbsences').value = '';
        const rows = document.querySelectorAll('#absencesTable tbody tr');
        rows.forEach(row => {
            row.style.display = '';
        });
        updateAbsencesCount(rows.length);
        showNotification('Recherche effacée', 'info');
    }
    
    // Fonction pour mettre à jour le compteur d'absences
    function updateAbsencesCount(count) {
        const footer = document.querySelector('#absencesTable')?.nextElementSibling;
        if (footer) {
            footer.innerHTML = `<i class="fas fa-info-circle"></i> ${count} absence(s) aujourd'hui`;
        }
    }
    
    // Fonction pour exporter les absences en CSV
    function exportAbsencesCSV() {
        const table = document.getElementById('absencesTable');
        const rows = table.querySelectorAll('tr');
        const csv = [];
        
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('th, td');
            
            cells.forEach(cell => {
                // Nettoyer le texte des icônes
                let cellText = cell.textContent
                    .replace(/[\n\r]+/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
                rowData.push('"' + cellText.replace(/"/g, '""') + '"');
            });
            
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const filename = `absences-<?php echo date('Y-m-d'); ?>.csv`;
        
        if (navigator.msSaveBlob) {
            navigator.msSaveBlob(blob, filename);
        } else {
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        showNotification('Export CSV réussi', 'success');
    }
    
    // Fonction utilitaire pour afficher des notifications
    function showNotification(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-error' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} fade-in`;
        alertDiv.style.animationDelay = '0s';
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 
                             type === 'error' ? 'exclamation-circle' : 
                             type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;
        
        const mainContent = document.querySelector('.main-content');
        const pageHeader = document.querySelector('.page-header');
        mainContent.insertBefore(alertDiv, pageHeader.nextSibling);
        
        // Supprimer l'alerte après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    // Gestion du thème auto
    function handleThemeChange() {
        const theme = document.documentElement.getAttribute('data-theme');
        if (theme === 'auto') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
            applyThemeStyles();
        }
    }
    
    // Écouter les changements de thème système
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleThemeChange);
    
    // Appliquer le thème auto au chargement
    handleThemeChange();
    </script>
</body>
</html>