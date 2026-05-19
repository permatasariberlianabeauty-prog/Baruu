<?php
/**
 * NOXARA - Bootstrap
 * Load semua config, database, session, constants, helpers
 */

// ── Load core config first ────────────────────────────────
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csrf.php';

// ── Load includes/helpers ─────────────────────────────────
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/wallet.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/vip.php';
require_once INCLUDES_PATH . '/referral.php';
require_once INCLUDES_PATH . '/mining.php';
require_once INCLUDES_PATH . '/notification.php';

// ── Init database connection ──────────────────────────────
Database::getInstance();

// ── Check maintenance mode ────────────────────────────────
function checkMaintenance(): void
{
    $maintenance = getSetting('maintenance_mode');
    if ($maintenance == '1') {
        // Allow admin to bypass
        if (defined('ADMIN_AREA') && ADMIN_AREA === true) return;
        if (isset($_COOKIE['admin_bypass'])) return;

        $message = getSetting('maintenance_message') ?: 'Website sedang dalam pemeliharaan.';
        include ROOT_PATH . '/errors/maintenance.php';
        exit;
    }
}

// ── Get a setting value ───────────────────────────────────
function getSetting(string $key, mixed $default = null): mixed
{
    static $settingsCache = [];

    if (!isset($settingsCache[$key])) {
        $row = db()->fetchOne('SELECT `value` FROM settings WHERE `key` = ?', 's', $key);
        $settingsCache[$key] = $row ? $row['value'] : null;
    }

    return $settingsCache[$key] ?? $default;
}

// ── Update a setting ──────────────────────────────────────
function updateSetting(string $key, string $value): void
{
    db()->execute(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
        'ss', $key, $value
    );
}

// ── Get client IP ─────────────────────────────────────────
function getClientIp(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// ── Get user agent ────────────────────────────────────────
function getUserAgent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

// ── JSON response helper ──────────────────────────────────
function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Redirect helper ───────────────────────────────────────
function redirect(string $path, bool $external = false): never
{
    $url = $external ? $path : BASE_URL . '/' . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

// ── Log admin action ─────────────────────────────────────
function logAdminAction(int $adminId, string $action, string $targetType = '', int $targetId = 0, string $desc = ''): void
{
    db()->execute(
        'INSERT INTO admin_logs (admin_id, action, target_type, target_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)',
        'ississ',
        $adminId, $action, $targetType, $targetId, $desc, getClientIp()
    );
}
