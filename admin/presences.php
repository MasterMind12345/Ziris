<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les préférences utilisateur (admin)
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        // Créer des préférences par défaut pour l'admin
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

// Déterminer le thème actuel pour l'affichage
$currentTheme = $preferences['theme'] ?? 'light';

$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$presences = getPresencesByPeriod($period, $date);

// Récupérer la liste des lieux autorisés pour la vérification
$lieux_autorises = [];
try {
    $stmt = $pdo->query("SELECT * FROM lieux_autorises WHERE est_actif = 1 ORDER BY quartier, ville");
    $lieux_autorises = $stmt->fetchAll();
    
    // Créer une liste des quartiers autorisés pour vérification rapide
    $quartiers_autorises = [];
    $villes_autorisees = [];
    $noms_lieux_autorises = [];
    
    foreach ($lieux_autorises as $lieu) {
        if (!empty($lieu['quartier'])) {
            $quartiers_autorises[] = strtolower(trim($lieu['quartier']));
        }
        if (!empty($lieu['ville'])) {
            $villes_autorisees[] = strtolower(trim($lieu['ville']));
        }
        if (!empty($lieu['nom_lieu'])) {
            $noms_lieux_autorises[] = strtolower(trim($lieu['nom_lieu']));
        }
    }
} catch(PDOException $e) {
    // Table non existante, on utilise des valeurs par défaut
    $lieux_autorises = [];
    $quartiers_autorises = ['cite des palmiers', 'akwa', 'bépanda'];
    $villes_autorisees = ['douala', 'carrières-sous-poissy'];
    $noms_lieux_autorises = ['mnlv africa', 'cite des palmiers'];
}

// Fonction pour vérifier si un lieu est autorisé
// Fonction pour vérifier si un lieu est autorisé (quartier OBLIGATOIRE)
function estLieuAutorise($lieu, $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises) {
    if (empty($lieu)) return true; // Lieu vide considéré comme OK
    
    $lieu_lower = strtolower(trim($lieu));
    
    // Étape 1: Vérifier si le lieu contient un quartier autorisé
    $has_quartier = false;
    foreach ($quartiers_autorises as $quartier) {
        if (strpos($lieu_lower, $quartier) !== false) {
            $has_quartier = true;
            break;
        }
    }
    
    // Si PAS de quartier autorisé → DIRECTEMENT SUSPECT
    if (!$has_quartier) {
        return false;
    }
    
    // Étape 2: Maintenant vérifier la ville (seulement si quartier OK)
    foreach ($villes_autorisees as $ville) {
        if (strpos($lieu_lower, $ville) !== false) {
            return true; // Quartier OK + Ville OK = AUTORISÉ
        }
    }
    
    // Étape 3: Vérifier les noms complets de lieux (pour exceptions)
    foreach ($noms_lieux_autorises as $nom_lieu) {
        if (strpos($lieu_lower, $nom_lieu) !== false) {
            return true; // Nom complet de lieu = AUTORISÉ (même sans vérification ville)
        }
    }
    
    return false; // Quartier OK mais ville non autorisée = SUSPECT
}

