<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $wdId   = (int)($_POST['wd_id'] ?? 0);
    $admin  = getSessionAdmin();

    if ($action === 'approve') {
        $result = approveWithdraw($wdId, $admin['id']);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        logAdminAction($admin['id'], 'approve_withdraw', 'withdrawal', $wdId);
    }
    if ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        $result = rejectWithdraw($wdId, $admin['id'], $reason);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        logAdminAction($admin['id'], 'reject_withdraw', 'withdrawal', $wdId, $reason);
    }
    if ($action === 'processing') {
        db()->execute("UPDATE withdrawals SET status='processing' WHERE id=?", 'i', $wdId);
        logAdminAction($admin['id'], 'processing_withdraw', 'withdrawal', $wdId);
        setFlash('success', 'Status diubah ke Processing.');
    }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$status = $_GET['status'] ?? 'pending';
$page   = getCurrentPage();

$total = db()->fetchOne("SELECT COUNT(*) as cnt FROM withdrawals WHERE status=?", 's', $status)['cnt'] ?? 0;
$pag   = paginate($total, ADMIN_PER_PAGE, $page, BASE_URL . '/admin/withdrawals.php?status=' . $status);
$withdrawals = db()->fetchAll(
    "SELECT w.*, u.full_name, u.username, u.phone, ba.bank_name, ba.account_number, ba.account_name
     FROM withdrawals w
     JOIN users u ON w.user_id = u.id
     LEFT JOIN bank_accounts ba ON w.bank_account_id = ba.id
     WHERE w.status = ? ORDER BY w.created_at ASC LIMIT ? OFFSET ?",
    'sii', $status, ADMIN_PER_PAGE, $pag['offset']
);

$walletLabels = ['main'=>'Utama','profit'=>'Profit','referral'=>'Referral'];

adminHeader('Manajemen Penarikan', 'withdrawals');
?>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach (['pending'=>'Pending','processing'=>'Diproses','approved'=>'Disetujui','rejected'=>'Ditolak'] as $k=>$l): ?>
  <?php $cnt = db()->fetchOne("SELECT COUNT(*) as c FROM withdrawals WHERE status=?", 's', $k)['c'] ?? 0; ?>
  <a href="?status=<?= $k ?>" class="btn-sm <?= $status===$k?'btn-primary':'btn-ghost' ?>"><?= $l ?> (<?= $cnt ?>)</a>
  <?php endforeach; ?>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Member</th><th>Nominal</th><th>Fee</th><th>Diterima</th><th>Rekening</th><th>Saldo</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($withdrawals)): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">Tidak ada data</td></tr>
        <?php else: ?>
        <?php foreach ($withdrawals as $w):
          $badge = match($w['status']) {
            'approved'  =>'<span class="badge badge-success">Disetujui</span>',
            'rejected'  =>'<span class="badge badge-danger">Ditolak</span>',
            'processing'=>'<span class="badge badge-info">Diproses</span>',
            default     =>'<span class="badge badge-warning">Pending</span>',
          };
        ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= e($w['full_name']) ?></div>
            <div style="font-size:.72rem;color:var(--text-muted)">@<?= e($w['username']) ?></div>
            <div style="font-size:.72rem;color:var(--text-muted)"><?= e($w['phone']) ?></div>
          </td>
          <td style="font-weight:700"><?= formatRupiah($w['amount']) ?></td>
          <td style="color:var(--text-warning)"><?= formatRupiah($w['fee']) ?> (<?= $w['fee_percent'] ?>%)</td>
          <td style="color:var(--text-success);font-weight:700"><?= formatRupiah($w['net_amount']) ?></td>
          <td style="font-size:.82rem">
            <div><?= e($w['bank_name'] ?? '-') ?></div>
            <div style="font-family:monospace;color:var(--cyan)"><?= e($w['account_number'] ?? '') ?></div>
            <div style="color:var(--text-muted)"><?= e($w['account_name'] ?? '') ?></div>
          </td>
          <td><span class="badge badge-muted"><?= $walletLabels[$w['wallet_type']] ?? $w['wallet_type'] ?></span></td>
          <td><?= $badge ?><?php if($w['rejection_reason']): ?><div style="font-size:.7rem;color:var(--text-muted);margin-top:2px"><?= e(truncate($w['rejection_reason'],30)) ?></div><?php endif; ?></td>
          <td>
            <?php if ($w['status'] === 'pending'): ?>
            <form method="POST" style="display:flex;flex-direction:column;gap:4px">
              <?= csrfField() ?>
              <input type="hidden" name="wd_id" value="<?= $w['id'] ?>">
              <input type="hidden" name="action" value="processing">
              <button type="submit" class="btn-info btn-sm">📋 Proses</button>
            </form>
            <?php elseif ($w['status'] === 'processing'): ?>
            <div style="display:flex;flex-direction:column;gap:4px">
              <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="wd_id" value="<?= $w['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn-success btn-sm" onclick="return confirm('Transfer sudah dilakukan?')">✅ Setujui</button>
              </form>
              <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="wd_id" value="<?= $w['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="text" name="reason" class="form-control" style="font-size:.72rem;min-height:32px;margin-bottom:3px" placeholder="Alasan tolak">
                <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Tolak & kembalikan dana?')">❌ Tolak</button>
              </form>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
  <?php foreach ($pag['pages'] as $pg): ?><a href="<?= $pg['url'] ?>" class="page-btn <?= $pg['active']?'active':'' ?>"><?= $pg['page'] ?></a><?php endforeach; ?>
</div>
<?php endif; ?>
<?php adminFooter(); ?>
