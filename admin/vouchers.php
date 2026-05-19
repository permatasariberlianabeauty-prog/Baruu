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
        $id=(int)($_POST['id']??0);$code=strtoupper(trim($_POST['code']??''));$type=$_POST['type']??'deposit';
        $dType=$_POST['discount_type']??'percent';$dVal=(float)($_POST['discount_value']??0);
        $minAmt=(float)($_POST['min_amount']??0);$maxDisc=((float)$_POST['max_discount']>0)?(float)$_POST['max_discount']:null;
        $minVip=(int)($_POST['min_vip_level']??0);$limit=((int)$_POST['usage_limit']>0)?(int)$_POST['usage_limit']:null;
        $validFrom=$_POST['valid_from']?:null;$validUntil=$_POST['valid_until']?:null;$active=isset($_POST['is_active'])?1:0;
        if(!$code){setFlash('error','Kode voucher wajib diisi.');header('Location:'.$_SERVER['PHP_SELF']);exit;}
        if($id){
            db()->execute('UPDATE vouchers SET code=?,type=?,discount_type=?,discount_value=?,min_amount=?,max_discount=?,min_vip_level=?,usage_limit=?,valid_from=?,valid_until=?,is_active=? WHERE id=?','sssdddiissii',$code,$type,$dType,$dVal,$minAmt,$maxDisc,$minVip,$limit,$validFrom,$validUntil,$active,$id);
            setFlash('success','Voucher diperbarui.');
        } else {
            $exists=db()->fetchOne('SELECT id FROM vouchers WHERE code=?','s',$code);
            if($exists){setFlash('error','Kode sudah ada.');header('Location:'.$_SERVER['PHP_SELF']);exit;}
            db()->execute('INSERT INTO vouchers(code,type,discount_type,discount_value,min_amount,max_discount,min_vip_level,usage_limit,valid_from,valid_until,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?)','sssdddiissii',$code,$type,$dType,$dVal,$minAmt,$maxDisc,$minVip,$limit,$validFrom,$validUntil,$active);
            setFlash('success','Voucher dibuat.');
        }
        logAdminAction($admin['id'],$id?'edit_voucher':'add_voucher','voucher',$id,$code);
    }
    if($action==='deactivate'){db()->execute('UPDATE vouchers SET is_active=0 WHERE id=?','i',(int)$_POST['id']);setFlash('success','Voucher dinonaktifkan.');}
    if($action==='delete'){db()->execute('DELETE FROM vouchers WHERE id=? AND used_count=0','i',(int)$_POST['id']);setFlash('success','Voucher dihapus.');}
    header('Location:'.$_SERVER['PHP_SELF']);exit;
}

$edit=isset($_GET['edit'])?db()->fetchOne('SELECT * FROM vouchers WHERE id=?','i',(int)$_GET['edit']):null;
$vouchers=db()->fetchAll('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100');

