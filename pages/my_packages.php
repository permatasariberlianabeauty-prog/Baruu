<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];

// Handle mining action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mine') {
    validateCsrf();
    $pkgId  = (int)($_POST['package_id'] ?? 0);
    $result = doMining($userId, $pkgId);
    if ($result['success']) {
        setFlash('success', $result['message']);
        $_SESSION['show_popup'] = 'mining_start';
    } else {
        setFlash('error', $result['message']);
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$tab = $_GET['tab'] ?? 'active';
$page = getCurrentPage();

// Active packages
$activePackages = getUserActivePackages($userId);

// All packages with pagination
$totalAll = db()->fetchOne('SELECT COUNT(*) as cnt FROM user_products WHERE user_id = ?', 'i', $userId)['cnt'] ?? 0;
$paginationAll = paginate($totalAll, PER_PAGE, $page, BASE_URL . '/pages/my_packages.php?tab=all');
$allPackages   = getUserAllPackages($userId, PER_PAGE, $paginationAll['offset']);

// Stats
$stats = db()->fetchOne("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_cnt, COALESCE(SUM(total_earned),0) as total_earned FROM user_products WHERE user_id = ?", 'i', $userId);

$pageTitle = 'Paket Aktif';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Paket Mining Saya</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Paket Aktif</div>
</div>

<!-- Stats -->
<div class="stats-grid stagger" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-label">Total Paket</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $stats['active_cnt'] ?></div>
    <div class="stat-label">Paket Aktif</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value" style="font-size:.9rem"><?= formatRupiahShort($stats['total_earned']) ?></div>
    <div class="stat-label">Total Earned</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⛏️</div>
    <div class="stat-value"><?= db()->fetchOne("SELECT COALESCE(SUM(mine_count),0) as c FROM user_products WHERE user_id = ?", 'i', $userId)['c'] ?></div>
    <div class="stat-label">Total Mining</div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs-wrapper" data-tabs>
  <div class="tabs">
    <button class="tab-btn <?= $tab === 'active' ? 'active' : '' ?>" data-tab="tab-active">Aktif (<?= $stats['active_cnt'] ?>)</button>
    <button class="tab-btn <?= $tab === 'all'    ? 'active' : '' ?>" data-tab="tab-all">Semua Paket</button>
  </div>

  <!-- Active tab -->
  <div id="tab-active" class="tab-content <?= $tab === 'active' ? 'active' : '' ?>">
    <?php if (empty($activePackages)): ?>
    <div class="empty-state" style="padding:48px">
      <div style="font-size:3rem;margin-bottom:12px">⛏️</div>
      <h3>Belum Ada Paket Aktif</h3>
      <p style="margin:8px 0 16px">Beli paket mining untuk mulai mendapatkan profit setiap hari!</p>
      <a href="<?= BASE_URL ?>/pages/products.php" class="btn-primary">Beli Paket Mining</a>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;padding:16px 0">
      <?php foreach ($activePackages as $pkg):
        $progressPct = round((($pkg['duration_days'] - max(0, $pkg['days_remaining'])) / max($pkg['duration_days'],1)) * 100);
        $canMine     = (bool)$pkg['can_mine'];
        $cdSecs      = $canMine ? 0 : countdownSeconds(date('Y-m-d', strtotime('tomorrow')) . ' 00:00:00');
        $pendingProfit = db()->fetchOne("SELECT COALESCE(SUM(profit_amount),0) as total FROM mining_logs WHERE user_product_id = ? AND status = 'pending'", 'i', $pkg['id'])['total'];
      ?>
      <div class="package-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <div>
            <div style="font-weight:700;font-size:1rem"><?= e($pkg['product_name']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($pkg['category_name']) ?></div>
          </div>
          <div style="text-align:right">
            <span class="badge <?= ($pkg['days_remaining'] <= 3) ? 'badge-warning' : 'badge-info' ?>">
              <?= max(0, $pkg['days_remaining']) ?> hari lagi
            </span>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;font-size:.8rem">
          <div><span style="color:var(--text-muted)">Harga</span><br><strong><?= formatRupiah($pkg['purchase_price']) ?></strong></div>
          <div><span style="color:var(--text-muted)">Profit/Hari</span><br><strong style="color:var(--text-success)"><?= formatRupiah($pkg['profit_per_day']) ?></strong></div>
          <div><span style="color:var(--text-muted)">Total Earned</span><br><strong style="color:var(--cyan)"><?= formatRupiah($pkg['total_earned']) ?></strong></div>
          <div><span style="color:var(--text-muted)">Mining Count</span><br><strong><?= $pkg['mine_count'] ?>x</strong></div>
        </div>

        <?php if ($pendingProfit > 0): ?>
        <div style="background:rgba(0,212,255,.08);border:1px solid var(--border-active);border-radius:var(--radius-sm);padding:6px 10px;font-size:.78rem;margin-bottom:10px;color:var(--cyan)">
          ⏳ Profit pending: <?= formatRupiah($pendingProfit) ?> (masuk dalam 3 jam)
        </div>
        <?php endif; ?>

        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;font-size:.75rem;color:var(--text-muted);margin-bottom:4px">
            <span>Progress Durasi</span><span><?= $progressPct ?>%</span>
          </div>
          <div class="progress-wrap">
            <div class="progress-bar" data-progress="<?= $progressPct ?>" style="width:0%"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted);margin-top:3px">
            <span><?= formatDate($pkg['start_date']) ?></span>
            <span><?= formatDate($pkg['end_date']) ?></span>
          </div>
        </div>

        <?php if ($canMine): ?>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action"     value="mine">
          <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
          <button type="submit" class="btn-mine btn-block">⛏️ Mining Sekarang!</button>
        </form>
        <?php else: ?>
        <div style="text-align:center;padding:8px;background:var(--bg-card2);border-radius:var(--radius-md)">
          <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px">Sudah mining — besok lagi</div>
          <div class="mining-countdown" data-countdown="<?= $cdSecs ?>">--:--:--</div>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- All packages tab -->
  <div id="tab-all" class="tab-content <?= $tab === 'all' ? 'active' : '' ?>">
    <div class="table-wrapper card" style="margin-top:16px;padding:0;overflow:hidden">
      <table>
        <thead><tr><th>Paket</th><th>Harga</th><th>Profit/Hari</th><th>Total Earned</th><th>Mulai</th><th>Selesai</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($allPackages)): ?>
          <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada paket</td></tr>
          <?php else: ?>
          <?php foreach ($allPackages as $pkg):
            $badge = match($pkg['status']) {
              'active'   =>'<span class="badge badge-success">Aktif</span>',
              'completed'=>'<span class="badge badge-info">Selesai</span>',
              default    =>'<span class="badge badge-muted">Dibatalkan</span>',
            };
          ?>
          <tr>
            <td style="font-weight:600"><?= e($pkg['product_name']) ?></td>
            <td><?= formatRupiah($pkg['purchase_price']) ?></td>
            <td style="color:var(--text-success)"><?= formatRupiah($pkg['profit_per_day']) ?></td>
            <td style="color:var(--cyan)"><?= formatRupiah($pkg['total_earned']) ?></td>
            <td style="font-size:.8rem"><?= formatDate($pkg['start_date']) ?></td>
            <td style="font-size:.8rem"><?= formatDate($pkg['end_date']) ?></td>
            <td><?= $badge ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($paginationAll['total_pages'] > 1): ?>
    <div class="pagination">
      <?php if ($paginationAll['has_prev']): ?>
      <a href="<?= $paginationAll['prev_url'] ?>&tab=all" class="page-btn">‹</a>
      <?php endif; ?>
      <?php foreach ($paginationAll['pages'] as $pg): ?>
      <a href="<?= $pg['url'] ?>&tab=all" class="page-btn <?= $pg['active'] ? 'active' : '' ?>"><?= $pg['page'] ?></a>
      <?php endforeach; ?>
      <?php if ($paginationAll['has_next']): ?>
      <a href="<?= $paginationAll['next_url'] ?>&tab=all" class="page-btn">›</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
