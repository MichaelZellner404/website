<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Comments
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── GET /api/comments.php?action=list&article_id=X — List comments for article ──
if ($method === 'GET' && $action === 'list') {
    $article_id = (int)($_GET['article_id'] ?? 0);
    if (!$article_id) json_error('article_id erforderlich');
    $db = get_db();
    $stmt = $db->prepare('
        SELECT c.*, u.username, u.display_name, u.avatar, u.is_admin,
            (SELECT COUNT(*) FROM reactions r WHERE r.comment_id = c.id AND r.type = "like") as likes,
            (SELECT COUNT(*) FROM reactions r WHERE r.comment_id = c.id AND r.type = "dislike") as dislikes
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.article_id = ?
        ORDER BY c.created_at ASC
    ');
    $stmt->execute([$article_id]);
    $comments = $stmt->fetchAll();
    // Get current user's reactions
    $current = get_current_user_from_session();
    $my_reactions = [];
    if ($current) {
        $stmt = $db->prepare('SELECT comment_id, type FROM reactions WHERE user_id = ? AND comment_id IN (SELECT id FROM comments WHERE article_id = ?)');
        $stmt->execute([$current['id'], $article_id]);
        while ($row = $stmt->fetch()) {
            $my_reactions[$row['comment_id']] = $row['type'];
        }
    }
    json_response(['comments' => $comments, 'my_reactions' => $my_reactions]);
}

// ── POST /api/comments.php — Create comment ──
if ($method === 'POST') {
    $user = require_auth();
    $body = get_json_body();
    $db = get_db();
    $article_id = (int)($body['article_id'] ?? 0);
    $content = trim($body['content'] ?? '');
    $parent_id = isset($body['parent_id']) ? (int)$body['parent_id'] : null;

    if (!$article_id) json_error('article_id erforderlich');
    if (!$content) json_error('Kommentar darf nicht leer sein');
    if (strlen($content) > 5000) json_error('Kommentar zu lang (max. 5000 Zeichen)');

    // Check article exists
    $stmt = $db->prepare('SELECT id, author_id, title FROM articles WHERE id = ?');
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();
    if (!$article) json_error('Artikel nicht gefunden', 404);

    // Check parent exists if reply
    if ($parent_id) {
        $stmt = $db->prepare('SELECT id, user_id FROM comments WHERE id = ? AND article_id = ?');
        $stmt->execute([$parent_id, $article_id]);
        $parent = $stmt->fetch();
        if (!$parent) json_error('Eltern-Kommentar nicht gefunden', 404);
    }

    $db->prepare('INSERT INTO comments (article_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)')
        ->execute([$article_id, $user['id'], $parent_id, $content]);
    $comment_id = $db->lastInsertId();

    // Notify article author (if not self)
    if ($article['author_id'] && $article['author_id'] != $user['id']) {
        $notif_msg = ($user['display_name'] ?: $user['username']) . ' hat "' . mb_substr($article['title'], 0, 40) . '" kommentiert';
        $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_id, reference_type, message) VALUES (?, "comment", ?, ?, "article", ?)')
            ->execute([$article['author_id'], $user['id'], $article_id, $notif_msg]);
    }

    // Notify parent comment author (if reply and not self)
    if ($parent_id && isset($parent) && $parent['user_id'] != $user['id']) {
        $notif_msg = ($user['display_name'] ?: $user['username']) . ' hat auf deinen Kommentar geantwortet';
        $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_id, reference_type, message) VALUES (?, "reply", ?, ?, "comment", ?)')
            ->execute([$parent['user_id'], $user['id'], $comment_id, $notif_msg]);
    }

    // Log activity
    $db->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id) VALUES (?, "comment_created", "comment", ?)')
        ->execute([$user['id'], $comment_id]);

    // Return the new comment with user info
    $stmt = $db->prepare('
        SELECT c.*, u.username, u.display_name, u.avatar, u.is_admin
        FROM comments c JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ');
    $stmt->execute([$comment_id]);
    json_response($stmt->fetch(), 201);
}

// ── PUT /api/comments.php?id=X — Edit comment ──
if ($method === 'PUT') {
    $user = require_auth();
    $body = get_json_body();
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) json_error('Kommentar-ID erforderlich');

    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM comments WHERE id = ?');
    $stmt->execute([$id]);
    $comment = $stmt->fetch();
    if (!$comment) json_error('Kommentar nicht gefunden', 404);

    // Only author or admin can edit
    if ($comment['user_id'] != $user['id'] && !$user['is_admin']) {
        json_error('Keine Berechtigung', 403);
    }

    $content = trim($body['content'] ?? '');
    if (!$content) json_error('Kommentar darf nicht leer sein');
    if (strlen($content) > 5000) json_error('Kommentar zu lang');

    $db->prepare('UPDATE comments SET content = ?, is_edited = 1 WHERE id = ?')
        ->execute([$content, $id]);
    json_response(['success' => true]);
}

// ── DELETE /api/comments.php?id=X — Delete comment ──
if ($method === 'DELETE') {
    $user = require_auth();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        $body = get_json_body();
        $id = (int)($body['id'] ?? 0);
    }
    if (!$id) json_error('Kommentar-ID erforderlich');

    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM comments WHERE id = ?');
    $stmt->execute([$id]);
    $comment = $stmt->fetch();
    if (!$comment) json_error('Kommentar nicht gefunden', 404);

    // Only author or admin can delete
    if ($comment['user_id'] != $user['id'] && !$user['is_admin']) {
        json_error('Keine Berechtigung', 403);
    }

    $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}

json_error('Ungueltige Anfrage');
