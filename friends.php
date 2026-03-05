<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Friends & Followers
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/friends.php?action=list — My friends ──
if ($method === 'GET' && $action === 'list') {
    $user = require_auth();
    $db = get_db();
    $status = $_GET['status'] ?? 'accepted';
    $stmt = $db->prepare('
        SELECT f.id as friendship_id, f.status, f.created_at as since,
            CASE WHEN f.user_id = ? THEN u2.id ELSE u1.id END as friend_id,
            CASE WHEN f.user_id = ? THEN u2.username ELSE u1.username END as username,
            CASE WHEN f.user_id = ? THEN u2.display_name ELSE u1.display_name END as display_name,
            CASE WHEN f.user_id = ? THEN u2.avatar ELSE u1.avatar END as avatar,
            CASE WHEN f.user_id = ? THEN u2.bio ELSE u1.bio END as bio,
            CASE WHEN f.user_id = ? THEN u2.last_seen ELSE u1.last_seen END as last_seen
        FROM friends f
        JOIN users u1 ON f.user_id = u1.id
        JOIN users u2 ON f.friend_id = u2.id
        WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = ?
        ORDER BY f.updated_at DESC
    ');
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $status]);
    json_response($stmt->fetchAll());
}
// ── GET /api/friends.php?action=requests — Incoming friend requests ──
if ($method === 'GET' && $action === 'requests') {
    $user = require_auth();
    $db = get_db();
    $stmt = $db->prepare('
        SELECT f.id as friendship_id, f.created_at,
            u.id as user_id, u.username, u.display_name, u.avatar, u.bio
        FROM friends f
        JOIN users u ON f.user_id = u.id
        WHERE f.friend_id = ? AND f.status = "pending"
        ORDER BY f.created_at DESC
    ');
    $stmt->execute([$user['id']]);
    json_response($stmt->fetchAll());
}
// ── GET /api/friends.php?action=sent — Sent friend requests ──
if ($method === 'GET' && $action === 'sent') {
    $user = require_auth();
    $db = get_db();
    $stmt = $db->prepare('
        SELECT f.id as friendship_id, f.created_at,
            u.id as user_id, u.username, u.display_name, u.avatar
        FROM friends f
        JOIN users u ON f.friend_id = u.id
        WHERE f.user_id = ? AND f.status = "pending"
        ORDER BY f.created_at DESC
    ');
    $stmt->execute([$user['id']]);
    json_response($stmt->fetchAll());
}
// ── POST /api/friends.php — Send friend request ──
if ($method === 'POST') {
    $user = require_auth();
    $body = get_json_body();
    $action = $body['action'] ?? 'send';
    $db = get_db();
    if ($action === 'send') {
        $friend_id = (int)($body['friend_id'] ?? 0);
        if (!$friend_id || $friend_id === $user['id']) json_error('Ungültige Anfrage');
        // Check if user exists
        $stmt = $db->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$friend_id]);
        $friend = $stmt->fetch();
        if (!$friend) json_error('Benutzer nicht gefunden', 404);
        // Check existing friendship
        $stmt = $db->prepare('SELECT id, status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)');
        $stmt->execute([$user['id'], $friend_id, $friend_id, $user['id']]);
        $existing = $stmt->fetch();
        if ($existing) {
            if ($existing['status'] === 'accepted') json_error('Bereits befreundet');
            if ($existing['status'] === 'pending') json_error('Anfrage bereits gesendet');
            if ($existing['status'] === 'blocked') json_error('Dieser Benutzer ist blockiert');
            // If declined, allow re-sending
            $db->prepare('UPDATE friends SET status = "pending", user_id = ?, friend_id = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$user['id'], $friend_id, $existing['id']]);
        } else {
            $db->prepare('INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, "pending")')
                ->execute([$user['id'], $friend_id]);
        }
        // Notify the friend
        $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_type, message) VALUES (?, "friend_request", ?, "user", ?)')
            ->execute([$friend_id, $user['id'], ($user['display_name'] ?: $user['username']) . ' möchte dich als Freund hinzufügen']);
        // Log activity
        $db->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id) VALUES (?, "friend_request_sent", "user", ?)')
            ->execute([$user['id'], $friend_id]);
        json_response(['success' => true, 'message' => 'Freundschaftsanfrage gesendet']);
    }
    if ($action === 'accept') {
        $friendship_id = (int)($body['friendship_id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM friends WHERE id = ? AND friend_id = ? AND status = "pending"');
        $stmt->execute([$friendship_id, $user['id']]);
        $fr = $stmt->fetch();
        if (!$fr) json_error('Anfrage nicht gefunden', 404);
        $db->prepare('UPDATE friends SET status = "accepted" WHERE id = ?')->execute([$friendship_id]);
        // Notify the requester
        $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_type, message) VALUES (?, "friend_accepted", ?, "user", ?)')
            ->execute([$fr['user_id'], $user['id'], ($user['display_name'] ?: $user['username']) . ' hat deine Freundschaftsanfrage angenommen']);
        // Log
        $db->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id) VALUES (?, "friend_accepted", "user", ?)')
            ->execute([$user['id'], $fr['user_id']]);
        json_response(['success' => true]);
    }
    if ($action === 'decline') {
        $friendship_id = (int)($body['friendship_id'] ?? 0);
        $db->prepare('UPDATE friends SET status = "declined" WHERE id = ? AND friend_id = ? AND status = "pending"')
            ->execute([$friendship_id, $user['id']]);
        json_response(['success' => true]);
    }
    if ($action === 'remove') {
        $friend_id = (int)($body['friend_id'] ?? 0);
        $db->prepare('DELETE FROM friends WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) AND status = "accepted"')
            ->execute([$user['id'], $friend_id, $friend_id, $user['id']]);
        json_response(['success' => true]);
    }
    if ($action === 'block') {
        $friend_id = (int)($body['friend_id'] ?? 0);
        // Remove existing friendship
        $db->prepare('DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)')
            ->execute([$user['id'], $friend_id, $friend_id, $user['id']]);
        // Create block
        $db->prepare('INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, "blocked")')
            ->execute([$user['id'], $friend_id]);
        // Also unfollow
        $db->prepare('DELETE FROM followers WHERE (user_id = ? AND follower_id = ?) OR (user_id = ? AND follower_id = ?)')
            ->execute([$user['id'], $friend_id, $friend_id, $user['id']]);
        json_response(['success' => true]);
    }
    // Follow/Unfollow
    if ($action === 'follow') {
        $target_id = (int)($body['user_id'] ?? 0);
        if (!$target_id || $target_id === $user['id']) json_error('Ungültige Anfrage');
        // Check blocked
        $stmt = $db->prepare('SELECT id FROM friends WHERE user_id = ? AND friend_id = ? AND status = "blocked"');
        $stmt->execute([$target_id, $user['id']]);
        if ($stmt->fetch()) json_error('Blockiert');
        $db->prepare('INSERT IGNORE INTO followers (user_id, follower_id) VALUES (?, ?)')
            ->execute([$target_id, $user['id']]);
        // Notify
        $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_type, message) VALUES (?, "follow", ?, "user", ?)')
            ->execute([$target_id, $user['id'], ($user['display_name'] ?: $user['username']) . ' folgt dir jetzt']);
        json_response(['success' => true]);
    }
    if ($action === 'unfollow') {
        $target_id = (int)($body['user_id'] ?? 0);
        $db->prepare('DELETE FROM followers WHERE user_id = ? AND follower_id = ?')
            ->execute([$target_id, $user['id']]);
        json_response(['success' => true]);
    }
    json_error('Unbekannte Aktion');
}
json_error('Ungültige Anfrage');
