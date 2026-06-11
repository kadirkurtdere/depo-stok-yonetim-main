<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$warehouseId = (int)($_GET['id'] ?? 0);
if ($warehouseId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz depo ID.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare('
    SELECT
      COUNT(s.id)                                        AS total,
      SUM(s.item_name IS NOT NULL AND s.item_name != "") AS filled
    FROM cabinets c
    JOIN slots s ON s.cabinet_id = c.id
    WHERE c.warehouse_id = ?
');
$stmt->execute([$warehouseId]);
$row = $stmt->fetch();

$total  = (int)($row['total']  ?? 0);
$filled = (int)($row['filled'] ?? 0);

echo json_encode([
    'success' => true,
    'total'   => $total,
    'filled'  => $filled,
    'empty'   => $total - $filled,
    'pct'     => $total > 0 ? round($filled / $total * 100, JSON_UNESCAPED_UNICODE) : 0,
]);
