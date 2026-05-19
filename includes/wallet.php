<?php
/**
 * NOXARA - Wallet Functions
 */

function getUserWallet(int $userId): array
{
    $wallet = db()->fetchOne('SELECT * FROM user_wallets WHERE user_id = ?', 'i', $userId);
    if (!$wallet) {
        // Auto-create if missing
        db()->execute('INSERT INTO user_wallets (user_id) VALUES (?)', 'i', $userId);
        return ['user_id' => $userId, 'main_balance' => 0, 'profit_balance' => 0, 'bonus_balance' => 0, 'referral_balance' => 0];
    }
    return $wallet;
}

function getWalletBalance(int $userId, string $walletType): float
{
    $col = match($walletType) {
        WALLET_MAIN     => 'main_balance',
        WALLET_PROFIT   => 'profit_balance',
        WALLET_BONUS    => 'bonus_balance',
        WALLET_REFERRAL => 'referral_balance',
        default         => throw new InvalidArgumentException("Invalid wallet type: $walletType"),
    };
    $row = db()->fetchOne("SELECT $col as bal FROM user_wallets WHERE user_id = ?", 'i', $userId);
    return (float)($row['bal'] ?? 0);
}

function creditWallet(int $userId, string $walletType, float $amount, string $txType, string $refType = '', int $refId = 0, string $desc = ''): bool
{
    if ($amount <= 0) return false;

    $col = walletColumn($walletType);
    $balBefore = getWalletBalance($userId, $walletType);
    $balAfter  = $balBefore + $amount;

    db()->execute(
        "UPDATE user_wallets SET $col = $col + ? WHERE user_id = ?",
        'di', $amount, $userId
    );

    logTransaction($userId, $txType, $walletType, $amount, $balBefore, $balAfter, $refType, $refId, $desc);
    return true;
}

function debitWallet(int $userId, string $walletType, float $amount, string $txType, string $refType = '', int $refId = 0, string $desc = ''): bool
{
    if ($amount <= 0) return false;

    $col       = walletColumn($walletType);
    $balBefore = getWalletBalance($userId, $walletType);

    if ($balBefore < $amount) return false; // Insufficient balance

    $balAfter = $balBefore - $amount;
    db()->execute(
        "UPDATE user_wallets SET $col = $col - ? WHERE user_id = ? AND $col >= ?",
        'did', $amount, $userId, $amount
    );

    if (db()->execute('SELECT ROW_COUNT() as rc', '') === 0) {
        // Race condition - re-check
        $current = getWalletBalance($userId, $walletType);
        if ($current < $amount) return false;
    }

    logTransaction($userId, $txType, $walletType, -$amount, $balBefore, $balAfter, $refType, $refId, $desc);
    return true;
}

function walletColumn(string $walletType): string
{
    return match($walletType) {
        WALLET_MAIN     => 'main_balance',
        WALLET_PROFIT   => 'profit_balance',
        WALLET_BONUS    => 'bonus_balance',
        WALLET_REFERRAL => 'referral_balance',
        default         => throw new InvalidArgumentException("Invalid wallet type: $walletType"),
    };
}

function logTransaction(int $userId, string $type, string $walletType, float $amount, float $balBefore, float $balAfter, string $refType, int $refId, string $desc): void
{
    db()->execute(
        'INSERT INTO transactions (user_id, type, wallet_type, amount, balance_before, balance_after, reference_type, reference_id, description)
         VALUES (?,?,?,?,?,?,?,?,?)',
        'issdddsis',
        $userId, $type, $walletType, $amount, $balBefore, $balAfter, $refType, $refId, $desc
    );
}

