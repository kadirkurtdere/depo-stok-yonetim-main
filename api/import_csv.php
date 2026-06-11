<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin yetkisi gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Sadece POST desteklenir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dosya yüklenemedi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fileTmpPath = $_FILES['csv_file']['tmp_name'];
$fileName = $_FILES['csv_file']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExtension !== 'csv') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sadece CSV dosyaları kabul edilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();
$user = getCurrentUser();
$successCount = 0;
$errorCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();
    
    // Depo ve Dolapları cache'le
    $cabQuery = $pdo->query("
        SELECT c.id as cab_id, c.code as cab_code, c.rows, c.cols, w.name as w_name 
        FROM cabinets c 
        JOIN warehouses w ON c.warehouse_id = w.id
    ");
    $cabinets = [];
    foreach ($cabQuery->fetchAll() as $row) {
        $key = mb_strtolower($row['w_name']) . '|' . mb_strtolower($row['cab_code']);
        $cabinets[$key] = $row;
    }

    $handle = fopen($fileTmpPath, 'r');
    if ($handle !== FALSE) {
        // BOM kontrolü ve temizliği
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 1000, ';'); // Noktalı virgül varsayıyoruz
        if (!$header || count($header) < 7) {
            // Eğer virgül ile ayrılmışsa tekrar dene
            rewind($handle);
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            $header = fgetcsv($handle, 1000, ',');
        }

        // CSV yapısı: Depo, Dolap, Dolap Tipi, Satir, Kolon, Urun Adi, Miktar, Kritik Esik, Notlar
        // Minimum gerekli: Depo, Dolap, Satir, Kolon, Urun Adi, Miktar
        $expectedHeaders = ['depo', 'dolap', 'satir', 'kolon', 'urun adi', 'miktar'];
        $headerStr = mb_strtolower(implode(' ', $header));
        
        // Esnek eşleştirme yerine index bazlı gidelim
        // 0:Depo, 1:Dolap, 2:Dolap Tipi, 3:Satir, 4:Kolon, 5:Urun Adi, 6:Miktar, 7:Kritik Esik, 8:Notlar
        
        $rowNum = 1;
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            $rowNum++;
            if (count($data) < 6) {
                // virgül ile deneme (eğer header virgülse burası da virgüldür)
                $data = explode(',', implode(';', $data)); 
            }
            if (count($data) < 6) {
                $errorCount++;
                $errors[] = "Satır $rowNum: Eksik sütun.";
                continue;
            }

            $wName = trim($data[0]);
            $cCode = trim($data[1]);
            // index 2 = dolap tipi
            $sRow = (int)trim($data[3]);
            $sCol = (int)trim($data[4]);
            $itemName = trim($data[5]);
            $qty = (int)trim($data[6]);
            $minQty = isset($data[7]) ? (int)trim($data[7]) : 0;
            $note = isset($data[8]) ? trim($data[8]) : '';

            if (!$wName || !$cCode || !$sRow || !$sCol || !$itemName) {
                $errorCount++;
                $errors[] = "Satır $rowNum: Zorunlu alanlardan biri eksik.";
                continue;
            }

            $cacheKey = mb_strtolower($wName) . '|' . mb_strtolower($cCode);
            if (!isset($cabinets[$cacheKey])) {
                $errorCount++;
                $errors[] = "Satır $rowNum: '$wName' deposunda '$cCode' dolabı bulunamadı.";
                continue;
            }

            $cab = $cabinets[$cacheKey];
            if ($sRow > $cab['rows'] || $sCol > $cab['cols']) {
                $errorCount++;
                $errors[] = "Satır $rowNum: Hedef raf dolap boyutlarını aşıyor (Maks: {$cab['rows']}x{$cab['cols']}).";
                continue;
            }

            // Mevcut durumu kontrol et
            $stmt = $pdo->prepare('SELECT id, item_qty FROM slots WHERE cabinet_id = ? AND row_num = ? AND col_num = ?');
            $stmt->execute([$cab['cab_id'], $sRow, $sCol]);
            $prevSlot = $stmt->fetch();
            $prevQty = $prevSlot ? (int)$prevSlot['item_qty'] : 0;

            // UPSERT
            $stmt = $pdo->prepare('
                INSERT INTO slots (cabinet_id, row_num, col_num, item_name, item_qty, min_qty, item_note, updated_at, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    item_name  = VALUES(item_name),
                    item_qty   = VALUES(item_qty),
                    min_qty    = VALUES(min_qty),
                    item_note  = VALUES(item_note),
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by)
            ');
            $stmt->execute([$cab['cab_id'], $sRow, $sCol, $itemName, $qty, $minQty, $note, $user['id']]);
            
            $slotId = $prevSlot ? $prevSlot['id'] : $pdo->lastInsertId();

            // Stok Hareket Logu
            $qtyChange = $qty - $prevQty;
            if ($qtyChange !== 0 || !$prevSlot) {
                $type = 'SET';
                if ($prevSlot && $qtyChange > 0) $type = 'IN';
                if ($prevSlot && $qtyChange < 0) $type = 'OUT';
                
                $stmt = $pdo->prepare("INSERT INTO stock_movements (slot_id, user_id, type, qty_change, prev_qty, new_qty, notes) VALUES (?, ?, ?, ?, ?, ?, 'CSV ile İçe Aktarım')");
                $stmt->execute([$slotId, $user['id'], $type, $qtyChange, $prevQty, $qty]);
            }

            $successCount++;
        }
        fclose($handle);
    }

    if ($successCount > 0) {
        logActivity('IMPORT_CSV', "$successCount satır veri içe aktarıldı. Hatalı: $errorCount");
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => "$successCount adet kayıt başarıyla işlendi.",
        'errors' => $errors,
        'error_count' => $errorCount
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
