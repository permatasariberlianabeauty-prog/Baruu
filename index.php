<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/bootstrap.php';
initSession();
checkMaintenance();

// Redirect if logged in
if (isLoggedIn()) { redirect('pages/dashboard.php'); }

// Data
$banners      = db()->fetchAll('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 8');
$products     = db()->fetchAll('SELECT p.*, pc.name as cat_name, pc.color as cat_color FROM products p JOIN product_categories pc ON p.category_id = pc.id WHERE p.is_active = 1 ORDER BY pc.sort_order, p.sort_order LIMIT 12');
$categories   = db()->fetchAll('SELECT * FROM product_categories WHERE is_active = 1 ORDER BY sort_order');
$faqs         = db()->fetchAll("SELECT * FROM platform_info WHERE section = 'faq' LIMIT 1");
$contact      = db()->fetchOne('SELECT * FROM contact_settings LIMIT 1');
$marqueeItems = getMarqueeItems();

$totalMembers = (int)getSetting('total_members_display', 284750);
$totalPayout  = (float)getSetting('total_payout_display', 15800000000);
$rating       = getSetting('platform_rating', '4.9');
$year         = getSetting('platform_year', '2024');

$commSettings = db()->fetchAll('SELECT * FROM commission_settings ORDER BY type, level');
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= SITE_NAME ?> — <?= SITE_TAGLINE ?>. Platform investasi digital terpercaya di Indonesia.">
<meta name="theme-color" content="#0A0E1A">
<title><?= SITE_NAME ?> — <?= SITE_TAGLINE ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css">
<style>
/* ── Landing-specific overrides ── */
body { margin: 0; }
.landing-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  height: 64px; display: flex; align-items: center; justify-content: space-between;
  padding: 0 40px;
  background: rgba(10,14,26,0);
  backdrop-filter: blur(0);
  border-bottom: 1px solid transparent;
  transition: all .3s ease;
}
.landing-nav.scrolled {
  background: rgba(10,14,26,.95);
  backdrop-filter: blur(16px);
  border-bottom-color: var(--border);
}
.nav-brand { display:flex; align-items:center; gap:10px; }
.nav-brand span { font-family:var(--font-brand); font-size:1.1rem; font-weight:900; color:#fff; letter-spacing:.08em; }
.nav-links { display:flex; align-items:center; gap:6px; }
.nav-link  { padding:8px 14px; color:var(--text-secondary); font-size:.875rem; font-weight:500; border-radius:var(--radius-full); transition:var(--transition); }
.nav-link:hover { color:#fff; background:var(--bg-hover); }
.nav-cta { display:flex; gap:8px; }

/* ── Hero ── */
.hero {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  position: relative; overflow: hidden; text-align: center;
  padding: 100px 20px 60px;
  background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(0,212,255,.12) 0%, transparent 60%),
              radial-gradient(ellipse 60% 40% at 80% 60%, rgba(123,47,255,.1) 0%, transparent 60%),
              var(--bg-primary);
}
.hero-particles { position:absolute; inset:0; pointer-events:none; overflow:hidden; }
.hero-badge {
  display:inline-flex; align-items:center; gap:6px;
  background:var(--cyan-dim); border:1px solid var(--border-active);
  border-radius:var(--radius-full); padding:6px 14px; font-size:.8rem; font-weight:600;
  color:var(--cyan); margin-bottom:20px;
  animation: fadeInUp .6s ease;
}
.hero-badge-dot { width:6px; height:6px; border-radius:50%; background:var(--cyan); animation:pulseRing 1.5s infinite; }
.hero-title {
  font-size: clamp(2.4rem, 6vw, 4.2rem);
  font-weight: 900; line-height: 1.12; letter-spacing: -.02em;
  margin-bottom: 20px; animation: fadeInUp .7s .1s ease both;
}
.hero-sub {
  font-size: clamp(1rem, 2.5vw, 1.2rem); color: var(--text-secondary);
  max-width: 560px; margin: 0 auto 36px; line-height: 1.7;
  animation: fadeInUp .7s .2s ease both;
}
.hero-btns {
  display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;
  animation: fadeInUp .7s .3s ease both;
}
.hero-scroll-hint {
  position:absolute; bottom:30px; left:50%; transform:translateX(-50%);
  display:flex; flex-direction:column; align-items:center; gap:6px;
  color:var(--text-muted); font-size:.75rem; animation: float 2s ease infinite;
}

/* ── Section ── */
.section { padding: 80px 40px; max-width: 1200px; margin: 0 auto; }
.section-sm { padding: 60px 40px; max-width: 1200px; margin: 0 auto; }
.section-header { text-align:center; margin-bottom:48px; }
.section-label { display:inline-block; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.15em; color:var(--cyan); margin-bottom:10px; }
.section-title { font-size:clamp(1.6rem,3.5vw,2.4rem); font-weight:900; margin-bottom:12px; }
.section-sub   { color:var(--text-secondary); font-size:1rem; max-width:560px; margin:0 auto; line-height:1.7; }

/* ── Stats ── */
.stats-section { background:var(--bg-secondary); border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
.stats-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:0; max-width:900px; margin:0 auto; }
.stat-item { text-align:center; padding:40px 20px; border-right:1px solid var(--border); }
.stat-item:last-child { border-right:none; }
.stat-num { font-size:clamp(1.8rem,3vw,2.8rem); font-weight:900; font-family:var(--font-brand); background:var(--gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.stat-lbl { font-size:.85rem; color:var(--text-muted); margin-top:6px; }

/* ── How it works ── */
.steps-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
.step-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-xl); padding:32px 24px; text-align:center; position:relative; overflow:hidden; transition:var(--transition); }
.step-card:hover { border-color:var(--border-active); transform:translateY(-4px); }
.step-num { font-size:4rem; font-weight:900; font-family:var(--font-brand); background:var(--gradient); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; opacity:.2; position:absolute; top:10px; right:20px; line-height:1; }
.step-icon { width:56px; height:56px; border-radius:var(--radius-lg); background:var(--cyan-dim); border:1px solid var(--border-active); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
.step-title { font-size:1.1rem; font-weight:700; margin-bottom:8px; }
.step-desc  { color:var(--text-secondary); font-size:.875rem; line-height:1.7; }

/* ── Products grid ── */
.products-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:18px; }

/* ── Referral table ── */
.ref-table-wrap { overflow-x:auto; }
.ref-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.ref-table th { background:var(--bg-card2); padding:12px 16px; text-align:left; color:var(--text-muted); font-size:.8rem; text-transform:uppercase; }
.ref-table td { padding:12px 16px; border-bottom:1px solid var(--border); }
.ref-table tr:hover td { background:var(--bg-hover); }

/* ── FAQ ── */
.faq-grid { max-width:760px; margin:0 auto; }
.accordion-list .accordion-item { margin-bottom:10px; }

/* ── Footer ── */
.lp-footer { background:var(--bg-secondary); border-top:1px solid var(--border); padding:48px 40px 24px; }
.footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:40px; max-width:1200px; margin:0 auto 40px; }
.footer-brand p { color:var(--text-muted); font-size:.875rem; line-height:1.7; margin:12px 0; max-width:300px; }
.footer-col h4 { font-size:.9rem; font-weight:700; margin-bottom:14px; }
.footer-col ul { list-style:none; display:flex; flex-direction:column; gap:8px; }
.footer-col ul a { color:var(--text-muted); font-size:.875rem; transition:var(--transition); }
.footer-col ul a:hover { color:var(--cyan); }
.footer-bottom { border-top:1px solid var(--border); padding-top:24px; display:flex; align-items:center; justify-content:space-between; max-width:1200px; margin:0 auto; }
.footer-bottom p { color:var(--text-muted); font-size:.8rem; }
.socials { display:flex; gap:10px; }
.social-btn { width:36px; height:36px; border-radius:var(--radius-sm); background:var(--bg-card); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text-muted); transition:var(--transition); }
.social-btn:hover { border-color:var(--cyan); color:var(--cyan); }

