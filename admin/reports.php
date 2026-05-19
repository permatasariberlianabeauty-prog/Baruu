<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export'])) {
    validateCsrf();
    $type = $_GET['export'];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="noxara_report_' . $type . '_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

    $out = fopen('php://output', 'w');

    if ($type === 'monthly') {
        fputcsv($out, ['Bulan', 'Total Deposit', 'Jumlah Deposit', 'Total Penarikan', 'Jumlah Penarikan', 'Net']);
        $rows = db()->fetchAll(
            "SELECT
               DATE_FORMAT(t.month, '%M %Y') as label,
               COALESCE(d.total,0) as dep_total, COALESCE(d.cnt,0) as dep_cnt,
               COALESCE(w.total,0) as wd_total,  COALESCE(w.cnt,0) as wd_cnt
             FROM (
               SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL n MONTH), '%Y-%m-01') as month
               FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) nums
             ) t
             LEFT JOIN (SELECT DATE_FORMAT(created_at,'%Y-%m-01') m, SUM(amount) total, COUNT(*) cnt FROM deposits WHERE status='confirmed' GROUP BY m) d ON d.m = t.month
             LEFT JOIN (SELECT DATE_FORMAT(created_at,'%Y-%m-01') m, SUM(amount) total, COUNT(*) cnt FROM withdrawals WHERE status='approved' GROUP BY m) w ON w.m = t.month
             ORDER BY t.month DESC"
        );
        foreach ($rows as $r) {
            fputcsv($out, [$r['label'], $r['dep_total'], $r['dep_cnt'], $r['wd_total'], $r['wd_cnt'], $r['dep_total'] - $r['wd_total']]);
        }
    } elseif ($type === 'top_depositors') {
        fputcsv($out, ['Nama', 'Username', 'Email', 'Total Deposit', 'Jumlah Transaksi', 'VIP']);
        $rows = db()->fetchAll("SELECT u.full_name, u.username, u.email, u.vip_level, COUNT(*) cnt, SUM(d.amount) total FROM deposits d JOIN users u ON d.user_id = u.id WHERE d.status = 'confirmed' GROUP BY d.user_id ORDER BY total DESC LIMIT 100");
        foreach ($rows as $r) {
            fputcsv($out, [$r['full_name'], $r['username'], $r['email'], $r['total'], $r['cnt'], 'VIP '.$r['vip_level']]);
        }
    } elseif ($type === 'top_earners') {
        fputcsv($out, ['Nama', 'Username', 'VIP', 'Total Komisi', 'Jumlah Referral']);
        $rows = db()->fetchAll("SELECT u.full_name, u.username, u.vip_level, SUM(wt.amount) total, COUNT(*) cnt FROM wallet_transactions wt JOIN users u ON wt.user_id = u.id WHERE wt.type = ? GROUP BY wt.user_id ORDER BY total DESC LIMIT 100", 's', TRX_REFERRAL_COMMISSION);
        foreach ($rows as $r) {
            fputcsv($out, [$r['full_name'], $r['username'], 'VIP '.$r['vip_level'], $r['total'], $r['cnt']]);
        }
    }

    fclose($out);
    exit;
}

// ── Report Data ───────────────────────────────────────────

// Monthly deposit & withdraw (last 12 months)
$monthly = db()->fetchAll(
    "SELECT
       DATE_FORMAT(t.month, '%b %Y') as label,
       DATE_FORMAT(t.month, '%Y-%m') as ym,
       COALESCE(d.total,0) as dep_total, COALESCE(d.cnt,0) as dep_cnt,
       COALESCE(w.total,0) as wd_total,  COALESCE(w.cnt,0) as wd_cnt
     FROM (
       SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL n MONTH), '%Y-%m-01') as month
       FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) nums
     ) t
     LEFT JOIN (SELECT DATE_FORMAT(created_at,'%Y-%m-01') m, SUM(amount) total, COUNT(*) cnt FROM deposits WHERE status='confirmed' GROUP BY m) d ON d.m = t.month
     LEFT JOIN (SELECT DATE_FORMAT(created_at,'%Y-%m-01') m, SUM(amount) total, COUNT(*) cnt FROM withdrawals WHERE status='approved' GROUP BY m) w ON w.m = t.month
     ORDER BY t.month ASC"
);

