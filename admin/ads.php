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
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $url         = trim($_POST['url'] ?? '');
        $rewardAmt   = (float)($_POST['reward_amount'] ?? 0);
        $rewardWal   = $_POST['reward_wallet'] ?? WALLET_BONUS;
        $duration    = (int)($_POST['duration_seconds'] ?? 15);
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title)) { setFlash('error', 'Judul tidak boleh kosong.'); header('Location: ' . $_SERVER['PHP_SELF']); exit; }

        $imgFilename = null;
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadImage($_FILES['image'], 'ads');
            if (!$upload['success']) { setFlash('error', $upload['message']); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
            $imgFilename = $upload['filename'];
        }

        if ($id) {
            if ($imgFilename) {
                $old = db()->fetchOne('SELECT image FROM ads WHERE id = ?', 'i', $id);
                if ($old && $old['image']) deleteUploadedFile($old['image'], 'ads');
                db()->execute('UPDATE ads SET title=?,url=?,reward_amount=?,reward_wallet=?,duration_seconds=?,is_active=?,image=? WHERE id=?',
                    'ssdssisi', $title, $url, $rewardAmt, $rewardWal, $duration, $isActive, $imgFilename, $id);
            } else {
                db()->execute('UPDATE ads SET title=?,url=?,reward_amount=?,reward_wallet=?,duration_seconds=?,is_active=? WHERE id=?',
                    'ssdsisi', $title, $url, $rewardAmt, $rewardWal, $duration, $isActive, $id);
            }
            logAdminAction($admin['id'], 'edit_ad', 'ad', $id, $title);
            setFlash('success', 'Iklan berhasil diperbarui.');
        } else {
            db()->execute('INSERT INTO ads (title,url,image,reward_amount,reward_wallet,duration_seconds,is_active) VALUES (?,?,?,?,?,?,?)',
                'sssdssi', $title, $url, $imgFilename, $rewardAmt, $rewardWal, $duration, $isActive);
            $newId = db()->lastInsertId();
            logAdminAction($admin['id'], 'add_ad', 'ad', $newId, $title);
            setFlash('success', 'Iklan berhasil ditambahkan.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $ad = db()->fetchOne('SELECT image FROM ads WHERE id = ?', 'i', $id);
        if ($ad && $ad['image']) deleteUploadedFile($ad['image'], 'ads');
        db()->execute('DELETE FROM ads WHERE id = ?', 'i', $id);
        logAdminAction($admin['id'], 'delete_ad', 'ad', $id);
        setFlash('success', 'Iklan dihapus.');
    }

    if ($action === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = db()->fetchOne('SELECT is_active FROM ads WHERE id = ?', 'i', $id);
        if ($cur) {
            db()->execute('UPDATE ads SET is_active = ? WHERE id = ?', 'ii', $cur['is_active'] ? 0 : 1, $id);
            setFlash('success', 'Status iklan diperbarui.');
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// ── Data ──────────────────────────────────────────────────
$editAd = isset($_GET['edit']) ? db()->fetchOne('SELECT * FROM ads WHERE id = ?', 'i', (int)$_GET['edit']) : null;
$ads    = db()->fetchAll('SELECT * FROM ads ORDER BY created_at DESC');

adminHeader('Manajemen Iklan', 'ads');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div class="alert alert-<?= $t === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:14px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<!-- Ad Form -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:18px"><?= $editAd ? '✏️ Edit Iklan' : '➕ Tambah Iklan Baru' ?></h3>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($editAd): ?><input type="hidden" name="id" value="<?= $editAd['id'] ?>"><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Judul Iklan <span class="required" style="color:var(--text-danger)">*</span></label>
        <input type="text" name="title" class="form-control" value="<?= e($editAd['title'] ?? '') ?>" required>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">URL Tujuan (opsional)</label>
        <input type="url" name="url" class="form-control" value="<?= e($editAd['url'] ?? '') ?>" placeholder="https://...">
      </div>
      <div class="form-group">
        <label class="form-label">Reward Menonton (Rp)</label>
        <input type="number" name="reward_amount" class="form-control" value="<?= $editAd['reward_amount'] ?? 0 ?>" min="0" step="0.01">
      </div>
      <div class="form-group">
        <label class="form-label">Wallet Reward</label>
        <select name="reward_wallet" class="form-control">
          <?php foreach ([WALLET_BONUS=>'Bonus', WALLET_MAIN=>'Utama', WALLET_REFERRAL=>'Referral'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= ($editAd['reward_wallet'] ?? WALLET_BONUS) === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Durasi Tampil (detik)</label>
        <input type="number" name="duration_seconds" class="form-control" value="<?= $editAd['duration_seconds'] ?? 15 ?>" min="5" max="300">
      </div>
      <div class="form-group">
        <label class="form-label">Gambar Iklan</label>
        <input type="file" name="image" class="form-control" accept="image/*">
        <?php if ($editAd && $editAd['image']): ?>
        <div style="margin-top:6px"><img src="<?= UPLOADS_URL ?>/ads/<?= e($editAd['image']) ?>" style="height:50px;border-radius:var(--radius-sm);border:1px solid var(--border)"></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-check" style="margin-bottom:16px">
      <input type="checkbox" name="is_active" id="adActive" <?= (!$editAd || $editAd['is_active']) ? 'checked' : '' ?>>
      <label for="adActive" style="font-size:.875rem">Aktif (tampil ke member)</label>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit" class="btn-primary"><?= $editAd ? '💾 Simpan Perubahan' : '➕ Tambah Iklan' ?></button>
      <?php if ($editAd): ?><a href="<?= BASE_URL ?>/admin/ads.php" class="btn-ghost">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- Ad Settings -->
<?php $adSettings = db()->fetchOne('SELECT * FROM ad_settings LIMIT 1'); ?>
<div class="card">
  <h3 class="card-title" style="margin-bottom:14px">⚙️ Pengaturan Iklan</h3>
  <?php if ($adSettings): ?>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <div class="form-group"><label class="form-label">Maks Tonton/Hari</label>
      <input type="number" name="max_per_day" class="form-control" value="<?= (int)$adSettings['max_per_day'] ?>"></div>
    <div class="form-group"><label class="form-label">Cooldown (menit)</label>
      <input type="number" name="cooldown_minutes" class="form-control" value="<?= (int)$adSettings['cooldown_minutes'] ?>"></div>
    <button type="submit" class="btn-secondary btn-sm">Simpan</button>
  </form>
  <?php else: ?>
  <p style="font-size:.85rem;color:var(--text-muted)">Tabel ad_settings belum ada data.</p>
  <?php endif; ?>

  <?php $totalViews = db()->fetchOne('SELECT SUM(total_views) as t FROM ads')['t'] ?? 0; ?>
  <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
    <div style="font-size:.75rem;color:var(--text-muted)">Total Views</div>
    <div style="font-size:1.4rem;font-weight:800;color:var(--cyan)"><?= number_format($totalViews) ?></div>
  </div>
</div>

</div>

<!-- Ads Table -->
<div class="card" style="margin-top:20px;padding:0;overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-weight:700">📋 Daftar Iklan (<?= count($ads) ?>)</div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Gambar</th>
          <th>Judul</th>
          <th>Reward</th>
          <th>Durasi</th>
          <th>Views</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ads)): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Belum ada iklan</td></tr>
        <?php else: ?>
        <?php foreach ($ads as $ad): ?>
        <tr>
          <td>
            <?php if ($ad['image']): ?>
            <img src="<?= UPLOADS_URL ?>/ads/<?= e($ad['image']) ?>" style="width:60px;height:40px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border)">
            <?php else: ?>
            <div style="width:60px;height:40px;background:var(--bg-card2);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:.7rem">No img</div>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-weight:600"><?= e(truncate($ad['title'], 40)) ?></div>
            <?php if ($ad['url']): ?><div style="font-size:.72rem;color:var(--text-muted)"><?= e(truncate($ad['url'], 40)) ?></div><?php endif; ?>
          </td>
          <td style="color:var(--text-success);font-weight:600"><?= formatRupiah($ad['reward_amount']) ?><br><span style="font-size:.72rem;color:var(--text-muted)"><?= e($ad['reward_wallet']) ?></span></td>
          <td style="color:var(--text-secondary)"><?= (int)$ad['duration_seconds'] ?>d</td>
          <td><?= number_format($ad['total_views'] ?? 0) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $ad['id'] ?>">
              <button type="submit" class="badge <?= $ad['is_active'] ? 'badge-success' : 'badge-muted' ?>" style="cursor:pointer;border:none"><?= $ad['is_active'] ? '✅ Aktif' : '⏸ Nonaktif' ?></button>
            </form>
          </td>
          <td>
            <a href="?edit=<?= $ad['id'] ?>" class="btn-ghost btn-sm">✏️</a>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $ad['id'] ?>">
              <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus iklan ini?')">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminFooter(); ?>
