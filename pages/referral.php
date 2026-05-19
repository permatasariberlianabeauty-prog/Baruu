<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId   = $_SESSION['user_id'];
$stats    = getUserReferralStats($userId);
$refCode  = getUserReferralCode($userId);
$refLink  = BASE_URL . '/auth/register.php?ref=' . $refCode;

$level    = (int)($_GET['level'] ?? 1);
if ($level < 1 || $level > 3) $level = 1;

$downlines    = getUserDownlines($userId, $level);
$commSettings = db()->fetchAll('SELECT * FROM commission_settings ORDER BY type, level');
$wallet       = getUserWallet($userId);

// Recent commissions
$recentComm = db()->fetchAll(
    "SELECT c.*, u.full_name as from_name FROM commissions c
     JOIN users u ON c.from_user_id = u.id
     WHERE c.user_id = ? ORDER BY c.created_at DESC LIMIT 10",
    'i', $userId
);

// Tree data (3 levels)
$tree = getReferralTree($userId);

$pageTitle = 'Referral';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Program Referral</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Referral</div>
</div>

<!-- Referral Link Card -->
<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,var(--cyan-dim),var(--purple-dim));border-color:var(--border-active)">
  <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px">
    <div>
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:4px">Link Referral Anda</div>
      <div style="font-weight:700;font-size:1rem;color:var(--cyan);word-break:break-all"><?= e($refLink) ?></div>
      <div style="font-size:.8rem;margin-top:4px;color:var(--text-secondary)">Kode: <strong><?= e($refCode) ?></strong></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn-primary btn-sm copy-btn" data-copy="<?= e($refLink) ?>" onclick="copyText('<?= e($refLink) ?>',this)">Salin Link</button>
      <a href="https://wa.me/?text=<?= urlencode('Bergabung di NOXARA dan mulai investasi! ' . $refLink) ?>" target="_blank" class="btn-success btn-sm">Share WA</a>
    </div>
  </div>
</div>