// Top depositors
$topDepositors = db()->fetchAll(
    "SELECT u.full_name, u.username, u.vip_level, u.avatar,
            COUNT(*) as trx_count, SUM(d.amount) as total
     FROM deposits d
     JOIN users u ON d.user_id = u.id
     WHERE d.status = 'confirmed'
     GROUP BY d.user_id
     ORDER BY total DESC
     LIMIT 10"
);

// Top earners (referral commission)
$topEarners = db()->fetchAll(
    "SELECT u.full_name, u.username, u.vip_level, u.avatar,
            COUNT(*) as trx_count, SUM(wt.amount) as total
     FROM wallet_transactions wt
     JOIN users u ON wt.user_id = u.id
     WHERE wt.type = ?
     GROUP BY wt.user_id
     ORDER BY total DESC
     LIMIT 10",
    's', TRX_REFERRAL_COMMISSION
);

// Overall summary
$summary = [
    'total_deposit'  => db()->fetchOne("SELECT SUM(amount) as t FROM deposits WHERE status='confirmed'")['t'] ?? 0,
    'total_withdraw' => db()->fetchOne("SELECT SUM(amount) as t FROM withdrawals WHERE status='approved'")['t'] ?? 0,
    'total_members'  => db()->fetchOne("SELECT COUNT(*) as t FROM users")['t'] ?? 0,
    'total_products' => db()->fetchOne("SELECT COUNT(*) as t FROM user_products WHERE status='active'")['t'] ?? 0,
];

// Max values for bar charts
$maxDep = max(array_column($monthly, 'dep_total') ?: [1]);
$maxWd  = max(array_column($monthly, 'wd_total')  ?: [1]);

adminHeader('Laporan Keuangan', 'reports');
?>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
  <?php foreach ([
    ['💳','Total Deposit',  formatRupiah($summary['total_deposit']),  'var(--cyan)'],
    ['💸','Total Penarikan',formatRupiah($summary['total_withdraw']), 'var(--text-danger)'],
    ['👥','Total Member',   number_format($summary['total_members']), 'var(--purple)'],
    ['⛏️','Paket Aktif',    number_format($summary['total_products']),'var(--text-success)'],
  ] as [$icon,$label,$val,$color]): ?>
  <div class="card" style="text-align:center;padding:16px">
    <div style="font-size:1.5rem"><?= $icon ?></div>
    <div style="font-size:1.2rem;font-weight:800;color:<?= $color ?>;margin:4px 0"><?= $val ?></div>
    <div style="font-size:.72rem;color:var(--text-muted)"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Export Buttons -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:inline">
    <input type="hidden" name="export" value="monthly">
    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
    <button type="submit" class="btn-ghost btn-sm">📥 Export Bulanan CSV</button>
  </form>
  <form method="GET" style="display:inline">
    <input type="hidden" name="export" value="top_depositors">
    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
    <button type="submit" class="btn-ghost btn-sm">📥 Export Top Depositor</button>
  </form>
  <form method="GET" style="display:inline">
    <input type="hidden" name="export" value="top_earners">
    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
    <button type="submit" class="btn-ghost btn-sm">📥 Export Top Earner</button>
  </form>
</div>

