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
    if ($action === 'save') {
        $id=(int)($_POST['id']??0);$title=trim($_POST['title']??'');$desc=trim($_POST['description']??'');
        $type=$_POST['type']??MISSION_DAILY;$at=$_POST['action_type']??ACTION_LOGIN;
        $tgt=(int)($_POST['target_count']??1);$rt=$_POST['reward_type']??'balance_bonus';
        $rv=(float)($_POST['reward_value']??0);$sort=(int)($_POST['sort_order']??0);$active=isset($_POST['is_active'])?1:0;
        if(!$title){setFlash('error','Judul wajib.');header('Location:'.$_SERVER['PHP_SELF']);exit;}
        if($id){db()->execute('UPDATE missions SET title=?,description=?,type=?,action_type=?,target_count=?,reward_type=?,reward_value=?,sort_order=?,is_active=? WHERE id=?','ssssiisdii',$title,$desc,$type,$at,$tgt,$rt,$rv,$sort,$active,$id);setFlash('success','Misi diperbarui.');}
        else{db()->execute('INSERT INTO missions(title,description,type,action_type,target_count,reward_type,reward_value,sort_order,is_active) VALUES(?,?,?,?,?,?,?,?,?)','ssssiisdii',$title,$desc,$type,$at,$tgt,$rt,$rv,$sort,$active);setFlash('success','Misi ditambahkan.');}
        logAdminAction($admin['id'],$id?'edit_mission':'add_mission','mission',$id);
    }
    if($action==='toggle'){$id=(int)$_POST['id'];$c=db()->fetchOne('SELECT is_active FROM missions WHERE id=?','i',$id);if($c)db()->execute('UPDATE missions SET is_active=? WHERE id=?','ii',$c['is_active']?0:1,$id);}
    if($action==='delete'){db()->execute('UPDATE missions SET is_active=0 WHERE id=?','i',(int)$_POST['id']);setFlash('success','Misi dinonaktifkan.');}
    header('Location:'.$_SERVER['PHP_SELF'].'?type='.($_GET['type']??'all'));exit;
}

$edit=isset($_GET['edit'])?db()->fetchOne('SELECT * FROM missions WHERE id=?','i',(int)$_GET['edit']):null;
$ft=$_GET['type']??'all';
$where=$ft!=='all'?'WHERE type=?':'';$params=$ft!=='all'?[$ft]:[];$types=$ft!=='all'?'s':'';
$missions=db()->fetchAll("SELECT * FROM missions $where ORDER BY type,sort_order",$types,...$params);
$typeLabels=[MISSION_DAILY=>'Harian',MISSION_WEEKLY=>'Mingguan',MISSION_MILESTONE=>'Milestone'];
$actionLabels=[ACTION_LOGIN=>'Login',ACTION_MINING=>'Mining',ACTION_WATCH_AD=>'Nonton Iklan',ACTION_REFERRAL=>'Referral',ACTION_DEPOSIT=>'Deposit'];

adminHeader('Manajemen Misi','missions');
?>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach(array_merge(['all'=>'Semua'],$typeLabels) as $k=>$l): ?>
  <a href="?type=<?=$k?>" class="btn-sm <?=$ft===$k?'btn-primary':'btn-ghost'?>"><?=$l?></a>
  <?php endforeach;?>
</div>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper"><table>
    <thead><tr><th>Judul</th><th>Tipe</th><th>Aksi</th><th>Target</th><th>Reward</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($missions as $m): $ed=($edit&&$edit['id']==$m['id']); ?>
    <tr style="<?=$ed?'background:var(--cyan-dim)':''?>">
      <td><div style="font-weight:600;font-size:.875rem"><?=e(truncate($m['title'],35))?></div><?php if($m['description']):?><div style="font-size:.72rem;color:var(--text-muted)"><?=e(truncate($m['description'],35))?></div><?php endif;?></td>
      <td><span class="badge <?=match($m['type']){MISSION_DAILY=>'badge-info',MISSION_WEEKLY=>'badge-purple',default=>'badge-warning'}?>"><?=$typeLabels[$m['type']]??$m['type']?></span></td>
      <td><span class="badge badge-muted"><?=$actionLabels[$m['action_type']]??$m['action_type']?></span></td>
      <td style="text-align:center;font-weight:700"><?=$m['target_count']?>x</td>
      <td style="color:var(--text-success);font-weight:700"><?=formatRupiah($m['reward_value'])?></td>
      <td><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$m['id']?>"><button type="submit" class="badge <?=$m['is_active']?'badge-success':'badge-muted'?>" style="cursor:pointer;border:none"><?=$m['is_active']?'✅':'⏸'?></button></form></td>
      <td><a href="?edit=<?=$m['id']?>&type=<?=$ft?>" class="btn-ghost btn-sm">✏️</a><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$m['id']?>"><button type="submit" class="btn-danger btn-sm" onclick="return confirm('Nonaktifkan?')">🗑</button></form></td>
    </tr>
    <?php endforeach;if(empty($missions)):?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada misi</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px"><?=$edit?'Edit Misi':'Tambah Misi'?></h3>
  <form method="POST">
    <?=csrfField()?><input type="hidden" name="action" value="save">
    <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
    <div class="form-group"><label class="form-label">Judul *</label><input type="text" name="title" class="form-control" value="<?=e($edit['title']??'')?>" required></div>
    <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"><?=e($edit['description']??'')?></textarea></div>
    <div class="form-group"><label class="form-label">Tipe</label>
      <select name="type" class="form-control"><?php foreach($typeLabels as $k=>$l):?><option value="<?=$k?>" <?=($edit['type']??MISSION_DAILY)===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select>
    </div>
    <div class="form-group"><label class="form-label">Tipe Aksi</label>
      <select name="action_type" class="form-control"><?php foreach($actionLabels as $k=>$l):?><option value="<?=$k?>" <?=($edit['action_type']??ACTION_LOGIN)===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select>
    </div>
    <div class="form-group"><label class="form-label">Target (kali)</label><input type="number" name="target_count" class="form-control" value="<?=(int)($edit['target_count']??1)?>" min="1" required></div>
    <div class="form-group"><label class="form-label">Reward (Rp)</label><input type="number" name="reward_value" class="form-control" value="<?=$edit['reward_value']??0?>" min="0"></div>
    <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?=(int)($edit['sort_order']??0)?>"></div>
    <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" id="ma" <?=(!$edit||$edit['is_active'])?'checked':''?>><label for="ma">Aktif</label></div>
    <div style="display:flex;gap:8px"><button type="submit" class="btn-primary"><?=$edit?'Simpan':'Tambah'?></button><?php if($edit):?><a href="<?=BASE_URL?>/admin/missions.php" class="btn-ghost">Batal</a><?php endif;?></div>
  </form>
</div>
</div>
<?php adminFooter(); ?>
