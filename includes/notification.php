<?php
/**
 * NOXARA - Notification Functions
 */

function sendNotification(int $userId, string $title, string $message, string $type = NOTIF_INFO, string $actionUrl = ''): void
{
    db()->execute(
        'INSERT INTO notifications (user_id, title, message, type, action_url) VALUES (?,?,?,?,?)',
        'issss', $userId, $title, $message, $type, $actionUrl
    );

    // Send WhatsApp if enabled
    sendWhatsApp($userId, $title, $message);
}

function sendBroadcastNotification(string $title, string $message, string $type = NOTIF_SYSTEM): int
{
    // Insert for all active, non-blocked users
    $users = db()->fetchAll('SELECT id FROM users WHERE is_active = 1 AND is_blocked = 0');
    $count = 0;
    foreach ($users as $user) {
        db()->execute(
            'INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)',
            'isss', $user['id'], $title, $message, $type
        );
        $count++;
    }
    return $count;
}

function sendWhatsApp(int $userId, string $title, string $message): void
{
    $settings = db()->fetchOne('SELECT * FROM notification_settings LIMIT 1');
    if (!$settings || !$settings['whatsapp_enabled']) return;

    $user = db()->fetchOne('SELECT phone FROM users WHERE id = ?', 'i', $userId);
    if (!$user || empty($user['phone'])) return;

    $phone    = $user['phone'];
    $text     = "*" . SITE_NAME . "*\n" . $title . "\n\n" . $message;
    $apiUrl   = rtrim($settings['whatsapp_api_url'], '/');
    $token    = $settings['whatsapp_token'];
    $sender   = $settings['whatsapp_sender'];

    // Fonnte API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl . '/send',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['target' => $phone, 'message' => $text, 'sender' => $sender]),
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    // Log silently if error
    if (!$response) {
        error_log('WhatsApp send failed for user ' . $userId);
    }
}

function getUserNotifications(int $userId, int $limit = 20, int $offset = 0): array
{
    return db()->fetchAll(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
        'iii', $userId, $limit, $offset
    );
}

function markNotificationRead(int $notifId, int $userId): void
{
    db()->execute(
        'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
        'ii', $notifId, $userId
    );
}

function markAllNotificationsRead(int $userId): void
{
    db()->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', 'i', $userId);
}

function deleteNotification(int $notifId, int $userId): void
{
    db()->execute('DELETE FROM notifications WHERE id = ? AND user_id = ?', 'ii', $notifId, $userId);
}

// ── Daily Reward ──────────────────────────────────────────
function claimDailyReward(int $userId): array
{
    if (!getSetting('daily_reward_enabled', '1')) {
        return ['success' => false, 'message' => 'Hadiah harian tidak tersedia.'];
    }

    // Check if already claimed today
    $today   = date(DB_DATE_FORMAT);
    $claimed = db()->fetchOne(
        "SELECT id FROM user_daily_claims WHERE user_id = ? AND DATE(claimed_at) = ?",
        'is', $userId, $today
    );
    if ($claimed) {
        return ['success' => false, 'message' => 'Sudah klaim hadiah hari ini. Kembali besok!'];
    }

    // Get active reward items
    $items = db()->fetchAll('SELECT * FROM daily_reward_items WHERE is_active = 1');
    if (empty($items)) return ['success' => false, 'message' => 'Tidak ada hadiah tersedia.'];

    // Weighted random selection
    $totalWeight = array_sum(array_column($items, 'probability'));
    $rand        = (lcg_value() * $totalWeight);
    $cumulative  = 0;
    $selected    = $items[count($items) - 1]; // fallback

    foreach ($items as $item) {
        $cumulative += $item['probability'];
        if ($rand <= $cumulative) {
            $selected = $item;
            break;
        }
    }

    db()->beginTransaction();
    try {
        // Credit wallet
        creditWallet($userId, $selected['wallet_type'], (float)$selected['value'],
            TRX_DAILY_REWARD, 'daily_reward_item', $selected['id'],
            'Hadiah harian: ' . $selected['name']);

        // Record claim
        db()->execute(
            'INSERT INTO user_daily_claims (user_id, reward_item_id, reward_value) VALUES (?,?,?)',
            'iid', $userId, $selected['id'], $selected['value']
        );

        db()->commit();

        trackMissionProgress($userId, ACTION_LOGIN); // daily reward counts as login activity

        sendNotification($userId, 'Hadiah Harian Diklaim! 🎁',
            'Kamu mendapatkan: ' . $selected['name'] . ' (' . formatRupiah($selected['value']) . ')', NOTIF_SUCCESS);

        return [
            'success' => true,
            'reward'  => $selected,
            'message' => 'Hadiah berhasil diklaim: ' . $selected['name'],
        ];
    } catch (Exception $e) {
        db()->rollback();
        return ['success' => false, 'message' => 'Gagal klaim hadiah.'];
    }
}

