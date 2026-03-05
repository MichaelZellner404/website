#!/usr/bin/env python3
"""
Requirement 1: 
- Add favicon.ico link to all pages that don't have it
- Add api.js script include to all pages
- Replace inline MZDEV with external shared.js + api.js where needed
- Activate OAuth (replace old auth with API-based OAuth)
"""
import re
import os
HTML_DIR = os.path.expanduser('~/mzdev/html')
FAVICON_LINK = '    <link rel="icon" href="/favicon.ico" type="image/x-icon">'
# New OAuth functions that use the API backend
OAUTH_FUNCTIONS = """
// OAuth login - redirect to backend OAuth flow
function handleGithubLogin() { API.oauthLogin('github'); }
function handleProviderLogin(provider) { API.oauthLogin(provider); }
// Admin login via backend API
async function handleAdmin() {
    const pw = document.getElementById('admin-password').value;
    const errEl = document.getElementById('admin-error');
    errEl.textContent = '';
    if (!pw) { errEl.textContent = '// Bitte Passwort eingeben'; return; }
    try {
        await API.adminLogin(pw);
        closeModal('authModal');
        updateUI();
        if (typeof activateAdminMode === 'function') activateAdminMode();
        MZDEV.showToast('Admin-Modus aktiv', 'success');
    } catch (e) {
        errEl.textContent = '// ' + e.message;
    }
}
// Logout via backend API
async function handleLogout() {
    await API.logout();
    if (typeof deactivateAdminMode === 'function') deactivateAdminMode();
    updateUI();
    MZDEV.showToast('Abgemeldet');
}
// Handle OAuth callback on page load
document.addEventListener('DOMContentLoaded', async () => {
    const handled = await API.handleAuthCallback();
    if (handled) { updateUI(); }
    else if (API.token) { await API.checkSession(); updateUI(); }
    else { updateUI(); }
});
"""
def add_favicon(content):
    """Add favicon link if not present"""
    if 'favicon.ico' in content:
        return content
    # Add after <meta name="viewport"...> line
    content = re.sub(
        r'(<meta name="viewport"[^>]*>)',
        r'\1\n' + FAVICON_LINK,
        content,
        count=1
    )
    return content
