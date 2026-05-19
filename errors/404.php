<?php
http_response_code(404);
$siteName = defined('SITE_NAME') ? SITE_NAME : 'NOXARA';
$baseUrl  = defined('BASE_URL')  ? BASE_URL  : '';
$assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : '/assets';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>404 — Halaman Tidak Ditemukan | <?= $siteName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#0A0E1A;color:#F0F4FF;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{text-align:center;max-width:500px}
.code{font-family:'Orbitron',sans-serif;font-size:8rem;font-weight:900;background:linear-gradient(135deg,#00D4FF,#7B2FFF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;margin-bottom:12px}
h1{font-size:1.5rem;font-weight:800;margin-bottom:10px}
p{color:#8899BB;margin-bottom:24px;line-height:1.7}
.btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#00D4FF,#7B2FFF);color:#fff;padding:12px 28px;border-radius:999px;text-decoration:none;font-weight:700;font-size:.9rem;transition:opacity .2s;margin:0 6px}
.btn:hover{opacity:.85}.btn-ghost{background:transparent;color:#00D4FF;border:1px solid rgba(0,212,255,.4)}
.bg{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none}
.bg::before{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(0,212,255,.07),transparent 70%);top:-100px;right:-100px}
.bg::after{content:'';position:absolute;width:350px;height:350px;border-radius:50%;background:radial-gradient(circle,rgba(123,47,255,.07),transparent 70%);bottom:-80px;left:-80px}
</style>
</head>
<body>
<div class="bg"></div>
<div class="wrap">
  <div class="code">404</div>
  <h1>Halaman Tidak Ditemukan</h1>
  <p>Halaman yang Anda cari tidak ada atau telah dipindahkan. Silakan kembali ke halaman utama.</p>
  <div>
    <a href="<?= $baseUrl ?>/" class="btn">🏠 Beranda</a>
    <a href="javascript:history.back()" class="btn btn-ghost">← Kembali</a>
  </div>
</div>
</body>
</html>
