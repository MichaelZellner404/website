// ═══════════════════════════════════════════════════════
// MZDEV SHARED STATE — localStorage persistence across pages
// ═══════════════════════════════════════════════════════
const MZDEV = {
    // ── THEME ──
    get theme() { return JSON.parse(localStorage.getItem('mzdev_theme') || JSON.stringify({ accent: '#f5e642', bg: '#000000', surface: '#111111' })); },
    saveTheme(t) { localStorage.setItem('mzdev_theme', JSON.stringify(t)); MZDEV.applyTheme(t); },
    applyTheme(t) {
        t = t || MZDEV.theme;
        document.documentElement.style.setProperty('--yellow', t.accent);
        document.documentElement.style.setProperty('--black', t.bg);
        document.documentElement.style.setProperty('--gray-dark', t.surface);
    },
    // ── SESSION ──
    get currentUser() { return JSON.parse(localStorage.getItem('mzdev_current_user') || 'null'); },
    get isAdmin() { return localStorage.getItem('mzdev_is_admin') === '1'; },
    setSession(user, isAdmin) {
        localStorage.setItem('mzdev_current_user', JSON.stringify(user));
        localStorage.setItem('mzdev_is_admin', isAdmin ? '1' : '0');
    },
    clearSession() {
        localStorage.removeItem('mzdev_current_user');
        localStorage.removeItem('mzdev_is_admin');
    },
    // ── REACTIONS ──
    get reactions() { return JSON.parse(localStorage.getItem('mzdev_reactions') || '{}'); },
    saveReactions(r) { localStorage.setItem('mzdev_reactions', JSON.stringify(r)); },
    // ── COMMENTS ──
    get comments() { return JSON.parse(localStorage.getItem('mzdev_comments') || '{}'); },
    saveComments(c) { localStorage.setItem('mzdev_comments', JSON.stringify(c)); },
    // ── EDITED ARTICLES ──
    get editedArticles() { return JSON.parse(localStorage.getItem('mzdev_edited_articles') || '{}'); },
    saveEditedArticles(e) { localStorage.setItem('mzdev_edited_articles', JSON.stringify(e)); },
    // ── MESSAGES (XOR encrypted) ──
    get messages() { return JSON.parse(localStorage.getItem('mzdev_messages') || '{}'); },
    saveMessages(m) { localStorage.setItem('mzdev_messages', JSON.stringify(m)); },
    xorEncrypt(text, key = 'mzdev2026') {
        return btoa(Array.from(text).map((c, i) =>
            String.fromCharCode(c.charCodeAt(0) ^ key.charCodeAt(i % key.length))
        ).join(''));
    },
    xorDecrypt(b64, key = 'mzdev2026') {
        try {
            const text = atob(b64);
            return Array.from(text).map((c, i) =>
                String.fromCharCode(c.charCodeAt(0) ^ key.charCodeAt(i % key.length))
            ).join('');
        } catch { return b64; }
    },
    // ── ARTICLE STORAGE ──
    get storedArticles() { return JSON.parse(localStorage.getItem('mzdev_articles_extra') || '[]'); },
    saveArticles(a) { localStorage.setItem('mzdev_articles_extra', JSON.stringify(a)); },
    // ── SETTINGS ──
    get settings() { return JSON.parse(localStorage.getItem('mzdev_settings') || JSON.stringify({ animations: true, notifications: true, newArticleNotif: false, publicProfile: true, fontSize: 'normal' })); },
    saveSettings(s) { localStorage.setItem('mzdev_settings', JSON.stringify(s)); },
    // ── PROFILES ──
    get profiles() { return JSON.parse(localStorage.getItem('mzdev_profiles') || '{}'); },
    getProfile(username) { return MZDEV.profiles[username] || null; },
    saveProfile(username, data) {
        const p = MZDEV.profiles;
        p[username] = { ...p[username], ...data };
        localStorage.setItem('mzdev_profiles', JSON.stringify(p));
    },
    // ── FRIENDS ──
    get friends() { return JSON.parse(localStorage.getItem('mzdev_friends') || '{}'); },
    saveFriends(f) { localStorage.setItem('mzdev_friends', JSON.stringify(f)); },
    // ── PROJECTS ──
    get projects() { return JSON.parse(localStorage.getItem('mzdev_projects') || '[]'); },
    saveProjects(p) { localStorage.setItem('mzdev_projects', JSON.stringify(p)); },
    // ── ANNOUNCEMENTS ──
    get announcements() { return JSON.parse(localStorage.getItem('mzdev_announcements') || '[]'); },
    saveAnnouncements(a) { localStorage.setItem('mzdev_announcements', JSON.stringify(a)); },
    // ── COMMUNITIES ──
    get communities() { return JSON.parse(localStorage.getItem('mzdev_communities') || '[]'); },
    saveCommunities(c) { localStorage.setItem('mzdev_communities', JSON.stringify(c)); },
    // ── CONNECTED SERVICES ──
    getConnectedServices(username) {
        const p = MZDEV.getProfile(username);
        return p ? (p.connectedServices || []) : [];
    },
    addConnectedService(username, service) {
        const p = MZDEV.getProfile(username) || {};
        const services = p.connectedServices || [];
        if (!services.includes(service)) services.push(service);
        MZDEV.saveProfile(username, { connectedServices: services });
    },
    // ── TOAST ──
    showToast(msg, type = '') {
        let t = document.getElementById('mzdev-toast');
        if (!t) { t = document.createElement('div'); t.id = 'mzdev-toast'; document.body.appendChild(t); }
        t.textContent = msg;
        t.className = 'toast' + (type ? ' ' + type : '');
        t.classList.add('show');
        clearTimeout(MZDEV._toastTimer);
        MZDEV._toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
    },
    // ── ADMIN MODE ──
    activateAdminMode() {
        document.body.classList.add('admin-mode');
    },
    deactivateAdminMode() {
        document.body.classList.remove('admin-mode');
    },
    // ── CONTENT FILTER (basic) ──
    filterContent(text) {
        const badWords = ['porn','sex','xxx','nude','naked','fuck','shit','download this virus','malware'];
        const lower = text.toLowerCase();
        for (const w of badWords) { if (lower.includes(w)) return null; }
        // No download links
        if (/\.(exe|zip|rar|7z|msi|dmg|apk|bat|sh|ps1)\b/i.test(text)) return null;
        // Allow http links but strip download links
        return text;
    },
    // ── RANDOM NAME GENERATOR ──
    randomName() {
        const adj = ['Dark','Pixel','Neon','Ghost','Cyber','Binary','Void','Nova','Echo','Flux'];
        const noun = ['Coder','Dev','Hacker','Builder','Writer','Thinker','Maker','Craft','Fox','Wolf'];
        const num = Math.floor(Math.random() * 9000) + 1000;
        return adj[Math.floor(Math.random() * adj.length)] + noun[Math.floor(Math.random() * noun.length)] + num;
    },
    // ── NOTIFICATION BADGE ──
    updateNotificationBadge(count) {
        const badges = document.querySelectorAll('.notif-badge');
        badges.forEach(b => {
            if (count > 0) {
                b.textContent = count > 99 ? '99+' : count;
                b.style.display = 'flex';
            } else {
                b.style.display = 'none';
            }
        });
    },
    // ── INBOX BADGE ──
    updateInboxBadge(count) {
        const badges = document.querySelectorAll('.inbox-badge');
        badges.forEach(b => {
            if (count > 0) {
                b.textContent = count > 99 ? '99+' : count;
                b.style.display = 'flex';
            } else {
                b.style.display = 'none';
            }
        });
    },
    // ── FORMAT FILE SIZE ──
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },
    // ── FORMAT DATE ──
    formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
    formatDateTime(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    // ── FILE ICON ──
    fileIcon(ext) {
        const icons = {
            pdf: '\u{1F4C4}', doc: '\u{1F4DD}', docx: '\u{1F4DD}', xls: '\u{1F4CA}', xlsx: '\u{1F4CA}',
            ppt: '\u{1F4CA}', pptx: '\u{1F4CA}', txt: '\u{1F4C3}', md: '\u{1F4D6}', html: '\u{1F310}',
            css: '\u{1F3A8}', js: '\u{26A1}', ts: '\u{26A1}', py: '\u{1F40D}', php: '\u{1F418}',
            json: '\u{1F4CB}', xml: '\u{1F4CB}', sql: '\u{1F5C3}', sh: '\u{1F4DF}',
            png: '\u{1F5BC}', jpg: '\u{1F5BC}', jpeg: '\u{1F5BC}', gif: '\u{1F5BC}', svg: '\u{1F5BC}', webp: '\u{1F5BC}',
            mp4: '\u{1F3AC}', webm: '\u{1F3AC}', mp3: '\u{1F3B5}', wav: '\u{1F3B5}',
            zip: '\u{1F4E6}', rar: '\u{1F4E6}', '7z': '\u{1F4E6}', tar: '\u{1F4E6}', gz: '\u{1F4E6}',
        };
        return icons[ext] || '\u{1F4C1}';
    },
    // ── SYNTAX HIGHLIGHT CLASS ──
    codeLanguage(ext) {
        const map = {
            js: 'javascript', ts: 'typescript', py: 'python', rb: 'ruby', php: 'php',
            java: 'java', c: 'c', cpp: 'cpp', cs: 'csharp', go: 'go', rs: 'rust',
            swift: 'swift', kt: 'kotlin', sql: 'sql', sh: 'bash', bash: 'bash',
            html: 'html', css: 'css', json: 'json', xml: 'xml', yaml: 'yaml', yml: 'yaml',
            md: 'markdown', txt: 'plaintext', ini: 'ini', cfg: 'ini', env: 'bash',
            dockerfile: 'dockerfile', makefile: 'makefile', toml: 'toml',
        };
        return map[ext] || 'plaintext';
    }
};
// Auto-apply theme on load
document.addEventListener('DOMContentLoaded', () => MZDEV.applyTheme());