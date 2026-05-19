<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $admin = getSessionAdmin();
    foreach ($_POST as $key => $value) {
        if ($key === 'csrf_token' || $key === 'action') continue;
        updateSetting($key, is_array($value) ? implode(',', $value) : (string)$value);
    }
    // Contact settings
    if (isset($_POST['whatsapp'])) {
        db()->execute('UPDATE contact_settings SET whatsapp=?,email=?,telegram=?,instagram=?,facebook=?,tiktok=?,address=? LIMIT 1',
            'sssssss',
            trim($_POST['whatsapp']??''), trim($_POST['contact_email']??''),
            trim($_POST['telegram']??''), trim($_POST['instagram']??''),
            trim($_POST['facebook']??''), trim($_POST['tiktok']??''),
            trim($_POST['address']??'')
        );
    }
    logAdminAction($admin['id'], 'update_settings');
    setFlash('success', 'Pengaturan berhasil disimpan.');
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$settings = db()->fetchAll('SELECT * FROM settings ORDER BY `group`, `key`');
$settingsMap = array_column($settings, 'value', 'key');
$contact = db()->fetchOne('SELECT * FROM contact_settings LIMIT 1');

$groups = ['general'=>'Umum','statistics'=>'Statistik','features'=>'Fitur','deposit'=>'Deposit','withdraw'=>'Penarikan','bonus'=>'Bonus','chat'=>'Chat CS','security'=>'Keamanan'];

adminHeader('Pengaturan', 'settings');
?>
<form method="POST">
<?= csrfField() ?>
<?php foreach ($groups as $group => $groupLabel):
  $groupSettings = array_filter($settings, fn($s) => $s['group'] === $group);
  if (empty($groupSettings)) continue;
?>
<div class="card" style="margin-bottom:16px">
  <h3 class="card-title" style="margin-bottom:16px"><?= $groupLabel ?></h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <?php foreach ($groupSettings as $s): ?>
    <div class="form-group">
      <label class="form-label"><?= e($s['label'] ?? $s['key']) ?></label>
      <?php if ($s['type'] === 'boolean'): ?>
      <select name="<?= e($s['key']) ?>" class="form-control">
        <option value="1" <?= $s['value']=='1'?'selected':'' ?>>Aktif</option>
        <option value="0" <?= $s['value']=='0'?'selected':'' ?>>Nonaktif</option>
      </select>
      <?php elseif ($s['type'] === 'text'): ?>
      <textarea name="<?= e($s['key']) ?>" class="form-control" rows="3"><?= e($s['value'] ?? '') ?></textarea>
      <?php else: ?>
      <input type="text" name="<?= e($s['key']) ?>" class="form-control" value="<?= e($s['value'] ?? '') ?>">
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Contact Settings -->
<?php if ($contact): ?>
<div class="card" style="margin-bottom:16px">
  <h3 class="card-title" style="margin-bottom:16px">Kontak & Sosial Media</h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <?php foreach (['whatsapp'=>'WhatsApp (no HP)','contact_email'=>'Email','telegram'=>'Telegram (@username)','instagram'=>'Instagram (@username)','facebook'=>'Facebook','tiktok'=>'TikTok'] as $k=>$l): ?>
    <div class="form-group">
      <label class="form-label"><?= $l ?></label>
      <input type="text" name="<?= $k ?>" class="form-control" value="<?= e($k==='contact_email' ? ($contact['email']??'') : ($contact[$k]??'')) ?>">
    </div>
    <?php endforeach; ?>
    <div class="form-group" style="grid-column:1/-1">
      <label class="form-label">Alamat Kantor</label>
      <textarea name="address" class="form-control" rows="2"><?= e($contact['address']??'') ?></textarea>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Notification Settings -->
<?php $notifSettings = db()->fetchOne('SELECT * FROM notification_settings LIMIT 1'); ?>
<?php if ($notifSettings): ?>
<div class="card" style="margin-bottom:16px">
  <h3 class="card-title" style="margin-bottom:16px">WhatsApp Notifikasi (Fonnte)</h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <div class="form-group"><label class="form-label">Status</label>
      <select name="wa_enabled" class="form-control">
        <option value="1" <?= $notifSettings['whatsapp_enabled']?'selected':'' ?>>Aktif</option>
        <option value="0" <?= !$notifSettings['whatsapp_enabled']?'selected':'' ?>>Nonaktif</option>
      </select>
    </div>
    <div class="form-group"><label class="form-label">API URL Fonnte</label><input type="text" name="wa_api_url" class="form-control" value="<?= e($notifSettings['whatsapp_api_url']??'') ?>"></div>
    <div class="form-group"><label class="form-label">Token</label><input type="text" name="wa_token" class="form-control" value="<?= e($notifSettings['whatsapp_token']??'') ?>"></div>
    <div class="form-group"><label class="form-label">Nomor Pengirim</label><input type="text" name="wa_sender" class="form-control" value="<?= e($notifSettings['whatsapp_sender']??'') ?>"></div>
  </div>
</div>
<?php endif; ?>

<button type="submit" class="btn-primary btn-lg">💾 Simpan Semua Pengaturan</button>
</form>

<?php adminFooter(); ?>