<!-- Stats Row -->
<div class="stats-grid stagger" style="margin-bottom:20px">
  <?php
  $levels = [
    ['label'=>'Downline L1','count'=>$stats['level1']['count'],'comm'=>$stats['level1']['commission'],'color'=>'var(--cyan)'],
    ['label'=>'Downline L2','count'=>$stats['level2']['count'],'comm'=>$stats['level2']['commission'],'color'=>'var(--purple)'],
    ['label'=>'Downline L3','count'=>$stats['level3']['count'],'comm'=>$stats['level3']['commission'],'color'=>'var(--text-warning)'],
    ['label'=>'Total Komisi','count'=>null,'comm'=>$stats['total_commission'],'color'=>'var(--text-success)'],
  ];
  foreach ($levels as $lv): ?>
  <div class="stat-card">
    <?php if ($lv['count'] !== null): ?>
    <div class="stat-value" style="color:<?= $lv['color'] ?>"><?= $lv['count'] ?></div>
    <?php endif; ?>
    <div style="font-size:.85rem;font-weight:700;color:<?= $lv['color'] ?>"><?= formatRupiah($lv['comm']) ?></div>
    <div class="stat-label"><?= $lv['label'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Saldo Referral -->
<div class="card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <div style="font-size:.8rem;color:var(--text-muted)">Saldo Referral</div>
    <div style="font-size:1.6rem;font-weight:800;color:var(--text-success)"><?= formatRupiah($wallet['referral_balance']) ?></div>
  </div>
  <a href="<?= BASE_URL ?>/pages/withdraw.php?wallet=referral" class="btn-primary">Tarik Saldo Referral</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

<!-- Commission Rate Table -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px">Persentase Komisi</h3>
  <table style="width:100%;font-size:.875rem">
    <thead><tr style="background:var(--bg-card2)">
      <th style="padding:8px 12px;text-align:left;color:var(--text-muted)">Level</th>
      <th style="padding:8px 12px;text-align:left;color:var(--text-muted)">Rabat Deposit</th>
      <th style="padding:8px 12px;text-align:left;color:var(--text-muted)">Rabat Paket</th>
    </tr></thead>
    <tbody>
      <?php
      $depRates = array_values(array_filter($commSettings, fn($c) => $c['type'] === 'deposit'));
      $proRates = array_values(array_filter($commSettings, fn($c) => $c['type'] === 'product'));
      $lColors  = ['var(--cyan)','var(--purple)','var(--text-warning)'];
      for ($i = 0; $i < 3; $i++): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:10px 12px;font-weight:700;color:<?= $lColors[$i] ?>">Level <?= $i+1 ?></td>
        <td style="padding:10px 12px;color:var(--text-success);font-weight:600"><?= $depRates[$i]['percent'] ?? 0 ?>%</td>
        <td style="padding:10px 12px;color:var(--text-success);font-weight:600"><?= $proRates[$i]['percent'] ?? 0 ?>%</td>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>

<!-- Recent Commissions -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px">Komisi Terbaru</h3>
  <?php if (!empty($recentComm)): ?>
  <div style="display:flex;flex-direction:column;gap:8px;max-height:260px;overflow-y:auto">
    <?php foreach ($recentComm as $c): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--bg-card2);border-radius:var(--radius-sm);font-size:.82rem">
      <div>
        <div style="font-weight:600"><?= e(maskName($c['from_name'])) ?></div>
        <div style="color:var(--text-muted);font-size:.72rem">L<?= $c['level'] ?> · <?= ucfirst($c['type']) ?> · <?= timeAgo($c['created_at']) ?></div>
      </div>
      <div style="color:var(--text-success);font-weight:700">+<?= formatRupiah($c['amount']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state" style="padding:24px"><p>Belum ada komisi</p></div>
  <?php endif; ?>
</div>
</div>

<!-- Downline List -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h3 class="card-title">Daftar Downline</h3>
    <div style="display:flex;gap:6px">
      <?php for ($i = 1; $i <= 3; $i++): ?>
      <a href="?level=<?= $i ?>" class="btn-sm <?= $level === $i ? 'btn-primary' : 'btn-ghost' ?>">Level <?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php if (!empty($downlines)): ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Nama</th><th>Username</th><th>VIP</th><th>Total Deposit</th><th>Status</th><th>Bergabung</th></tr></thead>
      <tbody>
        <?php foreach ($downlines as $dl): ?>
        <tr>
          <td style="font-weight:600"><?= e(maskName($dl['full_name'])) ?></td>
          <td style="color:var(--text-muted)">@<?= e($dl['username']) ?></td>
          <td><?= vipBadge($dl['vip_level']) ?></td>
          <td style="color:var(--cyan)"><?= formatRupiah($dl['total_deposit'] ?? 0) ?></td>
          <td>
            <span class="badge <?= $dl['deposit_status'] === 'aktif' ? 'badge-success' : 'badge-muted' ?>">
              <?= $dl['deposit_status'] === 'aktif' ? 'Aktif' : 'Non-aktif' ?>
            </span>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($dl['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state" style="padding:32px">
    <div style="font-size:2.5rem;margin-bottom:10px">👥</div>
    <p>Belum ada downline Level <?= $level ?></p>
    <a href="<?= BASE_URL ?>/auth/register.php?ref=<?= $refCode ?>" class="btn-primary btn-sm" style="margin-top:12px">Bagikan Link Referral</a>
  </div>
  <?php endif; ?>
</div>

<!-- Referral Tree -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:20px">Pohon Referral</h3>
  <?php if (!empty($tree)): ?>
  <div class="ref-tree-wrap">
    <div class="ref-tree">
      <!-- Root -->
      <div style="background:var(--gradient);border-radius:var(--radius-md);padding:10px 20px;font-weight:700;color:#fff;display:inline-block">
        Anda (<?= e($_SESSION['full_name'] ?? '') ?>)
      </div>
      <!-- Level 1 -->
      <div>
        <div class="ref-level-label">Level 1 — <?= count($tree) ?> orang</div>
        <div class="ref-nodes">
          <?php foreach (array_slice($tree, 0, 8) as $l1Node): ?>
          <div class="ref-node" title="<?= e($l1Node['full_name']) ?>">
            <div class="ref-node-name"><?= e(maskName($l1Node['full_name'])) ?></div>
            <div class="ref-node-status aktif">L1</div>
            <?php if (!empty($l1Node['children'])): ?>
            <div style="font-size:.65rem;color:var(--cyan);margin-top:3px">↳ <?= count($l1Node['children']) ?> L2</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if (count($tree) > 8): ?>
          <div class="ref-node" style="background:var(--cyan-dim)"><div class="ref-node-name" style="color:var(--cyan)">+<?= count($tree)-8 ?> lagi</div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="empty-state"><p>Belum ada referral. Bagikan link Anda!</p></div>
  <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
