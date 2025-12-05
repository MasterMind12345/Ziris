<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Gestion des salaires par poste
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_salaire'])) {
        $poste_id = $_POST['poste_id'];
        $salaire_heure = $_POST['salaire_heure'];
        
        // Mettre à jour le salaire horaire dans la table postes
        try {
            $stmt = $pdo->prepare("UPDATE postes SET salaire_heure = ? WHERE id = ?");
            $stmt->execute([$salaire_heure, $poste_id]);
            
            $_SESSION['success'] = "Salaire horaire mis à jour avec succès!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        }
    } elseif (isset($_POST['paiement_mensuel'])) {
        $user_id = $_POST['user_id'];
        $mois = $_POST['mois'];
        $annee = $_POST['annee'];
        $montant = $_POST['montant'];
        $statut = $_POST['statut'];
        
        // Enregistrer le paiement
        try {
            $stmt = $pdo->prepare("
                INSERT INTO salaires_paiements (user_id, mois, annee, montant, statut, date_paiement)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                montant = VALUES(montant), 
                statut = VALUES(statut), 
                date_paiement = NOW()
            ");
            $stmt->execute([$user_id, $mois, $annee, $montant, $statut]);
            
            $_SESSION['success'] = "Paiement enregistré avec succès!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
    }
}

// Récupérer les postes avec salaires
function getPostesAvecSalaires() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM postes ORDER BY nom");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur getPostesAvecSalaires: " . $e->getMessage());
        return [];
    }
}

