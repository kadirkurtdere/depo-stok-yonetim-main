/**
 * Depo Yönetim Sistemi — Frontend JS
 * Slot modal aç/kapat, AJAX kaydet/temizle
 */

// Yardımcı fonksiyonlar
const h = (str) => String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const numToLetter = (num) => String.fromCharCode(64 + parseInt(num));

let activeSlot = null; // { el, cabId, row, col, cabLabel }
const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

let masterProducts = [];

// Sayfa yüklendiğinde Master Data ürünlerini çek
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const apiBase = typeof API_BASE !== 'undefined' ? API_BASE : BASE_URL + '/api';
    const res = await fetch(apiBase + '/products.php');
    const data = await res.json();
    if (data.success) {
      masterProducts = data.data;
      const datalist = document.getElementById('product-list');
      if (datalist) {
        masterProducts.forEach(p => {
          const option = document.createElement('option');
          option.value = p.name;
          option.dataset.minqty = p.min_qty;
          option.dataset.id = p.id;
          datalist.appendChild(option);
        });
      }
    }
  } catch (e) {
    console.error('Ürünler yüklenirken hata oluştu', e);
  }
});

function onProductSelect(input) {
  const selectedName = input.value;
  const product = masterProducts.find(p => p.name === selectedName);
  const qtyInput = document.getElementById('inp-qty');
  const minQtyInput = document.getElementById('inp-minqty');
  
  if (product) {
    // Ürün master data'da varsa min_qty'yi otomatik getir (eğer mevcut raf sıfırsa veya yeni ekleniyorsa vs.)
    if (!minQtyInput.value || minQtyInput.value == '0') {
      minQtyInput.value = product.min_qty;
    }
  }
}

/* ── QR/BARKOD SCANNER Lojik ─────────────────────────────────────── */
let html5QrcodeScanner = null;

document.addEventListener('DOMContentLoaded', () => {
    // Both desktop and mobile scanner buttons
    const btnScanner = document.getElementById('btn-global-scanner');
    const btnDesktopScanner = document.getElementById('btn-desktop-scanner');
    
    if (btnScanner) btnScanner.addEventListener('click', openScannerModal);
    if (btnDesktopScanner) btnDesktopScanner.addEventListener('click', openScannerModal);
});

function openScannerModal() {
    document.getElementById('scanner-modal').classList.add('open');
    if (!html5QrcodeScanner && typeof Html5QrcodeScanner !== 'undefined') {
        html5QrcodeScanner = new Html5QrcodeScanner(
            "reader",
            { 
                fps: 20, 
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                    const qrboxSize = Math.floor(minEdge * 0.7);
                    return {
                        width: qrboxSize,
                        height: qrboxSize
                    };
                },
                aspectRatio: 1.0
            },
            /* verbose= */ false
        );
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    }
}

function closeScannerModal() {
    document.getElementById('scanner-modal').classList.remove('open');
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(error => {
            console.error("Failed to clear html5QrcodeScanner. ", error);
        });
        html5QrcodeScanner = null;
    }
}

function onScanSuccess(decodedText, decodedResult) {
    closeScannerModal();
    console.log("Scanned:", decodedText);
    
    if (decodedText.startsWith('SLOT_')) {
        const parts = decodedText.split('_');
        if (parts.length === 4) {
            const cabId = parseInt(parts[1]);
            const row = parseInt(parts[2]);
            const col = parseInt(parts[3]);
            
            const slotEl = document.querySelector(`.slot[data-cab="${cabId}"][data-row="${row}"][data-col="${col}"]`);
            if (slotEl) {
                if (slotEl.hasAttribute('onclick')) {
                     slotEl.click();
                } else {
                     alert("Bu rafı düzenleme yetkiniz yok.");
                }
            } else {
                alert(`Raf (${cabId}-${row}-${col}) bu sayfada bulunamadı.`);
            }
        }
    } else {
        // Sync to all search inputs
        const inputs = [document.getElementById('global-search'), document.getElementById('global-search-desktop')];
        inputs.forEach(inp => {
            if (inp) {
                inp.value = decodedText;
                inp.dispatchEvent(new Event('input'));
            }
        });
    }
}

function onScanFailure(error) {
    // Çok fazla log basmamak için boş
}

