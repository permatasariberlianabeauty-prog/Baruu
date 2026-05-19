<?php
http_response_code(503);
header('Retry-After: 3600');
// $message may be passed from bootstrap
$maintenanceMsg = $message ?? 'Website sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.';
$siteName = defined('SITE_NAME') ? SITE_NAME : 'NOXARA';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="refresh" content="60">
<title>Pemeliharaan | <?= $siteName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#0A0E1A;color:#F0F4FF;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;text-align:center}
.wrap{max-width:520px}
.icon{font-size:5rem;animation:pulse 2s ease infinite;margin-bottom:16px}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.05);}}
.brand{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;background:linear-gradient(135deg,#00D4FF,#7B2FFF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:20px}
h1{font-size:1.5rem;font-weight:800;margin-bottom:10px}
p{color:#8899BB;line-height:1.7;margin-bottom:20px}
.timer{font-size:1rem;color:#00D4FF;font-weight:600;margin-top:8px}
.progress{height:4px;background:#1a2240;border-radius:2px;overflow:hidden;margin:20px 0}
.progress-inner{height:100%;background:linear-gradient(90deg,#00D4FF,#7B2FFF);border-radius:2px;animation:progressAnim 60s linear infinite}
@keyframes progressAnim{from{width:0}to{width:100%}}
.bg{position:fixed;inset:0;z-index:-1;pointer-events:none;overflow:hidden}
.bg::before{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(0,212,255,.07),transparent 70%);top:-100px;right:-100px}
</style>
</head>
<body>
<div class="bg"></div>
<div class="wrap">
  <div class="icon">🔧</div>
  <div class="brand"><?= htmlspecialchars($siteName) ?></div>
  <h1>Mode Pemeliharaan</h1>
  <p><?= htmlspecialchars($maintenanceMsg) ?></p>
  <div class="progress"><div class="progress-inner"></div></div>
  <div class="timer">Halaman akan otomatis refresh dalam 60 detik</div>
  <p style="font-size:.8rem;color:#4A5880;margin-top:24px">Mohon maaf atas ketidaknyamanan ini 🙏</p>
</div>
</body>
</html>