// Récupérer les statistiques de salaires
function getStatsSalaires() {
    global $pdo;
    $stats = [
        'total_salaire_mensuel' => 0,
        'moyenne_salaire_heure' => 0,
        'total_employes' => 0,
        'progression_mensuelle' => 0
    ];
    
    try {
        // Total des employés
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
        $stats['total_employes'] = $stmt->fetch()['total'];
        
        // Salaire mensuel total estimé
        $stmt = $pdo->query("
            SELECT SUM(
                COALESCE(p.salaire_heure, 0) * 
                (
                    SELECT COALESCE(SUM(
                        TIME_TO_SEC(TIMEDIFF(heure_fin_reel, heure_debut_reel)) / 3600
                    ), 0) 
                    FROM presences pr 
                    WHERE pr.user_id = u.id 
                    AND MONTH(pr.date_presence) = MONTH(CURDATE())
                    AND YEAR(pr.date_presence) = YEAR(CURDATE())
                    AND heure_fin_reel IS NOT NULL
                )
            ) as total_salaire
            FROM users u
            LEFT JOIN postes p ON u.poste_id = p.id
            WHERE u.is_admin = 0
        ");
        $result = $stmt->fetch();
        $stats['total_salaire_mensuel'] = round($result['total_salaire'] ?? 0, 2);
        
        // Moyenne salaire horaire
        $stmt = $pdo->query("
            SELECT AVG(COALESCE(salaire_heure, 0)) as moyenne 
            FROM postes 
            WHERE salaire_heure > 0
        ");
        $result = $stmt->fetch();
        $stats['moyenne_salaire_heure'] = round($result['moyenne'] ?? 0, 2);
        
        // Progression mensuelle
        $stmt = $pdo->query("
            SELECT (
                SELECT SUM(
                    COALESCE(p.salaire_heure, 0) * 
                    (
                        SELECT COALESCE(SUM(
                            TIME_TO_SEC(TIMEDIFF(heure_fin_reel, heure_debut_reel)) / 3600
                        ), 0) 
                        FROM presences pr 
                        WHERE pr.user_id = u.id 
                        AND MONTH(pr.date_presence) = MONTH(CURDATE())
                        AND YEAR(pr.date_presence) = YEAR(CURDATE())
                    )
                ) as total_courant
                FROM users u
                LEFT JOIN postes p ON u.poste_id = p.id
                WHERE u.is_admin = 0
            ) - (
                SELECT SUM(
                    COALESCE(p.salaire_heure, 0) * 
                    (
                        SELECT COALESCE(SUM(
                            TIME_TO_SEC(TIMEDIFF(heure_fin_reel, heure_debut_reel)) / 3600
                        ), 0) 
                        FROM presences pr 
                        WHERE pr.user_id = u.id 
                        AND MONTH(pr.date_presence) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                        AND YEAR(pr.date_presence) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                    )
                ) as total_precedent
                FROM users u
                LEFT JOIN postes p ON u.poste_id = p.id
                WHERE u.is_admin = 0
            ) as progression
        ");
        $result = $stmt->fetch();
        $stats['progression_mensuelle'] = round($result['progression'] ?? 0, 2);
        
    } catch (PDOException $e) {
        error_log("Erreur getStatsSalaires: " . $e->getMessage());
    }
    
    return $stats;
}

// Récupérer les salaires des employés pour le mois en cours
function getSalairesMensuels($mois = null, $annee = null) {
    global $pdo;
    
    if ($mois === null) $mois = date('m');
    if ($annee === null) $annee = date('Y');
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id as user_id,
                u.nom,
                u.email,
                p.id as poste_id,
                p.nom as poste_nom,
                COALESCE(p.salaire_heure, 0) as salaire_heure,
                (
                    SELECT COALESCE(SUM(
                        TIME_TO_SEC(TIMEDIFF(heure_fin_reel, heure_debut_reel)) / 3600
                    ), 0) 
                    FROM presences pr 
                    WHERE pr.user_id = u.id 
                    AND MONTH(pr.date_presence) = ?
                    AND YEAR(pr.date_presence) = ?
                    AND heure_fin_reel IS NOT NULL
                ) as heures_travaillees,
                (
                    SELECT COUNT(DISTINCT date_presence)
                    FROM presences pr 
                    WHERE pr.user_id = u.id 
                    AND MONTH(pr.date_presence) = ?
                    AND YEAR(pr.date_presence) = ?
                ) as jours_presents,
                COALESCE(sp.montant, 0) as montant_paye,
                COALESCE(sp.statut, 'non_paye') as statut_paiement,
                sp.date_paiement
            FROM users u
            LEFT JOIN postes p ON u.poste_id = p.id
            LEFT JOIN salaires_paiements sp ON u.id = sp.user_id 
                AND sp.mois = ? 
                AND sp.annee = ?
            WHERE u.is_admin = 0
            ORDER BY u.nom
        ");
        $stmt->execute([$mois, $annee, $mois, $annee, $mois, $annee]);
        
        $salaires = $stmt->fetchAll();
        
        // Calculer le salaire pour chaque employé
        foreach ($salaires as &$salaire) {
            $salaire['salaire_calcul'] = round($salaire['salaire_heure'] * $salaire['heures_travaillees'], 2);
            $salaire['reste_a_payer'] = max(0, $salaire['salaire_calcul'] - $salaire['montant_paye']);
        }
        
        return $salaires;
    } catch (PDOException $e) {
        error_log("Erreur getSalairesMensuels: " . $e->getMessage());
        return [];
    }
}

// Récupérer l'historique des paiements
function getHistoriquePaiements($limit = 50) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sp.*,
                u.nom,
                p.nom as poste_nom,
                MONTHNAME(STR_TO_DATE(CONCAT(sp.annee, '-', sp.mois, '-01'), '%Y-%m-%d')) as mois_nom
            FROM salaires_paiements sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN postes p ON u.poste_id = p.id
            ORDER BY sp.date_paiement DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur getHistoriquePaiements: " . $e->getMessage());
        return [];
    }
}

