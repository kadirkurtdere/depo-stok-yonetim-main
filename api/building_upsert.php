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
$id       = (int)($body['id']       ?? 0);
$name     = trim($body['name']      ?? '');
$location = trim($body['location']  ?? '');
$desc     = trim($body['description'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bina adı boş olamaz.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();

if ($id > 0) {
    // Güncelle
    $stmt = $pdo->prepare('UPDATE buildings SET name = ?, location = ?, description = ? WHERE id = ?');
    $stmt->execute([$name, $location, $desc, $id]);
    logActivity('BUILDING_UPDATE', "Bina güncellendi: $name (ID: $id)");
    echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'location' => $location], JSON_UNESCAPED_UNICODE);
} else {
    // Yeni ekle
    $stmt = $pdo->prepare('INSERT INTO buildings (name, location, description) VALUES (?, ?, ?)');
    $stmt->execute([$name, $location, $desc]);
    $newId = (int)$pdo->lastInsertId();
    logActivity('BUILDING_CREATE', "Yeni bina eklendi: $name (ID: $newId)");
    echo json_encode(['success' => true, 'id' => $newId, 'name' => $name, 'location' => $location], JSON_UNESCAPED_UNICODE);
}
