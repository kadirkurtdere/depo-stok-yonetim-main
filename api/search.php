<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('
    SELECT 
        s.item_name, 
        s.item_qty, 
        s.row_num, 
        s.col_num,
        c.code as cabinet_code,
        c.label as cabinet_label,
        c.type as cabinet_type,
        w.id as warehouse_id,
        w.name as warehouse_name,
        b.name as building_name
    FROM slots s
    JOIN cabinets c ON s.cabinet_id = c.id
    JOIN warehouses w ON c.warehouse_id = w.id
    JOIN buildings b ON w.building_id = b.id
    WHERE s.item_name LIKE ? OR s.item_note LIKE ?
    ORDER BY b.name ASC, s.item_name ASC
    LIMIT 30
');

$searchTerm = '%' . $query . '%';
$stmt->execute([$searchTerm, $searchTerm]);
$results = $stmt->fetchAll();

echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
