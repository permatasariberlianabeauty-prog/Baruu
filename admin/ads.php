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
        $id=(int)($_POST['id']??0);$title=trim($_POST['title']??'');$url=trim($_POST['url']??'');
        $reward=(float)($_POST['reward_amount']??0);$wallet=$_POST['reward_wallet']??WALLET_BONUS;
        $dur=(int)($_POST['watch_duration']??30);$active=isset($_POST['is_active'])?1:0;
        if(!$title){setFlash('error','Judul wajib diisi.');header('Location:'.$_SERVER['PHP_SELF']);exit;}
        $img=null;
        if(!empty($_FILES['image']['name'])){$u=uploadImage($_FILES['image'],'ads');if(!$u['success']){setFlash('error',$u['message']);header('Location:'.$_SERVER['PHP_SELF']);exit;}$img=$u['filename'];}
        if($id){
            if($img){$old=db()->fetchOne('SELECT image FROM ads WHERE id=?','i',$id);if($old&&$old['image'])deleteUploadedFile($old['image'],'ads');db()->execute('UPDATE ads SET title=?,url=?,image=?,reward_amount=?,reward_wallet=?,watch_duration=?,is_active=? WHERE id=?','sssdssii',$title,$url,$img,$reward,$wallet,$dur,$active,$id);}
            else{db()->execute('UPDATE ads SET title=?,url=?,reward_amount=?,reward_wallet=?,watch_duration=?,is_active=? WHERE id=?','ssdssii',$title,$url,$reward,$wallet,$dur,$active,$id);}
            setFlash('success','Iklan diperbarui.');
        } else {
            db()->execute('INSERT INTO ads(title,url,image,reward_amount,reward_wallet,watch_duration,is_active) VALUES(?,?,?,?,?,?,?)','sssdssii',$title,$url,$img,$reward,$wallet,$dur,$active);
            setFlash('success','Iklan ditambahkan.');
        }
        logAdminAction($admin['id'],$id?'edit_ad':'add_ad','ad',$id);
    }
    if($action==='delete'){$id=(int)$_POST['id'];$a=db()->fetchOne('SELECT image FROM ads WHERE id=?','i',$id);if($a&&$a['image'])deleteUploadedFile($a['image'],'ads');db()->execute('DELETE FROM ads WHERE id=?','i',$id);setFlash('success','Iklan dihapus.');}
    if($action==='toggle'){$id=(int)$_POST['id'];$c=db()->fetchOne('SELECT is_active FROM ads WHERE id=?','i',$id);if($c)db()->execute('UPDATE ads SET is_active=? WHERE id=?','ii',$c['is_active']?0:1,$id);}
    if($action==='save_settings'){$adId=db()->fetchOne('SELECT id FROM ad_settings LIMIT 1')['id']??null;if($adId){db()->execute('UPDATE ad_settings SET max_per_day=?,cooldown_minutes=? WHERE id=?','iii',(int)$_POST['max_per_day'],(int)$_POST['cooldown_minutes'],$adId);}else{db()->execute('INSERT INTO ad_settings(max_per_day,cooldown_minutes) VALUES(?,?)','ii',(int)$_POST['max_per_day'],(int)$_POST['cooldown_minutes']);}setFlash('success','Setting iklan disimpan.');}
    header('Location:'.$_SERVER['PHP_SELF']);exit;
}

$edit=isset($_GET['edit'])?db()->fetchOne('SELECT * FROM ads WHERE id=?','i',(int)$_GET['edit']):null;
$ads=db()->fetchAll('SELECT * FROM ads ORDER BY created_at DESC');
$adSettings=db()->fetchOne('SELECT * FROM ad_settings LIMIT 1');

