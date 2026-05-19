<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId  = $_SESSION['user_id'];
$user    = db()->fetchOne('SELECT * FROM users WHERE id = ?', 'i', $userId);
$wallet  = getUserWallet($userId);
$vipInfo = getWithdrawVipRules($userId);
$banks   = db()->fetchAll('SELECT * FROM bank_accounts WHERE user_id = ? ORDER BY is_primary DESC', 'i', $userId);
$recentWD= db()->fetchAll("SELECT w.*, ba.bank_name, ba.account_number, ba.account_name FROM withdrawals w LEFT JOIN bank_accounts ba ON w.bank_account_id = ba.id WHERE w.user_id = ? ORDER BY w.created_at DESC LIMIT 10", 'i', $userId);

$walletType = $_GET['wallet'] ?? 'main';
if (!in_array($walletType, ['main','profit','referral'])) $walletType = 'main';

$withdrawEnabled = getSetting('withdraw_enabled', '1');
$startHour = (int)getSetting('withdraw_start_hour', 8);
$endHour   = (int)getSetting('withdraw_end_hour', 21);
$currHour  = (int)date('G');
$isOperational = $currHour >= $startHour && $currHour < $endHour;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $amount   = (float)preg_replace('/\D/', '', $_POST['amount'] ?? '0');
    $bankId   = (int)($_POST['bank_account_id'] ?? 0);
    $wType    = $_POST['wallet_type'] ?? 'main';
    $pin      = $_POST['pin'] ?? '';

    if (empty($user['pin'])) { setFlash('error', 'Anda belum mengatur PIN transaksi. Silakan buat PIN terlebih dahulu.'); }
    else {
        $result = createWithdraw($userId, $amount, $wType, $bankId, $pin);
        if ($result['success']) { setFlash('success', $result['message']); $_SESSION['show_popup'] = 'withdraw_submit'; }
        else { setFlash('error', $result['message']); }
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$pageTitle = 'Penarikan Dana';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Penarikan Dana</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Penarikan</div>
</div>

<?php if (!$withdrawEnabled): ?>
<div class="alert alert-warning">Fitur penarikan sedang dinonaktifkan sementara.</div>
<?php elseif (!$isOperational): ?>
<div class="alert alert-warning">⏰ Jam operasional penarikan: <strong><?= $startHour ?>:00 — <?= $endHour ?>:00 WIB</strong>. Saat ini di luar jam operasional.</div>
<?php elseif (empty($banks)): ?>
<div class="alert alert-warning" style="display:flex;align-items:center;justify-content:space-between">
  <span>⚠️ Belum ada rekening bank terdaftar. Tambahkan rekening untuk melakukan penarikan.</span>
  <a href="<?= BASE_URL ?>/pages/bank_account.php" class="btn-primary btn-sm">Tambah Rekening</a>
</div>
<?php elseif (empty($user['pin'])): ?>
<div class="alert alert-warning" style="display:flex;align-items:center;justify-content:space-between">
  <span>🔐 Anda belum mengatur PIN transaksi.</span>
  <a href="<?= BASE_URL ?>/pages/security.php" class="btn-primary btn-sm">Buat PIN</a>
</div>
<?php endif; ?>

<!-- VIP Rules Info -->
<div class="card" style="margin-bottom:20px;padding:14px 18px">
  <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:center">
    <div>
      <?= vipBadge($_SESSION['vip_level'] ?? 0) ?>
      <span style="font-size:.8rem;color:var(--text-muted);margin-left:8px">Level VIP Anda</span>
    </div>
    <div style="font-size:.85rem"><span style="color:var(--text-muted)">Min WD:</span> <strong><?= formatRupiah($vipInfo['min_withdraw'] ?? 100000) ?></strong></div>
    <div style="font-size:.85rem"><span style="color:var(--text-muted)">Fee:</span> <strong style="color:var(--text-warning)"><?= $vipInfo['withdraw_fee_percent'] ?? 15 ?>%</strong></div>
    <div style="font-size:.85rem"><span style="color:var(--text-muted)">Maks/hari:</span> <strong><?= $vipInfo['max_withdraw_per_day'] ?? 1 ?>x</strong></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="card">
  <h3 class="card-title" style="margin-bottom:20px">Form Penarikan</h3>
  <form method="POST" data-loading id="wdForm">
    <?= csrfField() ?>

    <div class="form-group">
      <label class="form-label">Pilih Saldo</label>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
        <?php
        $wTypes = [
          'main'     => ['label'=>'Saldo Utama', 'val'=> $wallet['main_balance']],
          'profit'   => ['label'=>'Saldo Profit', 'val'=> $wallet['profit_balance']],
          'referral' => ['label'=>'Saldo Referral','val'=> $wallet['referral_balance']],
        ];
        foreach ($wTypes as $wk => $wv):
        ?>
        <label style="cursor:pointer">
          <input type="radio" name="wallet_type" value="<?= $wk ?>" <?= $walletType === $wk ? 'checked' : '' ?> style="display:none" class="wallet-radio">
          <div class="wallet-select-btn card-sm" style="text-align:center;border:2px solid <?= $walletType === $wk ? 'var(--cyan)' : 'var(--border)' ?>;cursor:pointer;transition:var(--transition)" data-wallet="<?= $wk ?>">
            <div style="font-size:.7rem;color:var(--text-muted)"><?= $wv['label'] ?></div>
            <div style="font-weight:700;font-size:.85rem"><?= formatRupiahShort($wv['val']) ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Nominal Penarikan <span class="required">*</span></label>
      <div class="input-group">
        <span class="input-group-prepend" style="font-size:.85rem;font-weight:600;width:50px">Rp</span>
        <input type="number" name="amount" id="wdAmount" class="form-control" style="padding-left:54px" placeholder="Minimal <?= formatRupiah($vipInfo['min_withdraw'] ?? 100000) ?>" min="<?= $vipInfo['min_withdraw'] ?? 100000 ?>" required>
      </div>
      <div id="wdCalc" class="form-hint"></div>
    </div>

    <div class="form-group">
      <label class="form-label">Rekening Tujuan <span class="required">*</span></label>
      <select name="bank_account_id" class="form-control" required>
        <option value="">-- Pilih Rekening --</option>
        <?php foreach ($banks as $b): ?>
        <option value="<?= $b['id'] ?>"><?= e($b['bank_name']) ?> — <?= e($b['account_number']) ?> (<?= e($b['account_name']) ?>) <?= $b['is_primary'] ? '[Utama]' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">PIN Transaksi <span class="required">*</span></label>
      <div class="pin-input-group" style="display:flex;gap:8px;justify-content:center">
        <?php for($i=0;$i<6;$i++): ?>
        <input type="password" maxlength="1" class="form-control" style="width:48px;height:48px;text-align:center;font-size:1.2rem;font-weight:700;padding:0" inputmode="numeric" pattern="[0-9]">
        <?php endfor; ?>
        <input type="hidden" name="pin" id="pinValue">
      </div>
    </div>

    <div class="alert alert-warning" style="font-size:.85rem;margin-bottom:16px">
      ⚠️ Nama rekening harus sama dengan nama akun NOXARA Anda. Dana tidak dapat dikembalikan jika rekening salah.
    </div>

    <button type="submit" class="btn-primary btn-block" <?= (!$withdrawEnabled || !$isOperational || empty($banks) || empty($user['pin'])) ? 'disabled' : '' ?>>Ajukan Penarikan</button>
  </form>
</div>

<div>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3 class="card-title">Riwayat Penarikan</h3></div>
    <?php if (!empty($recentWD)): ?>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Nominal</th><th>Diterima</th><th>Bank</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentWD as $w):
            $badge = match($w['status']) {
              'approved'  =>'<span class="badge badge-success">Disetujui</span>',
              'rejected'  =>'<span class="badge badge-danger">Ditolak</span>',
              'processing'=>'<span class="badge badge-info">Diproses</span>',
              default     =>'<span class="badge badge-warning">Pending</span>',
            };
          ?>
          <tr>
            <td style="font-weight:700"><?= formatRupiah($w['amount']) ?></td>
            <td style="color:var(--text-success)"><?= formatRupiah($w['net_amount']) ?></td>
            <td style="font-size:.8rem"><?= e($w['bank_name'] ?? '-') ?></td>
            <td><?= $badge ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>Belum ada riwayat penarikan</p></div>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
