<?php
require_once dirname(dirname(__DIR__)) . '/config/auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

requireLogin();
requireAdmin();

$pdo = getDB();

// Kullanıcıları çek
$users = $pdo->query('SELECT id, username, full_name, email, phone, role, created_at FROM users ORDER BY role ASC, username ASC')->fetchAll();

// Yetkileri Çek
$uw_stmt = $pdo->query('SELECT user_id, warehouse_id, permission FROM user_warehouses');
$user_perms = [];
foreach ($uw_stmt->fetchAll() as $row) {
    $user_perms[$row['user_id']][$row['warehouse_id']] = $row['permission'];
}

// Binaları Çek
$warehouses = $pdo->query('SELECT id, name FROM warehouses ORDER BY name ASC')->fetchAll();

$pageTitle = 'Kullanıcı Yönetimi';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Kullanıcı Yönetimi</h2>
      <p class="page-sub">Sistem kullanıcılarını ve yetkilerini yönetin</p>
    </div>
    <div class="hero-actions">
      <button class="btn btn-primary" onclick="openUserModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Yeni Kullanıcı
      </button>
    </div>
  </div>

  <div class="user-grid">
    <?php foreach ($users as $u): ?>
    <div class="user-card" id="user-card-<?= (int)$u['id'] ?>">
      <div class="user-card-head">
        <div class="user-avatar-large"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
        <div class="user-card-info">
          <h3 class="user-card-name"><?= h($u['full_name']) ?></h3>
          <span class="user-card-username mono">@<?= h($u['username']) ?></span>
        </div>
        <span class="role-badge role-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span>
      </div>
      <div class="user-card-body">
        <div class="user-meta-list">
          <?php if ($u['role'] !== 'admin' && isset($user_perms[$u['id']])): ?>
          <div class="user-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            <span><?= count($user_perms[$u['id']]) ?> Depoda Yetkili</span>
          </div>
          <?php endif; ?>
          <?php if ($u['email']): ?>
          <div class="user-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span><?= h($u['email']) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($u['phone']): ?>
          <div class="user-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <span><?= h($u['phone']) ?></span>
          </div>
          <?php endif; ?>
          <div class="user-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span><?= date('d.m.Y', strtotime($u['created_at'])) ?></span>
          </div>
        </div>
      </div>
      <?php if ($u['id'] !== $_SESSION['user_id']): ?>
      <div class="user-card-actions">
        <?php
          $u_data = $u;
          $u_data['perms'] = $user_perms[$u['id']] ?? [];
        ?>
        <button class="btn-sm" onclick='editUser(<?= (int)$u['id'] ?>, <?= json_encode($u_data, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Düzenle
        </button>
        <button class="btn-sm danger" onclick="deleteUser(<?= (int)$u['id'] ?>, <?= htmlspecialchars(json_encode($u['username']), ENT_QUOTES) ?>)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          Sil
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- KULLANICI MODAL -->
<div class="mgmt-modal-overlay" id="user-modal" onclick="handleMgmtOverlay(event, 'user-modal')">
  <div class="mgmt-modal">
    <div class="mgmt-modal-head">
      <div class="mgmt-modal-title" id="user-modal-title">Yeni Kullanıcı</div>
      <button class="mgmt-modal-close" onclick="closeMgmtModal('user-modal')">✕</button>
    </div>
    <form id="user-form" onsubmit="saveUser(event)">
      <input type="hidden" id="user-id" value="">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Kullanıcı Adı</label>
          <input type="text" id="user-username" class="form-input" required placeholder="ör: ahmet_yilmaz">
        </div>
        <div class="form-group">
          <label class="form-label">Ad Soyad</label>
          <input type="text" id="user-fullname" class="form-input" required placeholder="ör: Ahmet Yılmaz">
        </div>
        <div class="form-group">
          <label class="form-label">E-Posta</label>
          <input type="email" id="user-email" class="form-input" placeholder="ör: ahmet@sirket.com">
        </div>
        <div class="form-group">
          <label class="form-label">Telefon</label>
          <input type="text" id="user-phone" class="form-input" placeholder="ör: 05xx xxx xx xx">
        </div>
        <div class="form-group full">
          <label class="form-label">Şifre <span id="pw-note" style="font-weight:400; font-size:11px; color:var(--muted)">(Değiştirmek istemiyorsanız boş bırakın)</span></label>
          <input type="password" id="user-password" class="form-input" placeholder="••••••••">
        </div>
        <div class="form-group full">
          <label class="form-label">Rol</label>
          <select id="user-role" class="form-input" onchange="toggleWarehousePerms()">
            <option value="admin">Admin (Tam Yetki)</option>
            <option value="editor">Editör (Sistem Geneli - Özel Yetki Gerektirmez)</option>
            <option value="viewer" selected>İzleyici (Bina Bazlı Yetki)</option>
          </select>
        </div>
        
        <div class="form-group full" id="warehouse-perms-group" style="display:none; border: 1px solid var(--border); padding: 15px; border-radius: 8px;">
            <label class="form-label" style="margin-bottom: 10px;">Bina Yetkileri (Sadece İzleyici/Editör rollerinde geçerlidir)</label>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach($warehouses as $w): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border2); padding-bottom: 5px;">
                    <span style="font-weight: 500; font-size: 14px;"><?= h($w['name']) ?></span>
                    <select class="form-input warehouse-perm-select" data-wid="<?= $w['id'] ?>" style="width: 150px; padding: 4px;">
                        <option value="none">Yetki Yok</option>
                        <option value="view">Görüntüle</option>
                        <option value="edit">Düzenle</option>
                    </select>
                </div>
                <?php endforeach; ?>
                <?php if(empty($warehouses)): ?>
                    <span style="color:var(--muted); font-size: 13px;">Sistemde henüz bina bulunmuyor.</span>
                <?php endif; ?>
            </div>
        </div>
      </div>
      <div class="mgmt-modal-btns">
        <button type="button" class="btn btn-ghost" onclick="closeMgmtModal('user-modal')">İptal</button>
        <button type="submit" class="btn btn-primary" id="btn-user-save">Kaydet</button>
      </div>
    </form>
    <div id="user-feedback" class="mgmt-feedback"></div>
  </div>
