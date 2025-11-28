<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer tous les utilisateurs
$users = getAllUsers();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Batobaye Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Batobaye">
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
    <meta name="apple-mobile-web-app-title" content="Batobaye">

    <!-- CSS existant -->
    <link rel="stylesheet" href="../css/employee.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        
        <div class="table-container">
            <div class="table-header">
                <h2>Liste des Employés</h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher un employé...">
                    <button class="btn btn-primary" onclick="exportToCSV('usersTable', 'utilisateurs-batobaye.csv')">
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