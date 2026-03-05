<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — File Upload/Download/View for Projects
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
cors_headers();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
// ── GET /api/files.php?action=list&project_id=X — List files ──
if ($method === 'GET' && $action === 'list') {
    $project_id = (int)($_GET['project_id'] ?? 0);
    if (!$project_id) json_error('project_id erforderlich');
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM project_files WHERE project_id = ? ORDER BY sort_order ASC, created_at ASC');
    $stmt->execute([$project_id]);
    json_response($stmt->fetchAll());
}
// ── GET /api/files.php?action=get&id=X — Get single file info ──
if ($method === 'GET' && $action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('File-ID erforderlich');
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM project_files WHERE id = ?');
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) json_error('Datei nicht gefunden', 404);
    json_response($file);
}
// ── GET /api/files.php?action=download&id=X — Download file ──
if ($method === 'GET' && $action === 'download') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('File-ID erforderlich');
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM project_files WHERE id = ?');
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) json_error('Datei nicht gefunden', 404);
    $path = __DIR__ . '/../' . $file['file_path'];
    if (!file_exists($path)) json_error('Datei existiert nicht auf dem Server', 404);
    // Increment download count
    $db->prepare('UPDATE project_files SET download_count = download_count + 1 WHERE id = ?')->execute([$id]);
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
// ── GET /api/files.php?action=view&id=X — View file content (for viewer) ──
if ($method === 'GET' && $action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('File-ID erforderlich');
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM project_files WHERE id = ?');
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) json_error('Datei nicht gefunden', 404);
    $path = __DIR__ . '/../' . $file['file_path'];
    if (!file_exists($path)) json_error('Datei existiert nicht auf dem Server', 404);
    $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
    // Text-based files: return content directly
    $text_exts = ['txt','md','html','htm','css','js','ts','tsx','jsx','json','xml','csv','yaml','yml',
                  'py','php','rb','java','c','cpp','h','hpp','cs','go','rs','swift','kt','sql','sh','bash',
                  'ini','cfg','conf','env','log','gitignore','dockerfile','makefile','toml','lock'];
    if (in_array($ext, $text_exts)) {
        $content = file_get_contents($path);
        json_response([
            'type' => 'text',
            'extension' => $ext,
            'content' => $content,
            'mime_type' => $file['mime_type'],
            'name' => $file['original_name'],
        ]);
    }
    // PDF: return base64 or URL
    if ($ext === 'pdf') {
        json_response([
            'type' => 'pdf',
            'url' => SITE_URL . '/' . $file['file_path'],
            'name' => $file['original_name'],
        ]);
    }
    // Images
    $img_exts = ['png','jpg','jpeg','gif','svg','webp','ico','bmp'];
    if (in_array($ext, $img_exts)) {
        json_response([
            'type' => 'image',
            'url' => SITE_URL . '/' . $file['file_path'],
            'name' => $file['original_name'],
        ]);
    }
    // DOCX, XLSX — provide download URL and try Google Docs viewer
    if (in_array($ext, ['docx','doc','xlsx','xls','pptx','ppt','odt','ods'])) {
        json_response([
            'type' => 'office',
            'url' => SITE_URL . '/' . $file['file_path'],
            'viewer_url' => 'https://docs.google.com/gview?url=' . urlencode(SITE_URL . '/' . $file['file_path']) . '&embedded=true',
            'name' => $file['original_name'],
            'extension' => $ext,
        ]);
    }
    // Video
    $video_exts = ['mp4','webm','ogg','mov'];
    if (in_array($ext, $video_exts)) {
        json_response([
            'type' => 'video',
            'url' => SITE_URL . '/' . $file['file_path'],
            'name' => $file['original_name'],
        ]);
    }
    // Audio
    $audio_exts = ['mp3','wav','ogg','flac','aac'];
    if (in_array($ext, $audio_exts)) {
        json_response([
            'type' => 'audio',
            'url' => SITE_URL . '/' . $file['file_path'],
            'name' => $file['original_name'],
        ]);
    }
    // Fallback: binary/download
    json_response([
        'type' => 'binary',
        'url' => SITE_URL . '/' . $file['file_path'],
        'name' => $file['original_name'],
        'extension' => $ext,
    ]);
}
// ── POST /api/files.php — Upload file (admin) ──
if ($method === 'POST') {
    $admin = require_admin();
    $db = get_db();
    $project_id = (int)($_POST['project_id'] ?? 0);
    if (!$project_id) json_error('project_id erforderlich');
    // Verify project exists
    $stmt = $db->prepare('SELECT id, name FROM projects WHERE id = ?');
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    if (!$project) json_error('Projekt nicht gefunden', 404);
    if (empty($_FILES['file'])) json_error('Keine Datei hochgeladen');
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) json_error('Upload-Fehler: ' . $file['error']);
    // Max 50MB
    if ($file['size'] > 50 * 1024 * 1024) json_error('Datei zu gross (max. 50 MB)');
    $original_name = basename($file['name']);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
    $upload_dir = __DIR__ . '/../uploads/projects/' . $project_id;
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $dest = $upload_dir . '/' . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) json_error('Fehler beim Speichern der Datei');
    $file_path = 'uploads/projects/' . $project_id . '/' . $safe_name;
    $description = $_POST['description'] ?? '';
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $db->prepare('INSERT INTO project_files (project_id, original_name, file_path, file_size, mime_type, extension, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$project_id, $original_name, $file_path, $file['size'], $file['type'], $ext, $description, $sort_order]);
    $file_id = $db->lastInsertId();
    // Notify all users about upload
    $users = $db->query('SELECT id FROM users WHERE id != ' . (int)$admin['id'])->fetchAll(PDO::FETCH_COLUMN);
    $notif_stmt = $db->prepare('INSERT INTO notifications (user_id, type, from_user_id, reference_id, reference_type, message) VALUES (?, "admin", ?, ?, "project_file", ?)');
    foreach ($users as $uid) {
        $notif_stmt->execute([$uid, $admin['id'], $file_id, 'Neue Datei in "' . $project['name'] . '": ' . $original_name]);
    }
    json_response(['success' => true, 'id' => $file_id, 'file_path' => $file_path], 201);
}
// ── DELETE /api/files.php?id=X — Delete file (admin) ──
if ($method === 'DELETE') {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { $body = get_json_body(); $id = (int)($body['id'] ?? 0); }
    if (!$id) json_error('File-ID erforderlich');
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM project_files WHERE id = ?');
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) json_error('Datei nicht gefunden', 404);
    $path = __DIR__ . '/../' . $file['file_path'];
    if (file_exists($path)) unlink($path);
    $db->prepare('DELETE FROM project_files WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}
json_error('Ungueltige Anfrage');