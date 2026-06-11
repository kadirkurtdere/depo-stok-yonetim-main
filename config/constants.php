<?php
require_once __DIR__ . '/env.php';

// ── Veritabanı ──────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME') ?: 'depo_yonetim');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ── Uygulama ────────────────────────────────────────────────────
define('APP_NAME',   getenv('APP_NAME') ?: 'Depo Yönetim Sistemi');
define('APP_VERSION',getenv('APP_VERSION') ?: '2.0.0');
define('BASE_URL',   getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/depo-yonetim');

// ── Session ─────────────────────────────────────────────────────
define('SESSION_NAME',    getenv('SESSION_NAME') ?: 'depo_session');
define('SESSION_LIFETIME',getenv('SESSION_LIFETIME') ?: 28800); // 8 saat

// ── Güvenlik ────────────────────────────────────────────────────
define('BCRYPT_COST', 12);
