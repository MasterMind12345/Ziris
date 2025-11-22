<?php
session_start();

try {
    $pdo = new PDO("mysql:host=localhost;dbname=systeme_presence", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Réinitialisation du compte administrateur</h2>";
    
    // Mot de passe: admin123
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@entreprise.com'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        // Mettre à jour le mot de passe
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@entreprise.com'");
        $stmt->execute([$hashed_password]);
        echo "<p style='color: green;'>✓ Mot de passe administrateur mis à jour</p>";
    } else {
        // Créer l'administrateur
        $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, poste_id, is_admin) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Administrateur', 'admin@entreprise.com', $hashed_password, 1, true]);
        echo "<p style='color: green;'>✓ Administrateur créé</p>";
    }
    
    // Vérifier que le mot de passe fonctionne
    $stmt = $pdo->prepare("SELECT password FROM users WHERE email = 'admin@entreprise.com'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user && password_verify('admin123', $user['password'])) {
        echo "<p style='color: green;'>✓ Vérification réussie : le mot de passe 'admin123' fonctionne</p>";
    } else {
        echo "<p style='color: red;'>✗ Échec de la vérification du mot de passe</p>";
    }
    
    echo "<p><strong>Identifiants :</strong><br>";
    echo "Email: admin@entreprise.com<br>";
    echo "Mot de passe: admin123</p>";
    
    echo "<p><a href='login.php'>Se connecter</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>