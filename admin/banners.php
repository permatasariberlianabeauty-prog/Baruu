<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $link_url  = trim($_POST['link_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        $imgFilename = null;
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'banners');
            if (!$upload['success']) { setFlash('error', $upload['message']); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
            $imgFilename = $upload['filename'];
        }

        if ($id) {
            if ($imgFilename) {
                $old = db()->fetchOne('SELECT image FROM banners WHERE id = ?', 'i', $id);
                if ($old && $old['image']) deleteUploadedFile($old['image'], 'banners');
                db()->execute('UPDATE banners SET title=?,link_url=?,image=?,sort_order=?,is_active=? WHERE id=?',
                    'ssssii', $title, $link_url, $imgFilename, $sortOrder, $isActive, $id);
            } else {
                db()->execute('UPDATE banners SET title=?,link_url=?,sort_order=?,is_active=? WHERE id=?',
                    'ssiii', $title, $link_url, $sortOrder, $isActive, $id);
            }
            logAdminAction($admin['id'], 'edit_banner', 'banner', $id);
            setFlash('success', 'Banner berhasil diperbarui.');
        } else {
            if (!$imgFilename) { setFlash('error', 'Gambar banner wajib diupload.'); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
            db()->execute('INSERT INTO banners (title, link_url, image, sort_order, is_active) VALUES (?,?,?,?,?)',
                'sssii', $title, $link_url, $imgFilename, $sortOrder, $isActive);
            logAdminAction($admin['id'], 'add_banner', 'banner', db()->lastInsertId());
            setFlash('success', 'Banner berhasil ditambahkan.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $b  = db()->fetchOne('SELECT image FROM banners WHERE id = ?', 'i', $id);
        if ($b && $b['image']) deleteUploadedFile($b['image'], 'banners');
        db()->execute('DELETE FROM banners WHERE id = ?', 'i', $id);
        logAdminAction($admin['id'], 'delete_banner', 'banner', $id);
        setFlash('success', 'Banner dihapus.');
    }

    if ($action === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = db()->fetchOne('SELECT is_active FROM banners WHERE id = ?', 'i', $id);
        if ($cur) db()->execute('UPDATE banners SET is_active = ? WHERE id = ?', 'ii', $cur['is_active'] ? 0 : 1, $id);
    }

    if ($action === 'reorder') {
        $orders = $_POST['orders'] ?? [];
        foreach ($orders as $bid => $ord) {
            db()->execute('UPDATE banners SET sort_order = ? WHERE id = ?', 'ii', (int)$ord, (int)$bid);
        }
        echo json_encode(['success' => true]); exit;
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$editBanner = isset($_GET['edit']) ? db()->fetchOne('SELECT * FROM banners WHERE id = ?', 'i', (int)$_GET['edit']) : null;
$banners    = db()->fetchAll('SELECT * FROM banners ORDER BY sort_order ASC, id ASC');

adminHeader('Manajemen Banner', 'banners');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<!-- Banner List -->
<div>
  <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:12px">🖼️ <?= count($banners) ?> banner aktif — urutan tampil sesuai sort order</div>

  <?php if (empty($banners)): ?>
  <div class="card" style="text-align:center;padding:40px;color:var(--text-muted)">
    <div style="font-size:2.5rem">🖼️</div>
    <div style="margin-top:10px">Belum ada banner. Tambahkan banner pertama!</div>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px" id="bannerList">
    <?php foreach ($banners as $b): ?>
    <div class="card" style="padding:14px;display:flex;align-items:center;gap:14px;<?= ($editBanner && $editBanner['id']==$b['id']) ? 'border-color:var(--cyan)' : '' ?>">
      <!-- Drag handle -->
      <div style="color:var(--text-muted);cursor:grab;font-size:1.2rem;padding:0 4px" title="Drag to reorder">⠿</div>
      <!-- Thumbnail -->
      <a href="<?= UPLOADS_URL ?>/banners/<?= e($b['image']) ?>" target="_blank">
        <img src="<?= UPLOADS_URL ?>/banners/<?= e($b['image']) ?>" style="width:120px;height:60px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border)">
      </a>
      <!-- Info -->
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:.9rem"><?= e($b['title'] ?: 'Banner #'.$b['id']) ?></div>
        <?php if ($b['link_url']): ?><div style="font-size:.72rem;color:var(--text-muted)"><?= e(truncate($b['link_url'], 50)) ?></div><?php endif; ?>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px">Sort: <?= (int)$b['sort_order'] ?> · <?= $b['is_active'] ? '<span style="color:var(--text-success)">Aktif</span>' : '<span style="color:var(--text-muted)">Nonaktif</span>' ?></div>
      </div>
      <!-- Actions -->
      <div style="display:flex;gap:6px;align-items:center">
        <form method="POST" style="display:inline">
          <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn-sm <?= $b['is_active'] ? 'btn-success' : 'btn-ghost' ?>" title="Toggle"><?= $b['is_active'] ? '✅' : '⏸' ?></button>
        </form>
        <a href="?edit=<?= $b['id'] ?>" class="btn-ghost btn-sm">✏️</a>
        <form method="POST" style="display:inline">
          <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus banner ini?')">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Form -->
<div class="card" style="position:sticky;top:20px">
  <h3 class="card-title" style="margin-bottom:16px"><?= $editBanner ? '✏️ Edit Banner' : '➕ Tambah Banner' ?></h3>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($editBanner): ?><input type="hidden" name="id" value="<?= $editBanner['id'] ?>"><?php endif; ?>

    <div class="form-group">
      <label class="form-label">Gambar Banner <?= $editBanner ? '(kosongkan jika tidak diganti)' : '<span style="color:var(--text-danger)">*</span>' ?></label>
      <input type="file" name="image" class="form-control" accept="image/*" <?= $editBanner ? '' : 'required' ?>>
      <div class="form-hint">Rekomendasi: 1200×400px, max 2MB</div>
      <?php if ($editBanner && $editBanner['image']): ?>
      <img src="<?= UPLOADS_URL ?>/banners/<?= e($editBanner['image']) ?>" style="width:100%;margin-top:8px;border-radius:var(--radius-sm);border:1px solid var(--border)">
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label">Judul / Alt Text</label>
      <input type="text" name="title" class="form-control" value="<?= e($editBanner['title'] ?? '') ?>" placeholder="Opsional">
    </div>

    <div class="form-group">
      <label class="form-label">Link URL (opsional)</label>
      <input type="url" name="link_url" class="form-control" value="<?= e($editBanner['link_url'] ?? '') ?>" placeholder="https://...">
    </div>

    <div class="form-group">
      <label class="form-label">Sort Order (urutan tampil)</label>
      <input type="number" name="sort_order" class="form-control" value="<?= (int)($editBanner['sort_order'] ?? count($banners)) ?>">
    </div>

    <div class="form-check" style="margin-bottom:16px">
      <input type="checkbox" name="is_active" id="bannerActive" <?= (!$editBanner || $editBanner['is_active']) ? 'checked' : '' ?>>
      <label for="bannerActive">Tampilkan banner</label>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit" class="btn-primary"><?= $editBanner ? '💾 Simpan' : '➕ Tambah' ?></button>
      <?php if ($editBanner): ?><a href="<?= BASE_URL ?>/admin/banners.php" class="btn-ghost">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

</div>

<?php adminFooter(); ?>
