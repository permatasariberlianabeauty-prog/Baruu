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
    if ($action === 'save_vip') {
        $id = (int)$_POST['id'];
        db()->execute('UPDATE vip_levels SET min_deposit_required=?,min_withdraw=?,withdraw_fee_percent=?,max_withdraw_per_day=?,daily_withdraw_limit=?,color=? WHERE id=?',
            'ddddisi',
            (float)$_POST['min_deposit_required'],(float)$_POST['min_withdraw'],(float)$_POST['withdraw_fee_percent'],
            (int)$_POST['max_withdraw_per_day'],(float)$_POST['daily_withdraw_limit'],trim($_POST['color']??'#888888'),$id);
        logAdminAction($admin['id'],'edit_vip_level','vip_level',$id);
        setFlash('success','VIP level disimpan.');
    }
    if ($action === 'save_code') {
        $cid=(int)($_POST['code_id']??0);$code=strtoupper(trim($_POST['code']??''));$vipLv=(int)$_POST['vip_level'];$active=isset($_POST['is_active'])?1:0;
        if($cid){db()->execute('UPDATE vip_codes SET code=?,vip_level=?,is_active=? WHERE id=?','ssii',$code,$vipLv,$active,$cid);}
        else{db()->execute('INSERT INTO vip_codes(code,vip_level,is_active) VALUES(?,?,?)','sii',$code,$vipLv,$active);}
        setFlash('success','VIP code disimpan.');
    }
    if($action==='delete_code'){db()->execute('DELETE FROM vip_codes WHERE id=?','i',(int)$_POST['code_id']);setFlash('success','Code dihapus.');}
    header('Location:'.$_SERVER['PHP_SELF']);exit;
}

$vipLevels=db()->fetchAll('SELECT * FROM vip_levels ORDER BY level ASC');
$vipCodes=db()->fetchAll('SELECT * FROM vip_codes ORDER BY vip_level ASC');
$editLevel=isset($_GET['edit'])?(int)$_GET['edit']:null;
$editCode=isset($_GET['edit_code'])?db()->fetchOne('SELECT * FROM vip_codes WHERE id=?','i',(int)$_GET['edit_code']):null;
$names=['Pemula','Bronze','Silver','Gold','Platinum','Diamond'];
$colors=['#888888','#CD7F32','#C0C0C0','#FFD700','#00D4FF','#7B2FFF'];

adminHeader('Setting VIP','vip_settings');
?>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px">
<div>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:20px">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">🏆 Level VIP</div>
  <div class="table-wrapper"><table>
    <thead><tr><th>Level</th><th>Min Deposit</th><th>Min WD</th><th>Fee%</th><th>Maks WD/Hari</th><th>Limit Harian</th><th>Warna</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($vipLevels as $vl): $lv=(int)$vl['level']; $isE=$editLevel===$lv; ?>
    <tr style="<?=$isE?'background:var(--cyan-dim)':''?>">
      <?php if($isE): ?>
      <td colspan="8"><form method="POST" style="padding:4px 0">
        <?=csrfField()?><input type="hidden" name="action" value="save_vip"><input type="hidden" name="id" value="<?=$vl['id']?>">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span style="font-weight:800;color:<?=e($vl['color']??$colors[$lv])?>">VIP <?=$lv?> <?=$names[$lv]??''?></span>
          <?php foreach(['min_deposit_required'=>'Min Dep','min_withdraw'=>'Min WD','withdraw_fee_percent'=>'Fee%','max_withdraw_per_day'=>'Maks WD','daily_withdraw_limit'=>'Limit Harian','color'=>'Warna'] as $f=>$l): ?>
          <div style="display:flex;flex-direction:column;gap:2px">
            <label style="font-size:.65rem;color:var(--text-muted)"><?=$l?></label>
            <input type="<?=$f==='color'?'color':'number'?>" name="<?=$f?>" class="form-control" value="<?=e($vl[$f]??'')?>" style="width:<?=$f==='color'?'50px':'110px'?>;min-height:34px;padding:4px 8px;font-size:.8rem" step="0.01">
          </div>
          <?php endforeach; ?>
          <div style="display:flex;gap:6px;margin-top:14px"><button type="submit" class="btn-success btn-sm">💾</button><a href="<?=BASE_URL?>/admin/vip_settings.php" class="btn-ghost btn-sm">✕</a></div>
        </div>
      </form></td>
      <?php else: ?>
      <td><span style="font-weight:800;color:<?=e($vl['color']??$colors[$lv])?>">VIP <?=$lv?><br><small><?=$names[$lv]??''?></small></span></td>
      <td><?=formatRupiah($vl['min_deposit_required'])?></td>
      <td><?=formatRupiah($vl['min_withdraw'])?></td>
      <td><?=number_format($vl['withdraw_fee_percent'],2)?>%</td>
      <td><?=$vl['max_withdraw_per_day']?>x</td>
      <td><?=formatRupiah($vl['daily_withdraw_limit'])?></td>
      <td><span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:<?=e($vl['color']??$colors[$lv])?>"></span></td>
      <td><a href="?edit=<?=$lv?>" class="btn-ghost btn-sm">✏️</a></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">🔑 VIP Codes</div>
  <div class="table-wrapper"><table>
    <thead><tr><th>Kode</th><th>Level</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($vipCodes as $vc): ?>
    <tr>
      <td><code style="color:var(--cyan)"><?=e($vc['code'])?></code></td>
      <td><?=vipBadge($vc['vip_level'])?></td>
      <td><?=$vc['is_active']?'<span class="badge badge-success">Aktif</span>':'<span class="badge badge-muted">Off</span>'?></td>
      <td><a href="?edit_code=<?=$vc['id']?>" class="btn-ghost btn-sm">✏️</a>
      <form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete_code"><input type="hidden" name="code_id" value="<?=$vc['id']?>"><button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus?')">🗑</button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
