<?php
/**
 * NOXARA - Session Management
 */

function initSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Custom session path fallback (shared hosting)
    $sessionPath = LOGS_PATH . '/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    // Secure cookie settings
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name(SESSION_NAME);
    session_start();

    // Regenerate session ID to prevent fixation
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

function initAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionPath = LOGS_PATH . '/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name(ADMIN_SESSION_NAME);
    session_start();

    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

// ── User Session Helpers ──────────────────────────────────

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_token']);
}

function getSessionUser(): ?array
{
    if (!isLoggedIn()) return null;
    return [
        'id'       => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'full_name'=> $_SESSION['full_name'] ?? '',
        'vip_level'=> (int)($_SESSION['vip_level'] ?? 0),
        'avatar'   => $_SESSION['avatar'] ?? null,
        'token'    => $_SESSION['user_token'],
    ];
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['vip_level'] = $user['vip_level'];
    $_SESSION['avatar']    = $user['avatar'];
    $_SESSION['user_token']= bin2hex(random_bytes(32));
    $_SESSION['login_time']= time();
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function requireLogin(string $redirect = ''): void
{
    if (!isLoggedIn()) {
        $url = BASE_URL . '/auth/login.php';
        if ($redirect) {
            $url .= '?redirect=' . urlencode($redirect);
        }
        header('Location: ' . $url);
        exit;
    }

    // Check if user is blocked/frozen
    $user = db()->fetchOne(
        'SELECT is_blocked, is_frozen FROM users WHERE id = ?',
        'i', $_SESSION['user_id']
    );

    if (!$user) {
        logoutUser();
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    if ($user['is_blocked']) {
        logoutUser();
        header('Location: ' . BASE_URL . '/errors/blocked.php');
        exit;
    }

    // Refresh session VIP level
    $fullUser = db()->fetchOne('SELECT vip_level, full_name, avatar FROM users WHERE id = ?', 'i', $_SESSION['user_id']);
    if ($fullUser) {
        $_SESSION['vip_level'] = $fullUser['vip_level'];
        $_SESSION['full_name'] = $fullUser['full_name'];
        $_SESSION['avatar']    = $fullUser['avatar'];
    }
}

// ── Admin Session Helpers ─────────────────────────────────

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_token']);
}

function getSessionAdmin(): ?array
{
    if (!isAdminLoggedIn()) return null;
    return [
        'id'        => (int)$_SESSION['admin_id'],
        'username'  => $_SESSION['admin_username'] ?? '',
        'full_name' => $_SESSION['admin_full_name'] ?? '',
        'role'      => $_SESSION['admin_role'] ?? 'cs',
    ];
}

function loginAdmin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']        = $admin['id'];
    $_SESSION['admin_username']  = $admin['username'];
    $_SESSION['admin_full_name'] = $admin['full_name'];
    $_SESSION['admin_role']      = $admin['role'];
    $_SESSION['admin_token']     = bin2hex(random_bytes(32));
    $_SESSION['admin_login_time']= time();
}

function logoutAdmin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function requireAdmin(string $role = ''): void
{
    if (!isAdminLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }

    if ($role && $_SESSION['admin_role'] !== ROLE_SUPERADMIN && $_SESSION['admin_role'] !== $role) {
        http_response_code(403);
        die('Akses ditolak.');
    }
}

function requireRole(array $roles): void
{
    requireAdmin();
    if (!in_array($_SESSION['admin_role'], $roles, true)) {
        http_response_code(403);
        die('Akses ditolak. Role tidak memadai.');
    }
}

// ── Flash Messages ────────────────────────────────────────

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function getFlash(string $type): array
{
    $messages = $_SESSION['flash'][$type] ?? [];
    unset($_SESSION['flash'][$type]);
    return $messages;
}

function hasFlash(string $type): bool
{
    return !empty($_SESSION['flash'][$type]);
}
