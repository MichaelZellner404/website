<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Messages (Chat)
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/messages.php?action=conversations — List conversations ──
if ($method === 'GET' && $action === 'conversations') {
    $user = require_auth();
    $db = get_db();
    $stmt = $db->prepare('
        SELECT 
            CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END as partner_id,
            u.username as partner_username, u.display_name as partner_name, u.avatar as partner_avatar, u.last_seen as partner_last_seen,
            m.content as last_message, m.created_at as last_message_at,
            (SELECT COUNT(*) FROM messages m2 WHERE m2.from_user_id = partner_id AND m2.to_user_id = ? AND m2.is_read = 0) as unread
        FROM messages m
        JOIN users u ON u.id = CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END
        WHERE m.id IN (
            SELECT MAX(id) FROM messages 
            WHERE from_user_id = ? OR to_user_id = ?
            GROUP BY LEAST(from_user_id, to_user_id), GREATEST(from_user_id, to_user_id)
        )
        ORDER BY m.created_at DESC
    ');
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
    json_response($stmt->fetchAll());
}
// ── GET /api/messages.php?action=chat&with=X — Get messages with user ──
if ($method === 'GET' && $action === 'chat') {
    $user = require_auth();
    $with = (int)($_GET['with'] ?? 0);
    if (!$with) json_error('with Parameter erforderlich');
    $db = get_db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $stmt = $db->prepare('
        SELECT m.*, 
            fu.username as from_username, fu.display_name as from_name, fu.avatar as from_avatar,
            tu.username as to_username, tu.display_name as to_name, tu.avatar as to_avatar
        FROM messages m
        JOIN users fu ON m.from_user_id = fu.id
        JOIN users tu ON m.to_user_id = tu.id
        WHERE (m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$user['id'], $with, $with, $user['id'], $limit, $offset]);
    $messages = array_reverse($stmt->fetchAll());
    // Mark as read
    $db->prepare('UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0')
        ->execute([$with, $user['id']]);
    json_response($messages);
}
// ── GET /api/messages.php?action=unread_count ──
if ($method === 'GET' && $action === 'unread_count') {
    $user = require_auth();
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM messages WHERE to_user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    json_response(['unread' => (int)$stmt->fetch()['c']]);
}
// ── POST /api/messages.php — Send message ──
if ($method === 'POST') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    $to_user_id = (int)($body['to_user_id'] ?? 0);
    $content = trim($body['content'] ?? '');
    if (!$to_user_id) json_error('Empfänger erforderlich');
    if (!$content) json_error('Nachricht darf nicht leer sein');
    if (strlen($content) > 2000) json_error('Nachricht zu lang');
    if ($to_user_id === $user['id']) json_error('Kann nicht an sich selbst senden');
    // Check blocked
    $stmt = $db->prepare('SELECT id FROM friends WHERE user_id = ? AND friend_id = ? AND status = "blocked"');
    $stmt->execute([$to_user_id, $user['id']]);
    if ($stmt->fetch()) json_error('Blockiert');
    // Check if target allows messages
    $stmt = $db->prepare('SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = "allow_messages"');
    $stmt->execute([$to_user_id]);
    $setting = $stmt->fetch();
    if ($setting && $setting['setting_value'] === 'false') json_error('Dieser Benutzer akzeptiert keine Nachrichten');
    $db->prepare('INSERT INTO messages (from_user_id, to_user_id, content) VALUES (?, ?, ?)')
        ->execute([$user['id'], $to_user_id, $content]);
    json_response(['success' => true, 'id' => $db->lastInsertId()], 201);
}
json_error('Ungültige Anfrage');
