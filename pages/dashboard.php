<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId   = $_SESSION['user_id'];
$user     = db()->fetchOne('SELECT * FROM users WHERE id = ?', 'i', $userId);
$wallet   = getUserWallet($userId);
$vipInfo  = getUserVipInfo($userId);

// Stats
$activePackages = db()->fetchOne("SELECT COUNT(*) as cnt FROM user_products WHERE user_id = ? AND status = 'active'", 'i', $userId);
$totalEarned    = db()->fetchOne("SELECT COALESCE(SUM(total_earned),0) as total FROM user_products WHERE user_id = ?", 'i', $userId);
$todayProfit    = db()->fetchOne("SELECT COALESCE(SUM(profit_amount),0) as total FROM mining_logs WHERE user_id = ? AND DATE(mined_at) = CURDATE()", 'i', $userId);
$totalDownlines = db()->fetchOne("SELECT COUNT(*) as cnt FROM referrals WHERE referrer_id = ?", 'i', $userId);

// Recent transactions
$recentTx = db()->fetchAll("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 8", 'i', $userId);

// Active packages (for mining widget)
$packages = getUserActivePackages($userId);

// Announcements
$announcements = db()->fetchAll("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3");

// Has daily reward today?
$hasClaimedReward = hasDailyClaimToday($userId);

// Today ads watched
$adsToday = db()->fetchOne("SELECT COUNT(*) as cnt FROM ad_watches WHERE user_id = ? AND DATE(watched_at) = CURDATE()", 'i', $userId);
$adSettings = db()->fetchOne('SELECT max_per_day FROM ad_settings LIMIT 1');
$adsMax = (int)($adSettings['max_per_day'] ?? 5);

$pageTitle = 'Dashboard';
include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
  <h1>Selamat Datang, <?= e(explode(' ', $user['full_name'])[0]) ?>! 👋</h1>
  <div class="breadcrumb">Dashboard — <?= formatDate(date('Y-m-d H:i:s')) ?></div>
</div>

<!-- Announcements -->
<?php foreach ($announcements as $ann): ?>
<div class="alert alert-info" style="margin-bottom:14px">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="12" y1="8" x2="12.01" y2="8" stroke="currentColor" stroke-width="2"/><line x1="12" y1="12" x2="12" y2="16" stroke="currentColor" stroke-width="1.7"/></svg>
  <div><strong><?= e($ann['title']) ?></strong><br><span style="font-size:.85rem"><?= e(truncate(strip_tags($ann['content']), 120)) ?></span></div>
</div>
<?php endforeach; ?>

<!-- Wallet Cards -->
<div class="wallet-grid wallet-grid-swipe stagger">
  <div class="wallet-card main">
    <div class="wallet-card-label">Saldo Utama</div>
    <div class="wallet-card-amount" data-countup="<?= $wallet['main_balance'] ?>">0</div>
    <div style="display:flex;gap:8px;margin-top:10px">
      <a href="<?= BASE_URL ?>/pages/deposit.php"  class="btn-primary btn-sm">Deposit</a>
      <a href="<?= BASE_URL ?>/pages/withdraw.php" class="btn-ghost btn-sm">Tarik</a>
    </div>
  </div>
  <div class="wallet-card profit">
    <div class="wallet-card-label">Saldo Profit</div>
    <div class="wallet-card-amount" data-countup="<?= $wallet['profit_balance'] ?>">0</div>
    <div style="margin-top:10px"><a href="<?= BASE_URL ?>/pages/withdraw.php?wallet=profit" class="btn-success btn-sm">Tarik Profit</a></div>
  </div>
  <div class="wallet-card bonus">
    <div class="wallet-card-label">Saldo Bonus</div>
    <div class="wallet-card-amount" data-countup="<?= $wallet['bonus_balance'] ?>">0</div>
    <div class="wallet-card-note warn" style="margin-top:6px">⚠️ Hanya untuk beli paket</div>
  </div>
  <div class="wallet-card referral">
    <div class="wallet-card-label">Saldo Referral</div>
    <div class="wallet-card-amount" data-countup="<?= $wallet['referral_balance'] ?>">0</div>
    <div style="margin-top:10px"><a href="<?= BASE_URL ?>/pages/withdraw.php?wallet=referral" class="btn-secondary btn-sm">Tarik</a></div>
  </div>
