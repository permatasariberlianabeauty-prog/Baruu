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

    if ($action === 'save_vip') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute(
            'UPDATE vip_levels SET
                min_deposit_required  = ?,
                min_withdraw          = ?,
                withdraw_fee_percent  = ?,
                max_withdraw_per_day  = ?,
                daily_withdraw_limit  = ?,
                color                 = ?
             WHERE id = ?',
            'dddddsi',
            (float)$_POST['min_deposit_required'],
            (float)$_POST['min_withdraw'],
            (float)$_POST['withdraw_fee_percent'],
            (float)$_POST['max_withdraw_per_day'],
            (float)$_POST['daily_withdraw_limit'],
            trim($_POST['color'] ?? '#888888'),
            $id
        );
        logAdminAction($admin['id'], 'edit_vip_level', 'vip_level', $id);
        setFlash('success', 'VIP level berhasil disimpan.');
    }

    if ($action === 'save_code') {
        $codeId  = (int)($_POST['code_id'] ?? 0);
        $code    = strtoupper(trim($_POST['code'] ?? ''));
        $vipGrant= (int)$_POST['vip_level_grant'];
        $useLim  = (int)$_POST['use_limit'];
        $isActive= isset($_POST['is_active']) ? 1 : 0;

        if ($codeId) {
            db()->execute('UPDATE vip_codes SET code=?,vip_level_grant=?,use_limit=?,is_active=? WHERE id=?',
                'siiii', $code, $vipGrant, $useLim, $isActive, $codeId);
        } else {
            db()->execute('INSERT INTO vip_codes (code, vip_level_grant, use_limit, is_active) VALUES (?,?,?,?)',
                'siii', $code, $vipGrant, $useLim, $isActive);
        }
        logAdminAction($admin['id'], $codeId ? 'edit_vip_code' : 'add_vip_code', 'vip_code', $codeId ?: db()->lastInsertId());
        setFlash('success', 'VIP code berhasil disimpan.');
    }

    if ($action === 'delete_code') {
        $codeId = (int)($_POST['code_id'] ?? 0);
        db()->execute('DELETE FROM vip_codes WHERE id = ?', 'i', $codeId);
        setFlash('success', 'VIP code dihapus.');
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// ── Data ──────────────────────────────────────────────────
$vipLevels = db()->fetchAll('SELECT * FROM vip_levels ORDER BY level ASC');
$vipCodes  = db()->fetchAll('SELECT * FROM vip_codes ORDER BY id ASC');
$editCode  = isset($_GET['edit_code']) ? db()->fetchOne('SELECT * FROM vip_codes WHERE id = ?', 'i', (int)$_GET['edit_code']) : null;
$editLevel = isset($_GET['edit']) ? (int)$_GET['edit'] : null;

$vipNames  = ['Pemula','Bronze','Silver','Gold','Platinum','Diamond'];
$vipColors = ['#888888','#CD7F32','#C0C0C0','#FFD700','#00D4FF','#7B2FFF'];

adminHeader('Setting VIP', 'vip_settings');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

<!-- VIP Levels Table -->
<div>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:20px">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:1rem">🏆 Level VIP (Klik baris untuk edit)</div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Level</th>
          <th>Min Deposit</th>
          <th>Min Withdraw</th>
          <th>Fee %</th>
          <th>Maks WD/Hari</th>
          <th>Limit WD Harian</th>
          <th>Warna</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vipLevels as $vl): ?>
        <?php $lvl = (int)$vl['level']; $isEditing = $editLevel === $lvl; ?>
        <tr style="<?= $isEditing ? 'background:var(--cyan-dim)' : '' ?>">
          <?php if ($isEditing): ?>
          <td colspan="8">
            <form method="POST" style="padding:4px 0">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="save_vip">
              <input type="hidden" name="id" value="<?= $vl['id'] ?>">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-weight:800;color:<?= e($vl['color'] ?? $vipColors[$lvl]) ?>;min-width:80px">VIP <?= $lvl ?> <?= $vipNames[$lvl] ?? '' ?></span>
                <?php foreach ([
                  'min_deposit_required' => ['Min Deposit',    'number', $vl['min_deposit_required']],
                  'min_withdraw'         => ['Min WD',         'number', $vl['min_withdraw']],
                  'withdraw_fee_percent' => ['Fee %',          'number', $vl['withdraw_fee_percent']],
                  'max_withdraw_per_day' => ['Maks WD/Hari',   'number', $vl['max_withdraw_per_day']],
                  'daily_withdraw_limit' => ['Limit WD Harian','number', $vl['daily_withdraw_limit']],
                  'color'                => ['Warna',          'color',  $vl['color'] ?? $vipColors[$lvl]],
                ] as $field => [$lbl, $type, $val]): ?>
                <div style="display:flex;flex-direction:column;gap:2px">
                  <label style="font-size:.68rem;color:var(--text-muted)"><?= $lbl ?></label>
                  <input type="<?= $type ?>" name="<?= $field ?>" class="form-control" value="<?= e($val) ?>" style="width:<?= $type==='color'?'50px':'110px' ?>;min-height:34px;padding:4px 8px;font-size:.8rem" step="0.01">
                </div>
                <?php endforeach; ?>
                <div style="display:flex;gap:6px;margin-top:14px">
                  <button type="submit" class="btn-success btn-sm">💾</button>
                  <a href="<?= BASE_URL ?>/admin/vip_settings.php" class="btn-ghost btn-sm">✕</a>
                </div>
              </div>
            </form>
          </td>
          <?php else: ?>
          <td><span style="font-weight:800;color:<?= e($vl['color'] ?? $vipColors[$lvl]) ?>">VIP <?= $lvl ?><br><small><?= $vipNames[$lvl] ?? '' ?></small></span></td>
          <td><?= formatRupiah($vl['min_deposit_required']) ?></td>
          <td><?= formatRupiah($vl['min_withdraw']) ?></td>
          <td><?= number_format($vl['withdraw_fee_percent'], 2) ?>%</td>
          <td><?= formatRupiah($vl['max_withdraw_per_day']) ?></td>
          <td><?= formatRupiah($vl['daily_withdraw_limit']) ?></td>
          <td><span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:<?= e($vl['color'] ?? $vipColors[$lvl]) ?>"></span> <code style="font-size:.72rem"><?= e($vl['color'] ?? '') ?></code></td>
          <td><a href="?edit=<?= $lvl ?>" class="btn-ghost btn-sm">✏️ Edit</a></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($vipLevels)): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted)">Tidak ada data VIP levels</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- VIP Codes Table -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:1rem">🔑 VIP Codes</div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Kode</th><th>Grant VIP</th><th>Limit Pakai</th><th>Sudah Pakai</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($vipCodes)): ?>
        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada VIP code</td></tr>
        <?php else: ?>
        <?php foreach ($vipCodes as $vc): ?>
        <tr>
          <td><code style="font-size:.85rem;color:var(--cyan)"><?= e($vc['code']) ?></code></td>
          <td><?= vipBadge((int)$vc['vip_level_grant']) ?></td>
          <td><?= $vc['use_limit'] ? number_format($vc['use_limit']) : '∞' ?></td>
          <td><?= number_format($vc['used_count'] ?? 0) ?></td>
          <td><?= $vc['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Nonaktif</span>' ?></td>
          <td>
            <a href="?edit_code=<?= $vc['id'] ?>" class="btn-ghost btn-sm">✏️</a>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete_code">
              <input type="hidden" name="code_id" value="<?= $vc['id'] ?>">
              <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Hapus code ini?')">🗑</button>
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

<!-- Right Panel: Code Form -->
<div>
<div class="card">
  <h3 class="card-title" style="margin-bottom:16px"><?= $editCode ? '✏️ Edit VIP Code' : '➕ Tambah VIP Code' ?></h3>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_code">
    <?php if ($editCode): ?><input type="hidden" name="code_id" value="<?= $editCode['id'] ?>"><?php endif; ?>

    <div class="form-group">
      <label class="form-label">Kode</label>
      <input type="text" name="code" class="form-control" value="<?= e($editCode['code'] ?? '') ?>" placeholder="Contoh: VIP2024" style="font-family:monospace;text-transform:uppercase" required>
    </div>
    <div class="form-group">
      <label class="form-label">Grant VIP Level</label>
      <select name="vip_level_grant" class="form-control">
        <?php for ($i = 0; $i <= 5; $i++): ?>
        <option value="<?= $i ?>" <?= ($editCode['vip_level_grant'] ?? 1) == $i ? 'selected' : '' ?>>VIP <?= $i ?> — <?= $vipNames[$i] ?? '' ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Batas Penggunaan (0 = tidak terbatas)</label>
      <input type="number" name="use_limit" class="form-control" value="<?= (int)($editCode['use_limit'] ?? 0) ?>" min="0">
    </div>
    <div class="form-check" style="margin-bottom:16px">
      <input type="checkbox" name="is_active" id="codeActive" <?= (!$editCode || $editCode['is_active']) ? 'checked' : '' ?>>
      <label for="codeActive">Aktif</label>
    </div>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn-primary"><?= $editCode ? '💾 Simpan' : '➕ Buat Code' ?></button>
      <?php if ($editCode): ?><a href="<?= BASE_URL ?>/admin/vip_settings.php" class="btn-ghost">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- Quick stats per level -->
<div class="card" style="margin-top:16px">
  <div style="font-weight:700;margin-bottom:12px;font-size:.9rem">📊 Member per VIP</div>
  <?php for ($i = 0; $i <= 5; $i++): ?>
  <?php $cnt = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE vip_level = ?', 'i', $i)['c'] ?? 0; ?>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
    <span style="width:60px;font-size:.75rem;color:<?= $vipColors[$i] ?>;font-weight:700">VIP <?= $i ?></span>
    <div style="flex:1;background:var(--bg-input);border-radius:var(--radius-full);height:8px;overflow:hidden">
      <?php $total = db()->fetchOne('SELECT COUNT(*) as c FROM users')['c'] ?: 1; ?>
      <div style="width:<?= min(100, round($cnt/$total*100)) ?>%;height:100%;background:<?= $vipColors[$i] ?>;border-radius:inherit"></div>
    </div>
    <span style="font-size:.75rem;color:var(--text-muted);min-width:30px;text-align:right"><?= number_format($cnt) ?></span>
  </div>
  <?php endfor; ?>
</div>
</div>

</div>

<?php adminFooter(); ?>
