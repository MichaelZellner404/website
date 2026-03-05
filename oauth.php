<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — OAuth Service Management (Connect/Disconnect)
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/oauth.php?action=services — List connected services ──
if ($method === 'GET' && $action === 'services') {
    $user = require_auth();
    $db = get_db();
    $stmt = $db->prepare('SELECT id, provider, provider_username, provider_email, provider_avatar, connected_at FROM oauth_connections WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    json_response($stmt->fetchAll());
}
// ── DELETE /api/oauth.php?action=disconnect&provider=X — Disconnect OAuth service ──
if ($method === 'DELETE' || ($method === 'POST' && $action === 'disconnect')) {
    $user = require_auth();
    $body = get_json_body();
    $provider = $body['provider'] ?? $_GET['provider'] ?? '';
    if (!$provider) json_error('Provider erforderlich');
    $db = get_db();
    // Check if this is the only login method
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM oauth_connections WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $count = (int)$stmt->fetch()['c'];
    // Check if user has a password (admin login)
    $has_password = $user['provider'] === 'admin' || !empty($user['password_hash']);
    if ($count <= 1 && !$has_password) {
        json_error('Du kannst deinen letzten Login-Dienst nicht trennen. Verbinde zuerst einen anderen Dienst.');
    }
    $db->prepare('DELETE FROM oauth_connections WHERE user_id = ? AND provider = ?')
        ->execute([$user['id'], $provider]);
    // Notification
    $db->prepare('INSERT INTO notifications (user_id, type, message) VALUES (?, "system", ?)')
        ->execute([$user['id'], ucfirst($provider) . ' wurde getrennt.']);
    json_response(['success' => true, 'message' => ucfirst($provider) . ' getrennt']);
}
json_error('Ungueltige Anfrage');