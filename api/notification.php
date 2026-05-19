<?php
/**
 * NOXARA API - Notification Endpoint
 * GET: action=list|read|read_all|chat_poll
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(false, 'Sesi tidak valid.', [], 401);
}

$userId = (int)$_SESSION['user_id'];
$action = trim($_GET['action'] ?? '');

// ── List notifications ────────────────────────────────────
if ($action === 'list') {
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $notifs = db()->fetchAll(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
        'iii', $userId, $limit, $offset
    );
    $unread = getUnreadNotifCount($userId);

    $data = array_map(fn($n) => [
        'id'         => (int)$n['id'],
        'title'      => $n['title'],
        'message'    => $n['message'],
        'type'       => $n['type'],
        'is_read'    => (bool)$n['is_read'],
        'action_url' => $n['action_url'],
        'time_ago'   => timeAgo($n['created_at']),
        'created_at' => $n['created_at'],
    ], $notifs);

    jsonResponse(true, '', ['notifications' => $data, 'unread_count' => $unread]);
}

// ── Mark single notification read ────────────────────────
if ($action === 'read') {
    $notifId = (int)($_GET['id'] ?? 0);
    if ($notifId) {
        markNotificationRead($notifId, $userId);
    }
    jsonResponse(true, 'Notifikasi ditandai dibaca.');
}

// ── Mark all read ─────────────────────────────────────────
if ($action === 'read_all') {
    markAllNotificationsRead($userId);
    jsonResponse(true, 'Semua notifikasi ditandai dibaca.', ['unread_count' => 0]);
}

// ── Delete notification ───────────────────────────────────
if ($action === 'delete') {
    $notifId = (int)($_GET['id'] ?? 0);
    if ($notifId) {
        deleteNotification($notifId, $userId);
    }
    jsonResponse(true, 'Notifikasi dihapus.');
}

// ── Unread count only ─────────────────────────────────────
if ($action === 'count') {
    jsonResponse(true, '', ['count' => getUnreadNotifCount($userId)]);
}

// ── Chat poll (for member chat page) ─────────────────────
if ($action === 'chat_poll') {
    $roomId = (int)($_GET['room_id'] ?? 0);
    $since  = (int)($_GET['last_id'] ?? 0);

    if (!$roomId) jsonResponse(false, 'Room ID tidak valid.');

    // Verify ownership
    $room = db()->fetchOne('SELECT id FROM chat_rooms WHERE id = ? AND user_id = ?', 'ii', $roomId, $userId);
    if (!$room) jsonResponse(false, 'Room tidak ditemukan.', [], 403);

    $messages = db()->fetchAll(
        "SELECT cm.*, COALESCE(au.full_name, 'CS NOXARA') as sender_name
         FROM chat_messages cm
         LEFT JOIN admin_users au ON cm.sender_id = au.id AND cm.sender_type = 'admin'
         WHERE cm.room_id = ? AND cm.id > ?
         ORDER BY cm.id ASC LIMIT 20",
        'ii', $roomId, $since
    );

    // Mark user-directed messages as read
    if (!empty($messages)) {
        db()->execute("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_type = 'admin' AND is_read = 0", 'i', $roomId);
        db()->execute('UPDATE chat_rooms SET unread_user = 0 WHERE id = ?', 'i', $roomId);
    }

    $data = array_map(fn($m) => [
        'id'          => (int)$m['id'],
        'sender_type' => $m['sender_type'],
        'message'     => $m['message'],
        'image'       => $m['image'] ? (UPLOADS_URL . '/chat/' . $m['image']) : null,
        'sender_name' => $m['sender_name'],
        'time'        => formatDatetime($m['created_at']),
        'created_at'  => $m['created_at'],
    ], $messages);

    jsonResponse(true, '', ['messages' => $data]);
}

// ── Get CS status ─────────────────────────────────────────
if ($action === 'cs_status') {
    $status = getSetting('cs_status', 'offline');
    jsonResponse(true, '', ['status' => $status]);
}

jsonResponse(false, 'Action tidak dikenal.');
