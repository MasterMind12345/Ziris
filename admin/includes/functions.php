<?php
// Vérifier si l'utilisateur est administrateur
function isAdmin($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user && $user['is_admin'];
}

// Récupérer les statistiques du dashboard
function getDashboardStats() {
    global $pdo;
    
    $stats = [];
    
    try {
        // Total des utilisateurs
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $stats['total_users'] = $stmt->fetch()['total'];
        
        // Présents aujourd'hui
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE()");
        $stmt->execute();
        $stats['present_today'] = $stmt->fetch()['total'];
        
        // Retards aujourd'hui
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE() AND retard_minutes > 0");
        $stmt->execute();
        $stats['late_today'] = $stmt->fetch()['total'];
        
        // Absents aujourd'hui
        $stats['absent_today'] = $stats['total_users'] - $stats['present_today'];
    } catch(PDOException $e) {
        // En cas d'erreur, retourner des valeurs par défaut
        $stats['total_users'] = 0;
        $stats['present_today'] = 0;
        $stats['late_today'] = 0;
        $stats['absent_today'] = 0;
    }
    
    return $stats;
}

// Récupérer les activités récentes - CORRIGÉ
function getRecentActivities($limit = 5) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.nom as user_name, p.date_presence, p.heure_debut_reel as check_in_time
            FROM presences p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        // Retourner un tableau vide en cas d'erreur
        error_log("Erreur getRecentActivities: " . $e->getMessage());
        return [];
    }
}

// Récupérer tous les utilisateurs
function getAllUsers() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT u.*, p.nom as poste_nom 
            FROM users u 
            LEFT JOIN postes p ON u.poste_id = p.id 
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getAllUsers: " . $e->getMessage());
        return [];
    }
}

// Récupérer les présences par période
function getPresencesByPeriod($period = 'daily', $date = null) {
    global $pdo;
    
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    try {
        switch ($period) {
            case 'daily':
                $stmt = $pdo->prepare("
                    SELECT p.*, u.nom, u.email, post.nom as poste
                    FROM presences p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN postes post ON u.poste_id = post.id
                    WHERE p.date_presence = ?
                    ORDER BY p.heure_debut_reel DESC
                ");
                $stmt->execute([$date]);
                break;
                
            case 'weekly':
                $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
                $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
                
                $stmt = $pdo->prepare("
                    SELECT p.*, u.nom, u.email, post.nom as poste
                    FROM presences p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN postes post ON u.poste_id = post.id
                    WHERE p.date_presence BETWEEN ? AND ?
                    ORDER BY p.date_presence DESC, p.heure_debut_reel DESC
                ");
                $stmt->execute([$week_start, $week_end]);
                break;
                
            case 'monthly':
                $month_start = date('Y-m-01', strtotime($date));
                $month_end = date('Y-m-t', strtotime($date));
                
                $stmt = $pdo->prepare("
                    SELECT p.*, u.nom, u.email, post.nom as poste
                    FROM presences p
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN postes post ON u.poste_id = post.id
                    WHERE p.date_presence BETWEEN ? AND ?
                    ORDER BY p.date_presence DESC, p.heure_debut_reel DESC
                ");
                $stmt->execute([$month_start, $month_end]);
                break;
                
            default:
                return [];
        }
        
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getPresencesByPeriod: " . $e->getMessage());
        return [];
    }
}

// Mettre à jour les paramètres système
function updateSystemSettings($heure_debut, $heure_fin) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE parametres_systeme SET heure_debut_normal = ?, heure_fin_normal = ? WHERE id = 1");
        return $stmt->execute([$heure_debut, $heure_fin]);
    } catch(PDOException $e) {
        error_log("Erreur updateSystemSettings: " . $e->getMessage());
        return false;
    }
}

// Récupérer les paramètres système
function getSystemSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM parametres_systeme WHERE id = 1");
        $settings = $stmt->fetch();
        
        // Si aucun paramètre n'existe, créer des valeurs par défaut
        if (!$settings) {
            $settings = [
                'heure_debut_normal' => '08:00:00',
                'heure_fin_normal' => '17:00:00',
                'qr_code_data' => ''
            ];
        }
        
        return $settings;
    } catch(PDOException $e) {
        error_log("Erreur getSystemSettings: " . $e->getMessage());
        return [
            'heure_debut_normal' => '08:00:00',
            'heure_fin_normal' => '17:00:00',
            'qr_code_data' => ''
        ];
    }
}

// Générer le QR Code
// Générer le QR Code
function generateQRCode() {
    global $pdo;
    
    // URL de la page de présence - ADAPTATION AUTOMATIQUE
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // Obtenir le chemin racine du projet
    $script_path = $_SERVER['SCRIPT_NAME'];
    $base_path = dirname(dirname($script_path)); // Remonter de deux niveaux (admin -> batobaye)
    
    // Nettoyer le chemin
    $base_path = rtrim($base_path, '/');
    if ($base_path === '\\' || $base_path === '') {
        $base_path = '';
    } else {
        $base_path = '/' . ltrim($base_path, '/');
    }
    
    $url = $protocol . "://" . $host . $base_path . "/markpresence.php";
    
    try {
        // Vérifier si la table parametres_systeme existe
        $tableExists = $pdo->query("SHOW TABLES LIKE 'parametres_systeme'")->rowCount() > 0;
        
        if ($tableExists) {
            // Mettre à jour la base de données avec les données du QR Code
            $stmt = $pdo->prepare("UPDATE parametres_systeme SET qr_code_data = ? WHERE id = 1");
            $stmt->execute([$url]);
        }
        
        return $url;
    } catch(PDOException $e) {
        error_log("Erreur generateQRCode: " . $e->getMessage());
        return $url; // Retourner l'URL même en cas d'erreur
    }
}

// Récupérer les logs de présence
function getPresenceLogs($limit = 50) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.nom as user_name, u.email, post.nom as poste
            FROM presences p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN postes post ON u.poste_id = post.id
            ORDER BY p.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getPresenceLogs: " . $e->getMessage());
        return [];
    }
}

// Vérifier si les tables existent
function checkDatabaseTables() {
    global $pdo;
    
    try {
        $tables = ['users', 'postes', 'presences', 'parametres_systeme'];
        $missing_tables = [];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missing_tables[] = $table;
            }
        }
        
        return $missing_tables;
    } catch(PDOException $e) {
        error_log("Erreur checkDatabaseTables: " . $e->getMessage());
        return $tables; // Toutes les tables sont considérées comme manquantes en cas d'erreur
    }
}
?>