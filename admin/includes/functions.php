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


// Récupérer les absences du jour avec retard en heures
function getAbsencesDuJour() {
    global $pdo;
    
    try {
        $date_aujourdhui = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.nom, u.email, p.nom as poste, 
                   (SELECT MAX(date_presence) FROM presences WHERE user_id = u.id) as derniere_presence,
                   ROUND(COALESCE(AVG(pr.retard_minutes), 0) / 60, 1) as retard_moyen_heures,
                   CASE 
                     WHEN EXISTS (SELECT 1 FROM presences WHERE user_id = u.id AND date_presence = ?) THEN 0
                     ELSE 1 
                   END as est_absent
            FROM users u
            LEFT JOIN postes p ON u.poste_id = p.id
            LEFT JOIN presences pr ON u.id = pr.user_id
            WHERE u.is_admin = 0
            GROUP BY u.id, u.nom, u.email, p.nom
            HAVING est_absent = 1
            ORDER BY u.nom
        ");
        $stmt->execute([$date_aujourdhui]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getAbsencesDuJour: " . $e->getMessage());
        return [];
    }
}

// Récupérer les statistiques d'absences avec retard en heures
function getStatsAbsences() {
    global $pdo;
    
    $stats = [
        'absences_aujourdhui' => 0,
        'absences_semaine' => 0,
        'taux_absence' => 0,
        'employes_total' => 0,
        'retard_moyen_heures' => 0
    ];
    
    try {
        // Total des employés
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
        $stats['employes_total'] = $stmt->fetch()['total'];
        
        // Absences aujourd'hui
        $date_aujourdhui = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as absences 
            FROM users u 
            WHERE u.is_admin = 0 
            AND NOT EXISTS (
                SELECT 1 FROM presences p 
                WHERE p.user_id = u.id AND p.date_presence = ?
            )
        ");
        $stmt->execute([$date_aujourdhui]);
        $stats['absences_aujourdhui'] = $stmt->fetch()['absences'];
        
        // Absences cette semaine
        $debut_semaine = date('Y-m-d', strtotime('monday this week'));
        $fin_semaine = date('Y-m-d', strtotime('sunday this week'));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT u.id) as absences
            FROM users u
            WHERE u.is_admin = 0
            AND NOT EXISTS (
                SELECT 1 FROM presences p 
                WHERE p.user_id = u.id AND p.date_presence BETWEEN ? AND ?
            )
        ");
        $stmt->execute([$debut_semaine, $fin_semaine]);
        $stats['absences_semaine'] = $stmt->fetch()['absences'];
        
        // Taux d'absence mensuel (approximatif)
        if ($stats['employes_total'] > 0) {
            $jours_ouvres = 22; // Estimation
            $total_presences_attendues = $stats['employes_total'] * $jours_ouvres;
            
            // Compter les présences du mois
            $debut_mois = date('Y-m-01');
            $fin_mois = date('Y-m-t');
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as presences_reelles
                FROM presences p
                JOIN users u ON p.user_id = u.id
                WHERE u.is_admin = 0
                AND p.date_presence BETWEEN ? AND ?
            ");
            $stmt->execute([$debut_mois, $fin_mois]);
            $presences_reelles = $stmt->fetch()['presences_reelles'];
            
            if ($total_presences_attendues > 0) {
                $stats['taux_absence'] = round((1 - ($presences_reelles / $total_presences_attendues)) * 100, 1);
            }
        }
        
        // Retard moyen en heures
        $stmt = $pdo->query("
            SELECT ROUND(AVG(retard_minutes) / 60, 1) as retard_moyen_heures
            FROM presences 
            WHERE date_presence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND retard_minutes > 0
        ");
        $result = $stmt->fetch();
        $stats['retard_moyen_heures'] = $result['retard_moyen_heures'] ?: 0;
        
    } catch(PDOException $e) {
        error_log("Erreur getStatsAbsences: " . $e->getMessage());
    }
    
    return $stats;
}

// Récupérer les employés les plus ponctuels avec retard en heures
function getEmployesPonctuels($limit = 5) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nom, p.nom as poste,
                   ROUND((COUNT(pr.id) / 22) * 100, 1) as taux_presence, -- 22 jours ouvrables estimés
                   ROUND(COALESCE(AVG(pr.retard_minutes), 0) / 60, 1) as retard_moyen_heures
            FROM users u
            LEFT JOIN postes p ON u.poste_id = p.id
            LEFT JOIN presences pr ON u.id = pr.user_id AND pr.date_presence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            WHERE u.is_admin = 0
            GROUP BY u.id, u.nom, p.nom
            ORDER BY taux_presence DESC, retard_moyen_heures ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getEmployesPonctuels: " . $e->getMessage());
        return [];
    }
}

