<?php
// $pageTitle ve $currentUser bu include edildiğinde zaten tanımlı olmalı
$title = ($pageTitle ?? 'Sayfa') . ' — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#ffffff">
  <title><?= h($title) ?></title>
  <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=IBM+Plex+Mono:wght@400;500;600&family=Figtree:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <script>const BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<header class="app-header">
  <div class="header-main">
    <div class="header-inner">
      <a href="<?= BASE_URL ?>/dashboard/" class="logo-link">
        <div class="logo-mark">D</div>
        <div class="logo-text">
          <span class="logo-name"><?= h(APP_NAME) ?></span>
          <span class="logo-ver">v<?= APP_VERSION ?></span>
        </div>
      </a>
      
      <?php if (!empty($currentUser)): ?>
      <div class="nav-links">
        <a href="<?= BASE_URL ?>/bina/">Binalar</a>
        <div class="nav-dropdown">
          <span>Raporlar ▾</span>
          <div class="nav-dropdown-content">
            <a href="<?= BASE_URL ?>/raporlar/doluluk/">Doluluk Özeti</a>
            <a href="<?= BASE_URL ?>/raporlar/lokasyon/">Lokasyon Raporu</a>
            <a href="<?= BASE_URL ?>/raporlar/hareketlilik/">Hareketlilik</a>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($currentUser)): ?>
      <div class="header-search-desktop">
        <div class="search-box">
          <div class="search-input-wrap">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="global-search-desktop" placeholder="Ürün, kod veya raf ara..." autocomplete="off">
            <div id="search-results-desktop" class="search-results"></div>
          </div>
          <button class="btn-qr-square" id="btn-desktop-scanner" title="QR/Barkod Oku">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h4v4H4zM16 4h4v4h-4zM4 16h4v4H4z"/><path d="M9 3v18M15 3v18M3 9h18M3 15h18"/></svg>
          </button>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($currentUser)): ?>
      <div class="header-right">
        <div class="user-menu-wrap">
          <div class="user-chip" id="user-chip-toggle">
            <div class="user-avatar"><?= strtoupper(substr($currentUser['username'], 0, 1)) ?></div>
          </div>
          <div class="user-dropdown">
            <div class="user-info-dropdown">
              <strong><?= h($currentUser['full_name'] ?: $currentUser['username']) ?></strong>
              <span>@<?= h($currentUser['username']) ?></span>
            </div>
            <hr>
            <a href="<?= BASE_URL ?>/logout.php" class="text-red">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
              Çıkış Yap
            </a>
          </div>
        </div>

        <?php if (isAdmin()): ?>
        <div class="admin-menu">
          <button class="settings-btn" title="Yönetim">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 01-2.83 0l-.06.06a1.65 1.65 0 00-1.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
          </button>
          <div class="admin-dropdown">
            <a href="<?= BASE_URL ?>/admin/users/">Kullanıcı Yönetimi</a>
            <a href="<?= BASE_URL ?>/admin/products/">Ürün Yönetimi</a>
            <a href="<?= BASE_URL ?>/raporlar/stok-hareketleri/">Stok Hareketleri</a>
            <a href="<?= BASE_URL ?>/admin/import-export/">İçe/Dışa Aktar</a>
            <a href="<?= BASE_URL ?>/api/backup.php" style="color:var(--accent); font-weight:600;">Veritabanı Yedeği</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($currentUser)): ?>
  <div class="header-search-mobile">
    <div class="header-inner">
      <div class="search-box">
        <button class="btn-qr-square" id="btn-global-scanner" title="QR/Barkod Oku">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h4v4H4zM16 4h4v4h-4zM4 16h4v4H4z"/><path d="M9 3v18M15 3v18M3 9h18M3 15h18"/></svg>
        </button>
        <div class="search-input-wrap">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="global-search" placeholder="Ürün, kod veya raf ara..." autocomplete="off">
          <div id="search-results" class="search-results"></div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</header>
<main class="app-main">
