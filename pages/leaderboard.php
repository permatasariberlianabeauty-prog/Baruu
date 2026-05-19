<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$tab    = $_GET['tab']    ?? 'deposit';
$month  = $_GET['month']  ?? date('Y-m');
$validTabs = ['deposit', 'referral', 'profit'];
if (!in_array($tab, $validTabs)) $tab = 'deposit';

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$monthStart = $month . '-01';
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('F Y', strtotime($monthStart));

// Build leaderboard query per tab
function getLeaderboard(string $tab, string $monthStart, string $monthEnd, int $limit = 10): array {
    return match($tab) {
        'deposit' => db()->fetchAll(
            "SELECT u.id, u.full_name, u.username, u.vip_level, u.avatar,
                    COALESCE(SUM(d.amount),0) as value
             FROM users u
             LEFT JOIN deposits d ON d.user_id = u.id AND d.status = 'confirmed'
               AND d.confirmed_at BETWEEN ? AND ?
             GROUP BY u.id ORDER BY value DESC LIMIT ?",
            'ssi', $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59', $limit
        ),
        'referral' => db()->fetchAll(
            "SELECT u.id, u.full_name, u.username, u.vip_level, u.avatar,
                    COALESCE(SUM(c.amount),0) as value
             FROM users u
             LEFT JOIN commissions c ON c.user_id = u.id
               AND c.created_at BETWEEN ? AND ?
             GROUP BY u.id ORDER BY value DESC LIMIT ?",
            'ssi', $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59', $limit
        ),
        'profit' => db()->fetchAll(
            "SELECT u.id, u.full_name, u.username, u.vip_level, u.avatar,
                    COALESCE(SUM(ml.profit_amount),0) as value
             FROM users u
             LEFT JOIN mining_logs ml ON ml.user_id = u.id
               AND ml.status = 'credited'
               AND ml.credited_at BETWEEN ? AND ?
             GROUP BY u.id ORDER BY value DESC LIMIT ?",
            'ssi', $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59', $limit
        ),
        default => []
    };
}

$leaderboard = getLeaderboard($tab, $monthStart, $monthEnd);

// Find current user's rank
$userRank  = null;
$userValue = 0;
foreach ($leaderboard as $i => $row) {
    if ($row['id'] == $userId) {
        $userRank  = $i + 1;
        $userValue = $row['value'];
        break;
    }
}
if (!$userRank) {
    // Get user's own value outside top 10
    $userRank  = '10+';
}

$tabLabels = ['deposit' => 'Top Deposit', 'referral' => 'Top Referral', 'profit' => 'Top Profit'];
$pageTitle = 'Leaderboard';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>🏆 Leaderboard</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Leaderboard</div>
</div>

<!-- Month Nav -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div style="display:flex;align-items:center;gap:12px">
    <?php
    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
    $isCurrentMonth = $month === date('Y-m');
    ?>
    <a href="?tab=<?= $tab ?>&month=<?= $prevMonth ?>" class="btn-ghost btn-sm">‹ Prev</a>
    <span style="font-weight:700;min-width:120px;text-align:center"><?= $monthLabel ?></span>
    <?php if (!$isCurrentMonth): ?>
    <a href="?tab=<?= $tab ?>&month=<?= $nextMonth ?>" class="btn-ghost btn-sm">Next ›</a>
    <?php else: ?>
    <span class="btn-ghost btn-sm" style="opacity:.3;cursor:default">Next ›</span>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:6px">
    <?php foreach ($tabLabels as $k => $lbl): ?>
    <a href="?tab=<?= $k ?>&month=<?= $month ?>" class="btn-sm <?= $tab === $k ? 'btn-primary' : 'btn-ghost' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Podium (Top 3) -->
