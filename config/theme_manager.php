<?php
// config/theme_manager.php

function getCurrentTheme($pdo, $user_id) {
    // Vérifier d'abord la session
    if (isset($_SESSION['user_theme'])) {
        return $_SESSION['user_theme'];
    }
    
    // Sinon, chercher dans la base de données
    try {
        $stmt = $pdo->prepare("SELECT theme FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch();
        
        if ($preferences && !empty($preferences['theme'])) {
            $_SESSION['user_theme'] = $preferences['theme'];
            return $preferences['theme'];
        }
    } catch(PDOException $e) {
        // En cas d'erreur, utiliser le thème par défaut
    }
    
    return 'light'; // Thème par défaut
}
?>