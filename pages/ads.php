<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId     = $_SESSION['user_id'];
$adSettings = db()->fetchOne('SELECT * FROM ad_settings LIMIT 1');
$maxPerDay  = (int)($adSettings['max_per_day']       ?? 5);
$cooldown   = (int)($adSettings['cooldown_minutes']  ?? 10);
$enabled    = (bool)($adSettings['is_enabled']       ?? true);
$adsEnabled = getSetting('ads_enabled', '1');

// Today's watch count
$todayCount = db()->fetchOne("SELECT COUNT(*) as cnt FROM ad_watches WHERE user_id = ? AND DATE(watched_at) = CURDATE()", 'i', $userId)['cnt'] ?? 0;

// Last watch time (for cooldown)
$lastWatch = db()->fetchOne("SELECT watched_at FROM ad_watches WHERE user_id = ? ORDER BY watched_at DESC LIMIT 1", 'i', $userId);
$cooldownRemaining = 0;
if ($lastWatch) {
    $elapsed = (time() - strtotime($lastWatch['watched_at'])) / 60;
    if ($elapsed < $cooldown) {
        $cooldownRemaining = ceil(($cooldown - $elapsed) * 60); // in seconds
    }
}

// Available ads
$ads = db()->fetchAll('SELECT * FROM ads WHERE is_active = 1 ORDER BY sort_order, id LIMIT 20');

// Today's watch history
$todayHistory = db()->fetchAll("SELECT aw.*, a.title FROM ad_watches aw JOIN ads a ON aw.ad_id = a.id WHERE aw.user_id = ? AND DATE(aw.watched_at) = CURDATE() ORDER BY aw.watched_at DESC", 'i', $userId);

$wallet = getUserWallet($userId);
$pageTitle = 'Tonton Iklan';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Tonton Iklan</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Tonton Iklan</div>
</div>

<!-- Status Bar -->
<div class="card" style="margin-bottom:20px;background:var(--bg-card2)">
  <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;justify-content:space-between">
    <div style="display:flex;gap:20px;flex-wrap:wrap">
      <div style="text-align:center">
        <div style="font-size:1.8rem;font-weight:900;color:var(--cyan)"><?= $todayCount ?>/<?= $maxPerDay ?></div>
        <div style="font-size:.78rem;color:var(--text-muted)">Iklan Hari Ini</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:1.8rem;font-weight:900;color:var(--text-warning)"><?= $cooldown ?>m</div>
        <div style="font-size:.78rem;color:var(--text-muted)">Cooldown</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:1.4rem;font-weight:700;color:var(--text-success)"><?= formatRupiahShort(array_sum(array_column($todayHistory,'reward_amount'))) ?></div>
        <div style="font-size:.78rem;color:var(--text-muted)">Earned Hari Ini</div>
      </div>
    </div>
    <div>
      <?php if ($todayCount >= $maxPerDay): ?>
      <span class="badge badge-warning" style="font-size:.8rem;padding:6px 12px">Kuota habis — kembali besok</span>
      <?php elseif ($cooldownRemaining > 0): ?>
      <div style="text-align:center">
        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:4px">Cooldown:</div>
        <div class="mining-countdown" id="adCooldown" data-countdown="<?= $cooldownRemaining ?>">--:--</div>
      </div>
      <?php else: ?>
      <span class="badge badge-success" style="font-size:.8rem;padding:6px 12px">✅ Siap menonton</span>
      <?php endif; ?>
    </div>
  </div>
  <!-- Progress bar -->
  <div style="margin-top:14px">
    <div style="display:flex;justify-content:space-between;font-size:.75rem;color:var(--text-muted);margin-bottom:4px">
      <span>Progress Hari Ini</span><span><?= $todayCount ?>/<?= $maxPerDay ?></span>
    </div>
    <div class="progress-wrap"><div class="progress-bar" data-progress="<?= round($todayCount/$maxPerDay*100) ?>" style="width:0%"></div></div>
  </div>
</div>

<?php if (!$adsEnabled || !$enabled): ?>
<div class="alert alert-warning">Fitur iklan sedang dinonaktifkan sementara.</div>
<?php elseif (empty($ads)): ?>
<div class="alert alert-info">Belum ada iklan tersedia saat ini.</div>
<?php else: ?>

