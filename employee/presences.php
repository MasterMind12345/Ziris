<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et n'est pas admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
try {
    $stmt = $pdo->prepare("SELECT u.*, p.nom as poste_nom FROM users u LEFT JOIN postes p ON u.poste_id = p.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données.");
}

// Déterminer la période
$period = $_GET['period'] ?? 'monthly';
$date = $_GET['date'] ?? date('Y-m-d');

// Récupérer les présences selon la période
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

// Statistiques
try {
    // Total des présences
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_presences = $stmt->fetch()['total'];
    
    // Jours de retard
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE user_id = ? AND retard_minutes > 0");
    $stmt->execute([$user_id]);
    $total_retards = $stmt->fetch()['total'];
    
    // Heures totales travaillées
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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Présences - Batobaye</title>
    <link rel="stylesheet" href="css/employee.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- PWA Meta Tags -->
<meta name="theme-color" content="#4361ee"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Batobaye">
<link rel="apple-touch-icon" href="icons/icon-152x152.png">
<link rel="manifest" href="manifest.json">

<!-- PWA Configuration -->
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="pwa-install.css">
<script src="pwa-install.js" defer></script>
<meta name="theme-color" content="#4361ee"/>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Batobaye">

<!-- CSS existant -->
<link rel="stylesheet" href="../css/employee.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="employee-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-fingerprint"></i> Batobaye</h1>
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
    /* CSS pour l'interface employé */
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
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fb;
        color: var(--dark);
        line-height: 1.6;
    }

    /* Header */
    .employee-header {
        background: white;
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
    }

    .user-poste {
        font-size: 12px;
        color: var(--gray);
    }

    .logout-btn {
        color: var(--gray);
        text-decoration: none;
        font-size: 18px;
        transition: var(--transition);
    }

    .logout-btn:hover {
        color: var(--danger);
    }

    /* Navigation */
    .employee-nav {
        background: white;
        border-bottom: 1px solid #e9ecef;
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
        color: var(--gray);
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
    }

    /* Page Header */
    .page-header {
        margin-bottom: 30px;
    }

    .page-header h1 {
        font-size: 32px;
        margin-bottom: 8px;
        color: var(--dark);
    }

    .page-header p {
        color: var(--gray);
    }

    /* Stats Section */
    .stats-section {
        margin-bottom: 30px;
    }

    .stats-section h2 {
        margin-bottom: 20px;
        font-size: 24px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .stat-card {
        background: white;
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
    }

    .stat-info p {
        color: var(--gray);
        font-size: 14px;
    }

    /* Filters Section */
    .filters-section {
        margin-bottom: 30px;
    }

    .filters-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 25px;
        box-shadow: var(--shadow);
    }

    .filters-card h3 {
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--dark);
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
        color: var(--dark);
        font-size: 14px;
    }

    .form-control {
        padding: 12px 15px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: var(--transition);
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
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-dark);
    }

    .btn-secondary {
        background: var(--gray);
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
    }

    /* Presences Section */
    .presences-section {
        background: white;
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
        color: var(--dark);
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
        background: #f8f9fa;
        border-radius: 10px;
        border-left: 4px solid var(--primary);
        transition: var(--transition);
    }

    .presence-card.late {
        border-left-color: var(--warning);
    }

    .presence-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .presence-date {
        background: white;
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
        color: var(--gray);
        text-transform: uppercase;
    }

    .date-year {
        font-size: 12px;
        color: var(--gray);
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
        color: var(--gray);
        text-transform: uppercase;
        font-weight: 600;
    }

    .time-value {
        font-weight: 600;
        color: var(--dark);
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
        background: rgba(255, 193, 7, 0.1);
        color: #856404;
    }

    .retard-info.on-time {
        background: rgba(40, 167, 69, 0.1);
        color: #155724;
    }

    .location-info {
        background: rgba(0, 123, 255, 0.1);
        color: #004085;
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
        background: #d4edda;
        color: #155724;
    }

    .status-badge.in-progress {
        background: #fff3cd;
        color: #856404;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray);
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state h3 {
        margin-bottom: 10px;
        color: var(--dark);
    }

    /* Welcome Section (pour dashboard) */
    .welcome-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .welcome-content h1 {
        font-size: 32px;
        margin-bottom: 10px;
        color: var(--dark);
    }

    .welcome-content p {
        color: var(--gray);
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
        color: var(--gray);
        font-size: 14px;
        font-weight: normal;
    }

    .profile-card {
        background: white;
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
    }

    .profile-poste {
        color: var(--primary);
        font-weight: 600;
        margin-bottom: 5px;
    }

    .profile-email {
        color: var(--gray);
        font-size: 14px;
    }

    /* Pointage Section (pour dashboard) */
    .pointage-section {
        background: white;
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
    }

    .pointage-date {
        color: var(--gray);
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
    }

    .status-in-progress {
        background: #fff3cd;
        color: #856404;
    }

    .status-pending {
        background: #e2e3e5;
        color: #383d41;
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

    /* History Section (pour dashboard) */
    .history-section {
        background: white;
        border-radius: var(--border-radius);
        padding: 25px;
        box-shadow: var(--shadow);
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
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
    }

    .history-card h3 {
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--dark);
    }

    .history-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .history-item:last-child {
        border-bottom: none;
    }

    .history-time, .history-date {
        font-weight: 600;
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
        color: var(--gray);
        font-size: 14px;
    }

    .no-data {
        text-align: center;
        color: var(--gray);
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
        background: white;
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
    </script>
</body>
</html>