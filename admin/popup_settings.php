<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── Default popup events (seed if missing) ────────────────
$defaultEvents = [
    ['login_success',      'Login Berhasil',        '🎉 Selamat Datang!',         'Selamat datang kembali di NOXARA!',                 '🔑'],
    ['register_success',   'Registrasi Berhasil',   '🎊 Akun Berhasil Dibuat',    'Selamat bergabung! Mulai investasi sekarang.',      '🎊'],
    ['deposit_confirmed',  'Deposit Dikonfirmasi',  '✅ Deposit Berhasil!',        'Deposit Anda telah dikonfirmasi dan saldo aktif.',   '💳'],
    ['withdraw_approved',  'Penarikan Disetujui',   '💸 Penarikan Diproses',       'Penarikan sedang diproses ke rekening Anda.',        '💸'],
    ['vip_upgrade',        'Naik VIP',              '🏆 Selamat! VIP Naik!',       'Anda telah berhasil naik ke level VIP lebih tinggi!','🏆'],
    ['mining_complete',    'Mining Selesai',         '⛏️ Profit Mining Masuk!',    'Hasil mining telah dikreditkan ke saldo profit.',    '⛏️'],
    ['daily_reward',       'Hadiah Harian',          '🎁 Klaim Hadiah Harian',      'Kamu mendapatkan hadiah harian! Klaim sekarang.',   '🎁'],
    ['mission_complete',   'Misi Selesai',           '🎯 Misi Berhasil!',           'Selamat! Misi kamu berhasil diselesaikan.',          '🎯'],
    ['referral_bonus',     'Bonus Referral',         '👥 Bonus Referral Diterima!', 'Downline Anda melakukan transaksi, komisi masuk!',   '👥'],
    ['voucher_applied',    'Voucher Dipakai',        '🎫 Voucher Aktif!',           'Voucher berhasil diterapkan ke akun kamu.',          '🎫'],
    ['package_purchased',  'Paket Dibeli',           '⛏️ Paket Aktif!',            'Paket mining Anda telah aktif dan mulai berjalan.',  '⛏️'],
    ['profile_updated',    'Profil Diperbarui',      '✅ Profil Berhasil Diubah',   'Data profil Anda telah berhasil disimpan.',          '👤'],
];

// Seed missing events
foreach ($defaultEvents as [$key, $eventName, $title, $message, $icon]) {
    $exists = db()->fetchOne('SELECT id FROM popup_settings WHERE event_key = ?', 's', $key);
    if (!$exists) {
        db()->execute(
            'INSERT INTO popup_settings (event_key, event_name, title, message, icon, is_enabled) VALUES (?,?,?,?,?,1)',
            'sssss', $key, $eventName, $title, $message, $icon
        );
    }
}

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $icon    = trim($_POST['icon'] ?? '');
        $enabled = isset($_POST['is_enabled']) ? 1 : 0;

        db()->execute(
            'UPDATE popup_settings SET title=?,message=?,icon=?,is_enabled=? WHERE id=?',
            'sssii', $title, $message, $icon, $enabled, $id
        );
        logAdminAction($admin['id'], 'edit_popup', 'popup_setting', $id);
        setFlash('success', 'Popup berhasil disimpan.');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $cur = db()->fetchOne('SELECT is_enabled FROM popup_settings WHERE id = ?', 'i', $id);
        if ($cur) {
            db()->execute('UPDATE popup_settings SET is_enabled = ? WHERE id = ?', 'ii', $cur['is_enabled'] ? 0 : 1, $id);
        }
    }

    if ($action === 'toggle_all') {
        $val = (int)$_POST['enable_all'];
        db()->execute('UPDATE popup_settings SET is_enabled = ?', 'i', $val);
        setFlash('success', $val ? 'Semua popup diaktifkan.' : 'Semua popup dinonaktifkan.');
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$popups = db()->fetchAll('SELECT * FROM popup_settings ORDER BY id ASC');

adminHeader('Popup Settings', 'popup_settings');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
  <div style="font-size:.85rem;color:var(--text-muted)"><?= count($popups) ?> popup events tersedia. Klik baris untuk edit.</div>
  <div style="display:flex;gap:8px">
    <form method="POST" style="display:inline">
      <?= csrfField() ?><input type="hidden" name="action" value="toggle_all"><input type="hidden" name="enable_all" value="1">
      <button type="submit" class="btn-success btn-sm">✅ Aktifkan Semua</button>
    </form>
    <form method="POST" style="display:inline">
      <?= csrfField() ?><input type="hidden" name="action" value="toggle_all"><input type="hidden" name="enable_all" value="0">
      <button type="submit" class="btn-ghost btn-sm">⏸ Nonaktifkan Semua</button>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr <?= $editId ? '380px' : '' ?>;gap:20px;align-items:start">

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Icon</th><th>Event Key</th><th>Nama Event</th><th>Judul Popup</th><th>Pesan</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($popups)): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Tidak ada popup</td></tr>
        <?php else: ?>
        <?php foreach ($popups as $p): $isEditing = ($editId === (int)$p['id']); ?>
        <tr style="cursor:pointer;<?= $isEditing ? 'background:var(--cyan-dim)' : '' ?>" onclick="window.location='?edit=<?= $p['id'] ?>'">
          <td style="font-size:1.3rem"><?= e($p['icon']) ?></td>
          <td><code style="font-size:.72rem;color:var(--purple)"><?= e($p['event_key']) ?></code></td>
          <td style="font-size:.85rem;font-weight:500"><?= e($p['event_name']) ?></td>
          <td style="font-size:.82rem"><?= e(truncate($p['title'], 30)) ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= e(truncate($p['message'], 40)) ?></td>
          <td>
            <form method="POST" style="display:inline" onclick="event.stopPropagation()">
              <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="badge <?= $p['is_enabled'] ? 'badge-success' : 'badge-muted' ?>" style="cursor:pointer;border:none"><?= $p['is_enabled'] ? '✅ ON' : '⏸ OFF' ?></button>
            </form>
          </td>
          <td onclick="event.stopPropagation()">
            <a href="?edit=<?= $p['id'] ?>" class="btn-ghost btn-sm">✏️ Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Form -->
