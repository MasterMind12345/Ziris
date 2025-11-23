<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les informations de l'admin
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $email = $_POST['email'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Vérifier le mot de passe actuel si changement demandé
    if (!empty($new_password)) {
        if (password_verify($current_password, $admin['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET nom = ?, email = ?, password = ? WHERE id = ?");
                $success = $stmt->execute([$nom, $email, $hashed_password, $admin_id]);
            } else {
                $error_message = "Les nouveaux mots de passe ne correspondent pas.";
            }
        } else {
            $error_message = "Mot de passe actuel incorrect.";
        }
    } else {
        // Mise à jour sans changer le mot de passe
        $stmt = $pdo->prepare("UPDATE users SET nom = ?, email = ? WHERE id = ?");
        $success = $stmt->execute([$nom, $email, $admin_id]);
    }
    
    if (isset($success) && $success) {
        $success_message = "Profil mis à jour avec succès!";
        // Recharger les données
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
    } elseif (!isset($error_message)) {
        $error_message = "Erreur lors de la mise à jour du profil.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - Batobaye Admin</title>
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
            <h1>Profil Administrateur</h1>
            <p>Gérez vos informations personnelles</p>
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
        
        <div class="content-grid">
            <div class="form-container">
                <form method="POST">
                    <h2>Informations Personnelles</h2>
                    
                    <div class="form-group">
                        <label for="nom">Nom complet:</label>
                        <input type="text" id="nom" name="nom" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['nom'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Adresse email:</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                    </div>
                    
                    <h2 style="margin-top: 30px;">Changer le mot de passe</h2>
                    <small class="form-text" style="display: block; margin-bottom: 15px; color: var(--gray);">
                        Laissez ces champs vides si vous ne souhaitez pas changer le mot de passe.
                    </small>
                    
                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel:</label>
                        <input type="password" id="current_password" name="current_password" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe:</label>
                        <input type="password" id="new_password" name="new_password" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour le profil
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php
                        $initials = '';
                        if ($admin && isset($admin['nom'])) {
                            $names = explode(' ', $admin['nom']);
                            foreach ($names as $name) {
                                $initials .= strtoupper(substr($name, 0, 1));
                            }
                            $initials = substr($initials, 0, 2);
                        }
                        ?>
                        <div class="avatar-large"><?php echo $initials; ?></div>
                    </div>
                    
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($admin['nom'] ?? 'Admin'); ?></h3>
                        <p class="profile-email"><?php echo htmlspecialchars($admin['email'] ?? ''); ?></p>
                        <p class="profile-role">
                            <span class="badge badge-success">Administrateur</span>
                        </p>
                        
                        <div class="profile-stats">
                            <div class="stat">
                                <strong><?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE user_id = ?");
                                    $stmt->execute([$admin_id]);
                                    echo $stmt->fetch()['total'];
                                ?></strong>
                                <span>Présences</span>
                            </div>
                            <div class="stat">
                                <strong><?php 
                                    echo date('d/m/Y', strtotime($admin['created_at'] ?? 'now'));
                                ?></strong>
                                <span>Membre depuis</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="security-card">
                    <h3>Sécurité</h3>
                    <div class="security-item">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Dernière connexion</strong>
                            <p><?php echo date('d/m/Y à H:i', strtotime($admin['updated_at'] ?? 'now')); ?></p>
                        </div>
                    </div>
                    
                    <div class="security-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <strong>Email vérifié</strong>
                            <p>Oui</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="js/script.js"></script>
</body>
</html>

<style>
.profile-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.profile-card, .security-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    box-shadow: var(--shadow);
}

.profile-avatar {
    text-align: center;
    margin-bottom: 20px;
}

.avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    margin: 0 auto;
}

.profile-info h3 {
    text-align: center;
    margin-bottom: 5px;
}

.profile-email {
    text-align: center;
    color: var(--gray);
    margin-bottom: 15px;
}

.profile-role {
    text-align: center;
    margin-bottom: 20px;
}

.profile-stats {
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.profile-stats .stat {
    display: flex;
    flex-direction: column;
}

.profile-stats .stat strong {
    font-size: 18px;
    margin-bottom: 5px;
}

.profile-stats .stat span {
    font-size: 12px;
    color: var(--gray);
}

.security-card h3 {
    margin-bottom: 15px;
    font-size: 18px;
}

.security-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-light);
}

.security-item:last-child {
    border-bottom: none;
}

.security-item i {
    color: var(--primary);
    font-size: 18px;
}

.security-item strong {
    display: block;
    margin-bottom: 2px;
}

.security-item p {
    color: var(--gray);
    font-size: 14px;
    margin: 0;
}
</style>