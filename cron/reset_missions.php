<?php
/**
 * NOXARA Cron - Reset Daily/Weekly Mission Progress
 * Schedule: Daily at 00:01, Weekly Monday 00:01
 * Command: php /path/to/cron/reset_missions.php --secret=CRON_SECRET
 */
define('CRON_RUN', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/bootstrap.php';

$secret = str_replace('--secret=', '', $argv[1] ?? $_GET['secret'] ?? '');
if ($secret !== CRON_SECRET) { http_response_code(403); die('Unauthorized'); }

$startTime = microtime(true);
$dailyReset = 0; $weeklyReset = 0;

try {
    $yesterday = date(DB_DATE_FORMAT, strtotime('-1 day'));
    $lastWeek  = date('Y') . '-W' . date('W', strtotime('-1 week'));

    // ── Reset daily missions (keep completed, mark uncompleted as stale) ──
    // We don't delete; new entries are created per period_key each day
    // Just clean up old daily entries (older than 30 days)
    $r1 = db()->execute(
        "DELETE FROM user_missions
         WHERE type = ? AND period_key < DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        's', MISSION_DAILY
    );
    $dailyReset = $r1;

    // ── Reset weekly missions (clean up entries older than 12 weeks) ──
    // Get cutoff week key
    $cutoffWeek = date('Y') . '-W' . str_pad(max(1, date('W') - 12), 2, '0', STR_PAD_LEFT);
    $r2 = db()->execute(
        "DELETE FROM user_missions
         WHERE type = ? AND period_key < ? AND period_key IS NOT NULL",
        'ss', MISSION_WEEKLY, $cutoffWeek
    );
    $weeklyReset = $r2;

    // ── Update leaderboard cache ──────────────────────────
    $currentMonth = date('Y-m');
    $types = ['deposit', 'referral', 'profit'];

    foreach ($types as $type) {
        $data = match($type) {
            'deposit' => db()->fetchAll(
                "SELECT user_id, SUM(amount) as value FROM deposits WHERE status='confirmed' AND DATE_FORMAT(confirmed_at,'%Y-%m')=? GROUP BY user_id ORDER BY value DESC LIMIT 10",
                's', $currentMonth
            ),
            'referral' => db()->fetchAll(
                "SELECT user_id, SUM(amount) as value FROM commissions WHERE DATE_FORMAT(created_at,'%Y-%m')=? GROUP BY user_id ORDER BY value DESC LIMIT 10",
                's', $currentMonth
            ),
            'profit' => db()->fetchAll(
                "SELECT user_id, SUM(profit_amount) as value FROM mining_logs WHERE status='credited' AND DATE_FORMAT(credited_at,'%Y-%m')=? GROUP BY user_id ORDER BY value DESC LIMIT 10",
                's', $currentMonth
            ),
        };

        // Clear and reinsert
        db()->execute("DELETE FROM leaderboard_cache WHERE type = ? AND period = ?", 'ss', $type, $currentMonth);
        foreach ($data as $i => $row) {
            db()->execute(
                "INSERT INTO leaderboard_cache (type, period, user_id, rank, value) VALUES (?,?,?,?,?)",
                'ssiii', $type, $currentMonth, $row['user_id'], $i + 1, $row['value']
            );
        }
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    $message  = "Daily cleaned: $dailyReset, Weekly cleaned: $weeklyReset, Leaderboard updated";

    db()->execute('INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?,?,?,?)', 'sssi', 'reset_missions', 'success', $message, $duration);
    echo "✅ reset_missions: $message ({$duration}ms)\n";

} catch (Throwable $e) {
    $duration = round((microtime(true) - $startTime) * 1000);
    db()->execute('INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?,?,?,?)', 'sssi', 'reset_missions', 'error', $e->getMessage(), $duration);
    echo "❌ reset_missions ERROR: " . $e->getMessage() . "\n";
    error_log('Cron reset_missions error: ' . $e->getMessage());
}
