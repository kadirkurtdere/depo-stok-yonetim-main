<?php
require_once dirname(__DIR__, 2) . '/includes/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = getDB();

// Filtreleme parametreleri
$filterType = $_GET['type'] ?? '';
$filterUser = $_GET['user'] ?? '';
$filterItem = $_GET['item'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($filterType) {
    $where[] = 'sm.type = ?';
    $params[] = $filterType;
}
if ($filterUser) {
    $where[] = 'sm.user_id = ?';
    $params[] = $filterUser;
}
if ($filterItem) {
    $where[] = 's.item_name LIKE ?';
    $params[] = '%' . $filterItem . '%';
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Toplam kayıt sayısı (Sayfalama için)
$countSql = "
    SELECT COUNT(*) 
    FROM stock_movements sm
    LEFT JOIN slots s ON sm.slot_id = s.id
    $whereSql
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Hareketleri Çek
$sql = "
    SELECT 
        sm.*, 
        u.full_name, u.username,
        s.item_name, s.cabinet_id, s.row_num, s.col_num,
        c.code as cabinet_code,
        w.name as warehouse_name
    FROM stock_movements sm
    LEFT JOIN users u ON sm.user_id = u.id
    LEFT JOIN slots s ON sm.slot_id = s.id
    LEFT JOIN cabinets c ON s.cabinet_id = c.id
    LEFT JOIN warehouses w ON c.warehouse_id = w.id
    $whereSql
    ORDER BY sm.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Kullanıcı listesi (Filtre için)
$users = $pdo->query('SELECT id, full_name, username FROM users ORDER BY full_name')->fetchAll();

$pageTitle = 'Stok Hareketleri';
require_once dirname(__DIR__, 2) . '/includes/header.php';

function getTypeBadge($type) {
    switch ($type) {
        case 'IN': return '<span class="log-type type-in">GİRİŞ</span>';
        case 'OUT': return '<span class="log-type type-out">ÇIKIŞ</span>';
        case 'TRANSFER': return '<span class="log-type type-tr">TRANSFER</span>';
        default: return '<span class="log-type type-set">GÜNCELLEME</span>';
    }
}
function numToLetter($num) {
    return chr(64 + (int)$num);
}
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Stok Hareketleri Geçmişi</h2>
      <p class="page-sub">Depoya giren, çıkan ve transfer edilen ürünlerin detaylı listesi.</p>
    </div>
  </div>

  <div class="filter-bar">
    <form method="GET" action="" class="filter-form">
      <div class="filter-group">
        <select name="type" class="form-input">
          <option value="">Tüm İşlemler</option>
          <option value="IN" <?= $filterType === 'IN' ? 'selected' : '' ?>>Giriş (Artış)</option>
          <option value="OUT" <?= $filterType === 'OUT' ? 'selected' : '' ?>>Çıkış (Azalış)</option>
          <option value="TRANSFER" <?= $filterType === 'TRANSFER' ? 'selected' : '' ?>>Transfer</option>
          <option value="SET" <?= $filterType === 'SET' ? 'selected' : '' ?>>Manuel Güncelleme</option>
        </select>
      </div>
      <div class="filter-group">
        <select name="user" class="form-input">
          <option value="">Tüm Kullanıcılar</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>>
            <?= h($u['full_name'] ?: $u['username']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group" style="flex:1;">
        <input type="text" name="item" class="form-input" placeholder="Ürün adına göre ara..." value="<?= h($filterItem) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Filtrele</button>
      <?php if ($filterType || $filterUser || $filterItem): ?>
      <a href="?" class="btn btn-danger">Temizle</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="log-card">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Tarih</th>
            <th>Kullanıcı</th>
            <th>İşlem Tipi</th>
            <th>Ürün Adı</th>
            <th>Konum</th>
            <th class="text-right">Önceki</th>
            <th class="text-center">Değişim</th>
            <th class="text-right">Sonraki</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px 0;">Hiçbir hareket bulunamadı.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td class="mono text-muted" style="font-size:12px;">
                <?= date('d.m.Y H:i', strtotime($log['created_at'])) ?>
              </td>
              <td>
                <div style="font-weight:600; font-size:13px;"><?= h($log['full_name'] ?: $log['username']) ?></div>
              </td>
              <td><?= getTypeBadge($log['type']) ?></td>
              <td style="font-weight:600; color:var(--accent);">
                <?= h($log['item_name'] ?: 'Bilinmeyen/Silinmiş Ürün') ?>
              </td>
              <td style="font-size:12px; color:var(--muted);">
                <?php if ($log['cabinet_code']): ?>
                  <?= h($log['warehouse_name']) ?><br>
                  <?= h($log['cabinet_code']) ?> · S<?= $log['row_num'] ?>-<?= numToLetter($log['col_num']) ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="text-right mono"><?= $log['prev_qty'] ?></td>
              <td class="text-center">
                <span class="qty-change <?= $log['qty_change'] > 0 ? 'pos' : ($log['qty_change'] < 0 ? 'neg' : 'neu') ?>">
                  <?= $log['qty_change'] > 0 ? '+' : '' ?><?= $log['qty_change'] ?>
                </span>
              </td>
              <td class="text-right mono" style="font-weight:700;"><?= $log['new_qty'] ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php 
        $qs = $_GET; 
        $qs['page'] = $i; 
        $url = '?' . http_build_query($qs);
      ?>
      <a href="<?= $url ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>



<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
