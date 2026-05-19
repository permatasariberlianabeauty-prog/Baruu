<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';
$admin = getSessionAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    for ($i = 1; $i <= 3; $i++) {
        $depPct = (float)($_POST["deposit_l$i"] ?? 0);
        $proPct = (float)($_POST["product_l$i"] ?? 0);
        db()->execute('UPDATE commission_settings SET percent=? WHERE type=? AND level=?', 'dsi', $depPct, 'deposit', $i);
        db()->execute('UPDATE commission_settings SET percent=? WHERE type=? AND level=?', 'dsi', $proPct, 'product', $i);
    }
    logAdminAction($admin['id'], 'update_commission_settings');
    setFlash('success', 'Pengaturan komisi berhasil disimpan.');
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$commSettings = db()->fetchAll('SELECT * FROM commission_settings ORDER BY type, level');
$dep = array_filter($commSettings, fn($c) => $c['type'] === 'deposit');
$pro = array_filter($commSettings, fn($c) => $c['type'] === 'product');
$depByLevel = array_column(array_values($dep), 'percent', 'level');
$proByLevel = array_column(array_values($pro), 'percent', 'level');

$totalComm = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM commissions WHERE status='credited'")['t'] ?? 0;
$todayComm = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM commissions WHERE status='credited' AND DATE(created_at)=CURDATE()")['t'] ?? 0;

adminHeader('Setting Komisi', 'commission_settings');
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="card">
  <h3 class="card-title" style="margin-bottom:16px">💰 Persentase Komisi Referral</h3>
  <form method="POST">
    <?= csrfField() ?>
    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:16px;margin-bottom:14px">
      <div style="font-weight:700;color:var(--cyan);margin-bottom:12px">💳 Komisi Deposit</div>
      <?php for($i=1;$i<=3;$i++): $colors=['#00D4FF','#7B2FFF','#FFB800']; ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <div style="width:28px;height:28px;border-radius:50%;background:<?=$colors[$i-1]?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff"><?=$i?></div>
        <label style="flex:1;font-size:.875rem;color:var(--text-secondary)">Level <?=$i?></label>
        <input type="number" name="deposit_l<?=$i?>" class="form-control" value="<?=$depByLevel[$i]??0?>" min="0" max="100" step="0.01" style="width:100px;text-align:right" oninput="calcPreview()">
        <span style="color:var(--text-muted)">%</span>
      </div>
      <?php endfor; ?>
    </div>
    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:16px;margin-bottom:20px">
      <div style="font-weight:700;color:var(--purple);margin-bottom:12px">⛏️ Komisi Beli Paket</div>
      <?php for($i=1;$i<=3;$i++): $colors=['#00D4FF','#7B2FFF','#FFB800']; ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <div style="width:28px;height:28px;border-radius:50%;background:<?=$colors[$i-1]?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff"><?=$i?></div>
        <label style="flex:1;font-size:.875rem;color:var(--text-secondary)">Level <?=$i?></label>
        <input type="number" name="product_l<?=$i?>" class="form-control" value="<?=$proByLevel[$i]??0?>" min="0" max="100" step="0.01" style="width:100px;text-align:right" oninput="calcPreview()">
        <span style="color:var(--text-muted)">%</span>
      </div>
      <?php endfor; ?>
    </div>
    <button type="submit" class="btn-primary btn-block">💾 Simpan Komisi</button>
  </form>
</div>
<div>
  <div class="card" style="margin-bottom:16px">
    <h3 style="font-size:.9rem;font-weight:700;margin-bottom:12px">🔢 Simulasi (Rp1.000.000)</h3>
    <div id="previewBox"></div>
  </div>
  <div class="card">
    <div style="font-weight:700;margin-bottom:12px">📈 Statistik Komisi</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:12px;text-align:center"><div style="font-size:.7rem;color:var(--text-muted)">Total Dibayar</div><div style="font-weight:800;color:var(--cyan)"><?=formatRupiah($totalComm)?></div></div>
      <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:12px;text-align:center"><div style="font-size:.7rem;color:var(--text-muted)">Hari Ini</div><div style="font-weight:800;color:var(--text-success)"><?=formatRupiah($todayComm)?></div></div>
    </div>
  </div>
</div>
</div>
<script>
const initDep=[<?=implode(',',[$depByLevel[1]??0,$depByLevel[2]??0,$depByLevel[3]??0])?>];
const initPro=[<?=implode(',',[$proByLevel[1]??0,$proByLevel[2]??0,$proByLevel[3]??0])?>];
function calcPreview(){
  const inputs=[...document.querySelectorAll('input[name^="deposit_l"]')].map(i=>parseFloat(i.value)||0);
  const pinputs=[...document.querySelectorAll('input[name^="product_l"]')].map(i=>parseFloat(i.value)||0);
  const base=1000000,fmt=v=>'Rp'+v.toLocaleString('id-ID');
  document.getElementById('previewBox').innerHTML=`
    <div style="font-weight:700;color:var(--text-muted);font-size:.8rem;margin-bottom:8px">Deposit Rp1.000.000</div>
    ${inputs.map((p,i)=>`<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border);font-size:.82rem"><span>L${i+1}</span><span style="color:var(--cyan);font-weight:700">${fmt(base*p/100)}</span></div>`).join('')}
    <div style="font-weight:700;color:var(--text-muted);font-size:.8rem;margin:10px 0 8px">Beli Paket Rp1.000.000</div>
    ${pinputs.map((p,i)=>`<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border);font-size:.82rem"><span>L${i+1}</span><span style="color:var(--purple);font-weight:700">${fmt(base*p/100)}</span></div>`).join('')}
  `;
}
document.addEventListener('DOMContentLoaded',calcPreview);
</script>
<?php adminFooter(); ?>
