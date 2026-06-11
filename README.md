# 📦 Depo Yönetim Sistemi

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

Modern, mobil uyumlu PHP tabanlı depo & raf stok takip uygulaması.

---

## 🗂 Klasör Yapısı

```
depo-yonetim/
├── index.php                  # Giriş ekranı (login)
├── logout.php                 # Oturum kapatma
├── README.md                  # Bu dosya
├── TODO.md                    # Görev listesi
│
├── config/
│   ├── database.php           # PDO bağlantısı
│   ├── auth.php               # Auth helper fonksiyonlar
│   └── constants.php          # Uygulama sabitleri (BASE_URL vs.)
│
├── includes/
│   ├── header.php             # HTML <head> + nav bar
│   ├── footer.php             # HTML footer + script tag'leri
│   └── auth_guard.php         # Oturum kontrolü (her korumalı sayfaya include edilir)
│
├── depo/
│   └── index.php              # Depo seçim ekranı (tüm depolar listelenir)
│
├── depolar/
│   └── index.php              # Seçilen deponun dolap & raf görünümü (?id=X)
│
├── api/
│   ├── slot_update.php        # POST → Slot içeriği güncelle/sil
│   ├── slot_get.php           # GET  → Tek slot verisini getir
│   └── warehouse_stats.php    # GET  → Depo doluluk istatistikleri
│
├── assets/
│   ├── css/
│   │   └── style.css          # Ana stil dosyası (mobil-first)
│   └── js/
│       └── app.js             # Frontend etkileşim (AJAX, modal, grid)
│
└── database/
    ├── schema.sql             # Tablo tanımları
    └── seed.sql               # Başlangıç verileri (admin user + örnek depolar)
```

---

## ⚙️ Kurulum

### 1. Gereksinimler
- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Apache veya Nginx (mod_rewrite aktif)

### 2. Veritabanı Kurulumu
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

### 3. Yapılandırma
`config/constants.php` dosyasını düzenle:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'depo_yonetim');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/depo-yonetim');
```

### 4. Varsayılan Giriş
```
Kullanıcı Adı: admin
Şifre:         admin123
```
> ⚠️ İlk girişten sonra şifreyi değiştirin!

---

## 🔄 Kullanıcı Akışı

```
index.php (Login)
    ↓
depo/index.php (Depo Seçimi)
    ↓
depolar/index.php?id=X (Dolap & Raf Yönetimi)
    ↓
[Modal] Slot düzenleme (AJAX → api/slot_update.php)
```

---

## 🗃 Veritabanı Şeması

| Tablo         | Açıklama                                |
|---------------|------------------------------------------|
| `users`       | Kullanıcı hesapları                      |
| `warehouses`  | Depo tanımları (id, name, location)      |
| `cabinets`    | Dolap tanımları (warehouse_id, type, rows, cols) |
| `slots`       | Raf/slot içerikleri (item_name, qty)     |

---

## 📱 Mobil Uyumluluk
- Tüm sayfalar mobil-first CSS ile tasarlandı
- A dolabı (22×6): yatay kaydırmalı grid
- B/C dolapları: tam genişlik raf kartları
- Touch-friendly modal ve butonlar (min 44px hedef alan)

---

## 🛡 Güvenlik
- Şifreler `password_hash()` (BCRYPT) ile saklanır
- Tüm SQL sorguları PDO prepared statements kullanır
- Session tabanlı kimlik doğrulama
- XSS koruması: `htmlspecialchars()` her çıkışta uygulanır

---

## 🤝 Katkıda Bulunma
Katkıda bulunmak isterseniz lütfen bir "Pull Request" açın veya karşılaştığınız sorunlar için "Issue" oluşturun.

1. Bu projeyi fork'layın
2. Yeni bir feature branch'i oluşturun (`git checkout -b feature/YeniOzellik`)
3. Değişikliklerinizi commit'leyin (`git commit -m 'Yeni özellik eklendi'`)
4. Branch'inizi push'layın (`git push origin feature/YeniOzellik`)
5. Pull Request oluşturun

---

## 📄 Lisans
Bu proje [MIT Lisansı](https://choosealicense.com/licenses/mit/) altında lisanslanmıştır. Daha fazla bilgi için proje kök dizinine bir `LICENSE` dosyası ekleyebilirsiniz.
