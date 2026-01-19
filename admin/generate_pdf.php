<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) { die("Accès refusé"); }

// --- CHARGEMENT LIBRAIRIES ---
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) die("Veuillez installer DomPDF via Composer");
require_once $vendorAutoload;
use Dompdf\Dompdf;
use Dompdf\Options;

// --- INPUTS ---
$user_id = $_POST['user_id'] ?? null;
$month = $_POST['month'] ?? date('Y-m');
$prime_13 = $_POST['prime_13'] ?? 0;
$prime_14 = $_POST['prime_14'] ?? 0;

if (!$user_id) die("Utilisateur manquant");

// --- DONNÉES ---
$entreprise = $pdo->query("SELECT * FROM entreprise_infos LIMIT 1")->fetch();
$taux = $pdo->query("SELECT * FROM config_paie_cameroun LIMIT 1")->fetch();
$sys = $pdo->query("SELECT heure_debut_normal, heure_fin_normal FROM parametres_systeme LIMIT 1")->fetch();
$h_deb = $sys['heure_debut_normal'] ?? '08:30:00';
$h_fin = $sys['heure_fin_normal'] ?? '17:30:00';

$sql_user = "SELECT u.*, p.nom as poste, ps.salaire_horaire FROM users u JOIN postes p ON u.poste_id=p.id JOIN parametres_salaire ps ON p.id=ps.poste_id WHERE u.id=?";
$employe = $pdo->prepare($sql_user);
$employe->execute([$user_id]);
$e = $employe->fetch();

// --- CALCULS (Réplique exacte de fiche.php) ---
$start = date('Y-m-01', strtotime($month));
$end = date('Y-m-t', strtotime($month));
$presences = $pdo->prepare("SELECT * FROM presences WHERE user_id=? AND date_presence BETWEEN ? AND ?");
$presences->execute([$user_id, $start, $end]);
$rows = $presences->fetchAll();

$total_h = 0;
$calendrier = [];
$nb_jours_mois = date('t', strtotime($month));

// Init calendrier vide
for($i=1; $i<=$nb_jours_mois; $i++) $calendrier[$i] = ['t'=>'abs', 'v'=>0];

foreach($rows as $p) {
    $d = strtotime(max($h_deb, $p['heure_debut_reel']));
    $f = strtotime($p['heure_fin_reel'] ?: $h_fin);
    $h = max(0, (($f - $d) / 3600) - 1);
    
    $j = date('j', strtotime($p['date_presence']));
    if ($h > 0) {
        $real_h = min(8, $h);
        $total_h += $real_h;
        $calendrier[$j] = ['t'=>'pres', 'v'=>$real_h];
    }
}

// Salaire
$salaire_base = round($total_h * $e['salaire_horaire']);
$brut_total = $salaire_base;

// Primes
$salaire_mensuel_fixe = 22 * 8 * $e['salaire_horaire']; // Base de calcul des primes
$mt_p13 = $prime_13 ? $salaire_mensuel_fixe : 0;
$mt_p14 = $prime_14 ? $salaire_mensuel_fixe : 0;
$brut_total += $mt_p13 + $mt_p14;

// Fiscalité Cameroun
$base_cnps = min($brut_total, $taux['plafond_cnps']);
$cnps = round($base_cnps * ($taux['taux_cnps_salarie'] / 100));

$brut_taxable = $brut_total - $cnps;
$abattement = round($brut_taxable * ($taux['abattement_frais_pro'] / 100));
$net_taxable = $brut_taxable - $abattement;

// IRPP
$irpp = 0;
$tb = $net_taxable;
if ($tb > 416666) { $irpp += ($tb - 416666) * 0.35; $tb = 416666; }
if ($tb > 250000) { $irpp += ($tb - 250000) * 0.25; $tb = 250000; }
if ($tb > 125000) { $irpp += ($tb - 125000) * 0.15; $tb = 125000; }
if ($tb > 62000)  { $irpp += ($tb - 62000) * 0.10; }
$irpp = floor($irpp);

$cac = round($irpp * ($taux['taux_cac'] / 100));
$cfc = round($brut_taxable * ($taux['taux_cfc_salarie'] / 100));

$total_retenues = $cnps + $irpp + $cac + $cfc;
$net_a_payer = $brut_total - $total_retenues;

// --- HTML PDF ---
$logo_html = $entreprise['logo'] ? '<img src="../'.$entreprise['logo'].'" style="height:60px;">' : '<h2>'.strtoupper($entreprise['nom']).'</h2>';

