<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId    = $_SESSION['user_id'];
$hasClaimed = hasDailyClaimToday($userId);
$items     = db()->fetchAll('SELECT * FROM daily_reward_items WHERE is_active = 1 ORDER BY probability DESC');
$history   = db()->fetchAll("SELECT udc.*, dri.name as reward_name, dri.type as reward_type, dri.is_jackpot FROM user_daily_claims udc JOIN daily_reward_items dri ON udc.reward_item_id = dri.id WHERE udc.user_id = ? ORDER BY udc.claimed_at DESC LIMIT 10", 'i', $userId);

// Handle claim via POST (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $result = claimDailyReward($userId);
    if ($result['success']) {
        $_SESSION['last_reward'] = $result['reward'];
        $_SESSION['show_popup'] = 'claim_reward';
        setFlash('success', $result['message']);
    } else {
        setFlash('error', $result['message']);
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$lastReward = $_SESSION['last_reward'] ?? null;
unset($_SESSION['last_reward']);

$totalClaimed = db()->fetchOne("SELECT COALESCE(SUM(reward_value),0) as total FROM user_daily_claims WHERE user_id = ?", 'i', $userId)['total'] ?? 0;
$streak = db()->fetchOne("SELECT COUNT(*) as cnt FROM user_daily_claims WHERE user_id = ? AND claimed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", 'i', $userId)['cnt'] ?? 0;

$pageTitle = 'Hadiah Harian';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Hadiah Harian</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Hadiah Harian</div>
</div>

<!-- Stats row -->
<div class="stats-grid stagger" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon">🔥</div><div class="stat-value"><?= $streak ?></div><div class="stat-label">Streak 7 Hari</div></div>
  <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-value" style="font-size:.9rem"><?= formatRupiahShort($totalClaimed) ?></div><div class="stat-label">Total Klaim</div></div>
  <div class="stat-card"><div class="stat-icon"><?= $hasClaimed ? '✅' : '🎁' ?></div><div class="stat-value" style="font-size:.85rem"><?= $hasClaimed ? 'Sudah' : 'Belum' ?></div><div class="stat-label">Hari Ini</div></div>
</div>

<!-- Main Claim Section -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
<div class="card" style="text-align:center">
  <h3 style="margin-bottom:20px">Klik untuk Buka Hadiah</h3>

  <!-- Gift box animation -->
  <div id="giftBoxWrap" style="margin:0 auto 24px;width:120px;height:120px;display:flex;align-items:center;justify-content:center">
    <?php if ($hasClaimed): ?>
    <div style="font-size:5rem;opacity:.4">📦</div>
    <?php else: ?>
    <div id="giftBox" class="gift-shake" style="font-size:5rem;cursor:pointer" onclick="openGiftBox()">🎁</div>
    <?php endif; ?>
  </div>

  <!-- Reveal area -->
  <div id="rewardReveal" style="display:none;margin-bottom:16px">
    <div id="rewardEmoji" style="font-size:3rem;animation:rewardReveal .6s ease"></div>
    <div id="rewardName"  style="font-weight:800;font-size:1.2rem;margin:8px 0"></div>
    <div id="rewardVal"   style="font-size:1.6rem;font-weight:900;color:var(--text-success)"></div>
  </div>

  <?php if ($hasClaimed): ?>
  <div class="alert alert-success" style="margin-bottom:16px">
    ✅ Sudah klaim hari ini! Kembali besok ya.
    <?php if ($lastReward): ?>
    <div style="margin-top:6px;font-weight:700">Tadi dapat: <?= e($lastReward['name']) ?> (<?= formatRupiah($lastReward['value']) ?>)</div>
    <?php endif; ?>
  </div>
  <button class="btn-ghost btn-block" disabled>Kembali Besok</button>
  <?php else: ?>
  <form method="POST" id="claimForm">
    <?= csrfField() ?>
    <button type="submit" id="claimBtn" class="btn-primary btn-block btn-lg">🎁 Buka Hadiah Sekarang!</button>
  </form>
  <?php endif; ?>

  <p style="color:var(--text-muted);font-size:.8rem;margin-top:12px">Reset setiap pukul 00:00 WIB</p>
</div>

<!-- Reward Items List -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px">Daftar Hadiah</h3>
  <div style="display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto">
    <?php foreach ($items as $item): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--bg-card2);border-radius:var(--radius-sm);border:1px solid <?= $item['is_jackpot'] ? 'rgba(255,215,0,.4)' : 'var(--border)' ?>">
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:1.4rem"><?= $item['is_jackpot'] ? '🎰' : ($item['type'] === 'ad_quota' ? '📺' : ($item['type'] === 'profit_boost' ? '🚀' : '💰')) ?></span>
        <div>
          <div style="font-weight:600;font-size:.875rem"><?= e($item['name']) ?></div>
          <div style="font-size:.72rem;color:var(--text-muted)"><?= $item['type'] === 'balance_bonus' ? ('Saldo ' . ucfirst($item['wallet_type'])) : ucfirst(str_replace('_',' ',$item['type'])) ?></div>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:700;color:<?= $item['is_jackpot'] ? '#FFD700' : 'var(--text-success)' ?>"><?= $item['type'] === 'balance_bonus' ? formatRupiah($item['value']) : $item['value'] . ($item['type']==='profit_boost'?'%':' iklan') ?></div>
        <div style="font-size:.7rem;color:var(--text-muted)"><?= $item['probability'] ?>% peluang</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>

<!-- Claim History -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px">Riwayat Klaim</h3>
  <?php if (!empty($history)): ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Hadiah</th><th>Nilai</th><th>Waktu</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td>
            <span style="margin-right:6px"><?= $h['is_jackpot'] ? '🎰' : '🎁' ?></span>
            <?= e($h['reward_name']) ?>
            <?php if ($h['is_jackpot']): ?><span class="badge badge-warning" style="margin-left:4px">JACKPOT</span><?php endif; ?>
          </td>
          <td style="color:var(--text-success);font-weight:700"><?= formatRupiah($h['reward_value']) ?></td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDatetime($h['claimed_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state"><p>Belum pernah klaim hadiah harian</p></div>
  <?php endif; ?>
</div>

<script>
function openGiftBox() {
  if (document.getElementById('claimBtn')?.disabled) return;
  const box = document.getElementById('giftBox');
  box.classList.remove('gift-shake');
  box.style.animation = 'none';
  setTimeout(() => {
    box.style.fontSize = '6rem';
    box.style.transition = 'all .3s';
    setTimeout(() => { box.textContent = '📦'; }, 200);
    setTimeout(() => { box.textContent = '✨'; }, 400);
    setTimeout(() => { document.getElementById('claimForm')?.submit(); }, 600);
  }, 100);
}

<?php if ($lastReward): ?>
// Show reward reveal animation
document.addEventListener('DOMContentLoaded', () => {
  const wrap  = document.getElementById('rewardReveal');
  const emoji = document.getElementById('rewardEmoji');
  const name  = document.getElementById('rewardName');
  const val   = document.getElementById('rewardVal');

  setTimeout(() => {
    wrap.style.display  = 'block';
    emoji.textContent   = '<?= $lastReward['is_jackpot'] ? '🎰' : '🎁' ?>';
    name.textContent    = '<?= addslashes(e($lastReward['name'])) ?>';
    val.textContent     = '<?= formatRupiah($lastReward['value']) ?>';
    if (typeof dropCoins !== 'undefined') dropCoins(12);
    <?php if ($lastReward['is_jackpot']): ?>if (typeof launchConfetti !== 'undefined') launchConfetti(80);<?php endif; ?>
  }, 300);
});
<?php endif; ?>
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
