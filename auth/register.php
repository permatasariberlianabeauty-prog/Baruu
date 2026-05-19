<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
checkMaintenance();

if (isLoggedIn()) { redirect('pages/dashboard.php'); }

$error   = '';
$success = '';
$refCode = trim($_GET['ref'] ?? '');

// Pre-fill referral info
$referrerInfo = null;
if ($refCode) {
    $referrerInfo = db()->fetchOne('SELECT full_name FROM users WHERE referral_code = ?', 's', strtoupper($refCode));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    // Captcha verify
    $captchaInput = trim($_POST['captcha'] ?? '');
    if (!verifyCaptcha($captchaInput)) {
        $error = 'Jawaban captcha salah. Coba lagi.';
    } elseif (empty($_POST['agree'])) {
        $error = 'Anda harus menyetujui syarat dan ketentuan.';
    } else {
        $result = registerUser([
            'full_name'     => trim($_POST['full_name']     ?? ''),
            'username'      => trim($_POST['username']      ?? ''),
            'email'         => trim($_POST['email']         ?? ''),
            'phone'         => trim($_POST['phone']         ?? ''),
            'password'      => $_POST['password']           ?? '',
            'referral_code' => strtoupper(trim($_POST['referral_code'] ?? '')),
        ]);

        if ($result['success']) {
            $_SESSION['show_popup'] = 'register';
            setFlash('success', 'Registrasi berhasil! Silakan login.');
            header('Location: ' . BASE_URL . '/auth/login.php'); exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Generate new captcha
$captcha = generateCaptcha();
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<style>
  .auth-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; background:var(--bg-primary); }
  .auth-card  { width:100%; max-width:480px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-xl); padding:32px; animation:fadeInUp .5s ease; }
  .auth-logo  { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:22px; }
  .auth-title { font-size:1.4rem; font-weight:800; text-align:center; margin-bottom:4px; }
  .auth-sub   { text-align:center; color:var(--text-muted); font-size:.875rem; margin-bottom:24px; }
  .auth-footer{ text-align:center; margin-top:18px; font-size:.875rem; color:var(--text-muted); }
  .auth-footer a { color:var(--cyan); font-weight:600; }
  .ref-banner { background:var(--cyan-dim); border:1px solid var(--border-active); border-radius:var(--radius-md); padding:10px 14px; font-size:.85rem; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .captcha-box { display:flex; align-items:center; gap:10px; }
  .captcha-question { background:var(--bg-input); border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 16px; font-weight:700; font-size:1rem; color:var(--cyan); white-space:nowrap; }
  .auth-bg-deco { position:fixed; inset:0; pointer-events:none; overflow:hidden; z-index:0; }
  .auth-bg-deco::before { content:''; position:absolute; width:400px; height:400px; border-radius:50%; background:radial-gradient(circle, rgba(0,212,255,.08) 0%, transparent 70%); top:-100px; right:-100px; }
  .auth-bg-deco::after  { content:''; position:absolute; width:350px; height:350px; border-radius:50%; background:radial-gradient(circle, rgba(123,47,255,.08) 0%, transparent 70%); bottom:-80px; left:-80px; }
  .auth-card { position:relative; z-index:1; }
  @media(max-width:480px){ .form-row { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="auth-bg-deco"></div>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <svg width="32" height="32" viewBox="0 0 28 28" fill="none">
        <polygon points="14,2 26,8 26,20 14,26 2,20 2,8" fill="none" stroke="url(#rlg)" stroke-width="2"/>
        <path d="M9 11l5 3 5-3M14 14v7" stroke="url(#rlg)" stroke-width="1.5" stroke-linecap="round"/>
        <defs><linearGradient id="rlg" x1="0" y1="0" x2="28" y2="28"><stop stop-color="#00D4FF"/><stop offset="1" stop-color="#7B2FFF"/></linearGradient></defs>
      </svg>
      <span class="orbitron text-gradient" style="font-size:1.3rem;font-weight:900"><?= SITE_NAME ?></span>
    </div>
    <h1 class="auth-title">Buat Akun Baru</h1>
    <p class="auth-sub">Bergabung dan mulai investasi hari ini</p>

    <?php if ($referrerInfo): ?>
    <div class="ref-banner">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="#00D4FF" stroke-width="1.7"/><circle cx="9" cy="7" r="4" stroke="#00D4FF" stroke-width="1.7"/></svg>
      <span>Diundang oleh: <strong><?= e(maskName($referrerInfo['full_name'])) ?></strong></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom:16px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="16" r=".5" fill="currentColor" stroke="currentColor" stroke-width="1"/></svg>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" data-loading>
      <?= csrfField() ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="Nama lengkap" value="<?= e($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Username <span class="required">*</span></label>
          <input type="text" name="username" class="form-control" placeholder="4-20 karakter" value="<?= e($_POST['username'] ?? '') ?>" pattern="[a-zA-Z0-9_]{4,20}" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Email <span class="required">*</span></label>
        <input type="email" name="email" class="form-control" placeholder="email@contoh.com" value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Nomor HP <span class="required">*</span></label>
        <input type="tel" name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="<?= e($_POST['phone'] ?? '') ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password <span class="required">*</span></label>
          <div class="input-group">
            <input type="password" id="pwInput" name="password" class="form-control" placeholder="Min 8 karakter" required autocomplete="new-password">
            <button type="button" class="input-group-append" data-pw-toggle="pwInput">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
            </button>
          </div>
          <div class="form-hint">Min 8 karakter, huruf kapital & angka</div>
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
          <input type="password" id="pwConfirm" name="password_confirm" class="form-control" placeholder="Ulangi password" required autocomplete="new-password">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Kode Referral <span style="font-weight:400;color:var(--text-muted)">(opsional)</span></label>
        <input type="text" name="referral_code" class="form-control" placeholder="Kode referral (jika ada)"
          value="<?= e($refCode ?: ($_POST['referral_code'] ?? '')) ?>"
          <?= $refCode ? 'readonly style="background:var(--bg-hover)"' : '' ?>>
      </div>

      <div class="form-group">
        <label class="form-label">Verifikasi Anti-Bot <span class="required">*</span></label>
        <div class="captcha-box">
          <div class="captcha-question"><?= e($captcha['question']) ?></div>
          <input type="number" name="captcha" class="form-control" placeholder="Jawaban" required style="max-width:100px">
        </div>
      </div>

      <div class="form-check" style="margin-bottom:18px">
        <input type="checkbox" name="agree" id="agreeCheck" required>
        <label for="agreeCheck" style="font-size:.85rem;color:var(--text-secondary)">
          Saya menyetujui <a href="<?= BASE_URL ?>/pages/info.php?tab=tnc" target="_blank">Syarat &amp; Ketentuan</a>
          dan <a href="<?= BASE_URL ?>/pages/info.php?tab=privacy" target="_blank">Kebijakan Privasi</a>
        </label>
      </div>

      <button type="submit" class="btn-primary btn-block">Buat Akun Sekarang</button>
    </form>

    <div class="auth-footer">
      Sudah punya akun? <a href="<?= BASE_URL ?>/auth/login.php">Masuk di sini</a>
    </div>
  </div>
</div>
<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<script>
// Confirm password match
document.querySelector('form').addEventListener('submit', function(e) {
  const pw  = document.getElementById('pwInput').value;
  const pw2 = document.getElementById('pwConfirm').value;
  if (pw !== pw2) {
    e.preventDefault();
    alert('Password dan konfirmasi password tidak cocok.');
  }
});
</script>
</body>
</html>
