<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — User Settings
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
// ── GET /api/settings.php — Get all settings for current user ──
if ($method === 'GET') {
    $user = require_auth();
    $db = get_db();
    $stmt = $db->prepare('SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $settings = [];
    while ($row = $stmt->fetch()) {
        $decoded = json_decode($row['setting_value'], true);
        $settings[$row['setting_key']] = $decoded !== null ? $decoded : $row['setting_value'];
    }
    // Defaults
    $defaults = [
        'theme_accent' => '#f5e642',
        'theme_bg' => '#000000',
        'theme_surface' => '#111111',
        'theme_preset' => 'default',
        'font_size' => 'normal',
        'animations' => true,
        'sound_effects' => false,
        'notifications_enabled' => true,
        'notif_comments' => true,
        'notif_reactions' => true,
        'notif_friends' => true,
        'notif_followers' => true,
        'notif_mentions' => true,
        'notif_admin' => true,
        'notif_new_articles' => true,
        'notif_sound' => false,
        'notif_desktop' => false,
        'public_profile' => true,
        'show_online_status' => true,
        'show_activity' => true,
        'show_friends' => true,
        'show_email' => false,
        'show_connected_services' => true,
        'allow_friend_requests' => true,
        'allow_messages' => true,
        'language' => 'de',
        'compact_mode' => false,
        'high_contrast' => false,
        'reduce_motion' => false,
        'custom_cursor' => true,
        'code_font' => 'IBM Plex Mono',
        'content_filter' => true,
        'two_factor_enabled' => false,
        'session_timeout' => 30,
        'email_notifications' => false,
    ];
    $merged = array_merge($defaults, $settings);
    json_response($merged);
}
// ── PUT /api/settings.php — Update settings ──
if ($method === 'PUT') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    if (empty($body)) json_error('Keine Einstellungen');
    $stmt = $db->prepare('INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($body as $key => $value) {
        // Validate key
        if (!preg_match('/^[a-z_]+$/', $key)) continue;
        $encoded = is_string($value) ? $value : json_encode($value);
        $stmt->execute([$user['id'], $key, $encoded]);
    }
    json_response(['success' => true]);
}
// ── DELETE /api/settings.php — Reset all settings ──
if ($method === 'DELETE') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    if (($body['action'] ?? '') === 'reset_all') {
        $db->prepare('DELETE FROM user_settings WHERE user_id = ?')->execute([$user['id']]);
        json_response(['success' => true, 'message' => 'Alle Einstellungen zurückgesetzt']);
    }
    if (($body['action'] ?? '') === 'delete_account') {
        // Delete everything for this user
        $db->prepare('DELETE FROM user_settings WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM friends WHERE user_id = ? OR friend_id = ?')->execute([$user['id'], $user['id']]);
        $db->prepare('DELETE FROM followers WHERE user_id = ? OR follower_id = ?')->execute([$user['id'], $user['id']]);
        $db->prepare('DELETE FROM reactions WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM article_reactions WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM comments WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM messages WHERE from_user_id = ? OR to_user_id = ?')->execute([$user['id'], $user['id']]);
        $db->prepare('DELETE FROM oauth_connections WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM activity_log WHERE user_id = ?')->execute([$user['id']]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
        setcookie(SESSION_COOKIE, '', time() - 3600, '/', '', true, true);
        json_response(['success' => true, 'message' => 'Account gelöscht']);
    }
    json_error('Unbekannte Aktion');
}
json_error('Ungültige Anfrage');
