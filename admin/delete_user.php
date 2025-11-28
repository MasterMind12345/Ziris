<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    // Empêcher l'auto-suppression
    if ($user_id === $_SESSION['user_id']) {
        $_SESSION['error'] = "Vous ne pouvez pas supprimer votre propre compte.";
        header('Location: users.php');
        exit;
    }
    
    try {
        // Commencer une transaction
        $pdo->beginTransaction();
        
        // Supprimer les présences de l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM presences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Supprimer les sessions de l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Supprimer les appareils de l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM user_devices WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Supprimer l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Valider la transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Utilisateur supprimé avec succès.";
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
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