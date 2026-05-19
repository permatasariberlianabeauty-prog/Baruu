<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$pageTitle = 'FAQ';
include INCLUDES_PATH . '/header.php';

$faqs = [
  ['q'=>'Bagaimana cara kerja sistem mining?','a'=>'Setelah membeli paket mining, Anda wajib klik tombol Mining setiap hari. Setelah klik, profit akan masuk ke saldo profit Anda dalam waktu 3 jam. Modal akan dikembalikan ke saldo utama setelah durasi paket berakhir (30 hari).'],
  ['q'=>'Apa bedanya 4 jenis saldo?','a'=>'(1) Saldo Utama: untuk deposit, withdraw, dan beli paket. (2) Saldo Profit: hasil mining, bisa withdraw. (3) Saldo Bonus: hadiah/reward, HANYA untuk beli paket (tidak bisa withdraw). (4) Saldo Referral: komisi dari referral, bisa withdraw.'],
  ['q'=>'Berapa lama proses konfirmasi deposit?','a'=>'Deposit dikonfirmasi manual oleh admin maksimal 1x24 jam di hari kerja. Anda akan mendapat notifikasi begitu deposit dikonfirmasi.'],
  ['q'=>'Berapa lama proses penarikan dana?','a'=>'Proses transfer 1-3 hari kerja setelah disetujui admin. Jam operasional withdraw 08:00-21:00 WIB.'],
  ['q'=>'Kenapa ada nominal unik saat deposit?','a'=>'Nominal unik (3 digit tambahan) digunakan untuk memudahkan admin mengidentifikasi transfer Anda secara akurat. Transfer HARUS sesuai dengan total nominal termasuk kode unik.'],
  ['q'=>'Bagaimana cara meningkatkan level VIP?','a'=>'Level VIP naik otomatis berdasarkan total deposit kumulatif. Semakin tinggi VIP, semakin rendah fee withdraw dan semakin tinggi limit penarikan. VIP tidak pernah turun.'],
  ['q'=>'Apakah saldo bonus bisa ditarik?','a'=>'TIDAK. Saldo bonus hanya bisa digunakan untuk membeli paket mining. Ini adalah reward untuk mendorong Anda berinvestasi lebih aktif.'],
  ['q'=>'Bagaimana sistem referral bekerja?','a'=>'Bagikan link referral Anda kepada teman. Setiap kali teman Anda deposit atau beli paket, Anda otomatis mendapat komisi sesuai persentase level (L1, L2, L3). Komisi masuk ke saldo referral.'],
  ['q'=>'Apa itu PIN transaksi?','a'=>'PIN 6 digit yang wajib dimasukkan setiap kali melakukan penarikan dana. PIN dibuat di menu Keamanan. Jangan bagikan PIN ke siapapun termasuk admin.'],
  ['q'=>'Bagaimana jika paket mining saya habis?','a'=>'Saat paket berakhir (30 hari), modal awal otomatis dikembalikan ke saldo utama Anda. Paket akan berstatus "Selesai" dan Anda bisa membeli paket baru.'],
  ['q'=>'Bisakah saya punya lebih dari 1 paket aktif?','a'=>'Ya! Anda bisa membeli beberapa paket mining sekaligus. Semua paket berjalan bersamaan dan profit dijumlahkan setiap hari.'],
  ['q'=>'Apa yang terjadi jika saya tidak mining sehari?','a'=>'Profit hari itu tidak akan diberikan. Sistem mining mengharuskan klik aktif setiap hari. Paket tetap berjalan normal, hanya profit hari skip yang tidak dikreditkan.'],
];
?>
<div class="page-header">
  <h1>FAQ — Pertanyaan Umum</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › FAQ</div>
</div>

<div class="faq-grid accordion-list" style="max-width:800px;margin:0 auto">
  <?php foreach ($faqs as $i => $faq): ?>
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

<div class="card" style="max-width:800px;margin:24px auto 0;text-align:center">
  <p style="color:var(--text-secondary);margin-bottom:14px">Tidak menemukan jawaban yang Anda cari?</p>
  <a href="<?= BASE_URL ?>/pages/chat.php"    class="btn-primary" style="margin-right:10px">Live Chat CS</a>
  <a href="<?= BASE_URL ?>/pages/contact.php" class="btn-ghost">Hubungi Kami</a>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
