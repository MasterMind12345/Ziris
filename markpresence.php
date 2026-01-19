<?php
session_start();
require_once 'config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_message'] = "Veuillez vous connecter pour pointer votre présence";
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les préférences utilisateur pour le thème
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

// Récupérer les informations de l'utilisateur
try {
    $stmt = $pdo->prepare("SELECT u.*, p.nom as poste_nom FROM users u LEFT JOIN postes p ON u.poste_id = p.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Vérifier le pointage du jour
try {
    $stmt = $pdo->prepare("SELECT * FROM presences WHERE user_id = ? AND date_presence = CURDATE()");
    $stmt->execute([$user_id]);
    $presence_aujourdhui = $stmt->fetch();
} catch(PDOException $e) {
    $presence_aujourdhui = null;
}

// Récupérer les paramètres système
try {
    $stmt = $pdo->query("SELECT * FROM parametres_systeme WHERE id = 1");
    $parametres = $stmt->fetch();
} catch(PDOException $e) {
    $parametres = ['heure_debut_normal' => '08:00:00', 'heure_fin_normal' => '17:00:00'];
}

$message = '';
$message_type = '';

// Traitement du pointage avec géolocalisation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $adresse_details = $_POST['adresse_details'] ?? '';
    $quartier = $_POST['quartier'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $heure_appareil = $_POST['heure_appareil'] ?? ''; // Heure locale capturée au clic
    $date_appareil = $_POST['date_appareil'] ?? ''; // Date locale capturée au clic
    
    error_log("Pointage attempt - Action: $action, Lat: $latitude, Lng: $longitude, Heure Appareil: $heure_appareil, Date Appareil: $date_appareil");
    
    try {
        if ($action === 'debut' && !$presence_aujourdhui) {
            // UTILISER L'HEURE ET DATE LOCALE DE L'APPAREIL
            if (!empty($heure_appareil) && !empty($date_appareil)) {
                // Combiner la date et l'heure locales
                $date_heure_complete = $date_appareil . ' ' . $heure_appareil;
                
                // Convertir en format MySQL
                $heure_debut = date('H:i:s', strtotime($date_heure_complete));
                $date_presence = date('Y-m-d', strtotime($date_heure_complete));
                
                error_log("✅ Pointage début - Heure locale: $heure_appareil, Date locale: $date_appareil => MySQL: $heure_debut, Date: $date_presence");
            } else {
                // Fallback: utiliser l'heure locale du serveur (pas UTC)
                $heure_debut = date('H:i:s');
                $date_presence = date('Y-m-d');
                error_log("⚠️ Heure appareil non disponible pour début, utilisation heure serveur local");
            }
            
            // Calcul du retard basé sur l'heure locale
            $retard_minutes = 0;
            if ($heure_debut > $parametres['heure_debut_normal']) {
                $diff = strtotime($heure_debut) - strtotime($parametres['heure_debut_normal']);
                $retard_minutes = floor($diff / 60);
            }
            
            // Construction de l'adresse complète
            $adresse_complete = $adresse_details;
            if ($quartier) {
                $adresse_complete .= " | Quartier: " . $quartier;
            }
            if ($ville) {
                $adresse_complete .= " | Ville: " . $ville;
            }
            
            $stmt = $pdo->prepare("INSERT INTO presences (user_id, date_presence, heure_debut_reel, lieu, retard_minutes, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $date_presence, $heure_debut, $adresse_complete, $retard_minutes, $latitude, $longitude]);
            
            $message = "✅ Pointage de début enregistré à " . $heure_debut . " (Heure locale appareil)";
            if ($retard_minutes > 0) {
                $message .= " (Retard: " . $retard_minutes . " minutes)";
            }
            $message_type = 'success';
            
        } elseif ($action === 'fin' && $presence_aujourdhui && !$presence_aujourdhui['heure_fin_reel']) {
            // UTILISER L'HEURE LOCALE DE L'APPAREIL
            if (!empty($heure_appareil) && !empty($date_appareil)) {
                // Combiner la date et l'heure locales
                $date_heure_complete = $date_appareil . ' ' . $heure_appareil;
                
                // Convertir en format MySQL
                $heure_fin = date('H:i:s', strtotime($date_heure_complete));
                
                error_log("✅ Pointage fin - Heure locale: $heure_appareil, Date locale: $date_appareil => MySQL: $heure_fin");
            } else {
                // Fallback: utiliser l'heure locale du serveur (pas UTC)
                $heure_fin = date('H:i:s');
                error_log("⚠️ Heure appareil non disponible pour fin, utilisation heure serveur local");
            }
            
            // Construction de l'adresse complète pour la fin
            $adresse_complete = $adresse_details;
            if ($quartier) {
                $adresse_complete .= " | Quartier: " . $quartier;
            }
            if ($ville) {
                $adresse_complete .= " | Ville: " . $ville;
            }
            
            $stmt = $pdo->prepare("UPDATE presences SET heure_fin_reel = ?, lieu_fin = ?, latitude_fin = ?, longitude_fin = ? WHERE id = ?");
            $stmt->execute([$heure_fin, $adresse_complete, $latitude, $longitude, $presence_aujourdhui['id']]);
            
            $message = "✅ Pointage de fin enregistré à " . $heure_fin . " (Heure locale appareil)";
            $message_type = 'success';
            
        } else {
            $message = "❌ Action non valide ou déjà effectuée";
            $message_type = 'error';
        }
        
        // Recharger les données
        $stmt = $pdo->prepare("SELECT * FROM presences WHERE user_id = ? AND date_presence = CURDATE()");
        $stmt->execute([$user_id]);
        $presence_aujourdhui = $stmt->fetch();
        
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $message = "❌ Erreur lors du pointage: " . $e->getMessage();
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pointage GPS - Ziris</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="manifest.json">

    <!-- CSS existant -->
    <link rel="stylesheet" href="../css/employee.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .container {
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 25px;
            text-align: center;
            color: white;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-top: 10px solid var(--secondary);
        }

        .logo {
            font-size: 42px;
            margin-bottom: 10px;
        }

        .user-info {
            background: var(--bg-secondary);
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .user-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-poste {
            color: var(--primary);
            margin-top: 5px;
            font-size: 14px;
        }

        .pointage-container {
            padding: 20px;
        }

        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: 500;
            text-align: center;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .status-section {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .status-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .geolocation-section {
            margin-bottom: 20px;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            border: 1px solid var(--border-color);
        }

        .geolocation-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .geolocation-header i {
            color: var(--primary);
            font-size: 18px;
        }

        .map-container {
            height: 250px;
            background: var(--bg-secondary);
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            position: relative;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .location-info {
            font-size: 13px;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 8px;
        }

        .address-details {
            background: var(--bg-card);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 12px;
            margin-top: 10px;
            line-height: 1.4;
        }

        .location-breakdown {
            margin-top: 8px;
            padding: 8px;
            background: var(--bg-secondary);
            border-radius: 5px;
        }

        .time-info {
            background: var(--bg-secondary);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid var(--success);
            border: 1px solid var(--border-color);
        }

        .time-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .time-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .time-value {
            font-family: monospace;
            font-weight: bold;
            color: var(--success);
            font-size: 14px;
        }

        .time-note {
            font-size: 11px;
            color: var(--text-secondary);
            text-align: center;
        }

        .btn-pointage {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .btn-start {
            background: var(--success);
            color: white;
        }

        .btn-start:hover:not(:disabled) {
            background: #0da271;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-end {
            background: var(--warning);
            color: white;
        }

        .btn-end:hover:not(:disabled) {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.3);
        }

        .btn-pointage:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .info-section {
            background: #fffbeb;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #f59e0b;
        }

        .info-section h4 {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
            color: #92400e;
            font-size: 14px;
        }

        .info-section p {
            font-size: 12px;
            color: #92400e;
            margin: 0;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 14px;
        }

        .loading {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        .manual-location-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .manual-location-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .accuracy-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .accuracy-bar {
            flex-grow: 1;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }

        .accuracy-fill {
            height: 100%;
            background: var(--success);
            border-radius: 2px;
            transition: width 0.5s ease;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .gps-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            margin-left: 6px;
        }

        .gps-active {
            background: #d1fae5;
            color: #065f46;
        }

        .gps-inactive {
            background: #fef3c7;
            color: #92400e;
        }
        
        .click-time-info {
            background: #e0f2fe;
            padding: 8px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 11px;
            text-align: center;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        .timezone-info {
            background: #f3e8ff;
            padding: 6px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 10px;
            text-align: center;
            color: #7c3aed;
            border: 1px solid #ddd6fe;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-satellite"></i>
            </div>
            <h1 style="font-size: 20px;">Pointage GPS Direct</h1>
            <p style="font-size: 14px; opacity: 0.9;">Heure Locale Appareil - Pas de Cache</p>
        </div>

        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user['nom']); ?></div>
            <div class="user-poste"><?php echo htmlspecialchars($user['poste_nom']); ?></div>
        </div>

        <div class="pointage-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Statut du pointage -->
            <div class="status-section">
                <?php if ($presence_aujourdhui): ?>
                    <?php if ($presence_aujourdhui['heure_fin_reel']): ?>
                        <div class="status-complete">
                            <div class="status-icon" style="color: var(--success);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 style="font-size: 16px; margin-bottom: 8px;">Pointage Complet</h3>
                            <p style="font-size: 13px;">Début: <?php echo $presence_aujourdhui['heure_debut_reel']; ?></p>
                            <p style="font-size: 13px;">Fin: <?php echo $presence_aujourdhui['heure_fin_reel']; ?></p>
                            <?php if ($presence_aujourdhui['lieu']): ?>
                                <p style="color: var(--primary); margin-top: 8px; font-size: 12px;">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($presence_aujourdhui['lieu']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="status-in-progress">
                            <div class="status-icon" style="color: var(--warning);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 style="font-size: 16px; margin-bottom: 8px;">En Cours de Travail</h3>
                            <p style="font-size: 13px;">Début: <?php echo $presence_aujourdhui['heure_debut_reel']; ?></p>
                            <?php if ($presence_aujourdhui['lieu']): ?>
                                <p style="color: var(--primary); margin-top: 8px; font-size: 12px;">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($presence_aujourdhui['lieu']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="status-pending">
                        <div class="status-icon" style="color: var(--gray);">
                            <i class="fas fa-hourglass-start"></i>
                        </div>
                        <h3 style="font-size: 16px; margin-bottom: 8px;">En Attente de Pointage</h3>
                        <p style="font-size: 13px;">Activez le GPS de votre appareil</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Affichage de l'heure de l'appareil -->
            <div class="time-info">
                <div class="time-display">
                    <span class="time-label">🕐 Heure Locale:</span>
                    <span class="time-value" id="deviceTime">--:--:--</span>
                </div>
                <div class="time-display">
                    <span class="time-label">📅 Date Locale:</span>
                    <span class="time-value" id="deviceDate">--/--/----</span>
                </div>
                <div class="time-display">
                    <span class="time-label">🌍 Fuseau Horaire:</span>
                    <span class="time-value" id="deviceTimezone">--</span>
                </div>
                <div id="clickTimeDisplay" class="click-time-info" style="display: none;">
                    ⏰ <strong>Heure du pointage:</strong> <span id="clickTimeText">--:--:--</span>
                    <div id="clickDateText" style="font-size: 10px; margin-top: 2px;"></div>
                </div>
                <div class="time-note">
                    ⚡ L'heure LOCALE sera capturée au moment du clic sur le bouton
                </div>
                <div class="timezone-info">
                    <i class="fas fa-globe-africa"></i> 
                    <span id="timezoneOffset">Fuseau horaire détecté...</span>
                </div>
            </div>

            <!-- Géolocalisation et Carte -->
            <div class="geolocation-section">
                <div class="geolocation-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3 style="font-size: 16px;">Localisation GPS Directe</h3>
                    <span id="gpsStatus" class="gps-status gps-inactive">
                        <i class="fas fa-satellite"></i> GPS
                    </span>
                </div>
                
                <div class="map-container">
                    <div id="map"></div>
                </div>
                
                <div class="location-info">
                    <i class="fas fa-sync fa-spin" id="locationLoading"></i>
                    <span id="locationStatus">Initialisation GPS...</span>
                </div>

                <div id="addressDetails" class="address-details" style="display: none;">
                    <strong>📍 Position Exacte:</strong>
                    <div id="fullAddress" style="margin: 6px 0; font-weight: 500; font-size: 11px;"></div>
                    
                    <div class="location-breakdown">
                        <div style="margin-bottom: 4px;">
                            <strong>🏘️ Quartier:</strong> 
                            <span id="quartierInfo" style="color: var(--primary); font-weight: 500;">Détection...</span>
                        </div>
                        <div style="margin-bottom: 4px;">
                            <strong>🏙️ Ville:</strong> 
                            <span id="villeInfo" style="color: var(--primary); font-weight: 500;">Détection...</span>
                        </div>
                        <div style="margin-bottom: 4px;">
                            <strong>📡 Coordonnées:</strong> 
                            <span id="coordinates" style="font-family: monospace; font-size: 10px;"></span>
                        </div>
                        <div class="accuracy-indicator">
                            <span>Précision:</span>
                            <div class="accuracy-bar">
                                <div class="accuracy-fill" id="accuracyBar" style="width: 0%"></div>
                            </div>
                            <span id="accuracyValue">-- m</span>
                        </div>
                    </div>
                    
                    <button type="button" class="manual-location-btn" onclick="refreshLocation()">
                        <i class="fas fa-satellite-dish"></i> Actualiser GPS
                    </button>
                </div>

                <div id="locationError" class="address-details" style="display: none; background: #fee2e2; border-color: #fecaca;">
                    <strong>❌ Erreur GPS:</strong>
                    <div id="errorMessage" style="margin: 6px 0; font-size: 11px;"></div>
                    <button type="button" class="manual-location-btn" onclick="refreshLocation()" style="background: var(--danger);">
                        <i class="fas fa-redo"></i> Réessayer
                    </button>
                </div>
            </div>

            <!-- Actions de pointage -->
            <form method="POST" id="pointageForm">
                <?php if (!$presence_aujourdhui): ?>
                    <input type="hidden" name="action" value="debut">
                    <button type="submit" class="btn-pointage btn-start" id="btnStart" disabled>
                        <i class="fas fa-play-circle"></i>
                        <span>Pointer mon Arrivée</span>
                        <small style="font-size: 11px; opacity: 0.9;">Heure référence: <?php echo substr($parametres['heure_debut_normal'], 0, 5); ?></small>
                    </button>
                <?php elseif (!$presence_aujourdhui['heure_fin_reel']): ?>
                    <input type="hidden" name="action" value="fin">
                    <button type="submit" class="btn-pointage btn-end" id="btnEnd" disabled>
                        <i class="fas fa-stop-circle"></i>
                        <span>Pointer mon Départ</span>
                        <small style="font-size: 11px; opacity: 0.9;">Heure référence: <?php echo substr($parametres['heure_fin_normal'], 0, 5); ?></small>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-pointage" disabled style="background: #9ca3af;">
                        <i class="fas fa-check-circle"></i>
                        <span>Pointage Terminé</span>
                        <small style="font-size: 11px; opacity: 0.9;">Revenez demain</small>
                    </button>
                <?php endif; ?>
                
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
                <input type="hidden" name="adresse_details" id="adresseDetails">
                <input type="hidden" name="quartier" id="quartier">
                <input type="hidden" name="ville" id="ville">
                <input type="hidden" name="heure_appareil" id="heureAppareil" value="">
                <input type="hidden" name="date_appareil" id="dateAppareil" value="">
            </form>

            <!-- Information importante -->
            <div class="info-section">
                <h4><i class="fas fa-mobile-alt"></i> Optimisé Smartphone</h4>
                <p>L'heure LOCALE de votre appareil sera capturée au moment du clic, pas au chargement de la page.</p>
                <p style="margin-top: 5px; font-size: 11px;"><i class="fas fa-info-circle"></i> Basé sur votre fuseau horaire local, pas UTC</p>
            </div>

            <div class="back-link">
                <a href="employee/dashboard.php">
                    <i class="fas fa-arrow-left"></i>
                    Retour au Tableau de Bord
                </a>
            </div>
        </div>
    </div>
<!-- Test de la clé -->
<script>
    console.log("Clé API test: AIzaSyB2D3Vaimk2LMCWPTcPg4NpwnP3rzmNtWM");
</script>
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBnLfjkd6LA8kbjVnKylz1IoBfleWblrSk&callback=initMap" async defer></script>
    
    <script>
        let map;
        let marker;
        let geocoder;
        let watchId;
        let userLatitude = null;
        let userLongitude = null;
        let currentAccuracy = null;
        let isHighAccuracy = false;
        let clickTime = null;
        let clickDate = null;

        // Fonction pour mettre à jour l'heure LOCALE de l'appareil
        function updateDeviceTime() {
            const now = new Date();
            
            // Heure LOCALE (pas UTC)
            const timeString = now.toLocaleTimeString('fr-FR');
            const dateString = now.toLocaleDateString('fr-FR');
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const timezoneOffset = -now.getTimezoneOffset() / 60; // Heure UTC+...
            
            document.getElementById('deviceTime').textContent = timeString;
            document.getElementById('deviceDate').textContent = dateString;
            document.getElementById('deviceTimezone').textContent = timezone;
            
            // Afficher le décalage horaire
            let offsetText = "UTC";
            if (timezoneOffset > 0) {
                offsetText = `UTC+${timezoneOffset}`;
            } else if (timezoneOffset < 0) {
                offsetText = `UTC${timezoneOffset}`;
            }
            document.getElementById('timezoneOffset').textContent = 
                `${timezone} (${offsetText}) - Heure Locale`;
        }

        // Mettre à jour l'heure toutes les secondes
        setInterval(updateDeviceTime, 1000);
        updateDeviceTime(); // Initialisation

        // Capturer l'heure LOCALE précise au moment du clic sur le bouton
        function setupClickTimeCapture() {
            const submitButtons = document.querySelectorAll('.btn-pointage[type="submit"]');
            
            submitButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const now = new Date();
                    
                    // Capturer l'heure et date LOCALES séparément
                    const heureLocale = now.toLocaleTimeString('fr-FR'); // Format: HH:MM:SS
                    const dateLocale = now.toLocaleDateString('fr-FR'); // Format: DD/MM/YYYY
                    
                    console.log("🕐 Heure locale capturée:", heureLocale);
                    console.log("📅 Date locale capturée:", dateLocale);
                    
                    // Convertir la date locale en format YYYY-MM-DD pour MySQL
                    const dateParts = dateLocale.split('/');
                    const dateMySQL = `${dateParts[2]}-${dateParts[1].padStart(2, '0')}-${dateParts[0].padStart(2, '0')}`;
                    
                    // Mettre à jour les champs cachés
                    document.getElementById('heureAppareil').value = heureLocale;
                    document.getElementById('dateAppareil').value = dateMySQL;
                    
                    // Afficher l'heure du pointage à l'utilisateur
                    const action = this.classList.contains('btn-start') ? 'début' : 'fin';
                    
                    document.getElementById('clickTimeText').textContent = heureLocale;
                    document.getElementById('clickDateText').textContent = `Date: ${dateLocale}`;
                    document.getElementById('clickTimeDisplay').style.display = 'block';
                    
                    console.log(`⏰ Pointage ${action} programmé pour:`, heureLocale, "le", dateLocale);
                    console.log(`📦 Données envoyées - Heure: ${heureLocale}, Date: ${dateMySQL}`);
                });
            });
        }

        function initMap() {
            console.log("🚀 Initialisation Google Maps - Mode GPS Smartphone");
            
            // Position par défaut (Douala)
            const defaultPosition = { lat: 4.0511, lng: 9.7679 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 16,
                center: defaultPosition,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                streetViewControl: false,
                fullscreenControl: true,
                mapTypeControl: false,
                gestureHandling: 'greedy' // Meilleur pour mobile
            });

            geocoder = new google.maps.Geocoder();
            
            // Configurer la capture d'heure LOCALE au clic
            setupClickTimeCapture();
            
            // Démarrer la surveillance GPS pour smartphones
            startMobileGPS();
        }

        function startMobileGPS() {
            if (!navigator.geolocation) {
                showError("❌ Géolocalisation non supportée par cet appareil");
                return;
            }

            console.log("📱 Démarrage surveillance GPS mobile...");
            
            // OPTIONS POUR SMARTPHONES (GPS HARDWARE)
            const mobileOptions = {
                enableHighAccuracy: true,    // FORCE le GPS hardware
                maximumAge: 0,              // PAS DE CACHE
                timeout: Infinity           // ATTENDRE INDÉFINIMENT
            };

            // SURVEILLANCE CONTINUE
            watchId = navigator.geolocation.watchPosition(
                function(position) {
                    handleGPSPosition(position);
                },
                function(error) {
                    handleGPSError(error);
                },
                mobileOptions
            );

            // PREMIÈRE POSITION
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    handleGPSPosition(position);
                },
                function(error) {
                    handleGPSError(error);
                },
                mobileOptions
            );
        }

        function handleGPSPosition(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            const isHighAcc = accuracy < 50; // Haute précision si < 50m

            console.log("📍 POSITION GPS MOBILE:", {
                latitude: lat,
                longitude: lng,
                accuracy: accuracy + "m",
                highAccuracy: isHighAcc,
                source: "GPS Hardware"
            });

            userLatitude = lat;
            userLongitude = lng;
            currentAccuracy = accuracy;
            isHighAccuracy = isHighAcc;

            // METTRE À JOUR L'INTERFACE
            updateGPSStatus(isHighAcc);
            updateMapPosition(lat, lng, accuracy);
            getAddressFromCoordinates(lat, lng);
        }

        function updateGPSStatus(highAccuracy) {
            const gpsStatus = document.getElementById('gpsStatus');
            if (highAccuracy) {
                gpsStatus.className = 'gps-status gps-active';
                gpsStatus.innerHTML = '<i class="fas fa-satellite"></i> GPS Actif';
                document.getElementById('locationLoading').className = 'fas fa-check-circle';
                document.getElementById('locationLoading').style.color = '#10b981';
                document.getElementById('locationStatus').textContent = '✅ GPS Haute Précision';
                document.getElementById('locationStatus').style.color = '#065f46';
            } else {
                gpsStatus.className = 'gps-status gps-inactive';
                gpsStatus.innerHTML = '<i class="fas fa-satellite"></i> GPS Faible';
                document.getElementById('locationStatus').textContent = '📡 GPS Moyenne Précision';
                document.getElementById('locationStatus').style.color = '#92400e';
            }
        }

        function updateMapPosition(lat, lng, accuracy) {
            const newPosition = { lat: lat, lng: lng };

            // CENTRER LA CARTE
            map.setCenter(newPosition);
            map.setZoom(17); // ZOOM ÉLEVÉ

            // SUPPRIMER ANCIEN MARQUEUR
            if (marker) marker.setMap(null);

            // CRÉER UN NOUVEAU MARQUEUR
            marker = new google.maps.Marker({
                position: newPosition,
                map: map,
                title: 'Position GPS Smartphone',
                animation: google.maps.Animation.DROP,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: isHighAccuracy ? 10 : 8,
                    fillColor: isHighAccuracy ? '#10b981' : '#f59e0b',
                    fillOpacity: 0.9,
                    strokeColor: '#ffffff',
                    strokeWeight: 2
                }
            });

            // AJOUTER UN CERCLES DE PRÉCISION
            if (accuracy) {
                new google.maps.Circle({
                    strokeColor: isHighAccuracy ? '#10b981' : '#f59e0b',
                    strokeOpacity: 0.8,
                    strokeWeight: 1,
                    fillColor: isHighAccuracy ? '#10b981' : '#f59e0b',
                    fillOpacity: 0.2,
                    map: map,
                    center: newPosition,
                    radius: accuracy
                });
            }

            // METTRE À JOUR LES CHAMPS CACHÉS
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // AFFICHER LES INFORMATIONS
            document.getElementById('coordinates').textContent = 
                `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            
            updateAccuracyDisplay(accuracy);
            document.getElementById('addressDetails').style.display = 'block';
        }

        function updateAccuracyDisplay(accuracy) {
            const accuracyBar = document.getElementById('accuracyBar');
            const accuracyValue = document.getElementById('accuracyValue');
            
            if (accuracy) {
                // CALCUL DU POURCENTAGE DE PRÉCISION
                let accuracyPercent = 100;
                if (accuracy <= 20) accuracyPercent = 95;    // Excellente
                else if (accuracy <= 50) accuracyPercent = 85;  // Très bonne
                else if (accuracy <= 100) accuracyPercent = 70; // Bonne
                else if (accuracy <= 200) accuracyPercent = 50; // Moyenne
                else accuracyPercent = 30; // Faible
                
                accuracyBar.style.width = accuracyPercent + '%';
                accuracyBar.style.backgroundColor = 
                    accuracy <= 20 ? '#10b981' : 
                    accuracy <= 50 ? '#f59e0b' : '#ef4444';
                
                accuracyValue.textContent = accuracy.toFixed(0) + ' m';
            }
        }

        function getAddressFromCoordinates(lat, lng) {
            const latlng = { lat: lat, lng: lng };
            
            geocoder.geocode({ location: latlng }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    const address = results[0].formatted_address;
                    
                    // ANALYSE DÉTAILLÉE DES COMPOSANTS D'ADRESSE
                    let quartier = '';
                    let ville = '';
                    let rue = '';
                    
                    for (let component of results[0].address_components) {
                        const types = component.types;
                        
                        // DÉTECTION DU QUARTIER
                        if (types.includes('sublocality') || types.includes('neighborhood')) {
                            quartier = component.long_name;
                        }
                        // DÉTECTION DE LA VILLE
                        else if (types.includes('locality')) {
                            ville = component.long_name;
                        }
                        // DÉTECTION DE LA RUE
                        else if (types.includes('route')) {
                            rue = component.long_name;
                        }
                        // FALLBACK POUR LA VILLE
                        else if (types.includes('administrative_area_level_2') && !ville) {
                            ville = component.long_name;
                        }
                    }
                    
                    // AFFICHAGE DES INFORMATIONS
                    document.getElementById('fullAddress').textContent = address;
                    document.getElementById('quartierInfo').textContent = quartier || 'Non spécifié';
                    document.getElementById('villeInfo').textContent = ville || 'Non spécifié';
                    
                    // STOCKAGE DANS LES CHAMPS CACHÉS
                    document.getElementById('adresseDetails').value = address;
                    document.getElementById('quartier').value = quartier;
                    document.getElementById('ville').value = ville;
                    
                    // ACTIVATION DES BOUTONS
                    enablePointageButtons();
                    
                } else {
                    console.warn('Geocoding échoué:', status);
                    handleGeocodingError(status);
                }
            });
        }

        function enablePointageButtons() {
            document.getElementById('btnStart').disabled = false;
            if (document.getElementById('btnEnd')) {
                document.getElementById('btnEnd').disabled = false;
            }
        }

        function handleGPSError(error) {
            let message = 'Erreur GPS: ';
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = '🔒 Autorisation GPS refusée. Activez la localisation dans les paramètres de votre appareil.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = '📡 GPS indisponible. Activez le GPS de votre smartphone et vérifiez votre connexion.';
                    break;
                case error.TIMEOUT:
                    message = '⏱️ GPS lent. Déplacez-vous vers un endroit dégagé pour améliorer le signal.';
                    break;
                default:
                    message = '❌ Erreur GPS inconnue';
            }
            
            showError(message);
        }

        function handleGeocodingError(status) {
            const address = 'Adresse non disponible - ' + status;
            document.getElementById('fullAddress').textContent = address;
            document.getElementById('adresseDetails').value = address;
            document.getElementById('addressDetails').style.display = 'block';
            enablePointageButtons();
        }

        function showError(message) {
            document.getElementById('locationStatus').textContent = message;
            document.getElementById('locationStatus').style.color = '#dc2626';
            document.getElementById('locationLoading').className = 'fas fa-exclamation-triangle';
            document.getElementById('locationLoading').style.color = '#dc2626';
            
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('locationError').style.display = 'block';
            document.getElementById('addressDetails').style.display = 'none';
            
            // ACTIVER QUAND MÊME EN MODE DÉGRADÉ
            enablePointageButtons();
        }

        function refreshLocation() {
            console.log("🔄 Actualisation manuelle du GPS...");
            
            document.getElementById('locationLoading').className = 'fas fa-sync fa-spin';
            document.getElementById('locationLoading').style.color = '';
            document.getElementById('locationStatus').textContent = 'Recherche GPS...';
            document.getElementById('locationStatus').style.color = '';
            document.getElementById('btnStart').disabled = true;
            if (document.getElementById('btnEnd')) {
                document.getElementById('btnEnd').disabled = true;
            }
            
            // REDÉMARRER LA SURVEILLANCE
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
            startMobileGPS();
        }

        // GESTION DU FORMULAIRE
        document.getElementById('pointageForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            
            if (btn.disabled) {
                e.preventDefault();
                alert('🛑 Veuillez attendre la détection GPS...');
                return;
            }
            
            if (!userLatitude || !userLongitude) {
                e.preventDefault();
                alert('❌ Position GPS non disponible');
                return;
            }
            
            const heureAppareil = document.getElementById('heureAppareil').value;
            const dateAppareil = document.getElementById('dateAppareil').value;
            
            if (!heureAppareil || !dateAppareil) {
                e.preventDefault();
                alert('❌ Erreur: heure ou date non capturée. Veuillez réessayer en cliquant à nouveau sur le bouton.');
                return;
            }
            
            console.log("📋 Données à envoyer:", {
                heure: heureAppareil,
                date: dateAppareil,
                lat: userLatitude,
                lng: userLongitude
            });
            
            // AFFICHER CONFIRMATION AVEC L'HEURE EXACTE
            const action = document.querySelector('input[name="action"]').value;
            
            let confirmationMessage = '';
            if (action === 'fin') {
                confirmationMessage = `✅ Confirmez-vous votre départ à ${heureAppareil} ?`;
            } else {
                confirmationMessage = `✅ Confirmez-vous votre arrivée à ${heureAppareil} ?`;
            }
            
            if (!confirm(confirmationMessage)) {
                e.preventDefault();
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<div class="loading"></div> Enregistrement...';
        });

        // NETTOYAGE
        window.addEventListener('beforeunload', function() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });

        // DÉTECTION MOBILE
        function isMobileDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        // OPTIMISATION MOBILE AU CHARGEMENT
        if (isMobileDevice()) {
            document.body.style.padding = '5px';
            console.log("📱 Mode mobile détecté - Optimisations activées");
        }

        // RECHARGEMENT APRÈS SUCCÈS
        <?php if ($message_type === 'success'): ?>
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>