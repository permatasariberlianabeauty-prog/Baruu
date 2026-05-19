<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];

// Get or create room
$room = db()->fetchOne('SELECT * FROM chat_rooms WHERE user_id = ?', 'i', $userId);
if (!$room) {
    db()->execute('INSERT INTO chat_rooms (user_id) VALUES (?)', 'i', $userId);
    $room = db()->fetchOne('SELECT * FROM chat_rooms WHERE user_id = ?', 'i', $userId);
}
$roomId = $room['id'];

// Mark messages read by user
db()->execute("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_type = 'admin'", 'i', $roomId);
db()->execute("UPDATE chat_rooms SET unread_user = 0 WHERE id = ?", 'i', $roomId);

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $message = trim($_POST['message'] ?? '');
    $imgFile = $_FILES['image'] ?? null;
    $imgFilename = null;

    if ($imgFile && $imgFile['error'] === UPLOAD_ERR_OK) {
        $upload = uploadImage($imgFile, 'chat');
        if ($upload['success']) $imgFilename = $upload['filename'];
    }

    if (!empty($message) || $imgFilename) {
        db()->execute(
            "INSERT INTO chat_messages (room_id, sender_type, sender_id, message, image) VALUES (?,?,?,?,?)",
            'isiss', $roomId, 'user', $userId, $message ?: null, $imgFilename
        );
        db()->execute("UPDATE chat_rooms SET last_message_at = NOW(), unread_admin = unread_admin + 1 WHERE id = ?", 'i', $roomId);
        if (isXhrRequest()) { jsonResponse(true, 'Pesan terkirim'); }
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

// Load messages
$messages = db()->fetchAll(
    "SELECT * FROM chat_messages WHERE room_id = ? ORDER BY created_at ASC LIMIT 100",
    'i', $roomId
);

// CS status
$csStatus = getSetting('cs_status', 'online');
$csColors = ['online'=>'var(--text-success)','busy'=>'var(--text-warning)','offline'=>'var(--text-muted)'];
$csLabels = ['online'=>'Online','busy'=>'Sibuk','offline'=>'Offline'];

$pageTitle = 'Live Chat';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Live Chat</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Live Chat</div>
</div>

<div style="max-width:700px;margin:0 auto">
  <!-- CS Info -->
  <div class="card" style="margin-bottom:16px;padding:12px 16px;display:flex;align-items:center;gap:12px">
    <div style="width:42px;height:42px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">🎧</div>
    <div style="flex:1">
      <div style="font-weight:700">Customer Service <?= SITE_NAME ?></div>
      <div style="font-size:.8rem;display:flex;align-items:center;gap:6px">
        <span style="width:8px;height:8px;border-radius:50%;background:<?= $csColors[$csStatus] ?>;display:inline-block"></span>
        <span style="color:<?= $csColors[$csStatus] ?>"><?= $csLabels[$csStatus] ?></span>
      </div>
    </div>
    <div style="font-size:.75rem;color:var(--text-muted);text-align:right">Respon dalam<br><strong style="color:var(--cyan)">1-24 jam</strong></div>
  </div>

  <!-- Chat window -->
  <div class="card" style="padding:0;overflow:hidden">
    <div id="chatMessages" style="height:420px;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px">
      <?php if (empty($messages)): ?>
      <div style="text-align:center;color:var(--text-muted);padding:40px;font-size:.9rem">
        💬 Mulai percakapan dengan CS kami.<br>
        <span style="font-size:.8rem">Kami siap membantu Anda!</span>
      </div>
      <?php else: ?>
      <?php foreach ($messages as $msg):
        $isMe = $msg['sender_type'] === 'user';
      ?>
      <div style="display:flex;gap:8px;<?= $isMe ? 'flex-direction:row-reverse' : '' ?>" class="chat-msg" data-id="<?= $msg['id'] ?>">
        <?php if (!$isMe): ?>
        <div style="width:32px;height:32px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">🎧</div>
        <?php endif; ?>
        <div style="max-width:75%">
          <div style="background:<?= $isMe ? 'var(--gradient)' : 'var(--bg-card2)' ?>;color:<?= $isMe ? '#fff' : 'var(--text-primary)' ?>;border-radius:<?= $isMe ? 'var(--radius-md) var(--radius-md) 4px var(--radius-md)' : 'var(--radius-md) var(--radius-md) var(--radius-md) 4px' ?>;padding:10px 14px;font-size:.875rem;word-break:break-word">
            <?php if ($msg['image']): ?>
            <img src="<?= UPLOADS_URL ?>/chat/<?= e($msg['image']) ?>" style="max-width:200px;border-radius:8px;display:block;margin-bottom:<?= $msg['message'] ? '6px' : '0' ?>">
            <?php endif; ?>
            <?php if ($msg['message']): ?>
            <?= nl2br(e($msg['message'])) ?>
            <?php endif; ?>
          </div>
          <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px;text-align:<?= $isMe ? 'right' : 'left' ?>"><?= formatDatetime($msg['created_at']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Input area -->
    <div style="border-top:1px solid var(--border);padding:12px 16px">
      <form id="chatForm" method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div style="display:flex;gap:8px;align-items:flex-end">
          <div style="flex:1">
            <textarea name="message" id="chatInput" class="form-control" placeholder="Ketik pesan..." rows="2" style="resize:none;min-height:44px"></textarea>
          </div>
          <label class="btn-ghost btn-icon" title="Kirim Gambar" style="cursor:pointer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.7"/><circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" stroke-width="1.7"/><polyline points="21 15 16 10 5 21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <input type="file" name="image" accept="image/*" style="display:none" onchange="previewChatImg(this)">
          </label>
          <button type="submit" class="btn-primary btn-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><line x1="22" y1="2" x2="11" y2="13" stroke="white" stroke-width="2" stroke-linecap="round"/><polygon points="22 2 15 22 11 13 2 9 22 2" stroke="white" stroke-width="2" stroke-linejoin="round" fill="none"/></svg>
          </button>
        </div>
        <div id="imgPreview" style="display:none;margin-top:6px"></div>
      </form>
    </div>
  </div>
</div>

<script>
// Scroll to bottom
const chatBox = document.getElementById('chatMessages');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

// Auto-poll for new messages
let lastMsgId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
let pollTimer = setInterval(pollMessages, <?= CHAT_POLL_INTERVAL * 1000 ?>);

async function pollMessages() {
  try {
    const res  = await fetch(`${BASE_URL}/api/notification.php?action=chat_poll&room_id=<?= $roomId ?>&last_id=${lastMsgId}`, {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const data = await res.json();
    if (data.success && data.data.messages?.length) {
      data.data.messages.forEach(msg => {
        appendMessage(msg);
        lastMsgId = Math.max(lastMsgId, msg.id);
      });
      chatBox.scrollTop = chatBox.scrollHeight;
    }
  } catch(e) {}
}

function appendMessage(msg) {
  const isMe = msg.sender_type === 'user';
  const div  = document.createElement('div');
  div.className = 'chat-msg';
  div.dataset.id = msg.id;
  div.style.display = 'flex';
  div.style.gap = '8px';
  if (isMe) div.style.flexDirection = 'row-reverse';
  div.innerHTML = `
    ${!isMe ? '<div style="width:32px;height:32px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">🎧</div>' : ''}
    <div style="max-width:75%">
      <div style="background:${isMe?'var(--gradient)':'var(--bg-card2)'};color:${isMe?'#fff':'var(--text-primary)'};border-radius:var(--radius-md);padding:10px 14px;font-size:.875rem;word-break:break-word">
        ${msg.image ? `<img src="${BASE_URL}/uploads/chat/${msg.image}" style="max-width:200px;border-radius:8px;display:block">` : ''}
        ${msg.message ? msg.message.replace(/\n/g,'<br>') : ''}
      </div>
      <div style="font-size:.68px;color:var(--text-muted);margin-top:3px">${msg.time}</div>
    </div>`;
  chatBox.appendChild(div);
}

function previewChatImg(input) {
  const preview = document.getElementById('imgPreview');
  if (input.files?.[0]) {
    const url = URL.createObjectURL(input.files[0]);
    preview.innerHTML = `<img src="${url}" style="height:60px;border-radius:6px"> <button type="button" onclick="clearImgPreview()" style="background:none;border:none;color:var(--text-muted);cursor:pointer">✕</button>`;
    preview.style.display = 'flex';
    preview.style.alignItems = 'center';
    preview.style.gap = '8px';
  }
}
function clearImgPreview() {
  document.querySelector('input[name="image"]').value = '';
  document.getElementById('imgPreview').style.display = 'none';
  document.getElementById('imgPreview').innerHTML = '';
}

// Ctrl+Enter to send
document.getElementById('chatInput')?.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    document.getElementById('chatForm').submit();
  }
});
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
