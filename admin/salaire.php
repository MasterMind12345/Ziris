<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les préférences utilisateur
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, font_size, notifications) VALUES (?, 'light', 'medium', 1)");
        $stmt->execute([$_SESSION['user_id']]);
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $preferences = $stmt->fetch();
    }
} catch(PDOException $e) {
    $preferences = ['theme' => 'light', 'font_size' => 'medium', 'notifications' => 1];
}

$currentTheme = $preferences['theme'] ?? 'light';

// Message de notification
$message = '';
$message_type = '';

// Vérifier si la table parametres_salaire existe, sinon la créer
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'parametres_salaire'")->rowCount() > 0;
    
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE parametres_salaire (
                id INT PRIMARY KEY AUTO_INCREMENT,
                poste_id INT NOT NULL,
                salaire_brut_mensuel DECIMAL(10,2) NOT NULL,
                jours_travail_mois INT NOT NULL DEFAULT 22,
                heures_travail_jour DECIMAL(4,2) NOT NULL DEFAULT 8.0,
                salaire_horaire DECIMAL(10,2) GENERATED ALWAYS AS (salaire_brut_mensuel / (jours_travail_mois * heures_travail_jour)) STORED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (poste_id) REFERENCES postes(id) ON DELETE CASCADE,
                UNIQUE KEY unique_poste (poste_id)
            )
        ");
    }
} catch(PDOException $e) {
    error_log("Erreur création table salaire: " . $e->getMessage());
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'configurer_poste') {
            $poste_id = $_POST['poste_id'] ?? '';
            $salaire_brut_mensuel = floatval($_POST['salaire_brut_mensuel'] ?? 0);
            $jours_travail_mois = intval($_POST['jours_travail_mois'] ?? 22);
            $heures_travail_jour = floatval($_POST['heures_travail_jour'] ?? 8.0);
            
            if (!empty($poste_id) && $salaire_brut_mensuel > 0 && $jours_travail_mois > 0 && $heures_travail_jour > 0) {
                // Vérifier si une configuration existe déjà
                $stmt = $pdo->prepare("SELECT id FROM parametres_salaire WHERE poste_id = ?");
                $stmt->execute([$poste_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Mettre à jour
                    $stmt = $pdo->prepare("
                        UPDATE parametres_salaire 
                        SET salaire_brut_mensuel = ?, jours_travail_mois = ?, heures_travail_jour = ?, updated_at = NOW()
                        WHERE poste_id = ?
                    ");
                    $stmt->execute([$salaire_brut_mensuel, $jours_travail_mois, $heures_travail_jour, $poste_id]);
                    $message = "Configuration de salaire mise à jour avec succès!";
                } else {
                    // Insérer
                    $stmt = $pdo->prepare("
                        INSERT INTO parametres_salaire (poste_id, salaire_brut_mensuel, jours_travail_mois, heures_travail_jour)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$poste_id, $salaire_brut_mensuel, $jours_travail_mois, $heures_travail_jour]);
                    $message = "Configuration de salaire ajoutée avec succès!";
                }
                $message_type = 'success';
            } else {
                $message = "Veuillez remplir tous les champs obligatoires avec des valeurs valides";
                $message_type = 'error';
            }
        }
    } catch(PDOException $e) {
        $message = "Erreur: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Récupérer les données pour le tableau
$salaireData = [];
$currentMonth = date('Y-m');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

try {
    // Récupérer tous les utilisateurs (sauf admin) avec leurs postes et configurations de salaire
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.nom as user_nom,
            u.email,
            p.id as poste_id,
            p.nom as poste_nom,
            ps.salaire_brut_mensuel,
            ps.jours_travail_mois,
            ps.heures_travail_jour,
            ps.salaire_horaire
        FROM users u
        LEFT JOIN postes p ON u.poste_id = p.id
        LEFT JOIN parametres_salaire ps ON p.id = ps.poste_id
        WHERE u.is_admin = 0
        ORDER BY u.nom
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // Pour chaque utilisateur, calculer les heures travaillées
    foreach ($users as $user) {
        // Calculer les heures travaillées pour le mois en cours
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as jours_presents,
                SUM(
                    TIME_TO_SEC(
                        TIMEDIFF(
                            COALESCE(heure_fin_reel, '17:30:00'), 
                            heure_debut_reel
                        )
                    ) / 3600 -
                    COALESCE(
                        TIME_TO_SEC(
                            TIMEDIFF(
                                COALESCE(fin_pause_reel, debut_pause_reel),
                                COALESCE(debut_pause_reel, heure_debut_reel)
                            )
                        ) / 3600,
                        0
                    )
                ) as heures_travail_mois,
                SUM(retard_minutes) as retard_total_minutes
            FROM presences
            WHERE user_id = ?
                AND date_presence BETWEEN ? AND ?
                AND heure_debut_reel IS NOT NULL
        ");
        $stmt->execute([$user['user_id'], $monthStart, $monthEnd]);
        $presenceData = $stmt->fetch();
        
        // Ajouter les données de présence à l'utilisateur
        $user['jours_presents'] = $presenceData['jours_presents'] ?? 0;
        $user['heures_travail_mois'] = round($presenceData['heures_travail_mois'] ?? 0, 2);
        $user['retard_total_minutes'] = $presenceData['retard_total_minutes'] ?? 0;
        
        // Calculer le salaire brut
        if ($user['salaire_horaire'] && $user['heures_travail_mois'] > 0) {
            $user['salaire_brut'] = $user['heures_travail_mois'] * $user['salaire_horaire'];
        } else {
            $user['salaire_brut'] = 0;
        }
        
        $salaireData[] = $user;
    }
    
    // Récupérer tous les postes pour le formulaire
    $stmt = $pdo->query("SELECT * FROM postes ORDER BY nom");
    $allPostes = $stmt->fetchAll();
    
    // Récupérer les configurations de salaire existantes
    $stmt = $pdo->query("
        SELECT ps.*, p.nom as poste_nom 
        FROM parametres_salaire ps
        JOIN postes p ON ps.poste_id = p.id
        ORDER BY p.nom
    ");
    $salaireConfigs = $stmt->fetchAll();
    
    // Statistiques globales
    $stats = [
        'total_employes' => 0,
        'total_salaire_brut' => 0,
        'moyenne_salaire_horaire' => 0,
        'postes_configures' => 0,
        'total_heures_travail' => 0
    ];
    
    foreach ($salaireData as $data) {
        $stats['total_employes']++;
        $stats['total_heures_travail'] += $data['heures_travail_mois'];
        if ($data['salaire_horaire']) {
            $stats['total_salaire_brut'] += $data['salaire_brut'];
            $stats['moyenne_salaire_horaire'] += $data['salaire_horaire'];
        }
    }
    
    foreach ($salaireConfigs as $config) {
        $stats['postes_configures']++;
    }
    
    if ($stats['total_employes'] > 0) {
        $stats['moyenne_salaire_horaire'] = $stats['moyenne_salaire_horaire'] / $stats['total_employes'];
    }
    
} catch(PDOException $e) {
    error_log("Erreur récupération données salaire: " . $e->getMessage());
    $salaireData = [];
    $allPostes = [];
    $salaireConfigs = [];
    $stats = [
        'total_employes' => 0,
        'total_salaire_brut' => 0,
        'moyenne_salaire_horaire' => 0,
        'postes_configures' => 0,
        'total_heures_travail' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Salaires - Ziris Admin</title>
    
    <!-- Style CSS principal -->
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="/manifest.json">

    <!-- CSS personnalisé -->
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
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 12px;
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-card: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --border-color: #404040;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .main-content {
            background-color: var(--bg-primary);
            padding: 20px;
            min-height: calc(100vh - 70px);
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
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 16px;
            margin: 0;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .card-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h2 {
            font-size: 22px;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: var(--primary);
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
            font-size: 14px;
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

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
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

        .btn-success {
            background: linear-gradient(135deg, var(--success), #0da271);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stat-card .stat-icon {
            color: var(--primary);
            font-size: 24px;
            margin-bottom: 12px;
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
            padding: 20px;
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

        /* Table Styling */
        #salaireTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        #salaireTable thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            position: sticky;
            top: 0;
            z-index: 10;
        }

        #salaireTable thead th {
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

        #salaireTable tbody tr {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        #salaireTable tbody tr:hover {
            background: var(--bg-secondary);
        }

        #salaireTable tbody td {
            padding: 14px 12px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
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

        .badge-primary {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(67, 97, 238, 0.2));
            color: var(--primary);
            border: 1px solid rgba(67, 97, 238, 0.3);
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

        /* Status indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            background: var(--bg-secondary);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-dot.active {
            background-color: var(--success);
        }

        .status-dot.inactive {
            background-color: var(--danger);
        }

        .status-dot.pending {
            background-color: var(--warning);
        }

        /* Currency formatting */
        .currency {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .currency-positive {
            color: var(--success);
        }

        .currency-negative {
            color: var(--danger);
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

        /* Table Footer */
        .table-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: var(--transition);
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .close:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            text-align: right;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            border: 1px solid;
            background: var(--bg-card);
        }

        .alert-success {
            border-color: rgba(16, 185, 129, 0.3);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(16, 185, 129, 0.1));
            color: var(--success);
        }

        .alert-error {
            border-color: rgba(239, 68, 68, 0.3);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(239, 68, 68, 0.1));
            color: var(--danger);
        }

        .alert-warning {
            border-color: rgba(245, 158, 11, 0.3);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(245, 158, 11, 0.1));
            color: var(--warning);
        }

        .alert i {
            font-size: 20px;
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
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease forwards;
            opacity: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
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
            
            .page-header {
                padding: 20px;
            }
            
            .page-header h1 {
                font-size: 24px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .card {
                padding: 15px;
            }
            
            #salaireTable {
                display: block;
                overflow-x: auto;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-card .stat-value {
                font-size: 24px;
            }
        }

        /* Loading State */
        .loading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30px;
            height: 30px;
            margin: -15px 0 0 -15px;
            border: 3px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Print Styles */
        @media print {
            .header, .sidebar, .form-actions, .table-actions {
                display: none !important;
            }
            
            .main-content {
                padding: 0;
            }
            
            .page-header, .card, .table-container {
                box-shadow: none;
                border: 1px solid #000;
            }
            
            #salaireTable {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-money-bill-wave"></i> Gestion des Salaires</h1>
            <p>Gérez les salaires et les configurations de rémunération par poste</p>
            <div class="filters" style="margin-top: 20px; display: flex; gap: 15px; align-items: center;">
                <span class="badge badge-primary">
                    <i class="fas fa-calendar-alt"></i> 
                    Période : <?php echo date('F Y', strtotime($currentMonth)); ?>
                </span>
                <button class="btn btn-secondary btn-sm" onclick="changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i> Mois précédent
                </button>
                <button class="btn btn-secondary btn-sm" onclick="changeMonth(1)">
                    Mois suivant <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : ($message_type === 'error' ? 'exclamation' : 'info'); ?>-circle"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card fade-in">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_employes']; ?></div>
                <div class="stat-label">Employés Total</div>
            </div>
            
            <div class="stat-card fade-in" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value">
                    <?php echo number_format($stats['total_salaire_brut'], 0, ',', ' '); ?> FCFA
                </div>
                <div class="stat-label">Masse Salariale</div>
            </div>
            
            <div class="stat-card fade-in" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value">
                    <?php echo number_format($stats['total_heures_travail'], 1, ',', ' '); ?> h
                </div>
                <div class="stat-label">Heures Total Travaillées</div>
            </div>
            
            <div class="stat-card fade-in" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-value"><?php echo $stats['postes_configures']; ?>/<?php echo count($allPostes); ?></div>
                <div class="stat-label">Postes Configurés</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid" style="margin-top: 30px;">
            <!-- Formulaire de configuration de salaire -->
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-cogs"></i> Configuration de Salaire par Poste</h2>
                </div>
                
                <form method="POST" id="salaireForm">
                    <input type="hidden" name="action" value="configurer_poste">
                    
                    <div class="form-group">
                        <label for="poste_id">Poste *</label>
                        <select id="poste_id" name="poste_id" class="form-control" required>
                            <option value="">Sélectionnez un poste...</option>
                            <?php foreach ($allPostes as $poste): ?>
                                <option value="<?php echo $poste['id']; ?>" 
                                    <?php echo isset($_POST['poste_id']) && $_POST['poste_id'] == $poste['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($poste['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                            Sélectionnez le poste à configurer
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="salaire_brut_mensuel">Salaire Brut Mensuel (FCFA) *</label>
                        <input type="number" id="salaire_brut_mensuel" name="salaire_brut_mensuel" 
                               class="form-control" required min="0" step="1000"
                               placeholder="Ex: 500000"
                               value="<?php echo $_POST['salaire_brut_mensuel'] ?? ''; ?>">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                            Salaire brut mensuel pour ce poste
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="jours_travail_mois">Jours de Travail par Mois *</label>
                        <input type="number" id="jours_travail_mois" name="jours_travail_mois" 
                               class="form-control" required min="1" max="31" step="1"
                               placeholder="Ex: 22"
                               value="<?php echo $_POST['jours_travail_mois'] ?? '22'; ?>">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                            Nombre de jours travaillés par mois (généralement 22)
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="heures_travail_jour">Heures de Travail par Jour *</label>
                        <input type="number" id="heures_travail_jour" name="heures_travail_jour" 
                               class="form-control" required min="1" max="24" step="0.5"
                               placeholder="Ex: 8"
                               value="<?php echo $_POST['heures_travail_jour'] ?? '8.0'; ?>">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                            Nombre d'heures travaillées par jour (ex: 8.0)
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer la Configuration
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="calculateHourlyRate()">
                            <i class="fas fa-calculator"></i> Calculer Taux Horaire
                        </button>
                    </div>
                    
                    <!-- Résultat du calcul -->
                    <div id="hourlyRateResult" style="margin-top: 20px; padding: 15px; background: var(--bg-secondary); border-radius: 8px; display: none;">
                        <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">
                            <i class="fas fa-chart-line"></i> Calcul du Taux Horaire
                        </h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <span style="font-size: 12px; color: var(--text-secondary);">Taux Horaire:</span>
                                <div style="font-size: 18px; font-weight: 600; color: var(--primary);" id="calculatedHourlyRate">0 FCFA/h</div>
                            </div>
                            <div>
                                <span style="font-size: 12px; color: var(--text-secondary);">Heures Mensuelles:</span>
                                <div style="font-size: 18px; font-weight: 600; color: var(--success);" id="totalMonthlyHours">0 h</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Liste des configurations de salaire -->
            <div class="card fade-in" style="animation-delay: 0.2s;">
                <div class="card-header">
                    <h2><i class="fas fa-list-check"></i> Configurations Enregistrées</h2>
                </div>
                
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($salaireConfigs)): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 10px; border-bottom: 1px solid var(--border-color);">Poste</th>
                                    <th style="text-align: right; padding: 10px; border-bottom: 1px solid var(--border-color);">Taux Horaire</th>
                                    <th style="text-align: center; padding: 10px; border-bottom: 1px solid var(--border-color);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salaireConfigs as $config): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 10px;">
                                        <strong><?php echo htmlspecialchars($config['poste_nom']); ?></strong><br>
                                        <small style="color: var(--text-secondary);">
                                            <?php echo number_format($config['salaire_brut_mensuel'], 0, ',', ' '); ?> FCFA/mois
                                        </small>
                                    </td>
                                    <td style="text-align: right; padding: 10px; font-weight: 600;" class="currency currency-positive">
                                        <?php echo number_format($config['salaire_horaire'], 0, ',', ' '); ?> FCFA/h
                                    </td>
                                    <td style="text-align: center; padding: 10px;">
                                        <button class="btn btn-secondary btn-sm" 
                                                onclick="loadConfig(<?php echo $config['poste_id']; ?>)"
                                                title="Charger cette configuration">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 30px;">
                            <i class="fas fa-cogs"></i>
                            <h3>Aucune configuration</h3>
                            <p>Utilisez le formulaire pour configurer les salaires par poste</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color);">
                    <h4 style="margin: 0 0 10px 0; color: var(--text-primary); font-size: 14px;">
                        <i class="fas fa-lightbulb"></i> Information
                    </h4>
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.4;">
                        Le taux horaire est calculé automatiquement : <br>
                        <strong>Salaire horaire = Salaire mensuel ÷ (Jours travail × Heures/jour)</strong>
                    </p>
                </div>
            </div>
        </div>

        <!-- Tableau des salaires -->
        <div class="table-container fade-in" style="animation-delay: 0.4s; margin-top: 30px;">
            <div class="table-header">
                <h2><i class="fas fa-table"></i> Tableau des Salaires - <?php echo date('F Y', strtotime($currentMonth)); ?></h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher un employé..." id="tableSearch">
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" onclick="clearSearch()">
                            <i class="fas fa-times"></i> Effacer
                        </button>
                        <button class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Exporter Excel
                        </button>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table id="salaireTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Employé</th>
                            <th><i class="fas fa-briefcase"></i> Poste</th>
                            <th><i class="fas fa-calendar-day"></i> Jours Présents</th>
                            <th><i class="fas fa-clock"></i> Heures Travaillées</th>
                            <th><i class="fas fa-hourglass-half"></i> Retard Total</th>
                            <th><i class="fas fa-money-bill"></i> Taux Horaire</th>
                            <th><i class="fas fa-calculator"></i> Salaire Brut</th>
                            <th><i class="fas fa-percentage"></i> État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salaireData as $index => $data): 
                            // Déterminer l'état
                            $has_config = !empty($data['salaire_horaire']);
                            $status_class = 'pending';
                            $status_text = 'Non configuré';
                            $status_icon = 'exclamation-circle';
                            
                            if ($has_config) {
                                if ($data['heures_travail_mois'] > 0) {
                                    $status_class = 'active';
                                    $status_text = 'Calculé';
                                    $status_icon = 'check-circle';
                                } else {
                                    $status_class = 'inactive';
                                    $status_text = 'Absent';
                                    $status_icon = 'times-circle';
                                }
                            }
                        ?>
                        <tr class="fade-in" style="animation-delay: <?php echo ($index * 0.05) + 0.5; ?>s;">
                            <td>
                                <strong class="employee-name"><?php echo htmlspecialchars($data['user_nom']); ?></strong><br>
                                <small style="color: var(--text-secondary); font-size: 12px;">
                                    <?php echo htmlspecialchars($data['email']); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge badge-primary">
                                    <i class="fas fa-briefcase"></i>
                                    <?php echo htmlspecialchars($data['poste_nom']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="text-align: center; font-weight: 600;">
                                    <?php echo $data['jours_presents']; ?> jours
                                </div>
                            </td>
                            <td>
                                <div class="currency">
                                    <?php echo number_format($data['heures_travail_mois'], 2, ',', ' '); ?> h
                                </div>
                            </td>
                            <td>
                                <?php if ($data['retard_total_minutes'] > 0): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock"></i>
                                        <?php echo floor($data['retard_total_minutes'] / 60); ?>h<?php echo $data['retard_total_minutes'] % 60; ?>min
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success">À l'heure</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_config): ?>
                                    <div class="currency currency-positive">
                                        <?php echo number_format($data['salaire_horaire'], 0, ',', ' '); ?> FCFA/h
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-exclamation-triangle"></i> Non configuré
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_config && $data['salaire_brut'] > 0): ?>
                                    <div class="currency" style="font-size: 16px; font-weight: 700; color: var(--success);">
                                        <?php echo number_format($data['salaire_brut'], 0, ',', ' '); ?> FCFA
                                    </div>
                                <?php elseif ($has_config): ?>
                                    <span class="badge badge-warning">0 FCFA</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="status-indicator">
                                    <span class="status-dot <?php echo $status_class; ?>"></span>
                                    <span><?php echo $status_text; ?></span>
                                    <i class="fas fa-<?php echo $status_icon; ?>" style="margin-left: 4px;"></i>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($salaireData)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <h3>Aucun employé trouvé</h3>
                                <p>Les données salariales apparaîtront ici une fois configurées</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($salaireData)): ?>
            <div class="table-footer">
                <div>
                    <i class="fas fa-info-circle"></i> 
                    <?php echo count($salaireData); ?> employé(s) - 
                    Total heures : <strong><?php echo number_format($stats['total_heures_travail'], 1, ',', ' '); ?> h</strong>
                    - Total salarial : <strong><?php echo number_format($stats['total_salaire_brut'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px;">
                        <i class="fas fa-lightbulb"></i> 
                        Données pour <?php echo date('F Y', strtotime($currentMonth)); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Recherche dans le tableau
            const tableSearch = document.getElementById('tableSearch');
            const tableRows = document.querySelectorAll('#salaireTable tbody tr');
            
            if (tableSearch) {
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
            }
            
            // Animation des lignes du tableau
            tableRows.forEach((row, index) => {
                if (row.style.display !== 'none') {
                    row.style.animationDelay = (index * 0.05) + 's';
                }
            });
            
            // Calculer le taux horaire si des valeurs sont présentes
            const salaire = document.getElementById('salaire_brut_mensuel');
            const jours = document.getElementById('jours_travail_mois');
            const heures = document.getElementById('heures_travail_jour');
            
            [salaire, jours, heures].forEach(input => {
                input.addEventListener('input', calculateHourlyRate);
            });
            
            // Initialiser le calcul si des valeurs existent
            if (salaire.value || jours.value || heures.value) {
                calculateHourlyRate();
            }
            
            // Mettre à jour le compteur initial
            updateVisibleCount(tableRows.length);
            
            // Afficher une notification si message
            <?php if ($message): ?>
                showNotification('<?php echo $message_type === 'success' ? 'Succès' : 'Erreur'; ?>', 
                               '<?php echo addslashes($message); ?>', 
                               '<?php echo $message_type; ?>');
            <?php endif; ?>
        });
        
        // Calculer le taux horaire
        function calculateHourlyRate() {
            const salaire = parseFloat(document.getElementById('salaire_brut_mensuel').value) || 0;
            const jours = parseInt(document.getElementById('jours_travail_mois').value) || 0;
            const heures = parseFloat(document.getElementById('heures_travail_jour').value) || 0;
            
            if (salaire > 0 && jours > 0 && heures > 0) {
                const totalHours = jours * heures;
                const hourlyRate = salaire / totalHours;
                
                document.getElementById('calculatedHourlyRate').textContent = 
                    formatCurrency(hourlyRate) + ' FCFA/h';
                document.getElementById('totalMonthlyHours').textContent = 
                    formatNumber(totalHours) + ' h';
                
                document.getElementById('hourlyRateResult').style.display = 'block';
            } else {
                document.getElementById('hourlyRateResult').style.display = 'none';
            }
        }
        
        // Charger une configuration existante
        function loadConfig(posteId) {
            fetch('ajax/get_salaire_config.php?poste_id=' + posteId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('poste_id').value = data.config.poste_id;
                        document.getElementById('salaire_brut_mensuel').value = data.config.salaire_brut_mensuel;
                        document.getElementById('jours_travail_mois').value = data.config.jours_travail_mois;
                        document.getElementById('heures_travail_jour').value = data.config.heures_travail_jour;
                        
                        calculateHourlyRate();
                        
                        showNotification('Configuration chargée', 
                                       'Configuration du poste "' + data.config.poste_nom + '" chargée', 
                                       'success');
                        
                        // Scroll vers le formulaire
                        document.getElementById('salaireForm').scrollIntoView({ behavior: 'smooth' });
                    } else {
                        showNotification('Erreur', 'Impossible de charger la configuration', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Erreur', 'Une erreur est survenue', 'error');
                });
        }
        
        // Fonction pour effacer la recherche
        function clearSearch() {
            const tableSearch = document.getElementById('tableSearch');
            if (tableSearch) {
                tableSearch.value = '';
                const tableRows = document.querySelectorAll('#salaireTable tbody tr');
                tableRows.forEach(row => {
                    row.style.display = '';
                });
                updateVisibleCount(tableRows.length);
                showNotification('Recherche effacée', 'Tous les filtres de recherche ont été réinitialisés.', 'info');
            }
        }
        
        // Fonction pour mettre à jour le compteur de lignes visibles
        function updateVisibleCount(count) {
            const footer = document.querySelector('.table-footer');
            if (footer) {
                const countElement = footer.querySelector('div:first-child');
                if (countElement) {
                    const parts = countElement.innerHTML.split('-');
                    if (parts.length > 1) {
                        countElement.innerHTML = `<i class="fas fa-info-circle"></i> ${count} employé(s) - ${parts[1]}`;
                    }
                }
            }
        }
        
        // Fonction pour changer de mois
        function changeMonth(offset) {
            const currentUrl = new URL(window.location.href);
            const currentMonth = '<?php echo $currentMonth; ?>';
            const newDate = new Date(currentMonth + '-01');
            newDate.setMonth(newDate.getMonth() + offset);
            
            const newMonth = newDate.getFullYear() + '-' + 
                           String(newDate.getMonth() + 1).padStart(2, '0');
            
            currentUrl.searchParams.set('month', newMonth);
            window.location.href = currentUrl.toString();
        }
        
        // Fonction pour exporter en Excel
        function exportToExcel() {
            showNotification('Export', 'Préparation de l\'export Excel...', 'info');
            
            const table = document.getElementById('salaireTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            rows.forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('th, td');
                
                cells.forEach(cell => {
                    // Nettoyer le texte (supprimer les balises, etc.)
                    let text = cell.innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
                    // Ajouter des guillemets si nécessaire
                    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                        text = '"' + text.replace(/"/g, '""') + '"';
                    }
                    rowData.push(text);
                });
                
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            link.href = URL.createObjectURL(blob);
            link.download = 'salaires_<?php echo date('Y-m'); ?>.csv';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotification('Export réussi', 'Le fichier Excel a été téléchargé', 'success');
        }
        
        // Fonction pour afficher des notifications
        function showNotification(title, message, type = 'info') {
            // Supprimer les notifications existantes
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            // Créer la notification
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--bg-card);
                color: var(--text-primary);
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                z-index: 1000;
                max-width: 400px;
                animation: slideInRight 0.3s ease;
                border: 1px solid var(--border-color);
                border-left-color: ${type === 'success' ? 'var(--success)' : 
                                  type === 'error' ? 'var(--danger)' : 
                                  type === 'warning' ? 'var(--warning)' : 'var(--info)'};
            `;
            
            let icon = 'info-circle';
            switch(type) {
                case 'success': icon = 'check-circle'; break;
                case 'error': icon = 'exclamation-circle'; break;
                case 'warning': icon = 'exclamation-triangle'; break;
                default: icon = 'info-circle';
            }
            
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${icon}"></i>
                    <div>
                        <strong>${title}</strong>
                        <p style="margin: 4px 0 0 0; font-size: 13px;">${message}</p>
                    </div>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Supprimer automatiquement après 5 secondes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Fonctions utilitaires de formatage
        function formatCurrency(amount) {
            return amount.toLocaleString('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }
        
        function formatNumber(number) {
            return number.toLocaleString('fr-FR', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            });
        }
        
        // Gestion de la soumission du formulaire
        document.getElementById('salaireForm').addEventListener('submit', function(e) {
            const salaire = document.getElementById('salaire_brut_mensuel').value;
            const jours = document.getElementById('jours_travail_mois').value;
            const heures = document.getElementById('heures_travail_jour').value;
            
            if (!salaire || !jours || !heures) {
                e.preventDefault();
                showNotification('Erreur', 'Veuillez remplir tous les champs obligatoires', 'error');
                return false;
            }
            
            if (parseFloat(salaire) <= 0 || parseInt(jours) <= 0 || parseFloat(heures) <= 0) {
                e.preventDefault();
                showNotification('Erreur', 'Les valeurs doivent être supérieures à 0', 'error');
                return false;
            }
            
            // Afficher un indicateur de chargement
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            btn.disabled = true;
            
            // Réactiver après 3 secondes
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
            
            return true;
        });
        
        // Gestion du thème auto
        function handleThemeChange() {
            const theme = document.documentElement.getAttribute('data-theme');
            if (theme === 'auto') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
            }
        }
        
        // Écouter les changements de thème système
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleThemeChange);
        handleThemeChange();
    </script>
</body>
</html>