#!/usr/bin/env python3
import os
PAGES = os.path.expanduser("~/website/pages")
path = os.path.join(PAGES, "settings.html")
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()
# 1) Add new HTML sections before the danger zone
NEW_SECTIONS = """    <!-- ACCOUNT & PROFILE (logged-in only) -->
    <div class="settings-section" id="accountSection" style="display:none;">
        <div class="settings-section-title">// Konto &amp; Profil</div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Anzeigename</div>
                <div class="setting-desc">Dein Name, der anderen angezeigt wird</div>
            </div>
            <input type="text" class="setting-input" id="settDisplayName" placeholder="Anzeigename" style="background:var(--gray-mid);border:1px solid var(--border);color:var(--white);padding:0.4rem 0.8rem;border-radius:6px;font-size:0.85rem;">
        </div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Bio</div>
                <div class="setting-desc">Kurze Beschreibung von dir</div>
            </div>
            <textarea class="setting-input" id="settBio" placeholder="Erz&auml;hl etwas &uuml;ber dich..." rows="2" style="background:var(--gray-mid);border:1px solid var(--border);color:var(--white);padding:0.4rem 0.8rem;border-radius:6px;font-size:0.85rem;width:220px;resize:vertical;"></textarea>
        </div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Website</div>
                <div class="setting-desc">Deine pers&ouml;nliche Website</div>
            </div>
            <input type="url" class="setting-input" id="settWebsite" placeholder="https://..." style="background:var(--gray-mid);border:1px solid var(--border);color:var(--white);padding:0.4rem 0.8rem;border-radius:6px;font-size:0.85rem;">
        </div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Standort</div>
                <div class="setting-desc">Wo du dich befindest</div>
            </div>
            <input type="text" class="setting-input" id="settLocation" placeholder="Stadt, Land" style="background:var(--gray-mid);border:1px solid var(--border);color:var(--white);padding:0.4rem 0.8rem;border-radius:6px;font-size:0.85rem;">
        </div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">E-Mail</div>
                <div class="setting-desc">Kontakt-E-Mail (nicht &ouml;ffentlich)</div>
            </div>
            <input type="email" class="setting-input" id="settEmail" placeholder="name@example.com" style="background:var(--gray-mid);border:1px solid var(--border);color:var(--white);padding:0.4rem 0.8rem;border-radius:6px;font-size:0.85rem;">
        </div>
        <button class="m-btn" onclick="saveAccountSettings()" style="margin-top:1rem;background:var(--yellow);color:var(--black);border:none;padding:0.5rem 1.5rem;border-radius:6px;font-weight:600;cursor:pointer;">Profil speichern</button>
    </div>
    <!-- OAUTH SERVICES (logged-in only) -->
    <div class="settings-section" id="oauthSection" style="display:none;">
        <div class="settings-section-title">// Verbundene Dienste</div>
        <div id="oauthServicesList">
            <div class="setting-row" data-service="github">
                <div class="setting-info">
                    <div class="setting-label">GitHub</div>
                    <div class="setting-desc" id="githubStatus">Nicht verbunden</div>
                </div>
                <button class="m-btn" id="githubBtn" onclick="toggleOAuthService('github')" style="background:var(--gray-mid);color:var(--white);border:1px solid var(--border);padding:0.4rem 1rem;border-radius:6px;cursor:pointer;">Verbinden</button>
            </div>
            <div class="setting-row" data-service="google">
                <div class="setting-info">
                    <div class="setting-label">Google</div>
                    <div class="setting-desc" id="googleStatus">Nicht verbunden</div>
                </div>
                <button class="m-btn" id="googleBtn" onclick="toggleOAuthService('google')" style="background:var(--gray-mid);color:var(--white);border:1px solid var(--border);padding:0.4rem 1rem;border-radius:6px;cursor:pointer;">Verbinden</button>
            </div>
            <div class="setting-row" data-service="discord">
                <div class="setting-info">
                    <div class="setting-label">Discord</div>
                    <div class="setting-desc" id="discordStatus">Nicht verbunden</div>
                </div>
                <button class="m-btn" id="discordBtn" onclick="toggleOAuthService('discord')" style="background:var(--gray-mid);color:var(--white);border:1px solid var(--border);padding:0.4rem 1rem;border-radius:6px;cursor:pointer;">Verbinden</button>
            </div>
            <div class="setting-row" data-service="twitch">
                <div class="setting-info">
                    <div class="setting-label">Twitch</div>
                    <div class="setting-desc" id="twitchStatus">Nicht verbunden</div>
                </div>
                <button class="m-btn" id="twitchBtn" onclick="toggleOAuthService('twitch')" style="background:var(--gray-mid);color:var(--white);border:1px solid var(--border);padding:0.4rem 1rem;border-radius:6px;cursor:pointer;">Verbinden</button>
            </div>
            <div class="setting-row" data-service="reddit">
                <div class="setting-info">
                    <div class="setting-label">Reddit</div>
                    <div class="setting-desc" id="redditStatus">Nicht verbunden</div>
                </div>
                <button class="m-btn" id="redditBtn" onclick="toggleOAuthService('reddit')" style="background:var(--gray-mid);color:var(--white);border:1px solid var(--border);padding:0.4rem 1rem;border-radius:6px;cursor:pointer;">Verbinden</button>
            </div>
            <div class="setting-row" data-service="spotify">
                <div class="setting-info">
                    <div class="setting-label">Spotify</div>
                    <div class="setting-desc" id="spotifyStatus">Nicht verbunden</div>
                </div>
                <button class="m-btn" id="spotifyBtn" onclick="toggleOAuthService('spotify')" style="background:var(--gray-mid);color:var(--white);border:1px solid var(--border);padding:0.4rem 1rem;border-radius:6px;cursor:pointer;">Verbinden</button>
            </div>
        </div>
    </div>
    <!-- LANGUAGE & REGION -->
    <div class="settings-section">
        <div class="settings-section-title">// Sprache &amp; Region</div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Sprache</div>
                <div class="setting-desc">Anzeigesprache der Website</div>
            </div>
            <select class="setting-select" id="settLanguage" onchange="saveSetting()">
                <option value="de" selected>Deutsch</option>
                <option value="en">English</option>
            </select>
        </div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Datumsformat</div>
                <div class="setting-desc">Format f&uuml;r Datumsanzeigen</div>
            </div>
            <select class="setting-select" id="settDateFormat" onchange="saveSetting()">
                <option value="DD.MM.YYYY" selected>DD.MM.YYYY</option>
                <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                <option value="YYYY-MM-DD">YYYY-MM-DD</option>
            </select>
        </div>
    </div>
    <!-- ACCESSIBILITY -->
    <div class="settings-section">
        <div class="settings-section-title">// Barrierefreiheit</div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Reduzierte Bewegung</div>
                <div class="setting-desc">Animationen und &Uuml;berg&auml;nge minimieren</div>
            </div>
            <label class="toggle"><input type="checkbox" id="settReducedMotion" onchange="saveSetting()"><span class="toggle-slider"></span></label>
        </div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Hoher Kontrast</div>
                <div class="setting-desc">Erh&ouml;hte Farbkontraste f&uuml;r bessere Lesbarkeit</div>
            </div>
            <label class="toggle"><input type="checkbox" id="settHighContrast" onchange="saveSetting()"><span class="toggle-slider"></span></label>
        </div>
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label">Eigener Cursor</div>
                <div class="setting-desc">Custom-Cursor deaktivieren und Standard-Cursor verwenden</div>
            </div>
            <label class="toggle"><input type="checkbox" id="settDefaultCursor" onchange="toggleCustomCursor()"><span class="toggle-slider"></span></label>
        </div>
    </div>
"""
# Insert new sections before danger zone
content = content.replace(
    '    <!-- DANGER -->\n    <div class="danger-zone">',
    NEW_SECTIONS + '    <!-- DANGER -->\n    <div class="danger-zone">'
)
# 2) Replace the entire script block (from <script> with MZDEV to </script></body></html>)
# Find the script block start
script_start = content.find('<script>\n// ' + chr(9552))  # Unicode box drawing char
if script_start == -1:
    # Try alternate marker
    script_start = content.find('<script>\n// MZDEV SHARED STATE')
    if script_start == -1:
        # Find the first <script> after chat panel
        chat_end = content.find('</div>\n<script>')
        if chat_end != -1:
            script_start = chat_end + len('</div>\n')
