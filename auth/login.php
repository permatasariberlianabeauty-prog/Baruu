<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
checkMaintenance();

if (isLoggedIn()) { redirect('pages/dashboard.php'); }

$error   = '';
$referer = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $ip         = getClientIp();
    $ua         = getUserAgent();

    if (empty($identifier) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $result = loginUser2($identifier, $password, $ip, $ua);
        if ($result['success']) {
            loginUser($result['user']);
            $_SESSION['show_popup'] = 'login';
            $dest = !empty($referer) ? $referer : BASE_URL . '/pages/dashboard.php';
            header('Location: ' . $dest); exit;
        } else {
            $error = $result['message'];
        }
    }
}
$pageTitle = 'Masuk';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<style>
  .auth-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; background:var(--bg-primary); }
  .auth-card  { width:100%; max-width:420px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-xl); padding:36px 32px; animation:fadeInUp .5s ease; }
  .auth-logo  { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:28px; }
  .auth-title { font-size:1.5rem; font-weight:800; text-align:center; margin-bottom:4px; }
  .auth-sub   { text-align:center; color:var(--text-muted); font-size:.875rem; margin-bottom:28px; }
  .auth-footer{ text-align:center; margin-top:22px; font-size:.875rem; color:var(--text-muted); }
  .auth-footer a { color:var(--cyan); font-weight:600; }
  .divider-or { display:flex; align-items:center; gap:12px; margin:20px 0; color:var(--text-muted); font-size:.8rem; }
  .divider-or::before,.divider-or::after { content:''; flex:1; height:1px; background:var(--border); }
  .auth-bg-deco { position:fixed; inset:0; pointer-events:none; overflow:hidden; z-index:0; }
  .auth-bg-deco::before { content:''; position:absolute; width:400px; height:400px; border-radius:50%; background:radial-gradient(circle, rgba(0,212,255,.08) 0%, transparent 70%); top:-100px; right:-100px; }
  .auth-bg-deco::after  { content:''; position:absolute; width:350px; height:350px; border-radius:50%; background:radial-gradient(circle, rgba(123,47,255,.08) 0%, transparent 70%); bottom:-80px; left:-80px; }
  .auth-card { position:relative; z-index:1; }
</style>
</head>
<body style="background:var(--bg-primary)">
<div class="auth-bg-deco"></div>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <svg width="36" height="36" viewBox="0 0 28 28" fill="none">
        <polygon points="14,2 26,8 26,20 14,26 2,20 2,8" fill="none" stroke="url(#alg)" stroke-width="2"/>
        <path d="M9 11l5 3 5-3M14 14v7" stroke="url(#alg)" stroke-width="1.5" stroke-linecap="round"/>
        <defs><linearGradient id="alg" x1="0" y1="0" x2="28" y2="28"><stop stop-color="#00D4FF"/><stop offset="1" stop-color="#7B2FFF"/></linearGradient></defs>
      </svg>
      <span class="orbitron text-gradient" style="font-size:1.4rem;font-weight:900"><?= SITE_NAME ?></span>
    </div>
    <h1 class="auth-title">Selamat Datang Kembali</h1>
    <p class="auth-sub">Masuk ke akun <?= SITE_NAME ?> Anda</p>

    <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom:18px">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="currentColor" stroke-width="2"/></svg>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" data-loading>
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Username / Email</label>
        <div class="input-group">
          <span class="input-group-prepend">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.7"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          </span>
          <input type="text" name="identifier" class="form-control has-prepend"
            placeholder="Username atau email" value="<?= e($_POST['identifier'] ?? '') ?>" required autocomplete="username">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between">
          Password
          <a href="<?= BASE_URL ?>/auth/forgot_password.php" style="font-size:.8rem;color:var(--cyan)">Lupa password?</a>
        </label>
        <div class="input-group">
          <span class="input-group-prepend">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          </span>
          <input type="password" id="passwordInput" name="password" class="form-control has-prepend"
            placeholder="Masukkan password" required autocomplete="current-password">
          <button type="button" class="input-group-append" data-pw-toggle="passwordInput" style="cursor:pointer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" fill="none"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-primary btn-block">Masuk</button>
    </form>

    <div class="divider-or">atau</div>
    <a href="<?= BASE_URL ?>/auth/register.php<?= $referer ? '?redirect=' . urlencode($referer) : '' ?>" class="btn-ghost btn-block" style="text-align:center">
      Buat Akun Baru
    </a>
    <div class="auth-footer">
      Dengan masuk, Anda menyetujui <a href="<?= BASE_URL ?>/pages/info.php?tab=tnc">Syarat &amp; Ketentuan</a> kami.
    </div>
  </div>
</div>
<script src="<?= ASSETS_URL ?>/js/main.js"></script>
</body>
</html>
