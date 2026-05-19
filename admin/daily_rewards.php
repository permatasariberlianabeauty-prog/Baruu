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
    $action = $_POST['action'] ?? '';
    if ($action === 'save_item') {
        $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$type=trim($_POST['type']??'balance_bonus');
        $val=(float)($_POST['value']??0);$prob=(float)($_POST['probability']??10);$wallet=trim($_POST['wallet_type']??WALLET_BONUS);
        $jack=isset($_POST['is_jackpot'])?1:0;$active=isset($_POST['is_active'])?1:0;
        if(!$name){setFlash('error','Nama wajib diisi.');header('Location:'.$_SERVER['PHP_SELF']);exit;}
        if($id){db()->execute('UPDATE daily_reward_items SET name=?,type=?,value=?,probability=?,wallet_type=?,is_jackpot=?,is_active=? WHERE id=?','ssddssii',$name,$type,$val,$prob,$wallet,$jack,$active,$id);setFlash('success','Item diperbarui.');}
        else{db()->execute('INSERT INTO daily_reward_items(name,type,value,probability,wallet_type,is_jackpot,is_active) VALUES(?,?,?,?,?,?,?)','ssddssii',$name,$type,$val,$prob,$wallet,$jack,$active);setFlash('success','Item ditambahkan.');}
        logAdminAction($admin['id'],$id?'edit_daily_reward':'add_daily_reward','daily_reward_item',$id);
    }
    if($action==='delete'){db()->execute('DELETE FROM daily_reward_items WHERE id=?','i',(int)$_POST['id']);setFlash('success','Item dihapus.');}
    if($action==='save_settings'){
        $enabled=isset($_POST['enabled'])?1:0;$resetH=(int)($_POST['reset_hour']??0);
        $existing=db()->fetchOne('SELECT id FROM daily_reward_settings LIMIT 1');
        if($existing){db()->execute('UPDATE daily_reward_settings SET is_enabled=?,reset_hour=?','ii',$enabled,$resetH);}
        else{db()->execute('INSERT INTO daily_reward_settings(is_enabled,reset_hour) VALUES(?,?)','ii',$enabled,$resetH);}
        setFlash('success','Setting disimpan.');
    }
    header('Location:'.$_SERVER['PHP_SELF']);exit;
}

$edit=isset($_GET['edit'])?db()->fetchOne('SELECT * FROM daily_reward_items WHERE id=?','i',(int)$_GET['edit']):null;
$items=db()->fetchAll('SELECT * FROM daily_reward_items ORDER BY probability DESC');
$drSettings=db()->fetchOne('SELECT * FROM daily_reward_settings LIMIT 1');
$totalProb=array_sum(array_column($items,'probability'));
$todayClaims=db()->fetchOne('SELECT COUNT(*) as c FROM user_daily_claims WHERE DATE(claimed_at)=CURDATE()')['c']??0;
$totalClaims=db()->fetchOne('SELECT COUNT(*) as c FROM user_daily_claims')['c']??0;

adminHeader('Hadiah Harian','daily_rewards');
?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
  <?php foreach([['🎁','Item Aktif',count(array_filter($items,fn($i)=>$i['is_active'])),'var(--cyan)'],['💥','Jackpot',count(array_filter($items,fn($i)=>$i['is_jackpot'])),'var(--purple)'],['📅','Klaim Hari Ini',$todayClaims,'var(--text-success)'],['📊','Total Klaim',$totalClaims,'var(--text-warning)']] as[$ic,$lb,$v,$c]): ?>
  <div class="card" style="text-align:center;padding:14px"><div style="font-size:1.5rem"><?=$ic?></div><div style="font-size:1.3rem;font-weight:800;color:<?=$c?>"><?=number_format($v)?></div><div style="font-size:.72rem;color:var(--text-muted)"><?=$lb?></div></div>
  <?php endforeach;?>
