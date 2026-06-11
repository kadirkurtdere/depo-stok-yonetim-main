<?php
require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo = getDB();
    
    // 1. Create `products` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(200) NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            min_qty INT NOT NULL DEFAULT 0,
            barcode VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_products_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Create `user_warehouses` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_warehouses (
            user_id INT UNSIGNED NOT NULL,
            warehouse_id INT UNSIGNED NOT NULL,
            permission ENUM('view', 'edit') NOT NULL DEFAULT 'view',
            PRIMARY KEY (user_id, warehouse_id),
            CONSTRAINT fk_uw_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_uw_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Add `product_id` to `slots`
    // First check if product_id exists
    $stmt = $pdo->query("SHOW COLUMNS FROM slots LIKE 'product_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE slots ADD COLUMN product_id INT UNSIGNED DEFAULT NULL AFTER cabinet_id");
        $pdo->exec("ALTER TABLE slots ADD CONSTRAINT fk_slots_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL");
    }

    // 4. Migration: Move existing slot `item_name` to `products`
    // We group by item_name to create unique products
    $stmt = $pdo->query("SELECT DISTINCT item_name, min_qty FROM slots WHERE item_name IS NOT NULL AND item_name != ''");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertProduct = $pdo->prepare("INSERT IGNORE INTO products (name, min_qty) VALUES (?, ?)");
    foreach ($items as $item) {
        $insertProduct->execute([$item['item_name'], $item['min_qty']]);
    }

    // Now update slots to link product_id
    $pdo->exec("
        UPDATE slots s 
        INNER JOIN products p ON s.item_name = p.name 
        SET s.product_id = p.id
    ");

    echo "Phase 7 Database Migration Successful!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
