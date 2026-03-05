#!/usr/bin/env python3
import os, re
PAGES = os.path.expanduser("~/website/pages")
def readf(name):
    with open(os.path.join(PAGES, name), 'r', encoding='utf-8') as f:
        return f.read()
def writef(name, content):
    with open(os.path.join(PAGES, name), 'w', encoding='utf-8') as f:
        f.write(content)
SHARED_API_TAGS = '<script src="shared.js"></script>\n<script src="api.js"></script>'
AUTH_HANDLERS = """
// -- AUTH (API-based) --
function handleGithubLogin(){API.oauthLogin('github');}
function handleProviderLogin(provider){API.oauthLogin(provider);}
async function handleAdmin(){
    const pw=document.getElementById('admin-password').value;
    const errEl=document.getElementById('admin-error');
    errEl.textContent='';
    if(!pw){errEl.textContent='// Bitte Passwort eingeben';return;}
    try{await API.adminLogin(pw);closeModal('authModal');updateUI();MZDEV.showToast('Admin-Modus aktiv','success');}
    catch(e){errEl.textContent='// '+e.message;}
}
async function handleLogout(){
    try{await API.logout();}catch(e){}
    MZDEV.clearSession();document.body.classList.remove('admin-mode');updateUI();MZDEV.showToast('Abgemeldet');
}
"""
COMMON_FUNCS = """
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');}));
function switchTab(tab){const tabs=['github','google','discord','twitch','reddit','spotify','admin'];tabs.forEach((t,i)=>{const btn=document.querySelector('#authTabs button:nth-child('+(i+1)+')');if(btn)btn.classList.toggle('active',t===tab);const form=document.getElementById('form-'+t);if(form)form.classList.toggle('active',t===tab);});}
function toggleProfileDropdown(){document.getElementById('profileDropdown').classList.toggle('open');}
function closeDropdown(){document.getElementById('profileDropdown').classList.remove('open');}
document.addEventListener('click',e=>{const w=document.querySelector('.profile-wrap');if(w&&!w.contains(e.target))closeDropdown();});
function toggleAdminMode(){if(MZDEV.isAdmin)document.body.classList.toggle('admin-mode');}
function toggleChatPanel(){document.getElementById('chatPanel').classList.toggle('open');}
function openChatPanel(){document.getElementById('chatPanel').classList.add('open');}
"""
UPDATE_UI = """
function updateUI(){
    const user=MZDEV.currentUser,ia=MZDEV.isAdmin;
    const dot=document.getElementById('online-dot');if(dot)dot.classList.toggle('visible',!!user);
    const pdu=document.getElementById('pd-username');if(pdu)pdu.textContent=user?(user.displayName||user.username):'Gast';
    const pdr=document.getElementById('pd-role');if(pdr)pdr.textContent=user?(ia?'// ADMIN':'// '+((user.provider||'').toUpperCase())):'// NICHT ANGEMELDET';
    const pli=document.getElementById('pd-loggedin');if(pli)pli.style.display=user?'block':'none';
    const plo=document.getElementById('pd-loggedout');if(plo)plo.style.display=user?'none':'block';
    const cb=document.getElementById('chatToggleBtn');if(cb)cb.style.display=user?'flex':'none';
    const ib=document.getElementById('inboxToggleBtn');if(ib)ib.style.display=user?'flex':'none';
    const ab=document.getElementById('pd-admin-btn');if(ab)ab.style.display=ia?'flex':'none';
    if(user&&user.avatar){
        const aI=document.getElementById('accountIcon');if(aI)aI.style.display='none';
        const aA=document.getElementById('accountAvatar');if(aA){aA.src=user.avatar;aA.style.display='block';}
        const paw=document.getElementById('pd-avatar-wrap');if(paw)paw.innerHTML='<img src="'+user.avatar+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">';
    }else{
        const aI=document.getElementById('accountIcon');if(aI)aI.style.display='';
        const aA=document.getElementById('accountAvatar');if(aA)aA.style.display='none';
    }
    const v=document.getElementById('pd-verified');
    if(v){if(user&&user.connectedServices&&user.connectedServices.length){v.style.display='flex';v.innerHTML=user.connectedServices.map(function(s){return '<span class=\"verified-badge\">'+s.toUpperCase()+' \\u2713</span>';}).join('');}else v.style.display='none';}
    if(ia)document.body.classList.add('admin-mode');
    loadNotifCount();
}
async function loadNotifCount(){
    try{
        const data=await API.getNotifCount();
        if(data&&data.unread>0){
            const badge=document.getElementById('notifBadge');if(badge){badge.style.display='block';badge.textContent=data.unread;}
        }
    }catch(e){}
}
"""
CURSOR_CODE = """const cursor=document.getElementById('cursor');let mouseX=0,mouseY=0,curX=0,curY=0;
document.addEventListener('mousemove',e=>{mouseX=e.clientX;mouseY=e.clientY;});
function animateCursor(){curX+=(mouseX-curX)*0.15;curY+=(mouseY-curY)*0.15;if(cursor){cursor.style.left=curX+'px';cursor.style.top=curY+'px';}requestAnimationFrame(animateCursor);}
animateCursor();
document.addEventListener('mouseover',e=>{if(e.target.matches('a,button,input,select,textarea,.form-btn,.login-btn,.filter-btn,.skill-card,.project-card,.theme-card,.danger-btn,.toggle,.suggestion-tag,.result-item,.search-filter'))cursor&&cursor.classList.add('hover');else cursor&&cursor.classList.remove('hover');});
"""
CHAT_CODE = """function sendChatMessage(){const user=MZDEV.currentUser;if(!user){MZDEV.showToast('Bitte erst anmelden','error');return;}const input=document.getElementById('chatInput');const msg=input.value.trim();if(!msg)return;const filtered=MZDEV.filterContent(msg);if(!filtered){MZDEV.showToast('Nachricht enthaelt unzulaessige Inhalte','error');return;}const chatBody=document.getElementById('chatBody');const el=document.createElement('div');el.className='chat-msg me';el.innerHTML=msg+'<div class=\"chat-msg-meta\">'+user.username+' - jetzt</div>';chatBody.appendChild(el);chatBody.scrollTop=chatBody.scrollHeight;const msgs=MZDEV.messages;if(!msgs[user.username])msgs[user.username]=[];msgs[user.username].push({from:user.username,text:MZDEV.xorEncrypt(msg),ts:Date.now()});MZDEV.saveMessages(msgs);input.value='';}
"""
# Build a full common script block
FULL_COMMON_SCRIPT = SHARED_API_TAGS + "\n<script>\n" + CURSOR_CODE + COMMON_FUNCS + AUTH_HANDLERS + UPDATE_UI + CHAT_CODE + "</script>"
# ============================================================
# 1) PROFILE.HTML
# ============================================================
print("1) profile.html")
c = readf('profile.html')
# Replace from the inline MZDEV definition to just before "// PROFILE"
idx_mzdev = c.find("<script>\nconst MZDEV = {")
idx_profile = c.find("// PROFILE\nfunction renderProfile(){")
if idx_mzdev >= 0 and idx_profile >= 0:
    c = c[:idx_mzdev] + FULL_COMMON_SCRIPT + "\n<script>\n" + c[idx_profile:]
    # Update connectService to use API
    c = c.replace(
        "function connectService(service){\n    const user = MZDEV.currentUser; if(!user) return;\n    MZDEV.addConnectedService(user.username, service);",
        "function connectService(service){\n    const user = MZDEV.currentUser; if(!user) return;\n    API.oauthConnect(service); return;\n    MZDEV.addConnectedService(user.username, service);"
    )
    writef('profile.html', c)
    print("   OK")
