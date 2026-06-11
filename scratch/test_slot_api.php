<?php
// Gerçek ortamda slot_update API'yi test et
// Bu dosyayı XAMPP üzerinden çağır: http://localhost/depo-yonetim/scratch/test_slot_api.php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

echo "<h3>Slot Update API Test</h3>";

// 1. Oturum kontrolü
echo "<p>isLoggedIn: " . (isLoggedIn() ? '<span style="color:green">✓ Giriş yapılmış</span>' : '<span style="color:red">✗ Giriş YOK - API 401 dönecek!</span>') . "</p>";

// 2. DB bağlantısı
try {
    $pdo = getDB();
    $count = $pdo->query("SELECT COUNT(*) FROM cabinets")->fetchColumn();
    echo "<p>DB Bağlantısı: <span style='color:green'>✓ OK — $count dolap var</span></p>";
    
    // İlk dolabı bul
    $cab = $pdo->query("SELECT id, code, label, `rows`, `cols` FROM cabinets LIMIT 1")->fetch();
    if ($cab) {
        echo "<p>İlk Dolap: <code>{$cab['code']} — {$cab['label']}</code> (ID: {$cab['id']})</p>";
    } else {
        echo "<p style='color:orange'>⚠ Hiç dolap yok, önce dolap ekleyin.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>DB Hatası: " . $e->getMessage() . "</p>";
}

// 3. slot_update.php'nin varlığını kontrol et
$slotUpdatePath = dirname(__DIR__) . '/api/slot_update.php';
echo "<p>slot_update.php: " . (file_exists($slotUpdatePath) ? '<span style="color:green">✓ Dosya mevcut</span>' : '<span style="color:red">✗ Dosya YOK!</span>') . "</p>";

// 4. BASE_URL
require_once dirname(__DIR__) . '/config/constants.php';
echo "<p>BASE_URL: <code>" . BASE_URL . "</code></p>";
echo "<p>Beklenen API URL: <code>" . BASE_URL . "/api/slot_update.php</code></p>";

// 5. Gerçek bir test isteği gönder (oturum açıksa)
if (isLoggedIn() && isset($cab)) {
    $payload = json_encode([
        'action' => 'save',
        'cabinet_id' => $cab['id'],
        'row' => 1,
        'col' => 1,
        'item_name' => 'API Test Ürünü',
        'item_qty' => 5,
        'item_note' => 'Test notu'
    ]);
    
    $ch = curl_init('http://localhost' . BASE_URL . '/api/slot_update.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => session_name() . '=' . session_id(),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>Test İsteği HTTP Kodu: <code>$httpCode</code></p>";
    echo "<p>Test İsteği Yanıtı: <code>" . htmlspecialchars($response) . "</code></p>";
}
