<?php
require_once dirname(__DIR__, 2) . '/includes/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = getDB();

$sql = "
    SELECT 
        w.id as warehouse_id, 
        w.name as warehouse_name, 
        b.name as building_name,
        (
            SELECT SUM(`rows` * `cols`) 
            FROM cabinets 
            WHERE warehouse_id = w.id
        ) as total_slots,
        (
            SELECT COUNT(*) 
            FROM slots s
            JOIN cabinets c ON s.cabinet_id = c.id
            WHERE c.warehouse_id = w.id AND s.item_name IS NOT NULL AND s.item_name != ''
        ) as filled_slots
    FROM warehouses w
    JOIN buildings b ON w.building_id = b.id
    ORDER BY b.name, w.name
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Doluluk Özeti';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Depo Doluluk Özeti</h2>
      <p class="page-sub">Sistemdeki tüm depoların anlık kapasite ve doluluk durumları.</p>
    </div>
  </div>

  <div class="report-cards">
    <?php foreach ($data as $row): 
        $total = (int)$row['total_slots'];
        $filled = (int)$row['filled_slots'];
        $empty = $total - $filled;
        $pct = $total > 0 ? round(($filled / $total) * 100) : 0;
        
        // Renk belirleme
        $color = 'var(--accent)';
        if ($pct >= 90) $color = 'var(--red)';
        elseif ($pct >= 70) $color = 'var(--orange)';
        elseif ($pct < 30 && $total > 0) $color = 'var(--green)';
    ?>
    <div class="rep-card">
      <div class="rc-head">
        <div class="rc-title"><?= h($row['warehouse_name']) ?></div>
        <div class="rc-sub"><?= h($row['building_name']) ?></div>
      </div>
      
      <div class="rc-body">
        <div class="rc-stats">
          <div class="rc-stat">
            <span class="rc-lbl">Toplam Kapasite</span>
            <span class="rc-val mono"><?= $total ?></span>
          </div>
          <div class="rc-stat">
            <span class="rc-lbl">Dolu Raf</span>
            <span class="rc-val mono"><?= $filled ?></span>
          </div>
          <div class="rc-stat">
            <span class="rc-lbl">Boş Raf</span>
            <span class="rc-val mono"><?= $empty ?></span>
          </div>
        </div>

        <div class="rc-prog-wrap">
          <div class="rc-prog-lbl">
            <span>Doluluk Oranı</span>
            <span style="color: <?= $color ?>; font-weight:700;"><?= $pct ?>%</span>
          </div>
          <div class="rc-prog-track">
            <div class="rc-prog-fill" style="width: <?= $pct ?>%; background: <?= $color ?>;"></div>
          </div>
        </div>
      </div>
      
      <div class="rc-foot">
        <a href="<?= BASE_URL ?>/depo/?id=<?= $row['warehouse_id'] ?>" class="btn btn-sm btn-primary" style="width:100%; justify-content:center;">Depoya Git →</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($data)): ?>
  <div class="empty-state">
    <div class="empty-icon">🏢</div>
    <p>Sistemde kayıtlı depo bulunamadı.</p>
  </div>
  <?php endif; ?>
</div>

<style>
.report-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
.rep-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
.rc-head { padding: 16px 20px; border-bottom: 1px solid var(--border2); background: var(--surface2); }
.rc-title { font-size: 16px; font-weight: 600; color: var(--text); }
.rc-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }

.rc-body { padding: 20px; flex: 1; display: flex; flex-direction: column; gap: 24px; }
.rc-stats { display: flex; justify-content: space-between; }
.rc-stat { display: flex; flex-direction: column; gap: 4px; }
.rc-lbl { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.rc-val { font-size: 20px; font-weight: 700; color: var(--text); }

.rc-prog-wrap { display: flex; flex-direction: column; gap: 8px; }
.rc-prog-lbl { display: flex; justify-content: space-between; font-size: 13px; font-weight: 500; }
.rc-prog-track { height: 8px; background: var(--surface); border-radius: 4px; overflow: hidden; }
.rc-prog-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease-out; }

.rc-foot { padding: 16px 20px; border-top: 1px solid var(--border2); }
.btn-sm { padding: 6px 12px; font-size: 13px; }
</style>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
