<?php
session_start();

try {
    $pdo = new PDO("mysql:host=localhost", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS systeme_presence");
    $pdo->exec("USE systeme_presence");

} catch(PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
            
            if (!$tableExists) {
                createDatabaseTables($pdo);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Vérifier le mot de passe
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['nom'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    
                    $success = "Connexion réussie ! Redirection...";
                    
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "' . ($user['is_admin'] ? 'admin/index.php' : 'employee/dashboard.php') . '";
                        }, 1000);
                    </script>';
                    
                } else {
                    $error = "Mot de passe incorrect.";
                }
            } else {
                $error = "Aucun utilisateur trouvé avec cet email.";
            }
        } catch(PDOException $e) {
            $error = "Erreur de base de données: " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}

// Fonction pour créer les tables si elles n'existent pas
function createDatabaseTables($pdo) {
    $sql = "
    -- Table des postes/roles dans l'entreprise
    CREATE TABLE IF NOT EXISTS postes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Table des utilisateurs/employés
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        poste_id INT,
        is_admin BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Table des paramètres système
    CREATE TABLE IF NOT EXISTS parametres_systeme (
        id INT AUTO_INCREMENT PRIMARY KEY,
        heure_debut_normal TIME NOT NULL,
        heure_fin_normal TIME NOT NULL,
        qr_code_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Table des présences
    CREATE TABLE IF NOT EXISTS presences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date_presence DATE NOT NULL,
        heure_debut_reel TIME NOT NULL,
        heure_fin_reel TIME,
        lieu VARCHAR(255),
        retard_minutes INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Insertion des données par défaut
    INSERT IGNORE INTO postes (id, nom, description) VALUES 
    (1, 'Administrateur', 'Responsable de la gestion du système'),
    (2, 'Manager', 'Responsable d équipe'),
    (3, 'Développeur', 'Développeur web/mobile'),
    (4, 'Designer', 'Designer UI/UX'),
    (5, 'Commercial', 'Responsable commercial'),
    (6, 'RH', 'Ressources Humaines');

    -- Insertion de l'admin par défaut (mot de passe: admin123)
    INSERT IGNORE INTO users (id, nom, email, password, poste_id, is_admin) 
    VALUES (1, 'Administrateur', 'admin@entreprise.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, TRUE);

    -- Insertion des paramètres par défaut
    INSERT IGNORE INTO parametres_systeme (id, heure_debut_normal, heure_fin_normal) 
    VALUES (1, '08:00:00', '17:00:00');
    ";
    
    $pdo->exec($sql);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Ziris | Système de Présence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- PWA Meta Tags -->
<meta name="theme-color" content="#4361ee"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Ziris">
<link rel="apple-touch-icon" href="icons/icon-152x152.png">
<link rel="manifest" href="manifest.json">

<!-- PWA Configuration -->
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="pwa-install.css">
<script src="pwa-install.js" defer></script>
<meta name="theme-color" content="#4361ee"/>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Ziris">

<!-- CSS existant -->
<link rel="stylesheet" href="../css/employee.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .bg-bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 15s infinite linear;
        }

        .bubble-1 {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .bubble-2 {
            width: 120px;
            height: 120px;
            top: 60%;
            left: 80%;
            animation-delay: -5s;
        }

        .bubble-3 {
            width: 60px;
            height: 60px;
            top: 80%;
            left: 20%;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            33% {
                transform: translateY(-30px) rotate(120deg);
            }
            66% {
                transform: translateY(20px) rotate(240deg);
            }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            animation: moveBackground 20s linear infinite;
        }

        @keyframes moveBackground {
            0% {
                transform: translate(0, 0);
            }
            100% {
                transform: translate(20px, 20px);
            }
        }

        .logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .login-header p {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 300;
        }

        .login-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            text-align: center;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fdd;
        }

        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #dfd;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            color: var(--gray);
            z-index: 2;
            transition: var(--transition);
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: var(--transition);
            background: #fff;
            font-weight: 500;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            transform: translateY(-2px);
        }

        .form-control:focus + .input-icon {
            color: var(--primary);
            transform: scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .demo-credentials {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .demo-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .credential-label {
            color: var(--gray);
            font-weight: 500;
        }

        .credential-value {
            color: var(--primary);
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 25px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--gray);
        }

        .feature-icon {
            color: var(--primary);
            font-size: 16px;
        }

        .copyright {
            margin-top: 20px;
            color: var(--gray);
            font-size: 12px;
            opacity: 0.7;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
                margin: 10px;
            }

            .login-header {
                padding: 30px 20px;
            }

            .login-body {
                padding: 30px 20px;
            }

            .logo {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .features {
                grid-template-columns: 1fr;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="bg-bubble bubble-1"></div>
        <div class="bg-bubble bubble-2"></div>
        <div class="bg-bubble bubble-3"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-fingerprint"></i>
            </div>
            <h1>Ziris</h1>
            <p>Système Intelligent de Présence</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <div class="loading"></div>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Adresse Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="votre@email.com" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@entreprise.com'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mot de Passe</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Votre mot de passe" required value="admin123">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Se Connecter
                </button>
            </form>

            <div class="login-footer">
                <div class="demo-credentials">
                    <div class="demo-title">Compte de Démonstration</div>
                    <div class="credential-item">
                        <span class="credential-label">Email:</span>
                        <span class="credential-value">admin@entreprise.com</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Mot de passe:</span>
                        <span class="credential-value">admin123</span>
                    </div>
                </div>

                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt feature-icon"></i>
                        <span>Sécurisé</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bolt feature-icon"></i>
                        <span>Rapide</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt feature-icon"></i>
                        <span>Responsive</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line feature-icon"></i>
                        <span>Analytique</span>
                    </div>
                </div>

                <div class="copyright">
                    &copy; 2025 Ziris. Tous droits réservés.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Animation du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const inputs = form.querySelectorAll('.form-control');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });

            // Effet de chargement sur le bouton
            form.addEventListener('submit', function() {
                const button = document.getElementById('loginButton');
                button.innerHTML = '<div class="loading"></div> Connexion...';
                button.disabled = true;
            });
        });
    </script>
</body>
</html>