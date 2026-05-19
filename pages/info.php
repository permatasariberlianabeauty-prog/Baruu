<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$tab = $_GET['tab'] ?? 'about';
$validTabs = ['about','tnc','privacy','withdraw_policy'];
if (!in_array($tab, $validTabs)) $tab = 'about';

$tabLabels = [
  'about'           => 'Tentang Kami',
  'tnc'             => 'Syarat & Ketentuan',
  'privacy'         => 'Kebijakan Privasi',
  'withdraw_policy' => 'Kebijakan WD',
];

$content = db()->fetchOne('SELECT * FROM platform_info WHERE section = ?', 's', $tab);

// Platform stats
$totalMembers = (int)getSetting('total_members_display', 284750);
$totalPayout  = (float)getSetting('total_payout_display', 15800000000);
$rating       = getSetting('platform_rating', '4.9');
$year         = getSetting('platform_year', '2024');

$pageTitle = $tabLabels[$tab] ?? 'Info Platform';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Info Platform</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Info</div>
</div>

<!-- Platform Stats (about tab) -->
<?php if ($tab === 'about'): ?>
<div class="stats-grid stagger" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-value" data-countup="<?= $totalMembers ?>">0</div>
    <div class="stat-label">Total Member</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value" style="font-size:.85rem"><?= formatRupiahShort($totalPayout) ?>+</div>
    <div class="stat-label">Total Payout</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div class="stat-value"><?= e($rating) ?>/5</div>
    <div class="stat-label">Rating</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-value"><?= e($year) ?></div>
    <div class="stat-label">Berdiri Sejak</div>
  </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs-wrapper" data-tabs>
  <div class="tabs">
    <?php foreach ($tabLabels as $k => $l): ?>
    <a href="?tab=<?= $k ?>" class="tab-btn <?= $tab === $k ? 'active' : '' ?>" data-no-transition style="text-decoration:none"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card" style="margin-top:16px;max-width:900px">
    <?php if ($content): ?>
    <h2 style="font-size:1.2rem;font-weight:800;margin-bottom:16px"><?= e($content['title']) ?></h2>
    <div style="color:var(--text-secondary);line-height:1.8;font-size:.9rem">
      <?= $content['content'] /* Admin-controlled HTML — already sanitized on input */ ?>
    </div>
    <div style="font-size:.75rem;color:var(--text-muted);margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
      Terakhir diperbarui: <?= formatDatetime($content['updated_at']) ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>Konten belum tersedia</p></div>
    <?php endif; ?>
  </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