</div>

<!-- Quick Stats -->
<div class="stats-grid stagger" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-icon">⛏️</div>
    <div class="stat-value" data-countup="<?= $activePackages['cnt'] ?>"><?= $activePackages['cnt'] ?></div>
    <div class="stat-label">Paket Aktif</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value" style="font-size:1rem"><?= formatRupiahShort($totalEarned['total']) ?></div>
    <div class="stat-label">Total Earned</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📈</div>
    <div class="stat-value" style="font-size:1rem"><?= formatRupiahShort($todayProfit['total']) ?></div>
    <div class="stat-label">Profit Hari Ini</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-value" data-countup="<?= $totalDownlines['cnt'] ?>"><?= $totalDownlines['cnt'] ?></div>
    <div class="stat-label">Total Downline</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

<!-- VIP Progress -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Status VIP</h3>
    <a href="<?= BASE_URL ?>/pages/vip.php" class="btn-text-sm">Detail →</a>
  </div>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
    <div style="font-size:2rem">
      <?= ['🥉','🥈','🥇','🏅','💎','👑'][$vipInfo['level']] ?? '🏅' ?>
    </div>
    <div>
      <div style="font-weight:700"><?= vipBadge($vipInfo['level']) ?></div>
      <div style="font-size:.8rem;color:var(--text-muted);margin-top:4px">
        Total Deposit: <?= formatRupiah($vipInfo['total_deposit']) ?>
      </div>
    </div>
  </div>
  <?php if (!$vipInfo['is_max'] && $vipInfo['next']): ?>
  <div class="progress-wrap">
    <div class="progress-bar" data-progress="<?= $vipInfo['progress_percent'] ?>" style="width:0%"></div>
  </div>
  <div class="progress-label">
    <span><?= $vipInfo['progress_percent'] ?>%</span>
    <span>VIP <?= $vipInfo['level']+1 ?> (<?= formatRupiah($vipInfo['next']['min_deposit_required']) ?>)</span>
  </div>
  <?php else: ?>
  <div class="badge badge-success">Level Maksimum 🏆</div>
  <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="card">
  <div class="card-header"><h3 class="card-title">Aksi Cepat</h3></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <a href="<?= BASE_URL ?>/pages/products.php" class="card-sm" style="background:var(--cyan-dim);border:1px solid var(--border-active);text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none">
      <span style="font-size:1.6rem">⛏️</span><span style="font-size:.8rem;font-weight:600;color:var(--cyan)">Beli Paket</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/deposit.php" class="card-sm" style="background:var(--purple-dim);border:1px solid rgba(123,47,255,.3);text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none">
      <span style="font-size:1.6rem">💳</span><span style="font-size:.8rem;font-weight:600;color:var(--purple)">Deposit</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/daily_reward.php" class="card-sm" style="background:rgba(255,184,0,.1);border:1px solid rgba(255,184,0,.2);text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none;position:relative">
      <span style="font-size:1.6rem">🎁</span>
      <span style="font-size:.8rem;font-weight:600;color:var(--text-warning)">Hadiah Harian</span>
      <?php if (!$hasClaimedReward): ?>
      <span style="position:absolute;top:6px;right:6px;width:8px;height:8px;background:var(--text-danger);border-radius:50%"></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/pages/ads.php" class="card-sm" style="background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none">
      <span style="font-size:1.6rem">📺</span>
      <span style="font-size:.8rem;font-weight:600;color:var(--text-success)">Iklan (<?= $adsToday['cnt'] ?>/<?= $adsMax ?>)</span>
    </a>
  </div>
</div>
</div>

