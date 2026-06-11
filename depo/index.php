<?php
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';

$pdo = getDB();

$buildingId = (int)($_GET['building_id'] ?? 0);

if ($buildingId <= 0) {
    // Bina seçilmemişse bina listesine yolla
    header('Location: ' . BASE_URL . '/bina/index.php');
    exit;
}

// Bina bilgisini çek
$stmt = $pdo->prepare('SELECT * FROM buildings WHERE id = ?');
$stmt->execute([$buildingId]);
$building = $stmt->fetch();

if (!$building) {
    header('Location: ' . BASE_URL . '/bina/index.php');
    exit;
}

// Bu binaya ait depoları ve istatistiklerini tek seferde çek
$stmt = $pdo->prepare('
    SELECT 
        w.*,
        COUNT(s.id) AS total_slots,
        SUM(CASE WHEN s.item_name IS NOT NULL AND s.item_name != "" THEN 1 ELSE 0 END) AS filled_slots
    FROM warehouses w
    LEFT JOIN cabinets c ON c.warehouse_id = w.id
    LEFT JOIN slots s ON s.cabinet_id = c.id
    WHERE w.building_id = ?
    GROUP BY w.id
    ORDER BY w.id ASC
');
$stmt->execute([$buildingId]);
$warehouses = $stmt->fetchAll();

$stats = [];
foreach ($warehouses as $wh) {
    $total  = (int)$wh['total_slots'];
    $filled = (int)$wh['filled_slots'];
    $stats[$wh['id']] = [
        'total'  => $total,
        'filled' => $filled,
        'empty'  => $total - $filled,
        'pct'    => $total > 0 ? round($filled / $total * 100) : 0,
    ];
}

$pageTitle = h($building['name']) . ' — Depolar';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <div class="breadcrumb">
        <a href="<?= BASE_URL ?>/bina/">Binalar</a>
        <span class="sep">/</span>
        <span class="curr"><?= h($building['name']) ?></span>
      </div>
      <h2 class="page-title">Depolar</h2>
      <p class="page-sub">Bu binadaki depoları yönetin</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="hero-actions">
      <button class="btn btn-primary" onclick="openWhModal()" id="btn-add-wh">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Yeni Depo
      </button>
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($warehouses)): ?>
  <div class="empty-state">
    <div class="empty-icon">🏭</div>
    <p>Bu binada henüz depo tanımlanmamış.</p>
    <span class="mono">Yeni Depo butonuna tıklayarak ekleyin.</span>
  </div>
  <?php else: ?>

  <div class="warehouse-grid" id="wh-grid">
    <?php foreach ($warehouses as $wh):
      $s = $stats[$wh['id']];
    ?>
    <div class="wh-card-wrap" data-wh-id="<?= (int)$wh['id'] ?>">
      <a href="<?= BASE_URL ?>/depolar/?id=<?= (int)$wh['id'] ?>" class="wh-card">
        <div class="wh-icon-area">
          <div class="wh-icon">
            <svg viewBox="0 0 48 60" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="2" y="2" width="44" height="56" rx="3" stroke="#334155" stroke-width="3" fill="#e2e8f0"/>
              <?php for ($r = 0; $r < 5; $r++): ?>
              <rect x="6" y="<?= 10 + $r * 9 ?>" width="36" height="6" rx="1" fill="<?= $r < 3 ? '#bfdbfe' : '#f1f5f9' ?>"/>
              <?php endfor; ?>
            </svg>
          </div>
        </div>

        <div class="wh-info">
          <div class="wh-id mono">DEPO #<?= str_pad($wh['id'], 3, '0', STR_PAD_LEFT) ?></div>
          <h3 class="wh-name"><?= h($wh['name']) ?></h3>
          <?php if ($wh['location']): ?>
          <p class="wh-loc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= h($wh['location']) ?>
          </p>
          <?php endif; ?>

          <div class="wh-stats">
            <div class="wh-stat-row">
              <span class="wh-stat-label">Dolu Raf</span>
              <span class="wh-stat-val green"><?= $s['filled'] ?></span>
            </div>
            <div class="wh-stat-row">
              <span class="wh-stat-label">Toplam Dolap</span>
              <span class="wh-stat-val"><?= $s['total'] ?></span>
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
          onclick="event.stopPropagation(); editWarehouse(<?= (int)$wh['id'] ?>, <?= htmlspecialchars(json_encode($wh['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($wh['location']), ENT_QUOTES) ?>)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Düzenle
        </button>
        <button class="btn-sm danger"
          onclick="event.stopPropagation(); confirmDeleteWarehouse(<?= (int)$wh['id'] ?>, <?= htmlspecialchars(json_encode($wh['name']), ENT_QUOTES) ?>)">
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

