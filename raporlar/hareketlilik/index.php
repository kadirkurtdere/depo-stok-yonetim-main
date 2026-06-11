<?php
require_once dirname(__DIR__, 2) . '/includes/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = getDB();

// Son 30 günlük hareketlilik
$sql = "
    SELECT 
        s.item_name,
        COUNT(sm.id) as total_tx,
        SUM(CASE WHEN sm.type = 'IN' THEN sm.qty_change ELSE 0 END) as total_in,
        SUM(CASE WHEN sm.type = 'OUT' THEN ABS(sm.qty_change) ELSE 0 END) as total_out,
        SUM(CASE WHEN sm.type = 'TRANSFER' AND sm.qty_change < 0 THEN ABS(sm.qty_change) ELSE 0 END) as total_tr
    FROM stock_movements sm
    JOIN slots s ON sm.slot_id = s.id
    WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND s.item_name IS NOT NULL AND s.item_name != ''
    GROUP BY s.item_name
    ORDER BY total_tx DESC
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Hareketlilik Raporu';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Hareketlilik Raporu (Son 30 Gün)</h2>
      <p class="page-sub">Son 1 ay içinde sirkülasyonu en yüksek olan ürünler (en çok işlem görenler).</p>
    </div>
  </div>

  <div class="panel">
    <!-- Masaüstü Görünümü (Tablo) -->
    <div class="table-responsive desktop-only">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 40%;">Ürün Adı</th>
            <th class="text-center" style="width: 15%;">İşlem Sayısı (Tx)</th>
            <th class="text-right" style="width: 15%; color: var(--green);">Toplam Giren</th>
            <th class="text-right" style="width: 15%; color: var(--red);">Toplam Çıkan</th>
            <th class="text-right" style="width: 15%; color: var(--accent);">Toplam Transfer</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($data)): ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:40px 0;">Son 30 gün içinde hiç stok hareketi bulunamadı.</td></tr>
          <?php else: ?>
            <?php foreach ($data as $row): ?>
            <tr>
              <td style="font-weight:600; font-size:15px;">
                <?= h($row['item_name']) ?>
              </td>
              <td class="text-center mono" style="font-weight:700;">
                <?= $row['total_tx'] ?>
              </td>
              <td class="text-right mono text-green">
                <?= $row['total_in'] > 0 ? '+'.$row['total_in'] : '-' ?>
              </td>
              <td class="text-right mono text-red">
                <?= $row['total_out'] > 0 ? '-'.$row['total_out'] : '-' ?>
              </td>
              <td class="text-right mono" style="color:var(--accent);">
                <?= $row['total_tr'] > 0 ? '⇄ '.$row['total_tr'] : '-' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobil Görünümü (Kartlar) -->
    <div class="mobile-only report-cards">
      <?php if (empty($data)): ?>
        <div class="text-center text-muted" style="padding:40px 20px;">Son 30 gün içinde hiç stok hareketi bulunamadı.</div>
      <?php else: ?>
        <?php foreach ($data as $row): ?>
        <div class="report-card">
          <div class="rc-head">
            <div class="rc-title"><?= h($row['item_name']) ?></div>
            <div class="rc-badge" title="İşlem Sayısı"><?= $row['total_tx'] ?> İşlem</div>
          </div>
          <div class="rc-grid">
            <div class="rc-stat">
              <span class="rc-lbl">Giriş</span>
              <span class="rc-val text-green"><?= $row['total_in'] > 0 ? '+'.$row['total_in'] : '0' ?></span>
            </div>
            <div class="rc-stat">
              <span class="rc-lbl">Çıkış</span>
              <span class="rc-val text-red"><?= $row['total_out'] > 0 ? '-'.$row['total_out'] : '0' ?></span>
            </div>
            <div class="rc-stat">
              <span class="rc-lbl">Transfer</span>
              <span class="rc-val" style="color:var(--accent);"><?= $row['total_tr'] > 0 ? $row['total_tr'] : '0' ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<style>
.panel { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th, .data-table td { padding: 16px 20px; border-bottom: 1px solid var(--border2); }
.data-table th { background: var(--surface2); font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.data-table tr:hover td { background: var(--surface2); }
.data-table tr:last-child td { border-bottom: none; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-green { color: var(--green); }
.text-red { color: var(--red); }
.mono { font-family: 'JetBrains Mono', monospace; }

/* Mobil Kart Tasarımı */
.report-cards { display: flex; flex-direction: column; }
.report-card { padding: 16px; border-bottom: 1px solid var(--border2); transition: background .2s; }
.report-card:hover { background: var(--bg); }
.report-card:last-child { border-bottom: none; }
.rc-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.rc-title { font-weight: 700; font-size: 15px; color: var(--text); flex: 1; }
.rc-badge { 
  background: var(--accent-soft); color: var(--accent); 
  padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; 
  white-space: nowrap; margin-left: 10px;
}
.rc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.rc-stat { display: flex; flex-direction: column; gap: 2px; }
.rc-lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; font-weight: 600; }
.rc-val { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 14px; }

.mobile-only { display: none; }

@media (max-width: 768px) {
  .desktop-only { display: none; }
  .mobile-only { display: block; }
  .panel { border-radius: 0; border-left: none; border-right: none; }
}
</style>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
