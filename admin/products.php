<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $admin  = getSessionAdmin();

    if ($action === 'save_product') {
        $id         = (int)($_POST['id'] ?? 0);
        $catId      = (int)$_POST['category_id'];
        $name       = trim($_POST['name'] ?? '');
        $price      = (float)$_POST['price'];
        $profitDay  = (float)$_POST['profit_per_day'];
        $duration   = (int)$_POST['duration_days'];
        $isActive   = isset($_POST['is_active']) ? 1 : 0;
        $desc       = trim($_POST['description'] ?? '');
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);
        $minVip     = (int)($_POST['min_vip_level'] ?? 0);

        $imgFilename = null;
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'products');
            if ($upload['success']) $imgFilename = $upload['filename'];
        }

        if ($id) {
            $sql = 'UPDATE products SET category_id=?,name=?,price=?,profit_per_day=?,duration_days=?,description=?,is_active=?,sort_order=?,min_vip_level=?' . ($imgFilename ? ',image=?' : '') . ' WHERE id=?';
            $types = 'isddiiiiii' . ($imgFilename ? 'si' : 'i');
            $params = [$catId,$name,$price,$profitDay,$duration,$desc,$isActive,$sortOrder,$minVip];
            if ($imgFilename) $params[] = $imgFilename;
            $params[] = $id;
            db()->execute($sql, $types, ...$params);
        } else {
            db()->execute('INSERT INTO products (category_id,name,slug,price,profit_per_day,duration_days,description,is_active,sort_order,min_vip_level,image) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                'issddiisiii', $catId, $name, slugify($name), $price, $profitDay, $duration, $desc, $isActive, $sortOrder, $minVip, $imgFilename);
        }
        logAdminAction($admin['id'], $id ? 'edit_product' : 'add_product', 'product', $id ?: db()->lastInsertId());
        setFlash('success', 'Produk berhasil disimpan.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->execute('UPDATE products SET is_active=0 WHERE id=?', 'i', $id);
        setFlash('success', 'Produk dinonaktifkan.');
    }

    if ($action === 'save_category') {
        $cid  = (int)($_POST['cat_id'] ?? 0);
        $cname= trim($_POST['cat_name'] ?? '');
        $color= trim($_POST['cat_color'] ?? '#00D4FF');
        $sort = (int)($_POST['cat_sort'] ?? 0);
        if ($cid) { db()->execute('UPDATE product_categories SET name=?,color=?,sort_order=? WHERE id=?', 'ssii', $cname, $color, $sort, $cid); }
        else { db()->execute('INSERT INTO product_categories (name,slug,color,sort_order) VALUES (?,?,?,?)', 'sssi', $cname, slugify($cname), $color, $sort); }
        setFlash('success', 'Kategori disimpan.');
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$categories = db()->fetchAll('SELECT * FROM product_categories ORDER BY sort_order');
$products   = db()->fetchAll('SELECT p.*,pc.name as cat_name FROM products p JOIN product_categories pc ON p.category_id=pc.id ORDER BY pc.sort_order, p.sort_order');
$editProduct = isset($_GET['edit']) ? db()->fetchOne('SELECT * FROM products WHERE id=?','i',(int)$_GET['edit']) : null;

adminHeader('Produk Mining', 'products');
?>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
<div>
<!-- Product Form -->
<div class="card" style="margin-bottom:20px">
  <h3 class="card-title" style="margin-bottom:16px"><?= $editProduct ? 'Edit Produk' : 'Tambah Produk' ?></h3>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_product">
    <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= $editProduct['id'] ?>"><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="form-group"><label class="form-label">Kategori</label>
        <select name="category_id" class="form-control" required>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= ($editProduct && $editProduct['category_id']==$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Nama Produk</label><input type="text" name="name" class="form-control" value="<?= e($editProduct['name'] ?? '') ?>" required></div>
      <div class="form-group"><label class="form-label">Harga (Rp)</label><input type="number" name="price" class="form-control" value="<?= $editProduct['price'] ?? '' ?>" required></div>
      <div class="form-group"><label class="form-label">Profit/Hari (Rp)</label><input type="number" name="profit_per_day" class="form-control" id="profitDay" value="<?= $editProduct['profit_per_day'] ?? '' ?>" oninput="calcRoi()" required></div>
      <div class="form-group"><label class="form-label">Durasi (hari)</label><input type="number" name="duration_days" class="form-control" value="<?= $editProduct['duration_days'] ?? 30 ?>" oninput="calcRoi()" required></div>
      <div class="form-group"><label class="form-label">Min VIP Level</label><select name="min_vip_level" class="form-control"><?php for($i=0;$i<=5;$i++): ?><option value="<?= $i ?>" <?= ($editProduct && $editProduct['min_vip_level']==$i)?'selected':'' ?>>VIP <?= $i ?></option><?php endfor; ?></select></div>
      <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= $editProduct['sort_order'] ?? 0 ?>"></div>
      <div class="form-group"><label class="form-label">Gambar</label><input type="file" name="image" class="form-control" accept="image/*"></div>
    </div>
    <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2"><?= e($editProduct['description'] ?? '') ?></textarea></div>
    <div id="roiPreview" style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:10px;margin-bottom:12px;font-size:.85rem;color:var(--cyan)"></div>
    <div class="form-check" style="margin-bottom:14px"><input type="checkbox" name="is_active" id="isActive" <?= (!$editProduct || $editProduct['is_active'])?'checked':'' ?>><label for="isActive">Aktif</label></div>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn-primary">Simpan Produk</button>
      <?php if ($editProduct): ?><a href="<?= BASE_URL ?>/admin/products.php" class="btn-ghost">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- Product List -->
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Produk</th><th>Harga</th><th>Profit/Hari</th><th>ROI</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><div style="font-weight:600"><?= e($p['name']) ?></div><div style="font-size:.72rem;color:var(--text-muted)"><?= e($p['cat_name']) ?></div></td>
          <td><?= formatRupiah($p['price']) ?></td>
          <td style="color:var(--text-success)"><?= formatRupiah($p['profit_per_day']) ?></td>
          <td style="color:var(--text-warning)"><?= number_format(($p['profit_per_day']*$p['duration_days']/$p['price'])*100,0) ?>%</td>
          <td><?= $p['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Nonaktif</span>' ?></td>
          <td>
            <a href="?edit=<?= $p['id'] ?>" class="btn-ghost btn-sm">Edit</a>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Nonaktifkan?')">Nonaktif</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- Categories -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px">Kategori</h3>
  <form method="POST" style="margin-bottom:16px">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_category">
    <div class="form-group"><label class="form-label">Nama Kategori</label><input type="text" name="cat_name" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Warna</label><input type="color" name="cat_color" class="form-control" value="#00D4FF" style="height:44px"></div>
    <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="cat_sort" class="form-control" value="0"></div>
    <button type="submit" class="btn-primary btn-sm">Simpan</button>
  </form>
  <?php foreach ($categories as $c): ?>
  <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--border)">
    <span style="width:12px;height:12px;border-radius:50%;background:<?= e($c['color']) ?>;flex-shrink:0"></span>
    <span style="flex:1;font-size:.875rem"><?= e($c['name']) ?></span>
    <a href="?edit_cat=<?= $c['id'] ?>" class="btn-ghost btn-sm" style="font-size:.72rem">Edit</a>
  </div>
  <?php endforeach; ?>
</div>
</div>

<script>
function calcRoi() {
  const price     = parseFloat(document.querySelector('[name=price]')?.value) || 0;
  const profitDay = parseFloat(document.querySelector('[name=profit_per_day]')?.value) || 0;
  const duration  = parseInt(document.querySelector('[name=duration_days]')?.value) || 30;
  const preview   = document.getElementById('roiPreview');
  if (price > 0 && profitDay > 0) {
    const totalProfit = profitDay * duration;
    const roi         = (totalProfit / price * 100).toFixed(2);
    preview.textContent = `ROI: ${roi}% | Total Profit: Rp${totalProfit.toLocaleString('id-ID')} | Modal kembali setelah ${duration} hari`;
  } else { preview.textContent = ''; }
}
document.querySelector('[name=price]')?.addEventListener('input', calcRoi);
calcRoi();
</script>
<?php adminFooter(); ?>
