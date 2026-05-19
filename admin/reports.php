<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';

// CSV Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="noxara_'.e($type).'_'.date('Ymd_His').'.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($type === 'deposits') {
        fputcsv($out, ['ID','Member','Email','Nominal','Kode Unik','Total','Bank','Status','Waktu']);
        $rows = db()->fetchAll("SELECT d.*,u.full_name,u.email,ab.bank_name FROM deposits d JOIN users u ON d.user_id=u.id LEFT JOIN admin_bank_accounts ab ON d.admin_bank_id=ab.id ORDER BY d.created_at DESC LIMIT 5000");
        foreach($rows as $r) fputcsv($out,[$r['id'],$r['full_name'],$r['email'],$r['amount'],$r['unique_code'],$r['total_amount'],$r['bank_name'],$r['status'],$r['created_at']]);
    } elseif ($type === 'withdrawals') {
        fputcsv($out, ['ID','Member','Nominal','Fee','Diterima','Bank','Wallet','Status','Waktu']);
        $rows = db()->fetchAll("SELECT w.*,u.full_name,ba.bank_name FROM withdrawals w JOIN users u ON w.user_id=u.id LEFT JOIN bank_accounts ba ON w.bank_account_id=ba.id ORDER BY w.created_at DESC LIMIT 5000");
        foreach($rows as $r) fputcsv($out,[$r['id'],$r['full_name'],$r['amount'],$r['fee'],$r['net_amount'],$r['bank_name'],$r['wallet_type'],$r['status'],$r['created_at']]);
    } elseif ($type === 'members') {
        fputcsv($out, ['ID','Nama','Username','Email','HP','VIP','Total Deposit','Bergabung']);
        $rows = db()->fetchAll("SELECT id,full_name,username,email,phone,vip_level,total_deposit,created_at FROM users ORDER BY created_at DESC LIMIT 10000");
        foreach($rows as $r) fputcsv($out,[$r['id'],$r['full_name'],$r['username'],$r['email'],$r['phone'],'VIP '.$r['vip_level'],$r['total_deposit'],$r['created_at']]);
    }
    fclose($out); exit;
}

// Monthly data
$monthly = db()->fetchAll(
    "SELECT DATE_FORMAT(m,'%b %Y') as label,
            COALESCE(d.dt,0) as dep_total, COALESCE(d.dc,0) as dep_cnt,
            COALESCE(w.wt,0) as wd_total,  COALESCE(w.wc,0) as wd_cnt
     FROM (SELECT DATE_FORMAT(DATE_SUB(NOW(),INTERVAL n MONTH),'%Y-%m-01') m FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) x) t
     LEFT JOIN (SELECT DATE_FORMAT(created_at,'%Y-%m-01') m,SUM(amount) dt,COUNT(*) dc FROM deposits WHERE status='confirmed' GROUP BY m) d ON d.m=t.m
     LEFT JOIN (SELECT DATE_FORMAT(created_at,'%Y-%m-01') m,SUM(amount) wt,COUNT(*) wc FROM withdrawals WHERE status='approved' GROUP BY m) w ON w.m=t.m
     ORDER BY t.m ASC"
);

$topDepositors = db()->fetchAll("SELECT u.full_name,u.username,u.vip_level,COUNT(*) cnt,SUM(d.amount) total FROM deposits d JOIN users u ON d.user_id=u.id WHERE d.status='confirmed' GROUP BY d.user_id ORDER BY total DESC LIMIT 10");
$topReferrers  = db()->fetchAll("SELECT u.full_name,u.username,u.vip_level,COUNT(*) cnt,SUM(c.amount) total FROM commissions c JOIN users u ON c.user_id=u.id GROUP BY c.user_id ORDER BY total DESC LIMIT 10");

$summary = [
    db()->fetchOne("SELECT SUM(amount) as t FROM deposits WHERE status='confirmed'")['t']??0,
    db()->fetchOne("SELECT SUM(amount) as t FROM withdrawals WHERE status='approved'")['t']??0,
    db()->fetchOne("SELECT COUNT(*) as t FROM users")['t']??0,
    db()->fetchOne("SELECT COUNT(*) as t FROM user_products WHERE status='active'")['t']??0,
];
$maxDep = max(array_column($monthly,'dep_total')?:[1]);

adminHeader('Laporan Keuangan','reports');
?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
  <?php foreach([['💳','Total Deposit',formatRupiah($summary[0]),'var(--cyan)'],['💸','Total WD',formatRupiah($summary[1]),'var(--text-danger)'],['👥','Total Member',number_format($summary[2]),'var(--purple)'],['⛏️','Paket Aktif',number_format($summary[3]),'var(--text-success)']] as[$i,$l,$v,$c]):?>
  <div class="card" style="text-align:center;padding:16px"><div style="font-size:1.5rem"><?=$i?></div><div style="font-size:1.2rem;font-weight:800;color:<?=$c?>"><?=$v?></div><div style="font-size:.72rem;color:var(--text-muted)"><?=$l?></div></div>
  <?php endforeach;?>
