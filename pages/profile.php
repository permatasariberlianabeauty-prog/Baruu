<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$user   = db()->fetchOne('SELECT * FROM users WHERE id = ?', 'i', $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if ($_POST['action'] === 'update_profile') {
        $result = updateProfile($userId, $_POST);
        if ($result['success']) setFlash('success', $result['message']);
        else setFlash('error', $result['message']);
    } elseif ($_POST['action'] === 'toggle_theme') {
        $theme = $_POST['theme'] === 'light' ? 'light' : 'dark';
        db()->execute('UPDATE users SET theme = ? WHERE id = ?', 'si', $theme, $userId);
        $_SESSION['user_theme'] = $theme;
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

// Reload user after update
$user = db()->fetchOne('SELECT * FROM users WHERE id = ?', 'i', $userId);
$loginLogs = db()->fetchAll("SELECT * FROM user_login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", 'i', $userId);
$vipInfo   = getUserVipInfo($userId);
$wallet    = getUserWallet($userId);

$pageTitle = 'Profil Saya';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Profil Saya</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Profil</div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start">

<!-- Avatar & Summary -->
<div>
  <div class="card" style="text-align:center;margin-bottom:16px">
    <div style="position:relative;width:96px;height:96px;margin:0 auto 12px">
      <img src="<?= avatarUrl($user['avatar']) ?>" id="avatarPreview" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--border-active)" onerror="this.src='<?= ASSETS_URL ?>/img/default-avatar.png'">
      <label style="position:absolute;bottom:0;right:0;width:28px;height:28px;background:var(--cyan);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--bg-primary)" title="Ganti foto">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="white" stroke-width="2"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="white" stroke-width="2"/></svg>
        <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">
      </label>
    </div>
    <h2 style="font-size:1.1rem;font-weight:800"><?= e($user['full_name']) ?></h2>
    <div style="color:var(--text-muted);font-size:.85rem">@<?= e($user['username']) ?></div>
    <div style="margin:8px 0"><?= vipBadge($vipInfo['level']) ?></div>
    <div style="font-size:.8rem;color:var(--text-muted)">Bergabung <?= formatDate($user['created_at']) ?></div>
  </div>

  <!-- Wallet Summary -->
  <div class="card" style="margin-bottom:16px">
    <h3 style="font-size:.9rem;font-weight:700;margin-bottom:12px">Ringkasan Saldo</h3>
    <?php foreach (['main_balance'=>'Saldo Utama','profit_balance'=>'Saldo Profit','bonus_balance'=>'Saldo Bonus','referral_balance'=>'Saldo Referral'] as $k=>$l): ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:.83rem">
      <span style="color:var(--text-muted)"><?= $l ?></span>
      <strong><?= formatRupiah($wallet[$k]) ?></strong>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Theme Toggle -->
  <div class="card" style="margin-bottom:16px">
    <h3 style="font-size:.9rem;font-weight:700;margin-bottom:12px">Tampilan</h3>
    <form method="POST" style="display:flex;gap:8px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="toggle_theme">
      <input type="hidden" name="theme"  id="themeValue" value="<?= $user['theme'] === 'dark' ? 'light' : 'dark' ?>">
      <button type="submit" class="btn-ghost btn-block" onclick="document.getElementById('themeValue').value = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'">
        <?= $user['theme'] === 'dark' ? '☀️ Mode Terang' : '🌙 Mode Gelap' ?>
      </button>
    </form>
  </div>
</div>

<!-- Profile Form -->
<div>
  <div class="card" style="margin-bottom:20px">
    <h3 class="card-title" style="margin-bottom:20px">Edit Profil</h3>
    <form method="POST" enctype="multipart/form-data" data-loading>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update_profile">
      <!-- Hidden avatar from preview -->
      <input type="file" name="avatar" id="avatarInputForm" accept="image/*" style="display:none">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" value="@<?= e($user['username']) ?>" disabled style="opacity:.6">
          <div class="form-hint">Username tidak bisa diubah</div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled style="opacity:.6">
        </div>
        <div class="form-group">
          <label class="form-label">Nomor HP <span class="required">*</span></label>
          <input type="tel" name="phone" class="form-control" value="<?= e($user['phone']) ?>" required>
        </div>
      </div>
      <button type="submit" class="btn-primary">Simpan Perubahan</button>
    </form>
  </div>

  <!-- Login History -->
  <div class="card">
    <h3 class="card-title" style="margin-bottom:14px">Riwayat Login</h3>
    <?php if (!empty($loginLogs)): ?>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>IP Address</th><th>Perangkat</th><th>Status</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php foreach ($loginLogs as $log): ?>
          <tr>
            <td style="font-family:monospace;font-size:.82rem"><?= e($log['ip_address'] ?? '-') ?></td>
            <td style="font-size:.78rem;color:var(--text-muted)"><?= e(truncate($log['user_agent'] ?? '-', 40)) ?></td>
            <td>
              <?php if ($log['status'] === 'success'): ?>
              <span class="badge badge-success">Berhasil</span>
              <?php elseif ($log['status'] === 'blocked'): ?>
              <span class="badge badge-danger">Diblokir</span>
              <?php else: ?>
              <span class="badge badge-warning">Gagal</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= timeAgo($log['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>Belum ada riwayat login</p></div>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
function previewAvatar(input) {
  if (input.files?.[0]) {
    const url = URL.createObjectURL(input.files[0]);
    document.getElementById('avatarPreview').src = url;
    // Copy file to form input
    const dt = new DataTransfer();
    dt.items.add(input.files[0]);
    document.getElementById('avatarInputForm').files = dt.files;
  }
}
// Sync avatar file input
document.getElementById('avatarInput')?.addEventListener('change', function() {
  previewAvatar(this);
});
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