else:
    print("   SKIP - markers not found")
# ============================================================
# 2) SEARCH.HTML
# ============================================================
print("2) search.html")
c = readf('search.html')
idx_mzdev = c.find("<script>\nconst MZDEV={")
idx_search = c.find("// SEARCH DATA")
if idx_mzdev >= 0 and idx_search >= 0:
    c = c[:idx_mzdev] + FULL_COMMON_SCRIPT + "\n<script>\n" + c[idx_search:]
    writef('search.html', c)
    print("   OK")
else:
    print("   SKIP - markers not found")
# ============================================================
# 3) SETTINGS.HTML
# ============================================================
print("3) settings.html")
c = readf('settings.html')
idx_mzdev = c.find("<script>\n// " + "=" * 55 + "\n// MZDEV SHARED STATE")
idx_end = c.find("</script>\n</body>")
if idx_mzdev >= 0 and idx_end >= 0:
    # Extract only settings-specific functions we need to keep
    # We'll build new settings script
    SETTINGS_SCRIPT = """
const THEME_PRESETS = {
    default: { accent: '#f5e642', bg: '#000000', surface: '#111111' },
    midnight: { accent: '#6366f1', bg: '#0a0a1a', surface: '#1a1a2e' },
    forest: { accent: '#22c55e', bg: '#0a0f0a', surface: '#111a11' },
    sunset: { accent: '#f97316', bg: '#1a0a0a', surface: '#1f1111' },
    rose: { accent: '#ec4899', bg: '#0f0a0f', surface: '#1a111a' },
    ice: { accent: '#38bdf8', bg: '#0a0f14', surface: '#111920' }
};
function applyPresetTheme(name, el) {
    const t = THEME_PRESETS[name]; if(!t) return;
    MZDEV.saveTheme(t);
    document.querySelectorAll('.theme-card').forEach(function(c){c.classList.remove('active');});
    if(el) el.classList.add('active');
    document.getElementById('colorAccent').value = t.accent;
    document.getElementById('colorBg').value = t.bg;
    document.getElementById('colorSurface').value = t.surface;
    MZDEV.showToast('Theme angewendet', 'success');
}
function applyCustomColor() {
    var t = { accent: document.getElementById('colorAccent').value, bg: document.getElementById('colorBg').value, surface: document.getElementById('colorSurface').value };
    MZDEV.saveTheme(t);
    document.querySelectorAll('.theme-card').forEach(function(c){c.classList.remove('active');});
    MZDEV.showToast('Farben angepasst', 'success');
}
function saveSetting() {
    var s = {
        animations: document.getElementById('settAnimations').checked,
        notifications: document.getElementById('settNotifications').checked,
        newArticleNotif: document.getElementById('settNewArticleNotif').checked,
        publicProfile: document.getElementById('settPublicProfile').checked,
        fontSize: document.getElementById('settFontSize').value
    };
    var lang = document.getElementById('settLanguage');
    if(lang) s.language = lang.value;
    var df = document.getElementById('settDateFormat');
    if(df) s.dateFormat = df.value;
    var rm = document.getElementById('settReducedMotion');
    if(rm) s.reducedMotion = rm.checked;
    var hc = document.getElementById('settHighContrast');
    if(hc) s.highContrast = hc.checked;
    var cc = document.getElementById('settCustomCursor');
    if(cc) s.customCursor = cc.checked;
    MZDEV.saveSettings(s);
    applyFontSize(s.fontSize);
    if(s.reducedMotion) document.body.classList.add('reduced-motion');
    else document.body.classList.remove('reduced-motion');
    if(s.highContrast) document.body.classList.add('high-contrast');
    else document.body.classList.remove('high-contrast');
    if(s.customCursor===false){var cur=document.getElementById('cursor');if(cur)cur.style.display='none';document.body.style.cursor='auto';}
    else{var cur=document.getElementById('cursor');if(cur)cur.style.display='';}
    MZDEV.showToast('Einstellung gespeichert', 'success');
}
function applyFontSize(size) {
    var root = document.documentElement;
    if(size === 'small') root.style.fontSize = '14px';
    else if(size === 'large') root.style.fontSize = '18px';
    else root.style.fontSize = '16px';
}
function clearAllData() {
    if(!confirm('Wirklich ALLE lokalen Daten loeschen?')) return;
    var keys = Object.keys(localStorage).filter(function(k){return k.indexOf('mzdev_')===0;});
    keys.forEach(function(k){localStorage.removeItem(k);});
    MZDEV.showToast('Alle Daten geloescht', 'success');
    setTimeout(function(){location.reload();}, 1000);
}
function resetTheme() {
    MZDEV.saveTheme(THEME_PRESETS['default']);
    document.getElementById('colorAccent').value = '#f5e642';
    document.getElementById('colorBg').value = '#000000';
    document.getElementById('colorSurface').value = '#111111';
    document.querySelectorAll('.theme-card').forEach(function(c){c.classList.remove('active');});
    var first = document.querySelector('.theme-card');
    if(first) first.classList.add('active');
    MZDEV.showToast('Theme zurueckgesetzt', 'success');
}
// -- ACCOUNT SETTINGS --
async function saveAccountSettings(){
    var user = MZDEV.currentUser;
    if(!user){MZDEV.showToast('Bitte erst anmelden','error');return;}
    try{
        var data = {
            display_name: document.getElementById('settDisplayName').value.trim(),
            bio: document.getElementById('settBio').value.trim(),
            website: document.getElementById('settWebsite').value.trim(),
            location: document.getElementById('settLocation').value.trim(),
            email: document.getElementById('settEmail').value.trim()
        };
        await API.updateSettings(data);
        MZDEV.saveProfile(user.username, {displayName:data.display_name, bio:data.bio, website:data.website, location:data.location, email:data.email});
        var updated = Object.assign({}, user, {displayName:data.display_name, bio:data.bio});
        MZDEV.setSession(updated, MZDEV.isAdmin);
        updateUI();
        MZDEV.showToast('Profil gespeichert!','success');
    }catch(e){
        MZDEV.saveProfile(user.username, {displayName:document.getElementById('settDisplayName').value.trim(), bio:document.getElementById('settBio').value.trim()});
        MZDEV.showToast('Profil lokal gespeichert!','success');
    }
}
// -- OAUTH SERVICES --
var OAUTH_PROVIDERS = [
    {id:'github',name:'GitHub',color:'#ffffff'},
    {id:'google',name:'Google',color:'#4285F4'},
    {id:'discord',name:'Discord',color:'#5865F2'},
    {id:'twitch',name:'Twitch',color:'#9146FF'},
    {id:'reddit',name:'Reddit',color:'#FF4500'},
    {id:'spotify',name:'Spotify',color:'#1DB954'}
];
async function renderOAuthServices(){
    var container = document.getElementById('oauthServicesList');
    if(!container) return;
    var user = MZDEV.currentUser;
    var connected = [];
    try {
        var resp = await API.getConnectedServices();
        if(resp && Array.isArray(resp.services)) connected = resp.services.map(function(s){return s.provider;});
    } catch(e) {
        if(user) connected = user.connectedServices || [];
    }
    container.innerHTML = OAUTH_PROVIDERS.map(function(p) {
        var isConn = connected.indexOf(p.id) >= 0;
        return '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.8rem 1rem;border:1px solid var(--border);margin-bottom:0.5rem;">' +
            '<span style="font-family:IBM Plex Mono,monospace;font-size:0.82rem;color:'+p.color+';">'+p.name+'</span>' +
            (isConn ?
                '<button onclick="disconnectOAuth(\\''+p.id+'\\',this)" style="background:none;border:1px solid rgba(255,95,87,0.4);color:var(--red);font-family:IBM Plex Mono,monospace;font-size:0.68rem;padding:0.3rem 0.8rem;cursor:pointer;">Trennen</button>' :
                '<button onclick="connectOAuth(\\''+p.id+'\\',this)" style="background:none;border:1px solid rgba(245,230,66,0.3);color:var(--yellow);font-family:IBM Plex Mono,monospace;font-size:0.68rem;padding:0.3rem 0.8rem;cursor:pointer;">Verbinden</button>'
            ) + '</div>';
    }).join('');
}
function connectOAuth(provider){API.oauthConnect(provider);}
async function disconnectOAuth(provider, btn){
    if(!confirm('Wirklich '+provider+' trennen?')) return;
    try{
        await API.disconnectService(provider);
        btn.textContent='Getrennt'; btn.disabled=true;
        MZDEV.showToast(provider+' getrennt','success');
        setTimeout(function(){renderOAuthServices();},1000);
    }catch(e){MZDEV.showToast('Fehler: '+e.message,'error');}
}
// -- INIT --
function loadSettings() {
    var s = MZDEV.settings;
    document.getElementById('settAnimations').checked = s.animations !== false;
    document.getElementById('settNotifications').checked = s.notifications !== false;
    document.getElementById('settNewArticleNotif').checked = !!s.newArticleNotif;
    document.getElementById('settPublicProfile').checked = s.publicProfile !== false;
    document.getElementById('settFontSize').value = s.fontSize || 'normal';
    var lang = document.getElementById('settLanguage');
    if(lang) lang.value = s.language || 'de';
    var df = document.getElementById('settDateFormat');
    if(df) df.value = s.dateFormat || 'dd.mm.yyyy';
    var rm = document.getElementById('settReducedMotion');
    if(rm) rm.checked = !!s.reducedMotion;
    var hc = document.getElementById('settHighContrast');
    if(hc) hc.checked = !!s.highContrast;
    var cc = document.getElementById('settCustomCursor');
    if(cc) cc.checked = s.customCursor !== false;
    applyFontSize(s.fontSize || 'normal');
    var t = MZDEV.theme;
    document.getElementById('colorAccent').value = t.accent;
    document.getElementById('colorBg').value = t.bg;
    document.getElementById('colorSurface').value = t.surface;
    var user = MZDEV.currentUser;
    if(user){
        var acct = document.getElementById('accountSection');
        if(acct) acct.style.display = '';
        var oauth = document.getElementById('oauthSection');
        if(oauth) oauth.style.display = '';
        var profile = MZDEV.getProfile(user.username) || user;
        var dn = document.getElementById('settDisplayName');
        if(dn) dn.value = profile.displayName || user.displayName || '';
        var bio = document.getElementById('settBio');
        if(bio) bio.value = profile.bio || '';
        var web = document.getElementById('settWebsite');
        if(web) web.value = profile.website || '';
        var loc = document.getElementById('settLocation');
        if(loc) loc.value = profile.location || '';
        var em = document.getElementById('settEmail');
        if(em) em.value = profile.email || user.email || '';
        renderOAuthServices();
    }
}
MZDEV.applyTheme();
updateUI();
loadSettings();
"""
    c = c[:idx_mzdev] + FULL_COMMON_SCRIPT + "\n<script>\n" + SETTINGS_SCRIPT + "\n</script>\n" + c[idx_end + len("</script>\n"):]
    # Now add account/profile/oauth sections before danger zone
    ACCOUNT_HTML = """    <!-- ACCOUNT -->
    <div class="settings-section" id="accountSection" style="display:none;">
        <div class="settings-section-title">// Konto &amp; Profil</div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Anzeigename</div><div class="setting-desc">Dein Name, wie er anderen angezeigt wird</div></div><input type="text" class="setting-input" id="settDisplayName" placeholder="Dein Name" style="background:var(--gray-dark);border:1px solid var(--border);color:var(--white);font-family:IBM Plex Mono,monospace;font-size:0.78rem;padding:0.4rem 0.7rem;outline:none;width:200px;"></div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Bio</div><div class="setting-desc">Kurze Beschreibung</div></div><input type="text" class="setting-input" id="settBio" placeholder="Ueber dich..." style="background:var(--gray-dark);border:1px solid var(--border);color:var(--white);font-family:IBM Plex Mono,monospace;font-size:0.78rem;padding:0.4rem 0.7rem;outline:none;width:200px;"></div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Website</div><div class="setting-desc">Deine Website</div></div><input type="url" class="setting-input" id="settWebsite" placeholder="https://..." style="background:var(--gray-dark);border:1px solid var(--border);color:var(--white);font-family:IBM Plex Mono,monospace;font-size:0.78rem;padding:0.4rem 0.7rem;outline:none;width:200px;"></div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Standort</div><div class="setting-desc">Dein Wohnort</div></div><input type="text" class="setting-input" id="settLocation" placeholder="Stadt, Land" style="background:var(--gray-dark);border:1px solid var(--border);color:var(--white);font-family:IBM Plex Mono,monospace;font-size:0.78rem;padding:0.4rem 0.7rem;outline:none;width:200px;"></div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">E-Mail</div><div class="setting-desc">Nicht oeffentlich</div></div><input type="email" class="setting-input" id="settEmail" placeholder="mail@example.com" style="background:var(--gray-dark);border:1px solid var(--border);color:var(--white);font-family:IBM Plex Mono,monospace;font-size:0.78rem;padding:0.4rem 0.7rem;outline:none;width:200px;"></div>
        <div style="margin-top:1rem;"><button class="m-btn" onclick="saveAccountSettings()" style="font-size:0.75rem;padding:0.5rem 1.5rem;">Profil speichern</button></div>
    </div>
    <!-- OAUTH SERVICES -->
    <div class="settings-section" id="oauthSection" style="display:none;">
        <div class="settings-section-title">// Verbundene Dienste</div>
        <p style="font-size:0.78rem;color:var(--gray-muted);margin-bottom:1.2rem;">Verwalte deine OAuth-Anmeldungen.</p>
        <div id="oauthServicesList"></div>
    </div>
    <!-- LANGUAGE -->
    <div class="settings-section">
        <div class="settings-section-title">// Sprache &amp; Region</div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Sprache</div><div class="setting-desc">Anzeigesprache</div></div><select class="setting-select" id="settLanguage" onchange="saveSetting()"><option value="de" selected>Deutsch</option><option value="en">English</option></select></div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Datumsformat</div><div class="setting-desc">Format der Daten</div></div><select class="setting-select" id="settDateFormat" onchange="saveSetting()"><option value="dd.mm.yyyy" selected>DD.MM.YYYY</option><option value="yyyy-mm-dd">YYYY-MM-DD</option><option value="mm/dd/yyyy">MM/DD/YYYY</option></select></div>
    </div>
    <!-- ACCESSIBILITY -->
    <div class="settings-section">
        <div class="settings-section-title">// Barrierefreiheit</div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Reduzierte Bewegung</div><div class="setting-desc">Animationen minimieren</div></div><label class="toggle"><input type="checkbox" id="settReducedMotion" onchange="saveSetting()"><span class="toggle-slider"></span></label></div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Hoher Kontrast</div><div class="setting-desc">Bessere Lesbarkeit</div></div><label class="toggle"><input type="checkbox" id="settHighContrast" onchange="saveSetting()"><span class="toggle-slider"></span></label></div>
        <div class="setting-row"><div class="setting-info"><div class="setting-label">Custom Cursor</div><div class="setting-desc">Benutzerdefinierten Cursor anzeigen</div></div><label class="toggle"><input type="checkbox" id="settCustomCursor" checked onchange="saveSetting()"><span class="toggle-slider"></span></label></div>
    </div>"""
    c = c.replace('    <!-- DANGER -->', ACCOUNT_HTML + '\n    <!-- DANGER -->')
    writef('settings.html', c)
    print("   OK")
