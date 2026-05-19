<?php
/**
 * NOXARA Cron - Check Expired Packages & Send Notifications
 * Schedule: Every day at 00:05
 * Command: php /path/to/cron/check_packages.php --secret=CRON_SECRET
 */
define('CRON_RUN', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/bootstrap.php';

$secret = str_replace('--secret=', '', $argv[1] ?? $_GET['secret'] ?? '');
if ($secret !== CRON_SECRET) { http_response_code(403); die('Unauthorized'); }

$startTime = microtime(true);
$completed = 0; $notified3d = 0; $notified1d = 0; $depositsExpired = 0;

try {
    // ── 1. Complete expired active packages ───────────────
    $expiredPackages = db()->fetchAll(
        "SELECT up.*, p.name as product_name, p.price
         FROM user_products up JOIN products p ON up.product_id = p.id
         WHERE up.status = 'active' AND up.end_date < CURDATE()"
    );

    foreach ($expiredPackages as $pkg) {
        db()->beginTransaction();
        try {
            db()->execute("UPDATE user_products SET status = 'completed' WHERE id = ?", 'i', $pkg['id']);

            // Return capital to main wallet
            creditWallet(
                $pkg['user_id'], WALLET_MAIN, (float)$pkg['purchase_price'],
                TRX_MINING_RETURN, 'user_product', $pkg['id'],
                'Modal kembali: ' . $pkg['product_name']
            );

            db()->commit();

            sendNotification(
                $pkg['user_id'],
                'Paket Mining Selesai ⛏️',
                'Paket ' . $pkg['product_name'] . ' telah selesai. Modal Rp' . number_format($pkg['purchase_price'], 0, ',', '.') . ' sudah dikembalikan ke saldo utama.',
                NOTIF_MINING
            );
            $completed++;
        } catch (Exception $e) {
            db()->rollback();
            error_log('check_packages complete error: ' . $e->getMessage());
        }
    }

    // ── 2. Notify H-3 (3 days before expiry) ─────────────
    $expiring3d = db()->fetchAll(
        "SELECT up.id, up.user_id, p.name as product_name, up.end_date
         FROM user_products up JOIN products p ON up.product_id = p.id
         WHERE up.status = 'active'
         AND up.notified_3days = 0
         AND DATEDIFF(up.end_date, CURDATE()) = 3"
    );

    foreach ($expiring3d as $pkg) {
        sendNotification(
            $pkg['user_id'],
            '⚠️ Paket Hampir Habis',
            'Paket ' . $pkg['product_name'] . ' akan berakhir dalam 3 hari ('. date(DATE_FORMAT, strtotime($pkg['end_date'])) .'). Segera perpanjang!',
            NOTIF_WARNING
        );
        db()->execute('UPDATE user_products SET notified_3days = 1 WHERE id = ?', 'i', $pkg['id']);
        $notified3d++;
    }

    // ── 3. Notify H-1 (1 day before expiry) ──────────────
    $expiring1d = db()->fetchAll(
        "SELECT up.id, up.user_id, p.name as product_name, up.end_date
         FROM user_products up JOIN products p ON up.product_id = p.id
         WHERE up.status = 'active'
         AND up.notified_1day = 0
         AND DATEDIFF(up.end_date, CURDATE()) = 1"
    );

    foreach ($expiring1d as $pkg) {
        sendNotification(
            $pkg['user_id'],
            '🔴 Paket Berakhir Besok!',
            'Paket ' . $pkg['product_name'] . ' akan berakhir BESOK! Segera beli paket baru agar profit tidak terputus.',
            NOTIF_WARNING
        );
        db()->execute('UPDATE user_products SET notified_1day = 1 WHERE id = ?', 'i', $pkg['id']);
        $notified1d++;
    }

    // ── 4. Expire old pending deposits ───────────────────
    $expired = db()->execute(
        "UPDATE deposits SET status = 'expired' WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()"
    );
    $depositsExpired = $expired;

    $duration = round((microtime(true) - $startTime) * 1000);
    $message  = "Completed: $completed pkgs, H-3 notif: $notified3d, H-1 notif: $notified1d, Deposits expired: $depositsExpired";

    db()->execute('INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?,?,?,?)', 'sssi', 'check_packages', 'success', $message, $duration);
    echo "✅ check_packages: $message ({$duration}ms)\n";

} catch (Throwable $e) {
    $duration = round((microtime(true) - $startTime) * 1000);
    db()->execute('INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?,?,?,?)', 'sssi', 'check_packages', 'error', $e->getMessage(), $duration);
    echo "❌ check_packages ERROR: " . $e->getMessage() . "\n";
    error_log('Cron check_packages error: ' . $e->getMessage());
}
