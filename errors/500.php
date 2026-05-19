<?php
http_response_code(500);
$siteName = defined('SITE_NAME') ? SITE_NAME : 'NOXARA';
$baseUrl  = defined('BASE_URL')  ? BASE_URL  : '';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>500 — Server Error | <?= $siteName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#0A0E1A;color:#F0F4FF;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{text-align:center;max-width:500px}
.code{font-family:'Orbitron',sans-serif;font-size:8rem;font-weight:900;background:linear-gradient(135deg,#FF4466,#FFB800);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;margin-bottom:12px}
h1{font-size:1.5rem;font-weight:800;margin-bottom:10px}
p{color:#8899BB;margin-bottom:24px;line-height:1.7}
.btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#00D4FF,#7B2FFF);color:#fff;padding:12px 28px;border-radius:999px;text-decoration:none;font-weight:700;font-size:.9rem;transition:opacity .2s;margin:0 6px}
.btn:hover{opacity:.85}.btn-ghost{background:transparent;color:#00D4FF;border:1px solid rgba(0,212,255,.4)}
.icon{font-size:4rem;margin-bottom:16px}
</style>
</head>
<body>
<div class="wrap">
  <div class="icon">⚠️</div>
  <div class="code">500</div>
  <h1>Terjadi Kesalahan Server</h1>
  <p>Maaf, terjadi kesalahan internal. Tim kami sudah diberitahu dan sedang memperbaiki masalah ini. Coba lagi dalam beberapa menit.</p>
  <div>
    <a href="<?= $baseUrl ?>/" class="btn">🏠 Beranda</a>
    <a href="javascript:location.reload()" class="btn btn-ghost">🔄 Coba Lagi</a>
  </div>
  <p style="font-size:.8rem;color:#4A5880;margin-top:24px">Error ID: <?= date('YmdHis') ?>-<?= substr(md5(microtime()),0,6) ?></p>
</div>
</body>
</html>
