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
    $action    = $_POST['action']    ?? '';
    $depositId = (int)($_POST['deposit_id'] ?? 0);
    $admin     = getSessionAdmin();

    if ($action === 'confirm') {
        $result = confirmDeposit($depositId, $admin['id']);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        logAdminAction($admin['id'], 'confirm_deposit', 'deposit', $depositId);
    }
    if ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        $result = rejectDeposit($depositId, $admin['id'], $reason);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        logAdminAction($admin['id'], 'reject_deposit', 'deposit', $depositId, $reason);
    }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$status = $_GET['status'] ?? 'pending';
$page   = getCurrentPage();

$total = db()->fetchOne("SELECT COUNT(*) as cnt FROM deposits WHERE status = ?", 's', $status)['cnt'] ?? 0;
$pag   = paginate($total, ADMIN_PER_PAGE, $page, BASE_URL . '/admin/deposits.php?status=' . $status);
$deposits = db()->fetchAll(
    "SELECT d.*, u.full_name, u.username, ab.bank_name, ab.account_number
     FROM deposits d
     JOIN users u ON d.user_id = u.id
     LEFT JOIN admin_bank_accounts ab ON d.admin_bank_id = ab.id
     WHERE d.status = ? ORDER BY d.created_at DESC LIMIT ? OFFSET ?",
    'sii', $status, ADMIN_PER_PAGE, $pag['offset']
);

$detail = null;
if (isset($_GET['id'])) {
    $detail = db()->fetchOne(
        "SELECT d.*, u.full_name, u.username, u.email, ab.bank_name, ab.account_number, ab.account_name
         FROM deposits d JOIN users u ON d.user_id=u.id LEFT JOIN admin_bank_accounts ab ON d.admin_bank_id=ab.id
         WHERE d.id = ?", 'i', (int)$_GET['id']
    );
}

adminHeader('Manajemen Deposit', 'deposits');
?>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach (['pending'=>'Pending','confirmed'=>'Dikonfirmasi','rejected'=>'Ditolak','expired'=>'Expired'] as $k=>$l): ?>
  <?php $cnt = db()->fetchOne("SELECT COUNT(*) as c FROM deposits WHERE status=?", 's', $k)['c'] ?? 0; ?>
  <a href="?status=<?= $k ?>" class="btn-sm <?= $status===$k?'btn-primary':'btn-ghost' ?>"><?= $l ?> (<?= $cnt ?>)</a>
  <?php endforeach; ?>
</div>

<?php if ($detail): ?>
<div class="card" style="margin-bottom:20px;border-color:var(--border-active)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h3>Detail Deposit #<?= $detail['id'] ?></h3>
    <a href="?status=<?= $status ?>" class="btn-ghost btn-sm">✕</a>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.875rem">
        <?php foreach (['Member'=>$detail['full_name'].' (@'.$detail['username'].')','Email'=>$detail['email'],'Bank Tujuan'=>$detail['bank_name'].' - '.$detail['account_number'],'Nominal'=>formatRupiah($detail['amount']),'Kode Unik'=>'+'. $detail['unique_code'],'Total Transfer'=>formatRupiah($detail['total_amount']),'Status'=>ucfirst($detail['status']),'Waktu'=>formatDatetime($detail['created_at'])] as $k=>$v): ?>
        <div style="display:flex;gap:12px"><span style="color:var(--text-muted);min-width:120px"><?= $k ?></span><strong><?= e($v) ?></strong></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <?php if ($detail['proof_image']): ?>
      <div style="margin-bottom:12px">
        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:6px">Bukti Transfer:</div>
        <a href="<?= UPLOADS_URL ?>/deposits/<?= e($detail['proof_image']) ?>" target="_blank">
          <img src="<?= UPLOADS_URL ?>/deposits/<?= e($detail['proof_image']) ?>" style="max-width:100%;max-height:200px;border-radius:var(--radius-md);border:1px solid var(--border)">
        </a>
      </div>
      <?php else: ?>
      <div class="alert alert-warning">Belum ada bukti transfer</div>
      <?php endif; ?>

      <?php if ($detail['status'] === 'pending'): ?>
      <div style="display:flex;gap:10px;margin-top:12px">
        <form method="POST" style="flex:1">
          <?= csrfField() ?>
          <input type="hidden" name="action"     value="confirm">
          <input type="hidden" name="deposit_id" value="<?= $detail['id'] ?>">
          <button type="submit" class="btn-success btn-block" onclick="return confirm('Konfirmasi deposit ini?')">✅ Konfirmasi</button>
        </form>
        <form method="POST" style="flex:1">
          <?= csrfField() ?>
          <input type="hidden" name="action"     value="reject">
          <input type="hidden" name="deposit_id" value="<?= $detail['id'] ?>">
          <div class="form-group" style="margin-bottom:6px">
            <input type="text" name="reason" class="form-control" placeholder="Alasan penolakan (opsional)">
          </div>
          <button type="submit" class="btn-danger btn-block" onclick="return confirm('Tolak deposit ini?')">❌ Tolak</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Member</th><th>Nominal</th><th>Bank</th><th>Bukti</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($deposits)): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Tidak ada data</td></tr>
        <?php else: ?>
        <?php foreach ($deposits as $d):
          $badge = match($d['status']) {
            'confirmed'=>'<span class="badge badge-success">Dikonfirmasi</span>',
            'rejected' =>'<span class="badge badge-danger">Ditolak</span>',
            'expired'  =>'<span class="badge badge-muted">Expired</span>',
            default    =>'<span class="badge badge-warning">Pending</span>',
          };
        ?>
        <tr>
          <td><div style="font-weight:600"><?= e($d['full_name']) ?></div><div style="font-size:.72rem;color:var(--text-muted)">@<?= e($d['username']) ?></div></td>
          <td style="font-weight:700;color:var(--cyan)"><?= formatRupiah($d['total_amount']) ?></td>
          <td style="font-size:.82rem"><?= e($d['bank_name'] ?? '-') ?></td>
          <td><?= $d['proof_image'] ? '<span class="badge badge-success">Ada</span>' : '<span class="badge badge-danger">Belum</span>' ?></td>
          <td><?= $badge ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= timeAgo($d['created_at']) ?></td>
          <td>
            <a href="?id=<?= $d['id'] ?>&status=<?= $status ?>" class="btn-ghost btn-sm">Detail</a>
            <?php if ($d['status'] === 'pending'): ?>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="confirm"><input type="hidden" name="deposit_id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn-success btn-sm" onclick="return confirm('Konfirmasi?')">✅</button>
            </form>
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
