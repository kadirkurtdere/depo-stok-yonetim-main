<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin yetkisi gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz ID.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Kendi hesabınızı silemezsiniz.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();

try {
    // İsmi al (log için)
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $uName = $stmt->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        logActivity('USER_DELETE', "Kullanıcı silindi: $uName (ID: $id)");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı.'], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası.'], JSON_UNESCAPED_UNICODE);
}
