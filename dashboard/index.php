<?php
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDB();

// 1. Özet İstatistikler
$stats = [
    'buildings' => $pdo->query('SELECT COUNT(*) FROM buildings')->fetchColumn(),
    'warehouses' => $pdo->query('SELECT COUNT(*) FROM warehouses')->fetchColumn(),
    'total_slots' => $pdo->query('SELECT SUM(`rows` * `cols`) FROM cabinets')->fetchColumn() ?: 0,
    'filled_slots' => $pdo->query("SELECT COUNT(*) FROM slots WHERE item_name IS NOT NULL AND item_name != ''")->fetchColumn(),
    'low_stock' => $pdo->query("SELECT COUNT(*) FROM slots WHERE item_name IS NOT NULL AND item_name != '' AND item_qty <= min_qty")->fetchColumn(),
];

// 2. Düşük Stoklu Ürünler (Limit 10)
$lowStockSql = "
    SELECT s.item_name, s.item_qty, s.min_qty, c.code as cab_code, w.name as warehouse_name
    FROM slots s
    JOIN cabinets c ON s.cabinet_id = c.id
    JOIN warehouses w ON c.warehouse_id = w.id
    WHERE s.item_name IS NOT NULL AND s.item_name != '' AND s.item_qty <= s.min_qty
    ORDER BY (s.item_qty - s.min_qty) ASC
    LIMIT 10
";
$lowStocks = $pdo->query($lowStockSql)->fetchAll(PDO::FETCH_ASSOC);

// 3. Son 10 Hareket (Log)
$logSql = "
    SELECT sm.*, u.full_name, u.username, s.item_name
    FROM stock_movements sm
    LEFT JOIN users u ON sm.user_id = u.id
    LEFT JOIN slots s ON sm.slot_id = s.id
    ORDER BY sm.created_at DESC
    LIMIT 10
";
$recentLogs = $pdo->query($logSql)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/header.php';

