<?php
/**
 * NOXARA - General Helper Functions
 */

// ── Format currency ───────────────────────────────────────
function formatRupiah(float|int|string $amount, bool $symbol = true): string
{
    $prefix = $symbol ? 'Rp' : '';
    return $prefix . number_format((float)$amount, 0, ',', '.');
}

function formatRupiahShort(float|int $amount): string
{
    if ($amount >= 1_000_000_000) return 'Rp' . number_format($amount / 1_000_000_000, 1) . 'M';
    if ($amount >= 1_000_000)     return 'Rp' . number_format($amount / 1_000_000, 1) . 'jt';
    if ($amount >= 1_000)         return 'Rp' . number_format($amount / 1_000, 0) . 'rb';
    return formatRupiah($amount);
}

// ── Format date/time ──────────────────────────────────────
function formatDate(string $datetime, string $format = DATE_FORMAT): string
{
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    try {
        $dt = new DateTime($datetime, new DateTimeZone(APP_TIMEZONE));
        return $dt->format($format);
    } catch (Exception) {
        return '-';
    }
}

function formatDatetime(string $datetime): string
{
    return formatDate($datetime, DATETIME_FORMAT);
}

function timeAgo(string $datetime): string
{
    try {
        $dt   = new DateTime($datetime, new DateTimeZone(APP_TIMEZONE));
        $now  = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
        $diff = $now->diff($dt);

        if ($diff->days === 0) {
            if ($diff->h === 0) {
                if ($diff->i === 0) return 'Baru saja';
                return $diff->i . ' menit lalu';
            }
            return $diff->h . ' jam lalu';
        }
        if ($diff->days === 1) return 'Kemarin';
        if ($diff->days < 7)  return $diff->days . ' hari lalu';
        if ($diff->days < 30) return (int)($diff->days / 7) . ' minggu lalu';
        if ($diff->days < 365)return (int)($diff->days / 30) . ' bulan lalu';
        return (int)($diff->days / 365) . ' tahun lalu';
    } catch (Exception) {
        return '-';
    }
}

// ── String helpers ────────────────────────────────────────
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function maskName(string $name): string
{
    if (mb_strlen($name) <= 2) return $name . '**';
    $visible = mb_substr($name, 0, 2);
    return $visible . str_repeat('*', max(2, mb_strlen($name) - 2));
}

function maskPhone(string $phone): string
{
    if (strlen($phone) <= 6) return '***';
    return substr($phone, 0, 3) . '****' . substr($phone, -3);
}

function maskEmail(string $email): string
{
    [$user, $domain] = explode('@', $email, 2) + ['', ''];
    if (strlen($user) <= 2) return '**@' . $domain;
    return substr($user, 0, 2) . str_repeat('*', strlen($user) - 2) . '@' . $domain;
}

function generateReferralCode(int $length = 8): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function generateUniqueCode(int $min = 100, int $max = 999): int
{
    return random_int($min, $max);
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $suffix;
}

// ── Number helpers ────────────────────────────────────────
function formatPercent(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals) . '%';
}

function countdownSeconds(string $targetDatetime): int
{
    $target = strtotime($targetDatetime);
    $diff   = $target - time();
    return max(0, $diff);
}

function formatCountdown(int $seconds): string
{
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// ── Upload helper ─────────────────────────────────────────
function uploadImage(array $file, string $folder, int $maxSize = MAX_UPLOAD_SIZE): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload gagal: ' . getUploadErrorMessage($file['error'])];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maks ' . formatBytes($maxSize)];
    }

    // Validate MIME type
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, atau WebP.'];
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTS, true)) {
        return ['success' => false, 'message' => 'Ekstensi file tidak diizinkan.'];
    }

    $uploadDir = UPLOADS_PATH . '/' . trim($folder, '/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename  = bin2hex(random_bytes(16)) . '.' . $ext;
    $filepath  = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Gagal menyimpan file.'];
    }

    return [
        'success'  => true,
        'filename' => $filename,
        'url'      => UPLOADS_URL . '/' . $folder . '/' . $filename,
        'path'     => $filepath,
    ];
}