function hasDailyClaimToday(int $userId): bool
{
    $today   = date(DB_DATE_FORMAT);
    $claimed = db()->fetchOne(
        "SELECT id FROM user_daily_claims WHERE user_id = ? AND DATE(claimed_at) = ?",
        'is', $userId, $today
    );
    return !empty($claimed);
}

// ── Watch Ad ──────────────────────────────────────────────
function watchAd(int $userId, int $adId): array
{
    if (!getSetting('ads_enabled', '1')) {
        return ['success' => false, 'message' => 'Fitur iklan tidak aktif.'];
    }

    $adSettings = db()->fetchOne('SELECT * FROM ad_settings LIMIT 1');
    $maxPerDay  = (int)($adSettings['max_per_day'] ?? 5);
    $cooldown   = (int)($adSettings['cooldown_minutes'] ?? 10);

    // Count today's watches
    $todayCount = db()->fetchOne(
        'SELECT COUNT(*) as cnt FROM ad_watches WHERE user_id = ? AND DATE(watched_at) = CURDATE()',
        'i', $userId
    );
    if ((int)$todayCount['cnt'] >= $maxPerDay) {
        return ['success' => false, 'message' => 'Kuota iklan harian sudah habis.'];
    }

    // Check cooldown
    $lastWatch = db()->fetchOne(
        'SELECT watched_at FROM ad_watches WHERE user_id = ? ORDER BY watched_at DESC LIMIT 1',
        'i', $userId
    );
    if ($lastWatch) {
        $elapsed = (time() - strtotime($lastWatch['watched_at'])) / 60;
        if ($elapsed < $cooldown) {
            $remainingMins = ceil($cooldown - $elapsed);
            return ['success' => false, 'message' => "Tunggu $remainingMins menit lagi sebelum tonton iklan berikutnya."];
        }
    }

    $ad = db()->fetchOne('SELECT * FROM ads WHERE id = ? AND is_active = 1', 'i', $adId);
    if (!$ad) return ['success' => false, 'message' => 'Iklan tidak ditemukan.'];

    db()->beginTransaction();
    try {
        creditWallet($userId, $ad['reward_wallet'], (float)$ad['reward_amount'],
            TRX_AD_REWARD, 'ad', $adId, 'Reward nonton iklan: ' . $ad['title']);

        db()->execute(
            'INSERT INTO ad_watches (user_id, ad_id, reward_amount) VALUES (?,?,?)',
            'iid', $userId, $adId, $ad['reward_amount']
        );
        db()->execute('UPDATE ads SET total_views = total_views + 1 WHERE id = ?', 'i', $adId);

        db()->commit();

        trackMissionProgress($userId, ACTION_WATCH_AD);

        return [
            'success' => true,
            'reward'  => $ad['reward_amount'],
            'wallet'  => $ad['reward_wallet'],
            'message' => 'Reward ' . formatRupiah($ad['reward_amount']) . ' berhasil diklaim!',
        ];
    } catch (Exception $e) {
        db()->rollback();
        return ['success' => false, 'message' => 'Gagal klaim reward iklan.'];
    }
}

// ── Mission Claim ─────────────────────────────────────────
function claimMissionReward(int $userId, int $missionId, ?string $periodKey = null): array
{
    $mission = db()->fetchOne('SELECT * FROM missions WHERE id = ? AND is_active = 1', 'i', $missionId);
    if (!$mission) return ['success' => false, 'message' => 'Misi tidak ditemukan.'];

    $periodWhere = $periodKey ? 'AND period_key = ?' : 'AND period_key IS NULL';
    $params      = $periodKey ? [$userId, $missionId, $periodKey] : [$userId, $missionId];
    $types       = $periodKey ? 'iis' : 'ii';

    $um = db()->fetchOne(
        "SELECT * FROM user_missions WHERE user_id = ? AND mission_id = ? $periodWhere AND is_completed = 1 AND is_claimed = 0",
        $types, ...$params
    );

    if (!$um) return ['success' => false, 'message' => 'Misi belum selesai atau sudah diklaim.'];

    db()->beginTransaction();
    try {
        if ($mission['reward_type'] === 'balance_bonus') {
            creditWallet($userId, WALLET_BONUS, (float)$mission['reward_value'],
                TRX_MISSION_REWARD, 'mission', $missionId, 'Reward misi: ' . $mission['title']);
        }
        // TODO: voucher reward type

        db()->execute(
            'UPDATE user_missions SET is_claimed = 1, claimed_at = NOW() WHERE id = ?',
            'i', $um['id']
        );
        db()->commit();

        sendNotification($userId, 'Reward Misi Diklaim! 🎯',
            'Reward misi "' . $mission['title'] . '" sebesar ' . formatRupiah($mission['reward_value']) . ' berhasil diklaim.', NOTIF_SUCCESS);

        return ['success' => true, 'message' => 'Reward berhasil diklaim!'];
    } catch (Exception $e) {
        db()->rollback();
        return ['success' => false, 'message' => 'Gagal klaim reward.'];
    }
}
