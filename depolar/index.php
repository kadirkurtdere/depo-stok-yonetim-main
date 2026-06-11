<?php
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';

$pdo = getDB();

$warehouseId = (int)($_GET['id'] ?? 0);
if ($warehouseId <= 0) {
    header('Location: ' . BASE_URL . '/bina/index.php');
    exit;
}

// Depo bilgisi ve bağlı olduğu bina
$stmt = $pdo->prepare('
    SELECT w.*, b.name as building_name 
    FROM warehouses w 
    JOIN buildings b ON w.building_id = b.id 
    WHERE w.id = ? 
    LIMIT 1
');
$stmt->execute([$warehouseId]);
$warehouse = $stmt->fetch();

if (!$warehouse) {
    header('Location: ' . BASE_URL . '/bina/index.php');
    exit;
}

$currentUser = getCurrentUser();
$hasEditPerm = false;

if ($currentUser['role'] === 'admin') {
    $hasEditPerm = true;
} else {
    // İzleyici veya Editör, depo yetkisine bak
    $stmt = $pdo->prepare('SELECT permission FROM user_warehouses WHERE user_id = ? AND warehouse_id = ?');
    $stmt->execute([$currentUser['id'], $warehouseId]);
    $uw = $stmt->fetch();
    
    if (!$uw) {
        // Hiç yetkisi yoksa binaya geri gönder (veya 403)
        header('Location: ' . BASE_URL . '/bina/index.php?error=no_permission');
        exit;
    }
    
    if ($uw['permission'] === 'edit') {
        $hasEditPerm = true;
    }
}

// Bu depoya ait dolapları (cabinets) çek
$stmt = $pdo->prepare('SELECT * FROM cabinets WHERE warehouse_id = ? ORDER BY sort_order, id');
$stmt->execute([$warehouseId]);
$cabinets = $stmt->fetchAll();

// Tüm slot verilerini çek
$cabinetIds = array_column($cabinets, 'id');
$allSlots = [];
if (!empty($cabinetIds)) {
    $placeholders = implode(',', array_fill(0, count($cabinetIds), '?'));
    $stmt = $pdo->prepare("
        SELECT cabinet_id, row_num, col_num, item_name, item_qty, min_qty, item_note, id
        FROM slots
        WHERE cabinet_id IN ($placeholders)
    ");
    $stmt->execute($cabinetIds);
    foreach ($stmt->fetchAll() as $slot) {
        $key = $slot['cabinet_id'] . '_' . $slot['row_num'] . '_' . $slot['col_num'];
        $allSlots[$key] = $slot;
    }
}

$totalSlots  = array_sum(array_map(fn($c) => $c['rows'] * $c['cols'], $cabinets));
$filledSlots = count(array_filter($allSlots, fn($s) => !empty($s['item_name'])));

$pageTitle = h($warehouse['name']);
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-wrap">

  <div class="detail-topbar">
    <div>
      <div class="breadcrumb">
        <a href="<?= BASE_URL ?>/bina/">Binalar</a>
        <span class="sep">/</span>
        <a href="<?= BASE_URL ?>/depo/?building_id=<?= (int)$warehouse['building_id'] ?>"><?= h($warehouse['building_name']) ?></a>
        <span class="sep">/</span>
        <span class="curr"><?= h($warehouse['name']) ?></span>
      </div>
      <div class="detail-head-center">
        <h2 class="detail-wh-name"><?= h($warehouse['name']) ?></h2>
        <?php if ($warehouse['location']): ?>
        <div class="detail-wh-loc"><?= h($warehouse['location']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="detail-summary">
      <div class="ds-item"><span class="ds-num green"><?= $filledSlots ?></span><span class="ds-lbl">Dolu Raf</span></div>
      <div class="ds-item"><span class="ds-num orange"><?= $totalSlots - $filledSlots ?></span><span class="ds-lbl">Boş Raf</span></div>
      <div class="ds-item"><span class="ds-num"><?= $totalSlots > 0 ? round($filledSlots/$totalSlots*100) : 0 ?>%</span><span class="ds-lbl">Doluluk</span></div>
    </div>
  </div>

  <div style="display:flex; justify-content:flex-end; margin-bottom:20px; gap: 10px;">
    <?php if ($hasEditPerm): ?>
    <a href="print_qr.php?id=<?= $warehouseId ?>" target="_blank" class="btn btn-ghost">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      QR Kodları Yazdır
    </a>
    <?php endif; ?>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary" onclick="openCabModal()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Yeni Dolap Ekle
    </button>
    <?php endif; ?>
  </div>

  <?php if (empty($cabinets)): ?>
  <div class="empty-state">
    <div class="empty-icon">🗄️</div>
    <p>Bu depoda henüz dolap tanımlanmamış.</p>
    <span>"Yeni Dolap Ekle" ile ilk dolabı oluşturun.</span>
  </div>
  <?php else: ?>

  <?php
  $groups = [];
  foreach ($cabinets as $cab) { $groups[$cab['type']][] = $cab; }
  $typeLabels = ['A' => 'A Tipi Dolaplar', 'B' => 'B Tipi Dolaplar', 'C' => 'C Tipi Dolaplar'];
  ?>

  <div class="cabinets-wrap" id="cabinets-wrap">
  <?php foreach ($groups as $type => $typeCabs): ?>
    <div class="cab-group" data-type="<?= $type ?>">
      <div class="cab-group-head">
        <span class="cab-group-label"><?= h($typeLabels[$type] ?? $type . ' Tipi') ?></span>
        <span class="cab-group-count mono"><?= count($typeCabs) ?> adet</span>
      </div>

      <div class="cab-cards-row <?= $type === 'A' ? 'cab-cards-a' : 'cab-cards-bc' ?>">
      <?php foreach ($typeCabs as $cab):
        $cabFilled = 0; $cabTotal = $cab['rows'] * $cab['cols'];
        for ($r = 1; $r <= $cab['rows']; $r++)
          for ($c = 1; $c <= $cab['cols']; $c++)
            if (!empty($allSlots[$cab['id'].'_'.$r.'_'.$c]['item_name'])) $cabFilled++;
        $cabPct = $cabTotal > 0 ? round($cabFilled / $cabTotal * 100) : 0;
      ?>
      <div class="cab-card" id="cab-card-<?= (int)$cab['id'] ?>" data-cab-id="<?= (int)$cab['id'] ?>">
        <div class="cab-head">
          <div>
            <span class="cab-code mono"><?= h($cab['code']) ?></span>
            <span class="cab-label"><?= h($cab['label']) ?></span>
          </div>
          <div class="cab-fill-badge <?= $cabPct > 80 ? 'badge-warn' : '' ?>"><?= $cabFilled ?>/<?= $cabTotal ?></div>
        </div>

        <div class="cab-grid-wrap">
          <?php if ($cab['type'] === 'A'): ?>
            <div class="grid-header-row">
              <div class="rn-spacer"></div>
              <?php for ($c = 1; $c <= $cab['cols']; $c++): ?><div class="col-head"><?= chr(64 + $c) ?></div><?php endfor; ?>
            </div>
            <?php for ($r = 1; $r <= $cab['rows']; $r++): ?>
            <div class="slot-row">
              <div class="row-num"><?= $r ?></div>
              <?php for ($c = 1; $c <= $cab['cols']; $c++):
                $slotData = $allSlots[$cab['id'].'_'.$r.'_'.$c] ?? null;
                $filled = !empty($slotData['item_name']);
              ?>
              <div class="slot slot-a <?= $filled ? 'filled' : '' ?> <?= ($filled && (int)$slotData['item_qty'] <= (int)$slotData['min_qty']) ? 'low-stock' : '' ?>"
                   data-cab="<?= (int)$cab['id'] ?>" data-row="<?= $r ?>" data-col="<?= $c ?>"
                   data-name="<?= h($slotData['item_name'] ?? '') ?>" data-qty="<?= h($slotData['item_qty'] ?? '') ?>"
                   data-minqty="<?= h($slotData['min_qty'] ?? 0) ?>"
                   data-note="<?= h($slotData['item_note'] ?? '') ?>" <?= $hasEditPerm ? 'onclick="openModal(this)"' : 'style="cursor:not-allowed;" title="Yetkiniz yok"' ?>>
                <?php if ($filled): ?>
                <span class="sa-name"><?= h($slotData['item_name']) ?></span>
                <?php if ($slotData['item_qty']): ?><span class="sa-qty"><?= h($slotData['item_qty']) ?></span><?php endif; ?>
                <?php else: ?><span class="sa-empty">—</span><?php endif; ?>
              </div>
              <?php endfor; ?>
            </div>
            <?php endfor; ?>
          <?php else: ?>
            <?php for ($r = 1; $r <= $cab['rows']; $r++):
              $slotData = $allSlots[$cab['id'].'_'.$r.'_1'] ?? null;
              $filled = !empty($slotData['item_name']);
            ?>
              <div class="slot slot-bc <?= $filled ? 'filled' : '' ?> <?= ($filled && (int)$slotData['item_qty'] <= (int)$slotData['min_qty']) ? 'low-stock' : '' ?>"
                   data-cab="<?= (int)$cab['id'] ?>" data-row="<?= $r ?>" data-col="1"
                   data-name="<?= h($slotData['item_name'] ?? '') ?>" data-qty="<?= h($slotData['item_qty'] ?? '') ?>"
                   data-minqty="<?= h($slotData['min_qty'] ?? 0) ?>"
                   data-note="<?= h($slotData['item_note'] ?? '') ?>" <?= $hasEditPerm ? 'onclick="openModal(this)"' : 'style="cursor:not-allowed;" title="Yetkiniz yok"' ?>>
                <div class="sbc-info">RAF <?= $r ?></div>
              <div class="sbc-content">
                <?php if ($filled): ?>
                <div class="sbc-name"><?= h($slotData['item_name']) ?></div>
                <?php if ($slotData['item_qty']): ?><div class="sbc-qty"><?= h($slotData['item_qty']) ?> adet</div><?php endif; ?>
                <?php else: ?><div class="sbc-empty">Boş — düzenlemek için tıklayın</div><?php endif; ?>
              </div>
              <div class="sbc-arrow"><?= $filled ? '✎' : '+' ?></div>
            </div>
            <?php endfor; ?>
          <?php endif; ?>
        </div>

        <div class="cab-progress"><div class="cab-prog-fill" style="width:<?= $cabPct ?>%"></div></div>

        <?php if ($hasEditPerm): ?>
        <div class="cab-actions">
          <button class="btn-sm" onclick="editCabinet(<?= (int)$cab['id'] ?>, <?= htmlspecialchars(json_encode($cab['code']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($cab['label']), ENT_QUOTES) ?>)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Düzenle
          </button>
          <button class="btn-sm danger" onclick="confirmDeleteCabinet(<?= (int)$cab['id'] ?>, <?= htmlspecialchars(json_encode($cab['label']), ENT_QUOTES) ?>)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            Sil
          </button>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- SLOT MODAL -->
<div class="modal-overlay" id="modal" onclick="handleOverlayClick(event)">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="modal-title">Raf Düzenle</div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-location" id="modal-location"></div>
    <div class="form-group">
      <label class="form-label">Ürün Seç (veya Yaz)</label>
      <input type="text" id="inp-name" list="product-list" class="form-input" placeholder="Ürün adı yazın veya seçin..." onchange="onProductSelect(this)">
      <datalist id="product-list"></datalist>
      <input type="hidden" id="inp-product-id" value="">
    </div>
    <div class="form-group row-group">
      <div class="col-half">
        <label class="form-label">Miktar</label>
        <div class="qty-controls">
          <button type="button" class="qty-btn" onclick="adjustQty(-1)">-</button>
          <input type="number" id="inp-qty" class="form-input text-center" placeholder="0" min="0">
          <button type="button" class="qty-btn" onclick="adjustQty(1)">+</button>
        </div>
      </div>
      <div class="col-half">
        <label class="form-label">Kritik Eşik (Min)</label>
        <input type="number" id="inp-minqty" class="form-input" placeholder="0" min="0">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Not (opsiyonel)</label>
      <input type="text" id="inp-note" class="form-input" placeholder="Seri no, raf kodu...">
    </div>
    <div class="modal-btns">
      <button class="btn btn-success" onclick="saveSlot()">✓ Kaydet</button>
      <button class="btn btn-primary" onclick="openTransferModal()" id="btn-transfer" style="display:none;">⇄ Transfer Et</button>
      <button class="btn btn-danger" onclick="clearSlot()">✕ Temizle</button>
    </div>
    <div id="modal-feedback" class="modal-feedback"></div>
  </div>
</div>

<!-- TRANSFER MODAL -->
<div class="mgmt-modal-overlay" id="transfer-modal" onclick="handleMgmtOverlay(event, 'transfer-modal')">
  <div class="mgmt-modal">
    <div class="mgmt-modal-head">
      <div class="mgmt-modal-title">Stok Transferi</div>
      <button class="mgmt-modal-close" onclick="closeMgmtModal('transfer-modal')">✕</button>
    </div>
    
    <div style="margin-bottom:16px; padding:12px; background:var(--accent-soft); border-radius:var(--radius-sm); border:1px solid var(--accent);">
      <div style="font-size:12px; color:var(--muted); margin-bottom:4px;">Transfer Edilecek Ürün:</div>
      <div style="font-weight:700; color:var(--accent);" id="tr-item-name">-</div>
      <div style="font-size:12px; margin-top:4px;">Mevcut Miktar: <span id="tr-max-qty" style="font-weight:700;">0</span></div>
    </div>

    <div class="form-group row-group">
      <div class="col-half">
        <label class="form-label">Transfer Edilecek Miktar</label>
        <input type="number" id="tr-qty" class="form-input" min="1" value="1">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Hedef Depo</label>
      <select id="tr-warehouse" class="form-input" onchange="trWarehouseChanged()">
        <option value="">Seçiniz...</option>
      </select>
    </div>
    
    <div class="form-group">
      <label class="form-label">Hedef Dolap</label>
      <select id="tr-cabinet" class="form-input" onchange="trCabinetChanged()" disabled>
        <option value="">Önce depo seçiniz...</option>
      </select>
    </div>

    <div class="form-group row-group" id="tr-slot-group" style="display:none;">
      <div class="col-half">
        <label class="form-label" id="tr-lbl-row">Satır/Kat</label>
        <select id="tr-row" class="form-input"></select>
      </div>
      <div class="col-half" id="tr-col-wrap">
        <label class="form-label">Kolon</label>
        <select id="tr-col" class="form-input"></select>
      </div>
    </div>

    <button class="btn btn-primary btn-full mt-2" onclick="executeTransfer()" id="btn-exec-tr">Transferi Tamamla</button>
    <div id="tr-feedback" class="mgmt-feedback mt-2"></div>
  </div>
</div>

<!-- DOLAP MODAL -->
<div class="mgmt-modal-overlay" id="cab-modal" onclick="handleMgmtOverlay(event, 'cab-modal')">
  <div class="mgmt-modal">
    <div class="mgmt-modal-head">
      <div class="mgmt-modal-title" id="cab-modal-title">Yeni Dolap Ekle</div>
      <button class="mgmt-modal-close" onclick="closeMgmtModal('cab-modal')">✕</button>
    </div>
    <input type="hidden" id="cab-id" value="">
    <div class="form-group">
      <label class="form-label">Dolap Kodu *</label>
      <input type="text" id="cab-code" class="form-input" placeholder="Örn: D1, D2, A-1">
    </div>
    <div class="form-group">
      <label class="form-label">Dolap Adı</label>
      <input type="text" id="cab-label" class="form-input" placeholder="Örn: Elektronik Dolabı">
    </div>
    <div class="form-group" id="cab-type-group">
      <label class="form-label">Dolap Tipi *</label>
      <div class="type-selector">
        <div class="type-option selected" data-type="A" onclick="selectType('A')">
          <div class="type-preview type-a">
            <div class="grid-dot"></div><div class="grid-dot"></div><div class="grid-dot"></div>
            <div class="grid-dot"></div><div class="grid-dot"></div><div class="grid-dot"></div>
            <div class="grid-dot"></div><div class="grid-dot"></div><div class="grid-dot"></div>
          </div>
          <span class="type-code">A</span>
          <span class="type-desc">22 Satır × 6 Raf<br>Grid tipi</span>
        </div>
        <div class="type-option" data-type="B" onclick="selectType('B')">
          <div class="type-preview type-bc">
            <div class="row-line"></div><div class="row-line"></div><div class="row-line"></div>
            <div class="row-line"></div><div class="row-line"></div><div class="row-line"></div>
          </div>
          <span class="type-code">B</span>
          <span class="type-desc">6 Raf<br>Tek kolon</span>
        </div>
        <div class="type-option" data-type="C" onclick="selectType('C')">
          <div class="type-preview type-bc">
            <div class="row-line"></div><div class="row-line"></div><div class="row-line"></div>
          </div>
          <span class="type-code">C</span>
          <span class="type-desc">3 Raf<br>Tek kolon</span>
        </div>
      </div>
    </div>
    <div class="mgmt-modal-btns">
      <button class="btn btn-ghost" onclick="closeMgmtModal('cab-modal')">İptal</button>
      <button class="btn btn-primary" onclick="saveCabinet()">Kaydet</button>
    </div>
    <div class="mgmt-feedback" id="cab-feedback"></div>
  </div>
</div>

<!-- SİLME ONAY KUTUSU -->
<div class="confirm-overlay" id="confirm-overlay">
  <div class="confirm-box">
    <div class="confirm-title" id="confirm-title">Emin misiniz?</div>
    <p class="confirm-msg" id="confirm-msg"></p>
    <div class="confirm-btns">
      <button class="btn btn-ghost" onclick="closeConfirm()">Vazgeç</button>
      <button class="btn btn-danger" id="confirm-ok-btn">Sil</button>
    </div>
  </div>
</div>

<script>
  const API_BASE = '<?= BASE_URL ?>/api';
  const WAREHOUSE_ID = <?= (int)$warehouseId ?>;
  let selectedType = 'A';

  document.querySelectorAll('.slot').forEach(slot => {
      slot.addEventListener('click', function() {
        if (!<?= $hasEditPerm ? 'true' : 'false' ?>) {
            return;
        }
        openModal(this);
      });
  });

  function selectType(type) {
    selectedType = type;
    document.querySelectorAll('.type-option').forEach(el => el.classList.toggle('selected', el.dataset.type === type));
  }

  function openCabModal() {
    document.getElementById('cab-id').value = '';
    document.getElementById('cab-code').value = '';
    document.getElementById('cab-label').value = '';
    document.getElementById('cab-modal-title').textContent = 'Yeni Dolap Ekle';
    document.getElementById('cab-type-group').style.display = '';
    document.getElementById('cab-feedback').textContent = '';
    selectType('A');
    document.getElementById('cab-modal').classList.add('open');
    setTimeout(() => document.getElementById('cab-code').focus(), 100);
  }

  function editCabinet(id, code, label) {
    document.getElementById('cab-id').value = id;
    document.getElementById('cab-code').value = code;
    document.getElementById('cab-label').value = label;
    document.getElementById('cab-modal-title').textContent = 'Dolabı Düzenle';
    document.getElementById('cab-type-group').style.display = 'none';
    document.getElementById('cab-feedback').textContent = '';
    document.getElementById('cab-modal').classList.add('open');
    setTimeout(() => document.getElementById('cab-code').focus(), 100);
  }

  async function saveCabinet() {
    const id = document.getElementById('cab-id').value;
    const code = document.getElementById('cab-code').value.trim();
    const label = document.getElementById('cab-label').value.trim();
    if (!code) { setMgmtFeedback('cab-feedback', 'Dolap kodu boş olamaz.', 'err'); return; }

    const payload = id ? { id: parseInt(id), code, label } : { warehouse_id: WAREHOUSE_ID, code, label, type: selectedType };

    setMgmtFeedback('cab-feedback', 'Kaydediliyor…', '');
    try {
      const res = await fetch(`${API_BASE}/cabinet_upsert.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        setMgmtFeedback('cab-feedback', '✓ Kaydedildi', 'ok');
        setTimeout(() => window.location.reload(), 600);
      } else {
        setMgmtFeedback('cab-feedback', data.error || 'Kaydetme hatası.', 'err');
      }
    } catch { setMgmtFeedback('cab-feedback', 'Bağlantı hatası.', 'err'); }
  }

  function confirmDeleteCabinet(id, label) {
    document.getElementById('confirm-title').textContent = 'Dolabı Sil';
    document.getElementById('confirm-msg').textContent = `"${label}" dolabı ve içindeki tüm raf verileri kalıcı olarak silinecek.`;
    document.getElementById('confirm-ok-btn').onclick = () => { closeConfirm(); deleteCabinet(id); };
    document.getElementById('confirm-overlay').classList.add('open');
  }

  async function deleteCabinet(id) {
    try {
      const res = await fetch(`${API_BASE}/cabinet_delete.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      });
      const data = await res.json();
      if (data.success) { window.location.reload(); } else { alert(data.error || 'Silme hatası.'); }
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
