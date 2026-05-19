<?php
/**
 * NOXARA - Authentication Functions
 */

function registerUser(array $data): array
{
    // Validate required fields
    $required = ['full_name','username','email','phone','password'];
    foreach ($required as $field) {
        if (empty(trim($data[$field] ?? ''))) {
            return ['success' => false, 'message' => 'Semua field wajib diisi.'];
        }
    }

    $fullName  = trim($data['full_name']);
    $username  = strtolower(trim($data['username']));
    $email     = strtolower(trim($data['email']));
    $phone     = normalizePhone(trim($data['phone']));
    $password  = $data['password'];
    $referCode = strtoupper(trim($data['referral_code'] ?? ''));

    // Validate username
    if (!preg_match('/^[a-z0-9_]{4,20}$/', $username)) {
        return ['success' => false, 'message' => 'Username hanya boleh huruf kecil, angka, underscore, 4-20 karakter.'];
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format email tidak valid.'];
    }

    // Validate phone
    if (!validatePhone($phone)) {
        return ['success' => false, 'message' => 'Format nomor HP tidak valid.'];
    }

    // Validate password
    $pwErrors = validatePassword($password);
    if (!empty($pwErrors)) {
        return ['success' => false, 'message' => implode(', ', $pwErrors)];
    }

    // Check duplicates
    $existing = db()->fetchOne(
        'SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1',
        'sss', $username, $email, $phone
    );
    if ($existing) {
        return ['success' => false, 'message' => 'Username, email, atau nomor HP sudah terdaftar.'];
    }

    // Handle referral code
    $referredBy = null;
    if (!empty($referCode)) {
        $referrer = db()->fetchOne('SELECT id FROM users WHERE referral_code = ?', 's', $referCode);
        if (!$referrer) {
            return ['success' => false, 'message' => 'Kode referral tidak valid.'];
        }
        $referredBy = $referrer['id'];
    }

    // Generate unique referral code
    do {
        $myRefCode = generateReferralCode();
        $exists    = db()->fetchOne('SELECT id FROM users WHERE referral_code = ?', 's', $myRefCode);
    } while ($exists);

    $hashedPw = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);

    db()->beginTransaction();
    try {
        // Insert user
        db()->execute(
            'INSERT INTO users (full_name, username, email, phone, password, referral_code, referred_by, vip_level)
             VALUES (?,?,?,?,?,?,?,0)',
            'ssssssi', $fullName, $username, $email, $phone, $hashedPw, $myRefCode, $referredBy
        );
        $userId = db()->lastInsertId();

        // Create wallet
        db()->execute('INSERT INTO user_wallets (user_id) VALUES (?)', 'i', $userId);

        // Give new member bonus
        $bonus = (float)getSetting('new_member_bonus', 0);
        if ($bonus > 0) {
            creditWallet($userId, WALLET_BONUS, $bonus, TRX_BONUS_REGISTER, 'register', $userId, 'Bonus registrasi member baru');
        }

        // Process referral chain
        if ($referredBy) {
            processReferralChain($userId, $referredBy);
        }

        // Create chat room
        db()->execute('INSERT INTO chat_rooms (user_id) VALUES (?)', 'i', $userId);

        db()->commit();

        sendNotification($userId, 'Selamat Bergabung!',
            'Selamat bergabung di NOXARA! Akun Anda berhasil dibuat.' . ($bonus > 0 ? ' Bonus Rp' . number_format($bonus,0,',','.') . ' sudah ditambahkan ke saldo bonus.' : ''),
            NOTIF_SUCCESS);

        return ['success' => true, 'user_id' => $userId, 'message' => 'Registrasi berhasil!'];

    } catch (Exception $e) {
        db()->rollback();
        error_log('Register error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Coba lagi.'];
    }
}

