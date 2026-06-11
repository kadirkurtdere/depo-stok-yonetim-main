-- ─────────────────────────────────────────────────────────────────
-- Depo Yönetim Sistemi — Veritabanı Şeması
-- ─────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS depo_yonetim
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE depo_yonetim;

-- ── KULLANICILAR ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)     NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  full_name     VARCHAR(100)    NOT NULL DEFAULT '',
  role          ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── KULLANICI DEPO YETKİLERİ (RBAC) ──────────────────────────────
CREATE TABLE IF NOT EXISTS user_warehouses (
  user_id       INT UNSIGNED    NOT NULL,
  warehouse_id  INT UNSIGNED    NOT NULL,
  permission    ENUM('view', 'edit') NOT NULL DEFAULT 'view',
  PRIMARY KEY (user_id, warehouse_id),
  CONSTRAINT fk_uw_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uw_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── DEPOLAR ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS warehouses (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100)  NOT NULL,
  location    VARCHAR(200)  NOT NULL DEFAULT '',
  description TEXT,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ÜRÜNLER (MASTER DATA) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(200)  NOT NULL,
  category    VARCHAR(100)  DEFAULT NULL,
  description TEXT          DEFAULT NULL,
  min_qty     INT           NOT NULL DEFAULT 0,
  barcode     VARCHAR(100)  DEFAULT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── DOLAPLAR ────────────────────────────────────────────────────
-- type: A = çok kolonlu (22×6), B = tek kolon 6 raf, C = tek kolon 3 raf
CREATE TABLE IF NOT EXISTS cabinets (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  warehouse_id INT UNSIGNED  NOT NULL,
  code         VARCHAR(20)   NOT NULL,           -- Örn: "A", "B1", "C2"
  label        VARCHAR(100)  NOT NULL DEFAULT '', -- Örn: "A Dolabı"
  type         ENUM('A','B','C') NOT NULL,
  `rows`       TINYINT       NOT NULL DEFAULT 6,
  `cols`       TINYINT       NOT NULL DEFAULT 1,
  sort_order   SMALLINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_warehouse (warehouse_id),
  CONSTRAINT fk_cab_warehouse FOREIGN KEY (warehouse_id)
    REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SLOTLAR / RAFLAR ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS slots (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  cabinet_id  INT UNSIGNED  NOT NULL,
  product_id  INT UNSIGNED  DEFAULT NULL,
  row_num     SMALLINT      NOT NULL,
  col_num     SMALLINT      NOT NULL,
  item_name   VARCHAR(200)  DEFAULT NULL,
  item_qty    INT           NOT NULL DEFAULT 0,
  min_qty     INT           NOT NULL DEFAULT 0,
  item_note   TEXT          DEFAULT NULL,
  updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by  INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slot (cabinet_id, row_num, col_num),
  KEY idx_cabinet (cabinet_id),
  CONSTRAINT fk_slot_cab FOREIGN KEY (cabinet_id)
    REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_slots_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_slot_user FOREIGN KEY (updated_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STOK HAREKETLERİ ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stock_movements (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  slot_id     INT UNSIGNED  DEFAULT NULL,
  user_id     INT UNSIGNED  NOT NULL,
  type        ENUM('IN','OUT','TRANSFER','SET') NOT NULL,
  qty_change  INT           NOT NULL,
  prev_qty    INT           NOT NULL,
  new_qty     INT           NOT NULL,
  notes       TEXT          DEFAULT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_slot (slot_id),
  KEY idx_user (user_id),
  CONSTRAINT fk_sm_slot FOREIGN KEY (slot_id)
    REFERENCES slots(id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
