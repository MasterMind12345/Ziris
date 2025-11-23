<?php
session_start();
require_once 'config/database.php';

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_admin']) {
        header('Location: admin/index.php');
    } else {
        header('Location: employee/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
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
                $error = "Email ou mot de passe incorrect.";
            }
        } catch(PDOException $e) {
            $error = "Erreur de connexion. Veuillez réessayer.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Employé - Batobaye</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- PWA Meta Tags -->
<meta name="theme-color" content="#4361ee"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Batobaye">
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
<meta name="apple-mobile-web-app-title" content="Batobaye">

<!-- CSS existant -->
<link rel="stylesheet" href="../css/employee.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
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
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .logo {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .login-header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .login-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
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
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            color: #6c757d;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }

        .login-footer {
            text-align: center;
            color: #6c757d;
        }

        .register-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link:hover {
            text-decoration: underline;
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
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-fingerprint"></i>
            </div>
            <h1>Batobaye</h1>
            <p>Espace Employé</p>
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

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Adresse Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="votre@email.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mot de Passe</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Se Connecter
                </button>
            </form>

            <div class="login-footer">
                <p>Nouvel employé ? <a href="register.php" class="register-link">Créer un compte</a></p>
            </div>
        </div>
    </div>
</body>
</html>