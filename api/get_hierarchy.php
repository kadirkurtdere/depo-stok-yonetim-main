<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();

try {
    $user = getCurrentUser();
    
    if ($user['role'] === 'admin') {
        $stmt = $pdo->query('
            SELECT w.id as w_id, w.name as w_name, c.id as c_id, c.code as c_code, c.type, c.rows, c.cols
            FROM warehouses w
            LEFT JOIN cabinets c ON w.id = c.warehouse_id
            ORDER BY w.name, c.sort_order, c.id
        ');
    } else {
        $stmt = $pdo->prepare('
            SELECT w.id as w_id, w.name as w_name, c.id as c_id, c.code as c_code, c.type, c.rows, c.cols
            FROM warehouses w
            INNER JOIN user_warehouses uw ON w.id = uw.warehouse_id
            LEFT JOIN cabinets c ON w.id = c.warehouse_id
            WHERE uw.user_id = ?
            ORDER BY w.name, c.sort_order, c.id
        ');
        $stmt->execute([$user['id']]);
    }
    
    $data = [];
    foreach ($stmt->fetchAll() as $row) {
        $wId = $row['w_id'];
        if (!isset($data[$wId])) {
            $data[$wId] = [
                'id' => $wId,
                'name' => $row['w_name'],
                'cabinets' => []
            ];
        }
        if ($row['c_id']) {
            $data[$wId]['cabinets'][] = [
                'id' => $row['c_id'],
                'code' => $row['c_code'],
                'type' => $row['type'],
                'rows' => $row['rows'],
                'cols' => $row['cols']
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => array_values($data)], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Sunucu hatası'], JSON_UNESCAPED_UNICODE);
}
