<?php
session_start();

// Stocker un message de déconnexion avant de détruire la session
$logout_message = "Vous avez été déconnecté avec succès.";

// Détruire toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Recréer une session temporaire pour le message
session_start();
$_SESSION['logout_message'] = $logout_message;

// Rediriger vers la page de connexion
header('Location: login.php');
exit;
?>