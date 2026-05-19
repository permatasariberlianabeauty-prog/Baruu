<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $type        = trim($_POST['type'] ?? 'deposit_bonus');
        $discountAmt = (float)($_POST['discount_amount'] ?? 0);
        $discountPct = (float)($_POST['discount_percent'] ?? 0);
        $minVip      = (int)($_POST['min_vip_level'] ?? 0);
        $minDeposit  = (float)($_POST['min_deposit'] ?? 0);
        $useLimit    = (int)($_POST['use_limit'] ?? 0);
        $expiresAt   = trim($_POST['expires_at'] ?? '') ?: null;
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code)) { setFlash('error', 'Kode voucher tidak boleh kosong.'); header('Location: ' . $_SERVER['PHP_SELF']); exit; }

        if ($id) {
            db()->execute(
                'UPDATE vouchers SET code=?,type=?,discount_amount=?,discount_percent=?,min_vip_level=?,min_deposit=?,use_limit=?,expires_at=?,is_active=? WHERE id=?',
                'ssddiddsi i', $code, $type, $discountAmt, $discountPct, $minVip, $minDeposit, $useLimit, $expiresAt, $isActive, $id
            );
            logAdminAction($admin['id'], 'edit_voucher', 'voucher', $id, $code);
            setFlash('success', 'Voucher berhasil diperbarui.');
        } else {
            $exists = db()->fetchOne('SELECT id FROM vouchers WHERE code = ?', 's', $code);
            if ($exists) { setFlash('error', 'Kode voucher sudah ada.'); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
            db()->execute(
                'INSERT INTO vouchers (code,type,discount_amount,discount_percent,min_vip_level,min_deposit,use_limit,expires_at,is_active) VALUES (?,?,?,?,?,?,?,?,?)',
                'ssddiidsi', $code, $type, $discountAmt, $discountPct, $minVip, $minDeposit, $useLimit, $expiresAt, $isActive
            );
            logAdminAction($admin['id'], 'add_voucher', 'voucher', db()->lastInsertId(), $code);
            setFlash('success', 'Voucher berhasil ditambahkan.');
        }
    }

    if ($action === 'deactivate') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute('UPDATE vouchers SET is_active = 0 WHERE id = ?', 'i', $id);
        logAdminAction($admin['id'], 'deactivate_voucher', 'voucher', $id);
        setFlash('success', 'Voucher dinonaktifkan.');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute('DELETE FROM vouchers WHERE id = ? AND used_count = 0', 'i', $id);
        setFlash('success', 'Voucher dihapus.');
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// ── Data ──────────────────────────────────────────────────
$editVoucher = isset($_GET['edit']) ? db()->fetchOne('SELECT * FROM vouchers WHERE id = ?', 'i', (int)$_GET['edit']) : null;
$search      = trim($_GET['q'] ?? '');
$filter      = $_GET['filter'] ?? 'all';

$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search) { $where .= ' AND code LIKE ?'; $params[] = '%'.$search.'%'; $types .= 's'; }
if ($filter === 'active')   { $where .= ' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())'; }
if ($filter === 'expired')  { $where .= ' AND (expires_at IS NOT NULL AND expires_at <= NOW())'; }
if ($filter === 'inactive') { $where .= ' AND is_active = 0'; }

$vouchers = db()->fetchAll("SELECT * FROM vouchers $where ORDER BY created_at DESC LIMIT 100", $types, ...$params);

$typeLabels = [
    'deposit_bonus'  => ['💳 Bonus Deposit',  'badge-info'],
    'fee_discount'   => ['💸 Diskon Fee WD',   'badge-purple'],
    'balance_credit' => ['💰 Kredit Saldo',    'badge-success'],
    'vip_upgrade'    => ['🏆 Upgrade VIP',     'badge-warning'],
];

adminHeader('Manajemen Voucher', 'vouchers');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