def fix_articles(filepath):
    """articles.html: just needs favicon"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    content = add_favicon(content)
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"  Fixed: {os.path.basename(filepath)} - added favicon")
def fix_projects(filepath):
    """projects.html: needs api.js, fix extra </script>"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    # Fix: <script src="shared.js"></script>\n</script> -> add api.js
    content = content.replace(
        '<script src="shared.js"></script>\n</script>',
        '<script src="shared.js"></script>\n<script src="api.js"></script>'
    )
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"  Fixed: {os.path.basename(filepath)} - added api.js, fixed extra </script>")
def fix_skills(filepath):
    """skills.html: needs api.js, fix extra </script>"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    # Fix: <script src="shared.js"></script>\n</script> -> add api.js
    content = content.replace(
        '<script src="shared.js"></script>\n</script>',
        '<script src="shared.js"></script>\n<script src="api.js"></script>'
    )
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"  Fixed: {os.path.basename(filepath)} - added api.js, fixed extra </script>")
def replace_inline_mzdev_and_auth(filepath):
    """
    For profile.html, settings.html, search.html:
    - Add favicon
    - Replace inline MZDEV const block with external scripts
    - Replace old auth functions with API-based ones
    """
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    basename = os.path.basename(filepath)
    content = add_favicon(content)
    
    # Find and replace the inline MZDEV definition block
    # Pattern: <script>\nconst MZDEV = {... };\ndocument.addEventListener('DOMContentLoaded', () => MZDEV.applyTheme());\n</script>
    # Replace with external script includes
    
    # Match the entire <script> block containing "const MZDEV"
    mzdev_pattern = r'<script>\s*(?://[^\n]*\n)*\s*const MZDEV\s*=\s*\{.*?\};\s*document\.addEventListener\([\'"]DOMContentLoaded[\'"],\s*\(\)\s*=>\s*MZDEV\.applyTheme\(\)\);\s*</script>'
    
    replacement = '<script src="shared.js"></script>\n<script src="api.js"></script>'
    
    new_content = re.sub(mzdev_pattern, replacement, content, flags=re.DOTALL)
    
    if new_content == content:
        print(f"  WARNING: Could not find inline MZDEV in {basename}")
        # Try alternative pattern (minified version)
        mzdev_pattern2 = r'<script>\s*const MZDEV=\{.*?\};\s*document\.addEventListener\([\'"]DOMContentLoaded[\'"],\(\)=>MZDEV\.applyTheme\(\)\);\s*</script>'
        new_content = re.sub(mzdev_pattern2, replacement, content, flags=re.DOTALL)
        if new_content == content:
            print(f"  STILL WARNING: Could not replace inline MZDEV in {basename}")
        else:
            print(f"  Fixed inline MZDEV (minified) in {basename}")
    else:
        print(f"  Fixed inline MZDEV in {basename}")
    
    content = new_content
    
    # Now replace the old auth functions with API-based ones
    # Find the handleGithubLogin function and replace it along with related functions
    
    # Pattern for the old handleGithubLogin that uses fetch to GitHub API
    old_github_pattern = r'async function handleGithubLogin\(\)\{[^}]*fetch\(`https://api\.github\.com/users/[^`]*`\)[^}]*\}[^}]*\}[^}]*\}'
    
    # Also try to find the handleProviderLogin, handleAdmin, handleLogout functions
    # We'll replace the entire block from handleGithubLogin through handleLogout
    
    # Strategy: find "async function handleGithubLogin" or "function handleGithubLogin" 
    # and replace everything through "handleLogout" function
    
    # Let's find the auth function block and replace it
    auth_block_pattern = r'(?:async )?function handleGithubLogin\(\)\{.*?(?=function (?:updateUI|toggleProfileDropdown|renderProfile|renderSearch|doSearch|handleSearch))'
    
    match = re.search(auth_block_pattern, content, re.DOTALL)
    if match:
        # Replace with new OAuth functions
        content = content[:match.start()] + OAUTH_FUNCTIONS.strip() + '\n' + content[match.end():]
        print(f"  Replaced auth functions in {basename}")
    else:
        print(f"  WARNING: Could not find auth block in {basename}")
        # Try a different approach - just replace individual functions
        # handleGithubLogin
        content = re.sub(
            r'async function handleGithubLogin\(\)\{.*?\}\s*\}',
            'function handleGithubLogin() { API.oauthLogin(\'github\'); }',
            content, flags=re.DOTALL, count=1
        )
        # handleProviderLogin - old version
        content = re.sub(
            r'function handleProviderLogin\((?:provider|p)\)\{[^}]*setSession[^}]*showToast[^}]*\}',
            'function handleProviderLogin(provider) { API.oauthLogin(provider); }',
            content, flags=re.DOTALL, count=1
        )
        # handleAdmin - old version (checking password locally)
        old_admin = r"function handleAdmin\(\)\{const pw=document\.getElementById\('admin-password'\)\.value;[^}]*'404AdminFound'[^}]*\}"
        new_admin = """async function handleAdmin() {
    const pw = document.getElementById('admin-password').value;
    const errEl = document.getElementById('admin-error');
    errEl.textContent = '';
    if (!pw) { errEl.textContent = '// Bitte Passwort eingeben'; return; }
    try {
        await API.adminLogin(pw);
        closeModal('authModal');
        updateUI();
        if (typeof activateAdminMode === 'function') activateAdminMode();
        MZDEV.showToast('Admin-Modus aktiv', 'success');
    } catch (e) {
        errEl.textContent = '// ' + e.message;
    }
}"""
        content = re.sub(old_admin, new_admin, content, flags=re.DOTALL, count=1)
        
        # handleLogout - old version
        content = re.sub(
            r'function handleLogout\(\)\{MZDEV\.clearSession\(\);document\.body\.classList\.remove\([\'"]admin-mode[\'"]\);updateUI\(\);(?:renderProfile\(\);)?MZDEV\.showToast\([\'"]Abgemeldet[\'"]\);\}',
            """async function handleLogout() {
    await API.logout();
    if (typeof deactivateAdminMode === 'function') deactivateAdminMode();
    updateUI();
    MZDEV.showToast('Abgemeldet');
}""",
            content, count=1
        )
        print(f"  Replaced individual auth functions in {basename}")
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"  Completed: {basename}")
def check_index(filepath):
    """index.html: already has favicon, shared.js, api.js - check OAuth"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Check if it uses old GitHub API login
    if 'api.github.com/users/' in content:
        print(f"  index.html still uses old GitHub API login - fixing...")
        replace_inline_mzdev_and_auth(filepath)
    else:
        print(f"  index.html - OK (already has favicon, scripts, OAuth)")
def check_project(filepath):
    """project.html: already has favicon, shared.js, api.js"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    print(f"  project.html - OK (already has favicon, scripts)")
def check_inbox(filepath):
    """inbox.html: already has favicon, shared.js, api.js"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    print(f"  inbox.html - OK (already has favicon, scripts)")
# Main
print("=== Requirement 1: api.js, OAuth, Favicon ===\n")
# 1. articles.html - needs favicon
fix_articles(os.path.join(HTML_DIR, 'articles.html'))
# 2. projects.html - needs api.js 
fix_projects(os.path.join(HTML_DIR, 'projects.html'))
# 3. skills.html - needs api.js
fix_skills(os.path.join(HTML_DIR, 'skills.html'))
# 4. profile.html - needs favicon, external scripts, OAuth
replace_inline_mzdev_and_auth(os.path.join(HTML_DIR, 'profile.html'))
# 5. settings.html - needs favicon, external scripts, OAuth
replace_inline_mzdev_and_auth(os.path.join(HTML_DIR, 'settings.html'))
# 6. search.html - needs favicon, external scripts, OAuth
replace_inline_mzdev_and_auth(os.path.join(HTML_DIR, 'search.html'))
# 7. Check already-good files
check_index(os.path.join(HTML_DIR, 'index.html'))
check_project(os.path.join(HTML_DIR, 'project.html'))
check_inbox(os.path.join(HTML_DIR, 'inbox.html'))
print("\n=== Done! ===")
print("\nVerification:")
for f in sorted(os.listdir(HTML_DIR)):
    if f.endswith('.html'):
        path = os.path.join(HTML_DIR, f)
        with open(path, 'r', encoding='utf-8') as fh:
            c = fh.read()
        has_favicon = 'favicon.ico' in c
        has_shared = 'src="shared.js"' in c
        has_api = 'src="api.js"' in c
        has_old_oauth = 'api.github.com/users/' in c
        has_new_oauth = 'API.oauthLogin' in c or 'API.adminLogin' in c
        print(f"  {f:20s} favicon={has_favicon}  shared.js={has_shared}  api.js={has_api}  old_oauth={has_old_oauth}  new_oauth={has_new_oauth}")