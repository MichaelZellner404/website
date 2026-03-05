<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Reactions (Likes/Dislikes)
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
// ── POST /api/reactions.php — Toggle reaction ──
if ($method === 'POST') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    $target = $body['target'] ?? 'comment'; // comment or article
    $type = $body['type'] ?? 'like';
    if ($target === 'comment') {
        $comment_id = (int)($body['comment_id'] ?? 0);
        if (!$comment_id) json_error('comment_id erforderlich');
        // Check comment exists
        $stmt = $db->prepare('SELECT id, user_id, article_id FROM comments WHERE id = ?');
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();
        if (!$comment) json_error('Kommentar nicht gefunden', 404);
        if (!in_array($type, ['like', 'dislike'])) json_error('Ungültiger Reaktionstyp');
        // Check existing reaction
        $stmt = $db->prepare('SELECT id, type FROM reactions WHERE comment_id = ? AND user_id = ?');
        $stmt->execute([$comment_id, $user['id']]);
        $existing = $stmt->fetch();
        if ($existing) {
            if ($existing['type'] === $type) {
                // Remove reaction (toggle off)
                $db->prepare('DELETE FROM reactions WHERE id = ?')->execute([$existing['id']]);
                $action = 'removed';
            } else {
                // Change reaction type
                $db->prepare('UPDATE reactions SET type = ? WHERE id = ?')->execute([$type, $existing['id']]);
                $action = 'changed';
            }
        } else {
            // Add new reaction
            $db->prepare('INSERT INTO reactions (comment_id, user_id, type) VALUES (?, ?, ?)')
                ->execute([$comment_id, $user['id'], $type]);
            $action = 'added';
            // Notify comment author (if not self)
            if ($comment['user_id'] != $user['id']) {
                $notif_type = $type === 'like' ? 'like' : 'dislike';
                $notif_msg = ($user['display_name'] ?: $user['username']) . ($type === 'like' ? ' hat deinen Kommentar geliked' : ' hat deinen Kommentar disliked');
                $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_id, reference_type, message) VALUES (?, ?, ?, ?, "comment", ?)')
                    ->execute([$comment['user_id'], $notif_type, $user['id'], $comment_id, $notif_msg]);
            }
        }
        // Get updated counts
        $stmt = $db->prepare('SELECT type, COUNT(*) as c FROM reactions WHERE comment_id = ? GROUP BY type');
        $stmt->execute([$comment_id]);
        $counts = ['like' => 0, 'dislike' => 0];
        while ($row = $stmt->fetch()) $counts[$row['type']] = (int)$row['c'];
        json_response(['action' => $action, 'likes' => $counts['like'], 'dislikes' => $counts['dislike']]);
    } elseif ($target === 'article') {
        $article_id = (int)($body['article_id'] ?? 0);
        if (!$article_id) json_error('article_id erforderlich');
        if (!in_array($type, ['like', 'dislike', 'heart', 'fire', 'mindblown'])) json_error('Ungültiger Reaktionstyp');
        // Check existing
        $stmt = $db->prepare('SELECT id FROM article_reactions WHERE article_id = ? AND user_id = ? AND type = ?');
        $stmt->execute([$article_id, $user['id'], $type]);
        $existing = $stmt->fetch();
        if ($existing) {
            $db->prepare('DELETE FROM article_reactions WHERE id = ?')->execute([$existing['id']]);
            $action = 'removed';
        } else {
            $db->prepare('INSERT INTO article_reactions (article_id, user_id, type) VALUES (?, ?, ?)')
                ->execute([$article_id, $user['id'], $type]);
            $action = 'added';
        }
        // Get updated counts
        $stmt = $db->prepare('SELECT type, COUNT(*) as c FROM article_reactions WHERE article_id = ? GROUP BY type');
        $stmt->execute([$article_id]);
        $counts = [];
        while ($row = $stmt->fetch()) $counts[$row['type']] = (int)$row['c'];
        json_response(['action' => $action, 'reactions' => $counts]);
    }
    json_error('Ungültiges Ziel');
}
json_error('Ungültige Anfrage');
