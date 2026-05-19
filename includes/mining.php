<?php
/**
 * NOXARA - Mining System Functions
 */

function purchaseProduct(int $userId, int $productId, string $walletType = WALLET_MAIN): array
{
    $product = db()->fetchOne('SELECT * FROM products WHERE id = ? AND is_active = 1', 'i', $productId);
    if (!$product) return ['success' => false, 'message' => 'Produk tidak ditemukan.'];

    // Check VIP requirement
    $user = db()->fetchOne('SELECT vip_level FROM users WHERE id = ?', 'i', $userId);
    if ($user['vip_level'] < $product['min_vip_level']) {
        return ['success' => false, 'message' => 'VIP level tidak mencukupi untuk produk ini.'];
    }

    // Validate wallet type (bonus can only buy, not withdraw)
    if (!in_array($walletType, [WALLET_MAIN, WALLET_BONUS], true)) {
        return ['success' => false, 'message' => 'Jenis saldo tidak valid.'];
    }

    $price   = (float)$product['price'];
    $balance = getWalletBalance($userId, $walletType);

    if ($balance < $price) {
        return ['success' => false, 'message' => 'Saldo ' . ($walletType === WALLET_BONUS ? 'bonus ' : '') . 'tidak mencukupi.'];
    }

    // Check stock
    if ($product['stock'] !== null && $product['stock'] <= 0) {
        return ['success' => false, 'message' => 'Stok produk habis.'];
    }

    db()->beginTransaction();
    try {
        // Debit wallet
        $debited = debitWallet($userId, $walletType, $price, TRX_BUY_PACKAGE, 'product', $productId, 'Beli paket ' . $product['name']);
        if (!$debited) {
            db()->rollback();
            return ['success' => false, 'message' => 'Gagal memotong saldo.'];
        }

        $startDate = date(DB_DATE_FORMAT);
        $endDate   = date(DB_DATE_FORMAT, strtotime('+' . $product['duration_days'] . ' days'));

        db()->execute(
            'INSERT INTO user_products (user_id, product_id, purchase_price, profit_per_day, duration_days, wallet_used, start_date, end_date)
             VALUES (?,?,?,?,?,?,?,?)',
            'iiddiiss',
            $userId, $productId, $price, $product['profit_per_day'], $product['duration_days'],
            $walletType, $startDate, $endDate
        );
        $userProductId = db()->lastInsertId();

        // Reduce stock if limited
        if ($product['stock'] !== null) {
            db()->execute('UPDATE products SET stock = stock - 1 WHERE id = ?', 'i', $productId);
        }

        db()->commit();

        // Process referral commission (product purchase)
        processReferralCommission($userId, $price, COMMISSION_PRODUCT, $userProductId);

        sendNotification($userId, 'Paket Aktif! ⛏️',
            'Paket ' . $product['name'] . ' berhasil diaktifkan. Mulai mining setiap hari untuk mendapatkan profit!',
            NOTIF_MINING);

        trackMissionProgress($userId, ACTION_MINING); // count purchase as mining intent

        return ['success' => true, 'user_product_id' => $userProductId, 'message' => 'Paket berhasil dibeli!'];

    } catch (Exception $e) {
        db()->rollback();
        error_log('purchaseProduct error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan. Coba lagi.'];
    }
}

function doMining(int $userId, int $userProductId): array
{
    $pkg = db()->fetchOne(
        'SELECT up.*, p.name as product_name FROM user_products up
         JOIN products p ON up.product_id = p.id
         WHERE up.id = ? AND up.user_id = ? AND up.status = ?',
        'iis', $userProductId, $userId, PACKAGE_ACTIVE
    );

    if (!$pkg) return ['success' => false, 'message' => 'Paket tidak ditemukan atau tidak aktif.'];

    // Check if already mined today
    if (!empty($pkg['last_mined_at'])) {
        $lastMined = new DateTime($pkg['last_mined_at'], new DateTimeZone(APP_TIMEZONE));
        $today     = new DateTime('today', new DateTimeZone(APP_TIMEZONE));
        if ($lastMined >= $today) {
            $nextMine = new DateTime('tomorrow', new DateTimeZone(APP_TIMEZONE));
            $secondsLeft = $nextMine->getTimestamp() - time();
            return [
                'success'      => false,
                'message'      => 'Sudah mining hari ini. Kembali besok.',
                'next_at'      => $nextMine->format(DB_DATETIME_FORMAT),
                'seconds_left' => $secondsLeft,
            ];
        }
    }

    $profit       = (float)$pkg['profit_per_day'];
    $creditedAt   = date(DB_DATETIME_FORMAT, strtotime('+' . MINING_CREDIT_DELAY . ' hours'));

    db()->beginTransaction();
    try {
        // Update last mined
        db()->execute(
            'UPDATE user_products SET last_mined_at = NOW(), mine_count = mine_count + 1 WHERE id = ?',
            'i', $userProductId
        );

        // Insert mining log (pending, will be credited by cron or API)
        db()->execute(
            'INSERT INTO mining_logs (user_product_id, user_id, profit_amount, mined_at, credited_at, status)
             VALUES (?,?,?,NOW(),?,?)',
            'iidss', $userProductId, $userId, $profit, $creditedAt, 'pending'
        );
        $miningLogId = db()->lastInsertId();

        db()->commit();

        trackMissionProgress($userId, ACTION_MINING);

        return [
            'success'       => true,
            'profit'        => $profit,
            'credited_at'   => $creditedAt,
            'mining_log_id' => $miningLogId,
            'message'       => 'Mining berhasil! Profit ' . formatRupiah($profit) . ' akan masuk dalam ' . MINING_CREDIT_DELAY . ' jam.',
        ];

    } catch (Exception $e) {
        db()->rollback();
        error_log('doMining error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal melakukan mining.'];
    }
}

