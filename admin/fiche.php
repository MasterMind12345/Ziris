
<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les préférences utilisateur
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch();
    
    if (!$preferences) {
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, font_size, notifications) VALUES (?, 'light', 'medium', 1)");
        $stmt->execute([$_SESSION['user_id']]);
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $preferences = $stmt->fetch();
    }
} catch(PDOException $e) {
    $preferences = ['theme' => 'light', 'font_size' => 'medium', 'notifications' => 1];
}

$currentTheme = $preferences['theme'] ?? 'light';

// Variables
$message = '';
$message_type = '';
$selected_user = null;
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_user_id = $_GET['user_id'] ?? null;
$fiche_data = null;
$calendrier_data = [];

// Récupérer tous les utilisateurs pour le select
try {
    $stmt = $pdo->query("
        SELECT u.id, u.nom, u.email, p.nom as poste_nom 
        FROM users u 
        LEFT JOIN postes p ON u.poste_id = p.id 
        WHERE u.is_admin = 0 
        ORDER BY u.nom
    ");
    $all_users = $stmt->fetchAll();
} catch(PDOException $e) {
    $all_users = [];
}

// Récupérer les informations de l'entreprise
try {
    $stmt = $pdo->query("SELECT * FROM entreprise_infos LIMIT 1");
    $entreprise_info = $stmt->fetch();
} catch(PDOException $e) {
    $entreprise_info = null;
}

// Traitement du formulaire d'édition des infos entreprise
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'update_entreprise_info') {
            $data = [
                'nom' => $_POST['nom'] ?? '',
                'adresse' => $_POST['adresse'] ?? '',
                'ville' => $_POST['ville'] ?? '',
                'code_postal' => $_POST['code_postal'] ?? '',
                'pays' => $_POST['pays'] ?? 'Cameroun',
                'telephone' => $_POST['telephone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'site_web' => $_POST['site_web'] ?? '',
                'numero_fiscal' => $_POST['numero_fiscal'] ?? '',
                'numero_cnps' => $_POST['numero_cnps'] ?? '',
                'capital_social' => $_POST['capital_social'] ?? '',
                'registre_commerce' => $_POST['registre_commerce'] ?? '',
                'conditions_paiement' => $_POST['conditions_paiement'] ?? '',
                'mentions_legales' => $_POST['mentions_legales'] ?? ''
            ];
            
            // Gestion du logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/logos/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $fileName = 'logo_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $filePath)) {
                    $data['logo'] = 'uploads/logos/' . $fileName;
                    
                    // Supprimer l'ancien logo s'il existe
                    if ($entreprise_info && $entreprise_info['logo']) {
                        $oldPath = '../' . $entreprise_info['logo'];
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                }
            }
            
            // Gestion de la signature direction upload
            if (isset($_FILES['signature_direction']) && $_FILES['signature_direction']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/signatures/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['signature_direction']['name'], PATHINFO_EXTENSION);
                $fileName = 'signature_dir_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['signature_direction']['tmp_name'], $filePath)) {
                    $data['signature_direction'] = 'uploads/signatures/' . $fileName;
                    
                    // Supprimer l'ancienne signature s'il existe
                    if ($entreprise_info && $entreprise_info['signature_direction']) {
                        $oldPath = '../' . $entreprise_info['signature_direction'];
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                }
            }
            
            // Gestion de la signature RH upload
            if (isset($_FILES['signature_rh']) && $_FILES['signature_rh']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/signatures/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['signature_rh']['name'], PATHINFO_EXTENSION);
                $fileName = 'signature_rh_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['signature_rh']['tmp_name'], $filePath)) {
                    $data['signature_rh'] = 'uploads/signatures/' . $fileName;
                    
                    // Supprimer l'ancienne signature s'il existe
                    if ($entreprise_info && $entreprise_info['signature_rh']) {
                        $oldPath = '../' . $entreprise_info['signature_rh'];
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                }
            }
            
            // Vérifier si une entrée existe déjà
            if ($entreprise_info) {
                // Mise à jour
                $setClause = [];
                $params = [];
                
                foreach ($data as $key => $value) {
                    if (!empty($value) || $key === 'logo' || $key === 'signature_direction' || $key === 'signature_rh') {
                        $setClause[] = "`$key` = ?";
                        $params[] = $value;
                    }
                }
                
                $params[] = $entreprise_info['id'];
                $sql = "UPDATE entreprise_infos SET " . implode(', ', $setClause) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insertion
                $columns = [];
                $placeholders = [];
                $params = [];
                
                foreach ($data as $key => $value) {
                    $columns[] = "`$key`";
                    $placeholders[] = "?";
                    $params[] = $value;
                }
                
                $sql = "INSERT INTO entreprise_infos (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            // Récupérer les nouvelles infos
            $stmt = $pdo->query("SELECT * FROM entreprise_infos LIMIT 1");
            $entreprise_info = $stmt->fetch();
            
            $message = "Informations de l'entreprise mises à jour avec succès!";
            $message_type = 'success';
        }
    } catch(PDOException $e) {
        $message = "Erreur: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Si un utilisateur est sélectionné, récupérer ses données pour la fiche de paie
if ($selected_user_id && $selected_month) {
    $year = date('Y', strtotime($selected_month));
    $month = date('m', strtotime($selected_month));
    
    try {
        // Récupérer les informations de l'utilisateur
        $stmt = $pdo->prepare("
            SELECT u.*, p.nom as poste_nom, p.description as poste_description,
                   ps.salaire_brut_mensuel, ps.salaire_horaire, ps.jours_travail_mois, ps.heures_travail_jour
            FROM users u
            LEFT JOIN postes p ON u.poste_id = p.id
            LEFT JOIN parametres_salaire ps ON p.id = ps.poste_id
            WHERE u.id = ? AND u.is_admin = 0
        ");
        $stmt->execute([$selected_user_id]);
        $selected_user = $stmt->fetch();
        
        if ($selected_user) {
            // Récupérer les heures de début/fin normales
            $stmt = $pdo->query("SELECT heure_debut_normal, heure_fin_normal FROM parametres_systeme LIMIT 1");
            $system_params = $stmt->fetch();
            $heure_debut_normal = $system_params['heure_debut_normal'] ?? '08:30:00';
            $heure_fin_normal = $system_params['heure_fin_normal'] ?? '17:30:00';
            
            // Calculer les jours du mois
            $month_start = date('Y-m-01', strtotime($selected_month));
            $month_end = date('Y-m-t', strtotime($selected_month));
            $month_name = date('F Y', strtotime($selected_month));
            $total_days_in_month = date('t', strtotime($selected_month));
            
            // Récupérer les présences pour le mois
            $stmt = $pdo->prepare("
                SELECT 
                    date_presence,
                    heure_debut_reel,
                    heure_fin_reel,
                    retard_minutes,
                    heure_pause_debut,
                    heure_pause_fin
                FROM presences 
                WHERE user_id = ? 
                    AND date_presence BETWEEN ? AND ?
                ORDER BY date_presence
            ");
            $stmt->execute([$selected_user_id, $month_start, $month_end]);
            $presences = $stmt->fetchAll();
            
            // Créer un tableau pour le calendrier
            $calendrier_data = [];
            for ($day = 1; $day <= $total_days_in_month; $day++) {
                $current_date = date('Y-m-d', strtotime($selected_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
                $day_of_week = date('N', strtotime($current_date)); // 1=lundi, 7=dimanche
                
                $presence_info = null;
                foreach ($presences as $presence) {
                    if ($presence['date_presence'] == $current_date) {
                        $presence_info = $presence;
                        break;
                    }
                }
                
                // Déterminer le type de jour
                $day_type = 'absence';
                $day_color = '#f8d7da'; // Rouge clair pour absence
                $day_icon = 'fas fa-times';
                $day_tooltip = 'Absent';
                
                if ($presence_info) {
                    if ($presence_info['retard_minutes'] > 0) {
                        $day_type = 'retard';
                        $day_color = '#fff3cd'; // Jaune clair pour retard
                        $day_icon = 'fas fa-clock';
                        $day_tooltip = 'Présent avec retard: ' . floor($presence_info['retard_minutes'] / 60) . 'h' . ($presence_info['retard_minutes'] % 60) . 'min';
                    } else {
                        $day_type = 'presence';
                        $day_color = '#d4edda'; // Vert clair pour présence
                        $day_icon = 'fas fa-check';
                        $day_tooltip = 'Présent: ' . substr($presence_info['heure_debut_reel'], 0, 5) . ' - ' . ($presence_info['heure_fin_reel'] ? substr($presence_info['heure_fin_reel'], 0, 5) : '--:--');
                    }
                } elseif ($day_of_week >= 6) { // Samedi (6) ou dimanche (7)
                    $day_type = 'weekend';
                    $day_color = '#e2e3e5'; // Gris pour weekend
                    $day_icon = 'fas fa-home';
                    $day_tooltip = 'Weekend';
                }
                
                // Calculer les heures travaillées pour ce jour
                $hours_worked = 0;
                if ($presence_info && $presence_info['heure_debut_reel'] && $presence_info['heure_fin_reel']) {
                    $start_time = strtotime($presence_info['heure_debut_reel']);
                    $end_time = strtotime($presence_info['heure_fin_reel']);
                    
                    // Ajuster l'heure de début si avant l'heure normale
                    $normal_start = strtotime($heure_debut_normal);
                    if ($start_time < $normal_start) {
                        $start_time = $normal_start;
                    }
                    
                    // Ajuster l'heure de fin si null
                    if (!$presence_info['heure_fin_reel']) {
                        $end_time = strtotime($heure_fin_normal);
                    }
                    
                    $duration_seconds = $end_time - $start_time;
                    $duration_hours = $duration_seconds / 3600;
                    
                    // Soustraire 1h pour la pause
                    $duration_hours -= 1;
                    
                    // Limiter à 8h maximum
                    if ($duration_hours > 8) {
                        $duration_hours = 8;
                    }
                    
                    // Ignorer les durées négatives
                    if ($duration_hours > 0) {
                        $hours_worked = $duration_hours;
                    }
                }
                
                $calendrier_data[] = [
                    'day' => $day,
                    'date' => $current_date,
                    'day_of_week' => $day_of_week,
                    'type' => $day_type,
                    'color' => $day_color,
                    'icon' => $day_icon,
                    'tooltip' => $day_tooltip,
                    'hours_worked' => $hours_worked,
                    'retard_minutes' => $presence_info['retard_minutes'] ?? 0,
                    'heure_debut' => $presence_info['heure_debut_reel'] ?? null,
                    'heure_fin' => $presence_info['heure_fin_reel'] ?? null
                ];
            }
            
            // Calculer les statistiques du mois
            $jours_presents = array_filter($calendrier_data, function($day) {
                return $day['type'] === 'presence' || $day['type'] === 'retard';
            });
            
            $jours_absents = array_filter($calendrier_data, function($day) {
                return $day['type'] === 'absence' && $day['day_of_week'] < 6;
            });
            
            $jours_retard = array_filter($calendrier_data, function($day) {
                return $day['type'] === 'retard';
            });
            
            $total_heures_travaillees = array_sum(array_column($calendrier_data, 'hours_worked'));
            $total_retard_minutes = array_sum(array_column($calendrier_data, 'retard_minutes'));
            
            // Calculer le salaire brut
            $salaire_brut = 0;
            if ($selected_user['salaire_horaire'] && $total_heures_travaillees > 0) {
                $salaire_brut = $total_heures_travaillees * $selected_user['salaire_horaire'];
            }
            
            // Vérifier si un paiement a déjà été effectué
            $stmt = $pdo->prepare("
                SELECT * FROM salaires_paiements 
                WHERE user_id = ? AND mois = ? AND annee = ?
            ");
            $stmt->execute([$selected_user_id, $month, $year]);
            $paiement_info = $stmt->fetch();
            
            // Construire les données de la fiche de paie
            $fiche_data = [
                'employe' => [
                    'nom' => $selected_user['nom'],
                    'email' => $selected_user['email'],
                    'poste' => $selected_user['poste_nom'],
                    'poste_description' => $selected_user['poste_description'] ?? '',
                    'date_embauche' => date('d/m/Y', strtotime($selected_user['created_at']))
                ],
                'periode' => [
                    'mois' => $month_name,
                    'debut' => $month_start,
                    'fin' => $month_end,
                    'jours_ouvrables' => count($jours_presents) + count($jours_absents)
                ],
                'presences' => [
                    'jours_presents' => count($jours_presents),
                    'jours_absents' => count($jours_absents),
                    'jours_retard' => count($jours_retard),
                    'heures_travaillees' => round($total_heures_travaillees, 2),
                    'retard_total' => $total_retard_minutes,
                    'retard_moyen' => count($jours_retard) > 0 ? round($total_retard_minutes / count($jours_retard)) : 0
                ],
                'remuneration' => [
                    'salaire_horaire' => $selected_user['salaire_horaire'] ?? 0,
                    'salaire_base' => $selected_user['salaire_brut_mensuel'] ?? 0,
                    'heures_supp' => max(0, $total_heures_travaillees - (($selected_user['jours_travail_mois'] ?? 22) * ($selected_user['heures_travail_jour'] ?? 8))),
                    'salaire_brut' => round($salaire_brut, 2),
                    'retenues' => [],
                    'avantages' => [],
                    'net_a_payer' => round($salaire_brut, 2)
                ],
                'paiement' => $paiement_info ?? null,
                'calendrier' => $calendrier_data
            ];
        }
    } catch(PDOException $e) {
        $message = "Erreur lors de la récupération des données: " . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiches de Paie - Ziris Admin</title>
    
    <!-- Style CSS principal -->
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="/manifest.json">

    <!-- CSS personnalisé -->
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-card: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 12px;
        }

        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-card: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --border-color: #404040;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .main-content {
            background-color: var(--bg-primary);
            padding: 20px;
            min-height: calc(100vh - 70px);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            padding: 25px;
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 16px;
            margin: 0;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .card-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h2 {
            font-size: 22px;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: var(--primary);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #0da271);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #e58a0a);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }

        /* Calendrier Style */
        .calendrier-mois {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 20px;
        }

        .calendrier-header {
            text-align: center;
            font-weight: 600;
            padding: 10px;
            background: var(--bg-secondary);
            border-radius: 6px;
            font-size: 12px;
            text-transform: uppercase;
        }

        .calendrier-jour {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .calendrier-jour:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .calendrier-jour.presence {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-color: #b1dfbb;
        }

        .calendrier-jour.retard {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-color: #f5c6cb;
        }

        .calendrier-jour.absence {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border-color: #f1b0b7;
        }

        .calendrier-jour.weekend {
            background: linear-gradient(135deg, #e2e3e5, #d6d8db);
            border-color: #c8cbcf;
        }

        .calendrier-jour .jour-numero {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .calendrier-jour .jour-icon {
            font-size: 12px;
            margin-bottom: 2px;
        }

        .calendrier-jour .jour-heures {
            font-size: 10px;
            font-weight: 600;
            background: rgba(0,0,0,0.1);
            padding: 2px 4px;
            border-radius: 3px;
            margin-top: 2px;
        }

        /* Fiche de paie preview */
        .fiche-paie-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 30px;
            animation: slideIn 0.6s ease;
        }

        .fiche-paie-header {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 30px;
            position: relative;
        }

        .fiche-paie-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 200" opacity="0.1"><path d="M0,100 C150,200 350,0 500,100 C650,200 850,0 1000,100 L1000,200 L0,200 Z"/></svg>');
            background-size: cover;
        }

        .fiche-paie-logo {
            max-height: 60px;
            margin-bottom: 20px;
        }

        .fiche-paie-title {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .fiche-paie-subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .fiche-paie-body {
            padding: 40px;
        }

        .fiche-section {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .fiche-section-title {
            font-size: 20px;
            font-weight: 700;
            color: #4361ee;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .fiche-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .fiche-info-item {
            margin-bottom: 15px;
        }

        .fiche-info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .fiche-info-value {
            font-size: 16px;
            font-weight: 600;
            color: #212529;
        }

        .table-fiche {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table-fiche th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .table-fiche td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .table-fiche tr:last-child td {
            border-bottom: none;
        }

        .table-fiche .total-row {
            background: #f8f9fa;
            font-weight: 700;
            font-size: 16px;
        }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 2px solid #dee2e6;
        }

        .signature-block {
            text-align: center;
        }

        .signature-line {
            width: 200px;
            height: 1px;
            background: #000;
            margin: 40px auto 10px;
        }

        .signature-name {
            font-weight: 600;
            margin-top: 5px;
        }

        .signature-title {
            font-size: 12px;
            color: #6c757d;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .badge-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Animations */
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .calendrier-mois {
                grid-template-columns: repeat(7, 1fr);
                gap: 4px;
            }
            
            .calendrier-jour .jour-numero {
                font-size: 14px;
            }
            
            .fiche-paie-body {
                padding: 20px;
            }
            
            .signature-section {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }

        /* Loading State */
        .loading {
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30px;
            height: 30px;
            margin: -15px 0 0 -15px;
            border: 3px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Fiches de Paie</h1>
            <p>Générez et téléchargez des fiches de paie professionnelles pour vos employés</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'error' ? 'error' : 'warning'); ?>" 
                 style="padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid;">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : ($message_type === 'error' ? 'exclamation' : 'info'); ?>-circle"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Formulaire de sélection -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-search"></i> Sélection</h2>
                </div>
                
                <form method="GET" action="fiche.php" id="selectionForm">
                    <div class="form-group">
                        <label for="user_id">Employé</label>
                        <select id="user_id" name="user_id" class="form-control" required>
                            <option value="">Sélectionnez un employé...</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $selected_user_id == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nom']); ?> - <?php echo htmlspecialchars($user['poste_nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="month">Mois</label>
                        <input type="month" id="month" name="month" class="form-control" 
                               value="<?php echo $selected_month; ?>" required>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Visualiser la fiche
                        </button>
                        <button type="button" class="btn btn-warning" onclick="document.getElementById('selectionForm').reset()">
                            <i class="fas fa-times"></i> Réinitialiser
                        </button>
                    </div>
                </form>
                
                <?php if ($selected_user && $fiche_data): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <button class="btn btn-success" onclick="generatePDF()">
                            <i class="fas fa-file-pdf"></i> Télécharger en PDF
                        </button>
                        <button class="btn btn-warning" onclick="printFiche()">
                            <i class="fas fa-print"></i> Imprimer la fiche
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Affichage principal -->
            <div>
                <?php if ($selected_user && $fiche_data): ?>
                <!-- Résumé rapide -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i class="fas fa-user-circle"></i> Récapitulatif</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <span style="font-size: 12px; color: var(--text-secondary);">Employé</span>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo $fiche_data['employe']['nom']; ?></div>
                        </div>
                        <div>
                            <span style="font-size: 12px; color: var(--text-secondary);">Poste</span>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo $fiche_data['employe']['poste']; ?></div>
                        </div>
                        <div>
                            <span style="font-size: 12px; color: var(--text-secondary);">Période</span>
                            <div style="font-size: 18px; font-weight: 700;"><?php echo $fiche_data['periode']['mois']; ?></div>
                        </div>
                        <div>
                            <span style="font-size: 12px; color: var(--text-secondary);">Salaire Brut</span>
                            <div style="font-size: 18px; font-weight: 700; color: var(--success);">
                                <?php echo number_format($fiche_data['remuneration']['salaire_brut'], 0, ',', ' '); ?> FCFA
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendrier du mois -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-alt"></i> Calendrier du mois</h2>
                        <div style="margin-top: 10px; display: flex; gap: 15px; font-size: 12px;">
                            <span><i class="fas fa-square" style="color: #d4edda;"></i> Présent</span>
                            <span><i class="fas fa-square" style="color: #fff3cd;"></i> Retard</span>
                            <span><i class="fas fa-square" style="color: #f8d7da;"></i> Absent</span>
                            <span><i class="fas fa-square" style="color: #e2e3e5;"></i> Weekend</span>
                        </div>
                    </div>
                    
                    <div class="calendrier-mois">
                        <!-- En-têtes des jours -->
                        <div class="calendrier-header">Lun</div>
                        <div class="calendrier-header">Mar</div>
                        <div class="calendrier-header">Mer</div>
                        <div class="calendrier-header">Jeu</div>
                        <div class="calendrier-header">Ven</div>
                        <div class="calendrier-header">Sam</div>
                        <div class="calendrier-header">Dim</div>
                        
                        <!-- Jours vides avant le premier du mois -->
                        <?php
                        $first_day_of_month = date('N', strtotime($selected_month . '-01'));
                        for ($i = 1; $i < $first_day_of_month; $i++) {
                            echo '<div class="calendrier-jour" style="visibility: hidden;"></div>';
                        }
                        
                        // Jours du mois
                        foreach ($calendrier_data as $jour) {
                            $hours_display = $jour['hours_worked'] > 0 ? number_format($jour['hours_worked'], 1) . 'h' : '';
                            echo '
                            <div class="calendrier-jour ' . $jour['type'] . '" title="' . htmlspecialchars($jour['tooltip']) . '">
                                <div class="jour-numero">' . $jour['day'] . '</div>
                                <div class="jour-icon"><i class="' . $jour['icon'] . '"></i></div>';
                            
                            if ($hours_display) {
                                echo '<div class="jour-heures">' . $hours_display . '</div>';
                            }
                            
                            if ($jour['retard_minutes'] > 0) {
                                $retard_h = floor($jour['retard_minutes'] / 60);
                                $retard_m = $jour['retard_minutes'] % 60;
                                echo '<div class="jour-heures" style="background: rgba(255,193,7,0.3); margin-top: 2px; font-size: 8px;">+'.$retard_h.'h'.$retard_m.'</div>';
                            }
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <!-- Statistiques du calendrier -->
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color);">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: var(--success);">
                                    <?php echo $fiche_data['presences']['jours_presents']; ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">Jours présents</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: var(--danger);">
                                    <?php echo $fiche_data['presences']['jours_absents']; ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">Jours absents</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: var(--warning);">
                                    <?php echo $fiche_data['presences']['jours_retard']; ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">Jours en retard</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 24px; font-weight: 700; color: var(--primary);">
                                    <?php echo number_format($fiche_data['presences']['heures_travaillees'], 1); ?>h
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">Heures travaillées</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fiche de paie preview -->
        <?php if ($selected_user && $fiche_data): ?>
        <div class="fiche-paie-container" id="fichePaie">
            <!-- En-tête -->
            <div class="fiche-paie-header">
                <?php if ($entreprise_info && $entreprise_info['logo']): ?>
                <img src="../<?php echo htmlspecialchars($entreprise_info['logo']); ?>" alt="Logo" class="fiche-paie-logo">
                <?php endif; ?>
                
                <div style="position: relative; z-index: 1;">
                    <h1 class="fiche-paie-title">FICHE DE PAIE</h1>
                    <div class="fiche-paie-subtitle">Période : <?php echo $fiche_data['periode']['mois']; ?></div>
                    
                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                        <div>
                            <div style="font-size: 14px; opacity: 0.9;">Employeur</div>
                            <div style="font-size: 18px; font-weight: 600;">
                                <?php echo $entreprise_info ? htmlspecialchars($entreprise_info['nom']) : 'MNLV Africa SARL'; ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 14px; opacity: 0.9;">Employé</div>
                            <div style="font-size: 18px; font-weight: 600;"><?php echo $fiche_data['employe']['nom']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Corps -->
            <div class="fiche-paie-body">
                <!-- Informations employeur/employé -->
                <div class="fiche-section">
                    <h3 class="fiche-section-title"><i class="fas fa-building"></i> Informations employeur</h3>
                    <div class="fiche-grid">
                        <?php if ($entreprise_info): ?>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Raison sociale</div>
                            <div class="fiche-info-value"><?php echo htmlspecialchars($entreprise_info['nom']); ?></div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Adresse</div>
                            <div class="fiche-info-value">
                                <?php echo htmlspecialchars($entreprise_info['adresse'] ?? ''); ?><br>
                                <?php echo htmlspecialchars($entreprise_info['code_postal'] ?? '') . ' ' . htmlspecialchars($entreprise_info['ville'] ?? ''); ?>
                            </div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Numéro fiscal</div>
                            <div class="fiche-info-value"><?php echo htmlspecialchars($entreprise_info['numero_fiscal'] ?? ''); ?></div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">CNPS</div>
                            <div class="fiche-info-value"><?php echo htmlspecialchars($entreprise_info['numero_cnps'] ?? ''); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="fiche-section-title" style="margin-top: 30px;"><i class="fas fa-user-tie"></i> Informations employé</h3>
                    <div class="fiche-grid">
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Nom complet</div>
                            <div class="fiche-info-value"><?php echo $fiche_data['employe']['nom']; ?></div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Poste</div>
                            <div class="fiche-info-value"><?php echo $fiche_data['employe']['poste']; ?></div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Date d'embauche</div>
                            <div class="fiche-info-value"><?php echo $fiche_data['employe']['date_embauche']; ?></div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Période de paie</div>
                            <div class="fiche-info-value"><?php echo $fiche_data['periode']['mois']; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Détails de présence -->
                <div class="fiche-section">
                    <h3 class="fiche-section-title"><i class="fas fa-calendar-check"></i> Détails de présence</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div>
                            <div class="fiche-info-label">Jours présents</div>
                            <div class="fiche-info-value" style="font-size: 24px; color: var(--success);">
                                <?php echo $fiche_data['presences']['jours_presents']; ?>
                            </div>
                        </div>
                        <div>
                            <div class="fiche-info-label">Jours absents</div>
                            <div class="fiche-info-value" style="font-size: 24px; color: var(--danger);">
                                <?php echo $fiche_data['presences']['jours_absents']; ?>
                            </div>
                        </div>
                        <div>
                            <div class="fiche-info-label">Heures travaillées</div>
                            <div class="fiche-info-value" style="font-size: 24px; color: var(--primary);">
                                <?php echo number_format($fiche_data['presences']['heures_travaillees'], 1); ?> heures
                            </div>
                        </div>
                        <div>
                            <div class="fiche-info-label">Retard total</div>
                            <div class="fiche-info-value" style="font-size: 24px; color: var(--warning);">
                                <?php echo floor($fiche_data['presences']['retard_total'] / 60); ?>h<?php echo $fiche_data['presences']['retard_total'] % 60; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Rémunération -->
                <div class="fiche-section">
                    <h3 class="fiche-section-title"><i class="fas fa-money-bill-wave"></i> Rémunération</h3>
                    
                    <table class="table-fiche">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Taux</th>
                                <th>Quantité</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Salaire de base</td>
                                <td><?php echo number_format($fiche_data['remuneration']['salaire_horaire'], 0, ',', ' '); ?> FCFA/h</td>
                                <td><?php echo number_format($fiche_data['remuneration']['heures_travaillees'], 1); ?> heures</td>
                                <td style="font-weight: 600;">
                                    <?php echo number_format($fiche_data['remuneration']['salaire_brut'], 0, ',', ' '); ?> FCFA
                                </td>
                            </tr>
                            
                            <?php if ($fiche_data['remuneration']['heures_supp'] > 0): ?>
                            <tr>
                                <td>Heures supplémentaires (25%)</td>
                                <td><?php echo number_format($fiche_data['remuneration']['salaire_horaire'] * 1.25, 0, ',', ' '); ?> FCFA/h</td>
                                <td><?php echo number_format($fiche_data['remuneration']['heures_supp'], 1); ?> heures</td>
                                <td style="font-weight: 600; color: var(--success);">
                                    <?php echo number_format($fiche_data['remuneration']['salaire_horaire'] * 1.25 * $fiche_data['remuneration']['heures_supp'], 0, ',', ' '); ?> FCFA
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr class="total-row">
                                <td colspan="3" style="text-align: right;"><strong>Total brut à payer</strong></td>
                                <td style="font-weight: 700; font-size: 18px; color: var(--success);">
                                    <?php echo number_format($fiche_data['remuneration']['net_a_payer'], 0, ',', ' '); ?> FCFA
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Informations de paiement -->
                <?php if ($fiche_data['paiement']): ?>
                <div class="fiche-section">
                    <h3 class="fiche-section-title"><i class="fas fa-credit-card"></i> Informations de paiement</h3>
                    <div class="fiche-grid">
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Statut</div>
                            <div class="fiche-info-value">
                                <span class="badge badge-success">
                                    <?php echo ucfirst(str_replace('_', ' ', $fiche_data['paiement']['statut'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Date de paiement</div>
                            <div class="fiche-info-value">
                                <?php echo date('d/m/Y H:i', strtotime($fiche_data['paiement']['date_paiement'])); ?>
                            </div>
                        </div>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Méthode</div>
                            <div class="fiche-info-value">
                                <?php echo ucfirst($fiche_data['paiement']['methode_paiement']); ?>
                            </div>
                        </div>
                        <?php if ($fiche_data['paiement']['reference_paiement']): ?>
                        <div class="fiche-info-item">
                            <div class="fiche-info-label">Référence</div>
                            <div class="fiche-info-value"><?php echo $fiche_data['paiement']['reference_paiement']; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Mentions légales -->
                <div class="fiche-section">
                    <h3 class="fiche-section-title"><i class="fas fa-file-contract"></i> Mentions légales</h3>
                    <div style="font-size: 12px; color: #6c757d; line-height: 1.6;">
                        <?php if ($entreprise_info && $entreprise_info['mentions_legales']): ?>
                            <?php echo nl2br(htmlspecialchars($entreprise_info['mentions_legales'])); ?>
                        <?php else: ?>
                            Cette fiche de paie est établie conformément aux dispositions légales en vigueur.
                            Elle constitue un justificatif de rémunération et doit être conservée par l'employé.
                            Toute reproduction ou falsification est interdite.
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Signatures -->
                <div class="signature-section">
                    <div class="signature-block">
                        <div class="fiche-info-label">Pour l'employeur</div>
                        <?php if ($entreprise_info && $entreprise_info['signature_direction']): ?>
                        <img src="../<?php echo htmlspecialchars($entreprise_info['signature_direction']); ?>" alt="Signature" style="max-height: 80px; margin: 20px 0;">
                        <?php else: ?>
                        <div class="signature-line"></div>
                        <?php endif; ?>
                        <div class="signature-name">La Direction</div>
                        <div class="signature-title"><?php echo $entreprise_info ? htmlspecialchars($entreprise_info['nom']) : 'MNLV Africa SARL'; ?></div>
                    </div>
                    
                    <div class="signature-block">
                        <div class="fiche-info-label">Pour l'employé</div>
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo $fiche_data['employe']['nom']; ?></div>
                        <div class="signature-title">Employé</div>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (!$selected_user && $selected_user_id): ?>
        <div class="card" style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--warning); margin-bottom: 20px;"></i>
            <h3>Aucune donnée disponible</h3>
            <p style="color: var(--text-secondary);">Aucune information de salaire ou de présence n'a été trouvée pour cet employé et cette période.</p>
        </div>
        <?php else: ?>
        <div class="card" style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-file-invoice-dollar" style="font-size: 48px; color: var(--primary); margin-bottom: 20px;"></i>
            <h3>Sélectionnez un employé</h3>
            <p style="color: var(--text-secondary);">Veuillez sélectionner un employé et un mois pour générer la fiche de paie.</p>
        </div>
        <?php endif; ?>
        
        <!-- Modal pour configurer les infos entreprise -->
        <?php if (isAdmin($_SESSION['user_id'])): ?>
        <div style="margin-top: 30px; text-align: center;">
            <button class="btn btn-secondary" onclick="openConfigModal()">
                <i class="fas fa-cog"></i> Configurer les informations de l'entreprise
            </button>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal de configuration entreprise -->
    <div id="configModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;"><i class="fas fa-building"></i> Informations de l'entreprise</h2>
                <button onclick="closeConfigModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="entrepriseForm">
                <input type="hidden" name="action" value="update_entreprise_info">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="nom">Nom de l'entreprise *</label>
                        <input type="text" id="nom" name="nom" class="form-control" required 
                               value="<?php echo $entreprise_info['nom'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <input type="text" id="adresse" name="adresse" class="form-control"
                               value="<?php echo $entreprise_info['adresse'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="ville">Ville</label>
                        <input type="text" id="ville" name="ville" class="form-control"
                               value="<?php echo $entreprise_info['ville'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="code_postal">Code postal</label>
                        <input type="text" id="code_postal" name="code_postal" class="form-control"
                               value="<?php echo $entreprise_info['code_postal'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="text" id="telephone" name="telephone" class="form-control"
                               value="<?php echo $entreprise_info['telephone'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo $entreprise_info['email'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="numero_fiscal">Numéro fiscal</label>
                        <input type="text" id="numero_fiscal" name="numero_fiscal" class="form-control"
                               value="<?php echo $entreprise_info['numero_fiscal'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="numero_cnps">Numéro CNPS</label>
                        <input type="text" id="numero_cnps" name="numero_cnps" class="form-control"
                               value="<?php echo $entreprise_info['numero_cnps'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="capital_social">Capital social</label>
                        <input type="text" id="capital_social" name="capital_social" class="form-control"
                               value="<?php echo $entreprise_info['capital_social'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="registre_commerce">Registre du commerce</label>
                        <input type="text" id="registre_commerce" name="registre_commerce" class="form-control"
                               value="<?php echo $entreprise_info['registre_commerce'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="logo">Logo de l'entreprise</label>
                    <input type="file" id="logo" name="logo" class="form-control" accept="image/*">
                    <?php if ($entreprise_info && $entreprise_info['logo']): ?>
                    <small>Logo actuel: <a href="../<?php echo $entreprise_info['logo']; ?>" target="_blank">Voir</a></small>
                    <?php endif; ?>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="signature_direction">Signature direction</label>
                        <input type="file" id="signature_direction" name="signature_direction" class="form-control" accept="image/*">
                        <?php if ($entreprise_info && $entreprise_info['signature_direction']): ?>
                        <small>Signature actuelle: <a href="../<?php echo $entreprise_info['signature_direction']; ?>" target="_blank">Voir</a></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="signature_rh">Signature RH</label>
                        <input type="file" id="signature_rh" name="signature_rh" class="form-control" accept="image/*">
                        <?php if ($entreprise_info && $entreprise_info['signature_rh']): ?>
                        <small>Signature actuelle: <a href="../<?php echo $entreprise_info['signature_rh']; ?>" target="_blank">Voir</a></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="conditions_paiement">Conditions de paiement</label>
                    <textarea id="conditions_paiement" name="conditions_paiement" class="form-control" rows="3"><?php echo $entreprise_info['conditions_paiement'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="mentions_legales">Mentions légales</label>
                    <textarea id="mentions_legales" name="mentions_legales" class="form-control" rows="4"><?php echo $entreprise_info['mentions_legales'] ?? ''; ?></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeConfigModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fonctions pour le modal de configuration
        function openConfigModal() {
            document.getElementById('configModal').style.display = 'flex';
        }
        
        function closeConfigModal() {
            document.getElementById('configModal').style.display = 'none';
        }
        
        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('configModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfigModal();
            }
        });
        
        // Générer PDF
        function generatePDF() {
            const userId = <?php echo $selected_user_id ?? 'null'; ?>;
            const month = '<?php echo $selected_month ?? ''; ?>';
            
            if (!userId || !month) return;
            
            // Afficher un message de chargement
            showNotification('Génération PDF', 'Préparation du fichier PDF...', 'info');
            
            // Créer un formulaire temporaire pour soumettre les données
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_pdf.php';
            form.style.display = 'none';
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;
            
            const monthInput = document.createElement('input');
            monthInput.type = 'hidden';
            monthInput.name = 'month';
            monthInput.value = month;
            
            form.appendChild(userIdInput);
            form.appendChild(monthInput);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Imprimer la fiche
        function printFiche() {
            const printContent = document.getElementById('fichePaie');
            const originalContent = document.body.innerHTML;
            
            // Mettre à jour le titre pour l'impression
            const titleElement = printContent.querySelector('.fiche-paie-title');
            const originalTitle = document.title;
            document.title = 'Fiche de Paie - ' + '<?php echo $fiche_data["employe"]["nom"] ?? ""; ?> - ' + '<?php echo $fiche_data["periode"]["mois"] ?? ""; ?>';
            
            document.body.innerHTML = printContent.outerHTML;
            window.print();
            
            // Restaurer le contenu original
            document.body.innerHTML = originalContent;
            document.title = originalTitle;
            
            // Recharger les événements
            window.location.reload();
        }
        
        // Fonction de notification
        function showNotification(title, message, type = 'info') {
            // Supprimer les notifications existantes
            document.querySelectorAll('.notification-toast').forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification-toast notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--bg-card);
                color: var(--text-primary);
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid;
                display: flex;
                align-items: center;
                gap: 15px;
                z-index: 1000;
                max-width: 400px;
                animation: slideInRight 0.3s ease;
                border: 1px solid var(--border-color);
                border-left-color: ${type === 'success' ? 'var(--success)' : 
                                  type === 'error' ? 'var(--danger)' : 
                                  type === 'warning' ? 'var(--warning)' : 'var(--info)'};
            `;
            
            let icon = 'info-circle';
            switch(type) {
                case 'success': icon = 'check-circle'; break;
                case 'error': icon = 'exclamation-circle'; break;
                case 'warning': icon = 'exclamation-triangle'; break;
                default: icon = 'info-circle';
            }
            
            notification.innerHTML = `
                <i class="fas fa-${icon}" style="font-size: 20px;"></i>
                <div>
                    <strong>${title}</strong>
                    <p style="margin: 4px 0 0 0; font-size: 13px;">${message}</p>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: var(--text-secondary); margin-left: 10px;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Supprimer automatiquement après 5 secondes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Écouter les changements dans le formulaire de sélection
        document.getElementById('user_id').addEventListener('change', function() {
            if (this.value && document.getElementById('month').value) {
                // Auto-submit the form
                document.getElementById('selectionForm').submit();
            }
        });
        
        document.getElementById('month').addEventListener('change', function() {
            if (this.value && document.getElementById('user_id').value) {
                // Auto-submit the form
                document.getElementById('selectionForm').submit();
            }
        });
        
        // Initialiser les tooltips pour le calendrier
        document.querySelectorAll('.calendrier-jour').forEach(day => {
            day.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.title;
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--bg-card);
                    color: var(--text-primary);
                    padding: 8px 12px;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 1000;
                    font-size: 12px;
                    white-space: nowrap;
                    border: 1px solid var(--border-color);
                    pointer-events: none;
                `;
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - 40) + 'px';
                tooltip.style.left = (rect.left + rect.width/2) + 'px';
                tooltip.style.transform = 'translateX(-50%)';
                
                document.body.appendChild(tooltip);
                
                this.tooltipElement = tooltip;
            });
            
            day.addEventListener('mouseleave', function() {
                if (this.tooltipElement) {
                    this.tooltipElement.remove();
                }
            });
        });
        
        // Empêcher la soumission du formulaire si aucune image n'est sélectionnée pour le logo
        document.getElementById('entrepriseForm').addEventListener('submit', function(e) {
            const logoInput = document.getElementById('logo');
            if (logoInput.files.length > 0) {
                const file = logoInput.files[0];
                if (!file.type.match('image.*')) {
                    e.preventDefault();
                    showNotification('Erreur', 'Le fichier logo doit être une image', 'error');
                    return false;
                }
                
                if (file.size > 5 * 1024 * 1024) { // 5MB max
                    e.preventDefault();
                    showNotification('Erreur', 'Le fichier logo est trop volumineux (max 5MB)', 'error');
                    return false;
                }
            }
            
            // Vérifier aussi les signatures
            const signatures = ['signature_direction', 'signature_rh'];
            signatures.forEach(sigId => {
                const sigInput = document.getElementById(sigId);
                if (sigInput.files.length > 0) {
                    const file = sigInput.files[0];
                    if (!file.type.match('image.*')) {
                        e.preventDefault();
                        showNotification('Erreur', `Le fichier ${sigId} doit être une image`, 'error');
                        return false;
                    }
                    
                    if (file.size > 5 * 1024 * 1024) {
                        e.preventDefault();
                        showNotification('Erreur', `Le fichier ${sigId} est trop volumineux (max 5MB)`, 'error');
                        return false;
                    }
                }
            });
            
            // Afficher un indicateur de chargement
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
            
            return true;
        });
    </script>
</body>
</html>
