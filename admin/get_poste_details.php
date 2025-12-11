<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit;
}

$poste_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM postes WHERE id = ?");
    $stmt->execute([$poste_id]);
    $poste = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($poste) {
        echo json_encode(['success' => true, 'poste' => $poste]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Poste non trouvé']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>