</div>
<div class="card" style="margin-bottom:16px">
  <form method="POST" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <?=csrfField()?><input type="hidden" name="action" value="save_settings">
    <div class="form-check" style="margin-bottom:0"><input type="checkbox" name="enabled" id="dre" <?=($drSettings['is_enabled']??1)?'checked':''?>><label for="dre" style="font-weight:600">Hadiah Harian Aktif</label></div>
    <div style="display:flex;align-items:center;gap:8px"><label style="font-size:.85rem;font-weight:600;color:var(--text-secondary)">Reset Jam:</label>
      <select name="reset_hour" class="form-control" style="width:80px"><?php for($h=0;$h<=23;$h++):?><option value="<?=$h?>" <?=($drSettings['reset_hour']??0)==$h?'selected':''?>><?=sprintf('%02d:00',$h)?></option><?php endfor;?></select>
    </div>
    <?php if($totalProb!=100):?><div style="background:rgba(255,184,0,.1);border:1px solid rgba(255,184,0,.3);border-radius:var(--radius-sm);padding:6px 10px;font-size:.78rem;color:var(--text-warning)">⚠️ Total prob: <?=number_format($totalProb,2)?>%</div><?php else:?><span class="badge badge-success">✅ Prob: 100%</span><?php endif;?>
    <button type="submit" class="btn-secondary btn-sm" style="margin-left:auto">💾 Simpan</button>
  </form>
</div>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper"><table>
    <thead><tr><th>Nama</th><th>Tipe</th><th>Nilai</th><th>Prob%</th><th>Jackpot</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($items as $it):?>
    <tr style="<?=($edit&&$edit['id']==$it['id'])?'background:var(--cyan-dim)':''?>">
      <td style="font-weight:600"><?=e($it['name'])?></td>
      <td><span class="badge badge-purple"><?=e($it['type'])?></span></td>
      <td style="color:var(--text-success);font-weight:700"><?=formatRupiah($it['value'])?></td>
      <td><?=number_format($it['probability'],2)?>%</td>
      <td><?=$it['is_jackpot']?'💥':'—'?></td>
      <td><?=$it['is_active']?'<span class="badge badge-success">On</span>':'<span class="badge badge-muted">Off</span>'?></td>
      <td><a href="?edit=<?=$it['id']?>" class="btn-ghost btn-sm">✏️</a><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$it['id']?>"><button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus?')">🗑</button></form></td>
    </tr>
    <?php endforeach;if(empty($items)):?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada item</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px"><?=$edit?'Edit Item':'Tambah Item'?></h3>
  <form method="POST">
    <?=csrfField()?><input type="hidden" name="action" value="save_item">
    <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
    <div class="form-group"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="<?=e($edit['name']??'')?>" required></div>
    <div class="form-group"><label class="form-label">Tipe</label>
      <select name="type" class="form-control"><?php foreach(['balance_bonus'=>'💰 Saldo Bonus','ad_quota'=>'📺 Kuota Iklan','profit_boost'=>'🚀 Boost Profit'] as $k=>$l):?><option value="<?=$k?>" <?=($edit['type']??'balance_bonus')===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select>
    </div>
    <div class="form-group"><label class="form-label">Nilai</label><input type="number" name="value" class="form-control" value="<?=$edit['value']??0?>" min="0" step="0.01"></div>
    <div class="form-group"><label class="form-label">Wallet</label>
      <select name="wallet_type" class="form-control"><?php foreach([WALLET_BONUS=>'Bonus',WALLET_PROFIT=>'Profit'] as $k=>$l):?><option value="<?=$k?>" <?=($edit['wallet_type']??WALLET_BONUS)===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select>
    </div>
    <div class="form-group"><label class="form-label">Probabilitas (%)</label><input type="number" name="probability" class="form-control" value="<?=$edit['probability']??10?>" min="0.01" max="100" step="0.01"></div>
    <div class="form-check" style="margin-bottom:8px"><input type="checkbox" name="is_jackpot" id="ij" <?=($edit['is_jackpot']??0)?'checked':''?>><label for="ij">💥 Jackpot</label></div>
    <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" id="ia" <?=(!$edit||$edit['is_active'])?'checked':''?>><label for="ia">Aktif</label></div>
    <div style="display:flex;gap:8px"><button type="submit" class="btn-primary"><?=$edit?'Simpan':'Tambah'?></button><?php if($edit):?><a href="<?=BASE_URL?>/admin/daily_rewards.php" class="btn-ghost">Batal</a><?php endif;?></div>
  </form>
</div>
</div>
<?php adminFooter(); ?>