script_end = content.rfind('</script>')
if script_start != -1 and script_end != -1:
    end_pos = script_end + len('</script>')
    
    NEW_SCRIPT = """<script src="shared.js"></script>
<script src="api.js"></script>
<script>
// CURSOR
const cursor=document.getElementById('cursor');
let mouseX=0,mouseY=0,curX=0,curY=0;
document.addEventListener('mousemove',e=>{mouseX=e.clientX;mouseY=e.clientY;});
function animateCursor(){curX+=(mouseX-curX)*0.15;curY+=(mouseY-curY)*0.15;if(cursor){cursor.style.left=curX+'px';cursor.style.top=curY+'px';}requestAnimationFrame(animateCursor);}
animateCursor();
document.addEventListener('mouseover',e=>{if(e.target.matches('a,button,input,select,.toggle,.theme-card,.danger-btn'))cursor&&cursor.classList.add('hover');else cursor&&cursor.classList.remove('hover');});
// MODALS
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');}));
function switchTab(tab){['github','google','discord','admin'].forEach((t,i)=>{document.querySelector(`#authTabs button:nth-child(${i+1})`).classList.toggle('active',t===tab);document.getElementById('form-'+t).classList.toggle('active',t===tab);});}
// AUTH (API-based)
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
// UI
function updateUI(){
    const user=MZDEV.currentUser,isAdmin=MZDEV.isAdmin;
    document.getElementById('online-dot').classList.toggle('visible',!!user);
    document.getElementById('pd-username').textContent=user?(user.displayName||user.username):'Gast';
    document.getElementById('pd-role').textContent=user?(isAdmin?'// ADMIN':'// '+(user.provider||'').toUpperCase()+' \\u00b7 '+user.username):'// NICHT ANGEMELDET';
    document.getElementById('pd-loggedin').style.display=user?'block':'none';
    document.getElementById('pd-loggedout').style.display=user?'none':'block';
    const chatBtn=document.getElementById('chatToggleBtn');if(chatBtn)chatBtn.style.display=user?'flex':'none';
    const inboxBtn=document.getElementById('inboxToggleBtn');if(inboxBtn)inboxBtn.style.display=user?'flex':'none';
    const admBtn=document.getElementById('pd-admin-btn');if(admBtn)admBtn.style.display=isAdmin?'flex':'none';
    if(user){
        const aI=document.getElementById('accountIcon'),aA=document.getElementById('accountAvatar');
        if(user.avatar){aI.style.display='none';aA.src=user.avatar;aA.style.display='block';document.getElementById('pd-avatar-wrap').innerHTML='<img src="'+user.avatar+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">';}
        else{aI.style.display='';aA.style.display='none';}
        const svcs=user.connectedServices||[];const vEl=document.getElementById('pd-verified');
        if(svcs.length>0){vEl.style.display='flex';vEl.innerHTML=svcs.map(s=>'<span class="verified-badge">'+s.toUpperCase()+' Verified \\u2713</span>').join('');}else vEl.style.display='none';
        if(isAdmin)document.body.classList.add('admin-mode');
        // Show account/oauth sections
        const accSec=document.getElementById('accountSection');if(accSec)accSec.style.display='';
        const oaSec=document.getElementById('oauthSection');if(oaSec)oaSec.style.display='';
    } else {
        document.getElementById('accountIcon').style.display='';
        document.getElementById('accountAvatar').style.display='none';
        document.getElementById('pd-verified').style.display='none';
        const accSec=document.getElementById('accountSection');if(accSec)accSec.style.display='none';
        const oaSec=document.getElementById('oauthSection');if(oaSec)oaSec.style.display='none';
    }
    loadAccountSettings();
    loadOAuthServices();
}
function toggleProfileDropdown(){document.getElementById('profileDropdown').classList.toggle('open');}
function closeDropdown(){document.getElementById('profileDropdown').classList.remove('open');}
document.addEventListener('click',e=>{const w=document.querySelector('.profile-wrap');if(w&&!w.contains(e.target))closeDropdown();});
function toggleAdminMode(){if(MZDEV.isAdmin)document.body.classList.toggle('admin-mode');}
// CHAT
function toggleChatPanel(){document.getElementById('chatPanel').classList.toggle('open');}
function openChatPanel(){document.getElementById('chatPanel').classList.add('open');}
function sendChatMessage(){const user=MZDEV.currentUser;if(!user){MZDEV.showToast('Bitte erst anmelden','error');return;}const input=document.getElementById('chatInput');const msg=input.value.trim();if(!msg)return;const filtered=MZDEV.filterContent(msg);if(!filtered){MZDEV.showToast('Nachricht enth\\u00e4lt unzul\\u00e4ssige Inhalte','error');return;}const chatBody=document.getElementById('chatBody');const el=document.createElement('div');el.className='chat-msg me';el.innerHTML=msg+'<div class="chat-msg-meta">'+user.username+' \\u00b7 jetzt</div>';chatBody.appendChild(el);chatBody.scrollTop=chatBody.scrollHeight;const msgs=MZDEV.messages;if(!msgs[user.username])msgs[user.username]=[];msgs[user.username].push({from:user.username,text:MZDEV.xorEncrypt(msg),ts:Date.now()});MZDEV.saveMessages(msgs);input.value='';}
// SETTINGS
const THEME_PRESETS = {
    default: { accent: '#f5e642', bg: '#000000', surface: '#111111' },
    midnight: { accent: '#6366f1', bg: '#0a0a1a', surface: '#1a1a2e' },
    forest: { accent: '#22c55e', bg: '#0a0f0a', surface: '#111a11' },
    sunset: { accent: '#f97316', bg: '#1a0a0a', surface: '#1f1111' },
    rose: { accent: '#ec4899', bg: '#0f0a0f', surface: '#1a111a' },
    ice: { accent: '#38bdf8', bg: '#0a0f14', surface: '#111920' }
};
function applyPresetTheme(name, el) {
    const t = THEME_PRESETS[name];
    MZDEV.saveTheme(t);
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    if(el) el.classList.add('active');
    document.getElementById('colorAccent').value = t.accent;
    document.getElementById('colorBg').value = t.bg;
    document.getElementById('colorSurface').value = t.surface;
    MZDEV.showToast('Theme angewendet', 'success');
}
function applyCustomColor() {
    const t = { accent: document.getElementById('colorAccent').value, bg: document.getElementById('colorBg').value, surface: document.getElementById('colorSurface').value };
    MZDEV.saveTheme(t);
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    MZDEV.showToast('Farben angepasst', 'success');
}
function saveSetting() {
    const s = {
        animations: document.getElementById('settAnimations').checked,
        notifications: document.getElementById('settNotifications').checked,
        newArticleNotif: document.getElementById('settNewArticleNotif').checked,
        publicProfile: document.getElementById('settPublicProfile').checked,
        fontSize: document.getElementById('settFontSize').value,
        language: document.getElementById('settLanguage').value,
        dateFormat: document.getElementById('settDateFormat').value,
        reducedMotion: document.getElementById('settReducedMotion').checked,
        highContrast: document.getElementById('settHighContrast').checked
    };
    MZDEV.saveSettings(s);
    applyFontSize(s.fontSize);
    if(s.reducedMotion) document.body.classList.add('reduced-motion');
    else document.body.classList.remove('reduced-motion');
    if(s.highContrast) document.body.classList.add('high-contrast');
    else document.body.classList.remove('high-contrast');
    MZDEV.showToast('Einstellung gespeichert', 'success');
}
function applyFontSize(size) {
    const root = document.documentElement;
    if(size === 'small') root.style.fontSize = '14px';
    else if(size === 'large') root.style.fontSize = '18px';
    else root.style.fontSize = '16px';
}
function toggleCustomCursor() {
    const off = document.getElementById('settDefaultCursor').checked;
    if(off) { document.body.style.cursor='auto'; if(cursor)cursor.style.display='none'; }
    else { document.body.style.cursor='none'; if(cursor)cursor.style.display=''; }
    const s = MZDEV.settings; s.defaultCursor = off; MZDEV.saveSettings(s);
}
// ACCOUNT
function loadAccountSettings(){
    const user=MZDEV.currentUser;if(!user)return;
    const p=MZDEV.getProfile(user.username);if(!p)return;
    const dn=document.getElementById('settDisplayName');if(dn)dn.value=p.displayName||'';
    const bio=document.getElementById('settBio');if(bio)bio.value=p.bio||'';
    const web=document.getElementById('settWebsite');if(web)web.value=p.website||'';
    const loc=document.getElementById('settLocation');if(loc)loc.value=p.location||'';
    const em=document.getElementById('settEmail');if(em)em.value=p.email||'';
}
async function saveAccountSettings(){
    const user=MZDEV.currentUser;if(!user){MZDEV.showToast('Nicht angemeldet','error');return;}
    const data={
        displayName:document.getElementById('settDisplayName').value,
        bio:document.getElementById('settBio').value,
        website:document.getElementById('settWebsite').value,
        location:document.getElementById('settLocation').value,
        email:document.getElementById('settEmail').value
    };
    MZDEV.saveProfile(user.username,data);
    // Also try API
    try{await API.request('/settings','PUT',data);}catch(e){}
    MZDEV.showToast('Profil gespeichert','success');
}
// OAUTH SERVICES
function loadOAuthServices(){
    const user=MZDEV.currentUser;if(!user)return;
    const svcs=user.connectedServices||[];
    ['github','google','discord','twitch','reddit','spotify'].forEach(s=>{
        const statusEl=document.getElementById(s+'Status');
        const btn=document.getElementById(s+'Btn');
        if(svcs.includes(s)){
            if(statusEl)statusEl.textContent='Verbunden';
            if(btn){btn.textContent='Trennen';btn.style.borderColor='var(--red);';btn.style.color='var(--red)';}
        } else {
            if(statusEl)statusEl.textContent='Nicht verbunden';
            if(btn){btn.textContent='Verbinden';btn.style.borderColor='var(--border)';btn.style.color='var(--white)';}
        }
    });
}
async function toggleOAuthService(service){
    const user=MZDEV.currentUser;if(!user){MZDEV.showToast('Nicht angemeldet','error');return;}
    const svcs=user.connectedServices||[];
    if(svcs.includes(service)){
        // Disconnect
        const idx=svcs.indexOf(service);svcs.splice(idx,1);
        user.connectedServices=svcs;
        MZDEV.setSession(user,MZDEV.isAdmin);
        MZDEV.saveProfile(user.username,{connectedServices:svcs});
        try{await API.request('/oauth/disconnect','POST',{provider:service});}catch(e){}
        MZDEV.showToast(service.charAt(0).toUpperCase()+service.slice(1)+' getrennt','success');
    } else {
        // Connect via OAuth
        API.oauthLogin(service);
    }
    loadOAuthServices();
    updateUI();
}
function clearAllData() {
    if(!confirm('Wirklich ALLE lokalen Daten l\\u00f6schen? Das kann nicht r\\u00fcckg\\u00e4ngig gemacht werden.')) return;
    const keys = Object.keys(localStorage).filter(k => k.startsWith('mzdev_'));
    keys.forEach(k => localStorage.removeItem(k));
    MZDEV.showToast('Alle Daten gel\\u00f6scht', 'success');
    setTimeout(() => location.reload(), 1000);
}
function resetTheme() {
    MZDEV.saveTheme(THEME_PRESETS.default);
    document.getElementById('colorAccent').value = '#f5e642';
    document.getElementById('colorBg').value = '#000000';
    document.getElementById('colorSurface').value = '#111111';
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
    document.querySelector('.theme-card').classList.add('active');
    MZDEV.showToast('Theme zur\\u00fcckgesetzt', 'success');
}
// INIT
function loadSettings() {
    const s = MZDEV.settings;
    document.getElementById('settAnimations').checked = s.animations !== false;
    document.getElementById('settNotifications').checked = s.notifications !== false;
    document.getElementById('settNewArticleNotif').checked = !!s.newArticleNotif;
    document.getElementById('settPublicProfile').checked = s.publicProfile !== false;
    document.getElementById('settFontSize').value = s.fontSize || 'normal';
    applyFontSize(s.fontSize);
    if(document.getElementById('settLanguage')) document.getElementById('settLanguage').value = s.language || 'de';
    if(document.getElementById('settDateFormat')) document.getElementById('settDateFormat').value = s.dateFormat || 'DD.MM.YYYY';
    if(document.getElementById('settReducedMotion')) document.getElementById('settReducedMotion').checked = !!s.reducedMotion;
    if(document.getElementById('settHighContrast')) document.getElementById('settHighContrast').checked = !!s.highContrast;
    if(document.getElementById('settDefaultCursor')) document.getElementById('settDefaultCursor').checked = !!s.defaultCursor;
    const t = MZDEV.theme;
    document.getElementById('colorAccent').value = t.accent;
    document.getElementById('colorBg').value = t.bg;
    document.getElementById('colorSurface').value = t.surface;
    if(s.reducedMotion) document.body.classList.add('reduced-motion');
    if(s.highContrast) document.body.classList.add('high-contrast');
    if(s.defaultCursor) { document.body.style.cursor='auto'; if(cursor)cursor.style.display='none'; }
}
MZDEV.applyTheme();
updateUI();
loadSettings();
</script>"""
    content = content[:script_start] + NEW_SCRIPT + '\n</body>\n</html>\n'
with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print("settings.html transformed OK")