<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Configuration
// ═══════════════════════════════════════════════════════
// Database
define('DB_HOST', 'mzdevdfadmin404.mysql.db');
define('DB_NAME', 'mzdevdfadmin404');
define('DB_USER', 'mzdevdfadmin404');
define('DB_PASS', ''); // <-- Dein MySQL-Passwort hier eintragen!
// Site
define('SITE_URL', 'https://mz-dev.de');
define('API_URL', SITE_URL . '/api');
// OAuth — Client IDs & Secrets hier eintragen
// GitHub: https://github.com/settings/developers
define('GITHUB_CLIENT_ID', '');
define('GITHUB_CLIENT_SECRET', '');
define('GITHUB_REDIRECT_URI', API_URL . '/auth.php?provider=github&action=callback');
// Google: https://console.cloud.google.com/
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', API_URL . '/auth.php?provider=google&action=callback');
// Discord: https://discord.com/developers/applications
define('DISCORD_CLIENT_ID', '');
define('DISCORD_CLIENT_SECRET', '');
define('DISCORD_REDIRECT_URI', API_URL . '/auth.php?provider=discord&action=callback');
// Twitch: https://dev.twitch.tv/console/apps
define('TWITCH_CLIENT_ID', '');
define('TWITCH_CLIENT_SECRET', '');
define('TWITCH_REDIRECT_URI', API_URL . '/auth.php?provider=twitch&action=callback');
// Reddit: https://www.reddit.com/prefs/apps
define('REDDIT_CLIENT_ID', '');
define('REDDIT_CLIENT_SECRET', '');
define('REDDIT_REDIRECT_URI', API_URL . '/auth.php?provider=reddit&action=callback');
// Spotify: https://developer.spotify.com/dashboard
define('SPOTIFY_CLIENT_ID', '');
define('SPOTIFY_CLIENT_SECRET', '');
define('SPOTIFY_REDIRECT_URI', API_URL . '/auth.php?provider=spotify&action=callback');
// Admin password for initial admin login
define('ADMIN_PASSWORD', '404AdminFound');
// Session
define('SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 days
define('SESSION_COOKIE', 'mzdev_session');
// CORS headers
function cors_headers() {
    header('Access-Control-Allow-Origin: ' . SITE_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
// Database connection
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}
// JSON response helper
function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_error($message, $code = 400) {
    json_response(['error' => $message], $code);
}
// Get JSON body
function get_json_body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return $data ?: [];
}
// Generate secure token
function generate_token($length = 64) {
    return bin2hex(random_bytes($length / 2));
}
// Get current session user
function get_current_user_from_session() {
    $token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE[SESSION_COOKIE] ?? null;
    if (!$token) return null;
    $db = get_db();
    $stmt = $db->prepare('
        SELECT u.* FROM users u
        JOIN sessions s ON s.user_id = u.id
        WHERE s.token = ? AND s.expires_at > NOW()
    ');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}
// Require authentication
function require_auth() {
    $user = get_current_user_from_session();
    if (!$user) {
        json_error('Nicht angemeldet', 401);
    }
    return $user;
}
// Require admin
function require_admin() {
    $user = require_auth();
    if (!$user['is_admin']) {
        json_error('Keine Admin-Berechtigung', 403);
    }
    return $user;
}
