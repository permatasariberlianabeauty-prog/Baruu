<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$user   = db()->fetchOne('SELECT full_name FROM users WHERE id = ?', 'i', $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $bankName   = trim($_POST['bank_name']     ?? '');
        $accNumber  = trim($_POST['account_number'] ?? '');
        $accName    = trim($_POST['account_name']   ?? '');

        if (!$bankName || !$accNumber || !$accName) {
            setFlash('error', 'Semua field wajib diisi.');
        } else {
            // Check count
            $count = db()->fetchOne('SELECT COUNT(*) as cnt FROM bank_accounts WHERE user_id = ?', 'i', $userId)['cnt'] ?? 0;
            if ($count >= MAX_BANK_ACCOUNTS) {
                setFlash('error', 'Maks ' . MAX_BANK_ACCOUNTS . ' rekening per akun.');
            } else {
                $isPrimary = $count === 0 ? 1 : 0;
                db()->execute('INSERT INTO bank_accounts (user_id, bank_name, account_number, account_name, is_primary) VALUES (?,?,?,?,?)', 'isssi', $userId, $bankName, $accNumber, $accName, $isPrimary);
                setFlash('success', 'Rekening berhasil ditambahkan.');
            }
        }
    }

    if ($action === 'set_primary') {
        $bankId = (int)$_POST['bank_id'];
        // Verify ownership
        $bank = db()->fetchOne('SELECT id FROM bank_accounts WHERE id = ? AND user_id = ?', 'ii', $bankId, $userId);
        if ($bank) {
            db()->execute('UPDATE bank_accounts SET is_primary = 0 WHERE user_id = ?', 'i', $userId);
            db()->execute('UPDATE bank_accounts SET is_primary = 1 WHERE id = ?', 'i', $bankId);
            setFlash('success', 'Rekening utama berhasil diubah.');
        }
    }

    if ($action === 'delete') {
        $bankId = (int)$_POST['bank_id'];
        $bank   = db()->fetchOne('SELECT id FROM bank_accounts WHERE id = ? AND user_id = ?', 'ii', $bankId, $userId);
        if ($bank) {
            // Check no pending withdrawals
            $pendingWD = db()->fetchOne("SELECT id FROM withdrawals WHERE bank_account_id = ? AND status IN ('pending','processing')", 'i', $bankId);
            if ($pendingWD) {
                setFlash('error', 'Tidak bisa menghapus rekening yang sedang dalam proses penarikan.');
            } else {
                db()->execute('DELETE FROM bank_accounts WHERE id = ? AND user_id = ?', 'ii', $bankId, $userId);
                setFlash('success', 'Rekening berhasil dihapus.');
            }
        }
    }

    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$banks = db()->fetchAll('SELECT * FROM bank_accounts WHERE user_id = ? ORDER BY is_primary DESC, id ASC', 'i', $userId);
$bankList = ['BCA','Mandiri','BNI','BRI','CIMB Niaga','Danamon','Permata','BSI','BTN','Bank Jago','SeaBank','Blu BCA','Jenius','GoPay','OVO','Dana','ShopeePay'];

$pageTitle = 'Rekening Bank';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Rekening Bank</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Rekening Bank</div>
</div>

<div class="alert alert-info" style="margin-bottom:20px;font-size:.85rem">
  💡 Nama rekening <strong>harus sama</strong> dengan nama akun NOXARA Anda: <strong style="color:var(--cyan)"><?= e($user['full_name']) ?></strong>
</div>

<!-- Bank List -->
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px">
  <?php if (empty($banks)): ?>
  <div class="empty-state card" style="padding:32px">
    <div style="font-size:2.5rem;margin-bottom:8px">🏦</div>
    <p>Belum ada rekening terdaftar.<br>Tambahkan rekening untuk melakukan penarikan.</p>
  </div>
  <?php else: ?>
  <?php foreach ($banks as $bank): ?>
  <div class="card" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--bg-card2);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">🏦</div>
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-weight:700"><?= e($bank['bank_name']) ?></span>
        <?php if ($bank['is_primary']): ?>
        <span class="badge badge-success">Utama</span>
        <?php endif; ?>
      </div>
      <div style="font-family:var(--font-mono);font-size:1rem;font-weight:600;color:var(--cyan);margin:2px 0"><?= e($bank['account_number']) ?></div>
      <div style="font-size:.82rem;color:var(--text-secondary)"><?= e($bank['account_name']) ?></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if (!$bank['is_primary']): ?>
      <form method="POST" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="action"  value="set_primary">
        <input type="hidden" name="bank_id" value="<?= $bank['id'] ?>">
        <button type="submit" class="btn-ghost btn-sm">Set Utama</button>
      </form>
      <?php endif; ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Hapus rekening ini?')">
        <?= csrfField() ?>
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="bank_id" value="<?= $bank['id'] ?>">
        <button type="submit" class="btn-danger btn-sm">Hapus</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Add Bank Form -->
<?php if (count($banks) < MAX_BANK_ACCOUNTS): ?>
<div class="card">
  <h3 class="card-title" style="margin-bottom:18px">Tambah Rekening Baru</h3>
  <form method="POST" data-loading>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-group">
        <label class="form-label">Nama Bank <span class="required">*</span></label>
        <select name="bank_name" class="form-control" required>
          <option value="">-- Pilih Bank --</option>
          <?php foreach ($bankList as $b): ?>
          <option value="<?= e($b) ?>"><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Nomor Rekening <span class="required">*</span></label>
        <input type="text" name="account_number" class="form-control" placeholder="Nomor rekening" inputmode="numeric" required>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Nama Pemilik Rekening <span class="required">*</span></label>
      <input type="text" name="account_name" class="form-control" placeholder="Harus sama dengan nama NOXARA Anda" value="<?= e($user['full_name']) ?>" required>
      <div class="form-hint">Pastikan sama persis dengan nama di rekening bank</div>
    </div>
    <button type="submit" class="btn-primary">Tambah Rekening</button>
  </form>
</div>
<?php else: ?>
<div class="alert alert-warning">Anda sudah mencapai batas maksimal <?= MAX_BANK_ACCOUNTS ?> rekening.</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