function getUploadErrorMessage(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
        UPLOAD_ERR_PARTIAL => 'File tidak terupload sempurna',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
        default => 'Error tidak dikenal'
    };
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function deleteUploadedFile(string $filename, string $folder): void
{
    if (!empty($filename)) {
        $path = UPLOADS_PATH . '/' . trim($folder, '/') . '/' . $filename;
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}

// ── Pagination ────────────────────────────────────────────
function paginate(int $total, int $perPage, int $currentPage, string $url): array
{
    $totalPages  = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;

    // Build page links
    $separator = str_contains($url, '?') ? '&' : '?';
    $pages     = [];
    $range     = 2;
    $start     = max(1, $currentPage - $range);
    $end       = min($totalPages, $currentPage + $range);

    for ($i = $start; $i <= $end; $i++) {
        $pages[] = ['page' => $i, 'url' => $url . $separator . 'page=' . $i, 'active' => $i === $currentPage];
    }

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
        'prev_url'    => $currentPage > 1 ? $url . $separator . 'page=' . ($currentPage - 1) : null,
        'next_url'    => $currentPage < $totalPages ? $url . $separator . 'page=' . ($currentPage + 1) : null,
        'pages'       => $pages,
    ];
}

function getCurrentPage(): int
{
    return max(1, (int)($_GET['page'] ?? 1));
}

// ── Validation helpers ────────────────────────────────────
function validatePhone(string $phone): bool
{
    $phone = preg_replace('/\D/', '', $phone);
    return preg_match('/^(08|628)\d{8,13}$/', $phone) === 1;
}

function normalizePhone(string $phone): string
{
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '08')) {
        $phone = '628' . substr($phone, 2);
    }
    return $phone;
}

function validatePassword(string $password): array
{
    $errors = [];
    if (strlen($password) < 8)             $errors[] = 'Password minimal 8 karakter';
    if (!preg_match('/[A-Z]/', $password))  $errors[] = 'Password harus mengandung huruf kapital';
    if (!preg_match('/[0-9]/', $password))  $errors[] = 'Password harus mengandung angka';
    return $errors;
}

// ── Marquee data ──────────────────────────────────────────
function getMarqueeItems(): array
{
    $items = [];
    $setting = db()->fetchOne('SELECT * FROM marquee_settings LIMIT 1');
    if (!$setting || !$setting['is_enabled']) return [];

    if ($setting['show_deposits']) {
        $deposits = db()->fetchAll(
            "SELECT u.full_name, d.amount FROM deposits d
             JOIN users u ON d.user_id = u.id
             WHERE d.status = 'confirmed'
             ORDER BY d.confirmed_at DESC LIMIT 5"
        );
        foreach ($deposits as $d) {
            $items[] = '💰 ' . maskName($d['full_name']) . ' melakukan deposit ' . formatRupiah($d['amount']);
        }
    }

    if ($setting['show_purchases']) {
        $purchases = db()->fetchAll(
            "SELECT u.full_name, p.name FROM user_products up
             JOIN users u ON up.user_id = u.id
             JOIN products p ON up.product_id = p.id
             ORDER BY up.created_at DESC LIMIT 5"
        );
        foreach ($purchases as $p) {
            $items[] = '⛏️ ' . maskName($p['full_name']) . ' membeli paket ' . e($p['name']);
        }
    }

    if ($setting['show_vip_upgrades']) {
        $upgrades = db()->fetchAll(
            "SELECT u.full_name, u.vip_level FROM users u
             WHERE u.vip_level > 0 ORDER BY u.updated_at DESC LIMIT 5"
        );
        foreach ($upgrades as $v) {
            $items[] = '🏆 ' . maskName($v['full_name']) . ' naik ke VIP ' . $v['vip_level'];
        }
    }

    if (!empty($setting['custom_messages'])) {
        $customs = array_filter(array_map('trim', explode('|', $setting['custom_messages'])));
        foreach ($customs as $msg) {
            $items[] = e($msg);
        }
    }

    shuffle($items);
    return $items;
}

