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
    echo json_encode(['success' => false, 'error' => 'POST gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$oldPassword = $body['old_password'] ?? '';
$newPassword = $body['new_password'] ?? '';

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'error' => 'Yeni şifre en az 6 karakter olmalıdır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];

try {
    // Mevcut şifreyi doğrula
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Mevcut şifre hatalı.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Yeni şifreyi kaydet
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$newHash, $userId]);

    logActivity('PASSWORD_CHANGE', "Kullanıcı kendi şifresini güncelledi.");

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası.'], JSON_UNESCAPED_UNICODE);
}