function loginUser2(string $usernameOrEmail, string $password, string $ip, string $ua): array
{
    // Rate limit check
    $rateLimit = checkLoginRateLimit($ip);
    if ($rateLimit['locked']) {
        return ['success' => false, 'message' => 'Terlalu banyak percobaan login. Coba lagi dalam ' . $rateLimit['lock_minutes'] . ' menit.'];
    }

    $identifier = strtolower(trim($usernameOrEmail));
    $user = db()->fetchOne(
        'SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1',
        'ss', $identifier, $identifier
    );

    if (!$user || !password_verify($password, $user['password'])) {
        logLoginAttempt(null, $identifier, $ip, $ua, 'failed');
        $remaining = max(0, $rateLimit['remaining'] - 1);
        $msg = 'Username/email atau password salah.';
        if ($remaining <= 2 && $remaining > 0) {
            $msg .= ' Sisa percobaan: ' . $remaining . ' kali.';
        }
        return ['success' => false, 'message' => $msg];
    }

    if ($user['is_blocked']) {
        logLoginAttempt($user['id'], $identifier, $ip, $ua, 'blocked');
        return ['success' => false, 'message' => 'Akun Anda telah diblokir. Hubungi CS.'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Akun tidak aktif. Hubungi CS.'];
    }

    // Rehash if needed
    if (password_needs_rehash($user['password'], HASH_ALGO, ['cost' => HASH_COST])) {
        $newHash = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
        db()->execute('UPDATE users SET password = ? WHERE id = ?', 'si', $newHash, $user['id']);
    }

    // Update last login
    db()->execute('UPDATE users SET updated_at = NOW() WHERE id = ?', 'i', $user['id']);
    logLoginAttempt($user['id'], $identifier, $ip, $ua, 'success');

    // Update user session in DB
    $token = bin2hex(random_bytes(32));
    db()->execute(
        'INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?,?,?,?)',
        'isss', $user['id'], $token, $ip, $ua
    );

    // Track mission: login
    trackMissionProgress($user['id'], ACTION_LOGIN);

    return ['success' => true, 'user' => $user];
}

function loginAdminUser(string $username, string $password, string $ip): array
{
    $admin = db()->fetchOne(
        'SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1',
        's', $username
    );

    if (!$admin || !password_verify($password, $admin['password'])) {
        return ['success' => false, 'message' => 'Username atau password salah.'];
    }

    db()->execute(
        'UPDATE admin_users SET last_login = NOW(), last_ip = ? WHERE id = ?',
        'si', $ip, $admin['id']
    );

    return ['success' => true, 'admin' => $admin];
}

function changePassword(int $userId, string $oldPassword, string $newPassword): array
{
    $user = db()->fetchOne('SELECT password FROM users WHERE id = ?', 'i', $userId);
    if (!$user) return ['success' => false, 'message' => 'User tidak ditemukan.'];

    if (!password_verify($oldPassword, $user['password'])) {
        return ['success' => false, 'message' => 'Password lama tidak sesuai.'];
    }

    $errors = validatePassword($newPassword);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(', ', $errors)];
    }

    $hash = password_hash($newPassword, HASH_ALGO, ['cost' => HASH_COST]);
    db()->execute('UPDATE users SET password = ? WHERE id = ?', 'si', $hash, $userId);
    return ['success' => true, 'message' => 'Password berhasil diubah.'];
}

function setTransactionPin(int $userId, string $pin, ?string $oldPin = null): array
{
    if (!preg_match('/^\d{6}$/', $pin)) {
        return ['success' => false, 'message' => 'PIN harus 6 digit angka.'];
    }

    $user = db()->fetchOne('SELECT pin FROM users WHERE id = ?', 'i', $userId);
    if (!$user) return ['success' => false, 'message' => 'User tidak ditemukan.'];

    // If existing PIN, verify old PIN first
    if (!empty($user['pin'])) {
        if (empty($oldPin)) return ['success' => false, 'message' => 'Masukkan PIN lama.'];
        if (!password_verify($oldPin, $user['pin'])) {
            return ['success' => false, 'message' => 'PIN lama tidak sesuai.'];
        }
    }

    $hash = password_hash($pin, HASH_ALGO, ['cost' => HASH_COST]);
    db()->execute('UPDATE users SET pin = ? WHERE id = ?', 'si', $hash, $userId);
    return ['success' => true, 'message' => 'PIN berhasil ' . (empty($user['pin']) ? 'dibuat' : 'diubah') . '.'];
}

