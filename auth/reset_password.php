<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
checkMaintenance();

if (isLoggedIn()) { redirect('pages/dashboard.php'); }

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = false;

// Validate token
$resetRecord = null;
if ($token) {
    $resetRecord = db()->fetchOne(
        'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL',
        's', $token
    );
}

if (!$token || !$resetRecord) {
    $error = 'Link reset tidak valid atau sudah expired. Silakan minta reset password baru.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRecord) {
    validateCsrf();
    $newPassword = $_POST['password']         ?? '';
    $confirm     = $_POST['password_confirm'] ?? '';

    if ($newPassword !== $confirm) {
        $error = 'Password dan konfirmasi tidak cocok.';
    } else {
        $result = resetPassword($token, $newPassword);
        if ($result['success']) {
            $success = true;
            setFlash('success', 'Password berhasil direset! Silakan login.');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<style>
  .auth-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .auth-card { width:100%; max-width:420px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-xl); padding:36px 32px; animation:fadeInUp .5s ease; }
</style>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div style="text-align:center;margin-bottom:24px">
      <span class="orbitron text-gradient" style="font-size:1.3rem;font-weight:900"><?= SITE_NAME ?></span>
    </div>
    <h1 style="font-size:1.3rem;font-weight:800;text-align:center;margin-bottom:6px">Reset Password</h1>
    <p style="text-align:center;color:var(--text-muted);font-size:.875rem;margin-bottom:24px">Masukkan password baru untuk akun Anda.</p>

    <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom:16px">
      <?= e($error) ?>
      <?php if (!$resetRecord): ?>
      <div style="margin-top:10px">
        <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="btn-primary btn-sm">Minta Reset Baru</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:16px">
      Password berhasil direset! Silakan login dengan password baru.
    </div>
    <div style="text-align:center">
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn-primary">Masuk Sekarang</a>
    </div>
    <?php elseif ($resetRecord && !$error): ?>
    <form method="POST" action="?token=<?= urlencode($token) ?>" data-loading>
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Password Baru <span class="required">*</span></label>
        <div class="input-group">
          <input type="password" id="newPw" name="password" class="form-control" placeholder="Min 8 karakter" required autocomplete="new-password">
          <button type="button" class="input-group-append" data-pw-toggle="newPw">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
          </button>
        </div>
        <div class="form-hint">Min 8 karakter, mengandung huruf kapital & angka</div>
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
        <div class="input-group">
          <input type="password" id="confPw" name="password_confirm" class="form-control" placeholder="Ulangi password baru" required autocomplete="new-password">
          <button type="button" class="input-group-append" data-pw-toggle="confPw">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-primary btn-block">Reset Password</button>
    </form>
    <?php endif; ?>

    <div style="text-align:center;margin-top:20px;font-size:.875rem;color:var(--text-muted)">
      <a href="<?= BASE_URL ?>/auth/login.php" style="color:var(--cyan)">← Kembali ke Login</a>
    </div>
  </div>
</div>
<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<script>
document.querySelector('form')?.addEventListener('submit', function(e) {
  const pw  = document.getElementById('newPw')?.value;
  const pw2 = document.getElementById('confPw')?.value;
  if (pw && pw2 && pw !== pw2) {
    e.preventDefault();
    alert('Password dan konfirmasi tidak cocok!');
  }
});
</script>
</body>
</html>