adminHeader('Manajemen Voucher','vouchers');
?>
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px">
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper"><table>
    <thead><tr><th>Kode</th><th>Tipe</th><th>Diskon</th><th>Min VIP</th><th>Pemakaian</th><th>Exp</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($vouchers as $v):
      $isExp=$v['valid_until']&&strtotime($v['valid_until'])<time();
      $badge=!$v['is_active']?'<span class="badge badge-muted">Off</span>':($isExp?'<span class="badge badge-danger">Expired</span>':'<span class="badge badge-success">Aktif</span>');
    ?>
    <tr style="<?=($edit&&$edit['id']==$v['id'])?'background:var(--cyan-dim)':''?>">
      <td><code style="color:var(--cyan);font-weight:700"><?=e($v['code'])?></code></td>
      <td><span class="badge badge-info"><?=$v['type']?></span></td>
      <td style="color:var(--text-success);"><?=$v['discount_type']==='percent'?$v['discount_value'].'%':formatRupiah($v['discount_value'])?></td>
      <td><?=$v['min_vip_level']>0?vipBadge($v['min_vip_level']):'Semua'?></td>
      <td><?=number_format($v['used_count']).'/'.(($v['usage_limit'])?number_format($v['usage_limit']):'∞')?></td>
      <td style="font-size:.75rem;color:var(--text-muted)"><?=$v['valid_until']?formatDate($v['valid_until']):'∞'?></td>
      <td><?=$badge?></td>
      <td>
        <a href="?edit=<?=$v['id']?>" class="btn-ghost btn-sm">✏️</a>
        <?php if($v['is_active']):?><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?=$v['id']?>"><button type="submit" class="btn-ghost btn-sm" style="color:var(--text-warning)" onclick="return confirm('Nonaktifkan?')">⏸</button></form><?php endif;?>
        <?php if(!$v['used_count']):?><form method="POST" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$v['id']?>"><button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus?')">🗑</button></form><?php endif;?>
      </td>
    </tr>
    <?php endforeach;if(empty($vouchers)):?><tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada voucher</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px"><?=$edit?'Edit Voucher':'Buat Voucher'?></h3>
  <form method="POST">
    <?=csrfField()?><input type="hidden" name="action" value="save">
    <?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
    <div class="form-group"><label class="form-label">Kode *</label>
      <div style="display:flex;gap:6px"><input type="text" name="code" id="vc" class="form-control" value="<?=e($edit['code']??'')?>" style="font-family:monospace;font-weight:700;text-transform:uppercase" required>
      <button type="button" class="btn-ghost btn-sm" onclick="genCode()">🎲</button></div>
    </div>
    <div class="form-group"><label class="form-label">Tipe</label>
      <select name="type" class="form-control"><?php foreach(['deposit'=>'Deposit','product'=>'Beli Paket'] as $k=>$l):?><option value="<?=$k?>" <?=($edit['type']??'deposit')===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select>
    </div>
    <div class="form-group"><label class="form-label">Tipe Diskon</label>
      <select name="discount_type" class="form-control"><?php foreach(['percent'=>'Persen (%)','nominal'=>'Nominal (Rp)'] as $k=>$l):?><option value="<?=$k?>" <?=($edit['discount_type']??'percent')===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select>
    </div>
    <div class="form-group"><label class="form-label">Nilai Diskon</label><input type="number" name="discount_value" class="form-control" value="<?=$edit['discount_value']??0?>" min="0" step="0.01"></div>
    <div class="form-group"><label class="form-label">Min. Transaksi (Rp)</label><input type="number" name="min_amount" class="form-control" value="<?=$edit['min_amount']??0?>" min="0"></div>
    <div class="form-group"><label class="form-label">Max Diskon (Rp, kosong=∞)</label><input type="number" name="max_discount" class="form-control" value="<?=$edit['max_discount']??0?>" min="0"></div>
    <div class="form-group"><label class="form-label">Min VIP Level</label>
      <select name="min_vip_level" class="form-control"><?php for($i=0;$i<=5;$i++):?><option value="<?=$i?>" <?=($edit['min_vip_level']??0)==$i?'selected':''?>>VIP <?=$i?></option><?php endfor;?></select>
    </div>
    <div class="form-group"><label class="form-label">Limit Pakai (0=∞)</label><input type="number" name="usage_limit" class="form-control" value="<?=(int)($edit['usage_limit']??0)?>" min="0"></div>
    <div class="form-group"><label class="form-label">Berlaku Dari</label><input type="datetime-local" name="valid_from" class="form-control" value="<?=$edit['valid_from']?date('Y-m-d\TH:i',strtotime($edit['valid_from'])):''?>"></div>
    <div class="form-group"><label class="form-label">Berlaku Sampai</label><input type="datetime-local" name="valid_until" class="form-control" value="<?=$edit['valid_until']?date('Y-m-d\TH:i',strtotime($edit['valid_until'])):''?>"></div>
    <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" id="va" <?=(!$edit||$edit['is_active'])?'checked':''?>><label for="va">Aktif</label></div>
    <div style="display:flex;gap:8px"><button type="submit" class="btn-primary"><?=$edit?'Simpan':'Buat'?></button><?php if($edit):?><a href="<?=BASE_URL?>/admin/vouchers.php" class="btn-ghost">Batal</a><?php endif;?></div>
  </form>
</div>
</div>
<script>
function genCode(){const c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';let s='';for(let i=0;i<8;i++)s+=c[Math.floor(Math.random()*c.length)];document.getElementById('vc').value=s;}
</script>
<?php adminFooter(); ?>
