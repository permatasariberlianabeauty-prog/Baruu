<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$tab    = $_GET['tab'] ?? 'daily';
$validTabs = [MISSION_DAILY, MISSION_WEEKLY, MISSION_MILESTONE];
if (!in_array($tab, $validTabs)) $tab = MISSION_DAILY;

// Handle claim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'claim') {
    validateCsrf();
    $missionId = (int)($_POST['mission_id'] ?? 0);
    $periodKey = $_POST['period_key'] ?? null;
    $result    = claimMissionReward($userId, $missionId, $periodKey ?: null);
    if ($result['success']) setFlash('success', $result['message']);
    else setFlash('error', $result['message']);
    header('Location: ?tab='.$tab); exit;
}

$today   = date(DB_DATE_FORMAT);
$weekKey = date('Y') . '-W' . date('W');

function getMissions(int $userId, string $type): array {
    $today   = date('Y-m-d');
    $weekKey = date('Y') . '-W' . date('W');

    $missions = db()->fetchAll("SELECT * FROM missions WHERE type = ? AND is_active = 1 ORDER BY sort_order", 's', $type);

    foreach ($missions as &$m) {
        $periodKey = match($type) {
            'daily'     => $today,
            'weekly'    => $weekKey,
            'milestone' => null,
        };
        if ($periodKey !== null) {
            $um = db()->fetchOne("SELECT * FROM user_missions WHERE user_id = ? AND mission_id = ? AND period_key = ?", 'iis', $userId, $m['id'], $periodKey);
        } else {
            $um = db()->fetchOne("SELECT * FROM user_missions WHERE user_id = ? AND mission_id = ? AND period_key IS NULL", 'ii', $userId, $m['id']);
        }
        $m['progress']     = (int)($um['progress'] ?? 0);
        $m['is_completed'] = (bool)($um['is_completed'] ?? false);
        $m['is_claimed']   = (bool)($um['is_claimed'] ?? false);
        $m['um_id']        = $um['id'] ?? null;
        $m['period_key']   = $periodKey;
    }
    unset($m);
    return $missions;
}

$dailyMissions     = getMissions($userId, MISSION_DAILY);
$weeklyMissions    = getMissions($userId, MISSION_WEEKLY);
$milestoneMissions = getMissions($userId, MISSION_MILESTONE);

$current = match($tab) {
    'weekly'    => $weeklyMissions,
    'milestone' => $milestoneMissions,
    default     => $dailyMissions,
};

// Summary
$completedToday = count(array_filter($dailyMissions, fn($m) => $m['is_completed']));
$claimedToday   = count(array_filter($dailyMissions, fn($m) => $m['is_claimed']));

$pageTitle = 'Misi';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Misi & Tantangan</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Misi</div>
</div>

<!-- Summary Cards -->
<div class="stats-grid stagger" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-value"><?= count($dailyMissions) ?></div><div class="stat-label">Misi Harian</div></div>
  <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?= $completedToday ?></div><div class="stat-label">Selesai Hari Ini</div></div>
  <div class="stat-card"><div class="stat-icon">🎁</div><div class="stat-value"><?= $claimedToday ?></div><div class="stat-label">Diklaim Hari Ini</div></div>
  <div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-value"><?= count(array_filter($milestoneMissions, fn($m) => $m['is_completed'])) ?></div><div class="stat-label">Milestone Selesai</div></div>
</div>

<div class="tabs-wrapper" data-tabs>
  <div class="tabs">
    <?php foreach ([
      'daily'     => 'Harian ('     . count($dailyMissions) . ')',
      'weekly'    => 'Mingguan ('   . count($weeklyMissions) . ')',
      'milestone' => 'Milestone ('  . count($milestoneMissions) . ')',
    ] as $k => $label): ?>
    <a href="?tab=<?= $k ?>" class="tab-btn <?= $tab === $k ? 'active' : '' ?>" data-no-transition style="text-decoration:none"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:16px;display:flex;flex-direction:column;gap:12px">
    <?php if (empty($current)): ?>
    <div class="empty-state card"><p>Tidak ada misi untuk kategori ini.</p></div>
    <?php else: ?>
    <?php foreach ($current as $m):
      $pct     = $m['target_count'] > 0 ? min(100, round($m['progress'] / $m['target_count'] * 100)) : 0;
      $canClaim= $m['is_completed'] && !$m['is_claimed'];
    ?>
    <div class="card" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;<?= $m['is_claimed'] ? 'opacity:.6' : '' ?>">
      <div style="font-size:2.2rem;flex-shrink:0"><?= $m['is_claimed'] ? '✅' : ($m['is_completed'] ? '🎁' : ($tab === 'daily' ? '📋' : ($tab === 'weekly' ? '📅' : '🏆'))) ?></div>
      <div style="flex:1;min-width:200px">
        <div style="font-weight:700;font-size:.95rem"><?= e($m['title']) ?></div>
        <?php if ($m['description']): ?>
        <div style="font-size:.8rem;color:var(--text-muted);margin:2px 0"><?= e($m['description']) ?></div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
          <div class="progress-wrap" style="flex:1">
            <div class="progress-bar" data-progress="<?= $pct ?>" style="width:0%"></div>
          </div>
          <span style="font-size:.78rem;color:var(--text-muted);white-space:nowrap"><?= $m['progress'] ?>/<?= $m['target_count'] ?></span>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:.8rem;color:var(--text-muted)">Reward</div>
        <div style="font-weight:700;color:var(--text-success)"><?= formatRupiah($m['reward_value']) ?></div>
        <?php if ($canClaim): ?>
        <form method="POST" style="margin-top:6px">
          <?= csrfField() ?>
          <input type="hidden" name="action"     value="claim">
          <input type="hidden" name="mission_id" value="<?= $m['id'] ?>">
          <input type="hidden" name="period_key" value="<?= e($m['period_key'] ?? '') ?>">
          <button type="submit" class="btn-primary btn-sm">Klaim!</button>
        </form>
        <?php elseif ($m['is_claimed']): ?>
        <span class="badge badge-success" style="margin-top:6px">Diklaim</span>
        <?php else: ?>
        <span class="badge badge-muted" style="margin-top:6px"><?= $pct ?>%</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
