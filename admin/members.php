<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';

$search  = trim($_GET['q']    ?? '');
$page    = getCurrentPage();
$filter  = $_GET['filter'] ?? 'all';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action   = $_POST['action']   ?? '';
    $memberId = (int)($_POST['member_id'] ?? 0);
    $admin    = getSessionAdmin();

    if ($action === 'block' || $action === 'unblock') {
        $val = $action === 'block' ? 1 : 0;
        db()->execute('UPDATE users SET is_blocked = ? WHERE id = ?', 'ii', $val, $memberId);
        if ($val) { db()->execute('DELETE FROM user_sessions WHERE user_id = ?', 'i', $memberId); }
        logAdminAction($admin['id'], $action . '_member', 'user', $memberId);
        setFlash('success', 'Status member diperbarui.');
    }
    if ($action === 'freeze' || $action === 'unfreeze') {
        $val = $action === 'freeze' ? 1 : 0;
        db()->execute('UPDATE users SET is_frozen = ? WHERE id = ?', 'ii', $val, $memberId);
        logAdminAction($admin['id'], $action . '_member', 'user', $memberId);
        setFlash('success', 'Status saldo member diperbarui.');
    }
    if ($action === 'credit_wallet' && $_SESSION['admin_role'] === ROLE_SUPERADMIN) {
        $walletType = $_POST['wallet_type'] ?? WALLET_MAIN;
        $amount     = (float)($_POST['amount'] ?? 0);
        $note       = trim($_POST['note'] ?? '');
        if ($amount > 0) {
            creditWallet($memberId, $walletType, $amount, TRX_ADMIN_CREDIT, 'admin', $admin['id'], $note ?: 'Kredit manual admin');
            logAdminAction($admin['id'], 'credit_wallet', 'user', $memberId, formatRupiah($amount) . ' ke ' . $walletType);
            setFlash('success', 'Saldo berhasil dikreditkan.');
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?q=' . urlencode($search)); exit;
}

// Build query
$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search) {
    $where   .= ' AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = array_merge($params, [$s,$s,$s,$s]);
    $types   .= 'ssss';
}
if ($filter === 'blocked')  { $where .= ' AND u.is_blocked = 1'; }
if ($filter === 'frozen')   { $where .= ' AND u.is_frozen = 1'; }

$total   = db()->fetchOne("SELECT COUNT(*) as cnt FROM users u $where", $types, ...$params)['cnt'] ?? 0;
$pag     = paginate($total, ADMIN_PER_PAGE, $page, BASE_URL . '/admin/members.php?q=' . urlencode($search) . '&filter=' . $filter);
$members = db()->fetchAll(
    "SELECT u.*, w.main_balance, w.profit_balance, w.bonus_balance, w.referral_balance
     FROM users u LEFT JOIN user_wallets w ON u.id = w.user_id
     $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?",
    $types . 'ii', ...[...$params, ADMIN_PER_PAGE, $pag['offset']]
);

// Detail view
$detailMember = null;
$detailWallet = null;
if (isset($_GET['id'])) {
    $mid = (int)$_GET['id'];
    $detailMember = db()->fetchOne('SELECT * FROM users WHERE id = ?', 'i', $mid);
    $detailWallet = $mid ? getUserWallet($mid) : null;
}

adminHeader('Manajemen Member', 'members');
?>

<!-- Search & Filter -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
  <input type="text" id="memberSearch" placeholder="Cari nama, username, email..." class="form-control" style="max-width:320px" value="<?= e($search) ?>" onkeydown="if(event.key==='Enter')filterMembers()">
  <button class="btn-primary btn-sm" onclick="filterMembers()">Cari</button>
  <div style="display:flex;gap:6px">
    <?php foreach (['all'=>'Semua','blocked'=>'Diblokir','frozen'=>'Dibekukan'] as $k=>$l): ?>
    <a href="?filter=<?= $k ?>" class="btn-sm <?= $filter===$k?'btn-primary':'btn-ghost' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <span style="margin-left:auto;color:var(--text-muted);font-size:.85rem"><?= number_format($total) ?> member</span>
</div>

