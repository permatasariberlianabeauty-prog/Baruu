<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$tab    = $_GET['tab']    ?? 'all';
$page   = getCurrentPage();
$search = trim($_GET['q'] ?? '');

// Filter options
$walletFilter = $_GET['wallet'] ?? '';
$typeFilter   = $_GET['type']   ?? '';

$tabs = [
  'all'      => ['label'=>'Semua',       'table'=>'transactions'],
  'deposit'  => ['label'=>'Deposit',     'table'=>'deposits'],
  'withdraw' => ['label'=>'Penarikan',   'table'=>'withdrawals'],
  'mining'   => ['label'=>'Mining',      'table'=>'mining_logs'],
];

// Build query
$where  = 'WHERE t.user_id = ?';
$params = [$userId];
$types  = 'i';

if ($walletFilter) { $where .= ' AND t.wallet_type = ?'; $params[] = $walletFilter; $types .= 's'; }
if ($typeFilter)   { $where .= ' AND t.type = ?';        $params[]  = $typeFilter;  $types .= 's'; }

$totalTx = db()->fetchOne("SELECT COUNT(*) as cnt FROM transactions t $where", $types, ...$params)['cnt'] ?? 0;
$pag     = paginate($totalTx, PER_PAGE, $page, BASE_URL . '/pages/history.php?tab=' . $tab . '&wallet=' . $walletFilter . '&type=' . $typeFilter);

$transactions = db()->fetchAll(
    "SELECT t.* FROM transactions t $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?",
    $types . 'ii', ...[...$params, PER_PAGE, $pag['offset']]
);

// Deposits
$totalDep = db()->fetchOne("SELECT COUNT(*) as cnt FROM deposits WHERE user_id = ?", 'i', $userId)['cnt'] ?? 0;
$pagDep   = paginate($totalDep, PER_PAGE, $page, BASE_URL . '/pages/history.php?tab=deposit');
$deposits = db()->fetchAll("SELECT d.*, ab.bank_name FROM deposits d LEFT JOIN admin_bank_accounts ab ON d.admin_bank_id = ab.id WHERE d.user_id = ? ORDER BY d.created_at DESC LIMIT ? OFFSET ?", 'iii', $userId, PER_PAGE, $pagDep['offset']);

// Withdrawals
$totalWD  = db()->fetchOne("SELECT COUNT(*) as cnt FROM withdrawals WHERE user_id = ?", 'i', $userId)['cnt'] ?? 0;
$pagWD    = paginate($totalWD, PER_PAGE, $page, BASE_URL . '/pages/history.php?tab=withdraw');
$withdrawals = db()->fetchAll("SELECT w.*, ba.bank_name, ba.account_number FROM withdrawals w LEFT JOIN bank_accounts ba ON w.bank_account_id = ba.id WHERE w.user_id = ? ORDER BY w.created_at DESC LIMIT ? OFFSET ?", 'iii', $userId, PER_PAGE, $pagWD['offset']);

// Mining logs
$totalML  = db()->fetchOne("SELECT COUNT(*) as cnt FROM mining_logs WHERE user_id = ?", 'i', $userId)['cnt'] ?? 0;
$pagML    = paginate($totalML, PER_PAGE, $page, BASE_URL . '/pages/history.php?tab=mining');
$miningLogs = db()->fetchAll("SELECT ml.*, up.purchase_price, p.name as product_name FROM mining_logs ml JOIN user_products up ON ml.user_product_id = up.id JOIN products p ON up.product_id = p.id WHERE ml.user_id = ? ORDER BY ml.mined_at DESC LIMIT ? OFFSET ?", 'iii', $userId, PER_PAGE, $pagML['offset']);

$txLabels = [
    'deposit'=>'Deposit','withdraw'=>'Penarikan','buy_package'=>'Beli Paket','mining_profit'=>'Profit Mining',
    'mining_return'=>'Pengembalian Modal','referral_commission'=>'Komisi Referral','bonus_register'=>'Bonus Daftar',
    'daily_reward'=>'Hadiah Harian','mission_reward'=>'Reward Misi','ad_reward'=>'Reward Iklan',
    'withdraw_return'=>'Dana Dikembalikan','admin_credit'=>'Kredit Admin','admin_debit'=>'Debit Admin',
];

$pageTitle = 'Riwayat Transaksi';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Riwayat Transaksi</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Riwayat</div>
</div>

