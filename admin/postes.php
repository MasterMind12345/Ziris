<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Postes - Batobaye Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Postes</h1>
            <p>Créez et gérez les différents postes de l'entreprise</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Formulaire d'ajout -->
            <div class="form-container">
                <h2><i class="fas fa-plus-circle"></i> Ajouter un Poste</h2>
                <form method="POST" id="formPoste">
                    <input type="hidden" name="action" value="ajouter" id="formAction">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="form-group">
                        <label for="nom">Nom du poste *</label>
                        <input type="text" id="nom" name="nom" class="form-control" required 
                               placeholder="Ex: Développeur Web, Commercial, RH...">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" 
                                  placeholder="Description des responsabilités du poste..." 
                                  rows="4"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-plus"></i> Ajouter le Poste
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statistiques des postes -->
            <div class="stats-container">
                <h2><i class="fas fa-chart-pie"></i> Répartition par Poste</h2>
                <div class="stats-grid-small">
                    <?php foreach ($stats_postes as $stat): ?>
                        <div class="stat-card-small">
                            <div class="stat-info">
                                <h3><?php echo $stat['nb_employes']; ?></h3>
                                <p><?php echo htmlspecialchars($stat['nom']); ?></p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($stats_postes)): ?>
                        <div class="stat-card-small">
                            <div class="stat-info">
                                <h3>0</h3>
                                <p>Aucun poste</p>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Liste des postes -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Liste des Postes</h2>
                <div class="table-actions">
                    <input type="text" class="form-control table-search" placeholder="Rechercher un poste...">
                </div>
            </div>
            
            <table id="postesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom du Poste</th>
                        <th>Description</th>
                        <th>Employés</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postes as $poste): 
                        // Compter le nombre d'employés pour ce poste
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE poste_id = ?");
                        $stmt->execute([$poste['id']]);
                        $nb_employes = $stmt->fetch()['count'];
                    ?>
                    <tr>
                        <td><?php echo $poste['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($poste['nom']); ?></strong>
                        </td>
                        <td>
                            <?php echo !empty($poste['description']) ? htmlspecialchars($poste['description']) : '<span class="text-muted">Aucune description</span>'; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $nb_employes > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                <?php echo $nb_employes; ?> employé(s)
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($poste['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="editPoste(<?php echo $poste['id']; ?>, '<?php echo htmlspecialchars($poste['nom']); ?>', '<?php echo htmlspecialchars($poste['description']); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $poste['id']; ?>, '<?php echo htmlspecialchars($poste['nom']); ?>', <?php echo $nb_employes; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($postes)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px;">
                            <i class="fas fa-inbox" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                            <p>Aucun poste créé pour le moment</p>
                            <p class="text-muted">Utilisez le formulaire ci-dessus pour ajouter votre premier poste</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmer la suppression</h3>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage"></p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .stats-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
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
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .stat-card-small .stat-info h3 {
            margin: 0;
            font-size: 24px;
            color: var(--dark);
        }

        .stat-card-small .stat-info p {
            margin: 0;
            color: var(--gray);
            font-size: 14px;
        }

        .stat-card-small .stat-icon {
            color: var(--primary);
            font-size: 24px;
        }

        .text-muted {
            color: #6c757d !important;
            font-style: italic;
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
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--dark);
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e9ecef;
            text-align: right;
        }

        .modal-footer .btn {
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px;
            }
        }
    </style>

    <script>
        // Édition d'un poste
        function editPoste(id, nom, description) {
            document.getElementById('formAction').value = 'modifier';
            document.getElementById('editId').value = id;
            document.getElementById('nom').value = nom;
            document.getElementById('description').value = description;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Modifier le Poste';
            document.getElementById('cancelBtn').style.display = 'inline-block';
            
            // Scroll vers le formulaire
            document.getElementById('formPoste').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('nom').focus();
        }

        // Annuler l'édition
        document.getElementById('cancelBtn').addEventListener('click', function() {
            resetForm();
        });

        // Réinitialiser le formulaire
        function resetForm() {
            document.getElementById('formPoste').reset();
            document.getElementById('formAction').value = 'ajouter';
            document.getElementById('editId').value = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-plus"></i> Ajouter le Poste';
            document.getElementById('cancelBtn').style.display = 'none';
        }

        // Confirmation de suppression
        function confirmDelete(id, nom, nbEmployes) {
            const modal = document.getElementById('deleteModal');
            const message = document.getElementById('deleteMessage');
            const deleteId = document.getElementById('deleteId');
            
            deleteId.value = id;
            
            if (nbEmployes > 0) {
                message.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention !</strong><br>
                        Le poste "${nom}" est actuellement assigné à ${nbEmployes} employé(s).<br>
                        Vous ne pouvez pas le supprimer tant qu'il est utilisé.
                    </div>
                `;
                document.querySelector('#deleteForm button[type="submit"]').style.display = 'none';
            } else {
                message.innerHTML = `
                    <p>Êtes-vous sûr de vouloir supprimer le poste <strong>"${nom}"</strong> ?</p>
                    <p class="text-muted">Cette action est irréversible.</p>
                `;
                document.querySelector('#deleteForm button[type="submit"]').style.display = 'inline-block';
            }
            
            modal.style.display = 'flex';
        }

        // Fermer le modal
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Recherche dans le tableau
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.table-search');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const table = document.getElementById('postesTable');
                    const rows = table.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(filter) ? '' : 'none';
                    });
                });
            }

            // Réinitialiser le formulaire après soumission réussie
            <?php if ($message_type === 'success'): ?>
                resetForm();
            <?php endif; ?>
        });

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>