// Vérifier et créer la table salaires_paiements si nécessaire
function verifierTableSalaires() {
    global $pdo;
    
    try {
        // Vérifier si la table existe
        $tableExists = $pdo->query("SHOW TABLES LIKE 'salaires_paiements'")->rowCount() > 0;
        
        if (!$tableExists) {
            // Créer la table
            $sql = "
                CREATE TABLE salaires_paiements (
                    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(11) NOT NULL,
                    mois INT(2) NOT NULL,
                    annee INT(4) NOT NULL,
                    montant DECIMAL(10,2) NOT NULL DEFAULT 0,
                    statut ENUM('paye', 'partiel', 'non_paye', 'retarde') DEFAULT 'non_paye',
                    date_paiement DATETIME DEFAULT NULL,
                    methode_paiement VARCHAR(50) DEFAULT 'virement',
                    reference_paiement VARCHAR(100) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_paiement_mensuel (user_id, mois, annee),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            $pdo->exec($sql);
            
            // Ajouter la colonne salaire_heure à la table postes si elle n'existe pas
            $colonneExists = $pdo->query("
                SHOW COLUMNS FROM postes LIKE 'salaire_heure'
            ")->rowCount() > 0;
            
            if (!$colonneExists) {
                $pdo->exec("ALTER TABLE postes ADD COLUMN salaire_heure DECIMAL(10,2) DEFAULT NULL AFTER description");
            }
            
            return true;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur verifierTableSalaires: " . $e->getMessage());
        return false;
    }
}

// Vérifier la table des salaires
verifierTableSalaires();

// Récupérer les données
$postes = getPostesAvecSalaires();
$stats_salaires = getStatsSalaires();
$salaires_mensuels = getSalairesMensuels();
$historique_paiements = getHistoriquePaiements();

// Mois et année actuels
$mois_actuel = date('m');
$annee_actuelle = date('Y');
$mois_nom = date('F', strtotime(date('Y-'.$mois_actuel.'-01')));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Salaires - Ziris Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4361ee"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ziris">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">
    <link rel="manifest" href="/manifest.json">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-money-bill-wave"></i> Gestion des Salaires</h1>
                <p>Gérez les salaires horaires par poste et consultez les rémunérations mensuelles</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="genererBulletins()">
                    <i class="fas fa-file-pdf"></i> Générer Bulletins
                </button>
                <button class="btn btn-success" onclick="exporterSalaires()">
                    <i class="fas fa-download"></i> Exporter Données
                </button>
            </div>
        </div>
        
        <!-- Messages d'alerte -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats_salaires['total_salaire_mensuel'], 2, ',', ' '); ?> FCFA</h3>
                    <p>Masse salariale mensuelle</p>
                    <div class="stat-trend <?php echo $stats_salaires['progression_mensuelle'] >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $stats_salaires['progression_mensuelle'] >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($stats_salaires['progression_mensuelle']); ?> FCFA
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats_salaires['moyenne_salaire_heure'], 2, ',', ' '); ?> FCFA</h3>
                    <p>Salaire horaire moyen</p>
                    <div class="stat-label">Tous postes</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats_salaires['total_employes']; ?></h3>
                    <p>Employés à rémunérer</p>
                    <div class="stat-label"><?php echo count(array_filter($salaires_mensuels, fn($s) => $s['salaire_calcul'] > 0)); ?> avec salaire ce mois</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count(array_filter($salaires_mensuels, fn($s) => $s['statut_paiement'] === 'paye')); ?></h3>
                    <p>Paiements effectués</p>
                    <div class="stat-label">Mois en cours</div>
                </div>
            </div>
        </div>
        
        <!-- Section principale avec onglets -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('tab-salaires')">
                    <i class="fas fa-calculator"></i> Salaires Mensuels
                </button>
                <button class="tab-btn" onclick="switchTab('tab-postes')">
                    <i class="fas fa-briefcase"></i> Salaires par Poste
                </button>
                <button class="tab-btn" onclick="switchTab('tab-paiements')">
                    <i class="fas fa-history"></i> Historique Paiements
                </button>
                <button class="tab-btn" onclick="switchTab('tab-analytique')">
                    <i class="fas fa-chart-bar"></i> Analyse
                </button>
            </div>
            
            <!-- Onglet 1: Salaires Mensuels -->
            <div id="tab-salaires" class="tab-content active">
                <div class="section-header">
                    <h2>Salaires pour <?php echo $mois_nom . ' ' . $annee_actuelle; ?></h2>
                    <div class="section-actions">
                        <div class="period-selector">
                            <select id="select-mois" class="form-control" onchange="changerMoisSalaire()">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == $mois_actuel ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select id="select-annee" class="form-control" onchange="changerMoisSalaire()">
                                <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == $annee_actuelle ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="table-salaires">
                        <thead>
                            <tr>
                                <th>Employé</th>
                                <th>Poste</th>
                                <th>Salaire/h</th>
                                <th>Heures</th>
                                <th>Jours</th>
                                <th>Salaire Brut</th>
                                <th>Montant Payé</th>
                                <th>Reste</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total_salaire = 0; $total_paye = 0; ?>
                            <?php foreach ($salaires_mensuels as $salaire): ?>
                                <?php 
                                    $total_salaire += $salaire['salaire_calcul'];
                                    $total_paye += $salaire['montant_paye'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="avatar-small" style="background: <?php echo getRandomColor($salaire['user_id']); ?>">
                                                <?php echo getInitials($salaire['nom']); ?>
                                            </div>
                                            <div>
                                                <div class="user-name"><?php echo htmlspecialchars($salaire['nom']); ?></div>
                                                <div class="user-email"><?php echo htmlspecialchars($salaire['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($salaire['poste_nom']); ?></td>
                                    <td class="text-right"><?php echo number_format($salaire['salaire_heure'], 2, ',', ' '); ?> FCFA</td>
                                    <td class="text-center"><?php echo number_format($salaire['heures_travaillees'], 1, ',', ' '); ?>h</td>
                                    <td class="text-center"><?php echo $salaire['jours_presents']; ?>j</td>
                                    <td class="text-right">
                                        <span class="amount"><?php echo number_format($salaire['salaire_calcul'], 2, ',', ' '); ?> FCFA</span>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($salaire['montant_paye'] > 0): ?>
                                            <span class="amount paid"><?php echo number_format($salaire['montant_paye'], 2, ',', ' '); ?> FCFA</span>
                                        <?php else: ?>
                                            <span class="amount">0,00 FCFA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($salaire['reste_a_payer'] > 0): ?>
                                            <span class="amount unpaid"><?php echo number_format($salaire['reste_a_payer'], 2, ',', ' '); ?> FCFA</span>
                                        <?php else: ?>
                                            <span class="amount">0,00 FCFA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $statut_classes = [
                                                'paye' => 'badge-success',
                                                'partiel' => 'badge-warning',
                                                'non_paye' => 'badge-error',
                                                'retarde' => 'badge-secondary'
                                            ];
                                            $statut_text = [
                                                'paye' => 'Payé',
                                                'partiel' => 'Partiel',
                                                'non_paye' => 'Non payé',
                                                'retarde' => 'Retardé'
                                            ];
                                        ?>
                                        <span class="badge <?php echo $statut_classes[$salaire['statut_paiement']]; ?>">
                                            <?php echo $statut_text[$salaire['statut_paiement']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" title="Payer" onclick="payerSalaire(<?php echo $salaire['user_id']; ?>, '<?php echo $salaire['nom']; ?>', <?php echo $salaire['salaire_calcul']; ?>)">
                                                <i class="fas fa-credit-card"></i>
                                            </button>
                                            <button class="btn-icon" title="Détails" onclick="voirDetails(<?php echo $salaire['user_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon" title="Bulletin" onclick="genererBulletin(<?php echo $salaire['user_id']; ?>)">
                                                <i class="fas fa-file-invoice"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Totaux:</strong></td>
                                <td class="text-right"><strong><?php echo number_format($total_salaire, 2, ',', ' '); ?> FCFA</strong></td>
                                <td class="text-right"><strong><?php echo number_format($total_paye, 2, ',', ' '); ?> FCFA</strong></td>
                                <td class="text-right"><strong><?php echo number_format($total_salaire - $total_paye, 2, ',', ' '); ?> FCFA</strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Onglet 2: Salaires par Poste -->
            <div id="tab-postes" class="tab-content">
                <div class="section-header">
                    <h2>Configuration des Salaires par Poste</h2>
                    <p>Définissez le salaire horaire pour chaque type de poste</p>
                </div>
                
                <div class="grid-2">
                    <div class="table-container">
                        <div class="table-header">
                            <h3>Liste des Postes</h3>
                            <button class="btn btn-primary" onclick="ouvrirModalNouveauPoste()">
                                <i class="fas fa-plus"></i> Nouveau Poste
                            </button>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Poste</th>
                                    <th>Description</th>
                                    <th>Salaire/h</th>
                                    <th>Employés</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($postes as $poste): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($poste['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($poste['description'] ?? 'Non spécifié'); ?></td>
                                        <td>
                                            <?php if ($poste['salaire_heure']): ?>
                                                <span class="badge badge-success"><?php echo number_format($poste['salaire_heure'], 2, ',', ' '); ?> FCFA/h</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Non défini</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE poste_id = ? AND is_admin = 0");
                                                $stmt->execute([$poste['id']]);
                                                $total = $stmt->fetch()['total'];
                                            ?>
                                            <span class="badge badge-info"><?php echo $total; ?> employé(s)</span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon" title="Modifier salaire" onclick="modifierSalairePoste(<?php echo $poste['id']; ?>, '<?php echo htmlspecialchars($poste['nom']); ?>', <?php echo $poste['salaire_heure'] ?? 0; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon" title="Voir employés" onclick="voirEmployesPoste(<?php echo $poste['id']; ?>)">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                        <p>Pour configurer le salaire horaire d'un poste :</p>
                        <ol>
                            <li>Sélectionnez le poste dans la liste</li>
                            <li>Cliquez sur l'icône <i class="fas fa-edit"></i> pour modifier</li>
                            <li>Entrez le salaire horaire en FCFA</li>
                            <li>Cliquez sur "Mettre à jour"</li>
                        </ol>
                        <p><strong>Note :</strong> Le salaire défini sera appliqué automatiquement à tous les employés occupant ce poste.</p>
                        
                        <div class="example-calculation">
                            <h4>Exemple de calcul :</h4>
                            <p>Salaire/h : 15,00 FCFA<br>
                               Heures travaillées : 160h<br>
                               <strong>Salaire brut : 2 400,00 FCFA</strong></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet 3: Historique des Paiements -->
            <div id="tab-paiements" class="tab-content">
                <div class="section-header">
                    <h2>Historique des Paiements</h2>
                    <div class="section-actions">
                        <input type="text" class="form-control" placeholder="Rechercher..." id="search-paiements">
                    </div>
                </div>
                
                <div class="table-container">
                    <?php if (count($historique_paiements) > 0): ?>
                        <table id="table-paiements">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Employé</th>
                                    <th>Période</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Méthode</th>
                                    <th>Référence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historique_paiements as $paiement): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($paiement['date_paiement'])); ?></td>
                                        <td><?php echo htmlspecialchars($paiement['nom']); ?></td>
                                        <td><?php echo $paiement['mois_nom'] . ' ' . $paiement['annee']; ?></td>
                                        <td class="text-right"><?php echo number_format($paiement['montant'], 2, ',', ' '); ?> FCFA</td>
                                        <td>
                                            <?php $statut_text = ['paye' => 'Payé', 'partiel' => 'Partiel', 'non_paye' => 'Non payé', 'retarde' => 'Retardé']; ?>
                                            <span class="badge badge-<?php echo $paiement['statut'] === 'paye' ? 'success' : ($paiement['statut'] === 'partiel' ? 'warning' : 'error'); ?>">
                                                <?php echo $statut_text[$paiement['statut']]; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($paiement['methode_paiement'] ?? 'Virement'); ?></td>
                                        <td><?php echo htmlspecialchars($paiement['reference_paiement'] ?? '-'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon" title="Voir reçu" onclick="voirRecu(<?php echo $paiement['id']; ?>)">
                                                    <i class="fas fa-receipt"></i>
                                                </button>
                                                <button class="btn-icon" title="Exporter" onclick="exporterPaiement(<?php echo $paiement['id']; ?>)">
                                                    <i class="fas fa-file-export"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history fa-3x"></i>
                            <h3>Aucun paiement enregistré</h3>
                            <p>Les paiements effectués apparaîtront ici</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Onglet 4: Analyse -->
            <div id="tab-analytique" class="tab-content">
                <div class="section-header">
                    <h2>Analyse des Salaires</h2>
                    <div class="section-actions">
                        <select id="select-periode-analyse" class="form-control" onchange="changerPeriodeAnalyse()">
                            <option value="mois">Ce mois</option>
                            <option value="trimestre">Ce trimestre</option>
                            <option value="semestre">Ce semestre</option>
                            <option value="annee">Cette année</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="chart-container">
                        <h3>Répartition par Poste</h3>
                        <canvas id="chart-repartition-poste"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3>Évolution Mensuelle</h3>
                        <canvas id="chart-evolution-mensuelle"></canvas>
                    </div>
                </div>
                
                <div class="table-container" style="margin-top: 30px;">
                    <h3>Top 5 des Salaires</h3>
                    <div class="ranking-list">
                        <?php 
                        $salaires_tries = $salaires_mensuels;
                        usort($salaires_tries, fn($a, $b) => $b['salaire_calcul'] <=> $a['salaire_calcul']);
                        $top_salaires = array_slice($salaires_tries, 0, 5);
                        ?>
                        <?php foreach ($top_salaires as $index => $salaire): ?>
                            <div class="ranking-item">
                                <div class="rank-number"><?php echo $index + 1; ?></div>
                                <div class="user-info">
                                    <div class="avatar-small" style="background: <?php echo getRandomColor($salaire['user_id']); ?>">
                                        <?php echo getInitials($salaire['nom']); ?>
                                    </div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($salaire['nom']); ?></div>
                                        <div class="user-poste"><?php echo htmlspecialchars($salaire['poste_nom']); ?></div>
                                    </div>
                                </div>
                                <div class="ranking-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo number_format($salaire['heures_travaillees'], 1, ',', ' '); ?>h</span>
                                        <span class="stat-label">Heures</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo number_format($salaire['salaire_calcul'], 0, ',', ' '); ?> FCFA</span>
                                        <span class="stat-label">Salaire</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal pour paiement -->
        <div id="modal-paiement" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Enregistrer un Paiement</h2>
                    <button class="close-modal" onclick="fermerModal('modal-paiement')">&times;</button>
                </div>
                <form method="POST" id="form-paiement">
                    <input type="hidden" name="user_id" id="paiement_user_id">
                    <input type="hidden" name="mois" value="<?php echo $mois_actuel; ?>">
                    <input type="hidden" name="annee" value="<?php echo $annee_actuelle; ?>">
                    
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Employé</label>
                            <input type="text" id="paiement_employe_nom" class="form-control" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Salaire à payer</label>
                            <input type="text" id="paiement_salaire_du" class="form-control" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Montant à payer (FCFA)</label>
                            <input type="number" name="montant" id="paiement_montant" class="form-control" step="0.01" min="0" required>
                            <small class="form-text">Montant effectivement versé</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Statut du paiement</label>
                            <select name="statut" id="paiement_statut" class="form-control" required>
                                <option value="paye">Payé</option>
                                <option value="partiel">Partiellement payé</option>
                                <option value="retarde">Retardé</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Méthode de paiement</label>
                            <select name="methode_paiement" class="form-control">
                                <option value="virement">Virement bancaire</option>
                                <option value="cheque">Chèque</option>
                                <option value="especes">Espèces</option>
                                <option value="mobile">Mobile money</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Référence de paiement</label>
                            <input type="text" name="reference_paiement" class="form-control" placeholder="Numéro de transaction ou référence">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="fermerModal('modal-paiement')">Annuler</button>
                        <button type="submit" name="paiement_mensuel" class="btn btn-primary">Enregistrer le paiement</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal pour modifier salaire poste -->
        <div id="modal-salaire-poste" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Modifier le salaire horaire</h2>
                    <button class="close-modal" onclick="fermerModal('modal-salaire-poste')">&times;</button>
                </div>
                <form method="POST">
                    <input type="hidden" name="poste_id" id="poste_id">
                    
                    <div class="modal-body">
                        <div class="form-group">
                            <label id="poste_nom_label">Poste</label>
                            <input type="text" id="poste_nom_display" class="form-control" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Salaire horaire (FCFA)</label>
                            <input type="number" name="salaire_heure" id="poste_salaire_heure" class="form-control" step="0.01" min="0" required>
                            <small class="form-text">Montant en FCFA par heure travaillée</small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="fermerModal('modal-salaire-poste')">Annuler</button>
                        <button type="submit" name="update_salaire" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <script src="js/script.js"></script>
    <script>
    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser les graphiques
        initialiserGraphiquesSalaires();
        
        // Recherche dans l'historique
        document.getElementById('search-paiements')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#table-paiements tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Ouvrir la modale si erreur dans formulaire
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('payer')) {
            const userId = urlParams.get('user_id');
            const salaire = urlParams.get('salaire');
            if (userId && salaire) {
                payerSalaire(userId, '', parseFloat(salaire));
            }
        }
    });
    
    // Gestion des onglets
    function switchTab(tabId) {
        // Masquer tous les onglets
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Désactiver tous les boutons d'onglets
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Activer l'onglet sélectionné
        document.getElementById(tabId).classList.add('active');
        event.target.classList.add('active');
    }
    
    // Fonction pour payer un salaire
    function payerSalaire(userId, employeNom, salaireDu) {
        document.getElementById('paiement_user_id').value = userId;
        document.getElementById('paiement_employe_nom').value = employeNom || 'Employé #' + userId;
        document.getElementById('paiement_salaire_du').value = salaireDu.toFixed(2) + ' FCFA';
        document.getElementById('paiement_montant').value = salaireDu.toFixed(2);
        document.getElementById('paiement_montant').max = salaireDu;
        
        ouvrirModal('modal-paiement');
    }
    
    // Fonction pour modifier le salaire d'un poste
    function modifierSalairePoste(posteId, posteNom, salaireActuel) {
        document.getElementById('poste_id').value = posteId;
        document.getElementById('poste_nom_display').value = posteNom;
        document.getElementById('poste_salaire_heure').value = salaireActuel || '';
        
        ouvrirModal('modal-salaire-poste');
    }
    
    // Fonction pour voir les détails d'un employé
    function voirDetails(userId) {
        window.location.href = `profile_employe.php?id=${userId}&tab=salaires`;
    }
    
    // Fonction pour générer un bulletin
    function genererBulletin(userId) {
        // Ici, vous intégrerez la génération de bulletin de paie
        showAlert('Génération du bulletin de paie...', 'info');
        // window.open(`generer_bulletin.php?id=${userId}`, '_blank');
    }
    
    // Fonction pour générer tous les bulletins
    function genererBulletins() {
        showAlert('Préparation de la génération des bulletins...', 'info');
        // Implémentez ici la logique pour générer tous les bulletins
    }
    
    // Fonction pour exporter les salaires
    function exporterSalaires() {
        const mois = document.getElementById('select-mois').value;
        const annee = document.getElementById('select-annee').value;
        
        // Exporter les données via AJAX ou redirection
        window.location.href = `export_salaires.php?mois=${mois}&annee=${annee}`;
    }
    
    // Fonction pour changer le mois affiché
    function changerMoisSalaire() {
        const mois = document.getElementById('select-mois').value;
        const annee = document.getElementById('select-annee').value;
        
        window.location.href = `salaires.php?mois=${mois}&annee=${annee}`;
    }
    
    // Fonction pour changer la période d'analyse
    function changerPeriodeAnalyse() {
        // Recharger les données d'analyse
        const periode = document.getElementById('select-periode-analyse').value;
        console.log('Changement de période d\'analyse:', periode);
        // Implémentez ici la logique AJAX pour recharger les graphiques
    }
    
    // Fonction pour initialiser les graphiques
    function initialiserGraphiquesSalaires() {
        // Graphique de répartition par poste
        const ctx1 = document.getElementById('chart-repartition-poste').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Administrateur', 'Développeur', 'Designer', 'Commercial', 'RH'],
                datasets: [{
                    data: [4500, 12000, 8500, 6800, 5200],
                    backgroundColor: [
                        'rgba(67, 97, 238, 0.8)',
                        'rgba(76, 201, 240, 0.8)',
                        'rgba(247, 37, 133, 0.8)',
                        'rgba(42, 157, 143, 0.8)',
                        'rgba(233, 196, 106, 0.8)'
                    ],
                    borderColor: [
                        'rgba(67, 97, 238, 1)',
                        'rgba(76, 201, 240, 1)',
                        'rgba(247, 37, 133, 1)',
                        'rgba(42, 157, 143, 1)',
                        'rgba(233, 196, 106, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.raw);
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Graphique d'évolution mensuelle
        const ctx2 = document.getElementById('chart-evolution-mensuelle').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Masse salariale',
                    data: [32000, 34000, 33500, 36000, 38000, 37500, 39000, 38500, 40000, 42000, 41000, 43000],
                    borderColor: 'rgba(67, 97, 238, 1)',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 }).format(value);
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Fonctions modales utilitaires
    function ouvrirModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }
    
    function fermerModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Fermer les modales en cliquant en dehors
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    
    // Fonction pour afficher des alertes
    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-error' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 
                             type === 'error' ? 'exclamation-circle' : 
                             type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;
        
        const pageHeader = document.querySelector('.page-header');
        if (pageHeader && pageHeader.nextElementSibling) {
            pageHeader.parentNode.insertBefore(alertDiv, pageHeader.nextElementSibling);
        } else {
            document.querySelector('.main-content').prepend(alertDiv);
        }
        
        // Supprimer l'alerte après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    </script>
    
    <style>
    /* Styles spécifiques pour la page salaires */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .header-content h1 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 5px;
    }
    
    .header-actions {
        display: flex;
        gap: 10px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: var(--transition);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
    }
    
    .stat-info {
        flex: 1;
    }
    
    .stat-info h3 {
        margin: 0;
        font-size: 28px;
        color: var(--dark);
    }
    
    .stat-info p {
        margin: 5px 0;
        color: var(--gray);
        font-size: 14px;
    }
    
    .stat-trend {
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 5px;
    }
    
    .stat-trend.positive {
        color: var(--success);
    }
    
    .stat-trend.negative {
        color: var(--danger);
    }
    
    .stat-label {
        font-size: 12px;
        color: var(--gray);
    }
    
    /* Tabs */
    .tabs-container {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    
    .tabs-header {
        display: flex;
        background: var(--gray-light);
        border-bottom: 1px solid #e0e0e0;
        overflow-x: auto;
    }
    
    .tab-btn {
        padding: 15px 25px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        font-weight: 500;
        color: var(--dark);
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .tab-btn:hover {
        background: rgba(255,255,255,0.5);
    }
    
    .tab-btn.active {
        background: white;
        border-bottom-color: var(--primary);
        color: var(--primary);
    }
    
    .tab-content {
        padding: 30px;
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .period-selector {
        display: flex;
        gap: 10px;
    }
    
    /* Tables */
    .table-container {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    table thead {
        background: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
    }
    
    table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: var(--dark);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    table td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
    }
    
    table tbody tr:hover {
        background: #f8f9fa;
    }
    
    table tfoot {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .avatar-small {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
        flex-shrink: 0;
    }
    
    .user-name {
        font-weight: 500;
        margin-bottom: 2px;
    }
    
    .user-email {
        font-size: 12px;
        color: var(--gray);
    }
    
    .amount {
        font-weight: 600;
        font-family: 'Courier New', monospace;
    }
    
    .amount.paid {
        color: var(--success);
    }
    
    .amount.unpaid {
        color: var(--danger);
    }
    
    /* Badges */
    .badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-error { background: #f8d7da; color: #721c24; }
    .badge-info { background: #d1ecf1; color: #0c5460; }
    .badge-secondary { background: #e2e3e5; color: #383d41; }
    
    /* Action buttons */
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    
    .btn-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: none;
        background: #f8f9fa;
        color: var(--dark);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }
    
    .btn-icon:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
    }
    
    /* Layout grid */
    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    
    @media (max-width: 992px) {
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }
    
    /* Info card */
    .info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: var(--border-radius);
    }
    
    .info-card h3 {
        margin-top: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-card ol {
        padding-left: 20px;
        margin: 15px 0;
    }
    
    .info-card li {
        margin-bottom: 5px;
    }
    
    .example-calculation {
        background: rgba(255,255,255,0.1);
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
    }
    
    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray);
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 20px;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        margin-bottom: 10px;
        color: var(--dark);
    }
    
    /* Ranking list */
    .ranking-list {
        padding: 0;
    }
    
    .ranking-item {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid var(--gray-light);
        transition: var(--transition);
    }
    
    .ranking-item:hover {
        background: #f8f9fa;
    }
    
    .ranking-item:last-child {
        border-bottom: none;
    }
    
    .rank-number {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .ranking-item:nth-child(1) .rank-number {
        background: gold;
        color: #333;
    }
    
    .ranking-item:nth-child(2) .rank-number {
        background: silver;
        color: #333;
    }
    
    .ranking-item:nth-child(3) .rank-number {
        background: #cd7f32;
        color: white;
    }
    
    .ranking-stats {
        display: flex;
        gap: 20px;
        margin-left: auto;
    }
    
    .stat-item {
        text-align: center;
        min-width: 80px;
    }
    
    .stat-value {
        display: block;
        font-weight: bold;
        font-size: 16px;
        color: var(--dark);
    }
    
    .stat-label {
        display: block;
        font-size: 11px;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Chart containers */
    .chart-container {
        background: white;
        padding: 20px;
        border-radius: var(--border-radius);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .chart-container h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 18px;
    }
    
    /* Modals */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background: white;
        border-radius: var(--border-radius);
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalFadeIn 0.3s;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-50px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid var(--gray-light);
    }
    
    .modal-header h2 {
        margin: 0;
        font-size: 20px;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: var(--gray);
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    
    .close-modal:hover {
        background: var(--gray-light);
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        padding: 20px;
        border-top: 1px solid var(--gray-light);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    /* Form styles */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--dark);
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: var(--transition);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }
    
    .form-text {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: var(--gray);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .header-actions {
            width: 100%;
            justify-content: flex-start;
        }
        
        .tabs-header {
            flex-wrap: wrap;
        }
        
        .tab-btn {
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .period-selector {
            width: 100%;
        }
        
        .ranking-stats {
            flex-direction: column;
            gap: 5px;
        }
        
        .stat-item {
            min-width: auto;
            text-align: left;
        }
        
        table {
            display: block;
            overflow-x: auto;
        }
    }
    
    /* Print styles */
    @media print {
        .sidebar, .header, .header-actions, .tabs-header, .action-buttons {
            display: none !important;
        }
        
        .main-content {
            margin: 0;
            padding: 0;
        }
        
        table {
            break-inside: avoid;
        }
    }
    </style>
</body>
</html>