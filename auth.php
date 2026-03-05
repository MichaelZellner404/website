<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Authentication (OAuth + Admin)
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$provider = $_GET['provider'] ?? '';
$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'login' : 'redirect');
// ── POST /api/auth.php — Admin login or check session ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_json_body();
    $action = $body['action'] ?? 'login';
    // Check current session
    if ($action === 'check') {
        $user = get_current_user_from_session();
        if (!$user) json_response(['authenticated' => false]);
        unset($user['password_hash']);
        // Get connected services
        $db = get_db();
        $stmt = $db->prepare('SELECT provider, provider_username, provider_avatar FROM oauth_connections WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $user['connected_services'] = $stmt->fetchAll();
        // Get notification count
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$user['id']]);
        $user['unread_notifications'] = (int)$stmt->fetch()['c'];
        // Get friend request count
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM friends WHERE friend_id = ? AND status = "pending"');
        $stmt->execute([$user['id']]);
        $user['pending_friend_requests'] = (int)$stmt->fetch()['c'];
        // Get friends count
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM friends WHERE (user_id = ? OR friend_id = ?) AND status = "accepted"');
        $stmt->execute([$user['id'], $user['id']]);
        $user['friends_count'] = (int)$stmt->fetch()['c'];
        // Get followers count
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM followers WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $user['followers_count'] = (int)$stmt->fetch()['c'];
        // Get following count
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM followers WHERE follower_id = ?');
        $stmt->execute([$user['id']]);
        $user['following_count'] = (int)$stmt->fetch()['c'];
        json_response(['authenticated' => true, 'user' => $user]);
    }
    // Admin login
    if ($action === 'admin_login') {
        $password = $body['password'] ?? '';
        if ($password !== ADMIN_PASSWORD) {
            json_error('Falsches Passwort', 401);
        }
        $db = get_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND is_admin = 1');
        $stmt->execute(['Michael']);
        $admin = $stmt->fetch();
        if (!$admin) {
            // Create admin if not exists
            $db->prepare('INSERT INTO users (username, display_name, is_admin, provider) VALUES (?, ?, 1, ?)')
                ->execute(['Michael', 'Michael (Admin)', 'admin']);
            $stmt2 = $db->prepare('SELECT * FROM users WHERE username = ? AND is_admin = 1');
            $stmt2->execute(['Michael']);
            $admin = $stmt2->fetch();
        }
        $token = create_session($admin['id']);
        unset($admin['password_hash']);
        json_response(['user' => $admin, 'token' => $token]);
    }
    // Logout
    if ($action === 'logout') {
        $token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE[SESSION_COOKIE] ?? null;
        if ($token) {
            $db = get_db();
            $db->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
        }
        setcookie(SESSION_COOKIE, '', time() - 3600, '/', '', true, true);
        json_response(['success' => true]);
    }
    json_error('Unbekannte Aktion');
}
// ── OAuth Flow: Redirect to provider ──
if ($action === 'redirect' && $provider) {
    $url = get_oauth_redirect_url($provider);
    if (!$url) json_error('Unbekannter Provider: ' . $provider);
    header('Location: ' . $url);
    exit;
}
// ── OAuth Flow: Callback from provider ──
if ($action === 'callback' && $provider) {
    $code = $_GET['code'] ?? '';
    $error = $_GET['error'] ?? '';
    if ($error) {
        header('Location: ' . SITE_URL . '/settings.html?oauth_error=' . urlencode($error));
        exit;
    }
    if (!$code) {
        header('Location: ' . SITE_URL . '/settings.html?oauth_error=no_code');
        exit;
    }
    try {
        $provider_data = exchange_oauth_code($provider, $code);
        $user = process_oauth_login($provider, $provider_data);
        $token = create_session($user['id']);
        // Redirect back to site with token
        header('Location: ' . SITE_URL . '/index.html?auth_token=' . urlencode($token) . '&provider=' . urlencode($provider));
        exit;
    } catch (Exception $e) {
        header('Location: ' . SITE_URL . '/settings.html?oauth_error=' . urlencode($e->getMessage()));
        exit;
    }
}
// ── Connect additional provider to existing account ──
if ($action === 'connect' && $provider) {
    // Store the current user's session in a temporary state
    $state = $_GET['state'] ?? '';
    $url = get_oauth_redirect_url($provider, $state ?: 'connect');
    header('Location: ' . $url);
    exit;
}
json_error('Ungültige Anfrage');
// ═══════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════
function get_oauth_redirect_url($provider, $state = 'login') {
    switch ($provider) {
        case 'github':
            return 'https://github.com/login/oauth/authorize?' . http_build_query([
                'client_id' => GITHUB_CLIENT_ID,
                'redirect_uri' => GITHUB_REDIRECT_URI,
                'scope' => 'read:user user:email',
                'state' => $state,
            ]);
        case 'google':
            return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => GOOGLE_CLIENT_ID,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'access_type' => 'offline',
            ]);
        case 'discord':
            return 'https://discord.com/api/oauth2/authorize?' . http_build_query([
                'client_id' => DISCORD_CLIENT_ID,
                'redirect_uri' => DISCORD_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => 'identify email',
                'state' => $state,
            ]);
        case 'twitch':
            return 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
                'client_id' => TWITCH_CLIENT_ID,
                'redirect_uri' => TWITCH_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => 'user:read:email',
                'state' => $state,
            ]);
        case 'reddit':
            return 'https://www.reddit.com/api/v1/authorize?' . http_build_query([
                'client_id' => REDDIT_CLIENT_ID,
                'redirect_uri' => REDDIT_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => 'identity',
                'state' => $state,
                'duration' => 'permanent',
            ]);
        case 'spotify':
            return 'https://accounts.spotify.com/authorize?' . http_build_query([
                'client_id' => SPOTIFY_CLIENT_ID,
                'redirect_uri' => SPOTIFY_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => 'user-read-private user-read-email',
                'state' => $state,
            ]);
        default:
            return null;
    }
}
function exchange_oauth_code($provider, $code) {
    switch ($provider) {
        case 'github':
            // Exchange code for token
            $token_data = http_post('https://github.com/login/oauth/access_token', [
                'client_id' => GITHUB_CLIENT_ID,
                'client_secret' => GITHUB_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => GITHUB_REDIRECT_URI,
            ], ['Accept: application/json']);
            $access_token = $token_data['access_token'] ?? null;
            if (!$access_token) throw new Exception('GitHub Token-Fehler');
            // Get user info
            $user = http_get('https://api.github.com/user', ['Authorization: Bearer ' . $access_token, 'User-Agent: MZDEV']);
            $emails = http_get('https://api.github.com/user/emails', ['Authorization: Bearer ' . $access_token, 'User-Agent: MZDEV']);
            $primary_email = '';
            foreach ($emails as $em) {
                if ($em['primary'] ?? false) { $primary_email = $em['email']; break; }
            }
            return [
                'id' => (string)$user['id'],
                'username' => $user['login'],
                'display_name' => $user['name'] ?? $user['login'],
                'email' => $primary_email ?: ($user['email'] ?? ''),
                'avatar' => $user['avatar_url'] ?? '',
                'bio' => $user['bio'] ?? '',
                'access_token' => $access_token,
            ];
        case 'google':
            $token_data = http_post('https://oauth2.googleapis.com/token', [
                'client_id' => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'grant_type' => 'authorization_code',
            ]);
            $access_token = $token_data['access_token'] ?? null;
            if (!$access_token) throw new Exception('Google Token-Fehler');
            $user = http_get('https://www.googleapis.com/oauth2/v2/userinfo', ['Authorization: Bearer ' . $access_token]);
            return [
                'id' => (string)$user['id'],
                'username' => explode('@', $user['email'] ?? 'user')[0],
                'display_name' => $user['name'] ?? '',
                'email' => $user['email'] ?? '',
                'avatar' => $user['picture'] ?? '',
                'bio' => '',
                'access_token' => $access_token,
                'refresh_token' => $token_data['refresh_token'] ?? null,
            ];
        case 'discord':
            $token_data = http_post('https://discord.com/api/v10/oauth2/token', http_build_query([
                'client_id' => DISCORD_CLIENT_ID,
                'client_secret' => DISCORD_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => DISCORD_REDIRECT_URI,
                'grant_type' => 'authorization_code',
            ]), ['Content-Type: application/x-www-form-urlencoded'], true);
            $access_token = $token_data['access_token'] ?? null;
            if (!$access_token) throw new Exception('Discord Token-Fehler');
            $user = http_get('https://discord.com/api/v10/users/@me', ['Authorization: Bearer ' . $access_token]);
            $avatar_url = $user['avatar'] ? "https://cdn.discordapp.com/avatars/{$user['id']}/{$user['avatar']}.png" : '';
            return [
                'id' => (string)$user['id'],
                'username' => $user['username'],
                'display_name' => $user['global_name'] ?? $user['username'],
                'email' => $user['email'] ?? '',
                'avatar' => $avatar_url,
                'bio' => '',
                'access_token' => $access_token,
                'refresh_token' => $token_data['refresh_token'] ?? null,
            ];
        case 'twitch':
            $token_data = http_post('https://id.twitch.tv/oauth2/token', [
                'client_id' => TWITCH_CLIENT_ID,
                'client_secret' => TWITCH_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => TWITCH_REDIRECT_URI,
                'grant_type' => 'authorization_code',
            ]);
            $access_token = $token_data['access_token'] ?? null;
            if (!$access_token) throw new Exception('Twitch Token-Fehler');
            $user = http_get('https://api.twitch.tv/helix/users', [
                'Authorization: Bearer ' . $access_token,
                'Client-Id: ' . TWITCH_CLIENT_ID,
            ]);
            $u = $user['data'][0] ?? [];
            return [
                'id' => (string)($u['id'] ?? ''),
                'username' => $u['login'] ?? '',
                'display_name' => $u['display_name'] ?? $u['login'] ?? '',
                'email' => $u['email'] ?? '',
                'avatar' => $u['profile_image_url'] ?? '',
                'bio' => $u['description'] ?? '',
                'access_token' => $access_token,
                'refresh_token' => $token_data['refresh_token'] ?? null,
            ];
        case 'reddit':
            $auth = base64_encode(REDDIT_CLIENT_ID . ':' . REDDIT_CLIENT_SECRET);
            $token_data = http_post('https://www.reddit.com/api/v1/access_token', http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => REDDIT_REDIRECT_URI,
            ]), ['Authorization: Basic ' . $auth, 'Content-Type: application/x-www-form-urlencoded', 'User-Agent: MZDEV/1.0'], true);
            $access_token = $token_data['access_token'] ?? null;
            if (!$access_token) throw new Exception('Reddit Token-Fehler');
            $user = http_get('https://oauth.reddit.com/api/v1/me', [
                'Authorization: Bearer ' . $access_token,
                'User-Agent: MZDEV/1.0',
            ]);
            return [
                'id' => (string)($user['id'] ?? ''),
                'username' => $user['name'] ?? '',
                'display_name' => $user['name'] ?? '',
                'email' => '',
                'avatar' => $user['icon_img'] ?? '',
                'bio' => $user['subreddit']['public_description'] ?? '',
                'access_token' => $access_token,
                'refresh_token' => $token_data['refresh_token'] ?? null,
            ];
        case 'spotify':
            $auth = base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);
            $token_data = http_post('https://accounts.spotify.com/api/token', http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => SPOTIFY_REDIRECT_URI,
            ]), ['Authorization: Basic ' . $auth, 'Content-Type: application/x-www-form-urlencoded'], true);
            $access_token = $token_data['access_token'] ?? null;
            if (!$access_token) throw new Exception('Spotify Token-Fehler');
            $user = http_get('https://api.spotify.com/v1/me', ['Authorization: Bearer ' . $access_token]);
            $avatar = '';
            if (!empty($user['images'])) $avatar = $user['images'][0]['url'] ?? '';
            return [
                'id' => (string)($user['id'] ?? ''),
                'username' => $user['id'] ?? '',
                'display_name' => $user['display_name'] ?? $user['id'] ?? '',
                'email' => $user['email'] ?? '',
                'avatar' => $avatar,
                'bio' => '',
                'access_token' => $access_token,
                'refresh_token' => $token_data['refresh_token'] ?? null,
            ];
        default:
            throw new Exception('Unbekannter Provider');
    }
}
function process_oauth_login($provider, $data) {
    $db = get_db();
    // Check if this provider account is already connected
    $stmt = $db->prepare('SELECT user_id FROM oauth_connections WHERE provider = ? AND provider_id = ?');
    $stmt->execute([$provider, $data['id']]);
    $existing = $stmt->fetch();
    if ($existing) {
        // Update connection
        $db->prepare('UPDATE oauth_connections SET access_token = ?, provider_username = ?, provider_avatar = ?, provider_email = ? WHERE provider = ? AND provider_id = ?')
            ->execute([$data['access_token'], $data['username'], $data['avatar'], $data['email'], $provider, $data['id']]);
        // Update user last_seen and avatar if not set
        $db->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$existing['user_id']]);
        $user = $db->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$existing['user_id']]);
        return $user->fetch();
    }
    // Check if we're connecting to an existing user (via state/session)
    $state = $_GET['state'] ?? 'login';
    if ($state === 'connect') {
        $current_user = get_current_user_from_session();
        if ($current_user) {
            // Connect this provider to existing user
            $db->prepare('INSERT INTO oauth_connections (user_id, provider, provider_id, provider_username, provider_email, provider_avatar, access_token, refresh_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$current_user['id'], $provider, $data['id'], $data['username'], $data['email'], $data['avatar'], $data['access_token'], $data['refresh_token'] ?? null]);
            // Create notification
            create_notification($current_user['id'], 'system', null, null, null, ucfirst($provider) . ' erfolgreich verbunden!');
            return $current_user;
        }
    }
    // New user — create account
    $username = $data['username'];
    // Ensure unique username
    $check = $db->prepare('SELECT id FROM users WHERE username = ?');
    $check->execute([$username]);
    if ($check->fetch()) {
        $username = $username . '_' . substr(md5($data['id']), 0, 4);
    }
    $db->prepare('INSERT INTO users (username, display_name, email, avatar, bio, provider, provider_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$username, $data['display_name'], $data['email'], $data['avatar'], $data['bio'], $provider, $data['id']]);
    $user_id = $db->lastInsertId();
    // Create OAuth connection
    $db->prepare('INSERT INTO oauth_connections (user_id, provider, provider_id, provider_username, provider_email, provider_avatar, access_token, refresh_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$user_id, $provider, $data['id'], $data['username'], $data['email'], $data['avatar'], $data['access_token'], $data['refresh_token'] ?? null]);
    // Welcome notification
    create_notification($user_id, 'system', null, null, null, 'Willkommen bei MZ.DEV! 🎉');
    $user = $db->prepare('SELECT * FROM users WHERE id = ?');
    $user->execute([$user_id]);
    return $user->fetch();
}
function create_session($user_id) {
    $db = get_db();
    $token = generate_token();
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    $db->prepare('INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$user_id, $token, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $expires]);
    // Clean up old sessions
    $db->exec('DELETE FROM sessions WHERE expires_at < NOW()');
    // Set cookie
    setcookie(SESSION_COOKIE, $token, [
        'expires' => time() + SESSION_LIFETIME,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    return $token;
}
function create_notification($user_id, $type, $from_user_id, $ref_id, $ref_type, $message) {
    $db = get_db();
    $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_id, reference_type, message) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$user_id, $type, $from_user_id, $ref_id, $ref_type, $message]);
}
function http_post($url, $data, $headers = [], $raw = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    if ($raw) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        if (!$raw && !in_array_check($headers, 'Content-Type')) {
            $headers[] = 'Content-Type: application/json';
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?: [];
}
function http_get($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?: [];
}
function in_array_check($headers, $needle) {
    foreach ($headers as $h) {
        if (stripos($h, $needle) === 0) return true;
    }
    return false;
}
