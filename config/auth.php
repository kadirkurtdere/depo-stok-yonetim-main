<?php
require_once __DIR__ . '/constants.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,   // HTTPS'de true yap
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token): bool {
    startSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['user_id']);
}

function requireLogin(string $redirectTo = null): void {
    if (!isLoggedIn()) {
        $redirectTo = $redirectTo ?? BASE_URL . '/';
        header('Location: ' . $redirectTo);
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? 0,
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role']      ?? 'viewer',
    ];
}

function login(string $username, string $password): array {
    require_once __DIR__ . '/database.php';
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, username, full_name, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Kullanıcı adı veya şifre hatalı.'];
    }

    startSession();
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];

    return ['success' => true];
}

function logout(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/');
    exit;
}

function isAdmin(): bool {
    startSession();
    return (($_SESSION['role'] ?? '') === 'admin');
}

function isEditor(): bool {
    startSession();
    $role = $_SESSION['role'] ?? '';
    return ($role === 'admin' || $role === 'editor');
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/');
        exit;
    }
}

/**
 * İşlemleri log tablosuna kaydeder.
 */
function logActivity(string $action, string $details = ''): void {
    if (!isLoggedIn()) return;
    
    require_once __DIR__ . '/database.php';
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        // Loglama hatası ana işlemi durdurmasın
    }
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