@media(max-width:900px) {
  .landing-nav { padding:0 20px; }
  .nav-links    { display:none; }
  .section, .section-sm { padding:60px 20px; }
  .stats-row  { grid-template-columns:repeat(2,1fr); }
  .stat-item  { border-bottom:1px solid var(--border); }
  .stat-item:nth-child(2) { border-right:none; }
  .steps-grid { grid-template-columns:1fr; }
  .footer-grid { grid-template-columns:1fr 1fr; }
}
@media(max-width:600px) {
  .hero-title { font-size:2rem; }
  .footer-grid { grid-template-columns:1fr; }
  .footer-bottom { flex-direction:column; gap:12px; text-align:center; }
}
</style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="landing-nav" id="landingNav">
  <div class="nav-brand">
    <svg width="30" height="30" viewBox="0 0 28 28" fill="none">
      <polygon points="14,2 26,8 26,20 14,26 2,20 2,8" fill="none" stroke="url(#nlg)" stroke-width="2"/>
      <path d="M9 11l5 3 5-3M14 14v7" stroke="url(#nlg)" stroke-width="1.5" stroke-linecap="round"/>
      <defs><linearGradient id="nlg" x1="0" y1="0" x2="28" y2="28"><stop stop-color="#00D4FF"/><stop offset="1" stop-color="#7B2FFF"/></linearGradient></defs>
    </svg>
    <span><?= SITE_NAME ?></span>
  </div>
  <div class="nav-links">
    <a href="#cara-kerja" class="nav-link">Cara Kerja</a>
    <a href="#produk"     class="nav-link">Produk</a>
    <a href="#referral"   class="nav-link">Referral</a>
    <a href="#faq"        class="nav-link">FAQ</a>
  </div>
  <div class="nav-cta">
    <a href="<?= BASE_URL ?>/auth/login.php"    class="btn-ghost btn-sm">Masuk</a>
    <a href="<?= BASE_URL ?>/auth/register.php" class="btn-primary btn-sm">Daftar Gratis</a>
  </div>
