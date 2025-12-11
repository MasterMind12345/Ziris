<?php
session_start();
require_once '../config/database.php';
require_once '../admin/includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer l'ID du poste depuis la requête GET
$poste_id = $_GET['poste_id'] ?? '';

if (empty($poste_id)) {
    echo json_encode(['success' => false, 'message' => 'ID de poste manquant']);
    exit;
}

try {
    // Récupérer la configuration du salaire pour ce poste
    $stmt = $pdo->prepare("
        SELECT ps.*, p.nom as poste_nom 
        FROM parametres_salaire ps
        JOIN postes p ON ps.poste_id = p.id
        WHERE ps.poste_id = ?
    ");
    $stmt->execute([$poste_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Configuration non trouvée'
        ]);
    }
} catch(PDOException $e) {
    error_log("Erreur get_salaire_config: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}
?>