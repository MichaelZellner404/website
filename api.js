// ═══════════════════════════════════════════════════════
// MZDEV API — Frontend Integration Layer
// Handles communication with PHP backend
// ═══════════════════════════════════════════════════════
const API = {
    baseUrl: '/api',
    _token: localStorage.getItem('mzdev_api_token') || null,
    get token() { return this._token || localStorage.getItem('mzdev_api_token'); },
    set token(t) { this._token = t; if (t) localStorage.setItem('mzdev_api_token', t); else localStorage.removeItem('mzdev_api_token'); },
    // ── Generic fetch wrapper ──
    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
        if (this.token) headers['X-Session-Token'] = this.token;
        try {
            const resp = await fetch(url, { ...options, headers });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'API-Fehler');
            return data;
        } catch (e) {
            if (e.message === 'Failed to fetch') {
                console.warn('API nicht erreichbar — Offline-Modus');
                return null;
            }
            throw e;
        }
    },
    async get(endpoint) { return this.request(endpoint, { method: 'GET' }); },
    async post(endpoint, body) { return this.request(endpoint, { method: 'POST', body: JSON.stringify(body) }); },
    async put(endpoint, body) { return this.request(endpoint, { method: 'PUT', body: JSON.stringify(body) }); },
    async del(endpoint, body) { return this.request(endpoint, { method: 'DELETE', body: body ? JSON.stringify(body) : undefined }); },
    // ═══════════════════════════════════════════════════
    // AUTH
    // ═══════════════════════════════════════════════════
    // Check current session with backend
    async checkSession() {
        try {
            const data = await this.post('/auth.php', { action: 'check' });
            if (data && data.authenticated && data.user) {
                // Sync to localStorage for MZDEV shared state
                const user = data.user;
                MZDEV.setSession({
                    id: user.id,
                    username: user.username,
                    displayName: user.display_name,
                    avatar: user.avatar,
                    bio: user.bio,
                    email: user.email,
                    provider: user.provider,
                    connectedServices: (user.connected_services || []).map(s => s.provider),
                    online: true,
                    website: user.website,
                    location: user.location,
                    friends_count: user.friends_count,
                    followers_count: user.followers_count,
                    following_count: user.following_count,
                    unread_notifications: user.unread_notifications,
                    pending_friend_requests: user.pending_friend_requests
                }, !!user.is_admin);
                return data.user;
            }
            return null;
        } catch (e) {
            console.warn('Session-Check fehlgeschlagen:', e.message);
            return null;
        }
    },
    // Admin login via backend
    async adminLogin(password) {
        const data = await this.post('/auth.php', { action: 'admin_login', password });
        if (data && data.token) {
            this.token = data.token;
            await this.checkSession();
            return data.user;
        }
        throw new Error('Login fehlgeschlagen');
    },
    // Logout via backend
    async logout() {
        try {
            await this.post('/auth.php', { action: 'logout' });
        } catch (e) { /* ignore */ }
        this.token = null;
        MZDEV.clearSession();
    },
    // Start OAuth login — redirect to provider
    oauthLogin(provider) {
        window.location.href = this.baseUrl + '/auth.php?provider=' + encodeURIComponent(provider);
    },
    // Connect additional OAuth provider to existing account
    oauthConnect(provider) {
        window.location.href = this.baseUrl + '/auth.php?provider=' + encodeURIComponent(provider) + '&action=connect&state=connect';
    },
    // Handle auth_token from OAuth callback (called on page load)
    async handleAuthCallback() {
        const params = new URLSearchParams(window.location.search);
        const token = params.get('auth_token');
        const provider = params.get('provider');
        const oauthError = params.get('oauth_error');
        if (oauthError) {
            MZDEV.showToast('OAuth-Fehler: ' + oauthError, 'error');
            // Clean URL
            window.history.replaceState({}, '', window.location.pathname);
            return false;
        }
        if (token) {
            this.token = token;
            const user = await this.checkSession();
            // Clean URL
            window.history.replaceState({}, '', window.location.pathname);
            if (user) {
                MZDEV.showToast('Willkommen' + (provider ? ' via ' + provider.charAt(0).toUpperCase() + provider.slice(1) : '') + '!', 'success');
                return true;
            }
        }
        return false;
    },
    // ═══════════════════════════════════════════════════
    // ARTICLES
    // ═══════════════════════════════════════════════════
    async getArticles(params = {}) {
        const qs = new URLSearchParams(params).toString();
        return this.get('/articles.php' + (qs ? '?' + qs : ''));
    },
    async getArticle(idOrSlug) {
        const param = typeof idOrSlug === 'number' ? 'id=' + idOrSlug : 'slug=' + encodeURIComponent(idOrSlug);
        return this.get('/articles.php?action=get&' + param);
    },
    async createArticle(article) { return this.post('/articles.php', article); },
    async updateArticle(id, data) { return this.put('/articles.php?id=' + id, data); },
    async deleteArticle(id) { return this.del('/articles.php?id=' + id); },
    // ═══════════════════════════════════════════════════
    // COMMENTS
    // ═══════════════════════════════════════════════════
    async getComments(articleId) { return this.get('/comments.php?action=list&article_id=' + articleId); },
    async createComment(articleId, content, parentId = null) {
        return this.post('/comments.php', { article_id: articleId, content, parent_id: parentId });
    },
    async editComment(id, content) { return this.put('/comments.php?id=' + id, { content }); },
    async deleteComment(id) { return this.del('/comments.php?id=' + id); },
    // ═══════════════════════════════════════════════════
    // REACTIONS
    // ═══════════════════════════════════════════════════
    async reactComment(commentId, type) {
        return this.post('/reactions.php', { target: 'comment', comment_id: commentId, type });
    },
    async reactArticle(articleId, type) {
        return this.post('/reactions.php', { target: 'article', article_id: articleId, type });
    },
    // ═══════════════════════════════════════════════════
    // USERS / PROFILES
    // ═══════════════════════════════════════════════════
    async getProfile(idOrUsername) {
        const param = typeof idOrUsername === 'number' ? 'id=' + idOrUsername : 'username=' + encodeURIComponent(idOrUsername);
        return this.get('/users.php?action=profile&' + param);
    },
    async updateProfile(data) { return this.put('/users.php', data); },
    async searchUsers(q) { return this.get('/users.php?action=search&q=' + encodeURIComponent(q)); },
    // ═══════════════════════════════════════════════════
    // FRIENDS & FOLLOWERS
    // ═══════════════════════════════════════════════════
    async getFriends(status = 'accepted') { return this.get('/friends.php?action=list&status=' + status); },
    async getFriendRequests() { return this.get('/friends.php?action=requests'); },
    async sendFriendRequest(friendId) { return this.post('/friends.php', { action: 'send', friend_id: friendId }); },
    async acceptFriend(friendshipId) { return this.post('/friends.php', { action: 'accept', friendship_id: friendshipId }); },
    async declineFriend(friendshipId) { return this.post('/friends.php', { action: 'decline', friendship_id: friendshipId }); },
    async removeFriend(friendId) { return this.post('/friends.php', { action: 'remove', friend_id: friendId }); },
    async blockUser(friendId) { return this.post('/friends.php', { action: 'block', friend_id: friendId }); },
    async follow(userId) { return this.post('/friends.php', { action: 'follow', user_id: userId }); },
    async unfollow(userId) { return this.post('/friends.php', { action: 'unfollow', user_id: userId }); },
    // ═══════════════════════════════════════════════════
    // MESSAGES
    // ═══════════════════════════════════════════════════
    async getConversations() { return this.get('/messages.php?action=conversations'); },
    async getChat(userId, page = 1) { return this.get('/messages.php?action=chat&with=' + userId + '&page=' + page); },
    async sendMessage(toUserId, content) { return this.post('/messages.php', { to_user_id: toUserId, content }); },
    async getUnreadCount() { return this.get('/messages.php?action=unread_count'); },
    // ═══════════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════════
    async getNotifications(page = 1, unreadOnly = false) {
        return this.get('/notifications.php?page=' + page + (unreadOnly ? '&unread=1' : ''));
    },
    async getNotifCount() { return this.get('/notifications.php?action=count'); },
    async markNotifRead(id) { return this.put('/notifications.php', { action: 'read', id }); },
    async markAllNotifsRead() { return this.put('/notifications.php', { action: 'read_all' }); },
    async deleteNotification(id) { return this.del('/notifications.php', { id }); },
    async clearAllNotifs() { return this.del('/notifications.php', { action: 'clear_all' }); },
    // ═══════════════════════════════════════════════════
    // SETTINGS
    // ═══════════════════════════════════════════════════
    async getSettings() { return this.get('/settings.php'); },
    async updateSettings(settings) { return this.put('/settings.php', settings); },
    async resetSettings() { return this.del('/settings.php', { action: 'reset_all' }); },
    async deleteAccount() { return this.del('/settings.php', { action: 'delete_account' }); },
    // ═══════════════════════════════════════════════════
    // PROJECTS
    // ═══════════════════════════════════════════════════
    async getProjects(params = {}) {
        const qs = new URLSearchParams(params).toString();
        return this.get('/projects.php' + (qs ? '?' + qs : ''));
    },
    async getProject(id) { return this.get('/projects.php?action=get&id=' + id); },
    async createProject(data) { return this.post('/projects.php', data); },
    async updateProject(id, data) { return this.put('/projects.php?id=' + id, data); },
    async deleteProject(id) { return this.del('/projects.php?id=' + id); },
    // ═══════════════════════════════════════════════════
    // PROJECT FILES
    // ═══════════════════════════════════════════════════
    async getProjectFiles(projectId) { return this.get('/files.php?action=list&project_id=' + projectId); },
    async getFileInfo(id) { return this.get('/files.php?action=get&id=' + id); },
    async viewFile(id) { return this.get('/files.php?action=view&id=' + id); },
    getFileDownloadUrl(id) { return this.baseUrl + '/files.php?action=download&id=' + id; },
    async uploadFile(projectId, file, description = '') {
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('file', file);
        if (description) formData.append('description', description);
        const url = this.baseUrl + '/files.php';
        const headers = {};
        if (this.token) headers['X-Session-Token'] = this.token;
        const resp = await fetch(url, { method: 'POST', headers, body: formData });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Upload-Fehler');
        return data;
    },
    async deleteFile(id) { return this.del('/files.php?id=' + id); },
    // ═══════════════════════════════════════════════════
    // SKILLS
    // ═══════════════════════════════════════════════════
    async getSkills(params = {}) {
        const qs = new URLSearchParams(params).toString();
        return this.get('/skills.php' + (qs ? '?' + qs : ''));
    },
    async getSkill(id) { return this.get('/skills.php?action=get&id=' + id); },
    async createSkill(data) { return this.post('/skills.php', data); },
    async updateSkill(id, data) { return this.put('/skills.php?id=' + id, data); },
    async deleteSkill(id) { return this.del('/skills.php?id=' + id); },
    // ═══════════════════════════════════════════════════
    // OAUTH SERVICE MANAGEMENT
    // ═══════════════════════════════════════════════════
    async getConnectedServices() { return this.get('/oauth.php?action=services'); },
    async disconnectService(provider) { return this.post('/oauth.php?action=disconnect', { provider }); },
};
// ═══════════════════════════════════════════════════════