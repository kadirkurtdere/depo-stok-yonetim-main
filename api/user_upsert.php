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
$id       = (int)($body['id']       ?? 0);
$username = trim($body['username']  ?? '');
$fullname = trim($body['fullname']  ?? '');
$email    = trim($body['email']     ?? '');
$phone    = trim($body['phone']     ?? '');
$password = $body['password']       ?? '';
$role     = $body['role']           ?? 'viewer';
$warehouses = $body['warehouses']   ?? []; // Array of {id, permission}

if ($username === '' || $fullname === '') {
    echo json_encode(['success' => false, 'error' => 'Kullanıcı adı ve Ad Soyad gereklidir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();

try {
    if ($id > 0) {
        // GÜNCELLE
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $stmt = $pdo->prepare('UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, role = ?, password_hash = ? WHERE id = ?');
            $stmt->execute([$username, $fullname, $email, $phone, $role, $hash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, role = ? WHERE id = ?');
            $stmt->execute([$username, $fullname, $email, $phone, $role, $id]);
        }
        logActivity('USER_UPDATE', "Kullanıcı güncellendi: $username (ID: $id)");
    } else {
        // YENİ EKLE
        if ($password === '') {
            echo json_encode(['success' => false, 'error' => 'Yeni kullanıcı için şifre gereklidir.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Kullanıcı adı kontrolü
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Bu kullanıcı adı zaten alınmış.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $stmt = $pdo->prepare('INSERT INTO users (username, full_name, email, phone, role, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, $fullname, $email, $phone, $role, $hash]);
        $id = $pdo->lastInsertId();
        logActivity('USER_ADD', "Yeni kullanıcı eklendi: $username (ID: $id)");
    }

    // Yetkileri Güncelle (Admin değilse veya admin olsa da kaydedilebilir)
    $pdo->prepare('DELETE FROM user_warehouses WHERE user_id = ?')->execute([$id]);
    if (!empty($warehouses) && is_array($warehouses)) {
        $stmtW = $pdo->prepare('INSERT INTO user_warehouses (user_id, warehouse_id, permission) VALUES (?, ?, ?)');
        foreach ($warehouses as $wId => $perm) {
            if (in_array($perm, ['view', 'edit'])) {
                $stmtW->execute([$id, $wId, $perm]);
            }
        }
    }

    echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
