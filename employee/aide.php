<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les préférences utilisateur (comme dans dashboard.php)
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        // Créer des préférences par défaut
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, font_size, notifications, accessibility_mode) VALUES (?, 'light', 'medium', 1, 0)");
        $stmt->execute([$user_id]);
        // Re-récupérer les préférences après insertion
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch();
    }
} catch(PDOException $e) {
    // Si la table n'existe pas, utiliser des valeurs par défaut
    $preferences = ['theme' => 'light', 'font_size' => 'medium', 'notifications' => 1, 'accessibility_mode' => 0];
}

// Déterminer le thème actuel pour l'affichage
$currentTheme = $preferences['theme'] ?? 'light';

try {
    $stmt = $pdo->prepare("SELECT u.*, p.nom as poste_nom FROM users u LEFT JOIN postes p ON u.poste_id = p.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    die("Erreur de base de données.");
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aide - Ziris</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    
    <meta name="theme-color" content="#4361ee"/>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="employee-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-fingerprint"></i>Ziris</h1>
                <span class="user-role">Espace Employé</span>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php
                        $initials = '';
                        $names = explode(' ', $user['nom']);
                        foreach ($names as $name) {
                            $initials .= strtoupper(substr($name, 0, 1));
                        }
                        $initials = substr($initials, 0, 2);
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($user['nom']); ?></span>
                        <span class="user-poste"><?php echo htmlspecialchars($user['poste_nom']); ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="employee-nav">
        <div class="nav-content">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de Bord</span>
            </a>
            <a href="presences.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>Mes Présences</span>
            </a>
            <a href="pointage.php" class="nav-item">
                <i class="fas fa-qrcode"></i>
                <span>Pointer</span>
            </a>
            <a href="param.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
            <a href="aide.php" class="nav-item active">
                <i class="fas fa-question-circle"></i>
                <span>Aide</span>
            </a>
        </div>
    </nav>

    <main class="employee-main">
        <div class="page-header">
            <h1>Centre d'Aide</h1>
            <p>Comment utiliser Ziris - Système de Présence Intelligent</p>
        </div>

        <div class="help-content">
            <!-- Section Comment ça marche -->
            <div class="help-section">
                <h2><i class="fas fa-play-circle"></i> Comment ça marche ?</h2>
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Scanner le QR Code</h3>
                            <p>Utilisez l'application camera de votre smartphone pour scanner le QR Code affiché dans vos locaux.</p>
                        </div>
                    </div>
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Pointer votre Arrivée</h3>
                            <p>La page de pointage s'ouvre automatiquement. Cliquez sur "Pointer mon Arrivée".</p>
                        </div>
                    </div>
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Géolocalisation Automatique</h3>
                            <p>Votre position est automatiquement détectée via Google Maps pour vérifier que vous êtes sur votre lieu de travail.</p>
                        </div>
                    </div>
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>Pointer votre Départ</h3>
                            <p>À la fin de votre journée, répétez le processus pour pointer votre départ.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Géolocalisation -->
            <div class="help-section">
                <h2><i class="fas fa-map-marker-alt"></i> Système de Géolocalisation</h2>
                <div class="info-grid">
                    <div class="info-card security">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Sécurité Renforcée</h3>
                        <p>La géolocalisation empêche les pointages frauduleux hors du cadre professionnel.</p>
                    </div>
                    <div class="info-card transparency">
                        <i class="fas fa-eye"></i>
                        <h3>Transparence Totale</h3>
                        <p>Votre position exacte est enregistrée à chaque pointage avec l'adresse complète.</p>
                    </div>
                    <div class="info-card accuracy">
                        <i class="fas fa-crosshairs"></i>
                        <h3>Précision Maximale</h3>
                        <p>Utilisation de Google Maps pour une localisation précise à quelques mètres près.</p>
                    </div>
                </div>

                <div class="warning-card">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <h4>Important : Détection des Pointages Hors Cadre</h4>
                        <p>Le système détecte automatiquement si vous tentez de pointer en dehors de votre lieu de travail. 
                           Les administrateurs reçoivent une alerte en cas de pointage suspect.</p>
                    </div>
                </div>
            </div>

            <!-- Section FAQ -->
            <div class="help-section">
                <h2><i class="fas fa-question-circle"></i> Questions Fréquentes</h2>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Que faire si je ne peux pas scanner le QR Code ?</h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Vous pouvez accéder directement à la page de pointage via le menu "Pointer" dans votre tableau de bord.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Pourquoi la géolocalisation est-elle obligatoire ?</h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Elle garantit l'intégrité du système en vérifiant que les pointages sont effectués depuis le lieu de travail approprié.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Comment sont calculés les retards ?</h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Les retards sont calculés automatiquement par rapport aux heures de référence définies par l'administration.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Puis-je pointer depuis mon domicile ?</h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Non, le système détecte les pointages hors du lieu de travail et les signale automatiquement aux administrateurs.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Contact -->
            <div class="help-section">
                <h2><i class="fas fa-headset"></i> Support Technique</h2>
                <div class="contact-card">
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email de Support</h4>
                                <p>russelfotie777@fmail.com</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Téléphone</h4>
                                <p>+237 697 68 51 92</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Horaires d'Ouverture</h4>
                                <p>Lun - Ven: 8h00 - 18h00</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        /* Variables CSS pour les thèmes */
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border-radius: 12px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            
            /* Variables pour le thème clair */
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-card: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
        }

        /* Thème sombre */
        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-card: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --border-color: #404040;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Header */
        .employee-header {
            background: var(--bg-card);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-left h1 {
            color: var(--primary);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-role {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            margin-left: 10px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .user-poste {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .logout-btn {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 18px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        /* Navigation */
        .employee-nav {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
        }

        .nav-content {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Main Content */
        .employee-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background: var(--bg-primary);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .page-header p {
            color: var(--text-secondary);
        }

        /* Help Content */
        .help-content {
            max-width: 1000px;
            margin: 0 auto;
        }

        .help-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            transition: var(--transition);
        }

        .help-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .help-section h2 {
            margin-bottom: 25px;
            font-size: 24px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Steps Grid */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .step-card {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 25px;
            background: var(--bg-secondary);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .step-card:hover {
            background: var(--bg-card);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .step-number {
            background: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .step-card:hover .step-number {
            transform: scale(1.1);
        }

        .step-content h3 {
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 16px;
        }

        .step-content p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-card {
            padding: 30px 25px;
            border-radius: 10px;
            text-align: center;
            color: white;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition);
        }

        .info-card:hover::before {
            left: 100%;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .info-card.security {
            background: linear-gradient(135deg, #4361ee, #3a56d4);
        }

        .info-card.transparency {
            background: linear-gradient(135deg, #7209b7, #5a08a1);
        }

        .info-card.accuracy {
            background: linear-gradient(135deg, #4cc9f0, #3ab8d9);
        }

        .info-card i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .info-card h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }

        .info-card p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
            line-height: 1.4;
        }

        /* Warning Card */
        .warning-card {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 25px;
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-radius: 10px;
            border-left: 4px solid #ffc107;
            transition: var(--transition);
        }

        .warning-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .warning-card i {
            color: #856404;
            font-size: 24px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .warning-card h4 {
            margin-bottom: 8px;
            color: #856404;
            font-size: 16px;
        }

        .warning-card p {
            color: #856404;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }

        /* FAQ Section */
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .faq-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            transition: var(--transition);
        }

        .faq-item:hover {
            border-color: var(--primary);
        }

        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--bg-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .faq-question:hover {
            background: var(--border-color);
        }

        .faq-question h3 {
            margin: 0;
            font-size: 16px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .faq-question i {
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: var(--bg-card);
        }

        .faq-answer p {
            margin: 0;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .faq-item.active {
            border-color: var(--primary);
            box-shadow: 0 2px 10px rgba(67, 97, 238, 0.1);
        }

        .faq-item.active .faq-answer {
            padding: 20px;
            max-height: 200px;
        }

        .faq-item.active .faq-question {
            background: var(--primary);
            color: white;
        }

        .faq-item.active .faq-question h3 {
            color: white;
        }

        .faq-item.active .faq-question i {
            color: white;
            transform: rotate(180deg);
        }

        /* Contact Section */
        .contact-card {
            background: linear-gradient(135deg, #4361ee, #7209b7);
            color: white;
            border-radius: 15px;
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
        }

        .contact-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }
            100% {
                transform: translate(-20px, -20px) rotate(360deg);
            }
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            position: relative;
            z-index: 2;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }

        .contact-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .contact-item i {
            font-size: 24px;
            opacity: 0.9;
            flex-shrink: 0;
        }

        .contact-item h4 {
            margin-bottom: 5px;
            font-size: 14px;
            opacity: 0.9;
            font-weight: 500;
        }

        .contact-item p {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 15px 20px;
            }

            .employee-main {
                padding: 20px;
            }

            .nav-content {
                overflow-x: auto;
                padding: 0 20px;
            }

            .help-section {
                padding: 20px;
            }

            .steps-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .contact-info {
                grid-template-columns: 1fr;
            }

            .contact-card {
                padding: 30px 20px;
            }

            .step-card {
                padding: 20px;
            }

            .warning-card {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }

            .faq-question {
                padding: 15px;
            }

            .faq-question h3 {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-right {
                width: 100%;
                justify-content: center;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .help-section h2 {
                font-size: 20px;
            }

            .info-card {
                padding: 25px 20px;
            }

            .info-card i {
                font-size: 36px;
            }

            .contact-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>

    <script>
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const item = question.parentElement;
                item.classList.toggle('active');
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.step-card, .info-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>