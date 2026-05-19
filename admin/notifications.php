<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'broadcast') {
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type    = trim($_POST['type'] ?? NOTIF_INFO);

        if (empty($title) || empty($message)) {
            setFlash('error', 'Judul dan pesan tidak boleh kosong.');
        } else {
            // Target filter
            $target     = trim($_POST['target'] ?? 'all');
            $minVip     = (int)($_POST['min_vip'] ?? 0);

            if ($target === 'all') {
                $users = db()->fetchAll('SELECT id FROM users WHERE is_active = 1 AND is_blocked = 0');
            } elseif ($target === 'vip') {
                $users = db()->fetchAll('SELECT id FROM users WHERE is_active = 1 AND is_blocked = 0 AND vip_level >= ?', 'i', $minVip);
            } elseif ($target === 'no_deposit') {
                $users = db()->fetchAll('SELECT id FROM users WHERE is_active = 1 AND is_blocked = 0 AND total_deposit = 0');
            } else {
                $users = db()->fetchAll('SELECT id FROM users WHERE is_active = 1 AND is_blocked = 0');
            }

            $count = 0;
            foreach ($users as $u) {
                db()->execute(
                    'INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)',
                    'isss', $u['id'], $title, $message, $type
                );
                $count++;
            }

            // Log this broadcast
            db()->execute(
                'INSERT INTO broadcast_logs (admin_id, title, message, type, target, recipient_count) VALUES (?,?,?,?,?,?)',
                'isssssi', $admin['id'], $title, $message, $type, $target, $count
            );

            logAdminAction($admin['id'], 'broadcast_notification', 'notification', 0, "\"$title\" ke $count member");
            setFlash('success', "✅ Notifikasi berhasil dikirim ke $count member.");
        }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'delete_log') {
        $id = (int)($_POST['log_id'] ?? 0);
        db()->execute('DELETE FROM broadcast_logs WHERE id = ?', 'i', $id);
        setFlash('success', 'Log dihapus.');
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
}

// ── Data ──────────────────────────────────────────────────
$broadcastLogs = db()->fetchAll(
    'SELECT bl.*, au.full_name as admin_name
     FROM broadcast_logs bl
     LEFT JOIN admin_users au ON bl.admin_id = au.id
     ORDER BY bl.created_at DESC LIMIT 20'
);

$totalMembers      = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE is_active = 1 AND is_blocked = 0')['c'] ?? 0;
$totalWithDeposit  = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE is_active = 1 AND is_blocked = 0 AND total_deposit > 0')['c'] ?? 0;
$totalNoDeposit    = $totalMembers - $totalWithDeposit;

$notifTypes = [
    NOTIF_INFO    => ['ℹ️ Info',     'badge-info'],
    NOTIF_SUCCESS => ['✅ Sukses',   'badge-success'],
    NOTIF_WARNING => ['⚠️ Warning',  'badge-warning'],
    NOTIF_ERROR   => ['❌ Error',    'badge-danger'],
    NOTIF_SYSTEM  => ['⚙️ Sistem',   'badge-muted'],
    NOTIF_DEPOSIT => ['💳 Deposit',  'badge-info'],
    NOTIF_VIP     => ['🏆 VIP',      'badge-purple'],
];

adminHeader('Kirim Notifikasi', 'notifications');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

<!-- Broadcast Form -->
<div class="card">
  <h3 class="card-title" style="margin-bottom:6px">📢 Kirim Broadcast Notifikasi</h3>
  <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:20px">Notifikasi akan masuk ke laci notifikasi masing-masing member.</p>

  <form method="POST" onsubmit="return confirmBroadcast(event)">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="broadcast">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Judul Notifikasi <span style="color:var(--text-danger)">*</span></label>
        <input type="text" name="title" id="notifTitle" class="form-control" placeholder="Contoh: Promo Deposit Bonus" required maxlength="100" oninput="updatePreview()">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Pesan <span style="color:var(--text-danger)">*</span></label>
        <textarea name="message" id="notifMessage" class="form-control" rows="4" placeholder="Isi pesan notifikasi yang akan dilihat member..." required maxlength="500" oninput="updatePreview()"></textarea>
        <div class="form-hint" id="charCount">0/500 karakter</div>
      </div>
      <div class="form-group">
        <label class="form-label">Tipe</label>
        <select name="type" id="notifType" class="form-control" onchange="updatePreview()">
          <?php foreach ($notifTypes as $k=>[$l,$c]): ?>
          <option value="<?= $k ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Target Penerima</label>
        <select name="target" id="notifTarget" class="form-control" onchange="updateTargetInfo()">
          <option value="all">👥 Semua Member (<?= number_format($totalMembers) ?>)</option>
          <option value="vip">🏆 Member VIP ≥ Level</option>
          <option value="no_deposit">💤 Belum Pernah Deposit (<?= number_format($totalNoDeposit) ?>)</option>
        </select>
      </div>
      <div class="form-group" id="vipLevelGroup" style="display:none">
        <label class="form-label">Minimal VIP Level</label>
        <select name="min_vip" class="form-control">
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <?php $vipCount = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE is_active=1 AND is_blocked=0 AND vip_level>=?','i',$i)['c']??0; ?>
          <option value="<?= $i ?>">VIP <?= $i ?>+ (<?= number_format($vipCount) ?> member)</option>
          <?php endfor; ?>
        </select>
      </div>
    </div>

    <!-- Preview -->
    <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;margin-bottom:16px">
      <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase">Preview Notifikasi</div>
      <div style="display:flex;gap:10px">
        <div id="prevIcon" style="font-size:1.3rem;flex-shrink:0">ℹ️</div>
        <div>
          <div id="prevTitle" style="font-weight:700;font-size:.9rem">Judul akan tampil di sini</div>
          <div id="prevMsg" style="font-size:.82rem;color:var(--text-secondary);margin-top:2px">Pesan akan tampil di sini</div>
        </div>
      </div>
    </div>

    <div id="targetInfo" style="padding:10px 14px;background:rgba(0,212,255,.08);border:1px solid var(--border-active);border-radius:var(--radius-md);font-size:.82rem;color:var(--cyan);margin-bottom:16px">
      📤 Akan dikirim ke <strong><?= number_format($totalMembers) ?></strong> member aktif
    </div>

    <button type="submit" class="btn-primary btn-lg">📢 Kirim Broadcast Sekarang</button>
  </form>
