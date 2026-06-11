<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['success' => false, 'error' => 'Geçersiz dolap ID.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare('DELETE FROM cabinets WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Dolap bulunamadı.'], JSON_UNESCAPED_UNICODE);
}