function creditMiningProfit(int $miningLogId): array
{
    $log = db()->fetchOne(
        'SELECT ml.*, up.user_id FROM mining_logs ml JOIN user_products up ON ml.user_product_id = up.id
         WHERE ml.id = ? AND ml.status = ?',
        'is', $miningLogId, 'pending'
    );
    if (!$log) return ['success' => false, 'message' => 'Mining log tidak ditemukan.'];
    if (strtotime($log['credited_at']) > time()) {
        return ['success' => false, 'message' => 'Belum waktunya dikreditkan.'];
    }

    db()->beginTransaction();
    try {
        creditWallet($log['user_id'], WALLET_PROFIT, (float)$log['profit_amount'],
            TRX_MINING_PROFIT, 'mining_log', $miningLogId, 'Profit mining');

        db()->execute(
            "UPDATE mining_logs SET status = 'credited', credited_at = NOW() WHERE id = ?",
            'i', $miningLogId
        );
        db()->execute(
            'UPDATE user_products SET total_earned = total_earned + ? WHERE id = ?',
            'di', $log['profit_amount'], $log['user_product_id']
        );

        db()->commit();

        sendNotification($log['user_id'], 'Profit Mining Masuk! 💰',
            'Profit mining ' . formatRupiah($log['profit_amount']) . ' telah masuk ke saldo profit.',
            NOTIF_MINING);

        return ['success' => true, 'message' => 'Profit berhasil dikreditkan.'];
    } catch (Exception $e) {
        db()->rollback();
        return ['success' => false, 'message' => 'Gagal mengkreditkan profit.'];
    }
}

function getUserActivePackages(int $userId): array
{
    return db()->fetchAll(
        "SELECT up.*, p.name as product_name, p.image as product_image,
                pc.name as category_name,
                DATEDIFF(up.end_date, CURDATE()) as days_remaining,
                CASE WHEN up.last_mined_at IS NULL OR DATE(up.last_mined_at) < CURDATE() THEN 1 ELSE 0 END as can_mine
         FROM user_products up
         JOIN products p ON up.product_id = p.id
         JOIN product_categories pc ON p.category_id = pc.id
         WHERE up.user_id = ? AND up.status = 'active'
         ORDER BY up.created_at DESC",
        'i', $userId
    );
}

function getUserAllPackages(int $userId, int $limit = PER_PAGE, int $offset = 0): array
{
    return db()->fetchAll(
        "SELECT up.*, p.name as product_name, p.image as product_image
         FROM user_products up JOIN products p ON up.product_id = p.id
         WHERE up.user_id = ?
         ORDER BY up.created_at DESC LIMIT ? OFFSET ?",
        'iii', $userId, $limit, $offset
    );
}

function getMiningCountdown(int $userProductId): array
{
    $pkg = db()->fetchOne('SELECT last_mined_at FROM user_products WHERE id = ?', 'i', $userProductId);
    if (!$pkg || empty($pkg['last_mined_at'])) return ['can_mine' => true, 'seconds_left' => 0];

    $lastMined   = new DateTime($pkg['last_mined_at'], new DateTimeZone(APP_TIMEZONE));
    $tomorrow    = new DateTime('tomorrow', new DateTimeZone(APP_TIMEZONE));
    $secondsLeft = max(0, $tomorrow->getTimestamp() - time());

    return ['can_mine' => $secondsLeft === 0, 'seconds_left' => $secondsLeft, 'next_at' => $tomorrow->format(DB_DATETIME_FORMAT)];
}

// Track mission progress helper
function trackMissionProgress(int $userId, string $actionType): void
{
    if (!getSetting('missions_enabled', '1')) return;

    $today      = date(DB_DATE_FORMAT);
    $weekKey    = date('Y') . '-W' . date('W');

    $missions = db()->fetchAll(
        "SELECT * FROM missions WHERE action_type = ? AND is_active = 1",
        's', $actionType
    );

    foreach ($missions as $mission) {
        $periodKey = match($mission['type']) {
            MISSION_DAILY     => $today,
            MISSION_WEEKLY    => $weekKey,
            MISSION_MILESTONE => null,
            default           => $today,
        };

        // Find or create user mission
        $periodWhere = $periodKey !== null ? 'AND period_key = ?' : 'AND period_key IS NULL';
        $params      = $periodKey !== null ? [$userId, $mission['id'], $periodKey] : [$userId, $mission['id']];
        $types       = $periodKey !== null ? 'iii' : 'ii';

        $um = db()->fetchOne(
            "SELECT * FROM user_missions WHERE user_id = ? AND mission_id = ? $periodWhere",
            $types, ...$params
        );

        if (!$um) {
            if ($periodKey !== null) {
                db()->execute(
                    'INSERT INTO user_missions (user_id, mission_id, progress, period_key) VALUES (?,?,1,?)',
                    'iis', $userId, $mission['id'], $periodKey
                );
            } else {
                db()->execute(
                    'INSERT INTO user_missions (user_id, mission_id, progress) VALUES (?,?,1)',
                    'ii', $userId, $mission['id']
                );
            }
            $newProgress = 1;
        } else {
            if ($um['is_completed']) continue; // Already done
            $newProgress = $um['progress'] + 1;
            db()->execute(
                'UPDATE user_missions SET progress = ? WHERE id = ?',
                'ii', $newProgress, $um['id']
            );
        }

        // Check completion
        if ($newProgress >= $mission['target_count']) {
            db()->execute(
                'UPDATE user_missions SET is_completed = 1, completed_at = NOW() WHERE user_id = ? AND mission_id = ? ' . $periodWhere,
                $types, ...$params
            );
        }
    }
}
