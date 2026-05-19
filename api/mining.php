<?php
/**
 * NOXARA API - Mining Endpoint
 * POST: action=mine&package_id=X
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(false, 'Sesi tidak valid. Silakan login kembali.', [], 401);
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Verify user is not blocked
$user = db()->fetchOne('SELECT is_blocked, is_frozen FROM users WHERE id = ?', 'i', $userId);
if (!$user || $user['is_blocked']) {
    jsonResponse(false, 'Akun Anda diblokir.', [], 403);
}
if ($user['is_frozen']) {
    jsonResponse(false, 'Saldo Anda sedang dibekukan.', [], 403);
}

if ($method === 'POST') {
    // Validate CSRF (XHR uses header)
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        jsonResponse(false, 'Token tidak valid.', [], 419);
    }

    $action = trim($_POST['action'] ?? '');

    // ── Mine a package ────────────────────────────────────
    if ($action === 'mine') {
        $packageId = (int)($_POST['package_id'] ?? 0);
        if (!$packageId) {
            jsonResponse(false, 'Package ID tidak valid.');
        }
        $result = doMining($userId, $packageId);
        jsonResponse($result['success'], $result['message'], $result['success'] ? [
            'profit'       => $result['profit'],
            'profit_fmt'   => formatRupiah($result['profit']),
            'credited_at'  => $result['credited_at'],
            'countdown'    => MINING_CREDIT_DELAY * 3600,
        ] : []);
    }

    // ── Credit pending profits (called by frontend after delay) ──
    if ($action === 'credit_pending') {
        $miningLogId = (int)($_POST['mining_log_id'] ?? 0);
        if (!$miningLogId) jsonResponse(false, 'Mining log ID tidak valid.');

        // Verify ownership
        $log = db()->fetchOne('SELECT ml.* FROM mining_logs ml JOIN user_products up ON ml.user_product_id=up.id WHERE ml.id=? AND up.user_id=?', 'ii', $miningLogId, $userId);
        if (!$log) jsonResponse(false, 'Log tidak ditemukan.');

        $result = creditMiningProfit($miningLogId);
        jsonResponse($result['success'], $result['message']);
    }

    jsonResponse(false, 'Action tidak dikenal.');
}

if ($method === 'GET') {
    $action = trim($_GET['action'] ?? '');

    // ── Get active packages + countdown status ────────────
    if ($action === 'packages') {
        $packages = getUserActivePackages($userId);
        $data = array_map(fn($p) => [
            'id'           => $p['id'],
            'product_name' => $p['product_name'],
            'profit_per_day' => (float)$p['profit_per_day'],
            'profit_fmt'   => formatRupiah($p['profit_per_day']),
            'days_remaining' => (int)$p['days_remaining'],
            'can_mine'     => (bool)$p['can_mine'],
            'last_mined_at'=> $p['last_mined_at'],
            'mine_count'   => (int)$p['mine_count'],
            'total_earned' => (float)$p['total_earned'],
        ], $packages);
        jsonResponse(true, '', ['packages' => $data]);
    }

    // ── Get mining countdown for specific package ────────
    if ($action === 'countdown') {
        $packageId = (int)($_GET['package_id'] ?? 0);
        if (!$packageId) jsonResponse(false, 'Package ID tidak valid.');
        $cd = getMiningCountdown($packageId);
        jsonResponse(true, '', $cd);
    }

    // ── Get today's mining stats ─────────────────────────
    if ($action === 'today_stats') {
        $todayProfit = db()->fetchOne(
            "SELECT COALESCE(SUM(profit_amount),0) as total, COUNT(*) as cnt FROM mining_logs WHERE user_id=? AND DATE(mined_at)=CURDATE()",
            'i', $userId
        );
        $pendingProfit = db()->fetchOne(
            "SELECT COALESCE(SUM(profit_amount),0) as total FROM mining_logs WHERE user_id=? AND status='pending'",
            'i', $userId
        );
        jsonResponse(true, '', [
            'today_profit'   => (float)$todayProfit['total'],
            'today_count'    => (int)$todayProfit['cnt'],
            'pending_profit' => (float)$pendingProfit['total'],
        ]);
    }

    jsonResponse(false, 'Action tidak dikenal.');
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
