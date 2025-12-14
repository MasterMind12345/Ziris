
<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// =====================================================================
// CONFIGURATION DOMPDF - VERSION SIMPLIFIÉE ET CORRECTE
// =====================================================================

// Vérifier si DomPDF est installé
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
$dompdfPath = __DIR__ . '/../vendor/dompdf/dompdf/src/Dompdf.php';

if (!file_exists($vendorAutoload)) {
    die("<h1>❌ DomPDF non installé</h1>
        <p>Exécutez cette commande à la racine du projet :</p>
        <pre>composer require dompdf/dompdf</pre>
        <p>Ensuite rechargez cette page.</p>");
}

// Charger l'autoloader de Composer (C'EST LA SOLUTION !)
require_once $vendorAutoload;

// Utiliser Dompdf directement via l'autoloader
use Dompdf\Dompdf;
use Dompdf\Options;

// =====================================================================
// RÉCUPÉRATION DES DONNÉES
// =====================================================================

// Récupérer les paramètres
$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
$month = $_POST['month'] ?? $_GET['month'] ?? date('Y-m');

if (!$user_id || !$month) {
    die('<h1>❌ Paramètres manquants</h1>
        <p>Veuillez sélectionner un employé et un mois.</p>');
}

try {
    // 1. Informations de l'employé
    $stmt = $pdo->prepare("
        SELECT 
            u.*, 
            p.nom as poste_nom, 
            p.description as poste_description,
            ps.salaire_brut_mensuel, 
            ps.salaire_horaire, 
            ps.jours_travail_mois, 
            ps.heures_travail_jour
        FROM users u
        LEFT JOIN postes p ON u.poste_id = p.id
        LEFT JOIN parametres_salaire ps ON p.id = ps.poste_id
        WHERE u.id = ? AND u.is_admin = 0
    ");
    $stmt->execute([$user_id]);
    $employe = $stmt->fetch();
    
    if (!$employe) {
        die('<h1>❌ Employé non trouvé</h1>');
    }
    
    // 2. Informations de l'entreprise
    $stmt = $pdo->query("SELECT * FROM entreprise_infos LIMIT 1");
    $entreprise = $stmt->fetch();
    
    // Données par défaut si pas de configuration
    if (!$entreprise) {
        $entreprise = [
            'nom' => 'MNLV Africa SARL',
            'adresse' => 'Cité des Palmiers, Akwa',
            'ville' => 'Douala',
            'code_postal' => 'BP 1234',
            'telephone' => '+237 6 99 99 99 99',
            'email' => 'contact@mnlvafrica.com',
            'numero_fiscal' => 'M1234567890',
            'numero_cnps' => 'CNPS-123456-789',
            'capital_social' => '10 000 000 FCFA',
            'registre_commerce' => 'RC/DLA/2024/B/1234'
        ];
    }
    
    // 3. Paramètres système
    $stmt = $pdo->query("SELECT heure_debut_normal, heure_fin_normal FROM parametres_systeme LIMIT 1");
    $params = $stmt->fetch();
    $heure_debut_normal = $params['heure_debut_normal'] ?? '08:30:00';
    $heure_fin_normal = $params['heure_fin_normal'] ?? '17:30:00';
    
    // 4. Période
    $annee = date('Y', strtotime($month));
    $mois_num = date('m', strtotime($month));
    $debut_mois = date('Y-m-01', strtotime($month));
    $fin_mois = date('Y-m-t', strtotime($month));
    $nom_mois = date('F Y', strtotime($month));
    $jours_dans_mois = date('t', strtotime($month));
    
    // 5. Présences du mois
    $stmt = $pdo->prepare("
        SELECT 
            date_presence,
            heure_debut_reel,
            heure_fin_reel,
            retard_minutes,
            heure_pause_debut,
            heure_pause_fin,
            lieu
        FROM presences 
        WHERE user_id = ? 
            AND date_presence BETWEEN ? AND ?
        ORDER BY date_presence
    ");
    $stmt->execute([$user_id, $debut_mois, $fin_mois]);
    $presences = $stmt->fetchAll();
    
    // 6. Paiement existant
    $stmt = $pdo->prepare("
        SELECT * FROM salaires_paiements 
        WHERE user_id = ? AND mois = ? AND annee = ?
    ");
    $stmt->execute([$user_id, $mois_num, $annee]);
    $paiement = $stmt->fetch();
    
    // =====================================================================
    // CALCULS
    // =====================================================================
    
    // Tableau pour le calendrier
    $calendrier = [];
    $jours_presents = 0;
    $jours_absents = 0;
    $jours_retard = 0;
    $total_heures = 0;
    $total_retard = 0;
    
    for ($jour = 1; $jour <= $jours_dans_mois; $jour++) {
        $date = date('Y-m-d', strtotime($month . '-' . str_pad($jour, 2, '0', STR_PAD_LEFT)));
        $jour_semaine = date('N', strtotime($date)); // 1=lundi, 7=dimanche
        
        // Chercher la présence
        $presence = null;
        foreach ($presences as $p) {
            if ($p['date_presence'] == $date) {
                $presence = $p;
                break;
            }
        }
        
        // Type de jour
        if ($presence) {
            if ($presence['retard_minutes'] > 0) {
                $type = 'retard';
                $jours_retard++;
            } else {
                $type = 'present';
            }
            $jours_presents++;
            
            // Calcul des heures travaillées
            $heures_travaillees = 0;
            if ($presence['heure_debut_reel'] && $presence['heure_fin_reel']) {
                $debut = strtotime($presence['heure_debut_reel']);
                $fin = strtotime($presence['heure_fin_reel']);
                
                // Ajustement début
                $debut_normal = strtotime($heure_debut_normal);
                if ($debut < $debut_normal) $debut = $debut_normal;
                
                // Durée
                $duree_secondes = $fin - $debut;
                $duree_heures = $duree_secondes / 3600;
                
                // Pause 1h
                $duree_heures -= 1;
                
                // Maximum 8h
                if ($duree_heures > 8) $duree_heures = 8;
                
                if ($duree_heures > 0) {
                    $heures_travaillees = $duree_heures;
                    $total_heures += $duree_heures;
                }
            }
            
            $total_retard += $presence['retard_minutes'];
            
        } elseif ($jour_semaine >= 6) { // Weekend
            $type = 'weekend';
        } else {
            $type = 'absent';
            $jours_absents++;
        }
        
        $calendrier[$jour] = [
            'date' => $date,
            'jour_semaine' => $jour_semaine,
            'type' => $type,
            'presence' => $presence,
            'heures' => $heures_travaillees ?? 0,
            'retard' => $presence['retard_minutes'] ?? 0
        ];
    }
    
    // Calcul du salaire
    $salaire_brut = 0;
    $taux_horaire = $employe['salaire_horaire'] ?? 0;
    
    if ($taux_horaire > 0 && $total_heures > 0) {
        $salaire_brut = $total_heures * $taux_horaire;
    }
    
    // Arrondi
    $salaire_brut = round($salaire_brut, 0);
    $montant_final = $paiement ? $paiement['montant'] : $salaire_brut;
    
    // =====================================================================
    // GÉNÉRATION DU HTML POUR LE PDF
    // =====================================================================
    
    // CSS inline pour éviter les problèmes de chargement
    $css = '
    <style>
        /* RESET */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            color: #333333; 
            line-height: 1.4;
            background: #ffffff;
            padding: 0;
            margin: 0;
        }
        
        /* CONTAINER */
        .container { 
            max-width: 210mm; 
            margin: 0 auto; 
            padding: 15mm;
        }
        
        /* HEADER */
        .header {
            background: #2c3e50;
            color: white;
            padding: 25px 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .header-info div {
            flex: 1;
            min-width: 200px;
        }
        
        .header-info-label {
            font-size: 12px;
            opacity: 0.7;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .header-info-value {
            font-size: 16px;
            font-weight: bold;
        }
        
        /* SECTIONS */
        .section {
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #3498db;
        }
        
        /* GRIDS */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .info-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* STATISTIQUES */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 13px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        /* CALENDRIER */
        .calendrier-container {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .calendrier-legende {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .legende-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #555;
        }
        
        .legende-couleur {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .present { background: #2ecc71; }
        .retard { background: #f39c12; }
        .absent { background: #e74c3c; }
        .weekend { background: #95a5a6; }
        
        .calendrier-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-top: 15px;
        }
        
        .calendrier-header {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            color: #2c3e50;
            padding: 10px;
            background: #ecf0f1;
            border-radius: 4px;
            text-transform: uppercase;
        }
        
        .calendrier-jour {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-weight: bold;
            border: 2px solid transparent;
        }
        
        .calendrier-jour.present { 
            background: #d5f4e6;
            border-color: #2ecc71;
            color: #27ae60;
        }
        
        .calendrier-jour.retard { 
            background: #fdebd0;
            border-color: #f39c12;
            color: #d68910;
        }
        
        .calendrier-jour.absent { 
            background: #fadbd8;
            border-color: #e74c3c;
            color: #c0392b;
        }
        
        .calendrier-jour.weekend { 
            background: #ebedef;
            border-color: #95a5a6;
            color: #7f8c8d;
        }
        
        .jour-numero {
            font-size: 16px;
            font-weight: bold;
        }
        
        .jour-details {
            font-size: 9px;
            margin-top: 4px;
            text-align: center;
            line-height: 1.2;
        }
        
        .jour-vide {
            aspect-ratio: 1;
            visibility: hidden;
        }
        
        /* TABLEAU SALAIRE */
        .salaire-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        
        .salaire-table thead {
            background: #3498db;
        }
        
        .salaire-table th {
            color: white;
            font-weight: bold;
            padding: 16px 12px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .salaire-table td {
            padding: 14px 12px;
            font-size: 13px;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .salaire-table .montant {
            font-family: "Courier New", monospace;
            font-weight: bold;
            text-align: right;
        }
        
        .total-row {
            background: #2c3e50 !important;
            color: white !important;
            font-weight: bold !important;
            font-size: 14px !important;
        }
        
        .total-row td {
            color: white !important;
            font-weight: bold;
        }
        
        /* SIGNATURES */
        .signatures-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .signature-block {
            text-align: center;
            padding: 20px;
        }
        
        .signature-line {
            width: 250px;
            height: 1px;
            background: #2c3e50;
            margin: 40px auto 15px;
        }
        
        .signature-name {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 10px;
        }
        
        .signature-title {
            font-size: 13px;
            color: #7f8c8d;
            font-style: italic;
        }
        
        /* FOOTER */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 11px;
            color: #95a5a6;
            line-height: 1.6;
        }
        
        /* PRINT */
        @media print {
            body { 
                font-size: 12pt !important;
                line-height: 1.3 !important;
            }
            
            .container { 
                padding: 10mm !important;
                max-width: 100% !important;
            }
            
            .header {
                padding: 15px 20px !important;
            }
            
            .title {
                font-size: 24pt !important;
            }
            
            .stat-card {
                break-inside: avoid;
            }
        }
    </style>';
    
    // HTML complet
    $html = '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Fiche de Paie - ' . htmlspecialchars($employe['nom']) . ' - ' . $nom_mois . '</title>
        ' . $css . '
    </head>
    <body>
        <div class="container">
            
            <!-- EN-TÊTE -->
            <div class="header">
                <h1 class="title">FICHE DE PAIE</h1>
                <div class="subtitle">' . $nom_mois . '</div>
                
                <div class="header-info">
                    <div>
                        <div class="header-info-label">Employeur</div>
                        <div class="header-info-value">' . htmlspecialchars($entreprise['nom']) . '</div>
                    </div>
                    <div>
                        <div class="header-info-label">Employé</div>
                        <div class="header-info-value">' . htmlspecialchars($employe['nom']) . '</div>
                    </div>
                    <div>
                        <div class="header-info-label">Référence</div>
                        <div class="header-info-value">FP-' . strtoupper(date('Ymd', strtotime($debut_mois))) . '-' . $user_id . '</div>
                    </div>
                </div>
            </div>
            
            <!-- INFORMATIONS ENTREPRISE -->
            <div class="section">
                <h2 class="section-title">Informations Entreprise</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Raison sociale</div>
                        <div class="info-value">' . htmlspecialchars($entreprise['nom']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Adresse</div>
                        <div class="info-value">
                            ' . htmlspecialchars($entreprise['adresse'] ?? '') . '<br>
                            ' . htmlspecialchars($entreprise['code_postal'] ?? '') . ' ' . htmlspecialchars($entreprise['ville'] ?? '') . '
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contact</div>
                        <div class="info-value">
                            ' . htmlspecialchars($entreprise['telephone'] ?? '') . '<br>
                            ' . htmlspecialchars($entreprise['email'] ?? '') . '
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Identifiants légaux</div>
                        <div class="info-value">
                            Fiscal: ' . htmlspecialchars($entreprise['numero_fiscal'] ?? '') . '<br>
                            CNPS: ' . htmlspecialchars($entreprise['numero_cnps'] ?? '') . '
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- INFORMATIONS EMPLOYÉ -->
            <div class="section">
                <h2 class="section-title">Informations Employé</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nom complet</div>
                        <div class="info-value">' . htmlspecialchars($employe['nom']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Poste occupé</div>
                        <div class="info-value">' . htmlspecialchars($employe['poste_nom']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Période de paie</div>
                        <div class="info-value">' . $nom_mois . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date d\'embauche</div>
                        <div class="info-value">' . date('d/m/Y', strtotime($employe['created_at'])) . '</div>
                    </div>
                </div>
            </div>
            
            <!-- STATISTIQUES -->
            <div class="section">
                <h2 class="section-title">Statistiques de présence</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #27ae60;">' . $jours_presents . '</div>
                        <div class="stat-label">Jours présents</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e74c3c;">' . $jours_absents . '</div>
                        <div class="stat-label">Jours absents</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f39c12;">' . $jours_retard . '</div>
                        <div class="stat-label">Jours en retard</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value" style="color: #3498db;">' . number_format($total_heures, 1) . 'h</div>
                        <div class="stat-label">Heures travaillées</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value" style="color: #9b59b6;">' . floor($total_retard / 60) . 'h' . ($total_retard % 60) . '</div>
                        <div class="stat-label">Retard total</div>
                    </div>
                </div>
            </div>
            
            <!-- CALENDRIER -->
            <div class="section">
                <h2 class="section-title">Calendrier du mois</h2>
                
                <div class="calendrier-container">
                    <div class="calendrier-legende">
                        <div class="legende-item">
                            <div class="legende-couleur present"></div>
                            <span>Présent</span>
                        </div>
                        <div class="legende-item">
                            <div class="legende-couleur retard"></div>
                            <span>Avec retard</span>
                        </div>
                        <div class="legende-item">
                            <div class="legende-couleur absent"></div>
                            <span>Absent</span>
                        </div>
                        <div class="legende-item">
                            <div class="legende-couleur weekend"></div>
                            <span>Weekend</span>
                        </div>
                    </div>
                    
                    <div class="calendrier-grid">
                        <!-- En-têtes -->
                        <div class="calendrier-header">Lun</div>
                        <div class="calendrier-header">Mar</div>
                        <div class="calendrier-header">Mer</div>
                        <div class="calendrier-header">Jeu</div>
                        <div class="calendrier-header">Ven</div>
                        <div class="calendrier-header">Sam</div>
                        <div class="calendrier-header">Dim</div>';
    
    // Premier jour du mois
    $premier_jour = date('N', strtotime($debut_mois));
    
    // Jours vides avant début
    for ($i = 1; $i < $premier_jour; $i++) {
        $html .= '<div class="jour-vide"></div>';
    }
    
    // Jours du mois
    foreach ($calendrier as $jour_num => $jour_data) {
        $details = '';
        if ($jour_data['type'] == 'present' || $jour_data['type'] == 'retard') {
            if ($jour_data['heures'] > 0) {
                $details .= number_format($jour_data['heures'], 1) . 'h';
            }
            if ($jour_data['retard'] > 0) {
                $details .= $details ? '<br>' : '';
                $details .= '+' . floor($jour_data['retard'] / 60) . 'h' . ($jour_data['retard'] % 60);
            }
        }
        
        $html .= '
                        <div class="calendrier-jour ' . $jour_data['type'] . '">
                            <div class="jour-numero">' . $jour_num . '</div>';
        
        if ($details) {
            $html .= '<div class="jour-details">' . $details . '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '
                    </div>
                </div>
            </div>
            
            <!-- SALAIRE -->
            <div class="section">
                <h2 class="section-title">Détail de la rémunération</h2>
                
                <table class="salaire-table">
                    <thead>
                        <tr>
                            <th width="40%">Description</th>
                            <th width="20%">Taux horaire</th>
                            <th width="20%">Heures</th>
                            <th width="20%">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Salaire de base</td>
                            <td>' . number_format($taux_horaire, 0, ',', ' ') . ' FCFA/h</td>
                            <td>' . number_format($total_heures, 1) . ' heures</td>
                            <td class="montant">' . number_format($salaire_brut, 0, ',', ' ') . ' FCFA</td>
                        </tr>';
    
    // Ajouter les retenues si paiement existant
    if ($paiement && $paiement['montant'] < $salaire_brut) {
        $difference = $salaire_brut - $paiement['montant'];
        $html .= '
                        <tr style="background: #fff5f5;">
                            <td>Retenues diverses</td>
                            <td>-</td>
                            <td>-</td>
                            <td class="montant" style="color: #e74c3c;">-' . number_format($difference, 0, ',', ' ') . ' FCFA</td>
                        </tr>';
    }
    
    $html .= '
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right; padding-right: 20px;">
                                <strong>NET À PAYER</strong>
                            </td>
                            <td class="montant" style="font-size: 18px;">
                                <strong>' . number_format($montant_final, 0, ',', ' ') . ' FCFA</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>';
    
    // Information de paiement
    if ($paiement) {
        $html .= '
                <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 6px; border-left: 4px solid #3498db;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 24px; height: 24px; background: #3498db; border-radius: 50%; text-align: center; line-height: 24px; color: white;">✓</div>
                        <div>
                            <div style="font-weight: bold; color: #2c3e50;">Paiement effectué</div>
                            <div style="font-size: 13px; color: #7f8c8d;">
                                Le ' . date('d/m/Y à H:i', strtotime($paiement['date_paiement'])) . ' 
                                par ' . htmlspecialchars($paiement['methode_paiement']) . '
                                ' . ($paiement['reference_paiement'] ? '(Réf: ' . htmlspecialchars($paiement['reference_paiement']) . ')' : '') . '
                            </div>
                        </div>
                    </div>
                </div>';
    }
    
    $html .= '
            </div>
            
            <!-- SIGNATURES -->
            <div class="signatures-section">
                <div class="signature-block">
                    <div style="font-weight: bold; color: #2c3e50; margin-bottom: 10px;">POUR L\'EMPLOYEUR</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">' . htmlspecialchars($entreprise['nom']) . '</div>
                    <div class="signature-title">Direction Générale</div>
                </div>
                
                <div class="signature-block">
                    <div style="font-weight: bold; color: #2c3e50; margin-bottom: 10px;">POUR L\'EMPLOYÉ</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">' . htmlspecialchars($employe['nom']) . '</div>
                    <div class="signature-title">Employé</div>
                </div>
            </div>
            
            <!-- FOOTER -->
            <div class="footer">
                <div>Document généré le ' . date('d/m/Y à H:i:s') . '</div>
                <div>' . htmlspecialchars($entreprise['nom']) . ' - ' . htmlspecialchars($entreprise['adresse'] ?? '') . ' - ' . htmlspecialchars($entreprise['ville'] ?? '') . '</div>
                <div style="margin-top: 10px; font-size: 10px; color: #bdc3c7;">
                    Ce document a une valeur légale. Toute reproduction ou falsification est interdite et passible de poursuites.
                </div>
            </div>
            
        </div>
    </body>
    </html>';
    
    // =====================================================================
    // GÉNÉRATION DU PDF AVEC DOMPDF
    // =====================================================================
    
    // Configuration de DomPDF
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false);
    $options->set('isJavascriptEnabled', false);
    $options->set('chroot', realpath(__DIR__ . '/../'));
    
    // Créer l'instance DomPDF
    $dompdf = new Dompdf($options);
    
    // Charger le HTML
    $dompdf->loadHtml($html, 'UTF-8');
    
    // Définir le format A4 portrait
    $dompdf->setPaper('A4', 'portrait');
    
    // Rendre le PDF
    $dompdf->render();
    
    // Nom du fichier
    $filename = 'Fiche_Paie_' . preg_replace('/[^a-zA-Z0-9]/', '_', $employe['nom']) . '_' . date('Y_m', strtotime($month)) . '.pdf';
    
    // Envoyer au navigateur
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    
    // Output the PDF
    echo $dompdf->output();
    
    exit;
    
} catch (Exception $e) {
    // Page d'erreur détaillée
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur génération PDF</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f8f9fa; }
            .error-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); border-left: 5px solid #e74c3c; }
            h1 { color: #e74c3c; margin-bottom: 20px; }
            .error-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 12px; overflow: auto; }
            .solution { background: #e8f6ff; padding: 20px; border-radius: 5px; border-left: 4px solid #3498db; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>❌ Erreur lors de la génération du PDF</h1>
            
            <div class="error-details">
                <p><strong>Message :</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p><strong>Fichier :</strong> ' . htmlspecialchars($e->getFile()) . '</p>
                <p><strong>Ligne :</strong> ' . $e->getLine() . '</p>
            </div>
            
            <div class="solution">
                <h3>🛠️ Solutions :</h3>
                <ol>
                    <li><strong>Vérifiez l\'installation :</strong><br>
                        Exécutez : <code>composer require dompdf/dompdf</code> à la racine</li>
                    <li><strong>Vérifiez les permissions :</strong><br>
                        <code>chmod 755 vendor</code></li>
                    <li><strong>Vérifiez la version PHP :</strong><br>
                        DomPDF nécessite PHP 7.1 ou supérieur</li>
                </ol>
            </div>
            
            <a href="fiche.php" class="btn">← Retour à la page des fiches de paie</a>
        </div>
    </body>
    </html>';
}
?>
