<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$contact = db()->fetchOne('SELECT * FROM contact_settings LIMIT 1');
$pageTitle = 'Hubungi Kami';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Hubungi Kami</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Kontak</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;margin:0 auto">

<!-- Contact Methods -->
<div style="display:flex;flex-direction:column;gap:14px">
  <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:4px">Saluran Komunikasi</h2>

  <?php if ($contact && $contact['whatsapp']): ?>
  <a href="https://wa.me/<?= e($contact['whatsapp']) ?>" target="_blank" class="card" style="display:flex;align-items:center;gap:14px;text-decoration:none;transition:var(--transition)" onmouseover="this.style.borderColor='#25D366'" onmouseout="this.style.borderColor='var(--border)'">
    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:rgba(37,211,102,.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">💬</div>
    <div>
      <div style="font-weight:700">WhatsApp</div>
      <div style="color:var(--text-success);font-size:.875rem"><?= e($contact['whatsapp']) ?></div>
      <div style="font-size:.75rem;color:var(--text-muted)">Respon cepat 1-2 jam</div>
    </div>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-left:auto;color:var(--text-muted)"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
  </a>
  <?php endif; ?>

  <?php if ($contact && $contact['telegram']): ?>
  <a href="https://t.me/<?= ltrim(e($contact['telegram']), '@') ?>" target="_blank" class="card" style="display:flex;align-items:center;gap:14px;text-decoration:none">
    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:rgba(0,136,204,.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">✈️</div>
    <div>
      <div style="font-weight:700">Telegram</div>
      <div style="color:var(--cyan);font-size:.875rem"><?= e($contact['telegram']) ?></div>
      <div style="font-size:.75rem;color:var(--text-muted)">Grup & channel resmi</div>
    </div>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-left:auto;color:var(--text-muted)"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
  </a>
  <?php endif; ?>

  <?php if ($contact && $contact['instagram']): ?>
  <a href="https://instagram.com/<?= ltrim(e($contact['instagram']), '@') ?>" target="_blank" class="card" style="display:flex;align-items:center;gap:14px;text-decoration:none">
    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:rgba(225,48,108,.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">📸</div>
    <div>
      <div style="font-weight:700">Instagram</div>
      <div style="color:#E1306C;font-size:.875rem"><?= e($contact['instagram']) ?></div>
    </div>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-left:auto;color:var(--text-muted)"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
  </a>
  <?php endif; ?>

  <?php if ($contact && $contact['email']): ?>
  <a href="mailto:<?= e($contact['email']) ?>" class="card" style="display:flex;align-items:center;gap:14px;text-decoration:none">
    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--cyan-dim);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">✉️</div>
    <div>
      <div style="font-weight:700">Email</div>
      <div style="color:var(--cyan);font-size:.875rem"><?= e($contact['email']) ?></div>
      <div style="font-size:.75rem;color:var(--text-muted)">Respon 1-2 hari kerja</div>
    </div>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-left:auto;color:var(--text-muted)"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
  </a>
  <?php endif; ?>

  <?php if ($contact && $contact['address']): ?>
  <div class="card" style="display:flex;align-items:flex-start;gap:14px">
    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:rgba(123,47,255,.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">📍</div>
    <div>
      <div style="font-weight:700;margin-bottom:4px">Alamat</div>
      <div style="font-size:.875rem;color:var(--text-secondary);line-height:1.6"><?= nl2br(e($contact['address'])) ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Live Chat Quick Launch -->
<div>
  <div class="card" style="text-align:center;padding:32px;background:linear-gradient(135deg,var(--cyan-dim),var(--purple-dim));border-color:var(--border-active)">
    <div style="font-size:3.5rem;margin-bottom:12px">🎧</div>
    <h3 style="font-size:1.2rem;font-weight:800;margin-bottom:8px">Live Chat CS</h3>
    <p style="color:var(--text-secondary);font-size:.875rem;line-height:1.7;margin-bottom:20px">
      Chat langsung dengan tim Customer Service kami.<br>
      Respon real-time selama jam operasional.
    </p>
    <?php
    $csStatus = getSetting('cs_status', 'online');
    $csColor  = ['online'=>'var(--text-success)','busy'=>'var(--text-warning)','offline'=>'var(--text-muted)'][$csStatus];
    $csLabel  = ['online'=>'Online','busy'=>'Sibuk','offline'=>'Offline'][$csStatus];
    ?>
    <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:16px;font-size:.85rem">
      <span style="width:8px;height:8px;border-radius:50%;background:<?= $csColor ?>;display:inline-block"></span>
      <span style="color:<?= $csColor ?>">CS <?= $csLabel ?></span>
    </div>
    <a href="<?= BASE_URL ?>/pages/chat.php" class="btn-primary btn-block">Mulai Chat Sekarang</a>
  </div>

  <div class="card" style="margin-top:14px">
    <h3 style="font-size:.9rem;font-weight:700;margin-bottom:12px">Jam Operasional CS</h3>
    <?php foreach (['Senin - Jumat'=>'08:00 - 22:00 WIB','Sabtu - Minggu'=>'09:00 - 20:00 WIB','Hari Libur Nasional'=>'10:00 - 18:00 WIB'] as $day=>$hours): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:.85rem">
      <span style="color:var(--text-secondary)"><?= $day ?></span>
      <strong><?= $hours ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
