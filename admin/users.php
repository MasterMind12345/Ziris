<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Ziris Admin</title>
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
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .form-section h2 {
            color: #4361ee;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section h2 i {
            color: #4361ee;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #4361ee;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid #dee2e6;
            cursor: pointer;
        }

        .form-check input[type="checkbox"]:checked {
            background-color: #4361ee;
            border-color: #4361ee;
        }

        .form-check label {
            font-weight: 500;
            color: #495057;
            cursor: pointer;
            margin: 0;
        }

        .btn-submit {
            background: linear-gradient(135deg, #4361ee, #3a56d4);
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            transition: color 0.3s ease;
        }

        .password-toggle .toggle-icon:hover {
            color: #4361ee;
        }

        .required::after {
            content: " *";
            color: #e63946;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-section {
                padding: 1.5rem;
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
                        <label for="nom" class="required">Nom complet</label>
                        <input type="text" id="nom" name="nom" class="form-control" 
                               placeholder="Ex: Jean Dupont" required 
                               value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="email" class="required">Adresse email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Ex: jean.dupont@entreprise.com" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="poste_id" class="required">Poste</label>
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
                        <label for="password" class="required">Mot de passe</label>
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
                    <label for="is_admin">Accorder les droits administrateur</label>
                </div>

                <button type="submit" name="add_user" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Créer le compte employé
                </button>
            </form>
        </section>
        
        <!-- Liste des utilisateurs existants -->
        <div class="table-container">
            <div class="table-header">
                <h2>Liste des Employés</h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher un employé...">
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
                                <?php echo $user['is_admin'] ? 'Oui' : 'Non'; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="editUser(<?php echo $user['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['nom'])); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
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
        });
    </script>
</body>
</html>