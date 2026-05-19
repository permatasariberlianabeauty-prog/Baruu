<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_item') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $type        = trim($_POST['type'] ?? 'cash');
        $value       = (float)($_POST['value'] ?? 0);
        $probability = (float)($_POST['probability'] ?? 10);
        $walletType  = trim($_POST['wallet_type'] ?? WALLET_BONUS);
        $isJackpot   = isset($_POST['is_jackpot']) ? 1 : 0;
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) { setFlash('error', 'Nama item tidak boleh kosong.'); header('Location: ' . $_SERVER['PHP_SELF']); exit; }

        if ($id) {
            db()->execute(
                'UPDATE daily_reward_items SET name=?,type=?,value=?,probability=?,wallet_type=?,is_jackpot=?,is_active=? WHERE id=?',
                'ssddssii', $name, $type, $value, $probability, $walletType, $isJackpot, $isActive, $id
            );
            logAdminAction($admin['id'], 'edit_daily_reward_item', 'daily_reward_item', $id);
            setFlash('success', 'Item hadiah berhasil diperbarui.');
        } else {
            db()->execute(
                'INSERT INTO daily_reward_items (name,type,value,probability,wallet_type,is_jackpot,is_active) VALUES (?,?,?,?,?,?,?)',
                'ssddssii', $name, $type, $value, $probability, $walletType, $isJackpot, $isActive
            );
            logAdminAction($admin['id'], 'add_daily_reward_item', 'daily_reward_item', db()->lastInsertId());
            setFlash('success', 'Item hadiah berhasil ditambahkan.');
        }
    }

    if ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute('DELETE FROM daily_reward_items WHERE id = ?', 'i', $id);
        setFlash('success', 'Item dihapus.');
    }

    if ($action === 'save_settings') {
        updateSetting('daily_reward_enabled', isset($_POST['enabled']) ? '1' : '0');
        updateSetting('daily_reward_reset_hour', (string)(int)$_POST['reset_hour']);
        logAdminAction($admin['id'], 'update_daily_reward_settings');
        setFlash('success', 'Pengaturan hadiah harian disimpan.');
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// ── Data ──────────────────────────────────────────────────
$editItem = isset($_GET['edit']) ? db()->fetchOne('SELECT * FROM daily_reward_items WHERE id = ?', 'i', (int)$_GET['edit']) : null;
$items    = db()->fetchAll('SELECT * FROM daily_reward_items ORDER BY probability DESC, id ASC');

$isEnabled = getSetting('daily_reward_enabled', '1') == '1';
$resetHour = (int)getSetting('daily_reward_reset_hour', 0);

// Stats
$todayClaims   = db()->fetchOne('SELECT COUNT(*) as c FROM user_daily_claims WHERE DATE(claimed_at) = CURDATE()')['c'] ?? 0;
$totalClaims   = db()->fetchOne('SELECT COUNT(*) as c FROM user_daily_claims')['c'] ?? 0;
$totalProbability = array_sum(array_column($items, 'probability'));

adminHeader('Hadiah Harian', 'daily_rewards');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
  <?php foreach ([
    ['🎁','Item Aktif',   count(array_filter($items, fn($i)=>$i['is_active'])), 'var(--cyan)'],
    ['💥','Jackpot',      count(array_filter($items, fn($i)=>$i['is_jackpot'])), 'var(--purple)'],
    ['📅','Klaim Hari Ini',$todayClaims,'var(--text-success)'],
    ['📊','Total Klaim',  $totalClaims,'var(--text-warning)'],
  ] as [$icon,$label,$val,$color]): ?>
  <div class="card" style="text-align:center;padding:14px">
    <div style="font-size:1.5rem"><?= $icon ?></div>
    <div style="font-size:1.3rem;font-weight:800;color:<?= $color ?>"><?= number_format($val) ?></div>
    <div style="font-size:.72rem;color:var(--text-muted)"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<!-- Items Table + Settings -->
<div>

<!-- Settings Card -->
<div class="card" style="margin-bottom:16px">
  <form method="POST" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_settings">
    <div class="form-check" style="margin-bottom:0">
      <input type="checkbox" name="enabled" id="drEnabled" <?= $isEnabled ? 'checked' : '' ?>>
      <label for="drEnabled" style="font-weight:600">Fitur Hadiah Harian Aktif</label>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <label style="font-size:.85rem;font-weight:600;color:var(--text-secondary)">Reset Jam:</label>
      <select name="reset_hour" class="form-control" style="width:80px">
        <?php for ($h = 0; $h <= 23; $h++): ?>
        <option value="<?= $h ?>" <?= $resetHour === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <?php if ($totalProbability != 100): ?>
    <div style="background:rgba(255,184,0,.1);border:1px solid rgba(255,184,0,.3);border-radius:var(--radius-sm);padding:6px 12px;font-size:.78rem;color:var(--text-warning)">
      ⚠️ Total probabilitas: <?= number_format($totalProbability, 2) ?>% (sebaiknya = 100%)
    </div>
    <?php else: ?>
    <div class="badge badge-success">✅ Probabilitas: 100%</div>
    <?php endif; ?>
    <button type="submit" class="btn-secondary btn-sm" style="margin-left:auto">💾 Simpan</button>
  </form>
</div>

<!-- Items Table -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700">🎁 Item Hadiah (<?= count($items) ?>)</div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Nama</th><th>Tipe</th><th>Nilai</th><th>Wallet</th><th>Probabilitas</th><th>Jackpot</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada item hadiah</td></tr>
        <?php else: ?>
        <?php foreach ($items as $item): ?>
        <tr style="<?= ($editItem && $editItem['id']==$item['id']) ? 'background:var(--cyan-dim)' : '' ?>">
          <td style="font-weight:600"><?= e($item['name']) ?></td>
          <td><span class="badge badge-purple"><?= e($item['type']) ?></span></td>
          <td style="color:var(--text-success);font-weight:700"><?= formatRupiah($item['value']) ?></td>
          <td style="font-size:.8rem;color:var(--text-muted)"><?= e($item['wallet_type']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="flex:1;background:var(--bg-input);border-radius:var(--radius-full);height:6px;overflow:hidden">
                <div style="width:<?= min(100, $item['probability']) ?>%;height:100%;background:var(--gradient);border-radius:inherit"></div>
              </div>
              <span style="font-size:.78rem;min-width:40px;text-align:right"><?= number_format($item['probability'], 2) ?>%</span>
            </div>
          </td>
          <td><?= $item['is_jackpot'] ? '💥 Ya' : '-' ?></td>
          <td><?= $item['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Off</span>' ?></td>
          <td>
            <a href="?edit=<?= $item['id'] ?>" class="btn-ghost btn-sm">✏️</a>
            <form method="POST" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus item ini?')">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- Form -->
<div class="card" style="position:sticky;top:20px">
  <h3 class="card-title" style="margin-bottom:16px"><?= $editItem ? '✏️ Edit Item' : '➕ Tambah Item' ?></h3>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_item">
    <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>

    <div class="form-group">
      <label class="form-label">Nama Item</label>
      <input type="text" name="name" class="form-control" value="<?= e($editItem['name'] ?? '') ?>" placeholder="Contoh: Bonus Cash" required>
    </div>
    <div class="form-group">
      <label class="form-label">Tipe</label>
      <select name="type" class="form-control">
        <?php foreach (['cash'=>'💵 Cash','bonus'=>'🎁 Bonus','voucher'=>'🎫 Voucher','jackpot'=>'💥 Jackpot'] as $k=>$l): ?>
        <option value="<?= $k ?>" <?= ($editItem['type'] ?? 'cash') === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Nilai (Rp)</label>
      <input type="number" name="value" class="form-control" value="<?= $editItem['value'] ?? 0 ?>" min="0" step="0.01" required>
    </div>
    <div class="form-group">
      <label class="form-label">Wallet Tujuan</label>
      <select name="wallet_type" class="form-control">
        <?php foreach ([WALLET_BONUS=>'Bonus',WALLET_MAIN=>'Utama',WALLET_REFERRAL=>'Referral'] as $k=>$l): ?>
        <option value="<?= $k ?>" <?= ($editItem['wallet_type'] ?? WALLET_BONUS) === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Probabilitas (%)</label>
      <input type="number" name="probability" class="form-control" value="<?= $editItem['probability'] ?? 10 ?>" min="0.01" max="100" step="0.01">
      <div class="form-hint">Total semua item sebaiknya = 100%</div>
    </div>
    <div class="form-check" style="margin-bottom:10px">
      <input type="checkbox" name="is_jackpot" id="isJackpot" <?= ($editItem['is_jackpot'] ?? 0) ? 'checked' : '' ?>>
      <label for="isJackpot">💥 Tandai sebagai Jackpot</label>
    </div>
    <div class="form-check" style="margin-bottom:16px">
      <input type="checkbox" name="is_active" id="itemActive" <?= (!$editItem || $editItem['is_active']) ? 'checked' : '' ?>>
      <label for="itemActive">Aktif</label>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit" class="btn-primary"><?= $editItem ? '💾 Simpan' : '➕ Tambah' ?></button>
      <?php if ($editItem): ?><a href="<?= BASE_URL ?>/admin/daily_rewards.php" class="btn-ghost">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

</div>

<?php adminFooter(); ?>