else:
    print("   SKIP - markers not found", idx_mzdev, idx_end)
# ============================================================
# 4) SKILLS.HTML
# ============================================================
print("4) skills.html")
c = readf('skills.html')
# Fix broken </script> before shared.js
c = c.replace('</script>\n<script src="shared.js"></script>\n</script>', '</script>\n' + SHARED_API_TAGS)
# Also handle the case where shared.js is already there but with extra </script>
c = c.replace('<script src="shared.js"></script>\n</script>', '<script src="shared.js"></script>')
# Replace inline MZDEV
idx_mzdev = c.find("<script>\nconst MZDEV={")
idx_skills = c.find("// SKILLS DATA")
if idx_mzdev >= 0 and idx_skills >= 0:
    c = c[:idx_mzdev] + SHARED_API_TAGS + "\n<script>\n" + c[idx_skills:]
# Remove duplicate shared.js at bottom
count = c.count('<script src="shared.js"></script>')
if count > 1:
    # Remove everything from 2nd shared.js to </body>
    first = c.find('<script src="shared.js"></script>')
    second = c.find('<script src="shared.js"></script>', first + 1)
    end_body = c.find('</body>', second)
    if second > 0 and end_body > 0:
        c = c[:second] + c[end_body:]
# Add auth handlers if missing
if 'function handleGithubLogin' not in c:
    c = c.replace('</script>\n</body>', AUTH_HANDLERS + '</script>\n</body>')