</nav>


<!-- ── HERO ── -->
<section class="hero" id="hero">
  <div class="hero-particles" id="heroParticles"></div>
  <div style="position:relative; z-index:1; max-width:800px; width:100%">
    <div class="hero-badge">
      <span class="hero-badge-dot"></span>
      Platform Investasi Digital #1 Indonesia
    </div>
    <h1 class="hero-title">
      <span class="text-gradient">Invest Smarter,</span><br>
      Grow Faster
    </h1>
    <p class="hero-sub">
      Dapatkan penghasilan pasif setiap hari melalui sistem mining otomatis.
      Mulai dari Rp10.000 dan raih profit hingga 225% per bulan.
    </p>
    <div class="hero-btns">
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn-primary btn-lg">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="white" stroke-width="2.5" stroke-linecap="round"/></svg>
        Mulai Investasi
      </a>
      <a href="#cara-kerja" class="btn-ghost btn-lg">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.7"/><polygon points="10 8 16 12 10 16 10 8" stroke="currentColor" stroke-width="1.7"/></svg>
        Cara Kerja
      </a>
    </div>
  </div>
  <div class="hero-scroll-hint">
    <span>Scroll ke bawah</span>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
  </div>
</section>

<!-- ── STATS ── -->
<section class="stats-section">
  <div class="stats-row">
    <div class="stat-item">
      <div class="stat-num" data-counter="<?= $totalMembers ?>" data-counter-suffix="+">0</div>
      <div class="stat-lbl">Total Member Aktif</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" id="payoutCounter">Rp15,8M+</div>
      <div class="stat-lbl">Total Payout</div>
    </div>
    <div class="stat-item">
      <div class="stat-num"><?= e($rating) ?>/5</div>
      <div class="stat-lbl">Rating Platform</div>
      <div style="color:#FFD700;font-size:.85rem;margin-top:4px">★★★★★</div>
    </div>
    <div class="stat-item">
      <div class="stat-num"><?= e($year) ?></div>
      <div class="stat-lbl">Tahun Berdiri</div>
    </div>
  </div>
</section>

<!-- ── MARQUEE ── -->
<?php if (!empty($marqueeItems)): ?>
<div class="marquee-bar" style="height:38px">
  <div class="marquee-inner">
    <div class="marquee-track">
      <?php foreach ($marqueeItems as $item): ?>
      <span class="marquee-item"><?= $item ?>&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;</span>
      <?php endforeach; ?>
      <?php foreach ($marqueeItems as $item): ?>
      <span class="marquee-item"><?= $item ?>&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;</span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── BANNER SLIDER ── -->
