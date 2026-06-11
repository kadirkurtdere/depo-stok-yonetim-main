<?php
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';

$pdo = getDB();

// Tüm binaları ve istatistiklerini tek seferde çek
$stmt = $pdo->query('
    SELECT 
        b.*,
        COUNT(DISTINCT w.id) AS total_warehouses,
        COUNT(s.id) AS total_slots,
        SUM(CASE WHEN s.item_name IS NOT NULL AND s.item_name != "" THEN 1 ELSE 0 END) AS filled_slots
    FROM buildings b
    LEFT JOIN warehouses w ON w.building_id = b.id
    LEFT JOIN cabinets c ON c.warehouse_id = w.id
    LEFT JOIN slots s ON s.cabinet_id = c.id
    GROUP BY b.id
    ORDER BY b.id ASC
');
$buildings = $stmt->fetchAll();

$stats = [];
foreach ($buildings as $b) {
    $totalSlots  = (int)$b['total_slots'];
    $filledSlots = (int)$b['filled_slots'];
    $stats[$b['id']] = [
        'warehouses' => (int)$b['total_warehouses'],
        'slots'      => $totalSlots,
        'filled'     => $filledSlots,
        'pct'        => $totalSlots > 0 ? round($filledSlots / $totalSlots * 100) : 0,
    ];
}

$pageTitle = 'Bina Seçimi';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Binalar</h2>
      <p class="page-sub">Yönetmek istediğiniz binayı seçin</p>
    </div>
    <div class="hero-actions">
      <div class="hero-user-badge">
        <span class="mono">👋 <?= h($currentUser['full_name'] ?: $currentUser['username']) ?></span>
      </div>
      <?php if (isAdmin()): ?>
      <button class="btn btn-primary" onclick="openBinaModal()" id="btn-add-bina">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Yeni Bina
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($buildings)): ?>
  <div class="empty-state">
    <div class="empty-icon">🏢</div>
    <p>Henüz bina tanımlanmamış.</p>
    <span class="mono">Yeni Bina butonuna tıklayarak ekleyin.</span>
  </div>
  <?php else: ?>

  <div class="warehouse-grid" id="bina-grid">
    <?php foreach ($buildings as $b):
      $s = $stats[$b['id']];
    ?>
    <div class="wh-card-wrap" data-bina-id="<?= (int)$b['id'] ?>">
      <a href="<?= BASE_URL ?>/depo/?building_id=<?= (int)$b['id'] ?>" class="wh-card">
        <div class="wh-icon-area">
          <div class="wh-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="2">
              <path d="M3 21h18M3 7v14M21 7v14M3 7l9-4 9 4M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4M7 11h2M15 11h2M7 15h2M15 15h2"/>
            </svg>
          </div>
        </div>

        <div class="wh-info">
          <div class="wh-id mono">BINA #<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?></div>
          <h3 class="wh-name"><?= h($b['name']) ?></h3>
          <?php if ($b['location']): ?>
          <p class="wh-loc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= h($b['location']) ?>
          </p>
          <?php endif; ?>

          <div class="wh-stats">
            <div class="wh-stat-row">
              <span class="wh-stat-label">Depo</span>
              <span class="wh-stat-val"><?= $s['warehouses'] ?></span>
            </div>
            <div class="wh-stat-row">
              <span class="wh-stat-label">Dolu Raf</span>
              <span class="wh-stat-val green"><?= $s['filled'] ?></span>
            </div>
          </div>

          <div class="wh-progress">
            <div class="wh-prog-track">
              <div class="wh-prog-fill" style="width:<?= $s['pct'] ?>%"></div>
            </div>
            <span class="wh-prog-label mono">%<?= $s['pct'] ?> dolu</span>
          </div>
        </div>

        <div class="wh-arrow">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
      </a>
      <?php if (isAdmin()): ?>
      <div class="card-actions">
        <button class="btn-sm"
          onclick="event.stopPropagation(); editBina(<?= (int)$b['id'] ?>, <?= htmlspecialchars(json_encode($b['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($b['location']), ENT_QUOTES) ?>)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Düzenle
        </button>
        <button class="btn-sm danger"
          onclick="event.stopPropagation(); confirmDeleteBina(<?= (int)$b['id'] ?>, <?= htmlspecialchars(json_encode($b['name']), ENT_QUOTES) ?>)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          Sil
        </button>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- BİNA YÖNETİM MODALI -->