</div>

<style>
.user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
.user-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; display: flex; flex-direction: column; gap: 16px; transition: transform .2s, box-shadow .2s; }
.user-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.user-card-head { display: flex; align-items: center; gap: 12px; }
.user-avatar-large { width: 48px; height: 48px; border-radius: 12px; background: var(--accent-soft); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; }
.user-card-info { flex: 1; }
.user-card-name { font-size: 16px; font-weight: 700; margin-bottom: 2px; color: var(--text); }
.user-card-username { font-size: 12px; color: var(--muted); }

.role-badge { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; font-family: 'IBM Plex Mono', monospace; }
.role-admin { background: #fee2e2; color: #b91c1c; }
.role-editor { background: #dcfce7; color: #15803d; }
.role-viewer { background: #f1f5f9; color: #475569; }

.user-meta-list { display: flex; flex-direction: column; gap: 8px; }
.user-meta-item { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; }
.user-meta-item svg { width: 14px; height: 14px; opacity: 0.7; }

.user-card-actions { display: flex; gap: 8px; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--border2); }
.user-card-actions .btn-sm { flex: 1; justify-content: center; }

/* Modal Grid */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group.full { grid-column: span 2; }
</style>

<script>
function handleMgmtOverlay(e, id) {
  if (e.target === e.currentTarget) closeMgmtModal(id);
}

function closeMgmtModal(id) {
  document.getElementById(id).classList.remove('open');
}

function toggleWarehousePerms() {
    const role = document.getElementById('user-role').value;
    const group = document.getElementById('warehouse-perms-group');
    if (role === 'admin') {
        group.style.display = 'none';
    } else {
        group.style.display = 'block';
    }
}

function openUserModal() {
  document.getElementById('user-id').value = '';
  document.getElementById('user-username').value = '';
  document.getElementById('user-fullname').value = '';
  document.getElementById('user-email').value = '';
  document.getElementById('user-phone').value = '';
  document.getElementById('user-password').value = '';
  document.getElementById('user-password').required = true;
  document.getElementById('pw-note').style.display = 'none';
  document.getElementById('user-role').value = 'viewer';
  
  // Clear permissions
  document.querySelectorAll('.warehouse-perm-select').forEach(sel => sel.value = 'none');
  
  toggleWarehousePerms();
  
  document.getElementById('user-modal-title').textContent = 'Yeni Kullanıcı';
  document.getElementById('user-feedback').textContent = '';
  document.getElementById('user-modal').classList.add('open');
}

function editUser(id, data) {
  document.getElementById('user-id').value = id;
  document.getElementById('user-username').value = data.username;
  document.getElementById('user-fullname').value = data.full_name;
  document.getElementById('user-email').value = data.email || '';
  document.getElementById('user-phone').value = data.phone || '';
  document.getElementById('user-password').value = '';
  document.getElementById('user-password').required = false;
  document.getElementById('pw-note').style.display = 'inline';
  document.getElementById('user-role').value = data.role;
  
  // Set permissions
  document.querySelectorAll('.warehouse-perm-select').forEach(sel => {
      const wid = sel.dataset.wid;
      if (data.perms && data.perms[wid]) {
          sel.value = data.perms[wid];
      } else {
          sel.value = 'none';
      }
  });
  
  toggleWarehousePerms();
  
  document.getElementById('user-modal-title').textContent = 'Kullanıcı Düzenle';
  document.getElementById('user-feedback').textContent = '';
  document.getElementById('user-modal').classList.add('open');
}

async function saveUser(e) {
  e.preventDefault();
  const id       = document.getElementById('user-id').value;
  const username = document.getElementById('user-username').value.trim();
  const fullname = document.getElementById('user-fullname').value.trim();
  const email    = document.getElementById('user-email').value.trim();
  const phone    = document.getElementById('user-phone').value.trim();
  const password = document.getElementById('user-password').value;
  const role     = document.getElementById('user-role').value;
  
  const warehouses = {};
  document.querySelectorAll('.warehouse-perm-select').forEach(sel => {
      if (sel.value !== 'none') {
          warehouses[sel.dataset.wid] = sel.value;
      }
  });

  const btn = document.getElementById('btn-user-save');
  const fb  = document.getElementById('user-feedback');

  btn.disabled = true;
  fb.textContent = 'Kaydediliyor...';
  fb.className = 'mgmt-feedback';

  try {
    const res = await fetch('<?= BASE_URL ?>/api/user_upsert.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, username, fullname, email, phone, password, role, warehouses })
    });
    const data = await res.json();
    if (data.success) {
      fb.textContent = 'Başarıyla kaydedildi! Sayfa yenileniyor...';
      fb.className = 'mgmt-feedback ok';
      setTimeout(() => location.reload(), 800);
    } else {
      fb.textContent = data.error || 'Bir hata oluştu.';
      fb.className = 'mgmt-feedback err';
      btn.disabled = false;
    }
  } catch (err) {
    fb.textContent = 'Bağlantı hatası.';
    fb.className = 'mgmt-feedback err';
    btn.disabled = false;
  }
}

async function deleteUser(id, username) {
  if (!confirm(`@${username} kullanıcısını silmek istediğinize emin misiniz?`)) return;
  
  try {
    const res = await fetch('<?= BASE_URL ?>/api/user_delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById(`user-card-${id}`).style.opacity = '0.5';
      document.getElementById(`user-card-${id}`).style.pointerEvents = 'none';
      setTimeout(() => location.reload(), 500);
    } else {
      alert(data.error || 'Silme başarısız.');
    }
  } catch (err) {
    alert('Bağlantı hatası.');
  }
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
