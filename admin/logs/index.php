<?php
require_once dirname(dirname(__DIR__)) . '/config/auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

requireLogin();
requireAdmin();

$pdo = getDB();

// Logları çek
$stmt = $pdo->query('
    SELECT l.*, u.username, u.full_name 
    FROM activity_logs l
    JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 100
');
$logs = $stmt->fetchAll();

$pageTitle = 'İşlem Logları';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">İşlem Logları</h2>
      <p class="page-sub">Sistemde yapılan son 100 işlem</p>
    </div>
  </div>

  <div class="log-container">
    <?php if (empty($logs)): ?>
    <div class="empty-state">
      <p>Henüz kayıtlı bir işlem yok.</p>
    </div>
    <?php else: ?>
    <table class="log-table">
      <thead>
        <tr>
          <th>Tarih</th>
          <th>Kullanıcı</th>
          <th>İşlem</th>
          <th>Detay</th>
          <th>IP Adresi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td class="mono"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
          <td>
            <strong><?= h($log['full_name'] ?: $log['username']) ?></strong>
            <div class="mono" style="font-size:10px; color:var(--muted)"><?= h($log['username']) ?></div>
          </td>
          <td><span class="badge-log action-<?= strtolower($log['action']) ?>"><?= h($log['action']) ?></span></td>
          <td class="log-details"><?= h($log['details']) ?></td>
          <td class="mono" style="font-size:11px;"><?= h($log['ip_address']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<style>
.log-container { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.log-table { width: 100%; border-collapse: collapse; }
.log-table th { background: #f8fafc; text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 700; color: var(--muted); border-bottom: 2px solid var(--border); }
.log-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px; }
.log-table tr:hover { background: #f8fafc; }
.badge-log { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; font-family: 'JetBrains Mono', monospace; background: var(--bg-hover); border: 1px solid var(--border); }
.log-details { color: var(--text); font-size: 12px; max-width: 400px; }

/* Action specific colors */
.action-slot_update, .action-slot_save { color: var(--green); border-color: var(--green-bdr); background: var(--green-soft); }
.action-slot_clear, .action-delete { color: var(--red); border-color: #fecaca; background: #fef2f2; }
.action-building_create, .action-warehouse_create { color: var(--accent); border-color: var(--accent-bdr); background: var(--accent-soft); }
</style>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