/* ── GLOBAL SEARCH ────────────────────────────────────────────────── */
function initSearch(inputId, resultsId) {
  const input = document.getElementById(inputId);
  const results = document.getElementById(resultsId);
  if (!input || !results) return;

  let searchTimeout;
  input.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const q = e.target.value.trim();
    if (q.length < 2) {
      results.classList.remove('open');
      return;
    }

    searchTimeout = setTimeout(async () => {
      try {
        const res = await fetch(`${BASE_URL}/api/search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.success) {
          renderSearchResults(data.results, results);
        }
      } catch (err) { console.error('Search error:', err); }
    }, 300);
  });

  document.addEventListener('click', (e) => {
    if (!input.contains(e.target) && !results.contains(e.target)) {
      results.classList.remove('open');
    }
  });
}

initSearch('global-search', 'search-results');
initSearch('global-search-desktop', 'search-results-desktop');

// ── ADMIN MENU TOGGLE ──────────────────────────────────────────────
const adminBtn = document.querySelector('.admin-btn');
const adminMenu = document.querySelector('.admin-menu');

if (adminBtn && adminMenu) {
  adminBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    adminMenu.classList.toggle('active');
  });

  document.addEventListener('click', () => {
    adminMenu.classList.remove('active');
  });
}

const userChip = document.getElementById('user-chip-toggle');
const userMenu = document.querySelector('.user-menu-wrap');
if (userChip && userMenu) {
  userChip.addEventListener('click', (e) => {
    e.stopPropagation();
    userMenu.classList.toggle('active');
  });
  document.addEventListener('click', () => {
    userMenu.classList.remove('active');
  });
}

function renderSearchResults(results, resultsBox) {
  if (results.length === 0) {
    resultsBox.innerHTML = '<div class="search-empty">Sonuç bulunamadı.</div>';
  } else {
    const groups = {};
    results.forEach(r => {
      if (!groups[r.building_name]) groups[r.building_name] = [];
      groups[r.building_name].push(r);
    });

    let html = '';
    for (const [buildingName, items] of Object.entries(groups)) {
      html += `<div class="search-category">${h(buildingName)}</div>`;
      html += items.map(r => `
        <a href="${BASE_URL}/depolar/index.php?id=${r.warehouse_id}" class="search-item">
          <div class="si-name">${h(r.item_name)}</div>
          ${r.item_qty ? `<div class="si-qty">${h(r.item_qty)} adet</div>` : ''}
          <div class="si-path">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 18l6-6-6-6"/></svg>
            <span>${h(r.warehouse_name)}</span>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 18l6-6-6-6"/></svg>
            <span>${h(r.cabinet_code)} / ${r.cabinet_type === 'A' ? `S${r.row_num}-${numToLetter(r.col_num)}` : `Kat ${r.row_num}`}</span>
          </div>
        </a>
      `).join('');
    }
    resultsBox.innerHTML = html;
  }
  resultsBox.classList.add('open');
}

/* ── MODAL LOGIC ─────────────────────────────────────────────────── */
// ── MODAL AÇ ──────────────────────────────────────────────────────
function openModal(el) {
  // Önceki seçimi temizle
  document.querySelectorAll('.slot.selected').forEach(s => s.classList.remove('selected'));
  el.classList.add('selected');

  const cabId    = el.dataset.cab;
  const row      = el.dataset.row;
  const col      = el.dataset.col;
  const cabCode  = el.closest('.cab-card')?.querySelector('.cab-code')?.textContent || '';
  const cabLabel = el.closest('.cab-card')?.querySelector('.cab-label')?.textContent || '';

  activeSlot = { el, cabId, row, col, cabLabel, cabCode };

  // Modal başlığı
  const type = el.classList.contains('slot-bc') ? 'bc' : 'a';
  document.getElementById('modal-title').textContent = cabLabel + ' — Raf Düzenle';
  document.getElementById('modal-location').textContent =
    type === 'bc'
      ? `${cabCode}  ·  KAT ${row}`
      : `${cabCode}  ·  SATIR ${row}  ·  KOLON ${numToLetter(col)}`;

  // Mevcut değerleri doldur
  document.getElementById('inp-name').value = el.dataset.name || '';
  document.getElementById('inp-qty').value  = el.dataset.qty  || '';
  document.getElementById('inp-minqty').value = el.dataset.minqty || '';
  document.getElementById('inp-note').value = el.dataset.note || '';

  const btnTransfer = document.getElementById('btn-transfer');
  if (el.dataset.name && parseInt(el.dataset.qty) > 0) {
      btnTransfer.style.display = 'inline-block';
  } else {
      btnTransfer.style.display = 'none';
  }

  clearFeedback();
  document.getElementById('modal').classList.add('open');

  // Focus ilk input'a (iOS klavye için küçük delay)
  setTimeout(() => document.getElementById('inp-name').focus(), 100);
}

function closeModal() {
  const modal = document.getElementById('modal');
  if (modal) modal.classList.remove('open');
  if (activeSlot) {
    activeSlot.el.classList.remove('selected');
    activeSlot = null;
  }
}

function handleOverlayClick(e) {
  if (e.target === e.currentTarget) closeModal();
}

// ── KAYDET ────────────────────────────────────────────────────────
async function saveSlot() {
  if (!activeSlot) return;
  const name   = document.getElementById('inp-name').value.trim();
  const qty    = document.getElementById('inp-qty').value.trim();
  const minqty = document.getElementById('inp-minqty').value.trim();
  const note   = document.getElementById('inp-note').value.trim();

  if (!name) {
    setFeedback('Ürün adı boş olamaz.', 'err');
    document.getElementById('inp-name').focus();
    return;
  }

  setFeedback('Kaydediliyor…', '');
  try {
    const slotApiUrl = (typeof API_BASE !== 'undefined' ? API_BASE : BASE_URL + '/api') + '/slot_update.php';
    const res = await fetch(slotApiUrl, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken()
      },
      body: JSON.stringify({
        action:     'save',
        cabinet_id: parseInt(activeSlot.cabId),
        row:        parseInt(activeSlot.row),
        col:        parseInt(activeSlot.col),
        item_name:  name,
        item_qty:   qty ? parseInt(qty) : 0,
        min_qty:    minqty ? parseInt(minqty) : 0,
        item_note:  note,
      }),
    });
    const data = await res.json();
    if (data.success) {
      // DOM güncelle
      updateSlotDOM(activeSlot.el, name, qty, minqty, note);
      setFeedback('✓ Kaydedildi', 'ok');
      setTimeout(closeModal, 600);
    } else {
      setFeedback(data.error || 'Kaydetme hatası.', 'err');
    }
  } catch (err) {
    console.error('Save error:', err);
    setFeedback('Hata: ' + err.message, 'err');
  }
}

// ── TEMİZLE ───────────────────────────────────────────────────────
async function clearSlot() {
  if (!activeSlot) return;
  setFeedback('Temizleniyor…', '');
  try {
    const slotApiUrl = (typeof API_BASE !== 'undefined' ? API_BASE : BASE_URL + '/api') + '/slot_update.php';
    const res = await fetch(slotApiUrl, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken()
      },
      body: JSON.stringify({
        action:     'clear',
        cabinet_id: parseInt(activeSlot.cabId),
        row:        parseInt(activeSlot.row),
        col:        parseInt(activeSlot.col),
      }),
    });
    const data = await res.json();
    if (data.success) {
      updateSlotDOM(activeSlot.el, '', '', '', '');
      setFeedback('✓ Temizlendi', 'ok');
      setTimeout(closeModal, 600);
    } else {
      setFeedback(data.error || 'Temizleme hatası.', 'err');
    }
  } catch {
    setFeedback('Bağlantı hatası.', 'err');
  }
}

// ── DOM GÜNCELLE ──────────────────────────────────────────────────
function updateSlotDOM(el, name, qty, minqty, note) {
  el.dataset.name = name;
  el.dataset.qty  = qty;
  el.dataset.minqty = minqty;
  el.dataset.note = note;

  const isBc = el.classList.contains('slot-bc');

  const q = qty ? parseInt(qty) : 0;
  const mq = minqty ? parseInt(minqty) : 0;
  const isLow = (name && q <= mq);

  if (isLow) el.classList.add('low-stock');
  else el.classList.remove('low-stock');

  if (name) {
    el.classList.add('filled');
    if (isBc) {
      el.querySelector('.sbc-content').innerHTML =
        `<div class="sbc-name">${esc(name)}</div>` +
        (qty ? `<div class="sbc-qty">${esc(qty)} adet</div>` : '');
      el.querySelector('.sbc-arrow').textContent = '✎';
    } else {
      el.innerHTML =
        `<span class="sa-name">${esc(name)}</span>` +
        (qty ? `<span class="sa-qty">${esc(qty)}</span>` : '');
    }
  } else {
    el.classList.remove('filled');
    if (isBc) {
      el.querySelector('.sbc-content').innerHTML = '<div class="sbc-empty">Boş — düzenlemek için tıklayın</div>';
      el.querySelector('.sbc-arrow').textContent = '+';
    } else {
      el.innerHTML = `<span class="sa-empty">—</span>`;
    }
  }

  // Dolap başlığındaki sayacı güncelle
  updateCabinetCounter(el);
}

function updateCabinetCounter(slotEl) {
  const card  = slotEl.closest('.cab-card');
  if (!card) return;
  const filled = card.querySelectorAll('.slot.filled').length;
  const total  = card.querySelectorAll('.slot').length;
  const badge  = card.querySelector('.cab-fill-badge');
  if (badge) badge.textContent = filled + '/' + total;
  const progFill = card.querySelector('.cab-prog-fill');
  if (progFill) progFill.style.width = total > 0 ? Math.round(filled/total*100) + '%' : '0%';
}

function adjustQty(amount) {
  const input = document.getElementById('inp-qty');
  let current = parseInt(input.value) || 0;
  let next = current + amount;
  if (next < 0) next = 0;
  input.value = next;
}

// ── HELPERS ───────────────────────────────────────────────────────
function setFeedback(msg, type) {
  const el = document.getElementById('modal-feedback');
  el.textContent = msg;
  el.className = 'modal-feedback ' + type;
}
function clearFeedback() { setFeedback('', ''); }
function esc(s) {
  return String(s || '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── KLAVYE KISA YOLLARI ───────────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeModal();
    closeMgmtModal('transfer-modal');
  }
  if (e.key === 'Enter' && activeSlot) {
    const modal = document.getElementById('modal');
    if (modal && modal.classList.contains('open')) saveSlot();
  }
});

// ── TRANSFER MODALI ────────────────────────────────────────────────
let trHierarchy = [];

function openTransferModal() {
  if (!activeSlot || !activeSlot.el.dataset.name) return;
  const itemName = activeSlot.el.dataset.name;
  const maxQty = parseInt(activeSlot.el.dataset.qty) || 0;
  
  document.getElementById('tr-item-name').textContent = itemName;
  document.getElementById('tr-max-qty').textContent = maxQty;
  document.getElementById('tr-qty').value = 1;
  document.getElementById('tr-qty').max = maxQty;

  const trFeedback = document.getElementById('tr-feedback');
  trFeedback.textContent = '';
  trFeedback.className = 'mgmt-feedback mt-2';

  // Modal'ı aç
  document.getElementById('transfer-modal').classList.add('open');

  // Hiyerarşiyi çek (daha önce çekilmediyse)
  if (trHierarchy.length === 0) {
    trFeedback.textContent = 'Depo verileri yükleniyor...';
    trFeedback.className = 'mgmt-feedback mt-2 text-muted';
    const apiBase = typeof API_BASE !== 'undefined' ? API_BASE : BASE_URL + '/api';
    fetch(apiBase + '/get_hierarchy.php')
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          trHierarchy = data.data;
          renderTrWarehouses();
          trFeedback.textContent = '';
        } else {
          trFeedback.textContent = 'Veriler yüklenemedi: ' + data.error;
          trFeedback.className = 'mgmt-feedback mt-2 err';
        }
      })
      .catch(err => {
        trFeedback.textContent = 'Bağlantı hatası.';
        trFeedback.className = 'mgmt-feedback mt-2 err';
      });
  } else {
    renderTrWarehouses();
  }
}

function renderTrWarehouses() {
  const sel = document.getElementById('tr-warehouse');
  sel.innerHTML = '<option value="">Seçiniz...</option>';
  trHierarchy.forEach(w => {
    const opt = document.createElement('option');
    opt.value = w.id;
    opt.textContent = w.name;
    sel.appendChild(opt);
  });
  document.getElementById('tr-cabinet').innerHTML = '<option value="">Önce depo seçiniz...</option>';
  document.getElementById('tr-cabinet').disabled = true;
  document.getElementById('tr-slot-group').style.display = 'none';
}

function trWarehouseChanged() {
  const wId = document.getElementById('tr-warehouse').value;
  const selCab = document.getElementById('tr-cabinet');
  
  if (!wId) {
    selCab.innerHTML = '<option value="">Önce depo seçiniz...</option>';
    selCab.disabled = true;
    document.getElementById('tr-slot-group').style.display = 'none';
    return;
  }
  
  const w = trHierarchy.find(x => x.id == wId);
  selCab.innerHTML = '<option value="">Seçiniz...</option>';
  if (w && w.cabinets) {
    w.cabinets.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.code + ' (' + (c.type === 'A' ? 'Gözlü' : 'Bütünleşik') + ')';
      selCab.appendChild(opt);
    });
  }
  selCab.disabled = false;
  document.getElementById('tr-slot-group').style.display = 'none';
}

function trCabinetChanged() {
  const wId = document.getElementById('tr-warehouse').value;
  const cId = document.getElementById('tr-cabinet').value;
  
  if (!cId) {
    document.getElementById('tr-slot-group').style.display = 'none';
    return;
  }
  
  const w = trHierarchy.find(x => x.id == wId);
  const c = w.cabinets.find(x => x.id == cId);
  
  const selRow = document.getElementById('tr-row');
  const selCol = document.getElementById('tr-col');
  const colWrap = document.getElementById('tr-col-wrap');
  
  selRow.innerHTML = '';
  selCol.innerHTML = '';
  
  for (let r = 1; r <= c.rows; r++) {
    selRow.innerHTML += `<option value="${r}">${r}. Satır</option>`;
  }
  
  if (c.type === 'A') {
    for (let cl = 1; cl <= c.cols; cl++) {
      selCol.innerHTML += `<option value="${cl}">${numToLetter(cl)} Kolonu</option>`;
    }
    colWrap.style.display = 'block';
  } else {
    selCol.innerHTML = '<option value="1">1</option>';
    colWrap.style.display = 'none';
  }
  
  document.getElementById('tr-slot-group').style.display = 'flex';
}

async function executeTransfer() {
  const f = document.getElementById('tr-feedback');
  f.textContent = 'İşleniyor...';
  f.className = 'mgmt-feedback mt-2 text-muted';
  document.getElementById('btn-exec-tr').disabled = true;

  const wId = document.getElementById('tr-warehouse').value;
  const cId = document.getElementById('tr-cabinet').value;
  const tRow = document.getElementById('tr-row').value;
  const tCol = document.getElementById('tr-col').value;
  const trQty = parseInt(document.getElementById('tr-qty').value);

  if (!wId || !cId || !tRow || !tCol || isNaN(trQty) || trQty <= 0) {
    f.textContent = 'Lütfen tüm alanları geçerli şekilde doldurun.';
    f.className = 'mgmt-feedback mt-2 err';
    document.getElementById('btn-exec-tr').disabled = false;
    return;
  }
  
  // Aynı slot mu kontrolü
  if (activeSlot.cabId == cId && activeSlot.row == tRow && activeSlot.col == tCol) {
    f.textContent = 'Aynı rafa transfer yapılamaz.';
    f.className = 'mgmt-feedback mt-2 err';
    document.getElementById('btn-exec-tr').disabled = false;
    return;
  }

  const payload = {
    action: 'transfer',
    source_cab: parseInt(activeSlot.cabId),
    source_row: parseInt(activeSlot.row),
    source_col: parseInt(activeSlot.col),
    target_cab: parseInt(cId),
    target_row: parseInt(tRow),
    target_col: parseInt(tCol),
    qty: trQty
  };

  try {
    const apiBase = typeof API_BASE !== 'undefined' ? API_BASE : BASE_URL + '/api';
    const res = await fetch(apiBase + '/slot_transfer.php', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken()
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      f.textContent = 'Transfer başarıyla tamamlandı!';
      f.className = 'mgmt-feedback mt-2 ok';
      setTimeout(() => {
        window.location.reload(); // Transfer sonrası sayfayı yenileyelim, DOM yönetimi kompleksleşmesin
      }, 800);
    } else {
      f.textContent = data.error || 'Bilinmeyen hata';
      f.className = 'mgmt-feedback mt-2 err';
      document.getElementById('btn-exec-tr').disabled = false;
    }
  } catch (e) {
    f.textContent = 'Bağlantı hatası.';
    f.className = 'mgmt-feedback mt-2 err';
    document.getElementById('btn-exec-tr').disabled = false;
  }
}

