USE depo_yonetim;

-- ── ADMIN KULLANICI ─────────────────────────────────────────────
-- Şifre: admin123
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$12$eyb0pwOh1wBJoiUt.ssVBuc51vhHJe9yJOIx3S1wYpbhZHlsZDkJW', 'Sistem Yöneticisi', 'admin'),
('depo1', '$2y$12$eyb0pwOh1wBJoiUt.ssVBuc51vhHJe9yJOIx3S1wYpbhZHlsZDkJW', 'Depo Sorumlusu 1',  'editor')
ON DUPLICATE KEY UPDATE id=id;

-- ── DEPOLAR ─────────────────────────────────────────────────────
INSERT INTO warehouses (id, name, location, description) VALUES
(1, 'Ana Depo',       'Zemin Kat — Blok A', 'Genel stok ve yedek parça deposu'),
(2, 'Teknik Depo',    '1. Kat — Blok B',    'Elektronik ve alet deposu'),
(3, 'Arşiv Deposu',   'Bodrum Kat',          'Evrak ve arşiv malzemeleri')
ON DUPLICATE KEY UPDATE id=id;

-- ── DOLAPLAR — Ana Depo (id=1) ──────────────────────────────────
INSERT INTO cabinets (warehouse_id, code, label, type, `rows`, `cols`, sort_order) VALUES
-- A tipi: 22 satır × 6 kolon
(1, 'A',  'A Dolabı',  'A', 22, 6, 1),
-- B tipi: 6 raf × 1 kolon (3 adet)
(1, 'B1', 'B1 Dolabı', 'B', 6,  1, 2),
(1, 'B2', 'B2 Dolabı', 'B', 6,  1, 3),
(1, 'B3', 'B3 Dolabı', 'B', 6,  1, 4),
-- C tipi: 3 raf × 1 kolon (2 adet)
(1, 'C1', 'C1 Dolabı', 'C', 3,  1, 5),
(1, 'C2', 'C2 Dolabı', 'C', 3,  1, 6)
ON DUPLICATE KEY UPDATE id=id;

-- ── DOLAPLAR — Teknik Depo (id=2) ───────────────────────────────
INSERT INTO cabinets (warehouse_id, code, label, type, `rows`, `cols`, sort_order) VALUES
(2, 'A',  'A Dolabı',  'A', 22, 6, 1),
(2, 'B1', 'B1 Dolabı', 'B', 6,  1, 2),
(2, 'B2', 'B2 Dolabı', 'B', 6,  1, 3),
(2, 'C1', 'C1 Dolabı', 'C', 3,  1, 4)
ON DUPLICATE KEY UPDATE id=id;

-- ── ÖRNEK SLOT VERİLERİ — Ana Depo A Dolabı (cabinet_id=1) ─────
-- Gerçek cabinet id'leri seed sırasına göre değişebilir.
-- Bu script çalıştırıldıktan sonra id'leri kontrol edin.
-- Örnek: cabinet_id=1 (Ana Depo A Dolabı)
INSERT INTO slots (cabinet_id, row_num, col_num, item_name, item_qty, item_note) VALUES
(1, 1, 1, 'M3×10 Vida',     500, 'Paslanmaz çelik'),
(1, 1, 2, 'M3×20 Vida',     320, NULL),
(1, 1, 3, 'M4×15 Vida',     200, NULL),
(1, 2, 1, 'Kablo Klips 5mm', 150, 'Beyaz'),
(1, 2, 2, 'Kablo Klips 8mm',  80, 'Siyah'),
(1, 3, 1, 'Sigorta 5A',      60, 'Cam tüp'),
(1, 3, 2, 'Sigorta 10A',     40, 'Cam tüp'),
-- B1 dolabı (cabinet_id=2)
(2, 1, 1, 'Multimetre',       3, 'Fluke 117'),
(2, 2, 1, 'Tornavida Seti',   5, '12 parça'),
(2, 3, 1, 'Pense Seti',       4, NULL)
ON DUPLICATE KEY UPDATE item_name=VALUES(item_name);
