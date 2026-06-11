<?php
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDB();

try {
    // 1. Bina tablosunu oluştur
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buildings (
            id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100)  NOT NULL,
            location    VARCHAR(200)  NOT NULL DEFAULT '',
            description TEXT,
            created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Varsayılan bir bina ekle (eğer yoksa)
    $stmt = $pdo->query("SELECT id FROM buildings LIMIT 1");
    $defaultBuildingId = $stmt->fetchColumn();

    if (!$defaultBuildingId) {
        $pdo->exec("INSERT INTO buildings (name, location, description) VALUES ('Ana Bina', 'Merkez Yerleşke', 'Sistem tarafından otomatik oluşturulan varsayılan bina.')");
        $defaultBuildingId = $pdo->lastInsertId();
    }

    // 3. Warehouses tablosuna building_id ekle
    $columns = $pdo->query("SHOW COLUMNS FROM warehouses LIKE 'building_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE warehouses ADD COLUMN building_id INT UNSIGNED AFTER id");
        
        // 4. Mevcut depoları bu binaya bağla
        $pdo->exec("UPDATE warehouses SET building_id = $defaultBuildingId");
        
        // 5. Foreign key ekle
        $pdo->exec("ALTER TABLE warehouses ADD CONSTRAINT fk_warehouse_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE");
    }

    echo "Veritabanı başarıyla güncellendi: Bina (Building) katmanı eklendi.\n";

} catch (Exception $e) {
    echo "Hata oluştu: " . $e->getMessage() . "\n";
}
