<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

if (!isAdmin()) {
    http_response_code(403);
    die('Bu işlem için admin yetkisi gerekiyor.');
}

$pdo = getDB();

$tables = [];
$query = $pdo->query('SHOW TABLES');
while ($row = $query->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sqlScript = "-- Depo Yönetim Sistemi Veritabanı Yedeği\n";
$sqlScript .= "-- Tarih: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    // Tablo oluşturma sorgusu
    $query = $pdo->query('SHOW CREATE TABLE `' . $table . '`');
    $row = $query->fetch(PDO::FETCH_NUM);
    $sqlScript .= "\nDROP TABLE IF EXISTS `" . $table . "`;\n";
    $sqlScript .= $row[1] . ";\n\n";

    // Tablo verileri
    $query = $pdo->query('SELECT * FROM `' . $table . '`');
    $columnCount = $query->columnCount();

    while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $sqlScript .= "INSERT INTO `" . $table . "` VALUES(";
        for ($j = 0; $j < $columnCount; $j++) {
            $row[$j] = $row[$j];
            if (isset($row[$j])) {
                // Özel karakterleri escape et
                $val = str_replace(array("\n", "\r"), array("\\n", "\\r"), addslashes($row[$j]));
                $sqlScript .= '"' . $val . '"';
            } else {
                $sqlScript .= 'NULL';
            }
            if ($j < ($columnCount - 1)) {
                $sqlScript .= ',';
            }
        }
        $sqlScript .= ");\n";
    }
    $sqlScript .= "\n";
}

$backupFileName = 'depo_backup_' . date('Ymd_His') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $backupFileName . '"');
echo $sqlScript;
exit;
