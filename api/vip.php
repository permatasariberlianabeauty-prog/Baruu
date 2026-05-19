<?php
/**
 * NOXARA API - VIP Endpoint
 * GET: action=info|code|levels
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
$action = trim($_GET['action'] ?? '');

// ── VIP Info ──────────────────────────────────────────────
if ($action === 'info') {
    $info = getUserVipInfo($userId);
    jsonResponse(true, '', [
        'level'            => $info['level'],
        'name'             => $info['info']['name'] ?? '',
        'color'            => $info['info']['color'] ?? '#888888',
        'total_deposit'    => (float)$info['total_deposit'],
        'progress_percent' => $info['progress_percent'],
        'is_max'           => $info['is_max'],
        'next_level'       => $info['next'] ? [
            'level'                => (int)$info['next']['level'],
            'name'                 => $info['next']['name'],
            'min_deposit_required' => (float)$info['next']['min_deposit_required'],
            'remaining'            => max(0, (float)$info['next']['min_deposit_required'] - (float)$info['total_deposit']),
        ] : null,
        'rules' => $info['info'] ? [
            'min_withdraw'        => (float)$info['info']['min_withdraw'],
            'withdraw_fee_percent'=> (float)$info['info']['withdraw_fee_percent'],
            'max_withdraw_per_day'=> (int)$info['info']['max_withdraw_per_day'],
            'daily_withdraw_limit'=> (float)$info['info']['daily_withdraw_limit'],
        ] : null,
    ]);
}

// ── VIP Code ──────────────────────────────────────────────
if ($action === 'code') {
    $level = (int)($_GET['level'] ?? 0);
    $result = getVipCode($level, $userId);
    if (!$result['accessible']) {
        jsonResponse(false, $result['message'], ['need_level' => $level]);
    }
    jsonResponse(true, '', ['code' => $result['code'], 'level' => $level]);
}

// ── All VIP Levels ────────────────────────────────────────
if ($action === 'levels') {
    $levels = getAllVipLevels();
    jsonResponse(true, '', ['levels' => $levels]);
}

// ── Check VIP upgrade ─────────────────────────────────────
if ($action === 'check_upgrade') {
    $upgraded = checkAndUpgradeVip($userId);
    $newInfo   = getUserVipInfo($userId);
    jsonResponse(true, '', [
        'upgraded'  => $upgraded,
        'new_level' => $newInfo['level'],
    ]);
}

jsonResponse(false, 'Action tidak dikenal.');
