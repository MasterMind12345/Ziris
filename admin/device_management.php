<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->query("
    SELECT ud.*, u.nom, u.email 
    FROM user_devices ud 
    JOIN users u ON ud.user_id = u.id 
    ORDER BY ud.last_login DESC
");
$devices = $stmt->fetchAll();

if (isset($_POST['deactivate_device'])) {
    $deviceId = $_POST['device_id'];
    $stmt = $pdo->prepare("UPDATE user_devices SET is_active = 0 WHERE id = ?");
    $stmt->execute([$deviceId]);
    header('Location: device_management.php');
    exit;
}

if (isset($_POST['activate_device'])) {
    $deviceId = $_POST['device_id'];
    $stmt = $pdo->prepare("UPDATE user_devices SET is_active = 1 WHERE id = ?");
    $stmt->execute([$deviceId]);
    header('Location: device_management.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils - Ziris Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Appareils</h1>
            <p>Gérez les appareils associés aux comptes utilisateurs</p>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h2>Appareils Enregistrés</h2>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Empreinte Appareil</th>
                        <th>IP</th>
                        <th>Dernière Connexion</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($device['nom']); ?></td>
                        <td><?php echo htmlspecialchars($device['email']); ?></td>
                        <td><code><?php echo substr($device['device_fingerprint'], 0, 16) . '...'; ?></code></td>
                        <td><?php echo htmlspecialchars($device['ip_address']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($device['last_login'])); ?></td>
                        <td>
                            <?php if ($device['is_active']): ?>
                                <span class="badge badge-success">Actif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                <?php if ($device['is_active']): ?>
                                    <button type="submit" name="deactivate_device" class="btn btn-danger btn-sm">
                                        <i class="fas fa-ban"></i> Désactiver
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="activate_device" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Activer
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>