<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/auth.php';

startSession();

// Zaten giriş yapmışsa depo seçimine yönlendir
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard/');
    exit;
}

$error = '';
$rateLimitMinutes = 5;
$maxAttempts = 5;

// Block check
if (isset($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']) {
    $remaining = ceil(($_SESSION['lockout_time'] - time()) / 60);
    $error = "Çok fazla hatalı giriş yaptınız. Lütfen $remaining dakika sonra tekrar deneyin.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        $result = login($username, $password);
        if ($result['success']) {
            // Başarılı girişte hata sayacını sıfırla
            unset($_SESSION['login_attempts']);
            unset($_SESSION['lockout_time']);
            header('Location: ' . BASE_URL . '/dashboard/');
            exit;
        } else {
            $error = $result['error'];
            
            // Hata sayacını artır
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= $maxAttempts) {
                $_SESSION['lockout_time'] = time() + ($rateLimitMinutes * 60);
                $error = "Çok fazla hatalı giriş yaptınız. Hesabınız $rateLimitMinutes dakika süreyle kilitlendi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#ffffff">
  <title>Giriş — <?= h(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=IBM+Plex+Mono:wght@400;500;600&family=Figtree:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-body">

<div class="login-wrap">
  <!-- Logo / Başlık -->
  <div class="login-header">
    <div class="login-logo-mark">D</div>
    <h1 class="login-title"><?= h(APP_NAME) ?></h1>
    <p class="login-sub">Devam etmek için giriş yapın</p>
  </div>

  <!-- Form -->
  <div class="login-card">
    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= h($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <div class="form-group">
        <label for="username" class="form-label">Kullanıcı Adı</label>
        <input
          type="text"
          id="username"
          name="username"
          class="form-input"
          value="<?= h($_POST['username'] ?? '') ?>"
          placeholder="kullanıcı_adı"
          autocomplete="username"
          autofocus
          required
        >
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Şifre</label>
        <div class="input-wrap">
          <input
            type="password"
            id="password"
            name="password"
            class="form-input"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          >
          <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Şifreyi göster">
            <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full">
        Giriş Yap
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </form>
  </div>

  <p class="login-footer-note">
    <span class="mono"><?= h(APP_NAME) ?> v<?= APP_VERSION ?></span>
  </p>
</div>

<script>
function togglePassword() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
