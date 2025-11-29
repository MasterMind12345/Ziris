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
    $stmt = $pdo->prepare("SELECT u.*, p.nom as poste_nom FROM users u LEFT JOIN postes p ON u.poste_id = p.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données.");
}

$period = $_GET['period'] ?? 'monthly';
$date = $_GET['date'] ?? date('Y-m-d');

try {
    switch ($period) {
        case 'daily':
            $stmt = $pdo->prepare("
                SELECT * FROM presences 
                WHERE user_id = ? AND date_presence = ?
                ORDER BY heure_debut_reel DESC
            ");
            $stmt->execute([$user_id, $date]);
            break;
            
        case 'weekly':
            $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            $stmt = $pdo->prepare("
                SELECT * FROM presences 
                WHERE user_id = ? AND date_presence BETWEEN ? AND ?
                ORDER BY date_presence DESC, heure_debut_reel DESC
            ");
            $stmt->execute([$user_id, $week_start, $week_end]);
            break;
            
        case 'monthly':
            $month_start = date('Y-m-01', strtotime($date));
            $month_end = date('Y-m-t', strtotime($date));
            $stmt = $pdo->prepare("
                SELECT * FROM presences 
                WHERE user_id = ? AND date_presence BETWEEN ? AND ?
                ORDER BY date_presence DESC, heure_debut_reel DESC
            ");
            $stmt->execute([$user_id, $month_start, $month_end]);
            break;
            
        default:
            $presences = [];
    }
    
    $presences = $stmt->fetchAll();
} catch(PDOException $e) {
    $presences = [];
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_presences = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE user_id = ? AND retard_minutes > 0");
    $stmt->execute([$user_id]);
    $total_retards = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(heure_fin_reel, heure_debut_reel)))) as total_heures
        FROM presences 
        WHERE user_id = ? AND heure_fin_reel IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $total_heures = $stmt->fetch()['total_heures'] ?? '00:00:00';
    
} catch(PDOException $e) {
    $total_presences = 0;
    $total_retards = 0;
    $total_heures = '00:00:00';
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Présences - Ziris</title>
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
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de Bord</span>
            </a>
            <a href="presences.php" class="nav-item active">
                <i class="fas fa-history"></i>
                <span>Mes Présences</span>
            </a>
            <a href="pointage.php" class="nav-item">
                <i class="fas fa-qrcode"></i>
                <span>Pointer</span>
            </a>
            <a href="param.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
            <a href="aide.php" class="nav-item">
                <i class="fas fa-question-circle"></i>
                <span>Aide</span>
            </a>
        </div>
    </nav>

    <main class="employee-main">
        <div class="page-header">
            <h1>Mes Présences</h1>
            <p>Historique complet de vos pointages</p>
        </div>

        <!-- Statistiques -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_presences; ?></h3>
                        <p>Jours Pointés</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_retards; ?></h3>
                        <p>Jours de Retard</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-business-time"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo substr($total_heures, 0, 5); ?></h3>
                        <p>Heures Total</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_presences > 0 ? round((($total_presences - $total_retards) / $total_presences) * 100) : 100; ?>%</h3>
                        <p>Taux de Ponctualité</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters-section">
            <div class="filters-card">
                <h3><i class="fas fa-filter"></i> Filtres</h3>
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Période:</label>
                        <select name="period" class="form-control" onchange="this.form.submit()">
                            <option value="daily" <?php echo $period == 'daily' ? 'selected' : ''; ?>>Journalier</option>
                            <option value="weekly" <?php echo $period == 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                            <option value="monthly" <?php echo $period == 'monthly' ? 'selected' : ''; ?>>Mensuel</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date:</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" onchange="this.form.submit()">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Appliquer
                    </button>
                </form>
            </div>
        </div>

        <!-- Liste des présences -->
        <div class="presences-section">
            <div class="section-header">
                <h2>Historique des Pointages</h2>
                <div class="export-actions">
                    <button class="btn btn-secondary" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Exporter PDF
                    </button>
                </div>
            </div>

            <div class="presences-list">
                <?php if (empty($presences)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Aucun pointage trouvé</h3>
                        <p>Aucun pointage enregistré pour la période sélectionnée.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($presences as $presence): ?>
                        <div class="presence-card <?php echo $presence['retard_minutes'] > 0 ? 'late' : ''; ?>">
                            <div class="presence-date">
                                <div class="date-day"><?php echo date('d', strtotime($presence['date_presence'])); ?></div>
                                <div class="date-month"><?php echo date('M', strtotime($presence['date_presence'])); ?></div>
                                <div class="date-year"><?php echo date('Y', strtotime($presence['date_presence'])); ?></div>
                            </div>
                            <div class="presence-info">
                                <div class="presence-times">
                                    <div class="time-block">
                                        <span class="time-label">Début:</span>
                                        <span class="time-value"><?php echo $presence['heure_debut_reel']; ?></span>
                                    </div>
                                    <?php if ($presence['heure_fin_reel']): ?>
                                        <div class="time-block">
                                            <span class="time-label">Fin:</span>
                                            <span class="time-value"><?php echo $presence['heure_fin_reel']; ?></span>
                                        </div>
                                        <div class="time-block">
                                            <span class="time-label">Durée:</span>
                                            <span class="time-value">
                                                <?php
                                                $debut = strtotime($presence['heure_debut_reel']);
                                                $fin = strtotime($presence['heure_fin_reel']);
                                                $duree = $fin - $debut;
                                                echo gmdate('H:i', $duree);
                                                ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="time-block">
                                            <span class="time-label">Statut:</span>
                                            <span class="status-badge in-progress">En cours</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="presence-meta">
                                    <?php if ($presence['retard_minutes'] > 0): ?>
                                        <div class="retard-info">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span>Retard: <?php echo $presence['retard_minutes']; ?> minutes</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="retard-info on-time">
                                            <i class="fas fa-check-circle"></i>
                                            <span>À l'heure</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($presence['lieu']): ?>
                                        <div class="location-info">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($presence['lieu']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="presence-status">
                                <?php if ($presence['heure_fin_reel']): ?>
                                    <span class="status-badge completed">Complété</span>
                                <?php else: ?>
                                    <span class="status-badge in-progress">En cours</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

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

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .page-header p {
            color: var(--text-secondary);
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 30px;
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

        /* Filters Section */
        .filters-section {
            margin-bottom: 30px;
        }

        .filters-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .filters-card h3 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Presences Section */
        .presences-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-header h2 {
            font-size: 24px;
            color: var(--text-primary);
        }

        .presences-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .presence-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .presence-card.late {
            border-left-color: var(--warning);
        }

        .presence-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background: var(--bg-card);
        }

        .presence-date {
            background: var(--bg-primary);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            min-width: 80px;
        }

        .date-day {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
        }

        .date-month {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .date-year {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .presence-info {
            flex: 1;
        }

        .presence-times {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 10px;
        }

        .time-block {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .time-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
        }

        .time-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .presence-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .retard-info, .location-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .retard-info {
            background: rgba(247, 37, 133, 0.1);
            color: var(--warning);
        }

        .retard-info.on-time {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .location-info {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .presence-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.completed {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .status-badge.in-progress {
            background: rgba(247, 37, 133, 0.1);
            color: var(--warning);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--text-primary);
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

            .nav-content {
                overflow-x: auto;
                padding: 0 20px;
            }

            .presence-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .presence-date {
                align-self: flex-start;
            }

            .presence-status {
                align-self: flex-end;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .presence-times {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        function exportToPDF() {
            alert('Fonctionnalité d\'export PDF à implémenter');
            // Implémentation future avec une bibliothèque PDF
        }

        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .presence-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>