// ── Deposit ───────────────────────────────────────────────
function createDeposit(int $userId, float $amount, int $adminBankId, ?int $voucherId = null): array
{
    if ($amount <= 0) return ['success' => false, 'message' => 'Nominal tidak valid.'];

    // Check deposit enabled
    if (!getSetting('deposit_enabled', '1')) {
        return ['success' => false, 'message' => 'Fitur deposit sedang dinonaktifkan.'];
    }

    // Check admin bank exists
    $bank = db()->fetchOne('SELECT * FROM admin_bank_accounts WHERE id = ? AND is_active = 1', 'i', $adminBankId);
    if (!$bank) return ['success' => false, 'message' => 'Rekening tujuan tidak ditemukan.'];

    $uniqueCode  = generateUniqueCode();
    $totalAmount = $amount + $uniqueCode;
    $discount    = 0.0;

    // Handle voucher
    if ($voucherId) {
        $voucher = validateVoucher($voucherId, $userId, 'deposit', $amount);
        if ($voucher['valid']) {
            $discount    = $voucher['discount'];
            $totalAmount = max(0, $amount - $discount) + $uniqueCode;
        }
    }

    $expiryHours = (int)getSetting('deposit_expiry_hours', 24);
    $expiresAt   = date(DB_DATETIME_FORMAT, strtotime('+' . $expiryHours . ' hours'));

    db()->execute(
        'INSERT INTO deposits (user_id, admin_bank_id, amount, unique_code, total_amount, voucher_id, voucher_discount, expires_at)
         VALUES (?,?,?,?,?,?,?,?)',
        'iididds',
        $userId, $adminBankId, $amount, $uniqueCode, $totalAmount,
        $voucherId ?: null, $discount, $expiresAt
    );

    $depositId = db()->lastInsertId();
    sendNotification($userId, 'Deposit Menunggu Konfirmasi',
        'Deposit Anda sebesar ' . formatRupiah($totalAmount) . ' sedang menunggu konfirmasi admin.', NOTIF_INFO);

    return ['success' => true, 'deposit_id' => $depositId, 'total_amount' => $totalAmount, 'unique_code' => $uniqueCode, 'message' => 'Deposit berhasil diajukan.'];
}

function confirmDeposit(int $depositId, int $adminId): array
{
    $deposit = db()->fetchOne('SELECT * FROM deposits WHERE id = ? AND status = ?', 'is', $depositId, DEPOSIT_PENDING);
    if (!$deposit) return ['success' => false, 'message' => 'Deposit tidak ditemukan atau sudah diproses.'];

    db()->beginTransaction();
    try {
        db()->execute(
            "UPDATE deposits SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?",
            'ii', $adminId, $depositId
        );

        // Credit main wallet
        creditWallet($deposit['user_id'], WALLET_MAIN, (float)$deposit['amount'], TRX_DEPOSIT, 'deposit', $depositId, 'Konfirmasi deposit #' . $depositId);

        // Update total deposit for VIP
        db()->execute(
            'UPDATE users SET total_deposit = total_deposit + ? WHERE id = ?',
            'di', $deposit['amount'], $deposit['user_id']
        );

        // Check VIP upgrade
        checkAndUpgradeVip($deposit['user_id']);

        // Process referral commissions
        processReferralCommission($deposit['user_id'], (float)$deposit['amount'], COMMISSION_DEPOSIT, $depositId);

        // Handle voucher
        if ($deposit['voucher_id']) {
            db()->execute('UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?', 'i', $deposit['voucher_id']);
            db()->execute(
                'INSERT INTO user_vouchers (user_id, voucher_id, reference_type, reference_id) VALUES (?,?,?,?)',
                'iisi', $deposit['user_id'], $deposit['voucher_id'], 'deposit', $depositId
            );
        }

        db()->commit();

        sendNotification($deposit['user_id'], 'Deposit Dikonfirmasi! ✅',
            'Deposit Anda sebesar ' . formatRupiah($deposit['amount']) . ' telah dikonfirmasi dan masuk ke saldo utama.', NOTIF_SUCCESS);

        trackMissionProgress($deposit['user_id'], ACTION_DEPOSIT);
        return ['success' => true, 'message' => 'Deposit berhasil dikonfirmasi.'];

    } catch (Exception $e) {
        db()->rollback();
        error_log('confirmDeposit error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal mengkonfirmasi deposit.'];
    }
}

function rejectDeposit(int $depositId, int $adminId, string $reason = ''): array
{
    $deposit = db()->fetchOne('SELECT * FROM deposits WHERE id = ? AND status = ?', 'is', $depositId, DEPOSIT_PENDING);
    if (!$deposit) return ['success' => false, 'message' => 'Deposit tidak ditemukan.'];

    db()->execute(
        "UPDATE deposits SET status = 'rejected', confirmed_by = ?, confirmed_at = NOW(), rejection_reason = ? WHERE id = ?",
        'isi', $adminId, $reason, $depositId
    );

    sendNotification($deposit['user_id'], 'Deposit Ditolak ❌',
        'Deposit Anda ditolak. Alasan: ' . ($reason ?: 'Tidak disebutkan'), NOTIF_ERROR);

    return ['success' => true, 'message' => 'Deposit ditolak.'];
}

