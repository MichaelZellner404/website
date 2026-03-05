<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Projects (CRUD + Files)
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/projects.php — List projects ──
if ($method === 'GET' && (!$action || $action === 'list')) {
    $db = get_db();
    $category = $_GET['category'] ?? '';
    $where = '1=1';
    $params = [];
    if ($category && $category !== 'alle') {
        $where .= ' AND p.category = ?';
        $params[] = $category;
    }
    $stmt = $db->prepare("
        SELECT p.*, u.username as author_username, u.display_name as author_name,
            (SELECT COUNT(*) FROM project_files pf WHERE pf.project_id = p.id) as files_count
        FROM projects p
        LEFT JOIN users u ON p.author_id = u.id
        WHERE {$where}
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    foreach ($projects as &$proj) {
        $proj['technologies'] = json_decode($proj['technologies'] ?? '[]', true) ?: [];
    }
    json_response(['projects' => $projects]);
}
// ── GET /api/projects.php?action=get&id=X ──
if ($method === 'GET' && $action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('Projekt-ID erforderlich');
    $db = get_db();
    $stmt = $db->prepare('SELECT p.*, u.username as author_username, u.display_name as author_name FROM projects p LEFT JOIN users u ON p.author_id = u.id WHERE p.id = ?');
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) json_error('Projekt nicht gefunden', 404);
    $project['technologies'] = json_decode($project['technologies'] ?? '[]', true) ?: [];
    // Get files
    $stmt = $db->prepare('SELECT * FROM project_files WHERE project_id = ? ORDER BY sort_order ASC, created_at ASC');
    $stmt->execute([$id]);
    $project['files'] = $stmt->fetchAll();
    json_response($project);
}
// ── POST /api/projects.php — Create project (admin) ──
if ($method === 'POST' && (!$action || $action === 'create')) {
    $admin = require_admin();
    $body = get_json_body();
    $db = get_db();
    $name = trim($body['name'] ?? '');
    $description = trim($body['description'] ?? '');
    $category = trim($body['category'] ?? 'Web App');
    $technologies = $body['technologies'] ?? [];
    $status = $body['status'] ?? 'active';
    $github_url = trim($body['github_url'] ?? '');
    $live_url = trim($body['live_url'] ?? '');
    $readme = $body['readme'] ?? '';
    if (!$name) json_error('Projektname ist Pflicht');
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    $check = $db->prepare('SELECT id FROM projects WHERE slug = ?');
    $check->execute([$slug]);
    if ($check->fetch()) $slug .= '-' . time();
    $db->prepare('INSERT INTO projects (name, slug, description, category, technologies, status, github_url, live_url, readme, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $slug, $description, $category, json_encode($technologies), $status, $github_url, $live_url, $readme, $admin['id']]);
    $id = $db->lastInsertId();
    // Notify all users about new project
    $users = $db->query('SELECT id FROM users WHERE id != ' . (int)$admin['id'])->fetchAll(PDO::FETCH_COLUMN);
    $notif_stmt = $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_id, reference_type, message) VALUES (?, "admin", ?, ?, "project", ?)');
    foreach ($users as $uid) {
        $notif_stmt->execute([$uid, $admin['id'], $id, 'Neues Projekt: ' . $name]);
    }
    json_response(['success' => true, 'id' => $id, 'slug' => $slug], 201);
}
// ── PUT /api/projects.php?id=X — Update project (admin) ──
if ($method === 'PUT') {
    $admin = require_admin();
    $body = get_json_body();
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) json_error('Projekt-ID erforderlich');
    $db = get_db();
    $updates = [];
    $params = [];
    $allowed = ['name', 'description', 'category', 'status', 'github_url', 'live_url', 'readme', 'cover_image'];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            $updates[] = "$field = ?";
            $params[] = $body[$field];
        }
    }
    if (isset($body['technologies'])) {
        $updates[] = "technologies = ?";
        $params[] = json_encode($body['technologies']);
    }
    if (empty($updates)) json_error('Keine Aenderungen');
    $params[] = $id;
    $db->prepare('UPDATE projects SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    json_response(['success' => true]);
}
// ── DELETE /api/projects.php?id=X — Delete project (admin) ──
if ($method === 'DELETE') {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { $body = get_json_body(); $id = (int)($body['id'] ?? 0); }
    if (!$id) json_error('Projekt-ID erforderlich');
    $db = get_db();
    // Delete files from disk
    $stmt = $db->prepare('SELECT file_path FROM project_files WHERE project_id = ?');
    $stmt->execute([$id]);
    while ($row = $stmt->fetch()) {
        $path = __DIR__ . '/../' . $row['file_path'];
        if (file_exists($path)) unlink($path);
    }
    $db->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}
json_error('Ungueltige Anfrage');