<?php if ($editId): ?>
<?php $editPopup = db()->fetchOne('SELECT * FROM popup_settings WHERE id = ?', 'i', $editId); ?>
<?php if ($editPopup): ?>
<div class="card" style="position:sticky;top:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <h3 style="font-size:.95rem;font-weight:700">✏️ Edit Popup</h3>
    <a href="<?= BASE_URL ?>/admin/popup_settings.php" class="btn-ghost btn-sm">✕</a>
  </div>

  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editPopup['id'] ?>">

    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:10px;margin-bottom:14px;font-size:.8rem">
      <span style="color:var(--text-muted)">Event: </span>
      <code style="color:var(--purple)"><?= e($editPopup['event_key']) ?></code>
      <span style="margin-left:8px;color:var(--text-secondary)"><?= e($editPopup['event_name']) ?></span>
    </div>

    <div class="form-group">
      <label class="form-label">Icon (emoji)</label>
      <input type="text" name="icon" class="form-control" value="<?= e($editPopup['icon']) ?>" placeholder="🎉" style="font-size:1.5rem;text-align:center">
    </div>
    <div class="form-group">
      <label class="form-label">Judul Popup</label>
      <input type="text" name="title" class="form-control" value="<?= e($editPopup['title']) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label">Pesan</label>
      <textarea name="message" class="form-control" rows="3" required><?= e($editPopup['message']) ?></textarea>
    </div>
    <div class="form-check" style="margin-bottom:16px">
      <input type="checkbox" name="is_enabled" id="popupEnabled" <?= $editPopup['is_enabled'] ? 'checked' : '' ?>>
      <label for="popupEnabled">Aktifkan popup ini</label>
    </div>

    <!-- Preview -->
    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:16px;margin-bottom:16px;text-align:center;border:1px solid var(--border)">
      <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase">Preview</div>
      <div style="font-size:2rem" id="prevIcon"><?= e($editPopup['icon']) ?></div>
      <div style="font-weight:700;margin:6px 0 4px" id="prevTitle"><?= e($editPopup['title']) ?></div>
      <div style="font-size:.8rem;color:var(--text-secondary)" id="prevMsg"><?= e($editPopup['message']) ?></div>
    </div>

    <button type="submit" class="btn-primary btn-block">💾 Simpan Popup</button>
  </form>
</div>
<?php endif; ?>
<?php endif; ?>

</div>

<script>
// Live preview
document.querySelector('[name=icon]')?.addEventListener('input', e => { document.getElementById('prevIcon').textContent = e.target.value; });
document.querySelector('[name=title]')?.addEventListener('input', e => { document.getElementById('prevTitle').textContent = e.target.value; });
document.querySelector('[name=message]')?.addEventListener('input', e => { document.getElementById('prevMsg').textContent = e.target.value; });
</script>

<?php adminFooter(); ?>
