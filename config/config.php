<?php
/**
 * NOXARA - Main Configuration
 * Edit sesuai environment hosting Anda
 */

// ── Database ──────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     3306);
define('DB_NAME',     'noxara_db');
define('DB_USER',     'noxara_user');
define('DB_PASS',     'your_strong_password_here');
define('DB_CHARSET',  'utf8mb4');

// ── Site ──────────────────────────────────────────────────
define('SITE_NAME',    'NOXARA');
define('SITE_TAGLINE', 'Invest Smarter, Grow Faster');
define('BASE_URL',     'https://noxara.id'); // Tanpa trailing slash
define('SITE_EMAIL',   'support@noxara.id');

// ── Paths ─────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('INCLUDES_PATH',ROOT_PATH . '/includes');
define('PAGES_PATH',   ROOT_PATH . '/pages');
define('ADMIN_PATH',   ROOT_PATH . '/admin');
define('ASSETS_PATH',  ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH',    ROOT_PATH . '/logs');

// ── Upload URL paths ──────────────────────────────────────
define('UPLOADS_URL',  BASE_URL . '/uploads');
define('ASSETS_URL',   BASE_URL . '/assets');

// ── Upload limits ─────────────────────────────────────────
define('MAX_UPLOAD_SIZE',    5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/webp','image/gif']);
define('ALLOWED_IMAGE_EXTS',  ['jpg','jpeg','png','webp','gif']);

// ── Session ───────────────────────────────────────────────
define('SESSION_NAME',    'NOXARA_SESSION');
define('SESSION_LIFETIME', 7200); // 2 jam
define('ADMIN_SESSION_NAME', 'NOXARA_ADMIN');

// ── Security ──────────────────────────────────────────────
define('CSRF_TOKEN_NAME',     'nxr_csrf_token');
define('LOGIN_MAX_ATTEMPTS',  5);
define('LOGIN_LOCK_MINUTES',  30);
define('HASH_ALGO',           PASSWORD_BCRYPT);
define('HASH_COST',           12);

// ── Cron secret key (set random string) ──────────────────
define('CRON_SECRET', 'change_this_to_random_string_12345');

// ── Timezone ─────────────────────────────────────────────
define('APP_TIMEZONE', 'Asia/Jakarta');

// ── Environment ──────────────────────────────────────────
define('APP_ENV',   'production'); // 'development' | 'production'
define('APP_DEBUG', false);

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_PATH . '/php_errors.log');
}

date_default_timezone_set(APP_TIMEZONE);
