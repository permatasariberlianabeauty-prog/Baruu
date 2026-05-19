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
        $id=$id=(int)($_POST['id']??0); $title=trim($_POST['title']??''); $link=trim($_POST['link_url']??''); $sort=(int)($_POST['sort_order']??0); $active=isset($_POST['is_active'])?1:0;
        $img=null;
        if (!empty($_FILES['image']['name'])) { $u=uploadImage($_FILES['image'],'banners'); if(!$u['success']){setFlash('error',$u['message']);header('Location:'.$_SERVER['PHP_SELF']);exit;} $img=$u['filename']; }
        if ($id) { if($img){$old=db()->fetchOne('SELECT image FROM banners WHERE id=?','i',$id);if($old&&$old['image'])deleteUploadedFile($old['image'],'banners');db()->execute('UPDATE banners SET title=?,url=?,image=?,sort_order=?,is_active=? WHERE id=?','ssssii',$title,$link,$img,$sort,$active,$id);}else{db()->execute('UPDATE banners SET title=?,url=?,sort_order=?,is_active=? WHERE id=?','sssii',$title,$link,$sort,$active,$id);} setFlash('success','Banner diperbarui.'); }
        else { if(!$img){setFlash('error','Gambar wajib diupload.');header('Location:'.$_SERVER['PHP_SELF']);exit;} db()->execute('INSERT INTO banners(title,url,image,sort_order,is_active) VALUES(?,?,?,?,?)','sssii',$title,$link,$img,$sort,$active); setFlash('success','Banner ditambahkan.'); }
        logAdminAction($admin['id'],'save_banner','banner',$id);
    }
    if ($action==='delete'){$id=(int)$_POST['id'];$b=db()->fetchOne('SELECT image FROM banners WHERE id=?','i',$id);if($b&&$b['image'])deleteUploadedFile($b['image'],'banners');db()->execute('DELETE FROM banners WHERE id=?','i',$id);setFlash('success','Banner dihapus.');}
    if ($action==='toggle'){$id=(int)$_POST['id'];$c=db()->fetchOne('SELECT is_active FROM banners WHERE id=?','i',$id);if($c)db()->execute('UPDATE banners SET is_active=? WHERE id=?','ii',$c['is_active']?0:1,$id);}
    header('Location:'.$_SERVER['PHP_SELF']);exit;
}
$edit=isset($_GET['edit'])?db()->fetchOne('SELECT * FROM banners WHERE id=?','i',(int)$_GET['edit']):null;
$banners=db()->fetchAll('SELECT * FROM banners ORDER BY sort_order ASC');
adminHeader('Manajemen Banner','banners');
?>
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px">
<div>
<?php foreach($banners as $b): ?>
<div class="card" style="display:flex;align-items:center;gap:14px;margin-bottom:12px;<?=($edit&&$edit['id']==$b['id'])?'border-color:var(--cyan)':''?>">
  <a href="<?=UPLOADS_URL?>/banners/<?=e($b['image'])?>" target="_blank"><img src="<?=UPLOADS_URL?>/banners/<?=e($b['image'])?>" style="width:120px;height:60px;object-fit:cover;border-radius:var(--radius-sm)"></a>
  <div style="flex:1"><div style="font-weight:600"><?=e($b['title']?:'Banner #'.$b['id'])?></div><div style="font-size:.72rem;color:var(--text-muted)">Sort: <?=$b['sort_order']?> · <?=$b['is_active']?'<span style="color:var(--text-success)">Aktif</span>':'<span style="color:var(--text-muted)">Nonaktif</span>'?></div></div>
  <div style="display:flex;gap:6px">
    <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$b['id']?>"><button type="submit" class="btn-sm <?=$b['is_active']?'btn-success':'btn-ghost'?>"><?=$b['is_active']?'✅':'⏸'?></button></form>
    <a href="?edit=<?=$b['id']?>" class="btn-ghost btn-sm">✏️</a>
    <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$b['id']?>"><button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus?')">🗑</button></form>
  </div>
</div>
<?php endforeach; ?>
<?php if(empty($banners)): ?><div class="empty-state card" style="padding:32px"><p>Belum ada banner</p></div><?php endif; ?>
</div>
<div class="card">
  <h3 class="card-title" style="margin-bottom:16px"><?=$edit?'Edit Banner':'Tambah Banner'?></h3>
  <form method="POST" enctype="multipart/form-data">
    <?=csrfField()?>
    <input type="hidden" name="action" value="save">
    <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
    <div class="form-group"><label class="form-label">Gambar <?=$edit?'(kosongkan jika tidak diganti)':'<span style="color:var(--text-danger)">*</span>'?></label><input type="file" name="image" class="form-control" accept="image/*" <?=$edit?'':'required'?>>
    <?php if($edit&&$edit['image']):?><img src="<?=UPLOADS_URL?>/banners/<?=e($edit['image'])?>" style="width:100%;margin-top:8px;border-radius:var(--radius-sm)"><?php endif;?></div>
    <div class="form-group"><label class="form-label">Judul / Alt</label><input type="text" name="title" class="form-control" value="<?=e($edit['title']??'')?>"></div>
    <div class="form-group"><label class="form-label">Link URL</label><input type="url" name="link_url" class="form-control" value="<?=e($edit['url']??'')?>"></div>
    <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?=(int)($edit['sort_order']??count($banners))?>"></div>
    <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" id="ba" <?=(!$edit||$edit['is_active'])?'checked':''?>><label for="ba">Tampilkan</label></div>
    <div style="display:flex;gap:8px"><button type="submit" class="btn-primary"><?=$edit?'Simpan':'Tambah'?></button><?php if($edit):?><a href="<?=BASE_URL?>/admin/banners.php" class="btn-ghost">Batal</a><?php endif;?></div>
  </form>
</div>
</div>
<?php adminFooter(); ?>