# Add inbox to updateUI if missing
if "getElementById('inboxToggleBtn')" not in c and "chatToggleBtn" in c:
    c = c.replace(
        "const cb=document.getElementById('chatToggleBtn');if(cb)cb.style.display=user?'flex':'none';",
        "const cb=document.getElementById('chatToggleBtn');if(cb)cb.style.display=user?'flex':'none';const ib=document.getElementById('inboxToggleBtn');if(ib)ib.style.display=user?'flex':'none';"
    )
writef('skills.html', c)
print("   OK")
# ============================================================
# 5) PROJECTS.HTML
# ============================================================
print("5) projects.html")
c = readf('projects.html')
c = c.replace('</script>\n<script src="shared.js"></script>\n</script>', '</script>\n' + SHARED_API_TAGS)
c = c.replace('<script src="shared.js"></script>\n</script>', '<script src="shared.js"></script>')
idx_mzdev = c.find("<script>\nconst MZDEV={")
idx_proj = c.find("// DEFAULT PROJECTS")
if idx_mzdev >= 0 and idx_proj >= 0:
    c = c[:idx_mzdev] + SHARED_API_TAGS + "\n<script>\n" + c[idx_proj:]
count = c.count('<script src="shared.js"></script>')
if count > 1:
    first = c.find('<script src="shared.js"></script>')
    second = c.find('<script src="shared.js"></script>', first + 1)
    end_body = c.find('</body>', second)
    if second > 0 and end_body > 0:
        c = c[:second] + c[end_body:]