// Récupérer les données pour le calendrier avec retard en heures
function getPresencesPourCalendrier() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                u.nom as title,
                p.date_presence as start,
                p.date_presence as end,
                CASE 
                    WHEN p.retard_minutes > 0 THEN 'retard'
                    ELSE 'presence'
                END as type,
                p.heure_debut_reel as heure,
                p.retard_minutes as retard,
                CASE 
                    WHEN p.retard_minutes > 0 THEN 'fas fa-clock'
                    ELSE 'fas fa-check'
                END as icon
            FROM presences p
            JOIN users u ON p.user_id = u.id
            WHERE p.date_presence >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            UNION ALL
            SELECT 
                u.nom as title,
                d.date as start,
                d.date as end,
                'absence' as type,
                NULL as heure,
                NULL as retard,
                'fas fa-times' as icon
            FROM users u
            CROSS JOIN (
                SELECT DISTINCT date_presence as date FROM presences 
                WHERE date_presence >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            ) d
            WHERE NOT EXISTS (
                SELECT 1 FROM presences p 
                WHERE p.user_id = u.id AND p.date_presence = d.date
            )
            AND u.is_admin = 0
            ORDER BY start DESC
            LIMIT 500
        ");
        
        $events = $stmt->fetchAll();
        
        // Formater les événements pour FullCalendar
        $formatted_events = [];
        foreach ($events as $event) {
            $formatted_events[] = [
                'title' => $event['title'],
                'start' => $event['start'],
                'end' => $event['end'],
                'extendedProps' => [
                    'type' => $event['type'],
                    'heure' => $event['heure'],
                    'retard' => $event['retard'],
                    'icon' => $event['icon']
                ],
                'backgroundColor' => $event['type'] === 'presence' ? '#d4edda' : 
                                   ($event['type'] === 'absence' ? '#f8d7da' : '#fff3cd'),
                'borderColor' => $event['type'] === 'presence' ? '#c3e6cb' : 
                               ($event['type'] === 'absence' ? '#f5c6cb' : '#ffeaa7'),
                'textColor' => $event['type'] === 'presence' ? '#155724' : 
                             ($event['type'] === 'absence' ? '#721c24' : '#856404')
            ];
        }
        
        return $formatted_events;
    } catch(PDOException $e) {
        error_log("Erreur getPresencesPourCalendrier: " . $e->getMessage());
        return [];
    }
}

// Fonction utilitaire pour générer des couleurs aléatoires
function getRandomColor($seed) {
    $colors = [
        '#4361ee', '#3a56d4', '#7209b7', '#4cc9f0', '#f72585',
        '#e63946', '#2a9d8f', '#e9c46a', '#f4a261', '#e76f51'
    ];
    return $colors[$seed % count($colors)];
}

// Fonction utilitaire pour obtenir les initiales
function getInitials($nom) {
    $names = explode(' ', $nom);
    $initials = '';
    foreach ($names as $name) {
        $initials .= strtoupper(substr($name, 0, 1));
    }
    return substr($initials, 0, 2);
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