// ── Withdraw ──────────────────────────────────────────────
function createWithdraw(int $userId, float $amount, string $walletType, int $bankAccountId, string $pin): array
{
    // Check withdraw enabled
    if (!getSetting('withdraw_enabled', '1')) {
        return ['success' => false, 'message' => 'Fitur withdraw sedang dinonaktifkan.'];
    }

    // Check operating hours
    $startHour = (int)getSetting('withdraw_start_hour', 8);
    $endHour   = (int)getSetting('withdraw_end_hour', 21);
    $currentHour = (int)date('G');
    if ($currentHour < $startHour || $currentHour >= $endHour) {
        return ['success' => false, 'message' => "Jam withdraw: {$startHour}:00 - {$endHour}:00 WIB."];
    }

    // Verify PIN
    if (!verifyPin($userId, $pin)) {
        return ['success' => false, 'message' => 'PIN transaksi salah.'];
    }

    // Validate wallet type for withdraw
    if (!in_array($walletType, [WALLET_MAIN, WALLET_PROFIT, WALLET_REFERRAL], true)) {
        return ['success' => false, 'message' => 'Jenis saldo tidak bisa ditarik.'];
    }

    // Get VIP rules
    $user    = db()->fetchOne('SELECT vip_level FROM users WHERE id = ?', 'i', $userId);
    $vipInfo = db()->fetchOne('SELECT * FROM vip_levels WHERE level = ?', 'i', $user['vip_level']);

    if ($amount < $vipInfo['min_withdraw']) {
        return ['success' => false, 'message' => 'Minimal withdraw ' . formatRupiah($vipInfo['min_withdraw']) . ' untuk VIP ' . $user['vip_level']];
    }

    // Check daily count
    $todayCount = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM withdrawals WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status != 'rejected'",
        'i', $userId
    );
    if ((int)$todayCount['cnt'] >= $vipInfo['max_withdraw_per_day']) {
        return ['success' => false, 'message' => 'Batas penarikan harian sudah tercapai.'];
    }

    // Check daily limit
    $todayTotal = db()->fetchOne(
        "SELECT COALESCE(SUM(amount),0) as total FROM withdrawals WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status != 'rejected'",
        'i', $userId
    );
    if (((float)$todayTotal['total'] + $amount) > $vipInfo['daily_withdraw_limit']) {
        return ['success' => false, 'message' => 'Batas total penarikan harian terlampaui.'];
    }

    // Check balance
    $balance = getWalletBalance($userId, $walletType);
    $fee     = $amount * ($vipInfo['withdraw_fee_percent'] / 100);
    $net     = $amount - $fee;

    if ($balance < $amount) {
        return ['success' => false, 'message' => 'Saldo tidak mencukupi.'];
    }

    // Validate bank account
    $bank = db()->fetchOne('SELECT * FROM bank_accounts WHERE id = ? AND user_id = ?', 'ii', $bankAccountId, $userId);
    if (!$bank) return ['success' => false, 'message' => 'Rekening bank tidak ditemukan.'];

    db()->beginTransaction();
    try {
        // Debit wallet immediately (hold funds)
        debitWallet($userId, $walletType, $amount, TRX_WITHDRAW, 'withdrawal', 0, 'Penarikan dana');

        db()->execute(
            'INSERT INTO withdrawals (user_id, bank_account_id, wallet_type, amount, fee, fee_percent, net_amount)
             VALUES (?,?,?,?,?,?,?)',
            'iisdddd', $userId, $bankAccountId, $walletType, $amount, $fee, $vipInfo['withdraw_fee_percent'], $net
        );
        $wdId = db()->lastInsertId();
        db()->execute('UPDATE withdrawals SET id = ? WHERE id = ?', 'ii', $wdId, $wdId); // refresh

        db()->commit();

        sendNotification($userId, 'Penarikan Diajukan 💸',
            'Permintaan penarikan ' . formatRupiah($amount) . ' (diterima: ' . formatRupiah($net) . ') sedang diproses.', NOTIF_INFO);

        return ['success' => true, 'withdrawal_id' => $wdId, 'message' => 'Penarikan berhasil diajukan.'];

    } catch (Exception $e) {
        db()->rollback();
        return ['success' => false, 'message' => 'Gagal mengajukan penarikan.'];
    }
}

