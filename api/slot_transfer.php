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
    echo json_encode(['success' => false, 'error' => 'Sadece POST desteklenir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geçersiz CSRF token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || ($body['action'] ?? '') !== 'transfer') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz veri.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();
$user = getCurrentUser();

$srcCabId = (int)($body['source_cab'] ?? 0);
$srcRow   = (int)($body['source_row'] ?? 0);
$srcCol   = (int)($body['source_col'] ?? 0);

$tgtCabId = (int)($body['target_cab'] ?? 0);
$tgtRow   = (int)($body['target_row'] ?? 0);
$tgtCol   = (int)($body['target_col'] ?? 0);

$trQty    = (int)($body['qty'] ?? 0);

if ($srcCabId <= 0 || $tgtCabId <= 0 || $trQty <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Eksik veya hatalı parametreler.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($srcCabId === $tgtCabId && $srcRow === $tgtRow && $srcCol === $tgtCol) {
    echo json_encode(['success' => false, 'error' => 'Aynı rafa transfer yapılamaz.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Permission Checks
if ($user['role'] !== 'admin') {
    // Get source and target warehouse
    $stmt = $pdo->prepare('SELECT id, warehouse_id FROM cabinets WHERE id IN (?, ?)');
    $stmt->execute([$srcCabId, $tgtCabId]);
    $cabs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $srcWId = $cabs[$srcCabId] ?? 0;
    $tgtWId = $cabs[$tgtCabId] ?? 0;

    $stmt = $pdo->prepare('SELECT warehouse_id, permission FROM user_warehouses WHERE user_id = ? AND warehouse_id IN (?, ?)');
    $stmt->execute([$user['id'], $srcWId, $tgtWId]);
    $perms = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [warehouse_id => permission]
    
    if (($perms[$srcWId] ?? '') !== 'edit') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Kaynak depoda düzenleme yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (($perms[$tgtWId] ?? '') !== 'edit') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Hedef depoda düzenleme yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // 1. Kaynak rafı kontrol et
    $stmt = $pdo->prepare('SELECT id, product_id, item_name, item_qty, min_qty, item_note FROM slots WHERE cabinet_id = ? AND row_num = ? AND col_num = ? FOR UPDATE');
    $stmt->execute([$srcCabId, $srcRow, $srcCol]);
    $srcSlot = $stmt->fetch();

    if (!$srcSlot || $srcSlot['item_qty'] < $trQty) {
        throw new Exception('Kaynak rafta yeterli ürün yok.');
    }

    $productId = $srcSlot['product_id'];
    $itemName = $srcSlot['item_name'];
    $itemNote = $srcSlot['item_note'];
    $newSrcQty = $srcSlot['item_qty'] - $trQty;

    // 2. Hedef rafı kontrol et
    $stmt = $pdo->prepare('SELECT id, item_name, item_qty FROM slots WHERE cabinet_id = ? AND row_num = ? AND col_num = ? FOR UPDATE');
    $stmt->execute([$tgtCabId, $tgtRow, $tgtCol]);
    $tgtSlot = $stmt->fetch();

    if ($tgtSlot && !empty($tgtSlot['item_name']) && $tgtSlot['item_name'] !== $itemName) {
        throw new Exception('Hedef rafta farklı bir ürün ("' . htmlspecialchars($tgtSlot['item_name']) . '") bulunuyor. Lütfen önce hedef rafı boşaltın veya aynı ürünü içeren bir raf seçin.');
    }

    $newTgtQty = ($tgtSlot ? $tgtSlot['item_qty'] : 0) + $trQty;
    $tgtSlotId = $tgtSlot ? $tgtSlot['id'] : null;

    // 3. Kaynak rafı güncelle (Eğer 0 kalırsa raf tamamen boşaltılır mı? Yoksa adı kalır mı? Stok 0 kalsın, isim kalsın, sıfır stok uyarısı verir.)
    if ($newSrcQty <= 0) {
        // İsteğe bağlı olarak tamamen silebilir veya sadece sıfırlayabiliriz.
        // Genelde ürünün o raftaki kaydının 0 olarak kalması re-stock (yeniden stoklama) için kolaylık sağlar.
        $stmt = $pdo->prepare('UPDATE slots SET item_qty = 0, updated_at = NOW(), updated_by = ? WHERE id = ?');
        $stmt->execute([$user['id'], $srcSlot['id']]);
    } else {
        $stmt = $pdo->prepare('UPDATE slots SET item_qty = ?, updated_at = NOW(), updated_by = ? WHERE id = ?');
        $stmt->execute([$newSrcQty, $user['id'], $srcSlot['id']]);
    }

    // 4. Hedef rafı güncelle veya ekle
    if ($tgtSlotId) {
        $stmt = $pdo->prepare('UPDATE slots SET product_id = ?, item_name = ?, item_qty = ?, updated_at = NOW(), updated_by = ? WHERE id = ?');
        $stmt->execute([$productId, $itemName, $newTgtQty, $user['id'], $tgtSlotId]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO slots (cabinet_id, row_num, col_num, product_id, item_name, item_qty, min_qty, item_note, updated_at, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW(), ?)
        ');
        $stmt->execute([$tgtCabId, $tgtRow, $tgtCol, $productId, $itemName, $newTgtQty, $itemNote, $user['id']]);
        $tgtSlotId = $pdo->lastInsertId();
    }

    // 5. Stok Hareketlerini Logla (OUT ve IN)
    // Kaynak'tan çıkış (- miktar, prev, new, type=TRANSFER_OUT ya da OUT + Not düşmek)
    $stmt = $pdo->prepare("INSERT INTO stock_movements (slot_id, user_id, type, qty_change, prev_qty, new_qty, notes) VALUES (?, ?, 'TRANSFER', ?, ?, ?, ?)");
    
    // Kaynak log
    $srcNote = "Transfer Edildi -> Hedef Dolap ID: $tgtCabId, R:$tgtRow C:$tgtCol";
    $stmt->execute([$srcSlot['id'], $user['id'], -$trQty, $srcSlot['item_qty'], $newSrcQty, $srcNote]);

    // Hedef log
    $tgtNote = "Transfer Geldi <- Kaynak Dolap ID: $srcCabId, R:$srcRow C:$srcCol";
    $stmt->execute([$tgtSlotId, $user['id'], $trQty, ($tgtSlot ? $tgtSlot['item_qty'] : 0), $newTgtQty, $tgtNote]);

    logActivity('SLOT_TRANSFER', "$itemName isimli üründen $trQty adet transfer edildi. (Kaynak Dolap: $srcCabId -> Hedef Dolap: $tgtCabId)");

    $pdo->commit();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