<!-- Monthly Chart (bar) + Table -->
<div class="card" style="margin-bottom:20px">
  <div style="font-weight:700;font-size:1rem;margin-bottom:16px">📊 Deposit & Penarikan 12 Bulan Terakhir</div>

  <!-- Bar Chart (CSS) -->
  <div style="overflow-x:auto;margin-bottom:16px">
    <div style="display:flex;align-items:flex-end;gap:6px;min-width:600px;height:120px;padding:0 4px">
      <?php foreach ($monthly as $m):
        $dPct = $maxDep > 0 ? ($m['dep_total'] / $maxDep * 100) : 0;
        $wPct = $maxWd  > 0 ? ($m['wd_total']  / $maxWd  * 100) : 0;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%;justify-content:flex-end">
        <div style="width:100%;display:flex;gap:2px;align-items:flex-end;justify-content:center;height:90%">
          <div style="flex:1;background:var(--cyan);border-radius:3px 3px 0 0;height:<?= max(2,$dPct) ?>%;opacity:.8" title="Deposit: <?= formatRupiah($m['dep_total']) ?>"></div>
          <div style="flex:1;background:var(--text-danger);border-radius:3px 3px 0 0;height:<?= max(2,$wPct) ?>%;opacity:.8" title="WD: <?= formatRupiah($m['wd_total']) ?>"></div>
        </div>
        <div style="font-size:.6rem;color:var(--text-muted);white-space:nowrap"><?= e($m['label']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:16px;margin-top:8px;font-size:.78rem">
      <span style="display:flex;align-items:center;gap:4px"><span style="width:12px;height:8px;background:var(--cyan);border-radius:2px;display:inline-block"></span>Deposit</span>
      <span style="display:flex;align-items:center;gap:4px"><span style="width:12px;height:8px;background:var(--text-danger);border-radius:2px;display:inline-block"></span>Penarikan</span>
    </div>
  </div>

  <!-- Table -->
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Bulan</th><th>Total Deposit</th><th>Jml Dep</th><th>Total WD</th><th>Jml WD</th><th>Net</th></tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($monthly) as $m):
          $net = $m['dep_total'] - $m['wd_total'];
        ?>
        <tr>
          <td style="font-weight:600"><?= e($m['label']) ?></td>
          <td style="color:var(--cyan);font-weight:600"><?= formatRupiah($m['dep_total']) ?></td>
          <td style="color:var(--text-muted)"><?= number_format($m['dep_cnt']) ?>x</td>
          <td style="color:var(--text-danger)"><?= formatRupiah($m['wd_total']) ?></td>
          <td style="color:var(--text-muted)"><?= number_format($m['wd_cnt']) ?>x</td>
          <td style="font-weight:700;color:<?= $net >= 0 ? 'var(--text-success)' : 'var(--text-danger)' ?>"><?= ($net >= 0 ? '+' : '') . formatRupiah($net) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Top Depositors & Earners -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">🏆 Top 10 Depositor</div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Member</th><th>Total</th><th>Trx</th></tr></thead>
      <tbody>
        <?php if (empty($topDepositors)): ?>
        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">Tidak ada data</td></tr>
        <?php else: ?>
        <?php foreach ($topDepositors as $i => $d): ?>
        <tr>
          <td>
            <?php if ($i < 3): ?>
            <span style="font-size:1.1rem"><?= ['🥇','🥈','🥉'][$i] ?></span>
            <?php else: ?><span style="color:var(--text-muted);font-size:.85rem"><?= $i+1 ?></span><?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <img src="<?= avatarUrl($d['avatar']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover">
              <div>
                <div style="font-weight:600;font-size:.82rem"><?= e($d['full_name']) ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)"><?= vipBadge($d['vip_level']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-weight:700;color:var(--cyan);font-size:.85rem"><?= formatRupiah($d['total']) ?></td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= number_format($d['trx_count']) ?>x</td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">💰 Top 10 Earner Komisi</div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Member</th><th>Total Komisi</th><th>Trx</th></tr></thead>
      <tbody>
        <?php if (empty($topEarners)): ?>
        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">Tidak ada data</td></tr>
        <?php else: ?>
        <?php foreach ($topEarners as $i => $e): ?>
        <tr>
          <td>
            <?php if ($i < 3): ?>
            <span style="font-size:1.1rem"><?= ['🥇','🥈','🥉'][$i] ?></span>
            <?php else: ?><span style="color:var(--text-muted);font-size:.85rem"><?= $i+1 ?></span><?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <img src="<?= avatarUrl($e['avatar']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover">
              <div>
                <div style="font-weight:600;font-size:.82rem"><?= e($e['full_name']) ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)"><?= vipBadge($e['vip_level']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-weight:700;color:var(--text-success);font-size:.85rem"><?= formatRupiah($e['total']) ?></td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= number_format($e['trx_count']) ?>x</td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<?php adminFooter(); ?>
