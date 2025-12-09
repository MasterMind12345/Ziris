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

$users = getAllUsers();
$postes = getAllPostes();

// Traitement de l'ajout d'un nouvel utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $poste_id = $_POST['poste_id'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    // Validation
    if (empty($nom) || empty($email) || empty($password)) {
        $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "L'adresse email n'est pas valide.";
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Un utilisateur avec cet email existe déjà.";
        } else {
            // Hasher le mot de passe
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insérer le nouvel utilisateur
            $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, poste_id, is_admin) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$nom, $email, $password_hash, $poste_id, $is_admin])) {
                $_SESSION['success'] = "Utilisateur créé avec succès !";
                header('Location: users.php');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la création de l'utilisateur.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Ziris Admin</title>
    
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
        /* Variables CSS pour les thèmes - alignées avec pointage.php */
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
        .page-header h1 {
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--text-secondary);
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
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-color: #10b981;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fca5a5);
            color: #991b1b;
            border-color: #ef4444;
        }

        .alert i {
            font-size: 20px;
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

        /* Form Section - Style amélioré avec thème */
        .form-section {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .form-section h2 {
            color: var(--primary);
            margin-bottom: 25px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }

        .form-section h2 i {
            color: var(--primary);
            font-size: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-control {
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            transition: var(--transition);
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--bg-card);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: 10px;
            border-left: 4px solid var(--warning);
        }

        .form-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            background-color: var(--bg-card);
            appearance: none;
            position: relative;
            transition: var(--transition);
        }

        .form-check input[type="checkbox"]:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .form-check input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 14px;
            font-weight: bold;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .form-check label {
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            margin: 0;
            font-size: 15px;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
            letter-spacing: 0.5px;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.4);
            background: linear-gradient(135deg, var(--primary-dark), #5a08a5);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary);
            transition: var(--transition);
            background: var(--bg-secondary);
            padding: 8px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle .toggle-icon:hover {
            color: var(--primary);
            background: var(--bg-card);
        }

        .required::after {
            content: " *";
            color: var(--danger);
            font-weight: bold;
        }

        /* Table Container */
        .table-container {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-top: 20px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .table-header h2 {
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
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            min-width: 250px;
            transition: var(--transition);
        }

        .table-search:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #2e46b3);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
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

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: var(--text-primary);
        }

        thead {
            background: var(--bg-secondary);
        }

        thead th {
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        tbody tr:hover {
            background: var(--bg-secondary);
            transform: translateX(5px);
        }

        tbody td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: linear-gradient(135deg, #10b981, #0da271);
            color: white;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3);
        }

        .badge-secondary {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        /* Animation pour les éléments du formulaire */
        .form-group, .form-check, .btn-submit {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-check { animation-delay: 0.5s; }
        .btn-submit { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .table-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-search {
                min-width: 100%;
            }
            
            .form-section h2 {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .btn-submit {
                width: 100%;
                justify-content: center;
            }
            
            .form-check {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Utilisateurs</h1>
            <p>Gérez les comptes des employés de l'entreprise</p>
        </div>

        <!-- Messages de confirmation -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Section d'ajout d'un nouvel employé -->
        <section class="form-section">
            <h2><i class="fas fa-user-plus"></i> Ajouter un Nouvel Employé</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nom" class="required">
                            <i class="fas fa-user"></i> Nom complet
                        </label>
                        <input type="text" id="nom" name="nom" class="form-control" 
                               placeholder="Ex: Jean Dupont" required 
                               value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="email" class="required">
                            <i class="fas fa-envelope"></i> Adresse email
                        </label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Ex: jean.dupont@entreprise.com" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="poste_id" class="required">
                            <i class="fas fa-briefcase"></i> Poste
                        </label>
                        <select id="poste_id" name="poste_id" class="form-control" required>
                            <option value="">Sélectionnez un poste</option>
                            <?php foreach ($postes as $poste): ?>
                                <option value="<?php echo $poste['id']; ?>" 
                                    <?php echo (isset($_POST['poste_id']) && $_POST['poste_id'] == $poste['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($poste['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group password-toggle">
                        <label for="password" class="required">
                            <i class="fas fa-lock"></i> Mot de passe
                        </label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Minimum 6 caractères" required minlength="6">
                        <span class="toggle-icon" onclick="togglePasswordVisibility('password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="is_admin" name="is_admin" value="1"
                           <?php echo (isset($_POST['is_admin']) && $_POST['is_admin']) ? 'checked' : ''; ?>>
                    <label for="is_admin">
                        <i class="fas fa-shield-alt"></i> Accorder les droits administrateur
                    </label>
                </div>

                <button type="submit" name="add_user" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Créer le compte employé
                </button>
            </form>
        </section>
        
        <!-- Liste des utilisateurs existants -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-users"></i> Liste des Employés</h2>
                <div class="table-actions">
                    <input type="text" class="table-search" placeholder="Rechercher un employé...">
                    <button class="btn btn-primary" onclick="exportToCSV('usersTable', 'utilisateurs-ziris.csv')">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                </div>
            </div>
            
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Poste</th>
                        <th>Admin</th>
                        <th>Date d'inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['nom']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['poste_nom'] ?? 'Non défini'); ?></td>
                        <td>
                            <span class="badge <?php echo $user['is_admin'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $user['is_admin'] ? 'Admin' : 'Employé'; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-secondary btn-sm" onclick="editUser(<?php echo $user['id']; ?>)" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['nom'])); ?>')" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <script src="js/script.js"></script>
    <script>
        // Fonction pour basculer la visibilité du mot de passe
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentNode.querySelector('.toggle-icon i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Fonction pour supprimer un utilisateur
        function deleteUser(userId, userName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${userName}" ?\n\nCette action est irréversible et supprimera également toutes ses données de présence.`)) {
                // Créer un formulaire pour envoyer la requête
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_user.php';
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                
                form.appendChild(userIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fonction pour éditer un utilisateur (à implémenter)
        function editUser(userId) {
            alert('Fonction d\'édition à implémenter pour l\'utilisateur ID: ' + userId);
            // window.location.href = `edit_user.php?id=${userId}`;
        }

        // Fonction de recherche dans le tableau
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.table-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const table = document.getElementById('usersTable');
                    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                    
                    for (let row of rows) {
                        const cells = row.getElementsByTagName('td');
                        let found = false;
                        
                        for (let cell of cells) {
                            if (cell.textContent.toLowerCase().includes(searchTerm)) {
                                found = true;
                                break;
                            }
                        }
                        
                        row.style.display = found ? '' : 'none';
                    }
                });
            }

            // Appliquer des animations au chargement
            const tableRows = document.querySelectorAll('#usersTable tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = (index * 0.05) + 's';
                row.style.animation = 'fadeInUp 0.5s ease forwards';
                row.style.opacity = '0';
            });

            // Fonction d'export CSV
            window.exportToCSV = function(tableId, filename) {
                const table = document.getElementById(tableId);
                const rows = table.querySelectorAll('tr');
                const csv = [];
                
                rows.forEach(row => {
                    const rowData = [];
                    const cells = row.querySelectorAll('th, td');
                    
                    cells.forEach(cell => {
                        // Exclure la colonne Actions
                        if (!cell.querySelector('.btn')) {
                            rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
                        }
                    });
                    
                    csv.push(rowData.join(','));
                });
                
                const csvContent = csv.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                
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
            };
        });

        // Animation CSS pour les lignes du tableau
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>