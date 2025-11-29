<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    if ($user_id === $_SESSION['user_id']) {
        $_SESSION['error'] = "Vous ne pouvez pas supprimer votre propre compte.";
        header('Location: users.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM presences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("DELETE FROM user_devices WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Utilisateur supprimé avec succès.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
    
    header('Location: users.php');
    exit;
} else {
    header('Location: users.php');
    exit;
}
?>