<?php if (!empty($banners)): ?>
<div style="max-width:1200px;margin:0 auto;padding:40px 40px 0">
  <div class="banner-slider">
    <div class="banner-track">
      <?php foreach ($banners as $b): ?>
      <div class="banner-slide">
        <?php if ($b['url']): ?>
        <a href="<?= e($b['url']) ?>" target="_blank" rel="noopener">
        <?php endif; ?>
        <img src="<?= UPLOADS_URL ?>/banners/<?= e($b['image']) ?>" alt="<?= e($b['title'] ?? '') ?>" loading="lazy" style="height:220px;object-fit:cover;width:100%;border-radius:var(--radius-lg)">
        <?php if ($b['url']): ?></a><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="banner-dots">
      <?php foreach ($banners as $i => $b): ?>
      <div class="banner-dot <?= $i === 0 ? 'active' : '' ?>"></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── HOW IT WORKS ── -->
<section class="section" id="cara-kerja">
  <div class="section-header animate-on-scroll">
    <span class="section-label">Mudah & Cepat</span>
    <h2 class="section-title">Cara Kerja <span class="text-gradient"><?= SITE_NAME ?></span></h2>
    <p class="section-sub">Mulai investasi dalam 3 langkah mudah dan dapatkan profit setiap hari</p>
  </div>
  <div class="steps-grid stagger">
    <div class="step-card">
      <div class="step-num">01</div>
      <div class="step-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="#00D4FF" stroke-width="1.7"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="#00D4FF" stroke-width="1.7" stroke-linecap="round"/></svg>
      </div>
      <div class="step-title">Daftar & Deposit</div>
      <div class="step-desc">Buat akun gratis dalam 2 menit. Lakukan deposit mulai Rp10.000 menggunakan transfer bank lokal.</div>
    </div>
    <div class="step-card">
      <div class="step-num">02</div>
      <div class="step-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M3 21l4-4 4.5-4.5M17 3l4 4-11 11-4-4 11-11z" stroke="#7B2FFF" stroke-width="1.7" stroke-linecap="round"/></svg>
      </div>
      <div class="step-title">Beli Paket Mining</div>
      <div class="step-desc">Pilih paket mining sesuai budget. Klik tombol mining setiap hari dan profit otomatis masuk ke saldo.</div>
    </div>
    <div class="step-card">
      <div class="step-num">03</div>
      <div class="step-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2v20M17 7H9.5a2.5 2.5 0 000 5h5a2.5 2.5 0 010 5H7" stroke="#00FF88" stroke-width="1.7" stroke-linecap="round"/></svg>
      </div>
      <div class="step-title">Tarik Keuntungan</div>
      <div class="step-desc">Withdraw profit kapan saja ke rekening bank Anda. Proses cepat dan mudah setiap hari kerja.</div>
    </div>
  </div>
</section>


