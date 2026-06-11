<?php
require_once dirname(__DIR__, 2) . '/includes/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = getDB();

$search = $_GET['q'] ?? '';

$where = "WHERE s.item_name IS NOT NULL AND s.item_name != ''";
$params = [];

if ($search !== '') {
    $where .= " AND s.item_name LIKE ?";
    $params[] = '%' . $search . '%';
}

$sql = "
    SELECT 
        s.item_name, 
        SUM(s.item_qty) as total_qty,
        GROUP_CONCAT(
            CONCAT(w.name, '::', c.code, '::', s.row_num, '::', s.col_num, '::', s.item_qty) 
            SEPARATOR '||'
        ) as locations
    FROM slots s
    JOIN cabinets c ON s.cabinet_id = c.id
    JOIN warehouses w ON c.warehouse_id = w.id
    $where
    GROUP BY s.item_name
    ORDER BY s.item_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

function numToLetter($num) {
    return chr(64 + (int)$num);
}

$pageTitle = 'Lokasyon Raporu';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Ürün Bazlı Lokasyon Raporu</h2>
      <p class="page-sub">Hangi ürünün hangi depolarda ve dolaplarda, kaç adet bulunduğunu listeleyin.</p>
    </div>
  </div>

  <div class="filter-bar">
    <form method="GET" action="" style="display:flex; gap:12px; width:100%; max-width:500px;">
      <input type="text" name="q" class="form-input" placeholder="Ürün adına göre ara..." value="<?= h($search) ?>">
      <button type="submit" class="btn btn-primary">Ara</button>
      <?php if($search): ?>
      <a href="?" class="btn btn-danger">Temizle</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="panel">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 30%;">Ürün Adı</th>
            <th style="width: 15%; text-align:right;">Toplam Miktar</th>
            <th style="width: 55%;">Bulunduğu Konumlar</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($data)): ?>
          <tr><td colspan="3" class="text-center text-muted" style="padding:40px 0;">Ürün bulunamadı.</td></tr>
          <?php else: ?>
            <?php foreach ($data as $row): 
                $locs = explode('||', $row['locations']);
            ?>
            <tr>
              <td style="font-weight:600; color:var(--accent); font-size:15px;">
                <?= h($row['item_name']) ?>
              </td>
              <td class="text-right mono" style="font-weight:700; font-size:15px;">
                <?= $row['total_qty'] ?>
              </td>
              <td>
                <div class="loc-tags">
                  <?php foreach($locs as $loc): 
                    $parts = explode('::', $loc);
                    if(count($parts) === 5) {
                        $wName = $parts[0];
                        $cCode = $parts[1];
                        $rNum = $parts[2];
                        $cNum = $parts[3];
                        $qty = $parts[4];
                        echo '<span class="loc-tag" title="'.h($wName).' - '.h($cCode).'">';
                        echo '<strong>'.h($wName).' / '.h($cCode).'</strong> · S'.$rNum.'-'.numToLetter($cNum);
                        echo '<span class="loc-tag-qty">'.$qty.'</span>';
                        echo '</span>';
                    }
                  endforeach; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<style>
.filter-bar { background: #fff; padding: 16px; border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 24px; border: 1px solid var(--border); }

.panel { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th, .data-table td { padding: 16px 20px; border-bottom: 1px solid var(--border2); vertical-align: top; }
.data-table th { background: var(--surface2); font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.data-table tr:hover td { background: var(--surface2); }
.data-table tr:last-child td { border-bottom: none; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.mono { font-family: 'JetBrains Mono', monospace; }

.loc-tags { display: flex; flex-wrap: wrap; gap: 8px; }
.loc-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 6px; padding: 4px 8px; font-size: 12px;
  color: var(--text);
}
.loc-tag strong { color: var(--accent); }
.loc-tag-qty { background: var(--accent-soft); color: var(--accent); padding: 2px 6px; border-radius: 4px; font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 11px; margin-left: 4px; }
</style>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