<!-- Active Packages Mining Widget -->
<?php if (!empty($packages)): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h3 class="card-title">⛏️ Paket Aktif — Mining Hari Ini</h3>
    <a href="<?= BASE_URL ?>/pages/my_packages.php" class="btn-text-sm">Lihat Semua →</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
    <?php foreach (array_slice($packages, 0, 4) as $pkg):
      $canMine       = (bool)$pkg['can_mine'];
      $daysRemaining = (int)$pkg['days_remaining'];
      $progressPct   = round((($pkg['duration_days'] - $daysRemaining) / max($pkg['duration_days'],1)) * 100);
      $cdSecs        = $canMine ? 0 : countdownSeconds(date('Y-m-d', strtotime('tomorrow')) . ' 00:00:00');
    ?>
    <div class="package-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
        <div>
          <div style="font-weight:700"><?= e($pkg['product_name']) ?></div>
          <div style="font-size:.75rem;color:var(--text-muted)"><?= e($pkg['category_name']) ?></div>
        </div>
        <span class="badge <?= $daysRemaining <= 3 ? 'badge-warning' : 'badge-info' ?>">
          <?= $daysRemaining ?> hari lagi
        </span>
      </div>
      <div style="font-size:.85rem;color:var(--text-secondary);margin-bottom:10px">
        Profit/Hari: <strong style="color:var(--text-success)"><?= formatRupiah($pkg['profit_per_day']) ?></strong>
      </div>
      <div class="progress-wrap" style="margin-bottom:10px">
        <div class="progress-bar" data-progress="<?= $progressPct ?>" style="width:0%"></div>
      </div>
      <?php if ($canMine): ?>
      <button class="btn-mine btn-block" data-package-id="<?= $pkg['id'] ?>">⛏️ Mining Sekarang!</button>
      <?php else: ?>
      <div style="text-align:center">
        <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px">Sudah mining — kembali besok</div>
        <div class="mining-countdown" data-countdown="<?= $cdSecs ?>">--:--:--</div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:32px;margin-bottom:24px">
  <div style="font-size:3rem;margin-bottom:10px">⛏️</div>
  <h3>Belum Ada Paket Aktif</h3>
  <p style="color:var(--text-muted);margin:8px 0 16px">Beli paket mining dan mulai dapat profit setiap hari!</p>
  <a href="<?= BASE_URL ?>/pages/products.php" class="btn-primary">Lihat Produk Mining</a>
</div>
<?php endif; ?>

<!-- Recent Transactions -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Transaksi Terbaru</h3>
    <a href="<?= BASE_URL ?>/pages/history.php" class="btn-text-sm">Lihat Semua →</a>
  </div>
  <?php if (!empty($recentTx)): ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Jenis</th><th>Saldo</th><th>Jumlah</th><th>Waktu</th></tr></thead>
      <tbody>
        <?php foreach ($recentTx as $tx):
          $isCredit = $tx['amount'] > 0;
          $txLabels = [
            'deposit'=>'Deposit','withdraw'=>'Penarikan','buy_package'=>'Beli Paket',
            'mining_profit'=>'Profit Mining','mining_return'=>'Pengembalian Modal',
            'referral_commission'=>'Komisi Referral','bonus_register'=>'Bonus Daftar',
            'daily_reward'=>'Hadiah Harian','mission_reward'=>'Reward Misi',
            'ad_reward'=>'Reward Iklan','withdraw_return'=>'Dana Dikembalikan',
          ];
          $label = $txLabels[$tx['type']] ?? ucwords(str_replace('_',' ',$tx['type']));
          $walletLabels = ['main'=>'Utama','profit'=>'Profit','bonus'=>'Bonus','referral'=>'Referral'];
        ?>
        <tr>
          <td>
            <span style="display:flex;align-items:center;gap:6px">
              <span style="font-size:1rem"><?= $isCredit ? '📈' : '📉' ?></span>
              <?= e($label) ?>
            </span>
          </td>
          <td><span class="badge badge-muted"><?= e($walletLabels[$tx['wallet_type']] ?? $tx['wallet_type']) ?></span></td>
          <td style="font-weight:700;color:<?= $isCredit ? 'var(--text-success)' : 'var(--text-danger)' ?>">
            <?= $isCredit ? '+' : '' ?><?= formatRupiah(abs($tx['amount'])) ?>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= timeAgo($tx['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><p>Belum ada transaksi</p></div>
  <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