<!-- ── PRODUCTS ── -->
<section style="background:var(--bg-secondary);border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
<div class="section" id="produk">
  <div class="section-header animate-on-scroll">
    <span class="section-label">Pilihan Investasi</span>
    <h2 class="section-title">Paket <span class="text-gradient">Mining</span> Kami</h2>
    <p class="section-sub">Dari pemula hingga profesional — kami punya paket yang tepat untuk Anda</p>
  </div>

  <?php foreach ($categories as $cat):
    $catProducts = array_filter($products, fn($p) => $p['category_id'] == $cat['id']);
    if (empty($catProducts)) continue;
  ?>
  <div style="margin-bottom:40px">
    <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <span style="width:10px;height:10px;border-radius:50%;background:<?= e($cat['color']) ?>;display:inline-block"></span>
      <?= e($cat['name']) ?>
    </h3>
    <div class="products-grid">
      <?php foreach ($catProducts as $p): ?>
      <div class="product-card animate-on-scroll">
        <div class="product-card-body">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <span class="badge" style="background:<?= e($cat['color']) ?>22;color:<?= e($cat['color']) ?>;border:1px solid <?= e($cat['color']) ?>44"><?= e($cat['name']) ?></span>
            <span style="font-size:.72rem;color:var(--text-muted)"><?= $p['duration_days'] ?> hari</span>
          </div>
          <div class="product-card-name"><?= e($p['name']) ?></div>
          <div class="product-card-price"><?= formatRupiah($p['price']) ?></div>
          <div class="product-card-meta">
            <div class="product-meta-item">
              <label>Profit/Hari</label>
              <span style="color:var(--text-success)"><?= formatRupiah($p['profit_per_day']) ?></span>
            </div>
            <div class="product-meta-item">
              <label>Total Profit</label>
              <span style="color:var(--cyan)"><?= formatRupiah($p['profit_per_day'] * $p['duration_days']) ?></span>
            </div>
            <div class="product-meta-item">
              <label>ROI</label>
              <span style="color:var(--text-warning)"><?= number_format(($p['profit_per_day'] * $p['duration_days'] / $p['price']) * 100, 0) ?>%</span>
            </div>
            <div class="product-meta-item">
              <label>Durasi</label>
              <span><?= $p['duration_days'] ?> Hari</span>
            </div>
          </div>
          <div class="product-card-footer">
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-primary btn-block btn-sm">Mulai Sekarang</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div style="text-align:center;margin-top:16px">
    <a href="<?= BASE_URL ?>/auth/register.php" class="btn-secondary">
      Lihat Semua Paket →
    </a>
  </div>
</div>
</section>

<!-- ── REFERRAL ── -->
<section class="section" id="referral">
  <div class="section-header animate-on-scroll">
    <span class="section-label">Penghasilan Tambahan</span>
    <h2 class="section-title">Sistem <span class="text-gradient">Referral 3 Level</span></h2>
    <p class="section-sub">Undang teman dan dapatkan komisi otomatis dari setiap aktivitas mereka</p>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start">
    <div class="animate-on-scroll">
      <div class="ref-table-wrap card" style="padding:0;overflow:hidden">
        <table class="ref-table">
          <thead>
            <tr>
              <th>Level</th>
              <th>Rabat Deposit</th>
              <th>Rabat Pembelian</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $refDeposit  = array_filter($commSettings, fn($c) => $c['type'] === 'deposit');
            $refProduct  = array_filter($commSettings, fn($c) => $c['type'] === 'product');
            $refDArr     = array_values($refDeposit);
            $refPArr     = array_values($refProduct);
            for ($i = 0; $i < 3; $i++):
              $dep = $refDArr[$i] ?? null;
              $pro = $refPArr[$i] ?? null;
              $colors = ['var(--cyan)', 'var(--purple)', 'var(--text-warning)'];
              $col = $colors[$i];
            ?>
            <tr>
              <td><span style="font-weight:700;color:<?= $col ?>">Level <?= $i+1 ?></span></td>
              <td style="font-weight:600;color:var(--text-success)"><?= $dep ? $dep['percent'] : '0' ?>%</td>
              <td style="font-weight:600;color:var(--text-success)"><?= $pro ? $pro['percent'] : '0' ?>%</td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
      <div class="alert alert-info" style="margin-top:14px;font-size:.85rem">
        💡 Komisi langsung masuk ke saldo referral Anda dan bisa ditarik kapan saja!
      </div>
    </div>

    <div class="animate-on-scroll">
      <div class="card" style="padding:24px">
        <h3 style="margin-bottom:20px;font-size:1.1rem">Visualisasi Pohon Referral</h3>
        <div style="text-align:center">
          <!-- You (root) -->
          <div style="display:flex;justify-content:center;margin-bottom:16px">
            <div style="background:var(--gradient);border-radius:var(--radius-md);padding:10px 20px;font-weight:700;font-size:.9rem;color:#fff">Anda</div>
          </div>
          <!-- Connector lines -->
          <div style="display:flex;justify-content:center;gap:40px;margin-bottom:4px">
            <div style="width:1px;height:20px;background:var(--border)"></div>
            <div style="width:1px;height:20px;background:var(--border)"></div>
            <div style="width:1px;height:20px;background:var(--border)"></div>
          </div>
          <!-- L1 -->
          <div style="display:flex;gap:10px;justify-content:center;margin-bottom:10px">
            <?php for($i=0;$i<3;$i++): ?>
            <div class="ref-node" style="opacity:1;min-width:70px">
              <div class="ref-node-name">L1 #<?=$i+1?></div>
              <div class="ref-node-status aktif" style="font-size:.65rem">Aktif</div>
            </div>
            <?php endfor; ?>
          </div>
          <!-- L2 -->
          <div style="display:flex;gap:6px;justify-content:center;margin-bottom:10px;flex-wrap:wrap">
            <?php for($i=0;$i<5;$i++): ?>
            <div class="ref-node" style="opacity:1;min-width:60px;background:var(--cyan-dim);border-color:var(--border-active)">
              <div class="ref-node-name" style="font-size:.75rem">L2</div>
            </div>
            <?php endfor; ?>
            <div style="color:var(--text-muted);font-size:.75rem;display:flex;align-items:center">+dst</div>
          </div>
          <p style="color:var(--text-muted);font-size:.8rem;margin-top:10px">Sampai 3 level downline</p>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ── FAQ ── -->
