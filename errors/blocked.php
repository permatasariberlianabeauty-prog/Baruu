<?php
http_response_code(403);
$siteName = defined('SITE_NAME') ? SITE_NAME : 'NOXARA';
$contactWa = defined('BASE_URL') ? '' : '';
if (defined('ROOT_PATH')) {
    require_once ROOT_PATH . '/config/config.php';
    require_once ROOT_PATH . '/config/database.php';
    try {
        $contact = Database::getInstance()->fetchOne('SELECT whatsapp FROM contact_settings LIMIT 1');
        $contactWa = $contact['whatsapp'] ?? '';
    } catch(Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Akun Diblokir | <?= $siteName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#0A0E1A;color:#F0F4FF;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;text-align:center}
.wrap{max-width:480px}
.icon{font-size:5rem;margin-bottom:16px}
.brand{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:900;background:linear-gradient(135deg,#00D4FF,#7B2FFF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:20px}
h1{font-size:1.4rem;font-weight:800;margin-bottom:10px;color:#FF4466}
p{color:#8899BB;line-height:1.7;margin-bottom:20px}
.btn{display:inline-flex;align-items:center;gap:6px;background:rgba(0,212,255,.12);color:#00D4FF;border:1px solid rgba(0,212,255,.3);padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:700;font-size:.875rem;transition:all .2s;margin:0 5px}
.btn:hover{background:rgba(0,212,255,.2)}
.alert{background:rgba(255,68,102,.1);border:1px solid rgba(255,68,102,.3);border-radius:12px;padding:16px;margin-bottom:20px;font-size:.875rem;color:#FF4466}
</style>
</head>
<body>
<div class="wrap">
  <div class="icon">🚫</div>
  <div class="brand"><?= htmlspecialchars($siteName) ?></div>
  <div class="alert">
    <strong>Akun Anda Telah Diblokir</strong><br>
    Akun Anda tidak dapat mengakses layanan ini saat ini.
  </div>
  <h1>Akses Ditolak</h1>
  <p>Akun Anda telah diblokir oleh administrator. Jika Anda merasa ini adalah kesalahan, silakan hubungi tim Customer Service kami untuk mendapatkan bantuan.</p>
  <?php if ($contactWa): ?>
  <a href="https://wa.me/<?= htmlspecialchars($contactWa) ?>?text=Halo+admin,+akun+saya+diblokir.+Mohon+bantuan." target="_blank" rel="noopener" class="btn">
    💬 Hubungi CS via WhatsApp
  </a>
  <?php endif; ?>
  <a href="mailto:<?= defined('SITE_EMAIL') ? SITE_EMAIL : 'support@noxara.id' ?>" class="btn">
    ✉️ Email Support
  </a>
  <p style="font-size:.78rem;color:#4A5880;margin-top:24px">
    <?= htmlspecialchars($siteName) ?> · Selalu bermain dengan jujur dan sesuai aturan.
  </p>
</div>
</body>
</html>