</div>

<!-- Side Stats -->
<div>
  <div class="card" style="margin-bottom:16px">
    <div style="font-weight:700;margin-bottom:12px;font-size:.9rem">👥 Target Audience</div>
    <?php foreach ([
      ['Semua Member',   $totalMembers,     'var(--cyan)'],
      ['Sudah Deposit',  $totalWithDeposit, 'var(--text-success)'],
      ['Belum Deposit',  $totalNoDeposit,   'var(--text-warning)'],
    ] as [$l,$v,$c]): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)">
      <span style="font-size:.85rem;color:var(--text-secondary)"><?= $l ?></span>
      <span style="font-weight:700;color:<?= $c ?>"><?= number_format($v) ?></span>
    </div>
    <?php endforeach; ?>
    <?php for ($i = 1; $i <= 5; $i++): ?>
    <?php $cnt = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE is_active=1 AND is_blocked=0 AND vip_level=?','i',$i)['c']??0; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0">
      <span style="font-size:.8rem;color:var(--text-muted)"><?= vipBadge($i) ?></span>
      <span style="font-size:.8rem;font-weight:600"><?= number_format($cnt) ?></span>
    </div>
    <?php endfor; ?>
  </div>

  <div class="card">
    <div style="font-weight:700;margin-bottom:10px;font-size:.9rem">📋 Tips Notifikasi</div>
    <?php foreach ([
      'Judul singkat & jelas (maks 50 karakter ideal)',
      'Sertakan call-to-action jika perlu',
      'Hindari kirim terlalu sering (max 1x/hari)',
      'Gunakan tipe yang sesuai isi pesan',
    ] as $tip): ?>
    <div style="display:flex;gap:6px;margin-bottom:6px;font-size:.8rem;color:var(--text-secondary)"><span style="color:var(--cyan);flex-shrink:0">→</span><?= $tip ?></div>
    <?php endforeach; ?>
  </div>
</div>

</div>

<!-- Broadcast History -->
<div class="card" style="margin-top:20px;padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">📜 Riwayat Broadcast (20 Terakhir)</div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Judul</th><th>Tipe</th><th>Target</th><th>Dikirim ke</th><th>Oleh</th><th>Waktu</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($broadcastLogs)): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada riwayat broadcast</td></tr>
        <?php else: ?>
        <?php foreach ($broadcastLogs as $log): ?>
        <?php [$tlbl, $tclr] = $notifTypes[$log['type']] ?? [$log['type'], 'badge-muted']; ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.875rem"><?= e(truncate($log['title'], 40)) ?></div>
            <div style="font-size:.72rem;color:var(--text-muted)"><?= e(truncate($log['message'], 50)) ?></div>
          </td>
          <td><span class="badge <?= $tclr ?>" style="font-size:.7rem"><?= $tlbl ?></span></td>
          <td style="font-size:.8rem;color:var(--text-secondary)"><?= e($log['target'] ?? 'all') ?></td>
          <td style="font-weight:700;color:var(--cyan)"><?= number_format($log['recipient_count'] ?? 0) ?> member</td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= e($log['admin_name'] ?? 'System') ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= timeAgo($log['created_at']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="delete_log"><input type="hidden" name="log_id" value="<?= $log['id'] ?>">
              <button type="submit" class="btn-ghost btn-sm" style="color:var(--text-danger)" onclick="return confirm('Hapus log ini?')">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const typeIcons = {
  info:'ℹ️', success:'✅', warning:'⚠️', error:'❌', system:'⚙️', deposit:'💳', vip:'🏆', withdraw:'💸', mining:'⛏️'
};

function updatePreview() {
  const title = document.getElementById('notifTitle').value || 'Judul akan tampil di sini';
  const msg   = document.getElementById('notifMessage').value || 'Pesan akan tampil di sini';
  const type  = document.getElementById('notifType').value;
  const charEl= document.getElementById('charCount');
  const msgEl = document.getElementById('notifMessage');
  charEl.textContent = msgEl.value.length + '/500 karakter';
  document.getElementById('prevTitle').textContent = title;
  document.getElementById('prevMsg').textContent   = msg;
  document.getElementById('prevIcon').textContent  = typeIcons[type] || 'ℹ️';
}

function updateTargetInfo() {
  const target   = document.getElementById('notifTarget').value;
  const vipGroup = document.getElementById('vipLevelGroup');
  const info     = document.getElementById('targetInfo');
  vipGroup.style.display = target === 'vip' ? 'block' : 'none';
  const msgs = {
    'all':       '📤 Akan dikirim ke <strong><?= number_format($totalMembers) ?></strong> member aktif',
    'vip':       '📤 Akan dikirim ke member VIP yang dipilih',
    'no_deposit':'📤 Akan dikirim ke <strong><?= number_format($totalNoDeposit) ?></strong> member belum deposit',
  };
  info.innerHTML = msgs[target] || msgs['all'];
}

function confirmBroadcast(e) {
  const title = document.getElementById('notifTitle').value;
  return confirm(`Kirim broadcast notifikasi "${title}" sekarang?\n\nPastikan isi pesan sudah benar.`);
}

document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<?php adminFooter(); ?>
