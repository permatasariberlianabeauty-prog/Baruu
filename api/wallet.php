<?php
/**
 * NOXARA API - Wallet Endpoint
 * GET:  action=balance|check_voucher|set_theme
 * POST: action=watch_ad|claim_daily|claim_mission
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(false, 'Sesi tidak valid.', [], 401);
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

// Verify user not blocked/frozen
$user = db()->fetchOne('SELECT is_blocked, is_frozen FROM users WHERE id = ?', 'i', $userId);
if (!$user || $user['is_blocked']) jsonResponse(false, 'Akun diblokir.', [], 403);

// ── GET Actions ───────────────────────────────────────────
if ($method === 'GET') {

    // Balance summary
    if ($action === 'balance') {
        $wallet = getUserWallet($userId);
        jsonResponse(true, '', [
            'main'     => (float)$wallet['main_balance'],
            'profit'   => (float)$wallet['profit_balance'],
            'bonus'    => (float)$wallet['bonus_balance'],
            'referral' => (float)$wallet['referral_balance'],
            'main_fmt'     => formatRupiah($wallet['main_balance']),
            'profit_fmt'   => formatRupiah($wallet['profit_balance']),
            'bonus_fmt'    => formatRupiah($wallet['bonus_balance']),
            'referral_fmt' => formatRupiah($wallet['referral_balance']),
        ]);
    }

    // Check voucher code
    if ($action === 'check_voucher') {
        $code   = strtoupper(trim($_GET['code'] ?? ''));
        $type   = trim($_GET['type'] ?? 'deposit');
        $amount = (float)($_GET['amount'] ?? 0);

        if (!$code) jsonResponse(false, 'Kode voucher tidak boleh kosong.');

        $result = validateVoucher($code, $userId, $type, $amount);
        if ($result['valid']) {
            jsonResponse(true, $result['message'], [
                'voucher_id' => $result['voucher']['id'],
                'discount'   => $result['discount'],
                'discount_fmt' => formatRupiah($result['discount']),
                'code'       => $result['voucher']['code'],
            ]);
        } else {
            jsonResponse(false, $result['message'], ['need_vip' => $result['need_vip'] ?? false]);
        }
    }

    // Set theme (dark/light)
    if ($action === 'set_theme') {
        $theme = $_GET['theme'] === 'light' ? 'light' : 'dark';
        db()->execute('UPDATE users SET theme = ? WHERE id = ?', 'si', $theme, $userId);
        $_SESSION['user_theme'] = $theme;
        jsonResponse(true, 'Tema diperbarui.', ['theme' => $theme]);
    }

    jsonResponse(false, 'Action tidak dikenal.');
}

// ── POST Actions ──────────────────────────────────────────
if ($method === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        jsonResponse(false, 'Token tidak valid.', [], 419);
    }

    if ($user['is_frozen']) jsonResponse(false, 'Saldo Anda sedang dibekukan.', [], 403);

    // Watch ad reward
    if ($action === 'watch_ad') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        if (!$adId) jsonResponse(false, 'Ad ID tidak valid.');

        $result = watchAd($userId, $adId);
        jsonResponse($result['success'], $result['message'], $result['success'] ? [
            'reward'     => $result['reward'],
            'reward_fmt' => formatRupiah($result['reward']),
            'wallet'     => $result['wallet'],
        ] : []);
    }

    // Claim daily reward
    if ($action === 'claim_daily') {
        $result = claimDailyReward($userId);
        jsonResponse($result['success'], $result['message'], $result['success'] ? [
            'reward_name'  => $result['reward']['name'],
            'reward_value' => $result['reward']['value'],
            'reward_fmt'   => formatRupiah($result['reward']['value']),
            'is_jackpot'   => (bool)$result['reward']['is_jackpot'],
        ] : []);
    }

    // Claim mission reward
    if ($action === 'claim_mission') {
        $missionId = (int)($_POST['mission_id'] ?? 0);
        $periodKey = $_POST['period_key'] ?? null;
        if (!$missionId) jsonResponse(false, 'Mission ID tidak valid.');

        $result = claimMissionReward($userId, $missionId, $periodKey ?: null);
        jsonResponse($result['success'], $result['message']);
    }

    // Buy product (AJAX)
    if ($action === 'buy_product') {
        $productId  = (int)($_POST['product_id'] ?? 0);
        $walletType = $_POST['wallet_type'] ?? WALLET_MAIN;
        if (!$productId) jsonResponse(false, 'Product ID tidak valid.');

        $result = purchaseProduct($userId, $productId, $walletType);
        jsonResponse($result['success'], $result['message'], $result['success'] ? ['user_product_id' => $result['user_product_id']] : []);
    }

    jsonResponse(false, 'Action tidak dikenal.');
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
