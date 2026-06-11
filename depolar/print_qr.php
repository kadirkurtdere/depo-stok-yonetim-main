<?php
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDB();

$warehouseId = (int)($_GET['id'] ?? 0);
if ($warehouseId <= 0) {
    die("Geçersiz depo ID.");
}

// Depo bilgisi
$stmt = $pdo->prepare('SELECT w.*, b.name as building_name FROM warehouses w JOIN buildings b ON w.building_id = b.id WHERE w.id = ?');
$stmt->execute([$warehouseId]);
$warehouse = $stmt->fetch();

if (!$warehouse) {
    die("Depo bulunamadı.");
}

// Depodaki tüm dolaplar
$stmt = $pdo->prepare('SELECT * FROM cabinets WHERE warehouse_id = ? ORDER BY sort_order, id');
$stmt->execute([$warehouseId]);
$cabinets = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= h($warehouse['name']) ?> - QR Kodları</title>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 20px; background: #fff; }
        .print-btn { padding: 10px 20px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; margin-bottom: 20px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            .page-break { page-break-after: always; }
        }
        .qr-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .qr-card { border: 1px dashed #ccc; padding: 15px; text-align: center; border-radius: 8px; }
        .qr-card h4 { margin: 0 0 10px 0; font-size: 14px; color: #333; }
        .qr-card p { margin: 5px 0 0 0; font-size: 12px; color: #666; }
        .qr-code { margin: 0 auto; }
    </style>
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
</head>
<body>

    <div class="no-print" style="display:flex; justify-content:space-between; align-items:center;">
        <h2><?= h($warehouse['building_name']) ?> - <?= h($warehouse['name']) ?> QR Kodları</h2>
        <button class="print-btn" onclick="window.print()">🖨️ Yazdır</button>
    </div>

    <?php foreach ($cabinets as $index => $cab): ?>
        <div style="margin-bottom: 40px; <?= $index > 0 ? 'class="page-break"' : '' ?>">
            <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px;"><?= h($cab['code']) ?> - <?= h($cab['label']) ?></h3>
            <div class="qr-grid">
                <?php
                for ($r = 1; $r <= $cab['rows']; $r++) {
                    for ($c = 1; $c <= $cab['cols']; $c++) {
                        $qrText = "SLOT_{$cab['id']}_{$r}_{$c}";
                        $label = $cab['type'] === 'A' ? "Satır $r, Kolon " . chr(64 + $c) : "Raf $r";
                        ?>
                        <div class="qr-card">
                            <h4><?= h($cab['code']) ?></h4>
                            <div class="qr-code" id="qr_<?= $cab['id'] ?>_<?= $r ?>_<?= $c ?>" data-text="<?= $qrText ?>"></div>
                            <p><?= $label ?></p>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.qr-code').forEach(el => {
                new QRCode(el, {
                    text: el.dataset.text,
                    width: 100,
                    height: 100,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.L
                });
            });
        });
    </script>
</body>
</html>
