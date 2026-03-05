<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Users / Profiles
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$username = $_GET['username'] ?? '';
// ── GET /api/users.php?action=profile&id=X or &username=X ──
if ($method === 'GET' && $action === 'profile') {
    $db = get_db();
    if ($user_id) {
        $stmt = $db->prepare('SELECT id, username, display_name, email, avatar, bio, website, location, is_admin, is_public, created_at, last_seen FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
    } elseif ($username) {
        $stmt = $db->prepare('SELECT id, username, display_name, email, avatar, bio, website, location, is_admin, is_public, created_at, last_seen FROM users WHERE username = ?');
        $stmt->execute([$username]);
    } else {
        json_error('ID oder Username erforderlich');
    }
    $profile = $stmt->fetch();
    if (!$profile) json_error('Benutzer nicht gefunden', 404);
    // Get connected services (public info only)
    $stmt = $db->prepare('SELECT provider, provider_username FROM oauth_connections WHERE user_id = ?');
    $stmt->execute([$profile['id']]);
    $profile['connected_services'] = $stmt->fetchAll();
    // Get stats
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM comments WHERE user_id = ?');
    $stmt->execute([$profile['id']]);
    $profile['comments_count'] = (int)$stmt->fetch()['c'];
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM reactions WHERE user_id = ?');
    $stmt->execute([$profile['id']]);
    $profile['reactions_count'] = (int)$stmt->fetch()['c'];
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM friends WHERE (user_id = ? OR friend_id = ?) AND status = "accepted"');
    $stmt->execute([$profile['id'], $profile['id']]);
    $profile['friends_count'] = (int)$stmt->fetch()['c'];
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM followers WHERE user_id = ?');
    $stmt->execute([$profile['id']]);
    $profile['followers_count'] = (int)$stmt->fetch()['c'];
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM followers WHERE follower_id = ?');
    $stmt->execute([$profile['id']]);
    $profile['following_count'] = (int)$stmt->fetch()['c'];
    // Check relationship with current user
    $current = get_current_user_from_session();
    if ($current && $current['id'] != $profile['id']) {
        $stmt = $db->prepare('SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)');
        $stmt->execute([$current['id'], $profile['id'], $profile['id'], $current['id']]);
        $fr = $stmt->fetch();
        $profile['friendship_status'] = $fr ? $fr['status'] : null;
        $stmt = $db->prepare('SELECT id FROM followers WHERE user_id = ? AND follower_id = ?');
        $stmt->execute([$profile['id'], $current['id']]);
        $profile['is_following'] = (bool)$stmt->fetch();
    }
    // Get recent activity
    $stmt = $db->prepare('
        SELECT al.action, al.target_type, al.target_id, al.created_at, al.metadata
        FROM activity_log al WHERE al.user_id = ?
        ORDER BY al.created_at DESC LIMIT 20
    ');
    $stmt->execute([$profile['id']]);
    $profile['recent_activity'] = $stmt->fetchAll();
    json_response($profile);
}
// ── GET /api/users.php?action=search&q=... ──
if ($method === 'GET' && $action === 'search') {
    $q = $_GET['q'] ?? '';
    if (strlen($q) < 2) json_error('Mindestens 2 Zeichen');
    $db = get_db();
    $stmt = $db->prepare('SELECT id, username, display_name, avatar, bio, is_admin FROM users WHERE username LIKE ? OR display_name LIKE ? LIMIT 20');
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    json_response($stmt->fetchAll());
}
// ── PUT /api/users.php — Update own profile ──
if ($method === 'PUT') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    $allowed = ['display_name', 'bio', 'website', 'location', 'is_public'];
    $updates = [];
    $params = [];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            $updates[] = "$field = ?";
            $params[] = $body[$field];
        }
    }
    if (isset($body['avatar']) && filter_var($body['avatar'], FILTER_VALIDATE_URL)) {
        $updates[] = "avatar = ?";
        $params[] = $body['avatar'];
    }
    if (isset($body['email']) && filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
        $updates[] = "email = ?";
        $params[] = $body['email'];
    }
    if (empty($updates)) json_error('Keine Änderungen');
    $params[] = $user['id'];
    $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    // Log activity
    $db->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id) VALUES (?, ?, ?, ?)')
        ->execute([$user['id'], 'profile_update', 'user', $user['id']]);
    json_response(['success' => true]);
}
// ── GET /api/users.php?action=list (admin only) ──
if ($method === 'GET' && $action === 'list') {
    $admin = require_admin();
    $db = get_db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $stmt = $db->prepare('SELECT id, username, display_name, email, avatar, is_admin, provider, created_at, last_seen FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->execute([$limit, $offset]);
    $users = $stmt->fetchAll();
    $total = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    json_response(['users' => $users, 'total' => $total, 'page' => $page]);
}
json_error('Ungültige Anfrage');
