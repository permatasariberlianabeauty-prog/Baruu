<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';
$admin = getSessionAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'broadcast') {
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type    = trim($_POST['type'] ?? NOTIF_INFO);
        $target  = trim($_POST['target'] ?? 'all');
        $minVip  = (int)($_POST['min_vip'] ?? 0);
        if (!$title || !$message) { setFlash('error', 'Judul dan pesan wajib diisi.'); header('Location:'.$_SERVER['PHP_SELF']); exit; }

        $users = match($target) {
            'vip'        => db()->fetchAll('SELECT id FROM users WHERE is_active=1 AND is_blocked=0 AND vip_level>=?','i',$minVip),
            'no_deposit' => db()->fetchAll('SELECT id FROM users WHERE is_active=1 AND is_blocked=0 AND total_deposit=0'),
            default      => db()->fetchAll('SELECT id FROM users WHERE is_active=1 AND is_blocked=0'),
        };

        $count = 0;
        foreach ($users as $u) {
            db()->execute('INSERT INTO notifications(user_id,title,message,type) VALUES(?,?,?,?)','isss',$u['id'],$title,$message,$type);
            $count++;
        }
        logAdminAction($admin['id'],'broadcast_notification','',0,"\"$title\" ke $count member");
        setFlash('success', "Notifikasi dikirim ke $count member.");
    }
    header('Location:'.$_SERVER['PHP_SELF']); exit;
}

$totalMembers    = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE is_active=1 AND is_blocked=0')['c'] ?? 0;
$totalNoDeposit  = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE is_active=1 AND is_blocked=0 AND total_deposit=0')['c'] ?? 0;
$recentBroadcast = db()->fetchAll("SELECT * FROM admin_logs WHERE action='broadcast_notification' ORDER BY created_at DESC LIMIT 20");
$notifTypes = [NOTIF_INFO=>'ℹ️ Info',NOTIF_SUCCESS=>'✅ Sukses',NOTIF_WARNING=>'⚠️ Warning',NOTIF_ERROR=>'❌ Error',NOTIF_SYSTEM=>'⚙️ Sistem',NOTIF_VIP=>'🏆 VIP'];

adminHeader('Kirim Notifikasi','notifications');
?>
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px">
<div class="card">
  <h3 class="card-title" style="margin-bottom:16px">📢 Broadcast Notifikasi</h3>
  <form method="POST" onsubmit="return confirm('Kirim notifikasi sekarang?')">
    <?=csrfField()?><input type="hidden" name="action" value="broadcast">
    <div class="form-group"><label class="form-label">Judul *</label><input type="text" name="title" class="form-control" placeholder="Judul notifikasi" required maxlength="200" oninput="document.getElementById('pt').textContent=this.value||'Judul'"></div>
    <div class="form-group"><label class="form-label">Pesan *</label><textarea name="message" class="form-control" rows="4" placeholder="Isi pesan..." required maxlength="500" oninput="document.getElementById('pm').textContent=this.value||'Pesan'"></textarea></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="form-group"><label class="form-label">Tipe</label>
        <select name="type" class="form-control" id="ntType" onchange="document.getElementById('pi').textContent=this.options[this.selectedIndex].text.split(' ')[0]">
          <?php foreach($notifTypes as $k=>$l):?><option value="<?=$k?>"><?=$l?></option><?php endforeach;?></select>
      </div>
      <div class="form-group"><label class="form-label">Target Penerima</label>
        <select name="target" class="form-control" id="ntTarget" onchange="toggleVip()">
          <option value="all">👥 Semua (<?=number_format($totalMembers)?>)</option>
          <option value="vip">🏆 VIP Level ≥</option>
          <option value="no_deposit">💤 Belum Deposit (<?=number_format($totalNoDeposit)?>)</option>
        </select>
      </div>
    </div>
    <div id="vipGroup" style="display:none" class="form-group"><label class="form-label">Min VIP</label>
      <select name="min_vip" class="form-control"><?php for($i=1;$i<=5;$i++):?><option value="<?=$i?>">VIP <?=$i?>+</option><?php endfor;?></select>
    </div>
    <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;margin-bottom:16px;display:flex;gap:12px">
      <div id="pi" style="font-size:1.3rem">ℹ️</div>
      <div><div id="pt" style="font-weight:700;font-size:.9rem">Judul</div><div id="pm" style="font-size:.82rem;color:var(--text-secondary)">Pesan</div></div>
    </div>
    <button type="submit" class="btn-primary btn-block btn-lg">📢 Kirim Sekarang</button>
  </form>
</div>
<div>
  <div class="card" style="margin-bottom:14px">
    <div style="font-weight:700;margin-bottom:12px;font-size:.9rem">📊 Target Audience</div>
    <?php foreach([['Semua Member',$totalMembers,'var(--cyan)'],['Belum Deposit',$totalNoDeposit,'var(--text-warning)']] as[$l,$v,$c]):?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:.85rem"><span style="color:var(--text-secondary)"><?=$l?></span><strong style="color:<?=$c?>"><?=number_format($v)?></strong></div>
    <?php endforeach;?>
  </div>
  <div class="card">
    <div style="font-weight:700;margin-bottom:10px;font-size:.9rem">📜 Broadcast Terbaru</div>
    <?php if(empty($recentBroadcast)):?><div style="font-size:.82rem;color:var(--text-muted)">Belum ada riwayat</div>
    <?php else: foreach($recentBroadcast as $log):?>
    <div style="padding:7px 0;border-bottom:1px solid var(--border);font-size:.8rem">
      <div style="font-weight:600"><?=e(truncate($log['description']??'-',50))?></div>
      <div style="color:var(--text-muted);font-size:.72rem"><?=timeAgo($log['created_at'])?></div>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>
</div>
<script>
function toggleVip(){document.getElementById('vipGroup').style.display=document.getElementById('ntTarget').value==='vip'?'block':'none';}
</script>
<?php adminFooter(); ?>
