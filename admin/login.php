<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();

if (isAdminLoggedIn()) { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = getClientIp();

    if ($username && $password) {
        $result = loginAdminUser($username, $password, $ip);
        if ($result['success']) {
            loginAdmin($result['admin']);
            logAdminAction($result['admin']['id'], 'login', '', 0, 'Admin login dari ' . $ip);
            header('Location: ' . BASE_URL . '/admin/index.php'); exit;
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Username dan password wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<style>
  .auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--bg-primary)}
  .auth-card{width:100%;max-width:400px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:36px 32px;animation:fadeInUp .5s ease}
</style>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div style="text-align:center;margin-bottom:24px">
      <div style="font-size:2.5rem;margin-bottom:8px">🛡️</div>
      <div class="orbitron text-gradient" style="font-size:1.2rem;font-weight:900"><?= SITE_NAME ?> Admin</div>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom:16px"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" data-loading>
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" placeholder="Admin username" value="<?= e($_POST['username'] ?? '') ?>" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-group">
          <input type="password" id="adminPw" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
          <button type="button" class="input-group-append" data-pw-toggle="adminPw">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-primary btn-block">Masuk ke Panel Admin</button>
    </form>
    <div style="text-align:center;margin-top:16px">
      <a href="<?= BASE_URL ?>/" style="font-size:.85rem;color:var(--text-muted)">← Kembali ke Website</a>
    </div>
  </div>
</div>
<script src="<?= ASSETS_URL ?>/js/main.js"></script>
</body>
</html>
