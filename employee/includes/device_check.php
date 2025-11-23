<?php
// Vérifier si l'appareil est autorisé pour ce compte
function checkDeviceAuthorization($userId) {
    global $pdo;
    
    if (!isset($_COOKIE['batobaye_device'])) {
        return false;
    }
    
    $deviceToken = $_COOKIE['batobaye_device'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM user_devices 
            WHERE user_id = ? AND device_token = ?
        ");
        $stmt->execute([$userId, $deviceToken]);
        
        return $stmt->fetch() !== false;
    } catch(PDOException $e) {
        error_log("Erreur vérification appareil: " . $e->getMessage());
        return false;
    }
}

// Déconnecter l'utilisateur si l'appareil n'est pas autorisé
function enforceDeviceAuthorization($userId) {
    if (!checkDeviceAuthorization($userId)) {
        session_destroy();
        setcookie('batobaye_device', '', time() - 3600, '/');
        header('Location: ../index.php?error=device_unauthorized');
        exit;
    }
}
?>