</div>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach(['deposits'=>'💳 Export Deposit','withdrawals'=>'💸 Export WD','members'=>'👥 Export Member'] as $k=>$l):?>
  <form method="GET" style="display:inline"><input type="hidden" name="export" value="<?=$k?>"><button type="submit" class="btn-ghost btn-sm"><?=$l?> CSV</button></form>
  <?php endforeach;?>
</div>
<div class="card" style="margin-bottom:20px">
  <div style="font-weight:700;margin-bottom:14px">📊 Deposit & Penarikan 12 Bulan</div>
  <div style="display:flex;align-items:flex-end;gap:5px;height:100px;padding:0 4px;overflow-x:auto">
    <?php foreach($monthly as $m):$dp=$maxDep>0?($m['dep_total']/$maxDep*100):0;$wp=$maxDep>0?($m['wd_total']/$maxDep*100):0;?>
    <div style="flex:1;min-width:28px;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%;justify-content:flex-end">
      <div style="width:100%;display:flex;gap:2px;align-items:flex-end;justify-content:center;height:80%">
        <div style="flex:1;background:var(--cyan);border-radius:3px 3px 0 0;height:<?=max(2,$dp)?>%;opacity:.8" title="Dep: <?=formatRupiah($m['dep_total'])?>"></div>
        <div style="flex:1;background:var(--text-danger);border-radius:3px 3px 0 0;height:<?=max(2,$wp)?>%;opacity:.8" title="WD: <?=formatRupiah($m['wd_total'])?>"></div>
      </div>
      <div style="font-size:.58rem;color:var(--text-muted);white-space:nowrap"><?=e($m['label'])?></div>
    </div>
    <?php endforeach;?>
  </div>
  <div class="table-wrapper" style="margin-top:16px"><table>
    <thead><tr><th>Bulan</th><th>Total Dep</th><th>Jml</th><th>Total WD</th><th>Jml</th><th>Net</th></tr></thead>
    <tbody>
    <?php foreach(array_reverse($monthly) as $m):$net=$m['dep_total']-$m['wd_total'];?>
    <tr><td style="font-weight:600"><?=e($m['label'])?></td><td style="color:var(--cyan)"><?=formatRupiah($m['dep_total'])?></td><td style="color:var(--text-muted)"><?=number_format($m['dep_cnt'])?>x</td><td style="color:var(--text-danger)"><?=formatRupiah($m['wd_total'])?></td><td style="color:var(--text-muted)"><?=number_format($m['wd_cnt'])?>x</td><td style="font-weight:700;color:<?=$net>=0?'var(--text-success)':'var(--text-danger)'?>"><?=($net>=0?'+':'').formatRupiah($net)?></td></tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">🏆 Top 10 Depositor</div>
  <div class="table-wrapper"><table><thead><tr><th>#</th><th>Member</th><th>Total</th><th>Trx</th></tr></thead><tbody>
  <?php foreach($topDepositors as $i=>$d):?>
  <tr><td><?=$i<3?['🥇','🥈','🥉'][$i]:'<span style="color:var(--text-muted)">'.($i+1).'</span>'?></td><td><div style="font-weight:600;font-size:.82rem"><?=e($d['full_name'])?></div><?=vipBadge($d['vip_level'])?></td><td style="color:var(--cyan);font-weight:700"><?=formatRupiah($d['total'])?></td><td style="color:var(--text-muted)"><?=$d['cnt']?>x</td></tr>
  <?php endforeach;if(empty($topDepositors)):?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Tidak ada data</td></tr><?php endif;?>
  </tbody></table></div>
</div>
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">💰 Top 10 Referral</div>
  <div class="table-wrapper"><table><thead><tr><th>#</th><th>Member</th><th>Total Komisi</th><th>Trx</th></tr></thead><tbody>
  <?php foreach($topReferrers as $i=>$r):?>
  <tr><td><?=$i<3?['🥇','🥈','🥉'][$i]:'<span style="color:var(--text-muted)">'.($i+1).'</span>'?></td><td><div style="font-weight:600;font-size:.82rem"><?=e($r['full_name'])?></div><?=vipBadge($r['vip_level'])?></td><td style="color:var(--text-success);font-weight:700"><?=formatRupiah($r['total'])?></td><td style="color:var(--text-muted)"><?=$r['cnt']?>x</td></tr>
  <?php endforeach;if(empty($topReferrers)):?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Tidak ada data</td></tr><?php endif;?>
  </tbody></table></div>
</div>
</div>
<?php adminFooter(); ?>
