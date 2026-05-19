<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$year   = (int)($_GET['year']  ?? date('Y'));
$month  = (int)($_GET['month'] ?? date('n'));

// Clamp
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$firstDay  = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('N', $firstDay); // 1=Mon, 7=Sun
$monthLabel = date('F Y', $firstDay);

// Fetch daily profits for this month
$rows = db()->fetchAll(
    "SELECT DATE(credited_at) as date, SUM(profit_amount) as total
     FROM mining_logs WHERE user_id = ? AND status = 'credited'
     AND YEAR(credited_at) = ? AND MONTH(credited_at) = ?
     GROUP BY DATE(credited_at)",
    'iii', $userId, $year, $month
);
$profitByDay = [];
foreach ($rows as $r) {
    $profitByDay[$r['date']] = (float)$r['total'];
}

// Max profit for color scaling
$maxProfit = !empty($profitByDay) ? max($profitByDay) : 1;

// Monthly summary
$monthTotal   = array_sum($profitByDay);
$daysActive   = count($profitByDay);
$monthlyTarget = db()->fetchOne("SELECT COALESCE(SUM(profit_per_day),0) as t FROM user_products WHERE user_id = ? AND status='active'", 'i', $userId)['t'] ?? 0;

// Detail for selected day
$selectedDay = (int)($_GET['day'] ?? 0);
$dayDetails  = [];
if ($selectedDay >= 1 && $selectedDay <= $daysInMonth) {
    $selDate = sprintf('%04d-%02d-%02d', $year, $month, $selectedDay);
    $dayDetails = db()->fetchAll(
        "SELECT ml.*, p.name as product_name FROM mining_logs ml
         JOIN user_products up ON ml.user_product_id = up.id
         JOIN products p ON up.product_id = p.id
         WHERE ml.user_id = ? AND DATE(ml.credited_at) = ? AND ml.status = 'credited'",
        'is', $userId, $selDate
    );
}

$pageTitle = 'Kalender Profit';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>📅 Kalender Profit</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Kalender Profit</div>
</div>

<!-- Summary -->
<div class="stats-grid stagger" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value" style="font-size:.9rem"><?= formatRupiahShort($monthTotal) ?></div>
    <div class="stat-label">Total Bulan Ini</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-value"><?= $daysActive ?></div>
    <div class="stat-label">Hari Aktif Mining</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⛏️</div>
    <div class="stat-value" style="font-size:.9rem"><?= formatRupiahShort($monthlyTarget) ?></div>
    <div class="stat-label">Potensi/Hari</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📈</div>
    <div class="stat-value" style="font-size:.9rem"><?= $daysActive > 0 ? formatRupiahShort($monthTotal / $daysActive) : 'Rp0' ?></div>
    <div class="stat-label">Rata-rata/Hari</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

<!-- Calendar -->
<div class="card profit-cal">
  <div class="cal-header">
    <a href="?year=<?= $month==1?$year-1:$year ?>&month=<?= $month==1?12:$month-1 ?>" class="btn-ghost btn-sm">‹</a>
    <h3 style="font-weight:700"><?= $monthLabel ?></h3>
    <?php $isCurrentMonth = ($year == date('Y') && $month == date('n')); ?>
    <?php if (!$isCurrentMonth): ?>
    <a href="?year=<?= $month==12?$year+1:$year ?>&month=<?= $month==12?1:$month+1 ?>" class="btn-ghost btn-sm">›</a>
    <?php else: ?>
    <span class="btn-ghost btn-sm" style="opacity:.3">›</span>
    <?php endif; ?>
  </div>

  <!-- Day labels -->
  <div class="cal-grid">
    <?php foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d): ?>
    <div class="cal-day-label"><?= $d ?></div>
    <?php endforeach; ?>

    <!-- Empty cells before first day -->
    <?php for ($i = 1; $i < $startWeekday; $i++): ?>
    <div class="cal-day"></div>
    <?php endfor; ?>

    <!-- Days -->
    <?php for ($day = 1; $day <= $daysInMonth; $day++):
      $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $profit  = $profitByDay[$dateKey] ?? 0;
      $isToday = ($dateKey === date('Y-m-d'));
      $hasPro  = $profit > 0;

      // Color intensity based on profit
      $alpha   = $hasPro ? min(.85, max(.2, $profit / $maxProfit)) : 0;
      $bgColor = $hasPro ? "rgba(0,212,255,{$alpha})" : 'transparent';
      $textCol = $hasPro && $alpha > .5 ? '#0A0E1A' : 'var(--text-primary)';
    ?>
    <a href="?year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>"
       class="cal-day <?= $isToday ? 'today' : '' ?> <?= $hasPro ? 'has-profit' : '' ?>"
       style="background:<?= $bgColor ?>;color:<?= $textCol ?>;text-decoration:none;flex-direction:column;gap:2px"
       title="<?= $hasPro ? formatRupiah($profit) : 'Tidak ada profit' ?>">
      <span class="cal-day-num"><?= $day ?></span>
      <?php if ($hasPro): ?>
      <span style="font-size:.55rem;font-weight:700"><?= formatRupiahShort($profit) ?></span>
      <?php endif; ?>
    </a>
    <?php endfor; ?>
  </div>

  <!-- Legend -->
  <div style="display:flex;align-items:center;gap:12px;padding:12px 16px 0;font-size:.75rem;color:var(--text-muted)">
    <span>Intensitas warna = besar profit</span>
    <div style="display:flex;gap:4px">
      <?php for ($a = 1; $a <= 5; $a++): ?>
      <div style="width:16px;height:16px;border-radius:3px;background:rgba(0,212,255,<?= $a*.17 ?>)"></div>
      <?php endfor; ?>
    </div>
    <span>Rendah → Tinggi</span>
  </div>
</div>

<!-- Detail panel -->
<div>
  <?php if ($selectedDay > 0 && $selectedDay <= $daysInMonth): ?>
  <?php $selDate = sprintf('%04d-%02d-%02d', $year, $month, $selectedDay);
        $dayTotal = $profitByDay[$selDate] ?? 0; ?>
  <div class="card" style="margin-bottom:16px">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px">
      <?= date('d M Y', strtotime($selDate)) ?>
    </h3>
    <?php if (!empty($dayDetails)): ?>
    <div style="margin-bottom:12px">
      <div style="font-size:.8rem;color:var(--text-muted)">Total Profit</div>
      <div style="font-size:1.5rem;font-weight:800;color:var(--text-success)"><?= formatRupiah($dayTotal) ?></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($dayDetails as $dl): ?>
      <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:10px 12px">
        <div style="font-weight:600;font-size:.875rem"><?= e($dl['product_name']) ?></div>
        <div style="color:var(--text-success);font-weight:700">+<?= formatRupiah($dl['profit_amount']) ?></div>
        <div style="font-size:.72rem;color:var(--text-muted)"><?= formatDatetime($dl['credited_at']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:20px"><p>Tidak ada profit di tanggal ini</p></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Monthly best days -->
  <div class="card">
    <h3 style="font-size:.9rem;font-weight:700;margin-bottom:12px">Hari Terbaik</h3>
    <?php
    arsort($profitByDay);
    $topDays = array_slice($profitByDay, 0, 5, true);
    foreach ($topDays as $date => $profit):
    ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:.85rem">
      <span><?= date('d M', strtotime($date)) ?></span>
      <span style="color:var(--text-success);font-weight:700"><?= formatRupiah($profit) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($topDays)): ?>
    <div style="color:var(--text-muted);font-size:.85rem">Belum ada data</div>
    <?php endif; ?>
  </div>
</div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
