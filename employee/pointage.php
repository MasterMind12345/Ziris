<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les préférences utilisateur
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, font_size, notifications, accessibility_mode) VALUES (?, 'light', 'medium', 1, 0)");
        $stmt->execute([$user_id]);
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch();
    }
} catch(PDOException $e) {
    $preferences = ['theme' => 'light', 'font_size' => 'medium', 'notifications' => 1, 'accessibility_mode' => 0];
}

$currentTheme = $preferences['theme'] ?? 'light';

// Récupérer les informations utilisateur
try {
    $stmt = $pdo->prepare("SELECT u.*, p.nom as poste_nom FROM users u LEFT JOIN postes p ON u.poste_id = p.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données.");
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
    $parametres = [
        'heure_debut_normal' => '08:00:00', 
        'heure_fin_normal' => '17:00:00',
        'debut_pause_normal' => '12:00:00',
        'fin_pause_normal' => '13:00:00'
    ];
}

$message = '';
$message_type = '';

// Traitement du pointage avec géolocalisation avancée
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $adresse_details = $_POST['adresse_details'] ?? '';
    $quartier = $_POST['quartier'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $heure_appareil = $_POST['heure_appareil'] ?? ''; // Heure locale HH:MM:SS
    $date_appareil = $_POST['date_appareil'] ?? '';   // Date locale YYYY-MM-DD
    
    error_log("Pointage attempt - Action: $action, User: $user_id, Heure Appareil: $heure_appareil, Date Appareil: $date_appareil");
    
    try {
        if ($action === 'debut' && !$presence_aujourdhui) {
            // Pointage de début - UTILISER L'HEURE LOCALE
            if (!empty($heure_appareil) && !empty($date_appareil)) {
                // Combiner date et heure locales
                $date_heure_complete = $date_appareil . ' ' . $heure_appareil;
                $heure_debut = date('H:i:s', strtotime($date_heure_complete));
                $date_presence = date('Y-m-d', strtotime($date_heure_complete));
                error_log("✅ Pointage début - Heure locale: $heure_appareil, Date locale: $date_appareil => MySQL: $heure_debut, Date: $date_presence");
            } else {
                $heure_debut = date('H:i:s');
                $date_presence = date('Y-m-d');
                error_log("⚠️ Heure appareil manquante, utilisation heure serveur local");
            }
            
            // Calcul du retard
            $retard_minutes = 0;
            if ($heure_debut > $parametres['heure_debut_normal']) {
                $diff = strtotime($heure_debut) - strtotime($parametres['heure_debut_normal']);
                $retard_minutes = floor($diff / 60);
            }
            
            // Construction de l'adresse
            $adresse_complete = $adresse_details;
            if ($quartier) $adresse_complete .= " | Quartier: " . $quartier;
            if ($ville) $adresse_complete .= " | Ville: " . $ville;
            
            $stmt = $pdo->prepare("INSERT INTO presences (user_id, date_presence, heure_debut_reel, lieu, retard_minutes, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $date_presence, $heure_debut, $adresse_complete, $retard_minutes, $latitude, $longitude]);
            
            $message = "✅ Pointage de début enregistré à " . $heure_debut . " (Heure locale appareil)";
            if ($retard_minutes > 0) $message .= " (Retard: " . $retard_minutes . " minutes)";
            $message_type = 'success';
            
        } elseif ($action === 'pause_debut' && $presence_aujourdhui && !$presence_aujourdhui['heure_pause_debut']) {
            // Début de pause - UTILISER L'HEURE LOCALE
            if (!empty($heure_appareil)) {
                $heure_pause_debut = $heure_appareil; // Déjà au format HH:MM:SS
                error_log("✅ Début pause - Heure locale: $heure_pause_debut");
            } else {
                $heure_pause_debut = date('H:i:s');
                error_log("⚠️ Heure appareil manquante, utilisation heure serveur local");
            }
            
            $adresse_complete = $adresse_details;
            if ($quartier) $adresse_complete .= " | Quartier: " . $quartier;
            if ($ville) $adresse_complete .= " | Ville: " . $ville;
            
            $stmt = $pdo->prepare("UPDATE presences SET heure_pause_debut = ?, lieu_pause_debut = ?, latitude_pause_debut = ?, longitude_pause_debut = ? WHERE id = ?");
            $stmt->execute([$heure_pause_debut, $adresse_complete, $latitude, $longitude, $presence_aujourdhui['id']]);
            
            $message = "⏸️ Début de pause enregistré à " . $heure_pause_debut . " (Heure locale appareil)";
            $message_type = 'success';
            
        } elseif ($action === 'pause_fin' && $presence_aujourdhui && $presence_aujourdhui['heure_pause_debut'] && !$presence_aujourdhui['heure_pause_fin']) {
            // Fin de pause - UTILISER L'HEURE LOCALE
            if (!empty($heure_appareil)) {
                $heure_pause_fin = $heure_appareil; // Déjà au format HH:MM:SS
                error_log("✅ Fin pause - Heure locale: $heure_pause_fin");
            } else {
                $heure_pause_fin = date('H:i:s');
                error_log("⚠️ Heure appareil manquante, utilisation heure serveur local");
            }
            
            $adresse_complete = $adresse_details;
            if ($quartier) $adresse_complete .= " | Quartier: " . $quartier;
            if ($ville) $adresse_complete .= " | Ville: " . $ville;
            
            $stmt = $pdo->prepare("UPDATE presences SET heure_pause_fin = ?, lieu_pause_fin = ?, latitude_pause_fin = ?, longitude_pause_fin = ? WHERE id = ?");
            $stmt->execute([$heure_pause_fin, $adresse_complete, $latitude, $longitude, $presence_aujourdhui['id']]);
            
            $message = "▶️ Fin de pause enregistrée à " . $heure_pause_fin . " (Heure locale appareil)";
            $message_type = 'success';
            
        } elseif ($action === 'fin' && $presence_aujourdhui && !$presence_aujourdhui['heure_fin_reel']) {
            // Pointage de fin - UTILISER L'HEURE LOCALE
            if (!empty($heure_appareil)) {
                $heure_fin = $heure_appareil; // Déjà au format HH:MM:SS
                error_log("✅ Pointage fin - Heure locale: $heure_fin");
            } else {
                $heure_fin = date('H:i:s');
                error_log("⚠️ Heure appareil manquante, utilisation heure serveur local");
            }
            
            $adresse_complete = $adresse_details;
            if ($quartier) $adresse_complete .= " | Quartier: " . $quartier;
            if ($ville) $adresse_complete .= " | Ville: " . $ville;
            
            $stmt = $pdo->prepare("UPDATE presences SET heure_fin_reel = ?, lieu_fin = ?, latitude_fin = ?, longitude_fin = ? WHERE id = ?");
            $stmt->execute([$heure_fin, $adresse_complete, $latitude, $longitude, $presence_aujourdhui['id']]);
            
            $message = "🛑 Pointage de fin enregistré à " . $heure_fin . " (Heure locale appareil)";
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
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
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
            line-height: 1.6;
        }

        /* Header */
        .employee-header {
            background: var(--bg-card);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            max-width: 1400px;
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
            color: var(--text-secondary);
        }

        .logout-btn {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        /* Navigation */
        .employee-nav {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 70px;
            z-index: 999;
        }

        .nav-content {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            overflow-x: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }

        .nav-item:hover, .nav-item.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Main Content */
        .employee-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
            background: var(--bg-primary);
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
            animation: slideIn 0.5s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        /* Pointage Interface */
        .pointage-interface {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .pointage-interface {
                grid-template-columns: 1fr;
            }
        }

        /* Status Card */
        .status-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }

        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .status-header h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            color: var(--text-primary);
        }

        .current-date {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
        }

        .status-content {
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .status-complete, .status-in-progress, .status-pending, .status-pause {
            text-align: center;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .status-complete {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-color: #10b981;
        }

        .status-in-progress {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border-color: #f59e0b;
        }

        .status-pause {
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #1e40af;
            border-color: #3b82f6;
        }

        .status-pending {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            color: #374151;
            border-color: #9ca3af;
        }

        .status-icon {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .status-complete h3, .status-in-progress h3, .status-pause h3, .status-pending h3 {
            margin-bottom: 12px;
            font-size: 20px;
            font-weight: 700;
        }

        .pointage-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 25px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--bg-primary);
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .detail-item:hover {
            background: var(--bg-secondary);
            transform: translateX(8px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .detail-item .label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-item .value {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 15px;
            font-family: 'Courier New', monospace;
        }

        .detail-item .value.warning {
            color: var(--warning);
            background: rgba(245, 158, 11, 0.1);
            padding: 6px 12px;
            border-radius: 8px;
        }

        .detail-item .value.success {
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            padding: 6px 12px;
            border-radius: 8px;
        }

        /* Actions Card */
        .actions-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }

        .actions-card h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            font-size: 22px;
            color: var(--text-primary);
        }

        .actions-content {
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Time Info */
        .time-info {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            border-left: 5px solid var(--success);
            border: 1px solid var(--border-color);
        }

        .time-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .time-label {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .time-value {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: var(--success);
            font-size: 16px;
            background: rgba(16, 185, 129, 0.1);
            padding: 6px 12px;
            border-radius: 8px;
        }

        .time-note {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 10px;
            font-style: italic;
        }
        
        .timezone-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .timezone-info {
            background: #f3e8ff;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 11px;
            color: #7c3aed;
            text-align: center;
            margin-top: 10px;
            border: 1px solid #ddd6fe;
        }

        /* Geolocation Section */
        .geolocation-section {
            margin-bottom: 25px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 15px;
            border-left: 5px solid var(--primary);
            border: 1px solid var(--border-color);
        }

        .geolocation-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .geolocation-header i {
            color: var(--primary);
            font-size: 20px;
        }

        .geolocation-header h3 {
            font-size: 18px;
            color: var(--text-primary);
        }

        .map-container {
            height: 300px;
            background: var(--bg-secondary);
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            position: relative;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .location-info {
            font-size: 14px;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .address-details {
            background: var(--bg-card);
            padding: 15px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            margin-top: 15px;
            line-height: 1.5;
        }

        .location-breakdown {
            margin-top: 12px;
            padding: 12px;
            background: var(--bg-secondary);
            border-radius: 8px;
        }

        .accuracy-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .accuracy-bar {
            flex-grow: 1;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }

        .accuracy-fill {
            height: 100%;
            background: var(--success);
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .gps-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
            font-weight: 600;
        }

        .gps-active {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .gps-inactive {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-pointage {
            width: 100%;
            padding: 20px;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-start {
            background: linear-gradient(135deg, var(--success), #0da271);
            color: white;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-start:hover:not(:disabled) {
            background: linear-gradient(135deg, #0da271, #0c925f);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-pause {
            background: linear-gradient(135deg, var(--info), #2563eb);
            color: white;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-pause:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-resume {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }

        .btn-resume:hover:not(:disabled) {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
        }

        .btn-end {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-end:hover:not(:disabled) {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        .btn-pointage:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.6;
        }

        .btn-pointage small {
            font-size: 12px;
            opacity: 0.9;
            font-weight: 400;
            text-transform: none;
            letter-spacing: 0;
        }

        .manual-location-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }

        .manual-location-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Info Card */
        .info-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            grid-column: 1 / -1;
        }

        .info-card h3 {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
            font-size: 22px;
        }

        .info-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 25px;
            background: var(--bg-secondary);
            border-radius: 15px;
            transition: all 0.3s ease;
            border-left: 5px solid var(--primary);
        }

        .info-item:hover {
            background: var(--bg-card);
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .info-item i {
            color: var(--primary);
            font-size: 24px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-item h4 {
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 18px;
        }

        .info-item p {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
            line-height: 1.6;
        }

        /* Heure Exacte */
        .heure-exacte {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin: 10px 0;
            text-align: center;
            font-weight: 600;
            display: none;
            animation: pulse 1.5s infinite;
        }
        
        .heure-exacte-details {
            font-size: 10px;
            opacity: 0.9;
            margin-top: 3px;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
            }
            70% {
                transform: scale(1.02);
                box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
                padding: 0 20px;
            }

            .pointage-interface {
                gap: 20px;
            }

            .status-card, .actions-card, .info-card {
                padding: 20px;
            }

            .status-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .current-date {
                align-self: flex-end;
            }

            .map-container {
                height: 250px;
            }

            .btn-pointage {
                padding: 18px;
                font-size: 14px;
            }

            .info-content {
                grid-template-columns: 1fr;
            }

            .info-item {
                padding: 20px;
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

            .status-complete, .status-in-progress, .status-pause, .status-pending {
                padding: 20px 15px;
            }

            .status-icon {
                font-size: 48px;
            }
        }
    </style>
</head>
<body>
    <header class="employee-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-satellite"></i> Ziris</h1>
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
                        echo substr($initials, 0, 2);
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
                <i class="fas fa-fingerprint"></i>
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
            <h1>Pointage GPS Intelligent</h1>
            <p>Localisation précise avec GPS hardware de votre smartphone - Heure locale</p>
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
                                <div class="status-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3>Journée Complète</h3>
                                <p>Votre pointage est terminé pour aujourd'hui</p>
                            </div>
                            <div class="pointage-details">
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-play"></i> Début:</span>
                                    <span class="value success"><?php echo $presence_aujourdhui['heure_debut_reel']; ?></span>
                                </div>
                                <?php if ($presence_aujourdhui['heure_pause_debut']): ?>
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-pause"></i> Pause début:</span>
                                    <span class="value"><?php echo $presence_aujourdhui['heure_pause_debut']; ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($presence_aujourdhui['heure_pause_fin']): ?>
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-play"></i> Pause fin:</span>
                                    <span class="value"><?php echo $presence_aujourdhui['heure_pause_fin']; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-stop"></i> Fin:</span>
                                    <span class="value success"><?php echo $presence_aujourdhui['heure_fin_reel']; ?></span>
                                </div>
                                <?php if ($presence_aujourdhui['retard_minutes'] > 0): ?>
                                    <div class="detail-item">
                                        <span class="label"><i class="fas fa-clock"></i> Retard:</span>
                                        <span class="value warning"><?php echo $presence_aujourdhui['retard_minutes']; ?> minutes</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($presence_aujourdhui['heure_pause_debut'] && !$presence_aujourdhui['heure_pause_fin']): ?>
                            <div class="status-pause">
                                <div class="status-icon">
                                    <i class="fas fa-pause-circle"></i>
                                </div>
                                <h3>En Pause</h3>
                                <p>Pause commencée à <?php echo $presence_aujourdhui['heure_pause_debut']; ?></p>
                            </div>
                            <div class="pointage-details">
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-play"></i> Début:</span>
                                    <span class="value"><?php echo $presence_aujourdhui['heure_debut_reel']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-pause"></i> Pause début:</span>
                                    <span class="value warning"><?php echo $presence_aujourdhui['heure_pause_debut']; ?></span>
                                </div>
                                <?php if ($presence_aujourdhui['retard_minutes'] > 0): ?>
                                    <div class="detail-item">
                                        <span class="label"><i class="fas fa-clock"></i> Retard:</span>
                                        <span class="value warning"><?php echo $presence_aujourdhui['retard_minutes']; ?> minutes</span>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-hourglass"></i> Durée totale:</span>
                                    <span class="value" id="dureeTotale">Calcul...</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="status-in-progress">
                                <div class="status-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3>En Cours de Travail</h3>
                                <p>Début: <?php echo $presence_aujourdhui['heure_debut_reel']; ?></p>
                            </div>
                            <div class="pointage-details">
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-play"></i> Début:</span>
                                    <span class="value success"><?php echo $presence_aujourdhui['heure_debut_reel']; ?></span>
                                </div>
                                <?php if ($presence_aujourdhui['retard_minutes'] > 0): ?>
                                    <div class="detail-item">
                                        <span class="label"><i class="fas fa-clock"></i> Retard:</span>
                                        <span class="value warning"><?php echo $presence_aujourdhui['retard_minutes']; ?> minutes</span>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="label"><i class="fas fa-hourglass"></i> Durée travaillée:</span>
                                    <span class="value" id="dureeTravail">Calcul...</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-pending">
                            <div class="status-icon">
                                <i class="fas fa-hourglass-start"></i>
                            </div>
                            <h3>En Attente de Pointage</h3>
                            <p>Vous n'avez pas encore pointé aujourd'hui</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions de pointage -->
            <div class="actions-card">
                <h2><i class="fas fa-rocket"></i> Actions de Pointage</h2>
                <div class="actions-content">
                    
                    <!-- Affichage de l'heure LOCALE de l'appareil -->
                    <div class="time-info">
                        <div class="time-display">
                            <span class="time-label"><i class="fas fa-clock"></i> Heure Locale:</span>
                            <span class="time-value" id="deviceTime">--:--:--</span>
                        </div>
                        <div class="time-display">
                            <span class="time-label"><i class="fas fa-calendar"></i> Date Locale:</span>
                            <span class="time-value" id="deviceDate">--/--/----</span>
                        </div>
                        <div class="timezone-display">
                            <span class="time-label"><i class="fas fa-globe"></i> Fuseau Horaire:</span>
                            <span class="time-value" id="deviceTimezone">--</span>
                        </div>
                        <div class="timezone-info">
                            <i class="fas fa-info-circle"></i> 
                            <span id="timezoneOffset">Détection fuseau horaire...</span>
                        </div>
                        <div class="time-note">
                            ⏰ L'heure LOCALE de votre appareil sera utilisée pour le pointage
                        </div>
                    </div>

                    <!-- Géolocalisation et Carte -->
                    <div class="geolocation-section">
                        <div class="geolocation-header">
                            <i class="fas fa-map-marker-alt"></i>
                            <h3>Localisation GPS Directe</h3>
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
                            <div id="fullAddress" style="margin: 8px 0; font-weight: 600; font-size: 12px;"></div>
                            
                            <div class="location-breakdown">
                                <div style="margin-bottom: 6px;">
                                    <strong>🏘️ Quartier:</strong> 
                                    <span id="quartierInfo" style="color: var(--primary); font-weight: 600;">Détection...</span>
                                </div>
                                <div style="margin-bottom: 6px;">
                                    <strong>🏙️ Ville:</strong> 
                                    <span id="villeInfo" style="color: var(--primary); font-weight: 600;">Détection...</span>
                                </div>
                                <div style="margin-bottom: 6px;">
                                    <strong>📡 Coordonnées:</strong> 
                                    <span id="coordinates" style="font-family: monospace; font-size: 11px;"></span>
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
                            <div id="errorMessage" style="margin: 8px 0; font-size: 12px;"></div>
                            <button type="button" class="manual-location-btn" onclick="refreshLocation()" style="background: var(--danger);">
                                <i class="fas fa-redo"></i> Réessayer
                            </button>
                        </div>
                    </div>

                    <!-- Heure exacte lors du clic -->
                    <div id="heureExacteDisplay" class="heure-exacte" style="display: none;">
                        <i class="fas fa-clock"></i> 
                        <span id="heureExacteTexte">Heure locale exacte: --:--:--</span>
                        <div id="heureExacteDate" class="heure-exacte-details">Date: --/--/----</div>
                    </div>

                    <!-- Actions de pointage -->
                    <div class="action-buttons">
                        <?php if (!$presence_aujourdhui): ?>
                            <form method="POST" id="pointageForm" class="pointage-form">
                                <input type="hidden" name="action" value="debut">
                                <input type="hidden" name="latitude" id="latitude">
                                <input type="hidden" name="longitude" id="longitude">
                                <input type="hidden" name="adresse_details" id="adresseDetails">
                                <input type="hidden" name="quartier" id="quartier">
                                <input type="hidden" name="ville" id="ville">
                                <input type="hidden" name="heure_appareil" id="heureAppareil">
                                <input type="hidden" name="date_appareil" id="dateAppareil">
                                
                                <button type="submit" class="btn-pointage btn-start" id="btnStart" disabled>
                                    <i class="fas fa-play-circle"></i>
                                    <span>Pointer mon Arrivée</span>
                                    <small>Heure référence: <?php echo substr($parametres['heure_debut_normal'], 0, 5); ?></small>
                                </button>
                            </form>
                        <?php elseif (!$presence_aujourdhui['heure_fin_reel']): ?>
                            <?php if (!$presence_aujourdhui['heure_pause_debut']): ?>
                                <form method="POST" id="pauseDebutForm" class="pointage-form">
                                    <input type="hidden" name="action" value="pause_debut">
                                    <input type="hidden" name="latitude" id="latitudePauseDebut">
                                    <input type="hidden" name="longitude" id="longitudePauseDebut">
                                    <input type="hidden" name="adresse_details" id="adresseDetailsPauseDebut">
                                    <input type="hidden" name="quartier" id="quartierPauseDebut">
                                    <input type="hidden" name="ville" id="villePauseDebut">
                                    <input type="hidden" name="heure_appareil" id="heureAppareilPauseDebut">
                                    
                                    <button type="submit" class="btn-pointage btn-pause" id="btnPauseDebut" disabled>
                                        <i class="fas fa-pause-circle"></i>
                                        <span>Début de Pause</span>
                                        <small>Heure référence: <?php echo substr($parametres['debut_pause_normal'], 0, 5); ?></small>
                                    </button>
                                </form>
                            <?php elseif (!$presence_aujourdhui['heure_pause_fin']): ?>
                                <form method="POST" id="pauseFinForm" class="pointage-form">
                                    <input type="hidden" name="action" value="pause_fin">
                                    <input type="hidden" name="latitude" id="latitudePauseFin">
                                    <input type="hidden" name="longitude" id="longitudePauseFin">
                                    <input type="hidden" name="adresse_details" id="adresseDetailsPauseFin">
                                    <input type="hidden" name="quartier" id="quartierPauseFin">
                                    <input type="hidden" name="ville" id="villePauseFin">
                                    <input type="hidden" name="heure_appareil" id="heureAppareilPauseFin">
                                    
                                    <button type="submit" class="btn-pointage btn-resume" id="btnPauseFin" disabled>
                                        <i class="fas fa-play-circle"></i>
                                        <span>Fin de Pause</span>
                                        <small>Heure référence: <?php echo substr($parametres['fin_pause_normal'], 0, 5); ?></small>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" id="pointageFinForm" class="pointage-form">
                                <input type="hidden" name="action" value="fin">
                                <input type="hidden" name="latitude" id="latitudeFin">
                                <input type="hidden" name="longitude" id="longitudeFin">
                                <input type="hidden" name="adresse_details" id="adresseDetailsFin">
                                <input type="hidden" name="quartier" id="quartierFin">
                                <input type="hidden" name="ville" id="villeFin">
                                <input type="hidden" name="heure_appareil" id="heureAppareilFin">
                                
                                <button type="submit" class="btn-pointage btn-end" id="btnEnd" disabled>
                                    <i class="fas fa-stop-circle"></i>
                                    <span>Pointer mon Départ</span>
                                    <small>Heure référence: <?php echo substr($parametres['heure_fin_normal'], 0, 5); ?></small>
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn-pointage" disabled style="background: var(--gray);">
                                <i class="fas fa-check-circle"></i>
                                <span>Pointage Terminé</span>
                                <small>Revenez demain</small>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations importantes -->
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Système de Pointage Intelligent</h3>
            <div class="info-content">
                <div class="info-item">
                    <i class="fas fa-satellite"></i>
                    <div>
                        <h4>GPS Hardware Actif</h4>
                        <p>Utilisation du GPS hardware de votre smartphone pour une localisation ultra-précise du quartier et de la rue.</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-map-marked-alt"></i>
                    <div>
                        <h4>Multi-localisation</h4>
                        <p>Enregistrement précis de chaque action : arrivée, début/fin de pause, et départ avec coordonnées exactes.</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h4>Heure Locale Exacte</h4>
                        <p>Utilisation de l'heure locale de votre appareil pour éviter les décalages horaires et garantir la précision.</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h4>Sécurité Renforcée</h4>
                        <p>Détection des tentatives de fraude grâce à la vérification GPS et l'heure réelle locale de l'appareil.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

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

        // Fonction pour capturer l'heure LOCALE exacte
        function captureLocalTime() {
            const now = new Date();
            
            // Format HH:MM:SS (24h)
            const heure = now.getHours().toString().padStart(2, '0') + ':' + 
                         now.getMinutes().toString().padStart(2, '0') + ':' + 
                         now.getSeconds().toString().padStart(2, '0');
            
            // Format YYYY-MM-DD
            const date = now.getFullYear() + '-' + 
                        (now.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                        now.getDate().toString().padStart(2, '0');
            
            // Heure d'affichage locale
            const heureAffichage = now.toLocaleTimeString('fr-FR', {hour12: false});
            const dateAffichage = now.toLocaleDateString('fr-FR');
            
            // Fuseau horaire
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const timezoneOffset = -now.getTimezoneOffset() / 60;
            
            return {
                heure: heure,
                date: date,
                heureAffichage: heureAffichage,
                dateAffichage: dateAffichage,
                timezone: timezone,
                timezoneOffset: timezoneOffset
            };
        }

        // Fonction pour mettre à jour l'heure LOCALE de l'appareil en temps réel
        function updateDeviceTime() {
            const localTime = captureLocalTime();
            
            document.getElementById('deviceTime').textContent = localTime.heureAffichage;
            document.getElementById('deviceDate').textContent = localTime.dateAffichage;
            document.getElementById('deviceTimezone').textContent = localTime.timezone;
            
            // Afficher le décalage horaire
            let offsetText = "UTC";
            if (localTime.timezoneOffset > 0) {
                offsetText = `UTC+${localTime.timezoneOffset}`;
            } else if (localTime.timezoneOffset < 0) {
                offsetText = `UTC${localTime.timezoneOffset}`;
            }
            document.getElementById('timezoneOffset').textContent = 
                `${localTime.timezone} (${offsetText}) - Heure Locale Réelle`;
        }

        // Mettre à jour l'heure toutes les secondes
        setInterval(updateDeviceTime, 1000);
        updateDeviceTime();

        // Calcul de la durée de travail
        function updateWorkingTime() {
            <?php if ($presence_aujourdhui && !$presence_aujourdhui['heure_fin_reel']): ?>
                const startTime = new Date('<?php echo date('Y-m-d'); ?>T<?php echo $presence_aujourdhui['heure_debut_reel']; ?>');
                const now = new Date();
                const diff = now - startTime;
                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                
                const dureeElement = document.getElementById('dureeTravail');
                if (dureeElement) dureeElement.textContent = hours + 'h ' + minutes + 'min';
                
                <?php if ($presence_aujourdhui['heure_pause_debut'] && !$presence_aujourdhui['heure_pause_fin']): ?>
                    const pauseStart = new Date('<?php echo date('Y-m-d'); ?>T<?php echo $presence_aujourdhui['heure_pause_debut']; ?>');
                    const totalDiff = pauseStart - startTime;
                    const totalHours = Math.floor(totalDiff / 3600000);
                    const totalMinutes = Math.floor((totalDiff % 3600000) / 60000);
                    
                    const dureeTotaleElement = document.getElementById('dureeTotale');
                    if (dureeTotaleElement) dureeTotaleElement.textContent = totalHours + 'h ' + totalMinutes + 'min';
                <?php endif; ?>
            <?php endif; ?>
        }

        setInterval(updateWorkingTime, 60000);
        updateWorkingTime();

        function initMap() {
            console.log("🚀 Initialisation Google Maps - Mode GPS Smartphone");
            
            const defaultPosition = { lat: 4.0511, lng: 9.7679 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 16,
                center: defaultPosition,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                streetViewControl: false,
                fullscreenControl: true,
                mapTypeControl: false,
                gestureHandling: 'greedy'
            });

            geocoder = new google.maps.Geocoder();
            startMobileGPS();
        }

        function startMobileGPS() {
            if (!navigator.geolocation) {
                showError("❌ Géolocalisation non supportée par cet appareil");
                return;
            }

            console.log("📱 Démarrage surveillance GPS mobile...");
            
            const mobileOptions = {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: Infinity
            };

            watchId = navigator.geolocation.watchPosition(
                function(position) {
                    handleGPSPosition(position);
                },
                function(error) {
                    handleGPSError(error);
                },
                mobileOptions
            );

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
            const isHighAcc = accuracy < 50;

            console.log("📍 POSITION GPS MOBILE:", {
                latitude: lat,
                longitude: lng,
                accuracy: accuracy + "m",
                highAccuracy: isHighAcc
            });

            userLatitude = lat;
            userLongitude = lng;
            currentAccuracy = accuracy;
            isHighAccuracy = isHighAcc;

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

            map.setCenter(newPosition);
            map.setZoom(17);

            if (marker) marker.setMap(null);

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

            // Mettre à jour tous les formulaires avec la position actuelle
            updateAllFormsWithLocation(lat, lng);
            
            document.getElementById('coordinates').textContent = 
                `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            
            updateAccuracyDisplay(accuracy);
            document.getElementById('addressDetails').style.display = 'block';
        }

        function updateAllFormsWithLocation(lat, lng) {
            const forms = [
                { lat: 'latitude', lng: 'longitude', adresse: 'adresseDetails', quartier: 'quartier', ville: 'ville', date: 'dateAppareil' },
                { lat: 'latitudePauseDebut', lng: 'longitudePauseDebut', adresse: 'adresseDetailsPauseDebut', quartier: 'quartierPauseDebut', ville: 'villePauseDebut' },
                { lat: 'latitudePauseFin', lng: 'longitudePauseFin', adresse: 'adresseDetailsPauseFin', quartier: 'quartierPauseFin', ville: 'villePauseFin' },
                { lat: 'latitudeFin', lng: 'longitudeFin', adresse: 'adresseDetailsFin', quartier: 'quartierFin', ville: 'villeFin' }
            ];
            
            forms.forEach(form => {
                const latElement = document.getElementById(form.lat);
                const lngElement = document.getElementById(form.lng);
                if (latElement) latElement.value = lat;
                if (lngElement) lngElement.value = lng;
            });
        }

        function updateAccuracyDisplay(accuracy) {
            const accuracyBar = document.getElementById('accuracyBar');
            const accuracyValue = document.getElementById('accuracyValue');
            
            if (accuracy) {
                let accuracyPercent = 100;
                if (accuracy <= 20) accuracyPercent = 95;
                else if (accuracy <= 50) accuracyPercent = 85;
                else if (accuracy <= 100) accuracyPercent = 70;
                else if (accuracy <= 200) accuracyPercent = 50;
                else accuracyPercent = 30;
                
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
                    
                    let quartier = '';
                    let ville = '';
                    
                    for (let component of results[0].address_components) {
                        const types = component.types;
                        
                        if (types.includes('sublocality') || types.includes('neighborhood')) {
                            quartier = component.long_name;
                        }
                        else if (types.includes('locality')) {
                            ville = component.long_name;
                        }
                        else if (types.includes('administrative_area_level_2') && !ville) {
                            ville = component.long_name;
                        }
                    }
                    
                    document.getElementById('fullAddress').textContent = address;
                    document.getElementById('quartierInfo').textContent = quartier || 'Non spécifié';
                    document.getElementById('villeInfo').textContent = ville || 'Non spécifié';
                    
                    // Mettre à jour tous les formulaires avec l'adresse
                    updateAllFormsWithAddress(address, quartier, ville);
                    
                    enablePointageButtons();
                    
                } else {
                    console.warn('Geocoding échoué:', status);
                    handleGeocodingError(status);
                }
            });
        }

        function updateAllFormsWithAddress(address, quartier, ville) {
            const forms = [
                { adresse: 'adresseDetails', quartier: 'quartier', ville: 'ville' },
                { adresse: 'adresseDetailsPauseDebut', quartier: 'quartierPauseDebut', ville: 'villePauseDebut' },
                { adresse: 'adresseDetailsPauseFin', quartier: 'quartierPauseFin', ville: 'villePauseFin' },
                { adresse: 'adresseDetailsFin', quartier: 'quartierFin', ville: 'villeFin' }
            ];
            
            forms.forEach(form => {
                const adresseElement = document.getElementById(form.adresse);
                const quartierElement = document.getElementById(form.quartier);
                const villeElement = document.getElementById(form.ville);
                
                if (adresseElement) adresseElement.value = address;
                if (quartierElement) quartierElement.value = quartier;
                if (villeElement) villeElement.value = ville;
            });
        }

        function enablePointageButtons() {
            const buttons = [
                'btnStart', 'btnPauseDebut', 'btnPauseFin', 'btnEnd'
            ];
            
            buttons.forEach(buttonId => {
                const button = document.getElementById(buttonId);
                if (button) button.disabled = false;
            });
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
            updateAllFormsWithAddress(address, '', '');
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
            
            enablePointageButtons();
        }

        function refreshLocation() {
            console.log("🔄 Actualisation manuelle du GPS...");
            
            document.getElementById('locationLoading').className = 'fas fa-sync fa-spin';
            document.getElementById('locationLoading').style.color = '';
            document.getElementById('locationStatus').textContent = 'Recherche GPS...';
            document.getElementById('locationStatus').style.color = '';
            
            const buttons = ['btnStart', 'btnPauseDebut', 'btnPauseFin', 'btnEnd'];
            buttons.forEach(buttonId => {
                const button = document.getElementById(buttonId);
                if (button) button.disabled = true;
            });
            
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
            startMobileGPS();
        }

        // Gestion des formulaires avec heure LOCALE exacte
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.pointage-form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
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
                    
                    // 🔥 CAPTURE L'HEURE LOCALE EXACTE AU MOMENT DU CLIC
                    const localTime = captureLocalTime();
                    console.log('🕐 Heure locale exacte capturée:', localTime);
                    
                    // Mettre à jour les champs d'heure et de date dans le formulaire actuel
                    const heureField = this.querySelector('input[id*="heureAppareil"]');
                    const dateField = this.querySelector('input[id*="dateAppareil"]');
                    
                    if (heureField) {
                        heureField.value = localTime.heure; // Format HH:MM:SS
                        console.log('✅ Heure locale enregistrée dans le champ:', heureField.value);
                    }
                    
                    if (dateField) {
                        dateField.value = localTime.date; // Format YYYY-MM-DD
                        console.log('✅ Date locale enregistrée dans le champ:', dateField.value);
                    }
                    
                    // Afficher l'heure exacte à l'utilisateur
                    const heureDisplay = document.getElementById('heureExacteDisplay');
                    const heureTexte = document.getElementById('heureExacteTexte');
                    const heureDate = document.getElementById('heureExacteDate');
                    
                    heureTexte.textContent = `Heure locale exacte: ${localTime.heureAffichage}`;
                    heureDate.textContent = `Date: ${localTime.dateAffichage}`;
                    heureDisplay.style.display = 'block';
                    
                    // Confirmation pour les actions importantes
                    const action = this.querySelector('input[name="action"]').value;
                    let confirmMessage = '';
                    
                    switch(action) {
                        case 'debut':
                            confirmMessage = `✅ Confirmez-vous votre arrivée ?\n\nHeure Locale: ${localTime.heureAffichage}\nDate: ${localTime.dateAffichage}`;
                            break;
                        case 'pause_debut':
                            confirmMessage = `⏸️ Confirmez-vous le début de votre pause ?\n\nHeure Locale: ${localTime.heureAffichage}`;
                            break;
                        case 'pause_fin':
                            confirmMessage = `▶️ Confirmez-vous la fin de votre pause ?\n\nHeure Locale: ${localTime.heureAffichage}`;
                            break;
                        case 'fin':
                            confirmMessage = `🛑 Confirmez-vous votre départ ?\n\nHeure Locale: ${localTime.heureAffichage}`;
                            break;
                    }
                    
                    if (confirmMessage && !confirm(confirmMessage)) {
                        e.preventDefault();
                        heureDisplay.style.display = 'none';
                        return;
                    }
                    
                    btn.disabled = true;
                    btn.innerHTML = '<div class="loading"></div> Enregistrement...';
                });
            });
        });

        // Nettoyage
        window.addEventListener('beforeunload', function() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });

        // Détection mobile
        function isMobileDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        if (isMobileDevice()) {
            console.log("📱 Mode mobile détecté - Optimisations activées");
        }

        // Rechargement après succès
        <?php if ($message_type === 'success'): ?>
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        <?php endif; ?>
    </script>
    <!-- Après les autres scripts -->
<script src="/pwa-notifications.js"></script>
</body>
</html>