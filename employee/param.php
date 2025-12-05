<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT u.*, p.nom as poste_nom FROM users u LEFT JOIN postes p ON u.poste_id = p.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données.");
}

// Récupérer les préférences utilisateur
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

// Traitement du formulaire de mise à jour des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = $_POST['theme'] ?? 'light';
    $font_size = $_POST['font_size'] ?? 'medium';
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $accessibility_mode = isset($_POST['accessibility_mode']) ? 1 : 0;
    
    try {
        // Vérifier d'abord si l'utilisateur a déjà des préférences
        $stmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $existing_preferences = $stmt->fetch();
        
        if ($existing_preferences) {
            // Mettre à jour les préférences existantes
            $stmt = $pdo->prepare("
                UPDATE user_preferences 
                SET theme = ?, font_size = ?, notifications = ?, accessibility_mode = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = ?
            ");
            $result = $stmt->execute([$theme, $font_size, $notifications, $accessibility_mode, $user_id]);
            
            if ($result) {
                // Recharger les préférences depuis la base
                $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $preferences = $stmt->fetch();
                
                $message = "Paramètres mis à jour avec succès!";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la mise à jour des paramètres";
                $message_type = "error";
            }
        } else {
            // Insérer de nouvelles préférences
            $stmt = $pdo->prepare("
                INSERT INTO user_preferences (user_id, theme, font_size, notifications, accessibility_mode) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([$user_id, $theme, $font_size, $notifications, $accessibility_mode]);
            
            if ($result) {
                // Recharger les préférences depuis la base
                $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $preferences = $stmt->fetch();
                
                $message = "Paramètres mis à jour avec succès!";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la création des paramètres";
                $message_type = "error";
            }
        }
        
    } catch(PDOException $e) {
        $message = "Erreur lors de la mise à jour des paramètres: " . $e->getMessage();
        $message_type = "error";
    }
}

// Déterminer le thème actuel pour l'affichage - DIRECTEMENT depuis la BD
$currentTheme = $preferences['theme'] ?? 'light';
?>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Ziris</title>
    
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
            <a href="param.php" class="nav-item active">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
        </div>
    </nav>

    <main class="employee-main">
        <div class="page-header">
            <h1>Paramètres</h1>
            <p>Personnalisez votre expérience Ziris</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="settingsForm">
            <div class="settings-container">
                <!-- Section Apparence -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-palette"></i> Apparence</h2>
                        <p>Personnalisez l'apparence de l'application</p>
                    </div>
                    
                    <div class="settings-grid">
                        <!-- Mode Sombre/Clair -->
                        <div class="setting-card">
                            <div class="setting-info">
                                <h3>Thème</h3>
                                <p>Choisissez entre le mode clair et sombre pour votre confort visuel</p>
                            </div>
                            <div class="setting-control">
                                <div class="theme-toggle">
                                    <input type="radio" name="theme" value="light" id="theme-light" <?php echo (($preferences['theme'] ?? 'light') === 'light') ? 'checked' : ''; ?>>
                                    <label for="theme-light" class="theme-option">
                                        <i class="fas fa-sun"></i>
                                        <span>Clair</span>
                                    </label>
                                    
                                    <input type="radio" name="theme" value="dark" id="theme-dark" <?php echo (($preferences['theme'] ?? 'light') === 'dark') ? 'checked' : ''; ?>>
                                    <label for="theme-dark" class="theme-option">
                                        <i class="fas fa-moon"></i>
                                        <span>Sombre</span>
                                    </label>
                                    
                                    <input type="radio" name="theme" value="auto" id="theme-auto" <?php echo (($preferences['theme'] ?? 'light') === 'auto') ? 'checked' : ''; ?>>
                                    <label for="theme-auto" class="theme-option">
                                        <i class="fas fa-adjust"></i>
                                        <span>Auto</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Taille de police -->
                        <div class="setting-card">
                            <div class="setting-info">
                                <h3>Taille du texte</h3>
                                <p>Ajustez la taille du texte pour une meilleure lisibilité</p>
                            </div>
                            <div class="setting-control">
                                <div class="font-size-toggle">
                                    <input type="radio" name="font_size" value="small" id="font-small" <?php echo (($preferences['font_size'] ?? 'medium') === 'small') ? 'checked' : ''; ?>>
                                    <label for="font-small" class="font-option">
                                        <span>A</span>
                                        <small>Petit</small>
                                    </label>
                                    
                                    <input type="radio" name="font_size" value="medium" id="font-medium" <?php echo (($preferences['font_size'] ?? 'medium') === 'medium') ? 'checked' : ''; ?>>
                                    <label for="font-medium" class="font-option">
                                        <span>A</span>
                                        <small>Moyen</small>
                                    </label>
                                    
                                    <input type="radio" name="font_size" value="large" id="font-large" <?php echo (($preferences['font_size'] ?? 'medium') === 'large') ? 'checked' : ''; ?>>
                                    <label for="font-large" class="font-option">
                                        <span>A</span>
                                        <small>Grand</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Accessibilité -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-universal-access"></i> Accessibilité</h2>
                        <p>Options pour améliorer l'accessibilité</p>
                    </div>
                    
                    <div class="settings-grid">
                        <!-- Mode Accessibilité -->
                        <div class="setting-card">
                            <div class="setting-info">
                                <h3>Mode Accessibilité</h3>
                                <p>Activez des fonctionnalités spéciales pour les utilisateurs ayant des besoins visuels spécifiques</p>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="accessibility_mode" <?php echo ($preferences['accessibility_mode'] ?? 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Notifications -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bell"></i> Notifications</h2>
                        <p>Gérez vos préférences de notifications</p>
                    </div>
                    
                    <div class="settings-grid">
                        <!-- Notifications générales -->
                        <div class="setting-card">
                            <div class="setting-info">
                                <h3>Notifications</h3>
                                <p>Recevez des alertes pour vos pointages et rappels importants</p>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notifications" <?php echo ($preferences['notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnReset">
                        <i class="fas fa-undo"></i> Réinitialiser
                    </button>
                </div>
            </div>
        </form>
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

        /* Styles généraux */
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Settings Container */
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .settings-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            transition: var(--transition);
        }

        .settings-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-header h2 {
            font-size: 24px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .section-header p {
            color: var(--text-secondary);
            margin: 0;
        }

        .settings-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .setting-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .setting-card:hover {
            background: var(--bg-card);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .setting-info h3 {
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 16px;
        }

        .setting-info p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 14px;
            line-height: 1.4;
        }

        .setting-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Theme Toggle */
        .theme-toggle {
            display: flex;
            gap: 10px;
            background: var(--bg-primary);
            padding: 5px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .theme-toggle input {
            display: none;
        }

        .theme-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
            font-size: 12px;
            min-width: 70px;
        }

        .theme-option i {
            font-size: 16px;
        }

        .theme-toggle input:checked + .theme-option {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(67, 97, 238, 0.3);
        }

        /* Font Size Toggle */
        .font-size-toggle {
            display: flex;
            gap: 10px;
            background: var(--bg-primary);
            padding: 5px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .font-size-toggle input {
            display: none;
        }

        .font-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
            font-size: 12px;
            min-width: 70px;
        }

        .font-option span {
            font-weight: bold;
            transition: var(--transition);
        }

        #font-small + .font-option span { font-size: 14px; }
        #font-medium + .font-option span { font-size: 18px; }
        #font-large + .font-option span { font-size: 22px; }

        .font-size-toggle input:checked + .font-option {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(67, 97, 238, 0.3);
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--text-secondary);
            transition: var(--transition);
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        /* Buttons */
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

        /* Settings Actions */
        .settings-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn-save {
            padding: 15px 25px;
            font-size: 16px;
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

            .settings-section {
                padding: 20px;
            }

            .setting-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .setting-control {
                align-self: stretch;
                justify-content: space-between;
            }

            .theme-toggle, .font-size-toggle {
                width: 100%;
                justify-content: space-between;
            }

            .theme-option, .font-option {
                flex: 1;
            }

            .settings-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-right {
                width: 100%;
                justify-content: center;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .section-header h2 {
                font-size: 20px;
            }
        }
    </style>

<script>
    // Gestion du formulaire
    document.addEventListener('DOMContentLoaded', function() {
        const btnReset = document.getElementById('btnReset');
        const form = document.getElementById('settingsForm');
        
        // Réinitialiser les paramètres
        btnReset.addEventListener('click', function() {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les paramètres ?')) {
                document.querySelector('input[name="theme"][value="light"]').checked = true;
                document.querySelector('input[name="font_size"][value="medium"]').checked = true;
                document.querySelector('input[name="notifications"]').checked = true;
                document.querySelector('input[name="accessibility_mode"]').checked = false;
                
                // Mettre à jour l'affichage immédiatement
                document.documentElement.setAttribute('data-theme', 'light');
                
                // Soumettre le formulaire pour sauvegarder les changements
                form.submit();
            }
        });
        
        // Appliquer le thème sélectionné immédiatement (pour preview)
        document.querySelectorAll('input[name="theme"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const selectedTheme = this.value;
                document.documentElement.setAttribute('data-theme', selectedTheme);
            });
        });
        
        // Animation des cartes
        const cards = document.querySelectorAll('.setting-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.1) + 's';
            card.classList.add('fade-in');
        });
    });
</script>

</body>
</html>