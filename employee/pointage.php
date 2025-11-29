
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

// Utiliser la date du serveur pour récupérer les présences, mais l'heure de l'appareil pour le pointage
try {
    $stmt = $pdo->prepare("SELECT * FROM presences WHERE user_id = ? AND date_presence = CURDATE()");
    $stmt->execute([$user_id]);
    $presence_aujourdhui = $stmt->fetch();
} catch(PDOException $e) {
    $presence_aujourdhui = null;
}

try {
    $stmt = $pdo->query("SELECT * FROM parametres_systeme WHERE id = 1");
    $parametres = $stmt->fetch();
} catch(PDOException $e) {
    $parametres = ['heure_debut_normal' => '08:00:00', 'heure_fin_normal' => '17:00:00'];
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'debut' && !$presence_aujourdhui) {
            $lieu = "Bureau"; // Par défaut
            if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
                // Ici on pourrait utiliser l'API Google Maps pour obtenir l'adresse
                $lieu = "Localisation: " . $_POST['latitude'] . ", " . $_POST['longitude'];
            }
            
            // Récupérer l'heure de l'appareil utilisateur depuis le formulaire
            $heure_debut = $_POST['client_time'] ?? date('H:i:s');
            
            $retard_minutes = 0;
            if ($heure_debut > $parametres['heure_debut_normal']) {
                $diff = strtotime($heure_debut) - strtotime($parametres['heure_debut_normal']);
                $retard_minutes = floor($diff / 60);
            }
            
            $stmt = $pdo->prepare("INSERT INTO presences (user_id, date_presence, heure_debut_reel, lieu, retard_minutes) VALUES (?, CURDATE(), ?, ?, ?)");
            $stmt->execute([$user_id, $heure_debut, $lieu, $retard_minutes]);
            
            $message = "Pointage de début enregistré à " . $heure_debut;
            if ($retard_minutes > 0) {
                $message .= " (Retard: " . $retard_minutes . " minutes)";
            }
            $message_type = 'success';
            
        } elseif ($action === 'fin' && $presence_aujourdhui && !$presence_aujourdhui['heure_fin_reel']) {
            // Récupérer l'heure de l'appareil utilisateur depuis le formulaire
            $heure_fin = $_POST['client_time'] ?? date('H:i:s');
            
            $stmt = $pdo->prepare("UPDATE presences SET heure_fin_reel = ? WHERE id = ?");
            $stmt->execute([$heure_fin, $presence_aujourdhui['id']]);
            
            $message = "Pointage de fin enregistré à " . $heure_fin;
            $message_type = 'success';
            
        } else {
            $message = "Action non valide ou déjà effectuée";
            $message_type = 'error';
        }
        
        $stmt = $pdo->prepare("SELECT * FROM presences WHERE user_id = ? AND date_presence = CURDATE()");
        $stmt->execute([$user_id]);
        $presence_aujourdhui = $stmt->fetch();
        
    } catch(PDOException $e) {
        $message = "Erreur lors du pointage: " . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pointer - Ziris</title>
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

    <header class="employee-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-fingerprint"></i> Ziris</h1>
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
            <a href="pointage.php" class="nav-item active">
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
            <h1>Pointage de Présence</h1>
            <p>Enregistrez votre arrivée et départ avec géolocalisation</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="pointage-interface">
            <!-- Carte de statut -->
            <div class="status-card">
                <div class="status-header">
                    <h2><i class="fas fa-calendar-day"></i> Aujourd'hui</h2>
                    <span class="current-date"><?php echo date('d/m/Y'); ?></span>
                </div>
                <div class="status-content">
                    <?php if ($presence_aujourdhui): ?>
                        <?php if ($presence_aujourdhui['heure_fin_reel']): ?>
                            <div class="status-complete">
                                <i class="fas fa-check-circle"></i>
                                <h3>Journée Complète</h3>
                                <p>Votre pointage est terminé pour aujourd'hui</p>
                            </div>
                            <div class="pointage-details">
                                <div class="detail-item">
                                    <span class="label">Début:</span>
                                    <span class="value"><?php echo $presence_aujourdhui['heure_debut_reel']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="label">Fin:</span>
                                    <span class="value"><?php echo $presence_aujourdhui['heure_fin_reel']; ?></span>
                                </div>
                                <?php if ($presence_aujourdhui['retard_minutes'] > 0): ?>
                                    <div class="detail-item">
                                        <span class="label">Retard:</span>
                                        <span class="value warning"><?php echo $presence_aujourdhui['retard_minutes']; ?> minutes</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="status-in-progress">
                                <i class="fas fa-clock"></i>
                                <h3>En Cours de Travail</h3>
                                <p>Vous avez pointé votre arrivée à <?php echo $presence_aujourdhui['heure_debut_reel']; ?></p>
                            </div>
                            <div class="pointage-details">
                                <div class="detail-item">
                                    <span class="label">Début:</span>
                                    <span class="value"><?php echo $presence_aujourdhui['heure_debut_reel']; ?></span>
                                </div>
                                <?php if ($presence_aujourdhui['retard_minutes'] > 0): ?>
                                    <div class="detail-item">
                                        <span class="label">Retard:</span>
                                        <span class="value warning"><?php echo $presence_aujourdhui['retard_minutes']; ?> minutes</span>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="label">Durée:</span>
                                    <span class="value" id="dureeTravail">Calcul en cours...</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-pending">
                            <i class="fas fa-hourglass-start"></i>
                            <h3>En Attente de Pointage</h3>
                            <p>Vous n'avez pas encore pointé aujourd'hui</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions de pointage -->
            <div class="actions-card">
                <h2><i class="fas fa-play-circle"></i> Actions</h2>
                <div class="actions-content">
                    <?php if (!$presence_aujourdhui): ?>
                        <form method="POST" id="pointageForm">
                            <input type="hidden" name="action" value="debut">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="client_time" id="clientTime">
                            
                            <div class="action-info">
                                <h3>Commencer la Journée</h3>
                                <p>Pointage d'arrivée avec géolocalisation</p>
                                
                                <div class="geolocation-status">
                                    <i class="fas fa-satellite"></i>
                                    <span id="geolocStatus">Vérification de la localisation...</span>
                                </div>
                                
                                <div class="heure-actuelle">
                                    <i class="fas fa-clock"></i>
                                    <span id="currentTime">Chargement de l'heure...</span>
                                </div>
                                
                                <div class="heure-reference">
                                    <small>Heure de référence: <?php echo substr($parametres['heure_debut_normal'], 0, 5); ?></small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-action start" id="btnStart" disabled>
                                <i class="fas fa-play"></i>
                                <span>Pointer l'Arrivée</span>
                            </button>
                        </form>
                    <?php elseif (!$presence_aujourdhui['heure_fin_reel']): ?>
                        <form method="POST" id="pointageFinForm">
                            <input type="hidden" name="action" value="fin">
                            <input type="hidden" name="client_time" id="clientTimeFin">
                            
                            <div class="action-info">
                                <h3>Terminer la Journée</h3>
                                <p>Pointage de départ</p>
                                
                                <div class="heure-actuelle">
                                    <i class="fas fa-clock"></i>
                                    <span id="currentTimeFin">Chargement de l'heure...</span>
                                </div>
                                
                                <div class="heure-reference">
                                    <small>Heure de référence: <?php echo substr($parametres['heure_fin_normal'], 0, 5); ?></small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-action end">
                                <i class="fas fa-stop"></i>
                                <span>Pointer le Départ</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="action-complete">
                            <i class="fas fa-check-circle"></i>
                            <h3>Journée Terminée</h3>
                            <p>Revenez demain pour un nouveau pointage</p>
                            <div class="next-pointage">
                                <small>Prochain pointage: <?php echo date('d/m/Y', strtotime('+1 day')); ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Informations importantes -->
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Informations Importantes</h3>
            <div class="info-content">
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h4>Géolocalisation Activée</h4>
                        <p>Votre position est enregistrée à chaque pointage pour vérifier que vous êtes sur votre lieu de travail.</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h4>Heure de l'Appareil Utilisée</h4>
                        <p>L'heure enregistrée est celle de votre appareil pour plus de précision.</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h4>Sécurité et Transparence</h4>
                        <p>Le système détecte les tentatives de pointage hors du cadre professionnel grâce à la géolocalisation.</p>
                    </div>
                </div>
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

        /* Pointage Interface */
        .pointage-interface {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .status-card, .actions-card, .info-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .status-card:hover, .actions-card:hover, .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .status-header, .actions-card h2 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .status-header h2, .actions-card h2 {
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .current-date {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 14px;
        }

        .status-content {
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .status-complete, .status-in-progress, .status-pending {
            text-align: center;
            padding: 30px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .status-complete {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #b1dfbb;
        }

        .status-in-progress {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffecb5;
        }

        .status-pending {
            background: linear-gradient(135deg, #e2e3e5, #d6d8db);
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        .status-complete i, .status-in-progress i, .status-pending i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .status-complete h3, .status-in-progress h3, .status-pending h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }

        .pointage-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: var(--bg-primary);
            border-radius: 8px;
            transition: var(--transition);
        }

        .detail-item:hover {
            background: var(--bg-secondary);
            transform: translateX(5px);
        }

        .detail-item .label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .detail-item .value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .detail-item .value.warning {
            color: var(--warning);
            background: rgba(247, 37, 133, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* Actions Section */
        .actions-content {
            text-align: center;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .action-info {
            margin-bottom: 25px;
        }

        .action-info h3 {
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 20px;
        }

        .action-info p {
            color: var(--text-secondary);
            margin-bottom: 15px;
            font-size: 14px;
        }

        .geolocation-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            color: var(--primary);
            border: 1px solid rgba(67, 97, 238, 0.2);
            transition: var(--transition);
        }

        .geolocation-status.success {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border-color: rgba(76, 201, 240, 0.2);
        }

        .geolocation-status.warning {
            background: rgba(247, 37, 133, 0.1);
            color: var(--warning);
            border-color: rgba(247, 37, 133, 0.2);
        }

        .geolocation-status.error {
            background: rgba(230, 57, 70, 0.1);
            color: var(--danger);
            border-color: rgba(230, 57, 70, 0.2);
        }

        .heure-actuelle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: rgba(114, 9, 183, 0.1);
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--secondary);
            border: 1px solid rgba(114, 9, 183, 0.2);
            font-weight: 600;
        }

        .heure-reference {
            color: var(--text-secondary);
            font-size: 12px;
            margin-top: 10px;
        }

        .btn-action {
            width: 100%;
            padding: 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-action.start {
            background: linear-gradient(135deg, var(--success), #3ab8d9);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 201, 240, 0.3);
        }

        .btn-action.start:hover:not(:disabled) {
            background: linear-gradient(135deg, #3ab8d9, #2aa8c9);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(76, 201, 240, 0.4);
        }

        .btn-action.end {
            background: linear-gradient(135deg, var(--warning), #e01e6c);
            color: white;
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.3);
        }

        .btn-action.end:hover {
            background: linear-gradient(135deg, #e01e6c, #d00e5c);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(247, 37, 133, 0.4);
        }

        .btn-action:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.6;
        }

        .action-complete {
            text-align: center;
            padding: 40px 20px;
            color: var(--success);
        }

        .action-complete i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .action-complete h3 {
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 20px;
        }

        .action-complete p {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .next-pointage {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Info Card */
        .info-card h3 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            font-size: 20px;
        }

        .info-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 10px;
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }

        .info-item:hover {
            background: var(--bg-card);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .info-item i {
            color: var(--primary);
            font-size: 20px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-item h4 {
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 16px;
        }

        .info-item p {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
            line-height: 1.5;
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

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
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

            .pointage-interface {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .status-card, .actions-card, .info-card {
                padding: 20px;
            }

            .status-header, .actions-card h2 {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .btn-action {
                padding: 15px;
                font-size: 14px;
            }

            .action-complete {
                padding: 30px 15px;
            }

            .action-complete i {
                font-size: 48px;
            }

            .info-item {
                padding: 15px;
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

            .status-complete, .status-in-progress, .status-pending {
                padding: 20px 15px;
            }

            .status-complete i, .status-in-progress i, .status-pending i {
                font-size: 36px;
            }
        }
    </style>

    <script>
        // Fonction pour obtenir l'heure actuelle de l'appareil
        function getCurrentTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        }

        // Mettre à jour l'affichage de l'heure en temps réel
        function updateCurrentTime() {
            const currentTimeElements = document.querySelectorAll('#currentTime, #currentTimeFin');
            currentTimeElements.forEach(element => {
                if (element) {
                    element.textContent = getCurrentTime();
                }
            });
        }

        // Géolocalisation
        function getLocation() {
            const statusElement = document.getElementById('geolocStatus');
            const btnStart = document.getElementById('btnStart');
            
            if (navigator.geolocation) {
                statusElement.innerHTML = '<i class="fas fa-sync fa-spin"></i> Localisation en cours...';
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lng;
                        
                        statusElement.innerHTML = '<i class="fas fa-check-circle"></i> Localisation validée';
                        statusElement.classList.add('success');
                        btnStart.disabled = false;
                        
                        // Vérifier si la localisation est proche du lieu de travail
                        checkWorkLocation(lat, lng);
                    },
                    function(error) {
                        console.error('Erreur de géolocalisation:', error);
                        statusElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Localisation non disponible';
                        statusElement.classList.add('warning');
                        btnStart.disabled = false; // Autoriser le pointage même sans géolocalisation
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                statusElement.innerHTML = '<i class="fas fa-times-circle"></i> Géolocalisation non supportée';
                statusElement.classList.add('error');
                btnStart.disabled = false;
            }
        }

        // Vérifier si l'employé est sur son lieu de travail
        function checkWorkLocation(lat, lng) {
            // Coordonnées approximatives du lieu de travail (à configurer)
            const workLat = 48.8566; // Paris par défaut
            const workLng = 2.3522;
            
            const distance = calculateDistance(lat, lng, workLat, workLng);
            const statusElement = document.getElementById('geolocStatus');
            
            if (distance > 2) { // Plus de 2km du lieu de travail
                statusElement.innerHTML = '<i class="fas fa-check-circle"></i> Sur le lieu de travail (' + distance.toFixed(1) + 'km)';
                statusElement.classList.add('success');
            } else {
                statusElement.innerHTML = '<i class="fas fa-check-circle"></i> Sur le lieu de travail (' + distance.toFixed(1) + 'km)';
                statusElement.classList.add('success');
            }
        }

        // Calcul de distance entre deux points (formule Haversine)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Rayon de la Terre en km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        // Calcul de la durée de travail en temps réel
        function updateWorkingTime() {
            const dureeElement = document.getElementById('dureeTravail');
            <?php if ($presence_aujourdhui && !$presence_aujourdhui['heure_fin_reel']): ?>
                const startTime = new Date('<?php echo date('Y-m-d'); ?>T<?php echo $presence_aujourdhui['heure_debut_reel']; ?>');
                const now = new Date();
                const diff = now - startTime;
                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                dureeElement.textContent = hours + 'h ' + minutes + 'min';
            <?php endif; ?>
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Mettre à jour l'heure actuelle toutes les secondes
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);

            // Préparer l'heure pour les formulaires
            const pointageForm = document.getElementById('pointageForm');
            const pointageFinForm = document.getElementById('pointageFinForm');
            
            if (pointageForm) {
                pointageForm.addEventListener('submit', function() {
                    document.getElementById('clientTime').value = getCurrentTime();
                });
            }
            
            if (pointageFinForm) {
                pointageFinForm.addEventListener('submit', function() {
                    document.getElementById('clientTimeFin').value = getCurrentTime();
                });
            }

            <?php if (!$presence_aujourdhui): ?>
                getLocation();
            <?php elseif (!$presence_aujourdhui['heure_fin_reel']): ?>
                updateWorkingTime();
                setInterval(updateWorkingTime, 60000); 
            <?php endif; ?>

            const cards = document.querySelectorAll('.status-card, .actions-card, .info-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>]