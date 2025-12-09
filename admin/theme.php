<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'utilisateur admin
try {
    $stmt = $pdo->prepare("SELECT u.*, p.nom as poste_nom FROM users u LEFT JOIN postes p ON u.poste_id = p.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données.");
}

// Récupérer les préférences utilisateur
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        // Créer des préférences par défaut
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

// Déterminer le thème actuel
$currentTheme = $preferences['theme'] ?? 'light';

$message = '';
$message_type = '';

// Traitement du formulaire de mise à jour des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = $_POST['theme'] ?? 'light';
    $font_size = $_POST['font_size'] ?? 'medium';
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $accessibility_mode = isset($_POST['accessibility_mode']) ? 1 : 0;
    
    try {
        // Vérifier d'abord si l'utilisateur a déjà des préférences
        $stmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $existing_preferences = $stmt->fetch();
        
        if ($existing_preferences) {
            // Mettre à jour les préférences existantes
            $stmt = $pdo->prepare("
                UPDATE user_preferences 
                SET theme = ?, font_size = ?, notifications = ?, accessibility_mode = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = ?
            ");
            $result = $stmt->execute([$theme, $font_size, $notifications, $accessibility_mode, $_SESSION['user_id']]);
            
            if ($result) {
                // Recharger les préférences depuis la base
                $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $preferences = $stmt->fetch();
                
                $message = "Paramètres du thème mis à jour avec succès!";
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
            $result = $stmt->execute([$_SESSION['user_id'], $theme, $font_size, $notifications, $accessibility_mode]);
            
            if ($result) {
                // Recharger les préférences depuis la base
                $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $preferences = $stmt->fetch();
                
                $message = "Paramètres du thème créés avec succès!";
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
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnaliser le Thème - Ziris Admin</title>
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

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .stats-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .stats-grid-small {
            display: grid;
            gap: 15px;
        }

        .stat-card-small {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .stat-card-small .stat-info h3 {
            margin: 0;
            font-size: 24px;
            color: var(--text-primary);
        }

        .stat-card-small .stat-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .stat-card-small .stat-icon {
            color: var(--primary);
            font-size: 24px;
        }

        .form-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        /* Theme Toggle */
        .theme-toggle {
            display: flex;
            gap: 10px;
            background: var(--bg-primary);
            padding: 5px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-top: 10px;
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
            flex: 1;
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
            margin-top: 10px;
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
            flex: 1;
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
            margin-top: 10px;
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

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group p {
            margin: 5px 0 10px 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        /* Settings Cards */
        .setting-card {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }

        .setting-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .setting-card p {
            margin-bottom: 15px;
            color: var(--text-secondary);
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

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Personnaliser le Thème</h1>
            <p>Configurez les préférences d'apparence et de notifications</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Formulaire de configuration -->
            <div class="form-container">
                <h2><i class="fas fa-palette"></i> Configuration du Thème</h2>
                <form method="POST" id="themeForm">
                    <!-- Section Apparence -->
                    <div class="setting-card">
                        <h3>Thème</h3>
                        <p>Choisissez entre le mode clair et sombre pour votre confort visuel</p>
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

                    <!-- Taille de police -->
                    <div class="setting-card">
                        <h3>Taille du texte</h3>
                        <p>Ajustez la taille du texte pour une meilleure lisibilité</p>
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

                    <!-- Accessibilité -->
                    <div class="setting-card">
                        <h3>Mode Accessibilité</h3>
                        <p>Activez des fonctionnalités spéciales pour les utilisateurs ayant des besoins visuels spécifiques</p>
                        <label class="toggle-switch">
                            <input type="checkbox" name="accessibility_mode" <?php echo ($preferences['accessibility_mode'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Notifications -->
                    <div class="setting-card">
                        <h3>Notifications</h3>
                        <p>Recevez des alertes pour vos pointages et rappels importants</p>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notifications" <?php echo ($preferences['notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <button type="button" class="btn btn-secondary" id="btnReset">
                            <i class="fas fa-undo"></i> Réinitialiser
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statistiques et informations -->
            <div class="stats-container">
                <h2><i class="fas fa-info-circle"></i> Informations</h2>
                <div class="stats-grid-small">
                    <div class="stat-card-small">
                        <div class="stat-info">
                            <h3><?php echo htmlspecialchars($preferences['theme'] ?? 'light'); ?></h3>
                            <p>Thème actuel</p>
                        </div>
                        <div class="stat-icon">
                            <?php 
                            $theme_icon = ($preferences['theme'] ?? 'light') === 'dark' ? 'moon' : 'sun';
                            echo '<i class="fas fa-' . $theme_icon . '"></i>'; 
                            ?>
                        </div>
                    </div>
                    
                    <div class="stat-card-small">
                        <div class="stat-info">
                            <h3><?php echo htmlspecialchars($preferences['font_size'] ?? 'medium'); ?></h3>
                            <p>Taille de police</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-text-height"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card-small">
                        <div class="stat-info">
                            <h3><?php echo ($preferences['notifications'] ?? 1) ? 'Activées' : 'Désactivées'; ?></h3>
                            <p>Notifications</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card-small">
                        <div class="stat-info">
                            <h3><?php echo ($preferences['accessibility_mode'] ?? 0) ? 'Activé' : 'Désactivé'; ?></h3>
                            <p>Mode accessibilité</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-universal-access"></i>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <h3><i class="fas fa-lightbulb"></i> Conseils</h3>
                    <ul style="color: var(--text-secondary); font-size: 14px; line-height: 1.6; padding-left: 20px; margin-top: 10px;">
                        <li>Le mode "Auto" ajuste automatiquement le thème selon les préférences de votre système</li>
                        <li>Le mode accessibilité améliore le contraste et la lisibilité</li>
                        <li>Les notifications vous alertent des nouveaux pointages et rappels</li>
                        <li>Les modifications sont appliquées immédiatement après enregistrement</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Aperçu du thème -->
        <div class="table-container" style="margin-top: 30px;">
            <div class="table-header">
                <h2><i class="fas fa-eye"></i> Aperçu du Thème</h2>
            </div>
            
            <div style="background: var(--bg-card); border-radius: var(--border-radius); padding: 30px; box-shadow: var(--shadow);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <!-- Carte exemple 1 -->
                    <div style="background: var(--bg-primary); padding: 20px; border-radius: 10px; border: 1px solid var(--border-color);">
                        <h3 style="color: var(--primary); margin-bottom: 10px;">Carte Exemple</h3>
                        <p style="color: var(--text-secondary); margin-bottom: 15px;">Ceci est un exemple de carte avec les couleurs du thème actuel.</p>
                        <button style="background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; transition: var(--transition);">
                            Bouton Exemple
                        </button>
                    </div>
                    
                    <!-- Carte exemple 2 -->
                    <div style="background: var(--bg-secondary); padding: 20px; border-radius: 10px; border-left: 4px solid var(--success);">
                        <h3 style="color: var(--text-primary); margin-bottom: 10px;">Message de succès</h3>
                        <p style="color: var(--text-secondary); margin-bottom: 15px;">Un exemple de message d'alerte avec les couleurs du thème.</p>
                        <span style="background: rgba(76, 201, 240, 0.1); color: var(--success); padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            <i class="fas fa-check-circle"></i> Réussi
                        </span>
                    </div>
                    
                    <!-- Carte exemple 3 -->
                    <div style="background: var(--bg-card); padding: 20px; border-radius: 10px; box-shadow: var(--shadow);">
                        <h3 style="color: var(--text-primary); margin-bottom: 10px;">Informations de texte</h3>
                        <p style="color: var(--text-secondary); font-size: <?php 
                            switch($preferences['font_size'] ?? 'medium') {
                                case 'small': echo '14px'; break;
                                case 'large': echo '18px'; break;
                                default: echo '16px';
                            }
                        ?>; line-height: 1.6;">
                            Ce texte montre la taille de police actuellement sélectionnée. 
                            Le mode accessibilité est actuellement <?php echo ($preferences['accessibility_mode'] ?? 0) ? 'activé' : 'désactivé'; ?>.
                        </p>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: center;">
                    <p style="color: var(--text-secondary); font-style: italic;">
                        Cet aperçu montre comment votre configuration de thème affecte l'apparence des éléments de l'interface.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Gestion du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const btnReset = document.getElementById('btnReset');
            const form = document.getElementById('themeForm');
            
            // Réinitialiser les paramètres
            btnReset.addEventListener('click', function() {
                if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les paramètres ?')) {
                    document.querySelector('input[name="theme"][value="light"]').checked = true;
                    document.querySelector('input[name="font_size"][value="medium"]').checked = true;
                    document.querySelector('input[name="notifications"]').checked = true;
                    document.querySelector('input[name="accessibility_mode"]').checked = false;
                    
                    // Soumettre le formulaire pour sauvegarder les changements
                    form.submit();
                }
            });
            
            // Appliquer le thème sélectionné immédiatement (pour preview)
            document.querySelectorAll('input[name="theme"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const selectedTheme = this.value;
                    document.documentElement.setAttribute('data-theme', selectedTheme);
                    
                    // Mettre à jour l'aperçu
                    updateThemePreview();
                });
            });
            
            // Animation des cartes
            const cards = document.querySelectorAll('.setting-card, .stat-card-small');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
            
            // Fonction pour mettre à jour l'aperçu
            function updateThemePreview() {
                // Cette fonction peut être étendue pour mettre à jour plus d'éléments d'aperçu
                console.log('Thème mis à jour pour l\'aperçu');
            }
        });
    </script>
</body>
</html>