<div class="mgmt-modal-overlay" id="bina-modal" onclick="handleMgmtOverlay(event, 'bina-modal')">
  <div class="mgmt-modal">
    <div class="mgmt-modal-head">
      <div class="mgmt-modal-title" id="bina-modal-title">Yeni Bina</div>
      <button class="mgmt-modal-close" onclick="closeMgmtModal('bina-modal')">✕</button>
    </div>
    <input type="hidden" id="bina-id" value="">
    <div class="form-group">
      <label class="form-label">Bina Adı *</label>
      <input type="text" id="bina-name" class="form-input" placeholder="Örn: A Blok, Merkez Bina…">
    </div>
    <div class="form-group">
      <label class="form-label">Konum</label>
      <input type="text" id="bina-location" class="form-input" placeholder="Örn: Kuzey Kampüsü">
    </div>
    <div class="mgmt-modal-btns">
      <button class="btn btn-ghost" onclick="closeMgmtModal('bina-modal')">İptal</button>
      <button class="btn btn-primary" onclick="saveBina()">Kaydet</button>
    </div>
    <div class="mgmt-feedback" id="bina-feedback"></div>
  </div>
</div>

<!-- SİLME ONAY KUTUSU -->
<div class="confirm-overlay" id="confirm-overlay">
  <div class="confirm-box">
    <div class="confirm-title" id="confirm-title">Emin misiniz?</div>
    <p class="confirm-msg" id="confirm-msg"></p>
    <div class="confirm-btns">
      <button class="btn btn-ghost" onclick="closeConfirm()">Vazgeç</button>
      <button class="btn btn-danger" id="confirm-ok-btn" onclick="">Sil</button>
    </div>
  </div>
</div>

<script>
  const API_BASE = '<?= BASE_URL ?>/api';

  function openBinaModal() {
    document.getElementById('bina-id').value = '';
    document.getElementById('bina-name').value = '';
    document.getElementById('bina-location').value = '';
    document.getElementById('bina-modal-title').textContent = 'Yeni Bina Ekle';
    document.getElementById('bina-feedback').textContent = '';
    document.getElementById('bina-modal').classList.add('open');
    setTimeout(() => document.getElementById('bina-name').focus(), 100);
  }

  function editBina(id, name, location) {
    document.getElementById('bina-id').value = id;
    document.getElementById('bina-name').value = name;
    document.getElementById('bina-location').value = location;
    document.getElementById('bina-modal-title').textContent = 'Binayı Düzenle';
    document.getElementById('bina-feedback').textContent = '';
    document.getElementById('bina-modal').classList.add('open');
    setTimeout(() => document.getElementById('bina-name').focus(), 100);
  }

  async function saveBina() {
    const id       = document.getElementById('bina-id').value;
    const name     = document.getElementById('bina-name').value.trim();
    const locationVal = document.getElementById('bina-location').value.trim();
    if (!name) { setMgmtFeedback('bina-feedback', 'Bina adı boş olamaz.', 'err'); return; }

    setMgmtFeedback('bina-feedback', 'Kaydediliyor…', '');
    try {
      const res  = await fetch(`${API_BASE}/building_upsert.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id ? parseInt(id) : 0, name, location: locationVal }),
      });
      const data = await res.json();
      if (data.success) {
        setMgmtFeedback('bina-feedback', '✓ Kaydedildi', 'ok');
        setTimeout(() => window.location.reload(), 600);
      } else {
        setMgmtFeedback('bina-feedback', data.error || 'Kaydetme hatası.', 'err');
      }
    } catch { setMgmtFeedback('bina-feedback', 'Bağlantı hatası.', 'err'); }
  }

  function confirmDeleteBina(id, name) {
    document.getElementById('confirm-title').textContent = 'Binayı Sil';
    document.getElementById('confirm-msg').textContent =
      `"${name}" binası ve içindeki TÜM depolar, raflar ve ürün verileri kalıcı olarak silinecek.`;
    document.getElementById('confirm-ok-btn').onclick = () => { closeConfirm(); deleteBina(id); };
    document.getElementById('confirm-overlay').classList.add('open');
  }

  async function deleteBina(id) {
    try {
      const res  = await fetch(`${API_BASE}/building_delete.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      });
      const data = await res.json();
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.error || 'Silme hatası.');
      }
    } catch { alert('Bağlantı hatası.'); }
  }

  function closeConfirm() { document.getElementById('confirm-overlay').classList.remove('open'); }
  function closeMgmtModal(id) { document.getElementById(id).classList.remove('open'); }
  function handleMgmtOverlay(e, id) { if (e.target === e.currentTarget) closeMgmtModal(id); }
  function setMgmtFeedback(elId, msg, type) {
    const el = document.getElementById(elId);
    el.textContent = msg;
    el.className = 'mgmt-feedback ' + type;
  }
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
