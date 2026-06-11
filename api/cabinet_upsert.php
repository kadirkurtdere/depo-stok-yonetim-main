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
$id           = (int)($body['id']           ?? 0);
$warehouseId  = (int)($body['warehouse_id'] ?? 0);
$code         = trim($body['code']          ?? '');
$label        = trim($body['label']         ?? '');
$type         = strtoupper(trim($body['type'] ?? ''));

if (!in_array($type, ['A', 'B', 'C'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz dolap tipi. A, B veya C olmalı.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($code === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dolap kodu boş olamaz.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($label === '') $label = $code . ' Dolabı';

// Tipe göre otomatik boyutlar
$dims = [
    'A' => ['rows' => 22, 'cols' => 6],
    'B' => ['rows' => 6,  'cols' => 1],
    'C' => ['rows' => 3,  'cols' => 1],
];
$rows = $dims[$type]['rows'];
$cols = $dims[$type]['cols'];

$pdo = getDB();

if ($id > 0) {
    // Güncelle (type değişirse slot sayısı değişeceği için type güncellemesine izin verilmiyor)
    $stmt = $pdo->prepare('UPDATE cabinets SET code = ?, label = ? WHERE id = ?');
    $stmt->execute([$code, $label, $id]);
    // Güncel veriyi çek
    $stmt = $pdo->prepare('SELECT * FROM cabinets WHERE id = ?');
    $stmt->execute([$id]);
    $cab = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'cabinet' => $cab], JSON_UNESCAPED_UNICODE);
} else {
    // Yeni dolap
    if ($warehouseId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Depo ID gerekli.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Deponun var olduğunu doğrula
    $chk = $pdo->prepare('SELECT id FROM warehouses WHERE id = ?');
    $chk->execute([$warehouseId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Depo bulunamadı.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sıra numarası
    $sort = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM cabinets WHERE warehouse_id = ?');
    $sort->execute([$warehouseId]);
    $sortOrder = (int)$sort->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO cabinets (warehouse_id, code, label, type, `rows`, `cols`, sort_order) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$warehouseId, $code, $label, $type, $rows, $cols, $sortOrder]);
    $newId = (int)$pdo->lastInsertId();

    // Slotları otomatik oluştur
    $ins = $pdo->prepare('INSERT IGNORE INTO slots (cabinet_id, row_num, col_num) VALUES (?,?,?)');
    for ($r = 1; $r <= $rows; $r++) {
        for ($c = 1; $c <= $cols; $c++) {
            $ins->execute([$newId, $r, $c]);
        }
    }

    $stmt = $pdo->prepare('SELECT * FROM cabinets WHERE id = ?');
    $stmt->execute([$newId]);
    $cab = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'cabinet' => $cab], JSON_UNESCAPED_UNICODE);
}