<!-- Vouchers List -->
<div>
  <!-- Search + Filter -->
  <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center">
    <input type="text" id="voucherSearch" class="form-control" placeholder="Cari kode voucher..." style="max-width:220px;font-size:.85rem" value="<?= e($search) ?>" onkeydown="if(event.key==='Enter')filterVouchers()">
    <button class="btn-ghost btn-sm" onclick="filterVouchers()">🔍</button>
    <?php foreach (['all'=>'Semua','active'=>'Aktif','expired'=>'Expired','inactive'=>'Nonaktif'] as $k=>$l): ?>
    <a href="?filter=<?= $k ?>" class="btn-sm <?= $filter===$k?'btn-primary':'btn-ghost' ?>"><?= $l ?></a>
    <?php endforeach; ?>
    <span style="margin-left:auto;font-size:.82rem;color:var(--text-muted)"><?= count($vouchers) ?> voucher</span>
  </div>

  <div class="card" style="padding:0;overflow:hidden">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Kode</th><th>Tipe</th><th>Diskon</th><th>Min VIP</th><th>Penggunaan</th><th>Exp</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if (empty($vouchers)): ?>
          <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted)">Tidak ada voucher ditemukan</td></tr>
          <?php else: ?>
          <?php foreach ($vouchers as $v): ?>
          <?php
            [$tlbl, $tclr] = $typeLabels[$v['type']] ?? [$v['type'], 'badge-muted'];
            $isExpired = $v['expires_at'] && strtotime($v['expires_at']) < time();
            $statusBadge = !$v['is_active'] ? '<span class="badge badge-muted">Nonaktif</span>'
                : ($isExpired ? '<span class="badge badge-danger">Expired</span>'
                : '<span class="badge badge-success">Aktif</span>');
          ?>
          <tr style="<?= ($editVoucher && $editVoucher['id']==$v['id']) ? 'background:var(--cyan-dim)' : '' ?>">
            <td><code style="font-size:.875rem;color:var(--cyan);font-weight:700"><?= e($v['code']) ?></code></td>
            <td><span class="badge <?= $tclr ?>" style="font-size:.68rem"><?= $tlbl ?></span></td>
            <td style="font-size:.82rem">
              <?php if ($v['discount_amount'] > 0): ?><div style="color:var(--text-success)">+<?= formatRupiah($v['discount_amount']) ?></div><?php endif; ?>
              <?php if ($v['discount_percent'] > 0): ?><div style="color:var(--text-warning)"><?= number_format($v['discount_percent'],1) ?>%</div><?php endif; ?>
            </td>
            <td><?= $v['min_vip_level'] > 0 ? vipBadge($v['min_vip_level']) : '<span style="color:var(--text-muted)">Semua</span>' ?></td>
            <td style="font-size:.82rem">
              <div style="color:var(--text-primary);font-weight:600"><?= number_format($v['used_count'] ?? 0) ?></div>
              <div style="color:var(--text-muted)"><?= $v['use_limit'] ? '/ '.number_format($v['use_limit']) : '/ ∞' ?></div>
            </td>
            <td style="font-size:.75rem;color:var(--text-muted)"><?= $v['expires_at'] ? formatDate($v['expires_at']) : '∞' ?></td>
            <td><?= $statusBadge ?></td>
            <td>
              <a href="?edit=<?= $v['id'] ?>&filter=<?= $filter ?>" class="btn-ghost btn-sm">✏️</a>
              <?php if ($v['is_active']): ?>
              <form method="POST" style="display:inline">
                <?= csrfField() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button type="submit" class="btn-warning btn-sm" style="background:rgba(255,184,0,.15);color:var(--text-warning);border:1px solid rgba(255,184,0,.3)" onclick="return confirm('Nonaktifkan?')">⏸</button>
              </form>
              <?php endif; ?>
              <?php if (($v['used_count'] ?? 0) == 0): ?>
              <form method="POST" style="display:inline">
                <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus voucher?')">🗑</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Form -->
<div class="card" style="position:sticky;top:20px">
  <h3 class="card-title" style="margin-bottom:16px"><?= $editVoucher ? '✏️ Edit Voucher' : '➕ Buat Voucher Baru' ?></h3>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($editVoucher): ?><input type="hidden" name="id" value="<?= $editVoucher['id'] ?>"><?php endif; ?>

    <div class="form-group">
      <label class="form-label">Kode Voucher</label>
      <div style="display:flex;gap:6px">
        <input type="text" name="code" id="voucherCode" class="form-control" value="<?= e($editVoucher['code'] ?? '') ?>" style="text-transform:uppercase;font-family:monospace;font-weight:700" required>
        <button type="button" class="btn-ghost btn-sm" onclick="generateCode()" title="Generate">🎲</button>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Tipe Voucher</label>
      <select name="type" class="form-control">
        <?php foreach ($typeLabels as $k=>[$l,$c]): ?>
        <option value="<?= $k ?>" <?= ($editVoucher['type'] ?? 'deposit_bonus') === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="form-group">
        <label class="form-label">Bonus Nominal (Rp)</label>
        <input type="number" name="discount_amount" class="form-control" value="<?= $editVoucher['discount_amount'] ?? 0 ?>" min="0" step="0.01">
      </div>
      <div class="form-group">
        <label class="form-label">Diskon Persen (%)</label>
        <input type="number" name="discount_percent" class="form-control" value="<?= $editVoucher['discount_percent'] ?? 0 ?>" min="0" max="100" step="0.01">
      </div>
      <div class="form-group">
        <label class="form-label">Min VIP Level</label>
        <select name="min_vip_level" class="form-control">
          <?php for ($i = 0; $i <= 5; $i++): ?>
          <option value="<?= $i ?>" <?= ($editVoucher['min_vip_level'] ?? 0) == $i ? 'selected' : '' ?>>VIP <?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Min Deposit (Rp)</label>
        <input type="number" name="min_deposit" class="form-control" value="<?= $editVoucher['min_deposit'] ?? 0 ?>" min="0">
      </div>
      <div class="form-group">
        <label class="form-label">Batas Pakai (0=∞)</label>
        <input type="number" name="use_limit" class="form-control" value="<?= (int)($editVoucher['use_limit'] ?? 0) ?>" min="0">
      </div>
      <div class="form-group">
        <label class="form-label">Kadaluarsa</label>
        <input type="datetime-local" name="expires_at" class="form-control" value="<?= $editVoucher['expires_at'] ? date('Y-m-d\TH:i', strtotime($editVoucher['expires_at'])) : '' ?>">
      </div>
    </div>

    <div class="form-check" style="margin-bottom:16px">
      <input type="checkbox" name="is_active" id="vActive" <?= (!$editVoucher || $editVoucher['is_active']) ? 'checked' : '' ?>>
      <label for="vActive">Aktif</label>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit" class="btn-primary"><?= $editVoucher ? '💾 Simpan' : '➕ Buat' ?></button>
      <?php if ($editVoucher): ?><a href="<?= BASE_URL ?>/admin/vouchers.php" class="btn-ghost">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

</div>

<script>
function filterVouchers() {
  const q = document.getElementById('voucherSearch').value;
  window.location = '?q=' + encodeURIComponent(q) + '&filter=<?= $filter ?>';
}
function generateCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let code = '';
  for (let i = 0; i < 8; i++) code += chars[Math.floor(Math.random() * chars.length)];
  document.getElementById('voucherCode').value = code;
}
</script>

<?php adminFooter(); ?>