// Wallet selector visual
document.querySelectorAll('.wallet-radio').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.wallet-select-btn').forEach(b => b.style.borderColor = 'var(--border)');
    radio.nextElementSibling.style.borderColor = 'var(--cyan)';
  });
});

// Fee calculator
const feePercent = <?= (float)($vipInfo['withdraw_fee_percent'] ?? 15) ?>;
document.getElementById('wdAmount')?.addEventListener('input', function() {
  const val = parseFloat(this.value) || 0;
  if (val > 0) {
    const fee = val * feePercent / 100;
    const net = val - fee;
    document.getElementById('wdCalc').innerHTML =
      `Fee <strong style="color:var(--text-warning)">${feePercent}%</strong> = <strong>Rp${fee.toLocaleString('id-ID')}</strong> &nbsp;|&nbsp; Diterima: <strong style="color:var(--text-success)">Rp${net.toLocaleString('id-ID')}</strong>`;
  }
});

// PIN collector
const pinBoxes = document.querySelectorAll('.pin-input-group input[type=password]');
const pinField = document.getElementById('pinValue');
pinBoxes.forEach((box, i) => {
  box.addEventListener('input', () => {
    const pin = [...pinBoxes].map(b => b.value).join('');
    pinField.value = pin;
  });
});

// Form confirm
document.getElementById('wdForm')?.addEventListener('submit', function(e) {
  const amount = document.getElementById('wdAmount').value;
  const pin    = document.getElementById('pinValue').value;
  if (pin.length !== 6) { e.preventDefault(); alert('PIN harus 6 digit!'); return; }
  if (!confirm(`Konfirmasi penarikan Rp${parseInt(amount).toLocaleString('id-ID')}?`)) e.preventDefault();
});
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