// Traitement de l'ajout d'un lieu autorisé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_lieu'])) {
    $nom_lieu = trim($_POST['nom_lieu']);
    $quartier = trim($_POST['quartier']);
    $ville = trim($_POST['ville']);
    $pays = trim($_POST['pays']);
    $rayon_autorise_km = floatval($_POST['rayon_autorise_km']);
    
    if (!empty($nom_lieu) && !empty($ville)) {
        try {
            // Vérifier si le lieu existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lieux_autorises WHERE nom_lieu = ? AND quartier = ? AND ville = ?");
            $stmt->execute([$nom_lieu, $quartier, $ville]);
            $exists = $stmt->fetchColumn();
            
            if ($exists == 0) {
                // Ajouter le nouveau lieu
                $stmt = $pdo->prepare("INSERT INTO lieux_autorises (nom_lieu, quartier, ville, pays, rayon_autorise_km, est_actif, created_at, updated_at) 
                                      VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
                $stmt->execute([$nom_lieu, $quartier, $ville, $pays, $rayon_autorise_km]);
                
                $message_success = "Lieu ajouté avec succès !";
                
                // Mettre à jour la liste des lieux autorisés
                $stmt = $pdo->query("SELECT * FROM lieux_autorises WHERE est_actif = 1 ORDER BY quartier, ville");
                $lieux_autorises = $stmt->fetchAll();
                
                // Mettre à jour les listes de vérification
                $quartiers_autorises = [];
                $villes_autorisees = [];
                $noms_lieux_autorises = [];
                
                foreach ($lieux_autorises as $lieu) {
                    if (!empty($lieu['quartier'])) {
                        $quartiers_autorises[] = strtolower(trim($lieu['quartier']));
                    }
                    if (!empty($lieu['ville'])) {
                        $villes_autorisees[] = strtolower(trim($lieu['ville']));
                    }
                    if (!empty($lieu['nom_lieu'])) {
                        $noms_lieux_autorises[] = strtolower(trim($lieu['nom_lieu']));
                    }
                }
            } else {
                $message_error = "Ce lieu existe déjà dans la base de données.";
            }
        } catch(PDOException $e) {
            $message_error = "Erreur lors de l'ajout du lieu : " . $e->getMessage();
        }
    } else {
        $message_error = "Le nom du lieu et la ville sont obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences - Ziris Admin</title>
    
    <!-- Style CSS principal avec support du thème -->
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="/manifest.json">

    <!-- CSS supplémentaire pour le thème -->
    <style>
        /* Variables CSS pour les thèmes - alignées avec theme.php et index.php */
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
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 12px;
            --suspicion-bg: #fff5f5;
            --suspicion-border: #feb2b2;
            --suspicion-text: #c53030;
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-card: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --border-color: #404040;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            --suspicion-bg: #2a1a1a;
            --suspicion-border: #742a2a;
            --suspicion-text: #fc8181;
        }

        /* Appliquer les variables CSS au body */
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .main-content {
            background-color: var(--bg-primary);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            padding: 25px;
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 16px;
            margin: 0;
        }

        /* Filters Section */
        .filters {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Table Container */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            animation: fadeIn 0.8s ease;
            margin-bottom: 30px;
        }

        .table-header {
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .table-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h2 i {
            color: var(--primary);
        }

        .table-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .table-search {
            width: 250px;
            padding: 10px 16px;
            padding-left: 40px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: var(--transition);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 12px center;
            background-size: 16px;
        }

        .table-search:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org2000/svg' width='16' height='16' fill='%234361ee' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
            border-color: var(--primary);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }

        /* Table Styling */
        #presencesTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        #presencesTable thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            position: sticky;
            top: 0;
            z-index: 10;
        }

        #presencesTable thead th {
            padding: 16px 12px;
            text-align: left;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        #presencesTable tbody tr {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        #presencesTable tbody tr:hover {
            background: var(--bg-secondary);
        }

        #presencesTable tbody td {
            padding: 14px 12px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .badge-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Lieu Information - Style amélioré */
        .lieu-container {
            max-width: 200px;
            min-width: 150px;
        }

        .lieu-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .lieu-info {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.4;
            word-wrap: break-word;
            padding: 6px 8px;
            background: var(--bg-secondary);
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            margin-bottom: 4px;
            transition: var(--transition);
        }

        /* Style pour lieu suspect/non autorisé */
        .lieu-suspect {
            background: var(--suspicion-bg) !important;
            color: var(--suspicion-text) !important;
            border-left: 3px solid var(--danger) !important;
            border: 1px solid var(--suspicion-border) !important;
            animation: pulseWarning 2s infinite;
        }

        .lieu-suspect .lieu-title {
            color: var(--suspicion-text) !important;
        }

        .text-muted {
            color: var(--text-secondary) !important;
            font-style: italic;
            opacity: 0.7;
        }

        /* Time Display */
        .time-display {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            background: var(--bg-secondary);
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }

        /* Stats Summary */
        .stats-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            flex: 1;
            min-width: 200px;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-icon.ontime {
            background: linear-gradient(135deg, var(--success), #0da271);
        }

        .stat-icon.late {
            background: linear-gradient(135deg, var(--warning), #d97706);
        }

        .stat-icon.pause {
            background: linear-gradient(135deg, var(--info), #2563eb);
        }

        .stat-icon.suspect {
            background: linear-gradient(135deg, var(--danger), #dc2626);
        }

        .stat-content h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }

        .stat-content p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Period Selector */
        .period-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .period-btn {
            padding: 10px 20px;
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .period-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .period-btn:hover:not(.active) {
            background: var(--bg-primary);
            border-color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 16px;
            margin: 0 0 20px 0;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Date Picker Enhancement */
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(var(--calendar-icon-invert, 0));
            cursor: pointer;
            opacity: 0.7;
        }

        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        input[type="date"]:focus::-webkit-calendar-picker-indicator {
            opacity: 1;
        }

        /* Table Footer */
        .table-footer {
            padding: 15px 25px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Animations */
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulseWarning {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease forwards;
            opacity: 0;
        }

        /* Filter Suspect Button */
        .filter-suspect-btn {
            padding: 8px 16px;
            border-radius: 6px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-suspect-btn.active {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            border-color: transparent;
        }

        .filter-suspect-btn:hover:not(.active) {
            background: var(--bg-primary);
            border-color: var(--danger);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 500px;
            animation: fadeIn 0.3s ease;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: var(--primary);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--text-primary);
            background: var(--bg-secondary);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: var(--bg-secondary);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .table-container {
                overflow-x: auto;
            }
            
            #presencesTable {
                min-width: 1200px;
            }
        }

        @media (max-width: 768px) {
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .table-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .table-search {
                width: 100%;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-header h1 {
                font-size: 24px;
            }
            
            .stats-summary {
                flex-direction: column;
            }
            
            .stat-item {
                min-width: 100%;
            }
            
            .period-selector {
                flex-wrap: wrap;
            }
            
            .table-footer {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 10px;
            }
        }

        @media (max-width: 480px) {
            .period-selector {
                flex-direction: column;
            }
            
            .period-btn {
                width: 100%;
                justify-content: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .filters {
                padding: 20px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
            }
        }

        /* Scrollbar Styling */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Hover effects for table rows */
        #presencesTable tbody tr {
            position: relative;
        }

        #presencesTable tbody tr::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 1px;
            background: var(--border-color);
            transition: var(--transition);
        }

        #presencesTable tbody tr:hover::after {
            background: var(--primary);
            opacity: 0.3;
        }

        /* Employee name styling */
        .employee-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .poste-badge {
            display: inline-block;
            padding: 3px 8px;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid var(--border-color);
        }

        /* Legend for suspect locations */
        .legend {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            padding: 10px 15px;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .legend-color.suspect {
            background: var(--suspicion-bg);
            border-color: var(--danger);
        }

        .legend-color.normal {
            background: var(--bg-secondary);
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-clipboard-check"></i> Gestion des Présences</h1>
            <p>Consultez et gérez les présences des employés | Vérification automatique des lieux</p>
        </div>
        
        <!-- Period Selector -->
        <div class="period-selector">
            <a href="?period=daily&date=<?php echo date('Y-m-d'); ?>" class="period-btn <?php echo $period == 'daily' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-day"></i> Journalier
            </a>
            <a href="?period=weekly&date=<?php echo date('Y-m-d'); ?>" class="period-btn <?php echo $period == 'weekly' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i> Hebdomadaire
            </a>
            <a href="?period=monthly&date=<?php echo date('Y-m-d'); ?>" class="period-btn <?php echo $period == 'monthly' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Mensuel
            </a>
        </div>
        
        <!-- Messages -->
        <?php if (isset($message_success)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message_success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($message_error)): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $message_error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Summary -->
        <?php if (!empty($presences)): ?>
        <?php
        // Calculer les statistiques
        $totalPresences = count($presences);
        $onTime = 0;
        $late = 0;
        $withPause = 0;
        $suspectLieux = 0;
        
        foreach ($presences as $presence) {
            if ($presence['retard_minutes'] <= 0) {
                $onTime++;
            } else {
                $late++;
            }
            if (!empty($presence['heure_pause_debut']) && !empty($presence['heure_pause_fin'])) {
                $withPause++;
            }
            
            // Vérifier les lieux suspects
            if (!empty($presence['lieu']) && !estLieuAutorise($presence['lieu'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises)) {
                $suspectLieux++;
            }
            if (!empty($presence['lieu_pause_debut']) && !estLieuAutorise($presence['lieu_pause_debut'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises)) {
                $suspectLieux++;
            }
            if (!empty($presence['lieu_pause_fin']) && !estLieuAutorise($presence['lieu_pause_fin'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises)) {
                $suspectLieux++;
            }
            if (!empty($presence['lieu_fin']) && !estLieuAutorise($presence['lieu_fin'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises)) {
                $suspectLieux++;
            }
        }
        ?>
        <div class="stats-summary">
            <div class="stat-item fade-in" style="animation-delay: 0.1s;">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $totalPresences; ?></h3>
                    <p>Présences totales</p>
                </div>
            </div>
            
            <div class="stat-item fade-in" style="animation-delay: 0.2s;">
                <div class="stat-icon ontime">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $onTime; ?></h3>
                    <p>À l'heure</p>
                </div>
            </div>
            
            <div class="stat-item fade-in" style="animation-delay: 0.3s;">
                <div class="stat-icon late">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $late; ?></h3>
                    <p>En retard</p>
                </div>
            </div>
            
            <div class="stat-item fade-in" style="animation-delay: 0.4s;">
                <div class="stat-icon pause">
                    <i class="fas fa-coffee"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $withPause; ?></h3>
                    <p>Avec pause</p>
                </div>
            </div>
            
            <div class="stat-item fade-in" style="animation-delay: 0.5s;">
                <div class="stat-icon suspect">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $suspectLieux; ?></h3>
                    <p>Lieux suspects</p>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="legend fade-in" style="animation-delay: 0.6s;">
            <div class="legend-item">
                <div class="legend-color normal"></div>
                <span>Lieu autorisé</span>
            </div>
            <div class="legend-item">
                <div class="legend-color suspect"></div>
                <span>Lieu suspect / Non autorisé</span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filters Section -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="period"><i class="fas fa-filter"></i> Type de période</label>
                    <select name="period" id="period" class="form-control" onchange="this.form.submit()">
                        <option value="daily" <?php echo $period == 'daily' ? 'selected' : ''; ?>>Journalier</option>
                        <option value="weekly" <?php echo $period == 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                        <option value="monthly" <?php echo $period == 'monthly' ? 'selected' : ''; ?>>Mensuel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date"><i class="fas fa-calendar-alt"></i> Date de référence</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?php echo $date; ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Table Container -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Liste des Présences</h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher..." id="tableSearch">
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" onclick="clearSearch()">
                            <i class="fas fa-times"></i> Effacer
                        </button>
                        <button class="filter-suspect-btn" onclick="filterSuspectLieux()" id="filterSuspectBtn">
                            <i class="fas fa-exclamation-triangle"></i> Lieux suspects
                        </button>
                        <button class="btn btn-primary" onclick="openAddLieuModal()">
                            <i class="fas fa-plus-circle"></i> Ajouter lieu
                        </button>
                        <button class="btn btn-primary" onclick="exportToCSV()">
                            <i class="fas fa-file-export"></i> Exporter CSV
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (empty($presences)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Aucune présence trouvée</h3>
                    <p>Aucune présence n'a été enregistrée pour la période sélectionnée.</p>
                    <a href="?period=daily&date=<?php echo date('Y-m-d'); ?>" class="btn btn-primary">
                        <i class="fas fa-calendar-day"></i> Voir les présences du jour
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table id="presencesTable">
                        <thead>
                            <tr>
                                <th style=" background: black; "><i class="fas fa-user"></i> Employé</th>
                                <th style=" background: black; "><i class="fas fa-briefcase"></i> Poste</th>
                                <th style=" background: black; "><i class="fas fa-calendar"></i> Date</th>
                                <th style=" background: black; "><i class="fas fa-sign-in-alt"></i> Heure d'arrivée</th>
                                <th style=" background: black; "><i class="fas fa-sign-out-alt"></i> Heure de départ</th>
                                <th style=" background: black; "><i class="fas fa-coffee"></i> Pause Début</th>
                                <th style=" background: black; "><i class="fas fa-coffee"></i> Pause Fin</th>
                                <th style=" background: black; "><i class="fas fa-clock"></i> Retard</th>
                                <th style=" background: black; "><i class="fas fa-map-marker-alt"></i> Lieu Arrivée</th>
                                <th style=" background: black; "><i class="fas fa-map-marker-alt"></i> Lieu Pause Début</th>
                                <th style=" background: black; "><i class="fas fa-map-marker-alt"></i> Lieu Pause Fin</th>
                                <th style=" background: black; "><i class="fas fa-map-marker-alt"></i> Lieu Départ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($presences as $index => $presence): 
                                // Vérifier chaque lieu pour déterminer s'il est suspect
                                $lieu_arrivee_suspect = !empty($presence['lieu']) && !estLieuAutorise($presence['lieu'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises);
                                $lieu_pause_debut_suspect = !empty($presence['lieu_pause_debut']) && !estLieuAutorise($presence['lieu_pause_debut'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises);
                                $lieu_pause_fin_suspect = !empty($presence['lieu_pause_fin']) && !estLieuAutorise($presence['lieu_pause_fin'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises);
                                $lieu_fin_suspect = !empty($presence['lieu_fin']) && !estLieuAutorise($presence['lieu_fin'], $quartiers_autorises, $villes_autorisees, $noms_lieux_autorises);
                                
                                // Déterminer si la ligne entière a des lieux suspects
                                $has_suspect_lieux = $lieu_arrivee_suspect || $lieu_pause_debut_suspect || $lieu_pause_fin_suspect || $lieu_fin_suspect;
                            ?>
                            <tr class="fade-in <?php echo $has_suspect_lieux ? 'has-suspect-lieu' : ''; ?>" 
                                style="animation-delay: <?php echo $index * 0.05; ?>s; <?php echo $has_suspect_lieux ? 'border-left: 3px solid var(--danger);' : ''; ?>">
                                
                                <!-- Employé -->
                                <td>
                                    <div class="employee-name"><?php echo htmlspecialchars($presence['nom']); ?></div>
                                    <?php if ($has_suspect_lieux): ?>
                                        <span class="badge badge-danger" style="margin-top: 4px;">
                                            <i class="fas fa-exclamation-triangle"></i> Lieu suspect
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Poste -->
                                <td>
                                    <span class="poste-badge"><?php echo htmlspecialchars($presence['poste'] ?? 'Non défini'); ?></span>
                                </td>
                                
                                <!-- Date -->
                                <td>
                                    <div class="time-display"><?php echo date('d/m/Y', strtotime($presence['date_presence'])); ?></div>
                                </td>
                                
                                <!-- Heure d'arrivée -->
                                <td>
                                    <div class="time-display" style="background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(67, 97, 238, 0.2)); color: var(--primary);">
                                        <?php echo $presence['heure_debut_reel']; ?>
                                    </div>
                                </td>
                                
                                <!-- Heure de départ -->
                                <td>
                                    <?php if ($presence['heure_fin_reel']): ?>
                                        <div class="time-display" style="background: linear-gradient(135deg, rgba(114, 9, 183, 0.1), rgba(114, 9, 183, 0.2)); color: var(--secondary);">
                                            <?php echo $presence['heure_fin_reel']; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));">
                                            <i class="fas fa-clock"></i> Non pointé
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Pause Début -->
                                <td>
                                    <?php if ($presence['heure_pause_debut']): ?>
                                        <div class="time-display" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2)); color: var(--success);">
                                            <?php echo $presence['heure_pause_debut']; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Pause Fin -->
                                <td>
                                    <?php if ($presence['heure_pause_fin']): ?>
                                        <div class="time-display" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2)); color: var(--success);">
                                            <?php echo $presence['heure_pause_fin']; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Retard -->
                                <td>
                                    <?php if ($presence['retard_minutes'] > 0): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-clock"></i> <?php echo $presence['retard_minutes']; ?> min
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> À l'heure
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Lieu Arrivée -->
                                <td class="lieu-container">
                                    <?php if (!empty($presence['lieu'])): ?>
                                        <div class="lieu-title">Arrivée:</div>
                                        <div class="lieu-info <?php echo $lieu_arrivee_suspect ? 'lieu-suspect' : ''; ?>">
                                            <?php echo htmlspecialchars($presence['lieu']); ?>
                                            <?php if ($lieu_arrivee_suspect): ?>
                                                <br><small style="color: inherit; opacity: 0.8;">
                                                    <i class="fas fa-exclamation-circle"></i> Non autorisé
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($lieu_arrivee_suspect): ?>
                                            <button class="btn btn-secondary btn-sm" style="margin-top: 5px; font-size: 11px; padding: 3px 8px;" onclick="addLieuFromText('<?php echo addslashes($presence['lieu']); ?>')">
                                                <i class="fas fa-plus"></i> Ajouter ce lieu
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non spécifié</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Lieu Pause Début -->
                                <td class="lieu-container">
                                    <?php if (!empty($presence['lieu_pause_debut'])): ?>
                                        <div class="lieu-title">Pause Début:</div>
                                        <div class="lieu-info <?php echo $lieu_pause_debut_suspect ? 'lieu-suspect' : ''; ?>">
                                            <?php echo htmlspecialchars($presence['lieu_pause_debut']); ?>
                                            <?php if ($lieu_pause_debut_suspect): ?>
                                                <br><small style="color: inherit; opacity: 0.8;">
                                                    <i class="fas fa-exclamation-circle"></i> Non autorisé
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($lieu_pause_debut_suspect): ?>
                                            <button class="btn btn-secondary btn-sm" style="margin-top: 5px; font-size: 11px; padding: 3px 8px;" onclick="addLieuFromText('<?php echo addslashes($presence['lieu_pause_debut']); ?>')">
                                                <i class="fas fa-plus"></i> Ajouter ce lieu
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Lieu Pause Fin -->
                                <td class="lieu-container">
                                    <?php if (!empty($presence['lieu_pause_fin'])): ?>
                                        <div class="lieu-title">Pause Fin:</div>
                                        <div class="lieu-info <?php echo $lieu_pause_fin_suspect ? 'lieu-suspect' : ''; ?>">
                                            <?php echo htmlspecialchars($presence['lieu_pause_fin']); ?>
                                            <?php if ($lieu_pause_fin_suspect): ?>
                                                <br><small style="color: inherit; opacity: 0.8;">
                                                    <i class="fas fa-exclamation-circle"></i> Non autorisé
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($lieu_pause_fin_suspect): ?>
                                            <button class="btn btn-secondary btn-sm" style="margin-top: 5px; font-size: 11px; padding: 3px 8px;" onclick="addLieuFromText('<?php echo addslashes($presence['lieu_pause_fin']); ?>')">
                                                <i class="fas fa-plus"></i> Ajouter ce lieu
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Lieu Départ -->
                                <td class="lieu-container">
                                    <?php if (!empty($presence['lieu_fin'])): ?>
                                        <div class="lieu-title">Départ:</div>
                                        <div class="lieu-info <?php echo $lieu_fin_suspect ? 'lieu-suspect' : ''; ?>">
                                            <?php echo htmlspecialchars($presence['lieu_fin']); ?>
                                            <?php if ($lieu_fin_suspect): ?>
                                                <br><small style="color: inherit; opacity: 0.8;">
                                                    <i class="fas fa-exclamation-circle"></i> Non autorisé
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($lieu_fin_suspect): ?>
                                            <button class="btn btn-secondary btn-sm" style="margin-top: 5px; font-size: 11px; padding: 3px 8px;" onclick="addLieuFromText('<?php echo addslashes($presence['lieu_fin']); ?>')">
                                                <i class="fas fa-plus"></i> Ajouter ce lieu
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Table Footer -->
                <div class="table-footer">
                    <div>
                        <i class="fas fa-info-circle"></i> 
                        <?php echo count($presences); ?> présence(s) trouvée(s) pour la période <?php echo $period; ?>
                        <?php if ($suspectLieux > 0): ?>
                            | <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                            <span style="color: var(--danger);"><?php echo $suspectLieux; ?> lieu(x) suspect(s)</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                        <button class="btn btn-primary" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i> Actualiser
                        </button>
                        <?php if ($suspectLieux > 0): ?>
                            <button class="btn btn-danger" onclick="alertSuspectLieux()">
                                <i class="fas fa-exclamation-triangle"></i> Signaler anomalies
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Modal pour ajouter un lieu -->
    <div id="addLieuModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-map-marker-alt"></i> Ajouter un lieu autorisé</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="addLieuForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nom_lieu"><i class="fas fa-signature"></i> Nom du lieu *</label>
                        <input type="text" id="nom_lieu" name="nom_lieu" class="form-control" required placeholder="Ex: MNLV Africa Sarl">
                    </div>
                    
                    <div class="form-group">
                        <label for="quartier"><i class="fas fa-map"></i> Quartier</label>
                        <input type="text" id="quartier" name="quartier" class="form-control" placeholder="Ex: Cité des Palmiers">
                    </div>
                    
                    <div class="form-group">
                        <label for="ville"><i class="fas fa-city"></i> Ville *</label>
                        <input type="text" id="ville" name="ville" class="form-control" required placeholder="Ex: Douala" value="Douala">
                    </div>
                    
                    <div class="form-group">
                        <label for="pays"><i class="fas fa-globe"></i> Pays</label>
                        <input type="text" id="pays" name="pays" class="form-control" placeholder="Ex: Cameroun" value="Cameroun">
                    </div>
                    
                    <div class="form-group">
                        <label for="rayon_autorise_km"><i class="fas fa-ruler"></i> Rayon autorisé (km)</label>
                        <input type="number" id="rayon_autorise_km" name="rayon_autorise_km" class="form-control" step="0.01" min="0.01" value="1.00" placeholder="Ex: 1.00">
                        <small style="color: var(--text-secondary);">Rayon en kilomètres autour duquel le pointage est accepté</small>
                    </div>
                    
                    <input type="hidden" name="ajouter_lieu" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Ajouter le lieu
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Appliquer le thème au chargement
            applyThemeStyles();
            
            // Recherche dans le tableau
            const tableSearch = document.getElementById('tableSearch');
            const tableRows = document.querySelectorAll('#presencesTable tbody tr');
            
            tableSearch.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                let visibleCount = 0;
                
                tableRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Mettre à jour le compteur
                updateVisibleCount(visibleCount);
            });
            
            // Animation des lignes du tableau
            tableRows.forEach((row, index) => {
                row.style.animationDelay = (index * 0.05) + 's';
            });
            
            // Animation des cartes de stats
            const statItems = document.querySelectorAll('.stat-item');
            statItems.forEach((item, index) => {
                item.style.animationDelay = (index * 0.1) + 's';
            });
            
            // Date picker default to today
            const datePicker = document.getElementById('date');
            if (!datePicker.value) {
                datePicker.value = new Date().toISOString().split('T')[0];
            }
            
            // Mettre à jour le compteur initial
            updateVisibleCount(tableRows.length);
            
            // Initialiser le bouton de filtre des lieux suspects
            initializeSuspectFilter();
            
            // Fermer la modal en cliquant à l'extérieur
            const modal = document.getElementById('addLieuModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            }
            
            // Empêcher la propagation du clic dans le contenu de la modal
            const modalContent = document.querySelector('.modal-content');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
        
        // Fonction pour appliquer les styles du thème
        function applyThemeStyles() {
            const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
            
            // Mettre à jour les couleurs des icônes si nécessaire
            if (isDarkTheme) {
                document.documentElement.style.setProperty('--calendar-icon-invert', '1');
            }
        }
        
        // Fonction d'export CSV
        function exportToCSV() {
            const table = document.getElementById('presencesTable');
            const rows = table.querySelectorAll('tr');
            const csv = [];
            
            rows.forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('th, td');
                
                cells.forEach(cell => {
                    // Exclure les éléments avec des classes spécifiques si nécessaire
                    if (!cell.classList.contains('text-muted')) {
                        // Pour les cellules avec lieu-info, on prend le texte complet
                        const lieuInfo = cell.querySelector('.lieu-info');
                        if (lieuInfo) {
                            let lieuText = lieuInfo.textContent;
                            // Supprimer "Non autorisé" du texte
                            lieuText = lieuText.replace('Non autorisé', '').trim();
                            rowData.push('"' + lieuText.replace(/"/g, '""') + '"');
                        } else {
                            // Nettoyer le texte des icônes
                            let cellText = cell.textContent;
                            // Supprimer les icônes Font Awesome si présentes
                            cellText = cellText.replace(/[\n\r]+/g, ' ')
                                              .replace(/\s+/g, ' ')
                                              .trim();
                            rowData.push('"' + cellText.replace(/"/g, '""') + '"');
                        }
                    }
                });
                
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const filename = `presences-<?php echo $period; ?>-<?php echo date('Y-m-d', strtotime($date)); ?>.csv`;
            
            if (navigator.msSaveBlob) {
                navigator.msSaveBlob(blob, filename);
            } else {
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            
            // Notification
            showNotification('Export réussi', 'Le fichier CSV a été téléchargé avec succès.', 'success');
        }
        
        // Fonction pour effacer la recherche
        function clearSearch() {
            const tableSearch = document.getElementById('tableSearch');
            tableSearch.value = '';
            const tableRows = document.querySelectorAll('#presencesTable tbody tr');
            tableRows.forEach(row => {
                row.style.display = '';
            });
            updateVisibleCount(tableRows.length);
            showNotification('Recherche effacée', 'Tous les filtres de recherche ont été réinitialisés.', 'info');
        }
        
        // Fonction pour filtrer les lieux suspects
        let filterSuspectActive = false;
        
        function initializeSuspectFilter() {
            const btn = document.getElementById('filterSuspectBtn');
            if (!btn) return;
            
            // Vérifier s'il y a des lieux suspects
            const suspectRows = document.querySelectorAll('#presencesTable tbody tr.has-suspect-lieu');
            if (suspectRows.length === 0) {
                btn.style.display = 'none';
            }
        }
        
        function filterSuspectLieux() {
            const btn = document.getElementById('filterSuspectBtn');
            const tableRows = document.querySelectorAll('#presencesTable tbody tr');
            
            filterSuspectActive = !filterSuspectActive;
            
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                if (filterSuspectActive) {
                    // Montrer uniquement les lignes avec des lieux suspects
                    if (row.classList.contains('has-suspect-lieu')) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                } else {
                    // Montrer toutes les lignes
                    row.style.display = '';
                    visibleCount++;
                }
            });
            
            // Mettre à jour le bouton
            if (filterSuspectActive) {
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-times"></i> Tout afficher';
                showNotification('Filtre activé', 'Affichage des présences avec lieux suspects uniquement.', 'info');
            } else {
                btn.classList.remove('active');
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Lieux suspects';
                showNotification('Filtre désactivé', 'Affichage de toutes les présences.', 'info');
            }
            
            updateVisibleCount(visibleCount);
        }
        
        // Fonction pour alerter sur les lieux suspects
        function alertSuspectLieux() {
            const suspectRows = document.querySelectorAll('#presencesTable tbody tr.has-suspect-lieu');
            if (suspectRows.length === 0) {
                showNotification('Aucun lieu suspect', 'Tous les lieux de pointage sont autorisés.', 'info');
                return;
            }
            
            const confirmMessage = `Il y a ${suspectRows.length} présence(s) avec des lieux suspects/non autorisés.\n\nVoulez-vous générer un rapport détaillé ?`;
            
            if (confirm(confirmMessage)) {
                // Générer un rapport des lieux suspects
                let report = "=== RAPPORT DES LIEUX SUSPECTS ===\n\n";
                report += `Période: <?php echo $period; ?> - Date: <?php echo $date; ?>\n`;
                report += `Généré le: ${new Date().toLocaleString()}\n\n`;
                report += "Liste des présences avec lieux suspects:\n\n";
                
                suspectRows.forEach((row, index) => {
                    const employeeName = row.querySelector('.employee-name').textContent;
                    const datePresence = row.querySelector('.time-display').textContent;
                    
                    report += `${index + 1}. ${employeeName} - ${datePresence}\n`;
                    
                    // Récupérer les lieux suspects
                    const suspectLieux = row.querySelectorAll('.lieu-suspect');
                    suspectLieux.forEach(lieu => {
                        const lieuTitle = lieu.previousElementSibling.textContent;
                        const lieuText = lieu.textContent.replace('Non autorisé', '').trim();
                        report += `   - ${lieuTitle} ${lieuText}\n`;
                    });
                    
                    report += '\n';
                });
                
                // Créer un blob pour télécharger le rapport
                const blob = new Blob([report], { type: 'text/plain;charset=utf-8' });
                const link = document.createElement('a');
                const filename = `rapport-lieux-suspects-<?php echo date('Y-m-d', strtotime($date)); ?>.txt`;
                
                if (navigator.msSaveBlob) {
                    navigator.msSaveBlob(blob, filename);
                } else {
                    link.href = URL.createObjectURL(blob);
                    link.download = filename;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
                
                showNotification('Rapport généré', 'Le rapport des lieux suspects a été téléchargé.', 'success');
            }
        }
        
        // Fonction pour actualiser la page
        function refreshPage() {
            window.location.reload();
        }
        
        // Fonction pour mettre à jour le compteur de lignes visibles
        function updateVisibleCount(count) {
            const footer = document.querySelector('.table-footer');
            if (footer) {
                const countElement = footer.querySelector('div:first-child');
                if (countElement) {
                    const period = '<?php echo $period; ?>';
                    const suspectText = filterSuspectActive ? ' (lieux suspects uniquement)' : '';
                    countElement.innerHTML = `<i class="fas fa-info-circle"></i> ${count} présence(s) trouvée(s) pour la période ${period}${suspectText}`;
                }
            }
        }
        
        // Fonctions pour gérer la modal d'ajout de lieu
        function openAddLieuModal(lieuText = '') {
            const modal = document.getElementById('addLieuModal');
            if (modal) {
                modal.style.display = 'flex';
                
                // Si un texte de lieu est fourni, essayer de l'analyser
                if (lieuText) {
                    // Extraire les informations du texte du lieu
                    // Format typique: "3Q38+W3G, Douala, Cameroon | Quartier: Cite des Palmiers | Ville: Douala"
                    const nomInput = document.getElementById('nom_lieu');
                    const quartierInput = document.getElementById('quartier');
                    const villeInput = document.getElementById('ville');
                    
                    // Essayer d'extraire le quartier
                    const quartierMatch = lieuText.match(/Quartier:\s*([^|]+)/i);
                    if (quartierMatch) {
                        quartierInput.value = quartierMatch[1].trim();
                    }
                    
                    // Essayer d'extraire la ville
                    const villeMatch = lieuText.match(/Ville:\s*([^|]+)/i);
                    if (villeMatch) {
                        villeInput.value = villeMatch[1].trim();
                    } else {
                        // Essayer de trouver la ville dans le texte
                        const villes = ['Douala', 'Yaoundé', 'Carrières-sous-Poissy', 'Paris'];
                        for (const ville of villes) {
                            if (lieuText.includes(ville)) {
                                villeInput.value = ville;
                                break;
                            }
                        }
                    }
                    
                    // Utiliser le début du texte comme nom du lieu
                    const firstPart = lieuText.split('|')[0];
                    nomInput.value = firstPart.trim();
                }
            }
        }
        
        function closeModal() {
            const modal = document.getElementById('addLieuModal');
            if (modal) {
                modal.style.display = 'none';
                // Réinitialiser le formulaire
                document.getElementById('addLieuForm').reset();
            }
        }
        
        // Fonction pour ajouter un lieu depuis le texte d'un lieu suspect
        function addLieuFromText(lieuText) {
            openAddLieuModal(lieuText);
        }
        
        // Fonction pour afficher des notifications
        function showNotification(title, message, type = 'info') {
            // Créer la notification
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <div>
                        <strong>${title}</strong>
                        <p>${message}</p>
                    </div>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Styles pour la notification
            const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
            const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
            const bgCard = getComputedStyle(document.documentElement).getPropertyValue('--bg-card').trim();
            const textPrimary = getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim();
            const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim();
            
            let typeColor;
            switch(type) {
                case 'success': typeColor = '#10b981'; break;
                case 'error': typeColor = '#ef4444'; break;
                case 'warning': typeColor = '#f59e0b'; break;
                default: typeColor = primaryColor;
            }
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${bgCard};
                color: ${textPrimary};
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid ${typeColor};
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                z-index: 1000;
                max-width: 400px;
                animation: slideInRight 0.3s ease;
                border: 1px solid ${borderColor};
            `;
            
            // Ajouter les styles CSS pour l'animation
            if (!document.querySelector('#notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    
                    .notification-content {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                    }
                    
                    .notification-content i {
                        font-size: 20px;
                    }
                    
                    .notification-close {
                        background: none;
                        border: none;
                        color: inherit;
                        cursor: pointer;
                        opacity: 0.7;
                        transition: opacity 0.2s;
                        padding: 0;
                        width: 24px;
                        height: 24px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 4px;
                    }
                    
                    .notification-close:hover {
                        opacity: 1;
                        background: rgba(0,0,0,0.1);
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Supprimer automatiquement après 5 secondes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Gestion du thème auto
        function handleThemeChange() {
            const theme = document.documentElement.getAttribute('data-theme');
            if (theme === 'auto') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
            }
        }
        
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleThemeChange);
        
        handleThemeChange();
    </script>

</body>
</html>