<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId    = $_SESSION['user_id'];
$pageTitle = 'Deposit';

// Load data
$adminBanks   = db()->fetchAll('SELECT * FROM admin_bank_accounts WHERE is_active = 1 ORDER BY sort_order');
$pendingDeps  = db()->fetchAll("SELECT d.*, ab.bank_name, ab.account_number, ab.account_name FROM deposits d LEFT JOIN admin_bank_accounts ab ON d.admin_bank_id = ab.id WHERE d.user_id = ? AND d.status = 'pending' ORDER BY d.created_at DESC LIMIT 5", 'i', $userId);
$recentDeps   = db()->fetchAll("SELECT d.*, ab.bank_name FROM deposits d LEFT JOIN admin_bank_accounts ab ON d.admin_bank_id = ab.id WHERE d.user_id = ? ORDER BY d.created_at DESC LIMIT 10", 'i', $userId);
$depositEnabled = getSetting('deposit_enabled', '1');

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCsrf();

    if ($_POST['action'] === 'create_deposit') {
        if (!$depositEnabled) { setFlash('error','Fitur deposit sedang dinonaktifkan.'); }
        else {
            $amount      = (float)preg_replace('/\D/', '', $_POST['amount'] ?? '0');
            $bankId      = (int)($_POST['admin_bank_id'] ?? 0);
            $voucherCode = strtoupper(trim($_POST['voucher_code'] ?? ''));
            $voucherId   = null;

            if ($voucherCode) {
                $vc = validateVoucher($voucherCode, $userId, 'deposit', $amount);
                if ($vc['valid']) $voucherId = $vc['voucher']['id'];
                elseif (!$vc['valid']) { setFlash('error', $vc['message']); header('Location: '.$_SERVER['PHP_SELF']); exit; }
            }

            // Handle proof upload
            if (!empty($_FILES['proof']['name'])) {
                $upload = uploadImage($_FILES['proof'], 'deposits');
                if (!$upload['success']) { setFlash('error', $upload['message']); header('Location: '.$_SERVER['PHP_SELF']); exit; }
            }

            $result = createDeposit($userId, $amount, $bankId, $voucherId);
            if ($result['success']) {
                // Update proof image
                if (!empty($upload['filename'])) {
                    db()->execute('UPDATE deposits SET proof_image = ? WHERE id = ?', 'si', $upload['filename'], $result['deposit_id']);
                }
                setFlash('success', 'Deposit Rp'.number_format($result['total_amount'],0,',','.').' berhasil diajukan!');
                $_SESSION['show_popup'] = 'deposit_pending';
            } else {
                setFlash('error', $result['message']);
            }
            header('Location: '.$_SERVER['PHP_SELF']); exit;
        }
    }

    if ($_POST['action'] === 'upload_proof') {
        $depositId = (int)($_POST['deposit_id'] ?? 0);
        $dep = db()->fetchOne("SELECT * FROM deposits WHERE id = ? AND user_id = ? AND status = 'pending'", 'ii', $depositId, $userId);
        if ($dep && !empty($_FILES['proof']['name'])) {
            $upload = uploadImage($_FILES['proof'], 'deposits');
            if ($upload['success']) {
                db()->execute('UPDATE deposits SET proof_image = ? WHERE id = ?', 'si', $upload['filename'], $depositId);
                setFlash('success', 'Bukti transfer berhasil diupload.');
            } else { setFlash('error', $upload['message']); }
        }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
}

include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Deposit</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Deposit</div>
</div>

<?php if (!$depositEnabled): ?>
<div class="alert alert-warning"><strong>Fitur deposit sedang dinonaktifkan sementara.</strong> Silakan coba lagi nanti.</div>
<?php else: ?>