adminHeader('Manajemen Iklan','ads');
?>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">
<div>
  <div class="card" style="margin-bottom:16px">
    <h3 class="card-title" style="margin-bottom:16px"><?=$edit?'Edit Iklan':'Tambah Iklan'?></h3>
    <form method="POST" enctype="multipart/form-data">
      <?=csrfField()?><input type="hidden" name="action" value="save">
      <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" style="grid-column:1/-1"><label class="form-label">Judul *</label><input type="text" name="title" class="form-control" value="<?=e($edit['title']??'')?>" required></div>
        <div class="form-group" style="grid-column:1/-1"><label class="form-label">URL Tujuan</label><input type="url" name="url" class="form-control" value="<?=e($edit['url']??'')?>"></div>
        <div class="form-group"><label class="form-label">Reward (Rp)</label><input type="number" name="reward_amount" class="form-control" value="<?=$edit['reward_amount']??0?>" min="0"></div>
        <div class="form-group"><label class="form-label">Wallet Reward</label>
          <select name="reward_wallet" class="form-control"><?php foreach([WALLET_BONUS=>'Bonus',WALLET_MAIN=>'Utama'] as $k=>$l):?><option value="<?=$k?>" <?=($edit['reward_wallet']??WALLET_BONUS)===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select>
        </div>
        <div class="form-group"><label class="form-label">Durasi (detik)</label><input type="number" name="watch_duration" class="form-control" value="<?=$edit['watch_duration']??30?>" min="5" max="300"></div>
        <div class="form-group"><label class="form-label">Gambar</label><input type="file" name="image" class="form-control" accept="image/*"><?php if($edit&&$edit['image']):?><img src="<?=UPLOADS_URL?>/ads/<?=e($edit['image'])?>" style="height:50px;margin-top:4px;border-radius:var(--radius-sm)"><?php endif;?></div>
      </div>
      <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" id="aa" <?=(!$edit||$edit['is_active'])?'checked':''?>><label for="aa">Aktif</label></div>
      <div style="display:flex;gap:8px"><button type="submit" class="btn-primary"><?=$edit?'Simpan':'Tambah'?></button><?php if($edit):?><a href="<?=BASE_URL?>/admin/ads.php" class="btn-ghost">Batal</a><?php endif;?></div>
    </form>
  </div>
  <div class="card" style="padding:0;overflow:hidden">
    <div class="table-wrapper"><table>
      <thead><tr><th>Gambar</th><th>Judul</th><th>Reward</th><th>Durasi</th><th>Views</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if(empty($ads)):?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada iklan</td></tr><?php endif;?>
        <?php foreach($ads as $a):?>
        <tr>
          <td><?php if($a['image']):?><img src="<?=UPLOADS_URL?>/ads/<?=e($a['image'])?>" style="width:60px;height:38px;object-fit:cover;border-radius:var(--radius-sm)"><?php else:?><div style="width:60px;height:38px;background:var(--bg-card2);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:.65rem">No img</div><?php endif;?></td>
          <td style="font-weight:600;font-size:.875rem"><?=e(truncate($a['title'],40))?></td>
          <td style="color:var(--text-success);font-weight:600"><?=formatRupiah($a['reward_amount'])?></td>
          <td><?=$a['watch_duration']?>s</td>
          <td><?=number_format($a['total_views']??0)?></td>
          <td><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$a['id']?>"><button type="submit" class="badge <?=$a['is_active']?'badge-success':'badge-muted'?>" style="cursor:pointer;border:none"><?=$a['is_active']?'✅ Aktif':'⏸ Off'?></button></form></td>
          <td><a href="?edit=<?=$a['id']?>" class="btn-ghost btn-sm">✏️</a><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$a['id']?>"><button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus?')">🗑</button></form></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table></div>
  </div>
</div>
<div>
  <div class="card">
    <h3 style="font-size:.9rem;font-weight:700;margin-bottom:12px">⚙️ Setting Iklan</h3>
    <form method="POST">
      <?=csrfField()?><input type="hidden" name="action" value="save_settings">
      <div class="form-group"><label class="form-label">Maks Tonton/Hari</label><input type="number" name="max_per_day" class="form-control" value="<?=(int)($adSettings['max_per_day']??5)?>"></div>
      <div class="form-group"><label class="form-label">Cooldown (menit)</label><input type="number" name="cooldown_minutes" class="form-control" value="<?=(int)($adSettings['cooldown_minutes']??10)?>"></div>
      <button type="submit" class="btn-secondary btn-sm">💾 Simpan</button>
    </form>
    <?php $totalViews=db()->fetchOne('SELECT SUM(total_views) as t FROM ads')['t']??0; ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)"><div style="font-size:.75rem;color:var(--text-muted)">Total Views</div><div style="font-size:1.4rem;font-weight:800;color:var(--cyan)"><?=number_format($totalViews)?></div></div>
  </div>
</div>
</div>
<?php adminFooter(); ?>
