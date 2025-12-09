<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
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

$message = '';
$message_type = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'ajouter') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!empty($nom)) {
                $stmt = $pdo->prepare("INSERT INTO postes (nom, description) VALUES (?, ?)");
                $stmt->execute([$nom, $description]);
                $message = "Poste ajouté avec succès!";
                $message_type = 'success';
            } else {
                $message = "Le nom du poste est obligatoire";
                $message_type = 'error';
            }
            
        } elseif ($action === 'modifier') {
            $id = $_POST['id'] ?? '';
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!empty($id) && !empty($nom)) {
                $stmt = $pdo->prepare("UPDATE postes SET nom = ?, description = ? WHERE id = ?");
                $stmt->execute([$nom, $description, $id]);
                $message = "Poste modifié avec succès!";
                $message_type = 'success';
            }
            
        } elseif ($action === 'supprimer') {
            $id = $_POST['id'] ?? '';
            
            if (!empty($id)) {
                // Vérifier si le poste est utilisé
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE poste_id = ?");
                $stmt->execute([$id]);
                $usage = $stmt->fetch();
                
                if ($usage['count'] > 0) {
                    $message = "Impossible de supprimer ce poste : il est assigné à des employés";
                    $message_type = 'error';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM postes WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Poste supprimé avec succès!";
                    $message_type = 'success';
                }
            }
        }
    } catch(PDOException $e) {
        $message = "Erreur: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Récupérer tous les postes
try {
    $stmt = $pdo->query("SELECT * FROM postes ORDER BY nom");
    $postes = $stmt->fetchAll();
} catch(PDOException $e) {
    $postes = [];
    $message = "Erreur lors du chargement des postes: " . $e->getMessage();
    $message_type = 'error';
}

// Récupérer les statistiques d'utilisation des postes
try {
    $stmt = $pdo->query("
        SELECT p.id, p.nom, COUNT(u.id) as nb_employes 
        FROM postes p 
        LEFT JOIN users u ON p.id = u.poste_id 
        GROUP BY p.id, p.nom 
        ORDER BY p.nom
    ");
    $stats_postes = $stmt->fetchAll();
} catch(PDOException $e) {
    $stats_postes = [];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Postes - Ziris Admin</title>
    
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

    <!-- PWA Configuration -->
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/pwa-install.css">
    <script src="/pwa-install.js" defer></script>
    <meta name="theme-color" content="#4361ee"/>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">

    <!-- CSS supplémentaire pour le thème -->
    <style>
        /* Variables CSS pour les thèmes - alignées avec theme.php et presences.php */
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

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Form Container */
        .form-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .form-container h2 {
            font-size: 22px;
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-container h2 i {
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

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Form Actions */
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

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Stats Container */
        .stats-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .stats-container h2 {
            font-size: 22px;
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-container h2 i {
            color: var(--primary);
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
            transition: var(--transition);
        }

        .stat-card-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%234361ee' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
        }

        /* Table Styling */
        #postesTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        #postesTable thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            position: sticky;
            top: 0;
            z-index: 10;
        }

        #postesTable thead th {
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

        #postesTable tbody tr {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        #postesTable tbody tr:hover {
            background: var(--bg-secondary);
        }

        #postesTable tbody td {
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

        .badge-primary {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(67, 97, 238, 0.2));
            color: var(--primary);
            border: 1px solid rgba(67, 97, 238, 0.3);
        }

        .badge-secondary {
            background: linear-gradient(135deg, rgba(108, 117, 125, 0.1), rgba(108, 117, 125, 0.2));
            color: var(--text-secondary);
            border: 1px solid rgba(108, 117, 125, 0.3);
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

        /* Text Muted */
        .text-muted {
            color: var(--text-secondary) !important;
            font-style: italic;
            opacity: 0.7;
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
            padding: 15px 25px;
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

        .modal-footer .btn {
            margin-left: 10px;
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
            
            .modal-content {
                width: 95%;
                margin: 20px;
            }
        }

        @media (max-width: 480px) {
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .form-container,
            .stats-container {
                padding: 20px;
            }
            
            #postesTable {
                display: block;
                overflow-x: auto;
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

        /* Notification Styles */
        .notification {
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
        }

        .notification-success {
            border-left-color: var(--success);
        }

        .notification-error {
            border-left-color: var(--danger);
        }

        .notification-warning {
            border-left-color: var(--warning);
        }

        .notification-info {
            border-left-color: var(--info);
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
            color: var(--text-secondary);
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
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-briefcase"></i> Gestion des Postes</h1>
            <p>Créez et gérez les différents postes de l'entreprise</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : ($message_type === 'error' ? 'exclamation' : 'info'); ?>-circle"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Formulaire d'ajout/modification -->
            <div class="form-container fade-in">
                <h2><i class="fas fa-plus-circle"></i> <span id="formTitle">Ajouter un Poste</span></h2>
                <form method="POST" id="formPoste">
                    <input type="hidden" name="action" value="ajouter" id="formAction">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="form-group">
                        <label for="nom">Nom du poste *</label>
                        <input type="text" id="nom" name="nom" class="form-control" required 
                               placeholder="Ex: Développeur Web, Commercial, RH...">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                            Le nom du poste doit être unique et descriptif
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" 
                                  placeholder="Description des responsabilités, qualifications requises, horaires..." 
                                  rows="4"></textarea>
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                            Optionnel - Cette description sera visible par les employés
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-plus"></i> <span id="submitText">Ajouter le Poste</span>
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statistiques des postes -->
            <div class="stats-container fade-in" style="animation-delay: 0.1s;">
                <h2><i class="fas fa-chart-pie"></i> Répartition par Poste</h2>
                <div class="stats-grid-small">
                    <?php foreach ($stats_postes as $index => $stat): ?>
                        <div class="stat-card-small fade-in" style="animation-delay: <?php echo ($index * 0.1) + 0.2; ?>s;">
                            <div class="stat-info">
                                <h3><?php echo $stat['nb_employes']; ?></h3>
                                <p><?php echo htmlspecialchars($stat['nom']); ?></p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($stats_postes)): ?>
                        <div class="stat-card-small">
                            <div class="stat-info">
                                <h3>0</h3>
                                <p>Aucun poste créé</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <h3 style="font-size: 16px; margin-bottom: 10px; color: var(--text-primary);">
                        <i class="fas fa-info-circle" style="color: var(--info);"></i> Informations
                    </h3>
                    <p style="font-size: 14px; color: var(--text-secondary); margin: 0; line-height: 1.5;">
                        Les postes sont utilisés pour catégoriser les employés et générer des rapports spécifiques par département.
                    </p>
                </div>
            </div>
        </div>

        <!-- Liste des postes -->
        <div class="table-container fade-in" style="animation-delay: 0.2s;">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Liste des Postes</h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher un poste..." id="tableSearch">
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" onclick="clearSearch()">
                            <i class="fas fa-times"></i> Effacer
                        </button>
                        <button class="btn btn-primary" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i> Actualiser
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table id="postesTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-briefcase"></i> Nom du Poste</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-users"></i> Employés</th>
                            <th><i class="fas fa-calendar"></i> Date de création</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($postes as $index => $poste): 
                            // Compter le nombre d'employés pour ce poste
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE poste_id = ?");
                            $stmt->execute([$poste['id']]);
                            $nb_employes = $stmt->fetch()['count'];
                        ?>
                        <tr class="fade-in" style="animation-delay: <?php echo ($index * 0.05) + 0.3; ?>s;">
                            <td>
                                <span class="badge badge-secondary">#<?php echo $poste['id']; ?></span>
                            </td>
                            <td>
                                <strong class="employee-name"><?php echo htmlspecialchars($poste['nom']); ?></strong>
                            </td>
                            <td style="max-width: 300px;">
                                <?php if (!empty($poste['description'])): ?>
                                    <div style="font-size: 13px; line-height: 1.4; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars(substr($poste['description'], 0, 100)); ?>
                                        <?php echo strlen($poste['description']) > 100 ? '...' : ''; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Aucune description</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $nb_employes > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                    <i class="fas fa-user<?php echo $nb_employes > 1 ? 's' : ''; ?>"></i>
                                    <?php echo $nb_employes; ?> employé(s)
                                </span>
                            </td>
                            <td>
                                <div class="time-display" style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 6px; font-size: 12px;">
                                    <?php echo date('d/m/Y', strtotime($poste['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-secondary btn-sm" onclick="editPoste(<?php echo $poste['id']; ?>, '<?php echo htmlspecialchars($poste['nom']); ?>', '<?php echo htmlspecialchars(addslashes($poste['description'])); ?>')" 
                                            title="Modifier le poste">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $poste['id']; ?>, '<?php echo htmlspecialchars($poste['nom']); ?>', <?php echo $nb_employes; ?>)" 
                                            title="Supprimer le poste" <?php echo $nb_employes > 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($postes)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>Aucun poste créé</h3>
                                <p>Utilisez le formulaire ci-dessus pour ajouter votre premier poste</p>
                                <button class="btn btn-primary" onclick="document.getElementById('nom').focus()">
                                    <i class="fas fa-plus"></i> Ajouter un poste
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Table Footer -->
            <?php if (!empty($postes)): ?>
            <div class="table-footer">
                <div>
                    <i class="fas fa-info-circle"></i> 
                    <?php echo count($postes); ?> poste(s) trouvé(s)
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px;">
                        <i class="fas fa-lightbulb"></i> 
                        Cliquez sur les icônes d'action pour modifier ou supprimer un poste
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirmer la suppression</h3>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="deleteMessage"></div>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-danger" id="deleteSubmitBtn">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Appliquer le thème au chargement
            applyThemeStyles();
            
            // Recherche dans le tableau
            const tableSearch = document.getElementById('tableSearch');
            const tableRows = document.querySelectorAll('#postesTable tbody tr');
            
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
            
            // Animation des cartes de stats
            const statItems = document.querySelectorAll('.stat-card-small');
            statItems.forEach((item, index) => {
                item.style.animationDelay = (index * 0.1) + 's';
            });
            
            // Mettre à jour le compteur initial
            updateVisibleCount(tableRows.length);
            
            // Réinitialiser le formulaire après soumission réussie
            <?php if ($message_type === 'success'): ?>
                resetForm();
                // Afficher une notification
                showNotification('Succès', '<?php echo addslashes($message); ?>', 'success');
            <?php elseif ($message_type === 'error'): ?>
                showNotification('Erreur', '<?php echo addslashes($message); ?>', 'error');
            <?php endif; ?>
        });
        
        // Fonction pour appliquer les styles du thème
        function applyThemeStyles() {
            const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
            
            // Mettre à jour les couleurs des icônes si nécessaire
            if (isDarkTheme) {
                document.documentElement.style.setProperty('--calendar-icon-invert', '1');
            }
        }
        
        // Édition d'un poste
        function editPoste(id, nom, description) {
            document.getElementById('formAction').value = 'modifier';
            document.getElementById('editId').value = id;
            document.getElementById('nom').value = nom;
            document.getElementById('description').value = description;
            document.getElementById('formTitle').textContent = 'Modifier le Poste';
            document.getElementById('submitText').textContent = 'Modifier le Poste';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Modifier le Poste';
            document.getElementById('cancelBtn').style.display = 'inline-block';
            
            // Scroll vers le formulaire
            document.getElementById('formPoste').scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('nom').focus();
            
            showNotification('Mode édition', 'Vous êtes en train de modifier le poste "' + nom + '"', 'info');
        }
        
        // Annuler l'édition
        document.getElementById('cancelBtn').addEventListener('click', function() {
            resetForm();
            showNotification('Action annulée', 'Les modifications ont été annulées', 'warning');
        });
        
        // Réinitialiser le formulaire
        function resetForm() {
            document.getElementById('formPoste').reset();
            document.getElementById('formAction').value = 'ajouter';
            document.getElementById('editId').value = '';
            document.getElementById('formTitle').textContent = 'Ajouter un Poste';
            document.getElementById('submitText').textContent = 'Ajouter le Poste';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-plus"></i> Ajouter le Poste';
            document.getElementById('cancelBtn').style.display = 'none';
        }
        
        // Confirmation de suppression
        function confirmDelete(id, nom, nbEmployes) {
            const modal = document.getElementById('deleteModal');
            const message = document.getElementById('deleteMessage');
            const deleteId = document.getElementById('deleteId');
            const deleteSubmitBtn = document.getElementById('deleteSubmitBtn');
            
            deleteId.value = id;
            
            if (nbEmployes > 0) {
                message.innerHTML = `
                    <div class="alert alert-warning" style="margin: 0;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Impossible de supprimer ce poste</strong><br>
                            <p style="margin: 8px 0 0 0;">Le poste <strong>"${nom}"</strong> est actuellement assigné à <strong>${nbEmployes}</strong> employé(s).</p>
                            <p style="margin: 8px 0 0 0; font-size: 13px;">Vous devez réassigner ces employés à un autre poste avant de pouvoir le supprimer.</p>
                        </div>
                    </div>
                `;
                deleteSubmitBtn.style.display = 'none';
            } else {
                message.innerHTML = `
                    <div style="text-align: center;">
                        <i class="fas fa-trash-alt" style="font-size: 48px; color: var(--danger); margin-bottom: 15px;"></i>
                        <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">Êtes-vous sûr ?</h4>
                        <p style="color: var(--text-secondary); margin: 0 0 15px 0;">
                            Vous êtes sur le point de supprimer le poste <strong>"${nom}"</strong>.<br>
                            Cette action est irréversible.
                        </p>
                    </div>
                `;
                deleteSubmitBtn.style.display = 'inline-block';
            }
            
            modal.style.display = 'flex';
        }
        
        // Fermer le modal
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Fonction pour effacer la recherche
        function clearSearch() {
            const tableSearch = document.getElementById('tableSearch');
            if (tableSearch) {
                tableSearch.value = '';
                const tableRows = document.querySelectorAll('#postesTable tbody tr');
                tableRows.forEach(row => {
                    row.style.display = '';
                });
                updateVisibleCount(tableRows.length);
                showNotification('Recherche effacée', 'Tous les filtres de recherche ont été réinitialisés.', 'info');
            }
        }
        
        // Fonction pour actualiser la page
        function refreshPage() {
            showNotification('Actualisation', 'Actualisation de la page en cours...', 'info');
            setTimeout(() => {
                window.location.reload();
            }, 300);
        }
        
        // Fonction pour mettre à jour le compteur de lignes visibles
        function updateVisibleCount(count) {
            const footer = document.querySelector('.table-footer');
            if (footer) {
                const countElement = footer.querySelector('div:first-child');
                if (countElement) {
                    countElement.innerHTML = `<i class="fas fa-info-circle"></i> ${count} poste(s) trouvé(s)`;
                }
            }
        }
        
        // Fonction pour afficher des notifications
        function showNotification(title, message, type = 'info') {
            // Supprimer les notifications existantes
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            // Créer la notification
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            
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
        
        // Appliquer le thème auto au chargement
        handleThemeChange();
        
        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Gestion de la soumission du formulaire de suppression
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            showNotification('Suppression', 'Suppression du poste en cours...', 'info');
        });
        
        // Gestion de la soumission du formulaire principal
        document.getElementById('formPoste').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            if (!nom) {
                e.preventDefault();
                showNotification('Erreur', 'Le nom du poste est obligatoire', 'error');
                document.getElementById('nom').focus();
                return false;
            }
            
            const action = document.getElementById('formAction').value;
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;
            
            // Afficher un indicateur de chargement
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
            btn.disabled = true;
            
            // Réactiver le bouton après 3 secondes (au cas où la soumission échoue)
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
            
            return true;
        });
    </script>
</body>
</html>