function getTypeBadge($type) {
    switch ($type) {
        case 'IN': return '<span class="log-type type-in">GİRİŞ</span>';
        case 'OUT': return '<span class="log-type type-out">ÇIKIŞ</span>';
        case 'TRANSFER': return '<span class="log-type type-tr">TRANSFER</span>';
        default: return '<span class="log-type type-set">GÜNCELLEME</span>';
    }
}
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Genel Durum (Dashboard)</h2>
      <p class="page-sub">Depo yönetim sisteminizin anlık istatistikleri ve özet görünümü.</p>
    </div>
  </div>

  <!-- ÖZET KARTLARI -->
  <div class="dash-cards">
    <div class="dash-card">
      <div class="dc-title">Toplam Bina / Depo</div>
      <div class="dc-value"><?= $stats['buildings'] ?> / <?= $stats['warehouses'] ?></div>
    </div>
    <div class="dash-card">
      <div class="dc-title">Doluluk Oranı</div>
      <div class="dc-value">
        <?= $stats['total_slots'] > 0 ? round(($stats['filled_slots'] / $stats['total_slots']) * 100) : 0 ?>%
      </div>
      <div class="dc-sub"><?= $stats['filled_slots'] ?> dolu / <?= $stats['total_slots'] ?> toplam raf</div>
    </div>
    <div class="dash-card <?= $stats['low_stock'] > 0 ? 'alert' : '' ?>">
      <div class="dc-title">Kritik Stok Uyarıları</div>
      <div class="dc-value"><?= $stats['low_stock'] ?></div>
      <div class="dc-sub">Minimum eşiğin altındaki raf sayısı</div>
    </div>
  </div>

  <div class="dash-row">
    <!-- DÜŞÜK STOK LİSTESİ -->
    <div class="dash-col">
      <div class="panel">
        <div class="panel-head">
          <h3>Kritik Stoklu Ürünler</h3>
          <?php if($stats['low_stock'] > 10): ?>
          <span class="panel-badge">Tümünü Raporlarda Gör</span>
          <?php endif; ?>
        </div>
        <div class="panel-body p-0">
          <?php if(empty($lowStocks)): ?>
          <div class="empty-state-sm">Harika! Kritik stoğa düşen ürün yok.</div>
          <?php else: ?>
          <table class="dash-table">
            <thead>
              <tr>
                <th>Ürün</th>
                <th>Lokasyon</th>
                <th class="text-right">Miktar</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($lowStocks as $ls): ?>
              <tr>
                <td style="font-weight:600; color:var(--red);"><?= h($ls['item_name']) ?></td>
                <td style="font-size:12px; color:var(--muted);"><?= h($ls['warehouse_name']) ?> - <?= h($ls['cab_code']) ?></td>
                <td class="text-right text-red mono" style="font-weight:700;">
                  <?= $ls['item_qty'] ?> <span style="font-size:10px; color:var(--muted);">/ min: <?= $ls['min_qty'] ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- SON HAREKETLER -->
    <div class="dash-col">
      <div class="panel">
        <div class="panel-head">
          <h3>Son Stok Hareketleri</h3>
          <a href="<?= BASE_URL ?>/raporlar/stok-hareketleri/" style="font-size:12px; color:var(--accent); text-decoration:none;">Tümünü Gör</a>
        </div>
        <div class="panel-body p-0">
          <?php if(empty($recentLogs)): ?>
          <div class="empty-state-sm">Henüz bir hareket kaydedilmedi.</div>
          <?php else: ?>
          <table class="dash-table">
            <thead>
              <tr>
                <th>İşlem</th>
                <th>Ürün</th>
                <th class="text-right">Değişim</th>
                <th class="text-right">Sonuç</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($recentLogs as $log): ?>
              <tr>
                <td><?= getTypeBadge($log['type']) ?></td>
                <td style="font-weight:600; font-size:13px;"><?= h($log['item_name'] ?: 'Silinmiş') ?></td>
                <td class="text-right mono <?= $log['qty_change'] > 0 ? 'text-green' : ($log['qty_change'] < 0 ? 'text-red' : '') ?>">
                  <?= $log['qty_change'] > 0 ? '+' : '' ?><?= $log['qty_change'] ?>
                </td>
                <td class="text-right mono" style="font-weight:700;"><?= $log['new_qty'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<style>
.dash-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 24px; }
.dash-card { background: #fff; padding: 24px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; flex-direction: column; }
.dash-card.alert { border-color: var(--red); background: #fef2f2; }
.dash-card.alert .dc-title { color: #991b1b; }
.dash-card.alert .dc-value { color: #b91c1c; }

.dc-title { font-size: 14px; font-weight: 600; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.dc-value { font-size: 32px; font-weight: 800; color: var(--text); margin-bottom: 4px; }
.dc-sub { font-size: 13px; color: var(--muted); }

.dash-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; }
.dash-col { display: flex; flex-direction: column; }

.panel { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; flex-direction: column; height: 100%; overflow: hidden; }
.panel-head { padding: 16px 20px; border-bottom: 1px solid var(--border2); display: flex; justify-content: space-between; align-items: center; background: var(--surface2); }
.panel-head h3 { margin: 0; font-size: 16px; font-weight: 600; color: var(--text); }
.panel-badge { font-size: 11px; padding: 2px 8px; background: #e5e7eb; color: #4b5563; border-radius: 12px; font-weight: 600; }
.panel-body { padding: 20px; flex: 1; }
.panel-body.p-0 { padding: 0; }

.empty-state-sm { padding: 40px 20px; text-align: center; color: var(--muted); font-size: 14px; }

.dash-table { width: 100%; border-collapse: collapse; text-align: left; }
.dash-table th, .dash-table td { padding: 12px 20px; border-bottom: 1px solid var(--border2); }
.dash-table th { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.dash-table tr:hover td { background: var(--surface2); }
.dash-table tr:last-child td { border-bottom: none; }

.text-right { text-align: right; }
.text-red { color: var(--red); }
.text-green { color: var(--green); }
.mono { font-family: 'JetBrains Mono', monospace; }

.log-type { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
.type-in { background: #dcfce7; color: #166534; }
.type-out { background: #fee2e2; color: #991b1b; }
.type-tr { background: #e0e7ff; color: #3730a3; }
.type-set { background: #f3f4f6; color: #4b5563; }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