if 'function handleGithubLogin' not in c:
    c = c.replace('</script>\n</body>', AUTH_HANDLERS + '</script>\n</body>')
if "getElementById('inboxToggleBtn')" not in c and "chatToggleBtn" in c:
    c = c.replace(
        "const cb=document.getElementById('chatToggleBtn');if(cb)cb.style.display=user?'flex':'none';",
        "const cb=document.getElementById('chatToggleBtn');if(cb)cb.style.display=user?'flex':'none';const ib=document.getElementById('inboxToggleBtn');if(ib)ib.style.display=user?'flex':'none';"
    )
writef('projects.html', c)
print("   OK")
# ============================================================
# 6) INDEX.HTML - Add inbox toggle to updateUI
# ============================================================
print("6) index.html")
c = readf('index.html')
if "getElementById('inboxToggleBtn')" not in c and "inboxToggleBtn" in c:
    c = c.replace(
        "const chatBtn=document.getElementById('chatToggleBtn');if(chatBtn)chatBtn.style.display=user?'flex':'none';",
        "const chatBtn=document.getElementById('chatToggleBtn');if(chatBtn)chatBtn.style.display=user?'flex':'none';const ib=document.getElementById('inboxToggleBtn');if(ib)ib.style.display=user?'flex':'none';"
    )
    writef('index.html', c)
    print("   OK")