<!-- Pending deposits -->
<?php foreach ($pendingDeps as $pd): ?>
<div class="alert alert-warning" style="margin-bottom:12px">
  ⏳ Deposit <strong><?= formatRupiah($pd['total_amount']) ?></strong> ke <?= e($pd['bank_name']) ?> sedang menunggu konfirmasi.
  <?php if (!$pd['proof_image']): ?>
  <button class="btn-sm btn-primary" style="margin-left:8px" onclick="showUploadProof(<?= $pd['id'] ?>, '<?= formatRupiah($pd['total_amount']) ?>')">Upload Bukti</button>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<!-- Form Deposit -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:20px">Form Deposit Baru</h3>
  <form method="POST" enctype="multipart/form-data" data-loading id="depositForm">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="create_deposit">

    <div class="form-group">
      <label class="form-label">Nominal Deposit <span class="required">*</span></label>
      <div class="input-group">
        <span class="input-group-prepend" style="font-size:.85rem;font-weight:600;width:50px">Rp</span>
        <input type="number" name="amount" id="depositAmount" class="form-control" style="padding-left:54px" placeholder="50000" min="10000" required>
      </div>
      <div class="form-hint" id="amountPreview">Nominal belum diisi</div>
    </div>

    <div class="form-group">
      <label class="form-label">Rekening Tujuan <span class="required">*</span></label>
      <select name="admin_bank_id" class="form-control" required id="bankSelect">
        <option value="">-- Pilih Bank --</option>
        <?php foreach ($adminBanks as $b): ?>
        <option value="<?= $b['id'] ?>"><?= e($b['bank_name']) ?> — <?= e($b['account_number']) ?> (<?= e($b['account_name']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Bank detail card -->
    <div id="bankDetail" style="display:none" class="card-sm" style="background:var(--bg-card2);border:1px solid var(--border-active);margin-bottom:14px">
      <div style="font-size:.8rem;color:var(--text-muted)">Transfer ke:</div>
      <div id="bankDetailContent"></div>
    </div>

    <div class="form-group">
      <label class="form-label">Kode Voucher <span style="color:var(--text-muted);font-weight:400">(opsional)</span></label>
      <div style="display:flex;gap:8px">
        <input type="text" name="voucher_code" id="voucherCode" class="form-control" placeholder="Masukkan kode voucher" style="text-transform:uppercase">
        <button type="button" id="checkVoucherBtn" class="btn-secondary btn-sm" style="white-space:nowrap">Cek</button>
      </div>
      <input type="hidden" name="voucher_id" id="voucherIdInput">
      <div id="voucherInfo" class="form-hint"></div>
    </div>

    <div class="form-group">
      <label class="form-label">Bukti Transfer <span class="required">*</span></label>
      <input type="file" name="proof" class="form-control" accept="image/jpeg,image/png,image/webp" required>
      <div class="form-hint">Format: JPG/PNG/WebP, maks 5MB</div>
    </div>

    <div class="alert alert-info" style="margin-bottom:16px;font-size:.85rem">
      <div>💡 <strong>Penting:</strong> Nominal transfer akan ditambah kode unik 3 digit untuk mempermudah verifikasi. Pastikan transfer sesuai nominal yang ditampilkan.</div>
    </div>

    <button type="submit" class="btn-primary btn-block">Ajukan Deposit</button>
  </form>
</div>

<!-- Info & Recent -->
<div>
  <div class="card" style="margin-bottom:16px">
    <h3 class="card-title" style="margin-bottom:14px">Cara Deposit</h3>
    <ol style="padding-left:18px;font-size:.875rem;color:var(--text-secondary);line-height:2">
      <li>Pilih bank tujuan dan masukkan nominal</li>
      <li>Transfer sesuai nominal <strong style="color:var(--cyan)">termasuk kode unik</strong></li>
      <li>Upload bukti transfer</li>
      <li>Tunggu konfirmasi admin (maks <?= getSetting('deposit_expiry_hours',24) ?> jam)</li>
      <li>Saldo otomatis masuk setelah dikonfirmasi</li>
    </ol>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">Riwayat Deposit</h3></div>
    <?php if (!empty($recentDeps)): ?>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Nominal</th><th>Bank</th><th>Status</th><th>Waktu</th></tr></thead>
        <tbody>
          <?php foreach ($recentDeps as $d):
            $statusBadge = match($d['status']) {
              'confirmed'=>'<span class="badge badge-success">Dikonfirmasi</span>',
              'rejected' =>'<span class="badge badge-danger">Ditolak</span>',
              'expired'  =>'<span class="badge badge-muted">Expired</span>',
              default    =>'<span class="badge badge-warning">Pending</span>',
            };
          ?>
          <tr>
            <td style="font-weight:600"><?= formatRupiah($d['total_amount']) ?></td>
            <td><?= e($d['bank_name'] ?? '-') ?></td>
            <td><?= $statusBadge ?></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= timeAgo($d['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><p>Belum ada riwayat deposit</p></div>
    <?php endif; ?>
  </div>
</div>
</div>
<?php endif; ?>

<!-- Upload Proof Modal -->
<div class="modal-overlay" id="uploadProofModal" style="display:none">
  <div class="modal-box">
    <h3>Upload Bukti Transfer</h3>
    <p id="uploadProofDesc" style="color:var(--text-muted);margin:8px 0 16px"></p>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="upload_proof">
      <input type="hidden" name="deposit_id" id="uploadDepositId">
      <div class="form-group">
        <input type="file" name="proof" class="form-control" accept="image/*" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ghost" onclick="document.getElementById('uploadProofModal').style.display='none'">Batal</button>
        <button type="submit" class="btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>

<script>
const bankData = <?= json_encode(array_column($adminBanks, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('bankSelect')?.addEventListener('change', function() {
  const bank = bankData[this.value];
  const detail = document.getElementById('bankDetail');
  if (bank) {
    detail.style.display = 'block';
    document.getElementById('bankDetailContent').innerHTML = `
      <div style="margin-top:6px;font-size:.9rem">
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--text-muted)">Bank</span><strong>${bank.bank_name}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--text-muted)">No. Rekening</span>
          <strong>${bank.account_number} <button class="copy-btn" onclick="copyText('${bank.account_number}',this)">Salin</button></strong>
        </div>
        <div style="display:flex;justify-content:space-between;padding:4px 0">
          <span style="color:var(--text-muted)">Atas Nama</span><strong>${bank.account_name}</strong>
        </div>
      </div>`;
  } else { detail.style.display = 'none'; }
});

document.getElementById('depositAmount')?.addEventListener('input', function() {
  const val = parseInt(this.value) || 0;
  const prev = document.getElementById('amountPreview');
  if (val > 0) {
    prev.textContent = 'Nominal: Rp' + val.toLocaleString('id-ID') + ' + kode unik 3 digit = total akan berbeda sedikit';
    prev.className = 'form-hint text-cyan';
  } else { prev.textContent = 'Nominal belum diisi'; prev.className = 'form-hint'; }
});

function showUploadProof(id, amount) {
  document.getElementById('uploadDepositId').value = id;
  document.getElementById('uploadProofDesc').textContent = 'Deposit ' + amount;
  document.getElementById('uploadProofModal').style.display = 'flex';
}
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
