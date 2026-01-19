<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Vérification Admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php'); exit;
}

// =====================================================================
// 1. CHARGEMENT DES CONFIGURATIONS
// =====================================================================

// A. Taux Fiscaux (Cameroun)
$taux = $pdo->query("SELECT * FROM config_paie_cameroun WHERE id = 1")->fetch();
if (!$taux) {
    // Initialisation si vide
    $pdo->exec("INSERT INTO config_paie_cameroun (id) VALUES (1)");
    $taux = $pdo->query("SELECT * FROM config_paie_cameroun WHERE id = 1")->fetch();
}

// B. Infos Entreprise
$entreprise = $pdo->query("SELECT * FROM entreprise_infos LIMIT 1")->fetch();

// C. Paramètres Système (Heures de travail)
$sys_params = $pdo->query("SELECT heure_debut_normal, heure_fin_normal FROM parametres_systeme LIMIT 1")->fetch();
$h_deb_norm = $sys_params['heure_debut_normal'] ?? '08:30:00';
$h_fin_norm = $sys_params['heure_fin_normal'] ?? '17:30:00';

// D. Inputs Utilisateur
$selected_user_id = $_GET['user_id'] ?? null;
$selected_month = $_GET['month'] ?? date('Y-m');
$prime_13 = isset($_GET['prime_13']) ? true : false;
$prime_14 = isset($_GET['prime_14']) ? true : false;

$fiche = null;
$calendrier = [];

// =====================================================================
// 2. TRAITEMENT DES FORMULAIRES (POST)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- A. MISE À JOUR DES TAUX LÉGAUX ---
    if (isset($_POST['update_taux'])) {
        $sql = "UPDATE config_paie_cameroun SET 
                taux_cnps_salarie=?, plafond_cnps=?, taux_cfc_salarie=?, 
                taux_cac=?, abattement_frais_pro=?, updated_at=NOW() WHERE id=1";
        $pdo->prepare($sql)->execute([
            $_POST['cnps'], $_POST['plafond'], $_POST['cfc'], 
            $_POST['cac'], $_POST['abattement']
        ]);
        
        // Redirection pour éviter la resoumission
        $params = http_build_query($_GET);
        header("Location: fiche.php?$params&msg=taux_ok"); exit;
    }
    
    // --- B. MISE À JOUR ENTREPRISE (CORRECTION PDO STRICTE) ---
    if (isset($_POST['update_entreprise'])) {
        $nom = $_POST['nom'];
        $adresse = $_POST['adresse'];
        $ville = $_POST['ville'];
        $telephone = $_POST['telephone'];
        $email = $_POST['email'];
        $niu = $_POST['niu'];
        $cnps_num = $_POST['cnps_num'];
        $rccm = $_POST['rccm'];
        
        $logoPath = null;
        
        // Upload Logo
        if (!empty($_FILES['logo']['name'])) {
            $uploadDir = '../uploads/logos/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $filename = time() . '_' . basename($_FILES['logo']['name']);
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                $logoPath = 'uploads/logos/' . $filename;
            }
        }
        
        if ($entreprise) {
            // UPDATE
            $sql = "UPDATE entreprise_infos SET nom=?, adresse=?, ville=?, telephone=?, email=?, numero_fiscal=?, numero_cnps=?, registre_commerce=?";
            $params = [$nom, $adresse, $ville, $telephone, $email, $niu, $cnps_num, $rccm];
            
            if ($logoPath) {
                $sql .= ", logo=?";
                $params[] = $logoPath;
            }
            
            $sql .= " WHERE id=?";
            $params[] = $entreprise['id'];
            $pdo->prepare($sql)->execute($params);
        } else {
            // INSERT
            $sql = "INSERT INTO entreprise_infos (nom, adresse, ville, telephone, email, numero_fiscal, numero_cnps, registre_commerce, logo) VALUES (?,?,?,?,?,?,?,?,?)";
            $params = [$nom, $adresse, $ville, $telephone, $email, $niu, $cnps_num, $rccm, $logoPath];
            $pdo->prepare($sql)->execute($params);
        }
        
        $params = http_build_query($_GET);
        header("Location: fiche.php?$params&msg=ent_ok"); exit;
    }
}