<div class="tabs-wrapper" data-tabs>
  <div class="tabs">
    <?php foreach ($tabs as $k => $t): ?>
    <a href="?tab=<?= $k ?>" class="tab-btn <?= $tab === $k ? 'active' : '' ?>" data-no-transition style="text-decoration:none"><?= $t['label'] ?></a>
    <?php endforeach; ?>
  </div>

  <!-- All Transactions -->
  <?php if ($tab === 'all'): ?>
  <div style="display:flex;gap:10px;margin:16px 0;flex-wrap:wrap">
    <select onchange="this.form?.submit()" class="form-control" style="width:auto" onchange="window.location='?tab=all&wallet='+this.value">
      <option value="">Semua Saldo</option>
      <?php foreach (['main'=>'Utama','profit'=>'Profit','bonus'=>'Bonus','referral'=>'Referral'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $walletFilter === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="card" style="padding:0;overflow:hidden">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Keterangan</th><th>Saldo</th><th>Jumlah</th><th>Saldo Sesudah</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php if (empty($transactions)): ?>
          <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada transaksi</td></tr>
          <?php else: ?>
          <?php foreach ($transactions as $tx):
            $isCredit = $tx['amount'] > 0;
            $label    = $txLabels[$tx['type']] ?? ucwords(str_replace('_',' ',$tx['type']));
            $walletLabels = ['main'=>'Utama','profit'=>'Profit','bonus'=>'Bonus','referral'=>'Referral'];
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:1.2rem"><?= $isCredit ? '📈' : '📉' ?></span>
                <div>
                  <div style="font-weight:600;font-size:.875rem"><?= e($label) ?></div>
                  <?php if ($tx['description']): ?>
                  <div style="font-size:.75rem;color:var(--text-muted)"><?= e(truncate($tx['description'], 50)) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><span class="badge badge-muted"><?= e($walletLabels[$tx['wallet_type']] ?? $tx['wallet_type']) ?></span></td>
            <td style="font-weight:700;color:<?= $isCredit ? 'var(--text-success)' : 'var(--text-danger)' ?>">
              <?= $isCredit ? '+' : '' ?><?= formatRupiah(abs($tx['amount'])) ?>
            </td>
            <td style="color:var(--text-secondary);font-size:.85rem"><?= formatRupiah($tx['balance_after']) ?></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDatetime($tx['created_at']) ?></td>
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
    <?php foreach ($pag['pages'] as $pg): ?>
    <a href="<?= $pg['url'] ?>" class="page-btn <?= $pg['active'] ? 'active' : '' ?>"><?= $pg['page'] ?></a>
    <?php endforeach; ?>
    <?php if ($pag['has_next']): ?><a href="<?= $pag['next_url'] ?>" class="page-btn">›</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Deposits tab -->
  <?php if ($tab === 'deposit'): ?>
  <div class="card" style="margin-top:16px;padding:0;overflow:hidden">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Nominal</th><th>Total Transfer</th><th>Kode Unik</th><th>Bank</th><th>Status</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php if (empty($deposits)): ?>
          <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada deposit</td></tr>
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
            <td style="font-weight:700"><?= formatRupiah($d['amount']) ?></td>
            <td style="color:var(--cyan)"><?= formatRupiah($d['total_amount']) ?></td>
            <td style="font-family:monospace;color:var(--text-warning)">+<?= $d['unique_code'] ?></td>
            <td><?= e($d['bank_name'] ?? '-') ?></td>
            <td><?= $badge ?></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDatetime($d['created_at']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pagDep['total_pages'] > 1): ?>
  <div class="pagination">
    <?php foreach ($pagDep['pages'] as $pg): ?><a href="<?= $pg['url'] ?>" class="page-btn <?= $pg['active'] ? 'active' : '' ?>"><?= $pg['page'] ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Withdrawals tab -->
  <?php if ($tab === 'withdraw'): ?>
  <div class="card" style="margin-top:16px;padding:0;overflow:hidden">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Nominal</th><th>Fee</th><th>Diterima</th><th>Rekening</th><th>Status</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php if (empty($withdrawals)): ?>
          <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada penarikan</td></tr>
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
            <td style="font-weight:700"><?= formatRupiah($w['amount']) ?></td>
            <td style="color:var(--text-warning)"><?= formatRupiah($w['fee']) ?> (<?= $w['fee_percent'] ?>%)</td>
            <td style="color:var(--text-success);font-weight:700"><?= formatRupiah($w['net_amount']) ?></td>
            <td style="font-size:.8rem"><?= e($w['bank_name'] ?? '-') ?> <?= e($w['account_number'] ?? '') ?></td>
            <td><?= $badge ?>
              <?php if ($w['status'] === 'rejected' && $w['rejection_reason']): ?>
              <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px"><?= e(truncate($w['rejection_reason'],40)) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDatetime($w['created_at']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pagWD['total_pages'] > 1): ?>
  <div class="pagination">
    <?php foreach ($pagWD['pages'] as $pg): ?><a href="<?= $pg['url'] ?>" class="page-btn <?= $pg['active'] ? 'active' : '' ?>"><?= $pg['page'] ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Mining logs tab -->
  <?php if ($tab === 'mining'): ?>
  <div class="card" style="margin-top:16px;padding:0;overflow:hidden">
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Paket</th><th>Profit</th><th>Mining At</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($miningLogs)): ?>
          <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada log mining</td></tr>
          <?php else: ?>
          <?php foreach ($miningLogs as $ml): ?>
          <tr>
            <td style="font-weight:600"><?= e($ml['product_name']) ?></td>
            <td style="color:var(--text-success);font-weight:700">+<?= formatRupiah($ml['profit_amount']) ?></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDatetime($ml['mined_at']) ?></td>
            <td><?= $ml['status'] === 'credited' ? '<span class="badge badge-success">Dikreditkan</span>' : '<span class="badge badge-warning">Pending</span>' ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pagML['total_pages'] > 1): ?>
  <div class="pagination">
    <?php foreach ($pagML['pages'] as $pg): ?><a href="<?= $pg['url'] ?>" class="page-btn <?= $pg['active'] ? 'active' : '' ?>"><?= $pg['page'] ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