function verifyPin(int $userId, string $pin): bool
{
    $user = db()->fetchOne('SELECT pin FROM users WHERE id = ?', 'i', $userId);
    if (!$user || empty($user['pin'])) return false;
    return password_verify($pin, $user['pin']);
}

function createPasswordReset(string $email): array
{
    $user = db()->fetchOne('SELECT id, full_name FROM users WHERE email = ?', 's', $email);
    if (!$user) {
        // Don't reveal if email exists
        return ['success' => true, 'message' => 'Jika email terdaftar, link reset akan dikirim.'];
    }

    $token     = bin2hex(random_bytes(32));
    $expiresAt = date(DB_DATETIME_FORMAT, strtotime('+1 hour'));

    db()->execute(
        'INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)',
        'sss', $email, $token, $expiresAt
    );

    $resetUrl = BASE_URL . '/auth/reset_password.php?token=' . $token;
    // In production: send email. Here we return the URL for admin info.
    sendNotification($user['id'], 'Reset Password',
        'Link reset password Anda: ' . $resetUrl . ' (berlaku 1 jam)', NOTIF_INFO);

    return ['success' => true, 'message' => 'Link reset password telah dikirim ke email Anda.', 'token' => $token];
}

function resetPassword(string $token, string $newPassword): array
{
    $reset = db()->fetchOne(
        'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL',
        's', $token
    );
    if (!$reset) {
        return ['success' => false, 'message' => 'Token tidak valid atau sudah expired.'];
    }

    $errors = validatePassword($newPassword);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(', ', $errors)];
    }

    $hash = password_hash($newPassword, HASH_ALGO, ['cost' => HASH_COST]);
    db()->beginTransaction();
    try {
        db()->execute('UPDATE users SET password = ? WHERE email = ?', 'ss', $hash, $reset['email']);
        db()->execute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', 'i', $reset['id']);
        db()->commit();
        return ['success' => true, 'message' => 'Password berhasil direset. Silakan login.'];
    } catch (Exception $e) {
        db()->rollback();
        return ['success' => false, 'message' => 'Terjadi kesalahan. Coba lagi.'];
    }
}

function updateProfile(int $userId, array $data): array
{
    $fullName = trim($data['full_name'] ?? '');
    $phone    = normalizePhone(trim($data['phone'] ?? ''));

    if (empty($fullName)) return ['success' => false, 'message' => 'Nama lengkap wajib diisi.'];
    if (!validatePhone($phone)) return ['success' => false, 'message' => 'Format HP tidak valid.'];

    // Check phone duplicate
    $existing = db()->fetchOne('SELECT id FROM users WHERE phone = ? AND id != ?', 'si', $phone, $userId);
    if ($existing) return ['success' => false, 'message' => 'Nomor HP sudah digunakan akun lain.'];

    $avatarFile = $_FILES['avatar'] ?? null;
    $avatarFilename = null;

    if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
        $upload = uploadImage($avatarFile, 'avatars');
        if (!$upload['success']) return ['success' => false, 'message' => $upload['message']];

        // Delete old avatar
        $old = db()->fetchOne('SELECT avatar FROM users WHERE id = ?', 'i', $userId);
        if ($old && !empty($old['avatar'])) deleteUploadedFile($old['avatar'], 'avatars');

        $avatarFilename = $upload['filename'];
        db()->execute(
            'UPDATE users SET full_name = ?, phone = ?, avatar = ?, updated_at = NOW() WHERE id = ?',
            'sssi', $fullName, $phone, $avatarFilename, $userId
        );
    } else {
        db()->execute(
            'UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?',
            'ssi', $fullName, $phone, $userId
        );
    }

    $_SESSION['full_name'] = $fullName;
    if ($avatarFilename) $_SESSION['avatar'] = $avatarFilename;

    return ['success' => true, 'message' => 'Profil berhasil diperbarui.'];
}
