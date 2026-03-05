<?php
// ═══════════════════════════════════════════════════════
// MZDEV API — Database Setup
// Run this ONCE to create all tables
// ═══════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';
// Allow browser access for setup
header('Content-Type: text/html; charset=utf-8');
$db = get_db();
$queries = [
    // Users
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        display_name VARCHAR(200) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        password_hash VARCHAR(255) DEFAULT NULL,
        avatar VARCHAR(500) DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        website VARCHAR(500) DEFAULT NULL,
        location VARCHAR(200) DEFAULT NULL,
        provider VARCHAR(50) DEFAULT 'local',
        provider_id VARCHAR(255) DEFAULT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        is_public TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_provider (provider, provider_id),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Sessions
    "CREATE TABLE IF NOT EXISTS sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL UNIQUE,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // OAuth Connections (multiple providers per user)
    "CREATE TABLE IF NOT EXISTS oauth_connections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(50) NOT NULL,
        provider_id VARCHAR(255) NOT NULL,
        provider_username VARCHAR(200) DEFAULT NULL,
        provider_email VARCHAR(255) DEFAULT NULL,
        provider_avatar VARCHAR(500) DEFAULT NULL,
        access_token TEXT DEFAULT NULL,
        refresh_token TEXT DEFAULT NULL,
        token_expires_at DATETIME DEFAULT NULL,
        connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_provider_user (user_id, provider),
        UNIQUE KEY unique_provider_id (provider, provider_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Friends / Followers
    "CREATE TABLE IF NOT EXISTS friends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        friend_id INT NOT NULL,
        status ENUM('pending','accepted','declined','blocked') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_friendship (user_id, friend_id),
        INDEX idx_friend (friend_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Followers (separate from friends — one-way)
    "CREATE TABLE IF NOT EXISTS followers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        follower_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_follow (user_id, follower_id),
        INDEX idx_user (user_id),
        INDEX idx_follower (follower_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Notifications
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('comment','reply','like','dislike','friend_request','friend_accepted','follow','mention','admin','system') NOT NULL,
        from_user_id INT DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        reference_type VARCHAR(50) DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Articles
    "CREATE TABLE IF NOT EXISTS articles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(500) NOT NULL,
        slug VARCHAR(500) NOT NULL UNIQUE,
        excerpt TEXT DEFAULT NULL,
        content LONGTEXT NOT NULL,
        cover_image VARCHAR(500) DEFAULT NULL,
        category VARCHAR(100) DEFAULT 'allgemein',
        tags JSON DEFAULT NULL,
        author_id INT DEFAULT NULL,
        status ENUM('draft','published','archived') DEFAULT 'published',
        featured TINYINT(1) DEFAULT 0,
        views INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_category (category),
        INDEX idx_featured (featured),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Comments
    "CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        article_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        content TEXT NOT NULL,
        is_edited TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
        INDEX idx_article (article_id),
        INDEX idx_user (user_id),
        INDEX idx_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Reactions (likes/dislikes on comments)
    "CREATE TABLE IF NOT EXISTS reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        type ENUM('like','dislike') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reaction (comment_id, user_id),
        INDEX idx_comment (comment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Article Reactions (likes on articles themselves)
    "CREATE TABLE IF NOT EXISTS article_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        article_id INT NOT NULL,
        user_id INT NOT NULL,
        type ENUM('like','dislike','heart','fire','mindblown') NOT NULL DEFAULT 'like',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_article_reaction (article_id, user_id, type),
        INDEX idx_article (article_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // User Settings
    "CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_setting (user_id, setting_key),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Messages (encrypted chat)
    "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_user_id INT NOT NULL,
        to_user_id INT NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_conversation (from_user_id, to_user_id),
        INDEX idx_to_user (to_user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Activity Log
    "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        target_type VARCHAR(50) DEFAULT NULL,
        target_id INT DEFAULT NULL,
        metadata JSON DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Projects
    "CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(300) NOT NULL,
        slug VARCHAR(300) NOT NULL UNIQUE,
        description TEXT DEFAULT NULL,
        readme LONGTEXT DEFAULT NULL,
        category VARCHAR(100) DEFAULT 'Web App',
        technologies JSON DEFAULT NULL,
        status ENUM('active','wip','done','archived') DEFAULT 'active',
        github_url VARCHAR(500) DEFAULT NULL,
        live_url VARCHAR(500) DEFAULT NULL,
        cover_image VARCHAR(500) DEFAULT NULL,
        author_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Project Files
    "CREATE TABLE IF NOT EXISTS project_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        original_name VARCHAR(500) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size BIGINT DEFAULT 0,
        mime_type VARCHAR(200) DEFAULT NULL,
        extension VARCHAR(20) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        download_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Skills
    "CREATE TABLE IF NOT EXISTS skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(100) DEFAULT 'frontend',
        level INT DEFAULT 50,
        icon VARCHAR(500) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        tags JSON DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#f5e642',
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MZDEV Setup</title>';
echo '<style>body{background:#000;color:#fff;font-family:monospace;padding:2rem;}';
} catch (PDOException $e) {
    echo "<p class='err'>✗ Artikel-Fehler: {$e->getMessage()}</p>";
}
// Insert sample skills if none exist
try {
    $stmt = $db->query('SELECT COUNT(*) as c FROM skills');
    $count = $stmt->fetch()['c'];
    if ($count == 0) {
        $skills = [
            ['JavaScript', 'frontend', 85, 'JS', 'Vanilla JS, ES6+, DOM Manipulation', '["ES6","DOM","Async"]', '#f7df1e'],
            ['React', 'frontend', 70, 'Re', 'Hooks, Context, Router, State Management', '["Hooks","JSX","SPA"]', '#61dafb'],
            ['CSS/SCSS', 'frontend', 80, 'CS', 'Grid, Flexbox, Animationen, Custom Properties', '["Grid","Flexbox","Animation"]', '#264de4'],
            ['HTML5', 'frontend', 90, 'HT', 'Semantisches HTML, Accessibility, SEO', '["Semantic","A11y","SEO"]', '#e34c26'],
            ['Node.js', 'backend', 65, 'No', 'Express, REST APIs, Middleware', '["Express","REST","npm"]', '#339933'],
            ['PHP', 'backend', 75, 'PH', 'PDO, REST APIs, Sessions, OAuth', '["PDO","REST","OAuth"]', '#777bb4'],
            ['Python', 'backend', 60, 'Py', 'Flask, Django Basics, Scripting, AI/ML', '["Flask","Scripting","AI"]', '#3776ab'],
            ['SQL', 'backend', 70, 'SQ', 'MySQL, PostgreSQL, Joins, Indexing', '["MySQL","PostgreSQL","Joins"]', '#4479a1'],
            ['Git', 'tools', 80, 'Gi', 'Branches, Merges, CI/CD, GitHub Actions', '["GitHub","CI/CD","Branches"]', '#f05032'],
            ['Docker', 'tools', 55, 'Do', 'Dockerfiles, Compose, Images', '["Container","Compose","Images"]', '#2496ed'],
            ['Linux', 'tools', 65, 'Li', 'Bash, Server-Admin, SSH, Systemd', '["Bash","SSH","Server"]', '#fcc624'],
            ['OWASP', 'security', 60, 'OW', 'Top 10, Penetration Testing Basics, XSS, CSRF', '["Top10","XSS","CSRF"]', '#ff5f57'],
            ['Figma', 'design', 50, 'Fi', 'UI Design, Prototyping, Components', '["UI","Prototyping","Components"]', '#a259ff'],
            ['TypeScript', 'frontend', 55, 'TS', 'Types, Interfaces, Generics', '["Types","Interfaces","Generics"]', '#3178c6'],
        ];
        $stmt = $db->prepare('INSERT INTO skills (name, category, level, icon, description, tags, color, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($skills as $i => $s) {
            $s[] = $i;
            $stmt->execute($s);
        }
        echo "<p class='ok'>&#10003; <strong>" . count($skills) . " Beispiel-Skills</strong> eingefuegt</p>";
    } else {
        echo "<p class='warn'>&rarr; Skills existieren bereits ({$count} Stueck)</p>";
    }
} catch (PDOException $e) {
    echo "<p class='err'>&#10007; Skills-Fehler: {$e->getMessage()}</p>";
}
// Insert sample projects if none exist
try {
    $stmt = $db->query('SELECT COUNT(*) as c FROM projects');
    $count = $stmt->fetch()['c'];
    if ($count == 0) {
        $projects = [
            ['MZ.DEV Portfolio', 'mz-dev-portfolio', 'Meine persoenliche Portfolio- und Blog-Website mit Admin-Modus, OAuth und mehr.', 'Web App', '["HTML","CSS","JavaScript","PHP","MySQL"]', 'active', 'https://github.com/michaelzellner404/mz-dev', 'https://mz-dev.de'],
            ['SecScan CLI', 'secscan-cli', 'Ein einfaches CLI-Tool zum Scannen von Webseiten auf gaengige Sicherheitsluecken.', 'Security', '["Python","Click","Requests"]', 'wip', '', ''],
            ['TaskFlow', 'taskflow', 'Minimale Kanban-Board Web-App mit Drag-and-Drop und localStorage.', 'Web App', '["React","TypeScript","DnD"]', 'done', '', ''],
            ['API Gateway', 'api-gateway', 'Leichtgewichtiger API-Gateway/Proxy mit Rate-Limiting und Auth.', 'API', '["Node.js","Express","Redis"]', 'active', '', ''],
            ['DevNotes', 'devnotes', 'Markdown-basierte Notiz-App fuer Entwickler mit Syntax-Highlighting.', 'Tool', '["React","Marked","Prism"]', 'done', '', ''],
        ];
        $admin_id = $db->query("SELECT id FROM users WHERE is_admin = 1 LIMIT 1")->fetchColumn();
        $stmt = $db->prepare('INSERT INTO projects (name, slug, description, category, technologies, status, github_url, live_url, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($projects as $p) {
            $p[] = $admin_id ?: null;
            $stmt->execute($p);
        }
        echo "<p class='ok'>&#10003; <strong>" . count($projects) . " Beispiel-Projekte</strong> eingefuegt</p>";
    } else {
        echo "<p class='warn'>&rarr; Projekte existieren bereits ({$count} Stueck)</p>";
    }
} catch (PDOException $e) {
    echo "<p class='err'>&#10007; Projekte-Fehler: {$e->getMessage()}</p>";
}
echo "<hr><p>Fertig: <span class='ok'>{$success} erfolgreich</span>";
if ($errors > 0) echo ", <span class='err'>{$errors} Fehler</span>";
echo "</p>";
echo "<p class='warn'>⚠ Lösche diese Datei (setup.php) nach dem Setup vom Server!</p>";
echo "<p><a href='../index.html' style='color:#f5e642;'>← Zurück zur Website</a></p>";
echo '</body></html>';