</div>
<div>
  <div class="card" style="margin-bottom:16px">
    <h3 class="card-title" style="margin-bottom:14px"><?=$editCode?'Edit Code':'Tambah VIP Code'?></h3>
    <form method="POST">
      <?=csrfField()?><input type="hidden" name="action" value="save_code">
      <?php if($editCode):?><input type="hidden" name="code_id" value="<?=$editCode['id']?>"><?php endif;?>
      <div class="form-group"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="<?=e($editCode['code']??'')?>" style="font-family:monospace;text-transform:uppercase" required></div>
      <div class="form-group"><label class="form-label">Untuk Level VIP</label>
        <select name="vip_level" class="form-control"><?php for($i=0;$i<=5;$i++):?><option value="<?=$i?>" <?=($editCode['vip_level']??0)==$i?'selected':''?>>VIP <?=$i?> — <?=$names[$i]??''?></option><?php endfor;?></select>
      </div>
      <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" id="coa" <?=(!$editCode||$editCode['is_active'])?'checked':''?>><label for="coa">Aktif</label></div>
      <div style="display:flex;gap:8px"><button type="submit" class="btn-primary"><?=$editCode?'Simpan':'Tambah'?></button><?php if($editCode):?><a href="<?=BASE_URL?>/admin/vip_settings.php" class="btn-ghost">Batal</a><?php endif;?></div>
    </form>
  </div>
  <div class="card">
    <div style="font-weight:700;margin-bottom:10px;font-size:.9rem">📊 Member per VIP</div>
    <?php $total=db()->fetchOne('SELECT COUNT(*) as c FROM users')['c']??1; for($i=0;$i<=5;$i++): $cnt=db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE vip_level=?','i',$i)['c']??0; ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
      <span style="width:55px;font-size:.75rem;color:<?=$colors[$i]?>;font-weight:700">VIP <?=$i?></span>
      <div style="flex:1;background:var(--bg-input);border-radius:var(--radius-full);height:8px;overflow:hidden"><div style="width:<?=min(100,round($cnt/$total*100))?>%;height:100%;background:<?=$colors[$i]?>;border-radius:inherit"></div></div>
      <span style="font-size:.75rem;color:var(--text-muted);min-width:28px;text-align:right"><?=number_format($cnt)?></span>
    </div>
    <?php endfor; ?>
  </div>
</div>
</div>
<?php adminFooter(); ?>
