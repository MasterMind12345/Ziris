<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Déterminer la période affichée
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Récupérer les présences selon la période
$presences = getPresencesByPeriod($period, $date);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Batobaye Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- PWA Meta Tags -->
<meta name="theme-color" content="#4361ee"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Batobaye">
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
<meta name="apple-mobile-web-app-title" content="Batobaye">

<!-- CSS existant -->
<link rel="stylesheet" href="../css/employee.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Présences</h1>
            <p>Consultez les présences des employés</p>
        </div>
        
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label>Période:</label>
                    <select name="period" class="form-control" onchange="this.form.submit()">
                        <option value="daily" <?php echo $period == 'daily' ? 'selected' : ''; ?>>Journalier</option>
                        <option value="weekly" <?php echo $period == 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                        <option value="monthly" <?php echo $period == 'monthly' ? 'selected' : ''; ?>>Mensuel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date:</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h2>Liste des Présences</h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher...">
                    <button class="btn btn-primary" onclick="exportToCSV('presencesTable', 'presences-batobaye.csv')">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                </div>
            </div>
            
            <table id="presencesTable">
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Poste</th>
                        <th>Date</th>
                        <th>Heure d'arrivée</th>
                        <th>Heure de départ</th>
                        <th>Retard</th>
                        <th>Lieu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presences as $presence): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($presence['nom']); ?></td>
                        <td><?php echo htmlspecialchars($presence['poste'] ?? 'Non défini'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($presence['date_presence'])); ?></td>
                        <td><?php echo $presence['heure_debut_reel']; ?></td>
                        <td><?php echo $presence['heure_fin_reel'] ?: 'Non pointé'; ?></td>
                        <td>
                            <?php if ($presence['retard_minutes'] > 0): ?>
                            <span class="badge badge-warning"><?php echo $presence['retard_minutes']; ?> min</span>
                            <?php else: ?>
                            <span class="badge badge-success">À l'heure</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($presence['lieu'] ?? 'Non spécifié'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <script src="js/script.js"></script>
</body>
</html>