<!-- DEPO YÖNETİM MODALI -->
<div class="mgmt-modal-overlay" id="wh-modal" onclick="handleMgmtOverlay(event, 'wh-modal')">
  <div class="mgmt-modal">
    <div class="mgmt-modal-head">
      <div class="mgmt-modal-title" id="wh-modal-title">Yeni Depo</div>
      <button class="mgmt-modal-close" onclick="closeMgmtModal('wh-modal')">✕</button>
    </div>
    <input type="hidden" id="wh-id" value="">
    <div class="form-group">
      <label class="form-label">Depo Adı *</label>
      <input type="text" id="wh-name" class="form-input" placeholder="Örn: Ana Depo, Teknik Depo…">
    </div>
    <div class="form-group">
      <label class="form-label">Konum</label>
      <input type="text" id="wh-location" class="form-input" placeholder="Örn: Zemin Kat — Blok A">
    </div>
    <div class="mgmt-modal-btns">
      <button class="btn btn-ghost" onclick="closeMgmtModal('wh-modal')">İptal</button>
      <button class="btn btn-primary" onclick="saveWarehouse()">Kaydet</button>
    </div>
    <div class="mgmt-feedback" id="wh-feedback"></div>
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
  const BUILDING_ID = <?= (int)$buildingId ?>;

  function openWhModal() {
    document.getElementById('wh-id').value = '';
    document.getElementById('wh-name').value = '';
    document.getElementById('wh-location').value = '';
    document.getElementById('wh-modal-title').textContent = 'Yeni Depo Ekle';
    document.getElementById('wh-feedback').textContent = '';
    document.getElementById('wh-modal').classList.add('open');
    setTimeout(() => document.getElementById('wh-name').focus(), 100);
  }

  function editWarehouse(id, name, location) {
    document.getElementById('wh-id').value = id;
    document.getElementById('wh-name').value = name;
    document.getElementById('wh-location').value = location;
    document.getElementById('wh-modal-title').textContent = 'Depoyu Düzenle';
    document.getElementById('wh-feedback').textContent = '';
    document.getElementById('wh-modal').classList.add('open');
    setTimeout(() => document.getElementById('wh-name').focus(), 100);
  }

  async function saveWarehouse() {
    const id       = document.getElementById('wh-id').value;
    const name     = document.getElementById('wh-name').value.trim();
    const locVal   = document.getElementById('wh-location').value.trim();
    if (!name) { setMgmtFeedback('wh-feedback', 'Depo adı boş olamaz.', 'err'); return; }

    setMgmtFeedback('wh-feedback', 'Kaydediliyor…', '');
    try {
      const res  = await fetch(`${API_BASE}/warehouse_upsert.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id ? parseInt(id) : 0, building_id: BUILDING_ID, name, location: locVal }),
      });
      const data = await res.json();
      if (data.success) {
        setMgmtFeedback('wh-feedback', '✓ Kaydedildi', 'ok');
        setTimeout(() => window.location.reload(), 600);
      } else {
        setMgmtFeedback('wh-feedback', data.error || 'Kaydetme hatası.', 'err');
      }
    } catch { setMgmtFeedback('wh-feedback', 'Bağlantı hatası.', 'err'); }
  }

  function confirmDeleteWarehouse(id, name) {
    document.getElementById('confirm-title').textContent = 'Depoyu Sil';
    document.getElementById('confirm-msg').textContent =
      `"${name}" deposu ve içindeki TÜM raflar ile ürün verileri kalıcı olarak silinecek.`;
    document.getElementById('confirm-ok-btn').onclick = () => { closeConfirm(); deleteWarehouse(id); };
    document.getElementById('confirm-overlay').classList.add('open');
  }

  async function deleteWarehouse(id) {
    try {
      const res  = await fetch(`${API_BASE}/warehouse_delete.php`, {
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
