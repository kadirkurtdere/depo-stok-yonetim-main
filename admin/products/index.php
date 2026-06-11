<?php
$pageTitle = 'Ürün Yönetimi (Master Data)';
require_once dirname(__DIR__, 2) . '/config/auth.php';
requireLogin();

// Sadece admin
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    die("Yetkisiz erişim.");
}

require_once dirname(__DIR__, 2) . '/config/database.php';
$pdo = getDB();

// Ürün Ekleme/Güncelleme
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Geçersiz CSRF token.");
    }
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $minQty = (int)($_POST['min_qty'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');
    
    if ($action === 'add' || $action === 'edit') {
        if (empty($name)) {
            $errorMsg = "Ürün adı boş olamaz.";
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare('INSERT INTO products (name, category, description, min_qty, barcode) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $category, $description, $minQty, $barcode]);
                    $successMsg = "Ürün başarıyla eklendi.";
                } else {
                    $stmt = $pdo->prepare('UPDATE products SET name = ?, category = ?, description = ?, min_qty = ?, barcode = ? WHERE id = ?');
                    $stmt->execute([$name, $category, $description, $minQty, $barcode, $id]);
                    $successMsg = "Ürün güncellendi.";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errorMsg = "Bu isimde bir ürün zaten mevcut.";
                } else {
                    $errorMsg = "Hata: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $successMsg = "Ürün silindi.";
        } catch (PDOException $e) {
            $errorMsg = "Bu ürün kullanımda olabilir, silinemedi.";
        }
    }
}

// Ürünleri Listele
$stmt = $pdo->query('SELECT * FROM products ORDER BY name ASC');
$products = $stmt->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-wrap">
  <div class="page-hero">
    <div>
      <h2 class="page-title">📦 Ürün Veritabanı (Master Data)</h2>
      <p class="page-sub">Sistemdeki tüm ürünleri ve kritik stok seviyelerini buradan yönetebilirsiniz</p>
    </div>
    <div class="hero-actions">
      <button class="btn btn-primary" onclick="openProductModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Yeni Ürün Ekle
      </button>
    </div>
  </div>

  <div style="background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
    <?php if ($successMsg): ?>
      <div class="mgmt-feedback ok" style="display:block; margin-bottom:15px;"><?= h($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
      <div class="mgmt-feedback err" style="display:block; margin-bottom:15px;"><?= h($errorMsg) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Ürün Adı</th>
            <th>Kategori</th>
            <th>Min. Stok</th>
            <th>Barkod</th>
            <th>İşlemler</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($products as $p): ?>
          <tr>
            <td>#<?= $p['id'] ?></td>
            <td><strong><?= h($p['name']) ?></strong></td>
            <td><span class="loc-tag"><?= h($p['category'] ?: '-') ?></span></td>
            <td><?= $p['min_qty'] ?></td>
            <td><?= h($p['barcode'] ?: '-') ?></td>
            <td>
              <div class="actions-cell" style="display:flex; gap:8px;">
                <button class="btn-sm" onclick='editProduct(<?= json_encode($p) ?>)'>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Düzenle
                </button>
                <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('Bu ürünü silmek istediğinize emin misiniz?');">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn-sm danger" style="background:#fff0f0; color:#d32f2f; border:1px solid #ffcdd2;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    Sil
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($products)): ?>
          <tr><td colspan="6" style="text-align:center;">Henüz ürün eklenmemiş.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>



<!-- Ürün Modal -->
<div id="product-modal" class="mgmt-modal-overlay" onclick="if(event.target===this) closeProductModal()">
  <div class="mgmt-modal" style="max-width: 500px;">
    <div class="mgmt-modal-head">
      <div class="mgmt-modal-title" id="product-modal-title">Ürün Ekle</div>
      <button class="mgmt-modal-close" type="button" onclick="closeProductModal()">✕</button>
    </div>
    <div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" id="product-action" value="add">
        <input type="hidden" name="product_id" id="product-id" value="">
        
        <div class="form-grid">
          <div class="form-group full">
            <label class="form-label">Ürün Adı <span style="color:red;">*</span></label>
            <input type="text" class="form-input" name="name" id="product-name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Kategori</label>
            <input type="text" class="form-input" name="category" id="product-category" placeholder="Örn: Sarf Malzeme">
          </div>
          <div class="form-group">
            <label class="form-label">Barkod Kodu</label>
            <input type="text" class="form-input" name="barcode" id="product-barcode" placeholder="Barkod veya QR numarası">
          </div>
          <div class="form-group full">
            <label class="form-label">Kritik Stok (Min. Miktar) <span style="color:red;">*</span></label>
            <input type="number" class="form-input" name="min_qty" id="product-minqty" value="0" required min="0">
          </div>
          <div class="form-group full">
            <label class="form-label">Açıklama</label>
            <textarea class="form-input" name="description" id="product-desc" rows="3"></textarea>
          </div>
        </div>
        
        <div class="mgmt-modal-btns" style="margin-top:20px;">
          <button type="button" class="btn btn-ghost" onclick="closeProductModal()">İptal</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openProductModal() {
    document.getElementById('product-modal-title').textContent = 'Yeni Ürün Ekle';
    document.getElementById('product-action').value = 'add';
    document.getElementById('product-id').value = '';
    document.getElementById('product-name').value = '';
    document.getElementById('product-category').value = '';
    document.getElementById('product-barcode').value = '';
    document.getElementById('product-minqty').value = '0';
    document.getElementById('product-desc').value = '';
    document.getElementById('product-modal').classList.add('open');
}

function editProduct(product) {
    document.getElementById('product-modal-title').textContent = 'Ürün Düzenle';
    document.getElementById('product-action').value = 'edit';
    document.getElementById('product-id').value = product.id;
    document.getElementById('product-name').value = product.name;
    document.getElementById('product-category').value = product.category || '';
    document.getElementById('product-barcode').value = product.barcode || '';
    document.getElementById('product-minqty').value = product.min_qty;
    document.getElementById('product-desc').value = product.description || '';
    document.getElementById('product-modal').classList.add('open');
}

function closeProductModal() {
    document.getElementById('product-modal').classList.remove('open');
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
