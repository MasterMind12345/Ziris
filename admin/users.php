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
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Utilisateurs</h1>
            <p>Gérez les comptes des employés de l'entreprise</p>
        </div>
        
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
                            <button class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm">
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
</body>
</html>