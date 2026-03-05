<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Notifications
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/notifications.php — List notifications ──
if ($method === 'GET' && (!$action || $action === 'list')) {
    $user = require_auth();
    $db = get_db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;
    $unread_only = ($_GET['unread'] ?? '0') === '1';
    $where = 'n.user_id = ?';
    $params = [$user['id']];
    if ($unread_only) {
        $where .= ' AND n.is_read = 0';
    }
    $stmt = $db->prepare("
        SELECT n.*, u.username as from_username, u.display_name as from_display_name, u.avatar as from_avatar
        FROM notifications n
        LEFT JOIN users u ON n.from_user_id = u.id
        WHERE {$where}
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    // Count unread
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    $unread = (int)$stmt->fetch()['c'];
    $total_stmt = $db->prepare('SELECT COUNT(*) as c FROM notifications WHERE user_id = ?');
    $total_stmt->execute([$user['id']]);
    $total = (int)$total_stmt->fetch()['c'];
    json_response([
        'notifications' => $notifications,
        'unread_count' => $unread,
        'total' => $total,
        'page' => $page,
    ]);
}
// ── GET /api/notifications.php?action=count — Unread count only ──
if ($method === 'GET' && $action === 'count') {
    $user = require_auth();
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    json_response(['unread_count' => (int)$stmt->fetch()['c']]);
}
// ── PUT /api/notifications.php — Mark as read ──
if ($method === 'PUT') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    $action = $body['action'] ?? 'read';
    if ($action === 'read' && isset($body['id'])) {
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
            ->execute([(int)$body['id'], $user['id']]);
        json_response(['success' => true]);
    }
    if ($action === 'read_all') {
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
            ->execute([$user['id']]);
        json_response(['success' => true]);
    }
    json_error('Unbekannte Aktion');
}
// ── DELETE /api/notifications.php — Delete notification ──
if ($method === 'DELETE') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    if (isset($body['id'])) {
        $db->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')
            ->execute([(int)$body['id'], $user['id']]);
    } elseif (($body['action'] ?? '') === 'clear_all') {
        $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$user['id']]);
    }
    json_response(['success' => true]);
}
json_error('Ungültige Anfrage');
