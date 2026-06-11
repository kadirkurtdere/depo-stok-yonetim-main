<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

if (!isAdmin()) {
    http_response_code(403);
    die('Bu işlem için admin yetkisi gerekiyor.');
}

$pdo = getDB();

$sql = "
    SELECT 
        w.name as depo, 
        c.code as dolap, 
        c.type as dolap_tipi,
        s.row_num as satir, 
        s.col_num as kolon, 
        s.item_name as urun_adi, 
        s.item_qty as miktar, 
        s.min_qty as kritik_esik, 
        s.item_note as notlar
    FROM slots s
    JOIN cabinets c ON s.cabinet_id = c.id
    JOIN warehouses w ON c.warehouse_id = w.id
    WHERE s.item_name IS NOT NULL AND s.item_name != ''
    ORDER BY w.name, c.sort_order, s.row_num, s.col_num
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=stok_durumu_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM (for Excel utf-8 compatibility)

fputcsv($output, ['Depo', 'Dolap', 'Dolap Tipi', 'Satir', 'Kolon', 'Urun Adi', 'Miktar', 'Kritik Esik', 'Notlar'], ';');

foreach ($data as $row) {
    fputcsv($output, [
        $row['depo'],
        $row['dolap'],
        $row['dolap_tipi'],
        $row['satir'],
        $row['kolon'],
        $row['urun_adi'],
        $row['miktar'],
        $row['kritik_esik'],
        $row['notlar']
    ], ';');
}

fclose($output);
exit;
