<?php
require_once dirname(__DIR__, 2) . '/includes/auth_guard.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$pageTitle = 'İçe ve Dışa Aktarma (Excel/CSV)';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">Toplu İçe / Dışa Aktarma</h2>
      <p class="page-sub">Depo verilerinizi CSV formatında yedekleyin veya toplu ürün yüklemesi yapın.</p>
    </div>
  </div>

  <div style="display:flex; gap:24px; flex-wrap:wrap;">
    
    <!-- DIŞA AKTAR (EXPORT) -->
    <div class="admin-card" style="flex:1; min-width:300px; display:flex; flex-direction:column;">
      <h3 style="margin-top:0; color:var(--accent);">Stok Verisini İndir (Dışa Aktar)</h3>
      <p style="font-size:14px; color:var(--muted); line-height:1.5; margin-bottom:20px; flex:1;">
        Mevcut depolardaki tüm dolu rafların (ürün adı, miktar, konum, vb.) listesini bilgisayarınıza CSV (Excel ile uyumlu) formatında indirin. Bu dosyayı yedekleme amacıyla saklayabilir veya düzenleyip tekrar yükleyebilirsiniz.
      </p>
      <a href="<?= BASE_URL ?>/api/export_csv.php" class="btn btn-success" style="justify-content:center;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Tüm Stokları İndir (CSV)
      </a>
    </div>

    <!-- İÇE AKTAR (IMPORT) -->
    <div class="admin-card" style="flex:1; min-width:300px; display:flex; flex-direction:column;">
      <h3 style="margin-top:0; color:var(--accent);">Toplu Veri Yükle (İçe Aktar)</h3>
      <p style="font-size:14px; color:var(--muted); line-height:1.5; margin-bottom:20px; flex:1;">
        İndirdiğiniz CSV dosyası üzerinde düzenlemeler yapıp tekrar sisteme yükleyebilirsiniz. 
        <strong>Önemli Not:</strong> Depo adı ve Dolap Kodu sistemdeki mevcut kodlarla tam eşleşmelidir. Sütun sırasını ve başlıkları değiştirmeyiniz (Noktalı virgül ';' ayracı kullanınız).
      </p>
      
      <form id="import-form" onsubmit="handleImport(event)">
        <div class="form-group">
          <input type="file" id="csv_file" name="csv_file" accept=".csv" required style="padding:10px; border:1px dashed var(--border); width:100%; border-radius:var(--radius-sm);">
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="btn-import">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
          Yükle ve Güncelle
        </button>
        <div id="import-feedback" class="mgmt-feedback mt-2"></div>
      </form>
    </div>

  </div>
</div>

<style>
.admin-card { background: #fff; padding: 24px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); }
</style>

<script>
async function handleImport(e) {
  e.preventDefault();
  const fileInput = document.getElementById('csv_file');
  const file = fileInput.files[0];
  const fb = document.getElementById('import-feedback');
  const btn = document.getElementById('btn-import');

  if (!file) {
    fb.textContent = 'Lütfen bir dosya seçin.';
    fb.className = 'mgmt-feedback mt-2 err';
    return;
  }

  fb.textContent = 'Yükleniyor ve işleniyor, lütfen bekleyin...';
  fb.className = 'mgmt-feedback mt-2 text-muted';
  btn.disabled = true;

  const formData = new FormData();
  formData.append('csv_file', file);

  try {
    const apiBase = typeof API_BASE !== 'undefined' ? API_BASE : BASE_URL + '/api';
    const res = await fetch(apiBase + '/import_csv.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    
    if (data.success) {
      let msg = data.message;
      if (data.error_count > 0) {
        msg += ' Ancak bazı satırlarda hatalar oluştu.';
        console.warn('Import Hataları:', data.errors);
      }
      fb.innerHTML = msg;
      fb.className = 'mgmt-feedback mt-2 ' + (data.error_count > 0 ? 'err' : 'ok');
      
      if (data.error_count > 0) {
        fb.innerHTML += '<ul style="margin-top:8px; padding-left:16px; font-size:12px; color:var(--red); text-align:left;">';
        data.errors.forEach(e => {
          fb.innerHTML += `<li>${e}</li>`;
        });
        fb.innerHTML += '</ul>';
      } else {
        fileInput.value = '';
      }

    } else {
      fb.textContent = data.error || 'Bilinmeyen bir hata oluştu.';
      fb.className = 'mgmt-feedback mt-2 err';
    }
  } catch (err) {
    fb.textContent = 'Bağlantı veya sunucu hatası.';
    fb.className = 'mgmt-feedback mt-2 err';
  } finally {
    btn.disabled = false;
  }
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
