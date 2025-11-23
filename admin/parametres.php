<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les paramètres actuels
$settings = getSystemSettings();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $heure_debut = $_POST['heure_debut_normal'] ?? '';
    $heure_fin = $_POST['heure_fin_normal'] ?? '';
    
    if (updateSystemSettings($heure_debut, $heure_fin)) {
        $success_message = "Paramètres mis à jour avec succès!";
        // Recharger les paramètres
        $settings = getSystemSettings();
    } else {
        $error_message = "Erreur lors de la mise à jour des paramètres.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Batobaye Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

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
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Paramètres du Système</h1>
            <p>Configurez les paramètres généraux de l'application</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <h2>Heures de Travail Normales</h2>
                
                <div class="form-group">
                    <label for="heure_debut_normal">Heure de début normale:</label>
                    <input type="time" id="heure_debut_normal" name="heure_debut_normal" 
                           class="form-control" value="<?php echo $settings['heure_debut_normal'] ?? '08:00'; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="heure_fin_normal">Heure de fin normale:</label>
                    <input type="time" id="heure_fin_normal" name="heure_fin_normal" 
                           class="form-control" value="<?php echo $settings['heure_fin_normal'] ?? '17:00'; ?>" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Réinitialiser
                    </button>
                </div>
            </form>
        </div>
        
        <div class="form-container" style="margin-top: 30px;">
            <h2>Informations Système</h2>
            
            <div class="system-info">
                <div class="info-item">
                    <label>Version de l'application:</label>
                    <span>Batobaye v1.0.0</span>
                </div>
                
                <div class="info-item">
                    <label>Dernière mise à jour:</label>
                    <span><?php echo date('d/m/Y à H:i', strtotime($settings['updated_at'] ?? 'now')); ?></span>
                </div>
                
                <div class="info-item">
                    <label>Nombre total d'utilisateurs:</label>
                    <span><?php 
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
                        echo $stmt->fetch()['total'];
                    ?></span>
                </div>
                
                <div class="info-item">
                    <label>Présences enregistrées aujourd'hui:</label>
                    <span><?php 
                        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE()");
                        $stmt->execute();
                        echo $stmt->fetch()['total'];
                    ?></span>
                </div>
            </div>
        </div>
    </main>
    
    <script src="js/script.js"></script>
</body>
</html>

<style>
.alert {
    padding: 15px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.system-info {
    display: grid;
    gap: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-light);
}

.info-item:last-child {
    border-bottom: none;
}

.info-item label {
    font-weight: 500;
    color: var(--gray);
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}
</style>