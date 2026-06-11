# ✅ TODO — Depo Yönetim Sistemi

## 🚀 Faz 1 — Temel Altyapı (MVP)
- [x] Klasör & mimari yapısı tasarlandı
- [x] Veritabanı şeması yazıldı
- [x] Login sayfası (index.php)
- [x] Auth helper fonksiyonlar (config/auth.php)
- [x] Session guard (includes/auth_guard.php)
- [x] Depo seçim ekranı (depo/index.php)
- [x] Dolap detay ekranı (depolar/index.php)
- [x] Slot güncelleme API (api/slot_update.php)
- [x] Ana CSS (assets/css/style.css)
- [x] Frontend JS (assets/js/app.js)
- [x] Seed verisi (database/seed.sql)

## 🔧 Faz 2 — Kullanıcı Yönetimi
- [x] Profil sayfası (şifre değiştirme)
- [x] Çoklu kullanıcı desteği (admin / editor / viewer rolleri)
- [x] Kullanıcı yönetim paneli (admin)
- [x] Son işlem logu (kim ne zaman neyi değiştirdi)

## 🏭 Faz 3 — Depo Yönetimi
- [x] Yeni depo ekleme (admin paneli)
- [x] Depo düzenleme / silme
- [ ] Dolap konfigürasyonu düzenleme (satır/kolon sayısı)
- [ ] Yeni dolap tipi ekleme
- [ ] Depo bazlı erişim yetkilendirmesi (kullanıcı sadece belirli depolara erişsin)

## 📦 Faz 4 — Stok Özellikleri
- [x] Ürün arama (tüm depolarda tek sorguda)
- [x] Düşük stok uyarısı (min_qty eşiği)
- [x] Stok hareketi geçmişi (giriş / çıkış / transfer)
- [x] Slot transfer (A dolabı → B dolabı)
- [x] Toplu içe aktarma (CSV/Excel import)
- [x] Dışa aktarma (PDF rapor, Excel export)

## 📊 Faz 5 — Raporlama
- [x] Depo doluluk özet sayfası
- [x] Ürün bazlı lokasyon raporu
- [x] Hareketlilik raporu (son 30 gün)
- [x] Dashboard (ana ekran istatistikleri)

## 🛡 Faz 6 — Güvenlik & DevOps
- [x] CSRF token koruması
- [x] Rate limiting (login brute-force koruması)
- [x] .htaccess ile config/ ve includes/ dizinlerini koru
- [x] Ortam değişkenleri ile .env desteği
- [x] Veritabanı yedekleme scripti

## 🚀 Faz 7 — İleri Seviye Özellikler (Tamamlandı)
- [x] **Ürün Veritabanı (Master Data):** Ürünlerin (Adı, Min Stok, Açıklama, Kategori vb.) tanımlandığı merkezi bir katalog oluşturulması. Hızlı raf düzenleme için otomatik tamamlama.
- [x] **Barkod / QR Kod Entegrasyonu:** Her raf/slot için benzersiz QR kod üretilmesi ve kamera ile taratıldığında doğrudan o rafın düzenleme ekranının açılması.
- [x] **Gelişmiş Yetkilendirme (RBAC):** Sadece belirli binalarda yetkili olan (Bina Yöneticisi) veya sadece raporları görebilen detaylı kullanıcı izin matrisinin kurulması.

## 🔮 Faz 8 — Gelecek Vizyonu (Planlananlar)
- [ ] **Gelişmiş Loglama (Audit Trail):** Her silme, güncelleme ve ekleme işleminin hangi IP ve kullanıcı tarafından ne zaman yapıldığının detaylı izlenmesi.
- [ ] **Mobil Uygulama / PWA Desteği:** Sistemin offline çalışabilen bir PWA (Progressive Web App) veya Native Mobil Uygulamaya dönüştürülmesi.
- [ ] **E-Posta / SMS Bildirimleri:** Kritik stok seviyesinin altına düşen ürünler için depo yöneticilerine otomatik uyarı mesajları gönderilmesi.
- [ ] **Dış API Entegrasyonları:** Muhasebe (ERP) sistemleriyle (Örn: SAP, Logo, Mikro vb.) çift yönlü stok senkronizasyonu.

## 🐛 Bilinen Sorunlar
> Henüz yok — ilk versiyon temiz başlıyor.

---
_Son güncelleme: Mayıs 2026_