<section style="background:var(--bg-secondary);border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
<div class="section-sm" id="faq">
  <div class="section-header animate-on-scroll">
    <span class="section-label">Pertanyaan Umum</span>
    <h2 class="section-title">FAQ — <span class="text-gradient">Tanya Jawab</span></h2>
  </div>
  <div class="faq-grid accordion-list">
    <?php
    $faqs_data = [
      ['q'=>'Apa itu '  .SITE_NAME.'?','a'=>SITE_NAME.' adalah platform investasi digital yang menggunakan sistem cloud mining. Member membeli paket mining, klik mining setiap hari, dan profit otomatis masuk ke saldo.'],
      ['q'=>'Berapa modal minimal untuk memulai?','a'=>'Modal minimal mulai dari Rp10.000 untuk paket STONE I. Kami menyediakan berbagai pilihan paket untuk semua kalangan.'],
      ['q'=>'Bagaimana cara mendapatkan profit?','a'=>'Setelah membeli paket mining, klik tombol Mining setiap hari. Profit akan masuk ke saldo profit Anda dalam waktu 3 jam setelah klik.'],
      ['q'=>'Apakah saldo bisa ditarik kapan saja?','a'=>'Ya, penarikan bisa dilakukan setiap hari dalam jam operasional 08:00–21:00 WIB. Minimum dan fee penarikan bergantung pada level VIP Anda.'],
      ['q'=>'Bagaimana sistem referral bekerja?','a'=>'Ajak teman mendaftar dengan kode referral Anda. Anda akan mendapat komisi hingga 3 level: 2% deposit L1, 1% L2, 0.5% L3 — dan 3% pembelian paket L1.'],
      ['q'=>'Apakah '.SITE_NAME.' aman?','a'=>SITE_NAME.' menggunakan enkripsi SSL, proteksi CSRF, dan sistem keamanan berlapis. PIN transaksi 6 digit diperlukan untuk setiap penarikan dana.'],
    ];
    foreach ($faqs_data as $i => $faq): ?>
    <div class="accordion-item <?= $i === 0 ? 'open' : '' ?>">
      <div class="accordion-header">
        <span><?= e($faq['q']) ?></span>
        <svg class="accordion-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
      </div>
      <div class="accordion-body">
        <div class="accordion-body-inner"><?= e($faq['a']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</section>

<!-- ── CTA FINAL ── -->
<section class="section" style="text-align:center">
  <div class="animate-on-scroll" style="max-width:600px;margin:0 auto">
    <div style="font-size:3rem;margin-bottom:16px">🚀</div>
    <h2 style="font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;margin-bottom:12px">
      Siap Mulai <span class="text-gradient">Investasi?</span>
    </h2>
    <p style="color:var(--text-secondary);margin-bottom:32px;font-size:1rem;line-height:1.7">
      Bergabung dengan <?= number_format($totalMembers) ?>+ member yang sudah merasakan manfaat <?= SITE_NAME ?>.
      Daftar sekarang dan dapatkan bonus selamat datang!
    </p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn-primary btn-lg">Daftar Sekarang — Gratis!</a>
      <a href="<?= BASE_URL ?>/auth/login.php"    class="btn-ghost btn-lg">Sudah Punya Akun</a>
    </div>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer class="lp-footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <svg width="32" height="32" viewBox="0 0 28 28" fill="none"><polygon points="14,2 26,8 26,20 14,26 2,20 2,8" fill="none" stroke="url(#flg)" stroke-width="2"/><path d="M9 11l5 3 5-3M14 14v7" stroke="url(#flg)" stroke-width="1.5" stroke-linecap="round"/><defs><linearGradient id="flg" x1="0" y1="0" x2="28" y2="28"><stop stop-color="#00D4FF"/><stop offset="1" stop-color="#7B2FFF"/></linearGradient></defs></svg>
        <span class="orbitron" style="font-size:1.1rem;font-weight:900"><?= SITE_NAME ?></span>
      </div>
      <p><?= SITE_TAGLINE ?></p>
      <p>Platform investasi digital terpercaya di Indonesia sejak <?= $year ?>.</p>
      <div class="socials" style="margin-top:14px">
        <?php if ($contact && $contact['whatsapp']): ?>
        <a href="https://wa.me/<?= e($contact['whatsapp']) ?>" class="social-btn" target="_blank" rel="noopener" title="WhatsApp">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($contact && $contact['telegram']): ?>
        <a href="https://t.me/<?= ltrim(e($contact['telegram']), '@') ?>" class="social-btn" target="_blank" rel="noopener" title="Telegram">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($contact && $contact['instagram']): ?>
        <a href="https://instagram.com/<?= ltrim(e($contact['instagram']), '@') ?>" class="social-btn" target="_blank" rel="noopener" title="Instagram">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="footer-col">
      <h4>Platform</h4>
      <ul>
        <li><a href="<?= BASE_URL ?>/auth/register.php">Daftar</a></li>
        <li><a href="<?= BASE_URL ?>/auth/login.php">Masuk</a></li>
        <li><a href="#produk">Produk Mining</a></li>
        <li><a href="#referral">Program Referral</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Informasi</h4>
      <ul>
        <li><a href="<?= BASE_URL ?>/pages/info.php?tab=about">Tentang Kami</a></li>
        <li><a href="<?= BASE_URL ?>/pages/info.php?tab=tnc">Syarat &amp; Ketentuan</a></li>
        <li><a href="<?= BASE_URL ?>/pages/info.php?tab=privacy">Kebijakan Privasi</a></li>
        <li><a href="<?= BASE_URL ?>/pages/info.php?tab=withdraw_policy">Kebijakan WD</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Bantuan</h4>
      <ul>
        <li><a href="#faq">FAQ</a></li>
        <li><a href="<?= BASE_URL ?>/pages/contact.php">Kontak</a></li>
        <?php if ($contact && $contact['whatsapp']): ?>
        <li><a href="https://wa.me/<?= e($contact['whatsapp']) ?>" target="_blank">WhatsApp CS</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
    <p>Made with ❤️ for Indonesian investors</p>
  </div>
</footer>

<!-- Scripts -->
<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<script src="<?= ASSETS_URL ?>/js/animations.js"></script>
<script>
// Sticky nav on scroll
const nav = document.getElementById('landingNav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 50);
}, { passive: true });

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', e => {
    const target = document.querySelector(link.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});

// Hero particles
initParticles('heroParticles');

// Stats counter animation
const statsSection = document.querySelector('.stats-section');
const counterDone  = { value: false };
const statsObserver = new IntersectionObserver(entries => {
  if (entries[0].isIntersecting && !counterDone.value) {
    counterDone.value = true;
    document.querySelectorAll('[data-counter]').forEach(el => {
      const target = parseInt(el.dataset.counter) || 0;
      const suffix = el.dataset.counterSuffix || '';
      const prefix = el.dataset.counterPrefix || '';
      const dur    = 2200;
      const start  = performance.now();
      const step   = now => {
        const p = Math.min((now - start) / dur, 1);
        const e2 = 1 - Math.pow(1 - p, 3);
        el.textContent = prefix + Math.floor(target * e2).toLocaleString('id-ID') + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }
}, { threshold: 0.3 });
if (statsSection) statsObserver.observe(statsSection);
</script>
</body>
</html>