<?php if (!empty($leaderboard)): ?>
<div class="card" style="margin-bottom:20px;text-align:center">
  <div class="podium-wrap">
    <!-- Rank 2 -->
    <?php $r2 = $leaderboard[1] ?? null; ?>
    <div class="podium-item podium-2">
      <div class="podium-rank">2</div>
      <img src="<?= avatarUrl($r2['avatar'] ?? null) ?>" style="width:44px;height:44px;border-radius:50%;border:2px solid #C0C0C0;margin-bottom:6px" alt="">
      <div class="podium-bar"><span style="font-size:1.5rem">🥈</span></div>
      <div class="podium-name"><?= $r2 ? e(maskName($r2['full_name'])) : '—' ?></div>
      <div class="podium-value"><?= $r2 ? formatRupiahShort($r2['value']) : '—' ?></div>
    </div>
    <!-- Rank 1 -->
    <?php $r1 = $leaderboard[0] ?? null; ?>
    <div class="podium-item podium-1">
      <div class="podium-rank">1</div>
      <img src="<?= avatarUrl($r1['avatar'] ?? null) ?>" style="width:56px;height:56px;border-radius:50%;border:3px solid #FFD700;margin-bottom:6px" alt="">
      <div class="podium-bar"><span style="font-size:2rem">👑</span></div>
      <div class="podium-name" style="font-weight:800"><?= $r1 ? e(maskName($r1['full_name'])) : '—' ?></div>
      <div class="podium-value"><?= $r1 ? formatRupiahShort($r1['value']) : '—' ?></div>
    </div>
    <!-- Rank 3 -->
    <?php $r3 = $leaderboard[2] ?? null; ?>
    <div class="podium-item podium-3">
      <div class="podium-rank">3</div>
      <img src="<?= avatarUrl($r3['avatar'] ?? null) ?>" style="width:40px;height:40px;border-radius:50%;border:2px solid #CD7F32;margin-bottom:6px" alt="">
      <div class="podium-bar"><span style="font-size:1.3rem">🥉</span></div>
      <div class="podium-name"><?= $r3 ? e(maskName($r3['full_name'])) : '—' ?></div>
      <div class="podium-value"><?= $r3 ? formatRupiahShort($r3['value']) : '—' ?></div>
    </div>
  </div>
</div>

<!-- Rank 4-10 Table -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:20px">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Rank</th><th>Member</th><th>VIP</th><th><?= $tabLabels[$tab] ?></th></tr></thead>
      <tbody>
        <?php foreach (array_slice($leaderboard, 3) as $i => $row):
          $rank = $i + 4;
          $isMe = $row['id'] == $userId;
        ?>
        <tr style="<?= $isMe ? 'background:var(--cyan-dim);border-left:3px solid var(--cyan)' : '' ?>">
          <td style="font-weight:700;color:var(--text-muted)">#<?= $rank ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <img src="<?= avatarUrl($row['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover" alt="">
              <div>
                <div style="font-weight:600;font-size:.875rem">
                  <?= e(maskName($row['full_name'])) ?>
                  <?= $isMe ? '<span style="color:var(--cyan);font-size:.72rem">(Anda)</span>' : '' ?>
                </div>
                <div style="font-size:.72rem;color:var(--text-muted)">@<?= e($row['username']) ?></div>
              </div>
            </div>
          </td>
          <td><?= vipBadge($row['vip_level']) ?></td>
          <td style="font-weight:700;color:var(--cyan)"><?= formatRupiah($row['value']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="empty-state card"><p>Belum ada data leaderboard bulan ini.</p></div>
<?php endif; ?>

<!-- Your Position -->
<div class="card" style="background:linear-gradient(135deg,var(--cyan-dim),var(--purple-dim));border-color:var(--border-active)">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div style="display:flex;align-items:center;gap:12px">
      <div style="font-size:2rem;font-weight:900;font-family:var(--font-brand);color:var(--cyan)">#<?= $userRank ?></div>
      <div>
        <div style="font-weight:700">Posisi Anda</div>
        <div style="font-size:.8rem;color:var(--text-muted)"><?= $tabLabels[$tab] ?> · <?= $monthLabel ?></div>
      </div>
    </div>
    <div style="font-size:1.2rem;font-weight:800;color:var(--text-success)"><?= formatRupiah($userValue) ?></div>
  </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