$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
    .header { width: 100%; border-bottom: 2px solid #0044cc; padding-bottom: 10px; margin-bottom: 20px; }
    .header td { vertical-align: top; }
    .company-info { font-size: 11px; color: #555; }
    .slip-title { text-align: right; }
    .slip-title h1 { color: #0044cc; margin: 0; font-size: 24px; }
    
    .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; border-radius: 5px; background: #f9f9f9; }
    .box-title { font-weight: bold; border-bottom: 1px solid #ccc; margin-bottom: 5px; padding-bottom: 3px; color: #0044cc; }
    
    .table-data { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .table-data th { background: #eee; text-align: left; padding: 8px; border-bottom: 1px solid #aaa; font-size: 10px; text-transform: uppercase; }
    .table-data td { padding: 8px; border-bottom: 1px solid #eee; }
    .amount { text-align: right; font-family: monospace; font-size: 13px; }
    .total-row td { background: #0044cc; color: white; font-weight: bold; border: none; }
    
    .cal-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
    .cal-table td { border: 1px solid #ddd; text-align: center; font-size: 9px; padding: 2px; }
    .pres { background: #cfc; } .abs { background: #fff; color: #eee; }
    
    .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #aaa; border-top: 1px solid #eee; padding-top: 5px; }
</style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                '.$logo_html.'<br>
                <div class="company-info">
                    '.htmlspecialchars($entreprise['adresse']).'<br>
                    NIU: '.htmlspecialchars($entreprise['numero_fiscal']).' | RC: '.htmlspecialchars($entreprise['registre_commerce']).'<br>
                    CNPS: '.htmlspecialchars($entreprise['numero_cnps']).'
                </div>
            </td>
            <td width="50%" class="slip-title">
                <h1>BULLETIN DE PAIE</h1>
                Période: <strong>'.date('F Y', strtotime($month)).'</strong><br>
                Date d\'édition: '.date('d/m/Y').'
            </td>
        </tr>
    </table>

    <table width="100%">
        <tr>
            <td width="48%">
                <div class="box">
                    <div class="box-title">EMPLOYÉ</div>
                    <strong>'.htmlspecialchars($e['nom']).'</strong><br>
                    Poste: '.htmlspecialchars($e['poste']).'<br>
                    Matricule: E-'.str_pad($e['id'], 4, '0', STR_PAD_LEFT).'
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%">
                <div class="box">
                    <div class="box-title">RÉCAPITULATIF</div>
                    Heures travaillées: <strong>'.$total_h.' h</strong><br>
                    Taux Horaire: '.number_format($e['salaire_horaire'],0,',',' ').' FCFA<br>
                    Mode de paiement: Virement
                </div>
            </td>
        </tr>
    </table>

    <table class="table-data">
        <thead>
            <tr>
                <th width="40%">Désignation</th>
                <th width="15%" style="text-align:center">Base / Taux</th>
                <th width="20%" class="amount">Gains</th>
                <th width="25%" class="amount">Retenues</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Salaire de base</td>
                <td align="center">'.$total_h.' h</td>
                <td class="amount">'.number_format($salaire_base,0,',',' ').'</td>
                <td></td>
            </tr>';
            
            if ($prime_13) {
                $html .= '<tr><td>Prime de 13ème Mois</td><td align="center">Forfait</td><td class="amount">'.number_format($mt_p13,0,',',' ').'</td><td></td></tr>';
            }
            if ($prime_14) {
                $html .= '<tr><td>Prime de 14ème Mois</td><td align="center">Forfait</td><td class="amount">'.number_format($mt_p14,0,',',' ').'</td><td></td></tr>';
            }

$html .= '
            <tr style="background:#f0f0f0; font-weight:bold;">
                <td>TOTAL BRUT</td>
                <td></td>
                <td class="amount">'.number_format($brut_total,0,',',' ').'</td>
                <td></td>
            </tr>
            <tr><td colspan="4" style="border:none; height:10px;"></td></tr>
            
            <tr>
                <td>CNPS (Part Salariale)</td>
                <td align="center">'.number_format($taux['taux_cnps_salarie'],2).'%</td>
                <td></td>
                <td class="amount">'.number_format($cnps,0,',',' ').'</td>
            </tr>
            <tr>
                <td>Crédit Foncier (CFC)</td>
                <td align="center">'.number_format($taux['taux_cfc_salarie'],2).'%</td>
                <td></td>
                <td class="amount">'.number_format($cfc,0,',',' ').'</td>
            </tr>
            <tr>
                <td>IRPP (Impôt Revenu)</td>
                <td align="center">Barème</td>
                <td></td>
                <td class="amount">'.number_format($irpp,0,',',' ').'</td>
            </tr>
            <tr>
                <td>CAC (Centimes Add. Comm.)</td>
                <td align="center">10% IRPP</td>
                <td></td>
                <td class="amount">'.number_format($cac,0,',',' ').'</td>
            </tr>

            <tr class="total-row">
                <td colspan="2">NET À PAYER</td>
                <td colspan="2" class="amount" style="font-size:16px; padding:10px;">'.number_format($net_a_payer,0,',',' ').' FCFA</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:20px;">
        <div class="box-title">CALENDRIER DE PRÉSENCE</div>
        <table class="cal-table">
            <tr>';
            for($i=1; $i<=$nb_jours_mois; $i++) {
                $class = isset($calendrier[$i]) && $calendrier[$i]['t']=='pres' ? 'pres' : 'abs';
                $html .= '<td class="'.$class.'">'.$i.'</td>';
            }
$html .= '  </tr>
        </table>
    </div>

    <table width="100%" style="margin-top:40px;">
        <tr>
            <td align="center" width="50%">
                <strong>L\'Employé</strong><br>
                <small>(Signature)</small>
                <br><br><br>__________________
            </td>
            <td align="center" width="50%">
                <strong>La Direction</strong><br>
                <small>(Cachet & Signature)</small>
                <br><br><br>__________________
            </td>
        </tr>
    </table>

    <div class="footer">
        Document généré informatiquement par Ziris - Conformité OHADA/Cameroun<br>
        Ce bulletin de paie doit être conservé sans limitation de durée.
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Fiche_".$e['nom']."_".$month.".pdf", ["Attachment" => true]);