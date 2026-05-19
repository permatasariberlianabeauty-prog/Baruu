<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $fields = [
        // deposit commissions
        'commission_deposit_l1' => (float)($_POST['commission_deposit_l1'] ?? 0),
        'commission_deposit_l2' => (float)($_POST['commission_deposit_l2'] ?? 0),
        'commission_deposit_l3' => (float)($_POST['commission_deposit_l3'] ?? 0),
        // product / package commissions
        'commission_product_l1' => (float)($_POST['commission_product_l1'] ?? 0),
        'commission_product_l2' => (float)($_POST['commission_product_l2'] ?? 0),
        'commission_product_l3' => (float)($_POST['commission_product_l3'] ?? 0),
    ];

    foreach ($fields as $key => $value) {
        updateSetting($key, (string)$value);
    }

    logAdminAction($admin['id'], 'update_commission_settings');
    setFlash('success', 'Pengaturan komisi berhasil disimpan.');
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// ── Load current values ───────────────────────────────────
$s = [];
foreach (['commission_deposit_l1','commission_deposit_l2','commission_deposit_l3',
          'commission_product_l1','commission_product_l2','commission_product_l3'] as $k) {
    $s[$k] = (float)getSetting($k, 0);
}

// ── Recent commission logs (last 20) ──────────────────────
$recentLogs = db()->fetchAll(
    "SELECT wt.*, u.full_name, u.username
     FROM wallet_transactions wt
     JOIN users u ON wt.user_id = u.id
     WHERE wt.type = ?
     ORDER BY wt.created_at DESC LIMIT 20",
    's', TRX_REFERRAL_COMMISSION
);

adminHeader('Setting Komisi', 'commission_settings');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

<!-- Commission Form -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:6px">💰 Persentase Komisi Referral</h3>
  <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:20px">Komisi diberikan kepada upline saat downline melakukan deposit atau membeli paket.</p>

  <form method="POST">
    <?= csrfField() ?>

    <!-- Deposit commission -->
    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:16px;margin-bottom:16px">
      <div style="font-weight:700;color:var(--cyan);margin-bottom:14px;font-size:.9rem">💳 Komisi Deposit</div>
      <?php foreach ([1=>['L1 (Langsung)','#00D4FF'], 2=>['L2 (Cucu)','#7B2FFF'], 3=>['L3 (Cicit)','#FFB800']] as $level => [$label, $color]): ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <div style="width:28px;height:28px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0"><?= $level ?></div>
        <label style="flex:1;font-size:.875rem;font-weight:500;color:var(--text-secondary)"><?= $label ?></label>
        <div style="display:flex;align-items:center;gap:6px">
          <input type="number" name="commission_deposit_l<?= $level ?>" class="form-control" value="<?= $s['commission_deposit_l'.$level] ?>"
                 min="0" max="100" step="0.01" style="width:100px;text-align:right"
                 onchange="updatePreview()">
          <span style="color:var(--text-muted)">%</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Product commission -->
    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:16px;margin-bottom:20px">
      <div style="font-weight:700;color:var(--purple);margin-bottom:14px;font-size:.9rem">⛏️ Komisi Pembelian Paket</div>
      <?php foreach ([1=>['L1 (Langsung)','#00D4FF'], 2=>['L2 (Cucu)','#7B2FFF'], 3=>['L3 (Cicit)','#FFB800']] as $level => [$label, $color]): ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <div style="width:28px;height:28px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0"><?= $level ?></div>
        <label style="flex:1;font-size:.875rem;font-weight:500;color:var(--text-secondary)"><?= $label ?></label>
        <div style="display:flex;align-items:center;gap:6px">
          <input type="number" name="commission_product_l<?= $level ?>" class="form-control" value="<?= $s['commission_product_l'.$level] ?>"
                 min="0" max="100" step="0.01" style="width:100px;text-align:right"
                 onchange="updatePreview()">
          <span style="color:var(--text-muted)">%</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="btn-primary btn-block">💾 Simpan Semua Pengaturan Komisi</button>
  </form>
</div>

