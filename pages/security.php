<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$user   = db()->fetchOne('SELECT pin FROM users WHERE id = ?', 'i', $userId);
$hasPin = !empty($user['pin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $result = changePassword($userId, $_POST['old_password'] ?? '', $_POST['new_password'] ?? '');
        if ($result['success']) setFlash('success', $result['message']);
        else setFlash('error', $result['message']);
    }

    if ($action === 'set_pin') {
        $pin    = implode('', array_slice(array_map('trim', $_POST['pin_digit'] ?? []), 0, 6));
        $oldPin = $hasPin ? implode('', array_slice(array_map('trim', $_POST['old_pin_digit'] ?? []), 0, 6)) : null;
        $result = setTransactionPin($userId, $pin, $oldPin);
        if ($result['success']) setFlash('success', $result['message']);
        else setFlash('error', $result['message']);
    }

    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$pageTitle = 'Keamanan';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Keamanan Akun</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Keamanan</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<!-- Change Password -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:18px">🔑 Ganti Password</h3>
  <form method="POST" data-loading>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="change_password">

    <div class="form-group">
      <label class="form-label">Password Lama <span class="required">*</span></label>
      <div class="input-group">
        <input type="password" id="oldPw" name="old_password" class="form-control" required autocomplete="current-password">
        <button type="button" class="input-group-append" data-pw-toggle="oldPw">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
        </button>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Password Baru <span class="required">*</span></label>
      <div class="input-group">
        <input type="password" id="newPw" name="new_password" class="form-control" required autocomplete="new-password">
        <button type="button" class="input-group-append" data-pw-toggle="newPw">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
        </button>
      </div>
      <div class="form-hint">Min 8 karakter, huruf kapital & angka</div>
    </div>
    <div class="form-group">
      <label class="form-label">Konfirmasi Password Baru <span class="required">*</span></label>
      <input type="password" id="confPw" name="confirm_password" class="form-control" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-primary" onclick="if(document.getElementById('newPw').value!==document.getElementById('confPw').value){alert('Password tidak cocok!');return false}">Ganti Password</button>
  </form>
</div>

<!-- PIN Transaction -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:4px">🔐 PIN Transaksi</h3>
  <div class="alert alert-info" style="margin-bottom:16px;font-size:.82rem">
    PIN 6 digit diperlukan untuk setiap penarikan dana. Jangan bagikan PIN kepada siapapun.
  </div>
  <?php if ($hasPin): ?>
  <div class="alert alert-success" style="margin-bottom:16px;font-size:.82rem">✅ PIN sudah aktif</div>
  <?php else: ?>
  <div class="alert alert-warning" style="margin-bottom:16px;font-size:.82rem">⚠️ PIN belum dibuat — Anda tidak bisa withdraw!</div>
  <?php endif; ?>

  <form method="POST" data-loading>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="set_pin">

    <?php if ($hasPin): ?>
    <div class="form-group">
      <label class="form-label">PIN Lama <span class="required">*</span></label>
      <div class="pin-input-group" style="display:flex;gap:8px">
        <?php for($i=0;$i<6;$i++): ?>
        <input type="password" name="old_pin_digit[]" maxlength="1" class="form-control" style="width:44px;height:44px;text-align:center;font-size:1.1rem;font-weight:700;padding:0" inputmode="numeric" pattern="[0-9]">
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label class="form-label"><?= $hasPin ? 'PIN Baru' : 'Buat PIN' ?> <span class="required">*</span></label>
      <div class="pin-input-group" style="display:flex;gap:8px">
        <?php for($i=0;$i<6;$i++): ?>
        <input type="password" name="pin_digit[]" maxlength="1" class="form-control" style="width:44px;height:44px;text-align:center;font-size:1.1rem;font-weight:700;padding:0" inputmode="numeric" pattern="[0-9]">
        <?php endfor; ?>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Konfirmasi PIN <span class="required">*</span></label>
      <div class="pin-input-group" style="display:flex;gap:8px" id="confirmPinGroup">
        <?php for($i=0;$i<6;$i++): ?>
        <input type="password" name="confirm_pin_digit[]" maxlength="1" class="form-control" style="width:44px;height:44px;text-align:center;font-size:1.1rem;font-weight:700;padding:0" inputmode="numeric" pattern="[0-9]">
        <?php endfor; ?>
      </div>
    </div>

    <button type="submit" class="btn-primary" id="setPinBtn"><?= $hasPin ? 'Ganti PIN' : 'Buat PIN' ?></button>
  </form>
</div>
</div>

<script>
document.getElementById('setPinBtn')?.closest('form')?.addEventListener('submit', function(e) {
  const pins    = [...this.querySelectorAll('[name="pin_digit[]"]')].map(i => i.value).join('');
  const confPins= [...this.querySelectorAll('[name="confirm_pin_digit[]"]')].map(i => i.value).join('');
  if (pins.length !== 6) { e.preventDefault(); alert('PIN harus 6 digit!'); return; }
  if (pins !== confPins) { e.preventDefault(); alert('Konfirmasi PIN tidak cocok!'); return; }
});
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
