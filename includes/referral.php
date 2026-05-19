<?php
/**
 * NOXARA - Referral & Commission Functions
 */

function processReferralChain(int $newUserId, int $directReferrerId): void
{
    // Build referral chain up to 3 levels
    $chain   = [];
    $current = $directReferrerId;

    for ($level = 1; $level <= REFERRAL_MAX_LEVEL; $level++) {
        if (!$current) break;
        $chain[] = ['user_id' => $current, 'level' => $level];

        // Find this user's own referrer
        $parent = db()->fetchOne('SELECT referred_by FROM users WHERE id = ?', 'i', $current);
        $current = $parent ? (int)$parent['referred_by'] : null;
    }

    // Insert referral records
    foreach ($chain as $ref) {
        // Check not already recorded
        $exists = db()->fetchOne(
            'SELECT id FROM referrals WHERE referrer_id = ? AND referred_id = ?',
            'ii', $ref['user_id'], $newUserId
        );
        if (!$exists) {
            db()->execute(
                'INSERT INTO referrals (referrer_id, referred_id, level) VALUES (?,?,?)',
                'iii', $ref['user_id'], $newUserId, $ref['level']
            );
        }
    }
}

function processReferralCommission(int $fromUserId, float $amount, string $type, int $referenceId): void
{
    // Find referral chain
    $referrals = db()->fetchAll(
        'SELECT r.referrer_id, r.level FROM referrals r WHERE r.referred_id = ? ORDER BY r.level ASC',
        'i', $fromUserId
    );

    foreach ($referrals as $ref) {
        $setting = db()->fetchOne(
            'SELECT percent FROM commission_settings WHERE type = ? AND level = ?',
            'si', $type, $ref['level']
        );
        if (!$setting || $setting['percent'] <= 0) continue;

        $percent    = (float)$setting['percent'];
        $commission = round($amount * $percent / 100, 2);
        if ($commission <= 0) continue;

        // Credit referral wallet
        creditWallet(
            $ref['referrer_id'], WALLET_REFERRAL, $commission,
            TRX_REFERRAL_COMMISSION, $type, $referenceId,
            'Komisi ' . ucfirst($type) . ' L' . $ref['level'] . ' dari ' . maskName(getUserName($fromUserId))
        );

        // Record commission
        db()->execute(
            'INSERT INTO commissions (user_id, from_user_id, type, level, base_amount, percent, amount, reference_id, status)
             VALUES (?,?,?,?,?,?,?,?,?)',
            'iiisddids',
            $ref['referrer_id'], $fromUserId, $type, $ref['level'],
            $amount, $percent, $commission, $referenceId, 'credited'
        );

        sendNotification($ref['referrer_id'], 'Komisi Referral Masuk! 💎',
            'Komisi level ' . $ref['level'] . ' sebesar ' . formatRupiah($commission) . ' masuk ke saldo referral.',
            NOTIF_SUCCESS);
    }
}

function getUserName(int $userId): string
{
    $user = db()->fetchOne('SELECT full_name FROM users WHERE id = ?', 'i', $userId);
    return $user['full_name'] ?? 'Pengguna';
}

function getUserDownlines(int $userId, int $level = 1): array
{
    $downlines = db()->fetchAll(
        "SELECT u.id, u.full_name, u.username, u.vip_level, u.created_at,
                CASE WHEN EXISTS(SELECT 1 FROM deposits d WHERE d.user_id = u.id AND d.status = 'confirmed') THEN 'aktif' ELSE 'nonaktif' END AS deposit_status,
                (SELECT SUM(amount) FROM deposits WHERE user_id = u.id AND status = 'confirmed') as total_deposit
         FROM referrals r
         JOIN users u ON r.referred_id = u.id
         WHERE r.referrer_id = ? AND r.level = ?
         ORDER BY u.created_at DESC",
        'ii', $userId, $level
    );
    return $downlines;
}

function getUserReferralStats(int $userId): array
{
    $l1 = db()->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(c.amount),0) as total_commission
        FROM referrals r LEFT JOIN commissions c ON c.user_id = ? AND c.from_user_id = r.referred_id AND c.level = 1
        WHERE r.referrer_id = ? AND r.level = 1", 'ii', $userId, $userId);
    $l2 = db()->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(c.amount),0) as total_commission
        FROM referrals r LEFT JOIN commissions c ON c.user_id = ? AND c.from_user_id = r.referred_id AND c.level = 2
        WHERE r.referrer_id = ? AND r.level = 2", 'ii', $userId, $userId);
    $l3 = db()->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(c.amount),0) as total_commission
        FROM referrals r LEFT JOIN commissions c ON c.user_id = ? AND c.from_user_id = r.referred_id AND c.level = 3
        WHERE r.referrer_id = ? AND r.level = 3", 'ii', $userId, $userId);

    $totalComm = db()->fetchOne('SELECT COALESCE(SUM(amount),0) as total FROM commissions WHERE user_id = ?', 'i', $userId);

    return [
        'level1' => ['count' => (int)$l1['cnt'], 'commission' => (float)$l1['total_commission']],
        'level2' => ['count' => (int)$l2['cnt'], 'commission' => (float)$l2['total_commission']],
        'level3' => ['count' => (int)$l3['cnt'], 'commission' => (float)$l3['total_commission']],
        'total_commission' => (float)$totalComm['total'],
        'referral_link'    => BASE_URL . '/auth/register.php?ref=' . getUserReferralCode($userId),
    ];
}

function getUserReferralCode(int $userId): string
{
    $user = db()->fetchOne('SELECT referral_code FROM users WHERE id = ?', 'i', $userId);
    return $user['referral_code'] ?? '';
}

function getReferralTree(int $userId, int $maxDepth = 3): array
{
    return buildReferralTree($userId, 1, $maxDepth);
}

function buildReferralTree(int $userId, int $currentDepth, int $maxDepth): array
{
    if ($currentDepth > $maxDepth) return [];

    $children = db()->fetchAll(
        'SELECT u.id, u.full_name, u.username, u.vip_level, u.created_at
         FROM referrals r JOIN users u ON r.referred_id = u.id
         WHERE r.referrer_id = ? AND r.level = 1
         ORDER BY u.created_at ASC LIMIT 50',
        'i', $userId
    );

    foreach ($children as &$child) {
        $child['children'] = buildReferralTree($child['id'], $currentDepth + 1, $maxDepth);
        $child['level']    = $currentDepth;
    }
    unset($child);

    return $children;
}
