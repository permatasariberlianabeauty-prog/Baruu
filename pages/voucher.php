<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId    = $_SESSION['user_id'];
$vipLevel  = $_SESSION['vip_level'] ?? 0;

// Check voucher via POST
$checkResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $code = strtoupper(trim($_POST['voucher_code'] ?? ''));
    if ($code) {
        $checkResult = validateVoucher($code, $userId, $_POST['type'] ?? 'deposit', (float)($_POST['amount'] ?? 0));
    }
}

// User's used vouchers
$usedVouchers = db()->fetchAll(
    "SELECT uv.*, v.code, v.type, v.discount_type, v.discount_value FROM user_vouchers uv
     JOIN vouchers v ON uv.voucher_id = v.id WHERE uv.user_id = ? ORDER BY uv.used_at DESC LIMIT 20",
    'i', $userId
);

// Available vouchers for this user's VIP level (publicly listed)
$availVouchers = db()->fetchAll(
    "SELECT * FROM vouchers WHERE is_active = 1 AND min_vip_level <= ?
     AND (valid_until IS NULL OR valid_until > NOW())
     AND (usage_limit IS NULL OR used_count < usage_limit)
     ORDER BY created_at DESC LIMIT 20",
    'i', $vipLevel
);

$pageTitle = 'Voucher';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Voucher</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Voucher</div>
</div>

<!-- Check Voucher Form -->
<div class="card" style="margin-bottom:20px">
  <h3 class="card-title" style="margin-bottom:16px">Cek Voucher</h3>
  <form method="POST">
    <?= csrfField() ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <input type="text" name="voucher_code" class="form-control" placeholder="Masukkan kode voucher" style="flex:1;min-width:160px;text-transform:uppercase" value="<?= e($_POST['voucher_code'] ?? '') ?>">
      <select name="type" class="form-control" style="width:160px">
        <option value="deposit"  <?= ($_POST['type'] ?? '') === 'deposit'  ? 'selected' : '' ?>>Deposit</option>
        <option value="product"  <?= ($_POST['type'] ?? '') === 'product'  ? 'selected' : '' ?>>Beli Paket</option>
      </select>
      <input type="number" name="amount" class="form-control" placeholder="Nominal (opsional)" style="width:180px" value="<?= e($_POST['amount'] ?? '') ?>">
      <button type="submit" class="btn-primary">Cek Voucher</button>
    </div>
  </form>

  <?php if ($checkResult !== null): ?>
  <div class="alert alert-<?= $checkResult['valid'] ? 'success' : 'danger' ?>" style="margin-top:14px">
    <?php if ($checkResult['valid']): ?>
    ✅ <strong>Voucher Valid!</strong> <?= e($checkResult['message']) ?>
    <div style="margin-top:8px;font-size:.85rem">
      Kode: <strong><?= e($checkResult['voucher']['code']) ?></strong> ·
      Tipe: <?= ucfirst($checkResult['voucher']['type']) ?> ·
      Diskon: <strong style="color:var(--text-success)"><?= e($checkResult['voucher']['discount_type'] === 'percent' ? $checkResult['voucher']['discount_value'] . '%' : formatRupiah($checkResult['voucher']['discount_value'])) ?></strong>
    </div>
    <?php else: ?>
    ❌ <?= e($checkResult['message']) ?>
    <?php if (!empty($checkResult['need_vip'])): ?>
    <div style="margin-top:8px"><a href="<?= BASE_URL ?>/pages/vip.php" class="btn-primary btn-sm">Upgrade VIP →</a></div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<!-- Available Vouchers -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:16px">Voucher Tersedia</h3>
  <?php if (!empty($availVouchers)): ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($availVouchers as $v):
      $discLabel = $v['discount_type'] === 'percent' ? $v['discount_value'] . '%' : formatRupiah($v['discount_value']);
    ?>
    <div style="background:var(--bg-card2);border:1px dashed var(--border-active);border-radius:var(--radius-md);padding:14px 16px;display:flex;align-items:center;gap:12px">
      <div style="font-size:2rem">🎫</div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
          <span style="font-family:var(--font-mono);font-weight:700;font-size:1rem;color:var(--cyan);letter-spacing:.08em"><?= e($v['code']) ?></span>
          <button class="copy-btn" onclick="copyText('<?= e($v['code']) ?>',this)">Salin</button>
        </div>
        <div style="font-size:.8rem;color:var(--text-secondary)">
          Diskon <strong style="color:var(--text-success)"><?= $discLabel ?></strong>
          <?php if ($v['min_amount'] > 0): ?> · Min. <?= formatRupiah($v['min_amount']) ?><?php endif; ?>
          <?php if ($v['max_discount']): ?> · Maks. <?= formatRupiah($v['max_discount']) ?><?php endif; ?>
        </div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px">
          <?= ucfirst($v['type']) ?> · VIP min <?= $v['min_vip_level'] ?>
          <?php if ($v['valid_until']): ?> · Berlaku s.d <?= formatDate($v['valid_until']) ?><?php endif; ?>
          <?php if ($v['usage_limit']): ?> · Sisa <?= max(0, $v['usage_limit'] - $v['used_count']) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state"><p>Tidak ada voucher aktif saat ini</p></div>
  <?php endif; ?>
</div>

<!-- Used History -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:16px">Riwayat Penggunaan</h3>
  <?php if (!empty($usedVouchers)): ?>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($usedVouchers as $uv):
      $discLabel = $uv['discount_type'] === 'percent' ? $uv['discount_value'] . '%' : formatRupiah($uv['discount_value']);
    ?>
    <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:10px 12px;opacity:.7">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-family:var(--font-mono);font-weight:700;color:var(--text-muted)"><?= e($uv['code']) ?></span>
        <span class="badge badge-muted">Terpakai</span>
      </div>
      <div style="font-size:.78rem;color:var(--text-muted);margin-top:3px">
        <?= ucfirst($uv['type']) ?> · Diskon <?= $discLabel ?> · <?= timeAgo($uv['used_at']) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state"><p>Belum pernah menggunakan voucher</p></div>
  <?php endif; ?>
</div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