// ── Rate limit (login brute-force) ───────────────────────
function checkLoginRateLimit(string $ip): array
{
    $maxAttempts = (int)getSetting('login_max_attempts', LOGIN_MAX_ATTEMPTS);
    $lockMinutes = (int)getSetting('login_lock_minutes', LOGIN_LOCK_MINUTES);

    // Count recent failed attempts from this IP
    $count = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM user_login_logs
         WHERE ip_address = ? AND status = 'failed'
         AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        'si', $ip, $lockMinutes
    );

    $attempts = (int)($count['cnt'] ?? 0);
    if ($attempts >= $maxAttempts) {
        return ['locked' => true, 'remaining' => $maxAttempts - $attempts, 'lock_minutes' => $lockMinutes];
    }
    return ['locked' => false, 'remaining' => $maxAttempts - $attempts];
}

function logLoginAttempt(int|null $userId, string $username, string $ip, string $ua, string $status): void
{
    db()->execute(
        'INSERT INTO user_login_logs (user_id, username_attempt, ip_address, user_agent, status) VALUES (?,?,?,?,?)',
        'issss', $userId, $username, $ip, $ua, $status
    );
}

// ── Popup helper ─────────────────────────────────────────
function getPopup(string $eventKey): ?array
{
    $popup = db()->fetchOne(
        'SELECT * FROM popup_settings WHERE event_key = ? AND is_enabled = 1',
        's', $eventKey
    );
    return $popup ?: null;
}

// ── Avatar URL helper ─────────────────────────────────────
function avatarUrl(?string $avatar): string
{
    if (!empty($avatar)) {
        return UPLOADS_URL . '/avatars/' . e($avatar);
    }
    return ASSETS_URL . '/img/default-avatar.png';
}

// ── VIP Badge HTML ────────────────────────────────────────
function vipBadge(int $level): string
{
    $names  = ['Pemula','Bronze','Silver','Gold','Platinum','Diamond'];
    $colors = ['#888888','#CD7F32','#C0C0C0','#FFD700','#00D4FF','#7B2FFF'];
    $name   = $names[$level] ?? 'VIP' . $level;
    $color  = $colors[$level] ?? '#888888';
    return '<span class="vip-badge" style="color:' . $color . ';border-color:' . $color . '">VIP ' . $level . ' ' . $name . '</span>';
}

// ── Unread notification count ─────────────────────────────
function getUnreadNotifCount(int $userId): int
{
    $row = db()->fetchOne(
        'SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0',
        'i', $userId
    );
    return (int)($row['cnt'] ?? 0);
}

// ── Unread chat count ─────────────────────────────────────
function getUnreadChatCount(int $userId): int
{
    $row = db()->fetchOne(
        'SELECT unread_user FROM chat_rooms WHERE user_id = ?',
        'i', $userId
    );
    return (int)($row['unread_user'] ?? 0);
}

// ── Math captcha ──────────────────────────────────────────
function generateCaptcha(): array
{
    $a = random_int(1, 15);
    $b = random_int(1, 15);
    $ops = ['+', '-', '×'];
    $op  = $ops[array_rand($ops)];
    $answer = match($op) {
        '+' => $a + $b,
        '-' => abs($a - $b),
        '×' => $a * $b,
    };
    if ($op === '-') { [$a, $b] = [$a > $b ? $a : $b, abs($a - $b)]; $answer = $a - $b; }
    $_SESSION['captcha_answer'] = $answer;
    return ['question' => "$a $op $b = ?", 'answer' => $answer];
}

function verifyCaptcha(string|int $input): bool
{
    $expected = $_SESSION['captcha_answer'] ?? null;
    unset($_SESSION['captcha_answer']);
    if ($expected === null) return false;
    return (int)$input === (int)$expected;
}
