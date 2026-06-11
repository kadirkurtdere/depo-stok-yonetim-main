<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Bu işlem için admin yetkisi gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Sadece POST kabul edilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz bina ID.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo  = getDB();

// Önce bina ismini al (log için)
$stmt = $pdo->prepare('SELECT name FROM buildings WHERE id = ?');
$stmt->execute([$id]);
$bName = $stmt->fetchColumn();

$stmt = $pdo->prepare('DELETE FROM buildings WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() > 0) {
    logActivity('BUILDING_DELETE', "Bina silindi: $bName (ID: $id)");
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Bina bulunamadı.'], JSON_UNESCAPED_UNICODE);
}

