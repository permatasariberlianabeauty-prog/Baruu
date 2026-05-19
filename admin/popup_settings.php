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
        $id=$_POST['id'];$title=trim($_POST['title']??'');$msg=trim($_POST['message']??'');$icon=trim($_POST['icon']??'');$enabled=isset($_POST['is_enabled'])?1:0;
        db()->execute('UPDATE popup_settings SET title=?,message=?,icon=?,is_enabled=? WHERE id=?','sssii',$title,$msg,$icon,$enabled,$id);
        logAdminAction($admin['id'],'edit_popup','popup_setting',$id);
        setFlash('success','Popup disimpan.');
    }
    if ($action==='toggle'){$id=(int)$_POST['id'];$c=db()->fetchOne('SELECT is_enabled FROM popup_settings WHERE id=?','i',$id);if($c)db()->execute('UPDATE popup_settings SET is_enabled=? WHERE id=?','ii',$c['is_enabled']?0:1,$id);}
    if ($action==='toggle_all'){$v=(int)$_POST['enable_all'];db()->execute('UPDATE popup_settings SET is_enabled=?','i',$v);setFlash('success',$v?'Semua popup diaktifkan.':'Semua popup dinonaktifkan.');}
    header('Location:'.$_SERVER['PHP_SELF']);exit;
}
$editId=(int)($_GET['edit']??0);
$popups=db()->fetchAll('SELECT * FROM popup_settings ORDER BY id ASC');
adminHeader('Popup Settings','popup_settings');
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
  <div style="font-size:.85rem;color:var(--text-muted)"><?=count($popups)?> popup events</div>
  <div style="display:flex;gap:8px">
    <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="toggle_all"><input type="hidden" name="enable_all" value="1"><button type="submit" class="btn-success btn-sm">✅ Aktifkan Semua</button></form>
    <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="toggle_all"><input type="hidden" name="enable_all" value="0"><button type="submit" class="btn-ghost btn-sm">⏸ Nonaktifkan Semua</button></form>
  </div>
</div>
<div style="display:grid;grid-template-columns:1fr <?=$editId?'380px':''?>;gap:20px">
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper"><table>
    <thead><tr><th>Icon</th><th>Event Key</th><th>Nama</th><th>Judul</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($popups as $p): $ed=($editId==(int)$p['id']); ?>
    <tr style="cursor:pointer;<?=$ed?'background:var(--cyan-dim)':''?>" onclick="window.location='?edit=<?=$p['id']?>'">
      <td style="font-size:1.3rem"><?=e($p['icon'])?></td>
      <td><code style="font-size:.72rem;color:var(--purple)"><?=e($p['event_key'])?></code></td>
      <td style="font-size:.85rem;font-weight:500"><?=e($p['event_name'])?></td>
      <td style="font-size:.82rem"><?=e(truncate($p['title'],30))?></td>
      <td onclick="event.stopPropagation()">
        <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$p['id']?>"><button type="submit" class="badge <?=$p['is_enabled']?'badge-success':'badge-muted'?>" style="cursor:pointer;border:none"><?=$p['is_enabled']?'✅ ON':'⏸ OFF'?></button></form>
      </td>
      <td onclick="event.stopPropagation()"><a href="?edit=<?=$p['id']?>" class="btn-ghost btn-sm">✏️</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php if($editId&&($ep=db()->fetchOne('SELECT * FROM popup_settings WHERE id=?','i',$editId))): ?>
<div class="card" style="position:sticky;top:20px">
  <div style="display:flex;justify-content:space-between;margin-bottom:16px"><h3 style="font-size:.95rem;font-weight:700">✏️ Edit Popup</h3><a href="<?=BASE_URL?>/admin/popup_settings.php" class="btn-ghost btn-sm">✕</a></div>
  <form method="POST">
    <?=csrfField()?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$ep['id']?>">
    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:10px;margin-bottom:14px;font-size:.8rem"><span style="color:var(--text-muted)">Event: </span><code style="color:var(--purple)"><?=e($ep['event_key'])?></code></div>
    <div class="form-group"><label class="form-label">Icon</label><input type="text" name="icon" class="form-control" value="<?=e($ep['icon'])?>" style="font-size:1.5rem;text-align:center" oninput="document.getElementById('pi').textContent=this.value"></div>
    <div class="form-group"><label class="form-label">Judul</label><input type="text" name="title" class="form-control" value="<?=e($ep['title'])?>" required oninput="document.getElementById('pt').textContent=this.value"></div>
    <div class="form-group"><label class="form-label">Pesan</label><textarea name="message" class="form-control" rows="3" required oninput="document.getElementById('pm').textContent=this.value"><?=e($ep['message'])?></textarea></div>
    <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_enabled" id="pe" <?=$ep['is_enabled']?'checked':''?>><label for="pe">Aktifkan</label></div>
    <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;margin-bottom:14px;text-align:center">
      <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:8px">Preview</div>
      <div id="pi" style="font-size:2rem"><?=e($ep['icon'])?></div>
      <div id="pt" style="font-weight:700;margin:6px 0 4px"><?=e($ep['title'])?></div>
      <div id="pm" style="font-size:.8rem;color:var(--text-secondary)"><?=e($ep['message'])?></div>
    </div>
    <button type="submit" class="btn-primary btn-block">💾 Simpan</button>
  </form>
</div>
<?php endif; ?>
</div>
<?php adminFooter(); ?>
