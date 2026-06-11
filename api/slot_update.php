<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Auth kontrolü
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

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geçersiz CSRF token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$action    = $body['action']     ?? '';
$cabinetId = (int)($body['cabinet_id'] ?? 0);
$rowNum    = (int)($body['row']        ?? 0);
$colNum    = (int)($body['col']        ?? 0);

if ($cabinetId <= 0 || $rowNum <= 0 || $colNum <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz slot parametreleri.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo  = getDB();
$user = getCurrentUser();

// Dolabın ve deponun gerçekten var olduğunu doğrula
$stmt = $pdo->prepare('SELECT c.id, c.warehouse_id, c.`rows`, c.`cols` FROM cabinets c WHERE c.id = ? LIMIT 1');
$stmt->execute([$cabinetId]);
$cabinet = $stmt->fetch();

if (!$cabinet || $rowNum > $cabinet['rows'] || $colNum > $cabinet['cols']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Slot bulunamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($user['role'] !== 'admin') {
    $stmt = $pdo->prepare('SELECT permission FROM user_warehouses WHERE user_id = ? AND warehouse_id = ?');
    $stmt->execute([$user['id'], $cabinet['warehouse_id']]);
    $uw = $stmt->fetch();
    if (!$uw || $uw['permission'] !== 'edit') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Bu depoda düzenleme yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── KAYDET ──────────────────────────────────────────────────────
if ($action === 'save') {
    $itemName = trim($body['item_name'] ?? '');
    $itemQty  = (int)($body['item_qty']  ?? 0);
    $minQty   = (int)($body['min_qty']   ?? 0);
    $itemNote = trim($body['item_note'] ?? '');

    if ($itemName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ürün adı boş olamaz.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Try to find product_id by name
    $stmt = $pdo->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
    $stmt->execute([$itemName]);
    $product = $stmt->fetch();
    $productId = $product ? $product['id'] : null;

    // Mevcut durumu al
    $stmt = $pdo->prepare('SELECT id, item_qty FROM slots WHERE cabinet_id = ? AND row_num = ? AND col_num = ?');
    $stmt->execute([$cabinetId, $rowNum, $colNum]);
    $prevSlot = $stmt->fetch();
    $prevQty = $prevSlot ? (int)$prevSlot['item_qty'] : 0;
    $slotId = $prevSlot ? $prevSlot['id'] : null;

    // UPSERT
    $stmt = $pdo->prepare('
        INSERT INTO slots (cabinet_id, row_num, col_num, product_id, item_name, item_qty, min_qty, item_note, updated_at, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            product_id = VALUES(product_id),
            item_name  = VALUES(item_name),
            item_qty   = VALUES(item_qty),
            min_qty    = VALUES(min_qty),
            item_note  = VALUES(item_note),
            updated_at = NOW(),
            updated_by = VALUES(updated_by)
    ');
    $stmt->execute([$cabinetId, $rowNum, $colNum, $productId, $itemName, $itemQty, $minQty, $itemNote, $user['id']]);

    // Slot ID yeni eklendiyse al
    if (!$slotId) {
        $slotId = $pdo->lastInsertId();
    }

    // Stok hareketini logla (Değişim varsa)
    $qtyChange = $itemQty - $prevQty;
    if ($qtyChange !== 0 || !$prevSlot) {
        $type = 'SET';
        if ($prevSlot && $qtyChange > 0) $type = 'IN';
        if ($prevSlot && $qtyChange < 0) $type = 'OUT';
        
        $stmt = $pdo->prepare('INSERT INTO stock_movements (slot_id, user_id, type, qty_change, prev_qty, new_qty) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$slotId, $user['id'], $type, $qtyChange, $prevQty, $itemQty]);
    }

    logActivity('SLOT_SAVE', "Raf güncellendi (Dolap ID: $cabinetId, R:$rowNum, C:$colNum, Ürün: $itemName, Adet: $itemQty)");

    echo json_encode([
        'success'   => true,
        'item_name' => $itemName,
        'item_qty'  => $itemQty,
        'item_note' => $itemNote,
    ], JSON_UNESCAPED_UNICODE);

// ── TEMİZLE ─────────────────────────────────────────────────────
} elseif ($action === 'clear') {
    $stmt = $pdo->prepare('
        UPDATE slots SET item_name = NULL, item_qty = 0, item_note = NULL,
                         updated_at = NOW(), updated_by = ?
        WHERE cabinet_id = ? AND row_num = ? AND col_num = ?
    ');
    $stmt->execute([$user['id'], $cabinetId, $rowNum, $colNum]);

    logActivity('SLOT_CLEAR', "Raf temizlendi (Dolap ID: $cabinetId, R:$rowNum, C:$colNum)");

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz action.'], JSON_UNESCAPED_UNICODE);
}
