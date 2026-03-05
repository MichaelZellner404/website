<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Articles (CRUD + Admin)
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/articles.php — List articles ──
if ($method === 'GET' && (!$action || $action === 'list')) {
    $db = get_db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 20);
    $limit = min($limit, 100);
    $offset = ($page - 1) * $limit;
    $category = $_GET['category'] ?? '';
    $search = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? 'published';
    $featured = $_GET['featured'] ?? '';
    $where = ['1=1'];
    $params = [];
    // Non-admins can only see published
    $current = get_current_user_from_session();
    if (!$current || !$current['is_admin']) {
        $where[] = 'a.status = "published"';
    } elseif ($status) {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    if ($category) {
        $where[] = 'a.category = ?';
        $params[] = $category;
    }
    if ($search) {
        $where[] = '(a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($featured === '1') {
        $where[] = 'a.featured = 1';
    }
    $where_sql = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT a.id, a.title, a.slug, a.excerpt, a.category, a.tags, a.status, a.featured, a.views, a.created_at, a.updated_at, a.cover_image,
            u.username as author_username, u.display_name as author_name, u.avatar as author_avatar,
            (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) as comments_count,
            (SELECT COUNT(*) FROM article_reactions ar WHERE ar.article_id = a.id) as reactions_count
        FROM articles a
        LEFT JOIN users u ON a.author_id = u.id
        WHERE {$where_sql}
        ORDER BY a.featured DESC, a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $articles = $stmt->fetchAll();
    // Decode tags JSON
    foreach ($articles as &$a) {
        $a['tags'] = json_decode($a['tags'] ?? '[]', true) ?: [];
    }
    // Total count
    $count_params = array_slice($params, 0, -2);
    $count_stmt = $db->prepare("SELECT COUNT(*) as c FROM articles a WHERE {$where_sql}");
    $count_stmt->execute($count_params);
    $total = (int)$count_stmt->fetch()['c'];
    // Categories
    $cats = $db->query('SELECT DISTINCT category FROM articles WHERE status = "published" ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    json_response([
        'articles' => $articles,
        'total' => $total,
        'page' => $page,
        'categories' => $cats,
    ]);
}
// ── GET /api/articles.php?action=get&id=X or &slug=X ──
if ($method === 'GET' && $action === 'get') {
    $db = get_db();
    $id = (int)($_GET['id'] ?? 0);
    $slug = $_GET['slug'] ?? '';
    if ($id) {
        $stmt = $db->prepare('SELECT a.*, u.username as author_username, u.display_name as author_name, u.avatar as author_avatar FROM articles a LEFT JOIN users u ON a.author_id = u.id WHERE a.id = ?');
        $stmt->execute([$id]);
    } elseif ($slug) {
        $stmt = $db->prepare('SELECT a.*, u.username as author_username, u.display_name as author_name, u.avatar as author_avatar FROM articles a LEFT JOIN users u ON a.author_id = u.id WHERE a.slug = ?');
        $stmt->execute([$slug]);
    } else {
        json_error('ID oder Slug erforderlich');
    }
    $article = $stmt->fetch();
    if (!$article) json_error('Artikel nicht gefunden', 404);
    $article['tags'] = json_decode($article['tags'] ?? '[]', true) ?: [];
    // Increment views
    $db->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([$article['id']]);
    // Get comments
    $stmt = $db->prepare('
        SELECT c.*, u.username, u.display_name, u.avatar, u.is_admin,
            (SELECT COUNT(*) FROM reactions r WHERE r.comment_id = c.id AND r.type = "like") as likes,
            (SELECT COUNT(*) FROM reactions r WHERE r.comment_id = c.id AND r.type = "dislike") as dislikes
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.article_id = ?
        ORDER BY c.created_at ASC
    ');
    $stmt->execute([$article['id']]);
    $article['comments'] = $stmt->fetchAll();
    // Get current user's reactions on comments
    $current = get_current_user_from_session();
    if ($current) {
        $stmt = $db->prepare('SELECT comment_id, type FROM reactions WHERE user_id = ? AND comment_id IN (SELECT id FROM comments WHERE article_id = ?)');
        $stmt->execute([$current['id'], $article['id']]);
        $article['my_reactions'] = [];
        while ($row = $stmt->fetch()) {
            $article['my_reactions'][$row['comment_id']] = $row['type'];
        }
        // Article reactions
        $stmt = $db->prepare('SELECT type FROM article_reactions WHERE article_id = ? AND user_id = ?');
        $stmt->execute([$article['id'], $current['id']]);
        $article['my_article_reactions'] = array_column($stmt->fetchAll(), 'type');
    }
    // Article reaction counts
    $stmt = $db->prepare('SELECT type, COUNT(*) as c FROM article_reactions WHERE article_id = ? GROUP BY type');
    $stmt->execute([$article['id']]);
    $article['article_reactions'] = [];
    while ($row = $stmt->fetch()) {
        $article['article_reactions'][$row['type']] = (int)$row['c'];
    }
    json_response($article);
}
// ── POST /api/articles.php — Create article (admin) ──
if ($method === 'POST') {
    $admin = require_admin();
    $body = get_json_body();
    $db = get_db();
    $title = trim($body['title'] ?? '');
    $content = $body['content'] ?? '';
    $excerpt = trim($body['excerpt'] ?? '');
    $category = trim($body['category'] ?? 'allgemein');
    $tags = $body['tags'] ?? [];
    $status = $body['status'] ?? 'published';
    $featured = (int)($body['featured'] ?? 0);
    $cover_image = trim($body['cover_image'] ?? '');
    if (!$title) json_error('Titel ist Pflicht');
    if (!$content) json_error('Inhalt ist Pflicht');
    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
    $check = $db->prepare('SELECT id FROM articles WHERE slug = ?');
    $check->execute([$slug]);
    if ($check->fetch()) $slug .= '-' . time();
    $db->prepare('INSERT INTO articles (title, slug, excerpt, content, cover_image, category, tags, author_id, status, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$title, $slug, $excerpt, $content, $cover_image, $category, json_encode($tags), $admin['id'], $status, $featured]);
    $id = $db->lastInsertId();
    // Log
    $db->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id) VALUES (?, "article_created", "article", ?)')
        ->execute([$admin['id'], $id]);
    json_response(['success' => true, 'id' => $id, 'slug' => $slug], 201);
}
// ── PUT /api/articles.php?id=X — Update article (admin) ──
if ($method === 'PUT') {
    $admin = require_admin();
    $body = get_json_body();
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) json_error('Artikel-ID erforderlich');
    $db = get_db();
    $article = $db->prepare('SELECT * FROM articles WHERE id = ?');
    $article->execute([$id]);
    if (!$article->fetch()) json_error('Artikel nicht gefunden', 404);
    $updates = [];
    $params = [];
    $allowed = ['title', 'content', 'excerpt', 'category', 'status', 'featured', 'cover_image'];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            if ($field === 'tags') continue;
            $updates[] = "$field = ?";
            $params[] = $body[$field];
        }
    }
    if (isset($body['tags'])) {
        $updates[] = "tags = ?";
        $params[] = json_encode($body['tags']);
    }
    if (empty($updates)) json_error('Keine Änderungen');
    $params[] = $id;
    $db->prepare('UPDATE articles SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    // Log
    $db->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id) VALUES (?, "article_updated", "article", ?)')
        ->execute([$admin['id'], $id]);
    json_response(['success' => true]);
}
// ── DELETE /api/articles.php?id=X — Delete article (admin) ──
if ($method === 'DELETE') {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        $body = get_json_body();
        $id = (int)($body['id'] ?? 0);
    }
    if (!$id) json_error('Artikel-ID erforderlich');
    $db = get_db();
    $db->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}
json_error('Ungültige Anfrage');