else:
    print("   SKIP - already done or no inboxToggleBtn")
# ============================================================
# 7) ARTICLES.HTML - ensure shared.js is loaded
# ============================================================
print("7) articles.html")
c = readf('articles.html')
if '<script src="shared.js"></script>' not in c:
    c = c.replace('<script src="api.js"></script>', '<script src="shared.js"></script>\n<script src="api.js"></script>')
    writef('articles.html', c)
    print("   Added shared.js")
else:
    print("   OK - already has shared.js")
# ============================================================
# 8) INBOX.HTML - ensure shared.js + api.js
# ============================================================
print("8) inbox.html")
c = readf('inbox.html')
if '<script src="shared.js"></script>' not in c:
    if '<script src="api.js"></script>' in c:
        c = c.replace('<script src="api.js"></script>', '<script src="shared.js"></script>\n<script src="api.js"></script>')
    else:
        c = c.replace('</body>', '<script src="shared.js"></script>\n<script src="api.js"></script>\n</body>')
    writef('inbox.html', c)
    print("   Added shared.js")
else:
    print("   OK")
# ============================================================
# 9) PROJECT.HTML (detail page) - already has both, just check
# ============================================================
print("9) project.html")
c = readf('project.html')
has_shared = '<script src="shared.js"></script>' in c
has_api = '<script src="api.js"></script>' in c
print(f"   shared.js={'yes' if has_shared else 'NO'}, api.js={'yes' if has_api else 'NO'}")
print("\nDONE - All pages transformed!")