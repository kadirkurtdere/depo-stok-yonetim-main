<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();
$q = trim($_GET['q'] ?? '');

try {
    if ($q !== '') {
        $stmt = $pdo->prepare('SELECT id, name, min_qty, barcode FROM products WHERE name LIKE ? OR barcode LIKE ? ORDER BY name ASC LIMIT 20');
        $stmt->execute(["%$q%", "%$q%"]);
    } else {
        $stmt = $pdo->query('SELECT id, name, min_qty, barcode FROM products ORDER BY name ASC LIMIT 50');
    }
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $products], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
