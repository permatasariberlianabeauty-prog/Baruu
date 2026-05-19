<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';

// Stats
$totalMembers   = db()->fetchOne("SELECT COUNT(*) as cnt FROM users")['cnt'] ?? 0;
$newMembersToday= db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at)=CURDATE()")['cnt'] ?? 0;
$pendingDeps    = db()->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as total FROM deposits WHERE status='pending'");
$pendingWDs     = db()->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM withdrawals WHERE status='pending'");
$totalDeposited = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE status='confirmed'")['total'] ?? 0;
$totalWithdrawn = db()->fetchOne("SELECT COALESCE(SUM(net_amount),0) as total FROM withdrawals WHERE status='approved'")['total'] ?? 0;
$activePackages = db()->fetchOne("SELECT COUNT(*) as cnt FROM user_products WHERE status='active'")['cnt'] ?? 0;
$todayMining    = db()->fetchOne("SELECT COUNT(*) as cnt FROM mining_logs WHERE DATE(mined_at)=CURDATE()")['cnt'] ?? 0;
$unreadChats    = db()->fetchOne("SELECT SUM(unread_admin) as cnt FROM chat_rooms")['cnt'] ?? 0;

// Revenue chart data (last 7 days)
$chartData = db()->fetchAll(
    "SELECT DATE(confirmed_at) as date, SUM(amount) as total
     FROM deposits WHERE status='confirmed' AND confirmed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(confirmed_at) ORDER BY date",
);

// Recent activity
$recentDeposits  = db()->fetchAll("SELECT d.*, u.full_name, u.username FROM deposits d JOIN users u ON d.user_id=u.id WHERE d.status='pending' ORDER BY d.created_at DESC LIMIT 5");
$recentWithdraws = db()->fetchAll("SELECT w.*, u.full_name FROM withdrawals w JOIN users u ON w.user_id=u.id WHERE w.status='pending' ORDER BY w.created_at DESC LIMIT 5");

adminHeader('Dashboard', 'index');
?>

<!-- Stat Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:24px">
  <?php
  $cards = [
    ['icon'=>'👥','value'=>number_format($totalMembers),'label'=>'Total Member','sub'=>'+' . $newMembersToday . ' hari ini','color'=>'var(--cyan)'],
    ['icon'=>'⏳','value'=>$pendingDeps['cnt'],'label'=>'Deposit Pending','sub'=>formatRupiahShort($pendingDeps['total']),'color'=>'var(--text-warning)'],
    ['icon'=>'💸','value'=>$pendingWDs['cnt'],'label'=>'WD Pending','sub'=>formatRupiahShort($pendingWDs['total']),'color'=>'var(--text-danger)'],
    ['icon'=>'💰','value'=>formatRupiahShort($totalDeposited),'label'=>'Total Masuk','sub'=>'Semua waktu','color'=>'var(--text-success)'],
    ['icon'=>'📤','value'=>formatRupiahShort($totalWithdrawn),'label'=>'Total Keluar','sub'=>'Semua waktu','color'=>'var(--purple)'],
    ['icon'=>'⛏️','value'=>$activePackages,'label'=>'Paket Aktif','sub'=>$todayMining . ' mining hari ini','color'=>'var(--cyan)'],
    ['icon'=>'💬','value'=>$unreadChats,'label'=>'Chat Belum Dibalas','sub'=>'Perlu direspons','color'=>'var(--text-warning)'],
  ];
  foreach ($cards as $c): ?>
  <div class="admin-stat-card">
    <div class="admin-stat-icon" style="background:<?= $c['color'] ?>22">
      <span style="font-size:1.3rem"><?= $c['icon'] ?></span>
    </div>
    <div>
      <div class="admin-stat-value" style="color:<?= $c['color'] ?>"><?= $c['value'] ?></div>
      <div class="admin-stat-label"><?= $c['label'] ?></div>
      <div class="admin-stat-delta" style="color:var(--text-muted)"><?= $c['sub'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<!-- Pending Deposits -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">⏳ Deposit Pending</h3>
    <a href="<?= BASE_URL ?>/admin/deposits.php" class="btn-text-sm">Lihat Semua →</a>
  </div>
  <?php if (!empty($recentDeposits)): ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Member</th><th>Nominal</th><th>Waktu</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($recentDeposits as $d): ?>
        <tr>
          <td><div style="font-weight:600;font-size:.875rem"><?= e($d['full_name']) ?></div><div style="font-size:.72rem;color:var(--text-muted)">@<?= e($d['username']) ?></div></td>
          <td style="font-weight:700;color:var(--cyan)"><?= formatRupiah($d['total_amount']) ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= timeAgo($d['created_at']) ?></td>
          <td><a href="<?= BASE_URL ?>/admin/deposits.php?id=<?= $d['id'] ?>" class="btn-primary btn-sm">Review</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><p>Tidak ada deposit pending</p></div>
  <?php endif; ?>
</div>

<!-- Pending Withdrawals -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">💸 Penarikan Pending</h3>
    <a href="<?= BASE_URL ?>/admin/withdrawals.php" class="btn-text-sm">Lihat Semua →</a>
  </div>
  <?php if (!empty($recentWithdraws)): ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Member</th><th>Nominal</th><th>Diterima</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($recentWithdraws as $w): ?>
        <tr>
          <td style="font-weight:600;font-size:.875rem"><?= e($w['full_name']) ?></td>
          <td style="font-weight:700"><?= formatRupiah($w['amount']) ?></td>
          <td style="color:var(--text-success)"><?= formatRupiah($w['net_amount']) ?></td>
          <td><a href="<?= BASE_URL ?>/admin/withdrawals.php?id=<?= $w['id'] ?>" class="btn-success btn-sm">Proses</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><p>Tidak ada penarikan pending</p></div>
  <?php endif; ?>
</div>
</div>

<!-- Recent 7-day deposit chart (simple CSS bars) -->
<?php if (!empty($chartData)): ?>
<div class="card" style="margin-top:20px">
  <h3 class="card-title" style="margin-bottom:16px">📊 Deposit 7 Hari Terakhir</h3>
  <?php $maxVal = max(array_column($chartData,'total')) ?: 1; ?>
  <div style="display:flex;align-items:flex-end;gap:10px;height:80px;padding-bottom:4px">
    <?php foreach ($chartData as $day): ?>
    <?php $pct = round(($day['total']/$maxVal)*100); ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
      <div style="font-size:.65rem;color:var(--text-muted)"><?= formatRupiahShort($day['total']) ?></div>
      <div style="width:100%;background:var(--gradient);border-radius:4px 4px 0 0;height:<?= max(4,$pct*.65) ?>px"></div>
      <div style="font-size:.65rem;color:var(--text-muted)"><?= date('d/m', strtotime($day['date'])) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php adminFooter(); ?>