<!-- Ad Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px">
  <?php foreach ($ads as $ad):
    $alreadyWatched = in_array($ad['id'], array_column($todayHistory, 'ad_id'));
    $canWatch = !$alreadyWatched && $todayCount < $maxPerDay && $cooldownRemaining <= 0;
  ?>
  <div class="card" style="overflow:hidden;<?= ($alreadyWatched || $todayCount >= $maxPerDay) ? 'opacity:.65' : '' ?>">
    <?php if ($ad['image']): ?>
    <img src="<?= UPLOADS_URL ?>/ads/<?= e($ad['image']) ?>" alt="<?= e($ad['title']) ?>" style="width:100%;height:160px;object-fit:cover;margin:-20px -20px 16px;width:calc(100% + 40px)">
    <?php else: ?>
    <div style="width:calc(100% + 40px);height:120px;margin:-20px -20px 16px;background:linear-gradient(135deg,var(--cyan-dim),var(--purple-dim));display:flex;align-items:center;justify-content:center;font-size:3rem">📺</div>
    <?php endif; ?>
    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:6px"><?= e($ad['title']) ?></h3>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;font-size:.85rem">
      <span style="color:var(--text-muted)">⏱ <?= $ad['watch_duration'] ?> detik</span>
      <span style="font-weight:700;color:var(--text-success)">+<?= formatRupiah($ad['reward_amount']) ?></span>
    </div>
    <?php if ($alreadyWatched): ?>
    <div class="btn-ghost btn-block btn-sm" style="text-align:center;opacity:.5;cursor:not-allowed">✅ Sudah Ditonton</div>
    <?php elseif ($canWatch): ?>
    <button class="btn-primary btn-block btn-sm watch-ad-btn"
      data-ad-id="<?= $ad['id'] ?>"
      data-duration="<?= $ad['watch_duration'] ?>"
      data-title="<?= e($ad['title']) ?>"
      data-reward="<?= formatRupiah($ad['reward_amount']) ?>"
      data-url="<?= e($ad['url'] ?? '') ?>">
      ▶ Tonton & Klaim Reward
    </button>
    <?php else: ?>
    <button class="btn-ghost btn-block btn-sm" disabled>Tidak Tersedia</button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Today history -->
<?php if (!empty($todayHistory)): ?>
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px">Riwayat Tonton Hari Ini</h3>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($todayHistory as $h): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 12px;background:var(--bg-card2);border-radius:var(--radius-sm);font-size:.85rem">
      <div>📺 <?= e($h['title']) ?></div>
      <div style="color:var(--text-success);font-weight:700">+<?= formatRupiah($h['reward_amount']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Ad Watch Modal (with countdown timer) -->
<div class="modal-overlay" id="adWatchModal" style="display:none">
  <div class="modal-box" style="text-align:center;max-width:460px">
    <h3 id="adModalTitle">Menonton Iklan</h3>
    <div id="adIframe" style="margin:16px 0;min-height:100px;background:var(--bg-card2);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:2rem">📺</div>
    <div style="margin-bottom:16px">
      <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:6px">Menunggu timer...</div>
      <div id="adTimerDisplay" class="mining-countdown" style="font-size:2rem">00:30</div>
      <div class="progress-wrap" style="margin-top:8px">
        <div class="progress-bar" id="adTimerBar" style="width:100%;transition:width 1s linear"></div>
      </div>
    </div>
    <button id="adClaimBtn" class="btn-primary btn-block" disabled>Tunggu timer selesai...</button>
    <button class="btn-ghost btn-sm" style="margin-top:8px" onclick="closeAdModal()">Tutup</button>
  </div>
</div>

<script>
const CSRF_TOKEN_ADS = document.querySelector('meta[name="csrf-token"]')?.content || '';
let adTimer = null;

document.querySelectorAll('.watch-ad-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const adId    = this.dataset.adId;
    const dur     = parseInt(this.dataset.duration) || 30;
    const title   = this.dataset.title;
    const reward  = this.dataset.reward;
    const url     = this.dataset.url;

    document.getElementById('adModalTitle').textContent = title;
    document.getElementById('adClaimBtn').disabled = true;
    document.getElementById('adClaimBtn').textContent = `Tunggu ${dur} detik...`;

    // Open ad URL in iframe or show placeholder
    const iframe = document.getElementById('adIframe');
    if (url) {
      iframe.innerHTML = `<iframe src="${url}" style="width:100%;height:200px;border:none;border-radius:8px" sandbox="allow-scripts allow-same-origin"></iframe>`;
    }

    document.getElementById('adWatchModal').style.display = 'flex';

    let remaining = dur;
    const total   = dur;
    const bar     = document.getElementById('adTimerBar');

    adTimer = setInterval(() => {
      remaining--;
      const s = remaining % 60, m = Math.floor(remaining / 60);
      document.getElementById('adTimerDisplay').textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
      bar.style.width = (remaining / total * 100) + '%';

      if (remaining <= 0) {
        clearInterval(adTimer);
        const claimBtn = document.getElementById('adClaimBtn');
        claimBtn.disabled = false;
        claimBtn.textContent = `✅ Klaim Reward ${reward}`;
        claimBtn.onclick = () => claimAdReward(adId);
      }
    }, 1000);
  });
});

async function claimAdReward(adId) {
  const btn = document.getElementById('adClaimBtn');
  btn.disabled = true; btn.textContent = 'Memproses...';
  try {
    const res  = await fetch(`${BASE_URL}/api/wallet.php`, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':CSRF_TOKEN_ADS,'X-Requested-With':'XMLHttpRequest'},
      body: `action=watch_ad&ad_id=${adId}&csrf_token=${encodeURIComponent(CSRF_TOKEN_ADS)}`
    });
    const data = await res.json();
    closeAdModal();
    if (data.success) { showToast('🎉 ' + data.message, 'success'); setTimeout(() => location.reload(), 1500); }
    else              { showToast(data.message, 'error'); }
  } catch(e) { showToast('Terjadi kesalahan', 'error'); btn.disabled = false; }
}

function closeAdModal() {
  clearInterval(adTimer);
  document.getElementById('adWatchModal').style.display = 'none';
  document.getElementById('adIframe').innerHTML = '📺';
}
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