// =====================================================================
// 3. MOTEUR DE CALCUL (IDENTIQUE À SALAIRE.PHP)
// =====================================================================
if ($selected_user_id) {
    // Info Employé
    $stmt = $pdo->prepare("
        SELECT u.*, p.nom as poste, ps.salaire_horaire 
        FROM users u 
        JOIN postes p ON u.poste_id=p.id 
        JOIN parametres_salaire ps ON p.id=ps.poste_id 
        WHERE u.id=?
    ");
    $stmt->execute([$selected_user_id]);
    $user = $stmt->fetch();

    if ($user) {
        $start = date('Y-m-01', strtotime($selected_month));
        $end = date('Y-m-t', strtotime($selected_month));
        
        // Récupération des présences
        $presences = $pdo->prepare("SELECT * FROM presences WHERE user_id=? AND date_presence BETWEEN ? AND ?");
        $presences->execute([$selected_user_id, $start, $end]);
        $rows = $presences->fetchAll();

        $total_h = 0;
        $jours_pres = 0;
        
        // --- BOUCLE DE CALCUL STRICTE (SALAIRE.PHP LOGIC) ---
        foreach($rows as $p) {
            if ($p['heure_debut_reel']) {
                // 1. Ajustement début (pas avant 08:30)
                $d = strtotime($p['heure_debut_reel']);
                $d_ref = strtotime($h_deb_norm);
                if ($d < $d_ref) $d = $d_ref;

                // 2. Ajustement fin (défaut 17:30 si oubli)
                $f = $p['heure_fin_reel'] ? strtotime($p['heure_fin_reel']) : strtotime($h_fin_norm);

                // 3. Calcul durée brut
                $duration = ($f - $d) / 3600;

                // 4. Déduction pause (1h)
                $duration -= 1;

                // 5. Plafond journalier (8h)
                if ($duration > 8) $duration = 8;

                // Somme si positif
                if ($duration > 0) {
                    $total_h += $duration;
                    $jours_pres++;
                }
            }
        }

        // --- CALCUL FINANCIER (CAMEROUN) ---
        
        // 1. Salaire de base
        $salaire_base = round($total_h * $user['salaire_horaire']);
        $brut_total = $salaire_base;

        // 2. Primes 13e/14e (Sur base théorique mensuelle)
        $salaire_theorique = 22 * 8 * $user['salaire_horaire'];
        $mt_p13 = $prime_13 ? $salaire_theorique : 0;
        $mt_p14 = $prime_14 ? $salaire_theorique : 0;
        $brut_total += $mt_p13 + $mt_p14;

        // 3. Retenues Sociales
        // CNPS (Plafonnée)
        $base_cnps = min($brut_total, $taux['plafond_cnps']);
        $cnps = round($base_cnps * ($taux['taux_cnps_salarie'] / 100));

        // 4. Fiscalité (IRPP & CAC)
        // Brut Taxable
        $brut_taxable = $brut_total - $cnps;
        
        // Abattement Frais Pro (30%)
        $abattement = round($brut_taxable * ($taux['abattement_frais_pro'] / 100));
        $net_taxable = $brut_taxable - $abattement;
        if ($net_taxable < 0) $net_taxable = 0;

        // Barème IRPP (Progressif Mensuel)
        $irpp = 0;
        $tb = $net_taxable;
        
        if ($tb > 416666) { $irpp += ($tb - 416666) * 0.35; $tb = 416666; }
        if ($tb > 250000) { $irpp += ($tb - 250000) * 0.25; $tb = 250000; }
        if ($tb > 125000) { $irpp += ($tb - 125000) * 0.15; $tb = 125000; }
        if ($tb > 62000)  { $irpp += ($tb - 62000) * 0.10; } // Tranche basse
        
        $irpp = floor($irpp);

        // CAC (10% de l'IRPP)
        $cac = round($irpp * ($taux['taux_cac'] / 100));

        // CFC (Crédit Foncier : 1% du Brut Taxable ou Brut Total selon interprétation, ici Taxable pour OHADA std)
        $cfc = round($brut_taxable * ($taux['taux_cfc_salarie'] / 100));

        // 5. Net à Payer
        $total_retenues = $cnps + $irpp + $cac + $cfc;
        $net_a_payer = $brut_total - $total_retenues;

        $fiche = [
            'base' => $salaire_base,
            'heures' => $total_h,
            'p13' => $mt_p13,
            'p14' => $mt_p14,
            'brut' => $brut_total,
            'cnps' => $cnps,
            'cfc' => $cfc,
            'irpp' => $irpp,
            'cac' => $cac,
            'net_taxable' => $net_taxable,
            'net' => $net_a_payer
        ];
    }
}

// Liste Employés
$all_users = $pdo->query("SELECT id, nom FROM users WHERE is_admin=0 ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Génération Paie Pro - Ziris</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { margin: 0; font-size: 24px; color: #2c3e50; }
        
        .main-grid { display: grid; grid-template-columns: 320px 1fr; gap: 25px; }
        
        /* Sidebar Controls */
        .controls-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        
        /* Preview Panel */
        .preview-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); min-height: 500px; }
        .bulletin-header { border-bottom: 2px solid #2c3e50; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        
        .rubrique-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .rubrique-table th { background: #f1f2f6; text-align: left; padding: 10px; border-bottom: 2px solid #dfe4ea; font-size: 12px; text-transform: uppercase; color: #57606f; }
        .rubrique-table td { padding: 10px; border-bottom: 1px solid #f1f2f6; font-size: 14px; }
        .amount { text-align: right; font-family: 'Courier New', monospace; font-weight: bold; }
        
        .total-block { background: #2c3e50; color: white; padding: 15px; margin-top: 20px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; font-size: 18px; font-weight: bold; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; width: 500px; padding: 25px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .close-btn { cursor: pointer; font-size: 24px; color: #999; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        
        <div class="page-header">
            <div>
                <h1><i class="fas fa-file-invoice"></i> Gestion de la Paie</h1>
                <p style="margin: 5px 0 0 0; color: #7f8c8d;">Conformité OHADA & Code Général des Impôts (Cameroun)</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="openModal('modalEntreprise')"><i class="fas fa-building"></i> Configurer Entreprise</button>
                <button class="btn btn-secondary" onclick="openModal('modalTaux')"><i class="fas fa-sliders-h"></i> Taux & Taxes</button>
            </div>
        </div>

        <div class="main-grid">
            <div class="controls-card">
                <h3><i class="fas fa-filter"></i> Sélection</h3>
                <form method="GET" id="paieForm">
                    <div class="form-group">
                        <label>Employé</label>
                        <select name="user_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach($all_users as $u) echo "<option value='{$u['id']}' ".($selected_user_id==$u['id']?'selected':'').">{$u['nom']}</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Période</label>
                        <input type="month" name="month" class="form-control" value="<?php echo $selected_month; ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; border: 1px solid #e9ecef;">
                        <label style="font-weight: bold; display: block; margin-bottom: 10px;">Gratifications</label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                            <input type="checkbox" name="prime_13" <?php echo $prime_13 ? 'checked' : ''; ?> onchange="this.form.submit()"> 
                            13ème Mois
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="prime_14" <?php echo $prime_14 ? 'checked' : ''; ?> onchange="this.form.submit()"> 
                            14ème Mois
                        </label>
                    </div>
                </form>

                <?php if ($fiche): ?>
                <div style="margin-top: 25px;">
                    <form action="generate_pdf.php" method="POST" target="_blank">
                        <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                        <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                        <input type="hidden" name="prime_13" value="<?php echo $prime_13 ? 1 : 0; ?>">
                        <input type="hidden" name="prime_14" value="<?php echo $prime_14 ? 1 : 0; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                            <i class="fas fa-file-pdf"></i> Télécharger le PDF
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div class="preview-card">
                <?php if ($fiche): ?>
                    <div class="bulletin-header">
                        <div>
                            <h2 style="margin: 0; color: #2c3e50;">BULLETIN DE PAIE</h2>
                            <div style="margin-top: 5px; color: #7f8c8d;">Période : <?php echo date('F Y', strtotime($selected_month)); ?></div>
                        </div>
                        <div style="text-align: right;">
                            <strong style="font-size: 16px;"><?php echo htmlspecialchars($entreprise['nom'] ?? 'MON ENTREPRISE'); ?></strong><br>
                            <?php echo htmlspecialchars($entreprise['ville'] ?? 'Douala'); ?>, Cameroun
                        </div>
                    </div>

                    <div style="background: #f1f2f6; padding: 15px; border-radius: 4px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 13px;">
                        <div>
                            <strong>Nom :</strong> <?php echo htmlspecialchars($user['nom']); ?><br>
                            <strong>Poste :</strong> <?php echo htmlspecialchars($user['poste']); ?><br>
                            <strong>Matricule :</strong> <?php echo $user['id']; ?>
                        </div>
                        <div>
                            <strong>Taux Horaire :</strong> <?php echo number_format($user['salaire_horaire'], 0, ',', ' '); ?> FCFA<br>
                            <strong>Heures Travaillées :</strong> <?php echo $fiche['heures']; ?> h (Calculé)
                        </div>
                    </div>

                    <table class="rubrique-table">
                        <thead>
                            <tr>
                                <th>Rubrique</th>
                                <th style="text-align: center;">Base / Taux</th>
                                <th class="amount">Gains</th>
                                <th class="amount">Retenues</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Salaire de base</td>
                                <td style="text-align: center;"><?php echo $fiche['heures']; ?> h</td>
                                <td class="amount"><?php echo number_format($fiche['base'], 0, ',', ' '); ?></td>
                                <td></td>
                            </tr>
                            <?php if ($prime_13): ?>
                            <tr>
                                <td>Prime 13ème mois</td>
                                <td style="text-align: center;">Forfait</td>
                                <td class="amount"><?php echo number_format($fiche['p13'], 0, ',', ' '); ?></td>
                                <td></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($prime_14): ?>
                            <tr>
                                <td>Prime 14ème mois</td>
                                <td style="text-align: center;">Forfait</td>
                                <td class="amount"><?php echo number_format($fiche['p14'], 0, ',', ' '); ?></td>
                                <td></td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td>TOTAL BRUT</td>
                                <td></td>
                                <td class="amount"><?php echo number_format($fiche['brut'], 0, ',', ' '); ?></td>
                                <td></td>
                            </tr>

                            <tr><td colspan="4" style="border: none; height: 10px;"></td></tr>
                            
                            <tr>
                                <td>CNPS (Part Salariale)</td>
                                <td style="text-align: center;"><?php echo $taux['taux_cnps_salarie']; ?> %</td>
                                <td></td>
                                <td class="amount" style="color: #c0392b;"><?php echo number_format($fiche['cnps'], 0, ',', ' '); ?></td>
                            </tr>
                            <tr>
                                <td>Crédit Foncier (CFC)</td>
                                <td style="text-align: center;"><?php echo $taux['taux_cfc_salarie']; ?> %</td>
                                <td></td>
                                <td class="amount" style="color: #c0392b;"><?php echo number_format($fiche['cfc'], 0, ',', ' '); ?></td>
                            </tr>
                            <tr>
                                <td>IRPP (Impôt Revenu)</td>
                                <td style="text-align: center;">Barème</td>
                                <td></td>
                                <td class="amount" style="color: #c0392b;"><?php echo number_format($fiche['irpp'], 0, ',', ' '); ?></td>
                            </tr>
                            <tr>
                                <td>CAC (Centimes Additionnels)</td>
                                <td style="text-align: center;"><?php echo $taux['taux_cac']; ?> % (IRPP)</td>
                                <td></td>
                                <td class="amount" style="color: #c0392b;"><?php echo number_format($fiche['cac'], 0, ',', ' '); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="total-block">
                        <span>NET À PAYER</span>
                        <span><?php echo number_format($fiche['net'], 0, ',', ' '); ?> FCFA</span>
                    </div>

                <?php else: ?>
                    <div style="text-align: center; padding: 100px 0; color: #bdc3c7;">
                        <i class="fas fa-file-invoice-dollar" style="font-size: 48px; margin-bottom: 20px;"></i>
                        <p>Veuillez sélectionner un employé et un mois pour générer l'aperçu.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="modalEntreprise" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Infos Entreprise</h3>
                <span class="close-btn" onclick="closeModal('modalEntreprise')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_entreprise" value="1">
                <div class="form-group"><label>Raison Sociale</label><input type="text" name="nom" class="form-control" value="<?php echo $entreprise['nom'] ?? ''; ?>" required></div>
                <div class="form-group"><label>Adresse</label><input type="text" name="adresse" class="form-control" value="<?php echo $entreprise['adresse'] ?? ''; ?>"></div>
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div><label>Ville</label><input type="text" name="ville" class="form-control" value="<?php echo $entreprise['ville'] ?? ''; ?>"></div>
                    <div><label>Téléphone</label><input type="text" name="telephone" class="form-control" value="<?php echo $entreprise['telephone'] ?? ''; ?>"></div>
                </div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo $entreprise['email'] ?? ''; ?>"></div>
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                    <div><label>NIU</label><input type="text" name="niu" class="form-control" value="<?php echo $entreprise['numero_fiscal'] ?? ''; ?>"></div>
                    <div><label>RCCM</label><input type="text" name="rccm" class="form-control" value="<?php echo $entreprise['registre_commerce'] ?? ''; ?>"></div>
                    <div><label>CNPS</label><input type="text" name="cnps_num" class="form-control" value="<?php echo $entreprise['numero_cnps'] ?? ''; ?>"></div>
                </div>
                <div class="form-group"><label>Logo (Optionnel)</label><input type="file" name="logo" class="form-control"></div>
                <button type="submit" class="btn btn-success" style="width: 100%;">Enregistrer</button>
            </form>
        </div>
    </div>

    <div id="modalTaux" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Configuration Fiscale (Cameroun)</h3>
                <span class="close-btn" onclick="closeModal('modalTaux')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="update_taux" value="1">
                <div class="form-group"><label>Plafond Cotisation CNPS (FCFA)</label><input type="number" name="plafond" class="form-control" value="<?php echo $taux['plafond_cnps']; ?>"></div>
                <div class="form-group"><label>Taux CNPS Ouvrier (%)</label><input type="number" step="0.01" name="cnps" class="form-control" value="<?php echo $taux['taux_cnps_salarie']; ?>"></div>
                <div class="form-group"><label>Taux Crédit Foncier - CFC (%)</label><input type="number" step="0.01" name="cfc" class="form-control" value="<?php echo $taux['taux_cfc_salarie']; ?>"></div>
                <div class="form-group"><label>Taux CAC sur IRPP (%)</label><input type="number" step="0.01" name="cac" class="form-control" value="<?php echo $taux['taux_cac']; ?>"></div>
                <div class="form-group"><label>Abattement Frais Pro (%)</label><input type="number" step="0.01" name="abattement" class="form-control" value="<?php echo $taux['abattement_frais_pro']; ?>"></div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Mettre à jour les taux</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) { event.target.style.display = 'none'; }
        }
    </script>
</body>
</html>