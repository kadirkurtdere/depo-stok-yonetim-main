<?php
require_once dirname(__DIR__) . '/config/auth.php';
requireLogin();

$currentUser = getCurrentUser();
$pageTitle = 'Profilim';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Profil Ayarları</h2>
      <p class="page-sub">Kişisel bilgilerinizi ve şifrenizi güncelleyin</p>
    </div>
  </div>

  <div class="profile-grid">
    <!-- Bilgi Kartı -->
    <div class="profile-card">
      <div class="profile-card-head">
        <div class="profile-avatar-large"><?= strtoupper(substr($currentUser['username'], 0, 1)) ?></div>
        <div>
          <h3 class="profile-fullname"><?= h($currentUser['full_name']) ?></h3>
          <p class="profile-username mono">@<?= h($currentUser['username']) ?></p>
        </div>
      </div>
      <div class="profile-info-list">
        <div class="p-info-item">
          <span class="p-label">Yetki Seviyesi:</span>
          <span class="role-badge role-<?= $currentUser['role'] ?>"><?= strtoupper($currentUser['role']) ?></span>
        </div>
      </div>
    </div>

    <!-- Şifre Değiştirme Formu -->
    <div class="profile-card">
      <h3 class="card-title">Şifre Değiştir</h3>
      <form id="password-form" onsubmit="updatePassword(event)">
        <div class="form-group">
          <label class="form-label">Mevcut Şifre</label>
          <input type="password" id="old_password" class="form-input" required placeholder="••••••••">
        </div>
        <div class="form-group">
          <label class="form-label">Yeni Şifre</label>
          <input type="password" id="new_password" class="form-input" required placeholder="Min. 6 karakter">
        </div>
        <div class="form-group">
          <label class="form-label">Yeni Şifre (Tekrar)</label>
          <input type="password" id="new_password_confirm" class="form-input" required placeholder="Tekrar yazın">
        </div>
        <div class="form-btns">
          <button type="submit" class="btn btn-primary btn-full" id="btn-pw-save">Şifreyi Güncelle</button>
        </div>
      </form>
      <div id="pw-feedback" class="mgmt-feedback"></div>
    </div>
  </div>
</div>

<style>
.profile-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
@media (min-width: 768px) { .profile-grid { grid-template-columns: 350px 1fr; } }

.profile-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
.profile-card-head { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; border-bottom: 1px solid var(--border2); padding-bottom: 20px; }
.profile-avatar-large { width: 64px; height: 64px; border-radius: 16px; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; }
.profile-fullname { font-size: 20px; font-weight: 700; }
.profile-username { color: var(--muted); font-size: 14px; }

.card-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; }
.p-info-item { display: flex; align-items: center; justify-content: space-between; font-size: 14px; }
.p-label { color: var(--muted); }

.role-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 800; font-family: 'IBM Plex Mono', monospace; }
.role-admin { background: #fee2e2; color: #b91c1c; }
.role-editor { background: #dcfce7; color: #15803d; }
.role-viewer { background: #f1f5f9; color: #475569; }

.form-btns { margin-top: 24px; }
</style>

<script>
async function updatePassword(e) {
  e.preventDefault();
  const oldPw = document.getElementById('old_password').value;
  const newPw = document.getElementById('new_password').value;
  const confirmPw = document.getElementById('new_password_confirm').value;
  
  const fb = document.getElementById('pw-feedback');
  const btn = document.getElementById('btn-pw-save');

  if (newPw.length < 6) {
    fb.textContent = 'Yeni şifre en az 6 karakter olmalıdır.';
    fb.className = 'mgmt-feedback err';
    return;
  }

  if (newPw !== confirmPw) {
    fb.textContent = 'Şifreler birbiriyle uyuşmuyor.';
    fb.className = 'mgmt-feedback err';
    return;
  }

  btn.disabled = true;
  fb.textContent = 'Güncelleniyor...';
  fb.className = 'mgmt-feedback';

  try {
    const res = await fetch('<?= BASE_URL ?>/api/profile_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ old_password: oldPw, new_password: newPw })
    });
    const data = await res.json();
    if (data.success) {
      fb.textContent = 'Şifreniz başarıyla güncellendi!';
      fb.className = 'mgmt-feedback ok';
      document.getElementById('password-form').reset();
    } else {
      fb.textContent = data.error || 'Güncelleme başarısız.';
      fb.className = 'mgmt-feedback err';
    }
  } catch (err) {
    fb.textContent = 'Bağlantı hatası.';
    fb.className = 'mgmt-feedback err';
  } finally {
    btn.disabled = false;
  }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