function approveWithdraw(int $wdId, int $adminId): array
{
    $wd = db()->fetchOne('SELECT * FROM withdrawals WHERE id = ? AND status = ?', 'is', $wdId, WITHDRAW_PENDING);
    if (!$wd) return ['success' => false, 'message' => 'Withdraw tidak ditemukan.'];

    db()->execute(
        "UPDATE withdrawals SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?",
        'ii', $adminId, $wdId
    );

    sendNotification($wd['user_id'], 'Penarikan Disetujui ✅',
        'Penarikan ' . formatRupiah($wd['amount']) . ' telah disetujui. Dana dalam proses transfer.', NOTIF_SUCCESS);

    return ['success' => true, 'message' => 'Withdraw disetujui.'];
}

function rejectWithdraw(int $wdId, int $adminId, string $reason = ''): array
{
    $wd = db()->fetchOne('SELECT * FROM withdrawals WHERE id = ? AND status IN (?,?)', 'iss', $wdId, WITHDRAW_PENDING, WITHDRAW_PROCESSING);
    if (!$wd) return ['success' => false, 'message' => 'Withdraw tidak ditemukan.'];

    db()->beginTransaction();
    try {
        db()->execute(
            "UPDATE withdrawals SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?",
            'isi', $adminId, $reason, $wdId
        );

        // Refund to wallet
        creditWallet($wd['user_id'], $wd['wallet_type'], (float)$wd['amount'], TRX_WITHDRAW_RETURN, 'withdrawal', $wdId, 'Pengembalian dana penarikan ditolak');

        db()->commit();

        sendNotification($wd['user_id'], 'Penarikan Ditolak ❌',
            'Penarikan Anda ditolak. Alasan: ' . ($reason ?: '-') . '. Dana dikembalikan ke saldo Anda.', NOTIF_ERROR);

        return ['success' => true, 'message' => 'Withdraw ditolak dan dana dikembalikan.'];
    } catch (Exception $e) {
        db()->rollback();
        return ['success' => false, 'message' => 'Gagal menolak withdraw.'];
    }
}

// ── Voucher validation ────────────────────────────────────
function validateVoucher(int|string $voucherIdOrCode, int $userId, string $type, float $amount): array
{
    if (is_string($voucherIdOrCode)) {
        $voucher = db()->fetchOne('SELECT * FROM vouchers WHERE code = ?', 's', strtoupper($voucherIdOrCode));
    } else {
        $voucher = db()->fetchOne('SELECT * FROM vouchers WHERE id = ?', 'i', $voucherIdOrCode);
    }

    if (!$voucher) return ['valid' => false, 'message' => 'Voucher tidak ditemukan.'];
    if (!$voucher['is_active']) return ['valid' => false, 'message' => 'Voucher tidak aktif.'];
    if ($voucher['type'] !== $type) return ['valid' => false, 'message' => 'Voucher tidak berlaku untuk transaksi ini.'];

    // Check VIP level
    $user = db()->fetchOne('SELECT vip_level FROM users WHERE id = ?', 'i', $userId);
    if ($user['vip_level'] < $voucher['min_vip_level']) {
        return ['valid' => false, 'message' => 'Voucher ini memerlukan VIP ' . $voucher['min_vip_level'] . ' atau lebih tinggi.', 'need_vip' => true];
    }

    // Check validity dates
    if ($voucher['valid_from'] && strtotime($voucher['valid_from']) > time()) {
        return ['valid' => false, 'message' => 'Voucher belum berlaku.'];
    }
    if ($voucher['valid_until'] && strtotime($voucher['valid_until']) < time()) {
        return ['valid' => false, 'message' => 'Voucher sudah expired.'];
    }

    // Check usage limit
    if ($voucher['usage_limit'] !== null && $voucher['used_count'] >= $voucher['usage_limit']) {
        return ['valid' => false, 'message' => 'Voucher sudah habis digunakan.'];
    }

    // Check min amount
    if ($amount < $voucher['min_amount']) {
        return ['valid' => false, 'message' => 'Minimum transaksi ' . formatRupiah($voucher['min_amount'])];
    }

    // Calculate discount
    $discount = $voucher['discount_type'] === 'percent'
        ? ($amount * $voucher['discount_value'] / 100)
        : (float)$voucher['discount_value'];

    if ($voucher['max_discount']) {
        $discount = min($discount, (float)$voucher['max_discount']);
    }

    return ['valid' => true, 'voucher' => $voucher, 'discount' => $discount, 'message' => 'Voucher valid! Diskon ' . formatRupiah($discount)];
}