<?php if ($detailMember): ?>
<!-- Member Detail Panel -->
<div class="card" style="margin-bottom:20px;border-color:var(--border-active)">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <h3>Detail: <?= e($detailMember['full_name']) ?></h3>
    <a href="<?= BASE_URL ?>/admin/members.php" class="btn-ghost btn-sm">✕ Tutup</a>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">
    <div><div style="font-size:.75rem;color:var(--text-muted)">Email</div><div><?= e($detailMember['email']) ?></div></div>
    <div><div style="font-size:.75rem;color:var(--text-muted)">HP</div><div><?= e($detailMember['phone']) ?></div></div>
    <div><div style="font-size:.75rem;color:var(--text-muted)">Bergabung</div><div><?= formatDate($detailMember['created_at']) ?></div></div>
    <div><div style="font-size:.75rem;color:var(--text-muted)">VIP</div><?= vipBadge($detailMember['vip_level']) ?></div>
    <div><div style="font-size:.75rem;color:var(--text-muted)">Total Deposit</div><div style="color:var(--cyan);font-weight:700"><?= formatRupiah($detailMember['total_deposit']) ?></div></div>
    <div><div style="font-size:.75rem;color:var(--text-muted)">Referral Code</div><div style="font-family:monospace"><?= e($detailMember['referral_code']) ?></div></div>
  </div>
  <?php if ($detailWallet): ?>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
    <?php foreach (['main_balance'=>'Utama','profit_balance'=>'Profit','bonus_balance'=>'Bonus','referral_balance'=>'Referral'] as $k=>$l): ?>
    <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:10px;text-align:center">
      <div style="font-size:.7rem;color:var(--text-muted)"><?= $l ?></div>
      <div style="font-weight:700"><?= formatRupiah($detailWallet[$k]) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($_SESSION['admin_role'] === ROLE_SUPERADMIN): ?>
  <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= csrfField() ?>
    <input type="hidden" name="action"    value="credit_wallet">
    <input type="hidden" name="member_id" value="<?= $detailMember['id'] ?>">
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label" style="font-size:.75rem">Wallet</label>
      <select name="wallet_type" class="form-control" style="min-width:120px">
        <option value="main">Utama</option><option value="profit">Profit</option><option value="bonus">Bonus</option><option value="referral">Referral</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label" style="font-size:.75rem">Nominal</label>
      <input type="number" name="amount" class="form-control" placeholder="Jumlah" style="width:140px">
    </div>
    <div class="form-group" style="margin-bottom:0;flex:1">
      <label class="form-label" style="font-size:.75rem">Keterangan</label>
      <input type="text" name="note" class="form-control" placeholder="Catatan kredit">
    </div>
    <button type="submit" class="btn-success btn-sm">💰 Kredit Saldo</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Members Table -->
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Member</th><th>VIP</th><th>Total Deposit</th><th>Saldo</th><th>Status</th><th>Bergabung</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($members)): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Tidak ada member ditemukan</td></tr>
        <?php else: ?>
        <?php foreach ($members as $m): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <img src="<?= avatarUrl($m['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover" alt="">
              <div>
                <div style="font-weight:600;font-size:.875rem"><?= e($m['full_name']) ?></div>
                <div style="font-size:.72rem;color:var(--text-muted)">@<?= e($m['username']) ?> · <?= e($m['email']) ?></div>
              </div>
            </div>
          </td>
          <td><?= vipBadge($m['vip_level']) ?></td>
          <td style="color:var(--cyan)"><?= formatRupiah($m['total_deposit']) ?></td>
          <td style="font-size:.82rem"><?= formatRupiahShort($m['main_balance'] ?? 0) ?></td>
          <td>
            <?php if ($m['is_blocked']): ?><span class="badge badge-danger">Diblokir</span>
            <?php elseif ($m['is_frozen']): ?><span class="badge badge-warning">Dibekukan</span>
            <?php else: ?><span class="badge badge-success">Aktif</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($m['created_at']) ?></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
              <a href="?id=<?= $m['id'] ?>" class="btn-ghost btn-sm">Detail</a>
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                <input type="hidden" name="action" value="<?= $m['is_blocked'] ? 'unblock' : 'block' ?>">
                <button type="submit" class="btn-sm <?= $m['is_blocked'] ? 'btn-success' : 'btn-danger' ?>" onclick="return confirm('Yakin?')"><?= $m['is_blocked'] ? 'Unblock' : 'Blokir' ?></button>
              </form>
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                <input type="hidden" name="action" value="<?= $m['is_frozen'] ? 'unfreeze' : 'freeze' ?>">
                <button type="submit" class="btn-sm btn-ghost" onclick="return confirm('Yakin?')"><?= $m['is_frozen'] ? '🔓 Unfreeze' : '🔒 Freeze' ?></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
  <?php if ($pag['has_prev']): ?><a href="<?= $pag['prev_url'] ?>" class="page-btn">‹</a><?php endif; ?>
  <?php foreach ($pag['pages'] as $pg): ?><a href="<?= $pg['url'] ?>" class="page-btn <?= $pg['active']?'active':'' ?>"><?= $pg['page'] ?></a><?php endforeach; ?>
  <?php if ($pag['has_next']): ?><a href="<?= $pag['next_url'] ?>" class="page-btn">›</a><?php endif; ?>
</div>
<?php endif; ?>

<script>
function filterMembers() {
  const q = document.getElementById('memberSearch').value;
  window.location = '?q=' + encodeURIComponent(q);
}
</script>
<?php adminFooter(); ?>
