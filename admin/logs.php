<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les logs de présence
$logs = getPresenceLogs(100);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - Batobaye Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Historique des Présences</h1>
            <p>Consultez l'historique complet des pointages</p>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h2>Logs des Présences</h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher...">
                    <button class="btn btn-primary" onclick="exportToCSV('logsTable', 'historique-batobaye.csv')">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                </div>
            </div>
            
            <table id="logsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employé</th>
                        <th>Poste</th>
                        <th>Heure d'arrivée</th>
                        <th>Heure de départ</th>
                        <th>Retard</th>
                        <th>Lieu</th>
                        <th>Date d'enregistrement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($log['date_presence'])); ?></td>
                        <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                        <td><?php echo htmlspecialchars($log['poste'] ?? 'Non défini'); ?></td>
                        <td><?php echo $log['heure_debut_reel']; ?></td>
                        <td><?php echo $log['heure_fin_reel'] ?: 'Non pointé'; ?></td>
                        <td>
                            <?php if ($log['retard_minutes'] > 0): ?>
                            <span class="badge badge-warning"><?php echo $log['retard_minutes']; ?> min</span>
                            <?php else: ?>
                            <span class="badge badge-success">À l'heure</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['lieu'] ?? 'Non spécifié'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <script src="js/script.js"></script>
</body>
</html>