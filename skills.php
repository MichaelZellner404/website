
<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Skills (CRUD, Admin)
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/skills.php — List all skills ──
if ($method === 'GET' && (!$action || $action === 'list')) {
    $db = get_db();
    $category = $_GET['category'] ?? '';
    $where = '1=1';
    $params = [];
    if ($category && $category !== 'alle') {
        $where .= ' AND s.category = ?';
        $params[] = $category;
    }
    $stmt = $db->prepare("SELECT s.* FROM skills s WHERE {$where} ORDER BY s.sort_order ASC, s.name ASC");
    $stmt->execute($params);
    $skills = $stmt->fetchAll();
    foreach ($skills as &$sk) {
        $sk['tags'] = json_decode($sk['tags'] ?? '[]', true) ?: [];
    }
    json_response(['skills' => $skills]);
}
// ── GET /api/skills.php?action=get&id=X ──
if ($method === 'GET' && $action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('Skill-ID erforderlich');
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM skills WHERE id = ?');
    $stmt->execute([$id]);
    $skill = $stmt->fetch();
    if (!$skill) json_error('Skill nicht gefunden', 404);
    $skill['tags'] = json_decode($skill['tags'] ?? '[]', true) ?: [];
    json_response($skill);
}
// ── POST /api/skills.php — Create skill (admin) ──
if ($method === 'POST') {
    $admin = require_admin();
    $body = get_json_body();
    $db = get_db();
    $name = trim($body['name'] ?? '');
    $category = trim($body['category'] ?? 'frontend');
    $level = (int)($body['level'] ?? 50);
    $icon = trim($body['icon'] ?? '');
    $description = trim($body['description'] ?? '');
    $tags = $body['tags'] ?? [];
    $color = trim($body['color'] ?? '#f5e642');
    $sort_order = (int)($body['sort_order'] ?? 0);
    if (!$name) json_error('Skill-Name ist Pflicht');
    $db->prepare('INSERT INTO skills (name, category, level, icon, description, tags, color, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $category, $level, $icon, $description, json_encode($tags), $color, $sort_order]);
    json_response(['success' => true, 'id' => $db->lastInsertId()], 201);
}
// ── PUT /api/skills.php?id=X — Update skill (admin) ──
if ($method === 'PUT') {
    $admin = require_admin();
    $body = get_json_body();
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) json_error('Skill-ID erforderlich');
    $db = get_db();
    $updates = [];
    $params = [];
    $allowed = ['name', 'category', 'level', 'icon', 'description', 'color', 'sort_order'];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            $updates[] = "$field = ?";
            $params[] = $body[$field];
        }
    }
    if (isset($body['tags'])) {
        $updates[] = "tags = ?";
        $params[] = json_encode($body['tags']);
    }
    if (empty($updates)) json_error('Keine Aenderungen');
    $params[] = $id;
    $db->prepare('UPDATE skills SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    json_response(['success' => true]);
}
// ── DELETE /api/skills.php?id=X — Delete skill (admin) ──
if ($method === 'DELETE') {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { $body = get_json_body(); $id = (int)($body['id'] ?? 0); }
    if (!$id) json_error('Skill-ID erforderlich');
    $db = get_db();
    $db->prepare('DELETE FROM skills WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}
json_error('Ungueltige Anfrage');