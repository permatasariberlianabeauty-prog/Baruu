<?php
/**
 * NOXARA - VIP System Functions
 */

function getUserVipInfo(int $userId): array
{
    $user     = db()->fetchOne('SELECT vip_level, total_deposit FROM users WHERE id = ?', 'i', $userId);
    $vipLevel = (int)($user['vip_level'] ?? 0);
    $vipData  = db()->fetchOne('SELECT * FROM vip_levels WHERE level = ?', 'i', $vipLevel);
    $nextVip  = db()->fetchOne('SELECT * FROM vip_levels WHERE level = ?', 'i', $vipLevel + 1);

    $totalDeposit  = (float)($user['total_deposit'] ?? 0);
    $progressPercent = 0;

    if ($nextVip) {
        $required = (float)$nextVip['min_deposit_required'];
        $progress = min($totalDeposit, $required);
        $progressPercent = $required > 0 ? round(($progress / $required) * 100) : 100;
    } else {
        $progressPercent = 100; // Max VIP
    }

    return [
        'level'            => $vipLevel,
        'info'             => $vipData,
        'next'             => $nextVip,
        'total_deposit'    => $totalDeposit,
        'progress_percent' => $progressPercent,
        'is_max'           => !$nextVip,
    ];
}

function checkAndUpgradeVip(int $userId): bool
{
    $user = db()->fetchOne('SELECT vip_level, total_deposit FROM users WHERE id = ?', 'i', $userId);
    $currentLevel  = (int)$user['vip_level'];
    $totalDeposit  = (float)$user['total_deposit'];

    // Find highest achievable level
    $newLevel = $currentLevel;
    for ($level = VIP_MAX; $level > $currentLevel; $level--) {
        $vip = db()->fetchOne('SELECT min_deposit_required FROM vip_levels WHERE level = ?', 'i', $level);
        if ($vip && $totalDeposit >= (float)$vip['min_deposit_required']) {
            $newLevel = $level;
            break;
        }
    }

    if ($newLevel > $currentLevel) {
        db()->execute('UPDATE users SET vip_level = ? WHERE id = ?', 'ii', $newLevel, $userId);
        $_SESSION['vip_level'] = $newLevel;

        $vipName = db()->fetchOne('SELECT name FROM vip_levels WHERE level = ?', 'i', $newLevel);
        sendNotification($userId, 'Selamat! Level VIP Naik! 🏆',
            'Selamat! Anda berhasil naik ke VIP ' . $newLevel . ' (' . ($vipName['name'] ?? '') . '). Nikmati keuntungan level baru!',
            NOTIF_VIP);

        // Set popup flag in session
        $_SESSION['popup_vip_upgrade'] = ['level' => $newLevel, 'name' => $vipName['name'] ?? ''];
        return true;
    }

    return false;
}

function getWithdrawVipRules(int $userId): array
{
    $user = db()->fetchOne('SELECT vip_level FROM users WHERE id = ?', 'i', $userId);
    $vip  = db()->fetchOne('SELECT * FROM vip_levels WHERE level = ?', 'i', $user['vip_level'] ?? 0);
    return $vip ?? [];
}

function getVipCode(int $vipLevel, int $userId): array
{
    $user = db()->fetchOne('SELECT vip_level FROM users WHERE id = ?', 'i', $userId);
    if ($user['vip_level'] < $vipLevel) {
        return ['accessible' => false, 'message' => 'Level VIP tidak mencukupi.'];
    }

    $code = db()->fetchOne('SELECT code FROM vip_codes WHERE vip_level = ? AND is_active = 1', 'i', $vipLevel);
    if (!$code) return ['accessible' => true, 'code' => '-'];

    return ['accessible' => true, 'code' => $code['code']];
}

function getAllVipLevels(): array
{
    return db()->fetchAll('SELECT * FROM vip_levels ORDER BY level ASC');
}

function updateVipLevel(int $level, array $data): array
{
    db()->execute(
        'UPDATE vip_levels SET name=?, min_deposit_required=?, min_deposit_per_tx=?, min_withdraw=?,
         withdraw_fee_percent=?, max_withdraw_per_day=?, daily_withdraw_limit=?, color=?, description=?
         WHERE level=?',
        'sddddiidsi',
        $data['name'], $data['min_deposit_required'], $data['min_deposit_per_tx'],
        $data['min_withdraw'], $data['withdraw_fee_percent'], $data['max_withdraw_per_day'],
        $data['daily_withdraw_limit'], $data['color'], $data['description'], $level
    );
    return ['success' => true, 'message' => 'VIP level berhasil diperbarui.'];
}