<!-- Preview & Stats -->
<div>
  <div class="card" style="margin-bottom:16px">
    <h3 class="card-title" style="margin-bottom:14px">🔢 Preview Simulasi</h3>
    <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px">Simulasi jika downline deposit/beli paket Rp 1.000.000:</p>
    <div id="previewBox" style="font-size:.875rem">
      <script>
      function updatePreview() {
        const inputs = {
          dl1: parseFloat(document.querySelector('[name=commission_deposit_l1]')?.value||0),
          dl2: parseFloat(document.querySelector('[name=commission_deposit_l2]')?.value||0),
          dl3: parseFloat(document.querySelector('[name=commission_deposit_l3]')?.value||0),
          pl1: parseFloat(document.querySelector('[name=commission_product_l1]')?.value||0),
          pl2: parseFloat(document.querySelector('[name=commission_product_l2]')?.value||0),
          pl3: parseFloat(document.querySelector('[name=commission_product_l3]')?.value||0),
        };
        const base = 1000000;
        const fmt = v => 'Rp' + v.toLocaleString('id-ID');
        document.getElementById('previewBox').innerHTML = `
          <div style="margin-bottom:10px;font-weight:700;color:var(--text-muted)">Deposit Rp 1.000.000</div>
          ${[1,2,3].map(l => `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border)"><span style="color:var(--text-secondary)">L${l}</span><span style="color:var(--cyan);font-weight:700">${fmt(base*inputs['dl'+l]/100)}</span></div>`).join('')}
          <div style="margin-top:12px;margin-bottom:10px;font-weight:700;color:var(--text-muted)">Beli Paket Rp 1.000.000</div>
          ${[1,2,3].map(l => `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border)"><span style="color:var(--text-secondary)">L${l}</span><span style="color:var(--purple);font-weight:700">${fmt(base*inputs['pl'+l]/100)}</span></div>`).join('')}
        `;
      }
      document.addEventListener('DOMContentLoaded', updatePreview);
      </script>
    </div>
  </div>

  <!-- Commission stats -->
  <?php
  $totalComm = db()->fetchOne("SELECT SUM(amount) as t FROM wallet_transactions WHERE type = ?", 's', TRX_REFERRAL_COMMISSION)['t'] ?? 0;
  $todayComm = db()->fetchOne("SELECT SUM(amount) as t FROM wallet_transactions WHERE type = ? AND DATE(created_at)=CURDATE()", 's', TRX_REFERRAL_COMMISSION)['t'] ?? 0;
  $topEarner = db()->fetchOne("SELECT u.full_name, SUM(wt.amount) as total FROM wallet_transactions wt JOIN users u ON wt.user_id=u.id WHERE wt.type=? GROUP BY wt.user_id ORDER BY total DESC LIMIT 1", 's', TRX_REFERRAL_COMMISSION);
  ?>
  <div class="card" style="margin-bottom:16px">
    <div style="font-weight:700;margin-bottom:12px;font-size:.9rem">📈 Statistik Komisi</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:12px;text-align:center">
        <div style="font-size:.7rem;color:var(--text-muted)">Total Komisi Dibayar</div>
        <div style="font-weight:800;color:var(--cyan);font-size:1.1rem"><?= formatRupiah($totalComm) ?></div>
      </div>
      <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:12px;text-align:center">
        <div style="font-size:.7rem;color:var(--text-muted)">Komisi Hari Ini</div>
        <div style="font-weight:800;color:var(--text-success);font-size:1.1rem"><?= formatRupiah($todayComm) ?></div>
      </div>
    </div>
    <?php if ($topEarner): ?>
    <div style="margin-top:10px;padding:10px;background:var(--bg-card2);border-radius:var(--radius-md);font-size:.82rem">
      <span style="color:var(--text-muted)">Top Earner: </span>
      <strong><?= e($topEarner['full_name']) ?></strong>
      <span style="color:var(--cyan);float:right"><?= formatRupiah($topEarner['total']) ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>

</div>

<!-- Recent Logs -->
<div class="card" style="margin-top:20px;padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">📋 Log Komisi Terbaru</div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Member (Penerima)</th><th>Nominal</th><th>Sumber</th><th>Waktu</th></tr></thead>
      <tbody>
        <?php if (empty($recentLogs)): ?>
        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada log komisi</td></tr>
        <?php else: ?>
        <?php foreach ($recentLogs as $log): ?>
        <tr>
          <td><div style="font-weight:600;font-size:.85rem"><?= e($log['full_name']) ?></div><div style="font-size:.72rem;color:var(--text-muted)">@<?= e($log['username']) ?></div></td>
          <td style="color:var(--text-success);font-weight:700"><?= formatRupiah($log['amount']) ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= e($log['description'] ?? $log['reference_type'] ?? '-') ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= timeAgo($log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminFooter(); ?>
