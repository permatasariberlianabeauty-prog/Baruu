<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
checkMaintenance();

if (isLoggedIn()) { redirect('pages/dashboard.php'); }

$message = '';
$type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Format email tidak valid.';
        $type    = 'error';
    } else {
        $result  = createPasswordReset($email);
        $message = $result['message'];
        $type    = 'success';

        // DEV: show token link in debug mode
        if (APP_DEBUG && isset($result['token'])) {
            $message .= ' [DEV] <a href="' . BASE_URL . '/auth/reset_password.php?token=' . $result['token'] . '">Reset link</a>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lupa Password — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<style>
  .auth-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .auth-card { width:100%; max-width:420px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-xl); padding:36px 32px; animation:fadeInUp .5s ease; }
  .auth-logo { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:24px; }
  .auth-icon-wrap { width:64px; height:64px; border-radius:50%; background:var(--cyan-dim); border:2px solid var(--border-active); display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
</style>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <span class="orbitron text-gradient" style="font-size:1.3rem;font-weight:900"><?= SITE_NAME ?></span>
    </div>
    <div class="auth-icon-wrap">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="#00D4FF" stroke-width="1.7"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="#00D4FF" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="16" r="1.5" fill="#00D4FF"/></svg>
    </div>
    <h1 style="font-size:1.3rem;font-weight:800;text-align:center;margin-bottom:6px">Lupa Password?</h1>
    <p style="text-align:center;color:var(--text-muted);font-size:.875rem;margin-bottom:24px">Masukkan email terdaftar. Kami akan mengirimkan link reset password.</p>

    <?php if ($message): ?>
    <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?>" style="margin-bottom:18px">
      <?= $message ?>
    </div>
    <?php endif; ?>

    <?php if ($type !== 'success'): ?>
    <form method="POST" action="" data-loading>
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Alamat Email</label>
        <input type="email" name="email" class="form-control" placeholder="email@contoh.com"
          value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>
      <button type="submit" class="btn-primary btn-block">Kirim Link Reset</button>
    </form>
    <?php else: ?>
    <div style="text-align:center;margin-top:8px">
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn-secondary" style="display:inline-flex">Kembali ke Login</a>
    </div>
    <?php endif; ?>

    <div style="text-align:center;margin-top:20px;font-size:.875rem;color:var(--text-muted)">
      Ingat password? <a href="<?= BASE_URL ?>/auth/login.php" style="color:var(--cyan);font-weight:600">Masuk</a>
    </div>
  </div>
</div>
<script src="<?= ASSETS_URL ?>/js/main.js"></script>
</body>
</html>
