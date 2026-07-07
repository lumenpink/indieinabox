<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class MicropubClientHandler
 */
class MicropubClientHandler
{
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;

    /**
     * Method __construct
     * @param \Indieinabox\Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Method handle
     * @return void
     */
    public function handle(): void
    {
        // Require authentication
        if (empty($_SESSION['admin_authenticated'])) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            header('Location: ' . $fqdn . '/admin/config');
            return;
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        
        $activeTab = 'micropub';
        $adminLayoutPath = dirname(__DIR__) . '/resources/views/admin_layout.php';
        
        ob_start();
        ?>
            <style>
                :root {
                    --bg-gradient: transparent;
                    --card-bg: rgba(17, 24, 39, 0.7);
                    --accent: #eccb00;
                    --accent-glow: rgba(236, 203, 0, 0.35);
                    --text-primary: #f9fafb;
                    --text-secondary: #9ca3af;
                    --border: rgba(255, 255, 255, 0.08);
                    --input-bg: rgba(3, 7, 18, 0.6);
                    --input-focus: rgba(236, 203, 0, 0.15);
                    --error-color: #ef4444;
                    --success-color: #10b981;
                }

                .micropub-wrapper {
                    font-family: 'Outfit', sans-serif;
                    background: transparent;
                    color: var(--text-primary);
                    padding: 2rem 1.5rem;
                    box-sizing: border-box;
                    display: flex;
                    justify-content: center;
                }

                .micropub-wrapper .container {
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    background: var(--card-bg);
                    border: 1px solid var(--border);
                    border-radius: 28px;
                    padding: 3rem;
                    max-width: 640px;
                    width: 100%;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7),
                                0 0 50px rgba(236, 203, 0, 0.03);
                    position: relative;
                    overflow: hidden;
                    transition: transform 0.3s ease, box-shadow 0.3s ease;
                }

                .micropub-wrapper .container::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #eccb00, #f59e0b);
                }

                .micropub-wrapper h1 {
                    font-size: 2.2rem;
                    font-weight: 800;
                    margin: 0 0 0.5rem 0;
                    background: linear-gradient(to right, #ffffff, #d1d5db);
                    background-clip: text;
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    letter-spacing: -0.02em;
                }

                .micropub-wrapper .subtitle {
                    color: var(--text-secondary);
                    font-size: 1.05rem;
                    margin: 0 0 2rem 0;
                    line-height: 1.6;
                }

                .micropub-wrapper .form-group {
                    margin-bottom: 1.5rem;
                }

                .micropub-wrapper label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 0.6rem;
                    color: #d1d5db;
                    font-size: 0.95rem;
                    letter-spacing: 0.01em;
                }

                .micropub-wrapper input[type="text"],
                .micropub-wrapper input[type="file"],
                .micropub-wrapper select,
                .micropub-wrapper textarea {
                    width: 100%;
                    background: var(--input-bg);
                    border: 1px solid var(--border);
                    color: var(--text-primary);
                    padding: 14px 16px;
                    border-radius: 12px;
                    font-family: inherit;
                    font-size: 1rem;
                    box-sizing: border-box;
                    transition: all 0.2s ease;
                }

                .micropub-wrapper textarea {
                    min-height: 150px;
                    resize: vertical;
                    line-height: 1.5;
                }

                .micropub-wrapper input:focus,
                .micropub-wrapper select:focus,
                .micropub-wrapper textarea:focus {
                    outline: none;
                    border-color: var(--accent);
                    background: var(--input-focus);
                    box-shadow: 0 0 0 4px rgba(236, 203, 0, 0.1);
                }

                .micropub-wrapper button {
                    background: var(--accent);
                    color: #000;
                    border: none;
                    padding: 16px 24px;
                    border-radius: 12px;
                    font-family: inherit;
                    font-weight: 700;
                    font-size: 1.05rem;
                    cursor: pointer;
                    width: 100%;
                    margin-top: 1rem;
                    transition: all 0.2s ease;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .micropub-wrapper button:hover {
                    background: #f59e0b;
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px -10px var(--accent-glow);
                }

                .micropub-wrapper button:active {
                    transform: translateY(0);
                }

                .micropub-wrapper #status {
                    margin-top: 1.5rem;
                    padding: 14px;
                    border-radius: 12px;
                    font-weight: 600;
                    text-align: center;
                    display: none;
                    animation: fadeIn 0.3s ease;
                }

                .micropub-wrapper .status-success {
                    background: rgba(16, 185, 129, 0.1);
                    color: var(--success-color);
                    border: 1px solid rgba(16, 185, 129, 0.2);
                }

                .micropub-wrapper .status-error {
                    background: rgba(239, 68, 68, 0.1);
                    color: var(--error-color);
                    border: 1px solid rgba(239, 68, 68, 0.2);
                }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(-5px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .micropub-wrapper #auth-section { display: block; }
                .micropub-wrapper #compose-section { display: none; }
            </style>
        <div class="micropub-wrapper">
            <div class="container">
                <h1>Publish</h1>
                
                <div id="auth-section">
                    <p class="subtitle">Authenticate with <strong><?= htmlspecialchars($fqdn) ?></strong> to publish content.</p>
                    <button id="btn-login">Login with IndieAuth</button>
                </div>

                <div id="compose-section">
                    <p class="subtitle">Compose a new post to <strong><?= htmlspecialchars($fqdn) ?></strong></p>
                    <form id="post-form">
                        <div class="form-group">
                            <label for="post-type">Post Type</label>
                            <select id="post-type" name="post-type">
                                <option value="article">Article</option>
                                <option value="note">Note</option>
                                <option value="photo">Photo</option>
                            </select>
                        </div>
                        <div class="form-group" id="group-title">
                            <label for="post-title">Title (Optional)</label>
                            <input type="text" id="post-title" name="name" placeholder="Leave blank for a quick note">
                        </div>
                        <div class="form-group" id="group-content">
                            <label for="post-content" id="label-content">Content (Markdown)</label>
                            <textarea id="post-content" name="content" placeholder="What's on your mind?"></textarea>
                        </div>
                        <div class="form-group" id="group-tags">
                            <label for="post-tags">Tags (Comma separated)</label>
                            <input type="text" id="post-tags" name="category" placeholder="tech, thoughts">
                        </div>
                        <div class="form-group" id="group-photo">
                            <label for="post-photo">Photos (Optional)</label>
                            <input type="file" id="post-photo" accept="image/*" multiple>
                        </div>
                        <div class="form-group" style="display:none;" id="upload-progress">
                            <small style="color:var(--accent);">Uploading photo...</small>
                        </div>
                        <div id="photo-gallery" style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.5rem;"></div>
                        <button type="submit" id="btn-publish">Publish Post</button>
                        <div id="status"></div>
                    </form>
                </div>
            </div>

            <script>
                const fqdn = <?= json_encode($fqdn) ?>;
                const micropubUrl = fqdn + '/micropub';
                const authUrl = fqdn + '/auth';
                const tokenUrl = fqdn + '/token';
                const redirectUri = window.location.origin + window.location.pathname;
                
                const authSection = document.getElementById('auth-section');
                const composeSection = document.getElementById('compose-section');
                const statusDiv = document.getElementById('status');
                
                let accessToken = localStorage.getItem('ia_access_token');

                // Generate random string for PKCE and State
                function generateRandomString(length) {
                    const array = new Uint8Array(length);
                    window.crypto.getRandomValues(array);
                    return Array.from(array, dec => ('0' + dec.toString(16)).substr(-2)).join('');
                }

                // SHA256 base64url encoded for PKCE
                async function generateCodeChallenge(codeVerifier) {
                    const encoder = new TextEncoder();
                    const data = encoder.encode(codeVerifier);
                    const hash = await window.crypto.subtle.digest('SHA-256', data);
                    return btoa(String.fromCharCode.apply(null, new Uint8Array(hash)))
                        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                }

                document.getElementById('btn-login').addEventListener('click', async () => {
                    const state = generateRandomString(16);
                    const codeVerifier = generateRandomString(32);
                    const codeChallenge = await generateCodeChallenge(codeVerifier);
                    
                    sessionStorage.setItem('ia_state', state);
                    sessionStorage.setItem('ia_code_verifier', codeVerifier);
                    
                    const params = new URLSearchParams({
                        me: fqdn,
                        client_id: fqdn + '/micropub/client',
                        redirect_uri: redirectUri,
                        response_type: 'code',
                        scope: 'create media',
                        state: state,
                        code_challenge: codeChallenge,
                        code_challenge_method: 'S256'
                    });
                    
                    window.location.href = authUrl + '?' + params.toString();
                });

                // Check for auth callback
                window.addEventListener('load', async () => {
                    const urlParams = new URLSearchParams(window.location.search);
                    const code = urlParams.get('code');
                    const state = urlParams.get('state');
                    
                    if (code && state) {
                        const savedState = sessionStorage.getItem('ia_state');
                        const codeVerifier = sessionStorage.getItem('ia_code_verifier');
                        
                        if (state === savedState) {
                            // Exchange code for token
                            const body = new URLSearchParams({
                                grant_type: 'authorization_code',
                                code: code,
                                client_id: fqdn + '/micropub/client',
                                redirect_uri: redirectUri,
                                code_verifier: codeVerifier
                            });
                            
                            try {
                                const response = await fetch(tokenUrl, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: body.toString()
                                });
                                
                                const data = await response.json();
                                if (data.access_token) {
                                    localStorage.setItem('ia_access_token', data.access_token);
                                    window.history.replaceState({}, document.title, redirectUri);
                                    accessToken = data.access_token;
                                    showCompose();
                                }
                            } catch (e) {
                                alert("Failed to exchange token.");
                            }
                        }
                    } else if (accessToken) {
                        showCompose();
                    }
                });

                function showCompose() {
                    authSection.style.display = 'none';
                    composeSection.style.display = 'block';
                }

                // --- Post Type UI Logic ---
                const postTypeSelect = document.getElementById('post-type');
                const groupTitle = document.getElementById('group-title');
                const groupContent = document.getElementById('group-content');
                const labelContent = document.getElementById('label-content');
                const groupPhoto = document.getElementById('group-photo');

                function updateUIForPostType() {
                    const type = postTypeSelect.value;
                    if (type === 'article') {
                        groupTitle.style.display = 'flex';
                        groupPhoto.style.display = 'flex';
                        labelContent.textContent = 'Content (Markdown)';
                    } else if (type === 'note') {
                        groupTitle.style.display = 'none';
                        groupPhoto.style.display = 'none';
                        labelContent.textContent = 'Note Content';
                    } else if (type === 'photo') {
                        groupTitle.style.display = 'none';
                        groupPhoto.style.display = 'flex';
                        labelContent.textContent = 'Caption (Optional)';
                    }
                    renderGallery(); // Re-render to show/hide Markdown snippet
                }
                
                postTypeSelect.addEventListener('change', updateUIForPostType);

                // --- Upload & Gallery Logic ---
                const photoInput = document.getElementById('post-photo');
                const progressDiv = document.getElementById('upload-progress');
                const galleryDiv = document.getElementById('photo-gallery');
                let uploadedPhotos = [];

                updateUIForPostType(); // Init

                function renderGallery() {
                    galleryDiv.innerHTML = '';
                    const type = postTypeSelect.value;
                    
                    uploadedPhotos.forEach((url, index) => {
                        const itemDiv = document.createElement('div');
                        itemDiv.style.cssText = 'display:flex; flex-direction:column; background:rgba(0,0,0,0.2); padding:0.5rem; border-radius:8px; border:1px solid var(--border);';
                        
                        const topRow = document.createElement('div');
                        topRow.style.cssText = 'display:flex; align-items:center; gap:1rem;';
                        
                        const img = document.createElement('img');
                        img.src = url;
                        img.style.cssText = 'width:60px; height:60px; object-fit:cover; border-radius:4px;';
                        
                        const controls = document.createElement('div');
                        controls.style.cssText = 'display:flex; gap:0.5rem; margin-left:auto;';
                        
                        const btnUp = document.createElement('button');
                        btnUp.type = 'button';
                        btnUp.innerHTML = '⬆️';
                        btnUp.style.cssText = 'padding:0.25rem 0.5rem; font-size:0.8rem; background:rgba(255,255,255,0.1); flex:none;';
                        btnUp.disabled = index === 0;
                        btnUp.onclick = () => movePhoto(index, -1);
                        
                        const btnDown = document.createElement('button');
                        btnDown.type = 'button';
                        btnDown.innerHTML = '⬇️';
                        btnDown.style.cssText = 'padding:0.25rem 0.5rem; font-size:0.8rem; background:rgba(255,255,255,0.1); flex:none;';
                        btnDown.disabled = index === uploadedPhotos.length - 1;
                        btnDown.onclick = () => movePhoto(index, 1);
                        
                        const btnRemove = document.createElement('button');
                        btnRemove.type = 'button';
                        btnRemove.innerHTML = '❌';
                        btnRemove.style.cssText = 'padding:0.25rem 0.5rem; font-size:0.8rem; background:rgba(239, 68, 68, 0.2); flex:none;';
                        btnRemove.onclick = () => removePhoto(index);
                        
                        controls.appendChild(btnUp);
                        controls.appendChild(btnDown);
                        controls.appendChild(btnRemove);
                        
                        topRow.appendChild(img);
                        topRow.appendChild(controls);
                        itemDiv.appendChild(topRow);
                        
                        if (type === 'article') {
                            const mdDiv = document.createElement('div');
                            mdDiv.style.cssText = 'margin-top:0.5rem;';
                            mdDiv.innerHTML = '<small style="color:var(--text-secondary);">Markdown link (click to copy):</small><br><input type="text" readonly value="![](' + url + ')" style="padding:0.4rem;font-size:0.75rem;margin-top:0.25rem;cursor:pointer;background:rgba(0,0,0,0.3);border:1px solid var(--border);color:var(--accent);width:100%;border-radius:6px;" onclick="this.select();document.execCommand(\'copy\');">';
                            itemDiv.appendChild(mdDiv);
                        }
                        
                        galleryDiv.appendChild(itemDiv);
                    });
                }

                function movePhoto(index, dir) {
                    const newIndex = index + dir;
                    if (newIndex >= 0 && newIndex < uploadedPhotos.length) {
                        const temp = uploadedPhotos[index];
                        uploadedPhotos[index] = uploadedPhotos[newIndex];
                        uploadedPhotos[newIndex] = temp;
                        renderGallery();
                    }
                }

                function removePhoto(index) {
                    uploadedPhotos.splice(index, 1);
                    renderGallery();
                }

                photoInput.addEventListener('change', async (e) => {
                    const files = Array.from(e.target.files);
                    if (files.length === 0) return;

                    progressDiv.style.display = 'block';
                    document.getElementById('btn-publish').disabled = true;

                    for (const file of files) {
                        progressDiv.innerHTML = '<small style="color:var(--accent);">Uploading ' + file.name + '...</small>';
                        const formData = new FormData();
                        formData.append('file', file);

                        try {
                            const res = await fetch(micropubUrl + '/media', {
                                method: 'POST',
                                headers: { 'Authorization': 'Bearer ' + accessToken },
                                body: formData
                            });

                            if (res.status === 201) {
                                const location = res.headers.get('Location');
                                uploadedPhotos.push(location);
                            } else {
                                const err = await res.json();
                                alert('Failed to upload ' + file.name + ': ' + (err.error_description || err.error));
                            }
                        } catch(err) {
                            alert('Network error uploading ' + file.name);
                        }
                    }
                    
                    photoInput.value = ''; // clear input
                    progressDiv.style.display = 'none';
                    document.getElementById('btn-publish').disabled = false;
                    renderGallery();
                });

                document.getElementById('post-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const type = postTypeSelect.value;
                    const title = document.getElementById('post-title').value;
                    const content = document.getElementById('post-content').value;
                    const tagsStr = document.getElementById('post-tags').value;
                    
                    const payload = {
                        type: ['h-entry'],
                        properties: {}
                    };
                    
                    if (content.trim()) payload.properties.content = [content.trim()];
                    
                    if (type === 'article' && title.trim()) {
                        payload.properties.name = [title.trim()];
                    }
                    
                    if (tagsStr.trim()) {
                        payload.properties.category = tagsStr.split(',').map(s => s.trim());
                    }
                    
                    if ((type === 'article' || type === 'photo') && uploadedPhotos.length > 0) {
                        payload.properties.photo = uploadedPhotos;
                    }

                    statusDiv.textContent = 'Publishing...';
                    statusDiv.className = '';
                    document.getElementById('btn-publish').disabled = true;

                    try {
                        const response = await fetch(micropubUrl, {
                            method: 'POST',
                            headers: {
                                'Authorization': 'Bearer ' + accessToken,
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });

                        if (response.status === 201 || response.status === 202) {
                            const location = response.headers.get('Location');
                            statusDiv.innerHTML = 'Post published successfully! <a href="' + location + '" target="_blank">View Post</a>';
                            statusDiv.className = 'success';
                            document.getElementById('post-form').reset();
                            uploadedPhotos = [];
                            renderGallery();
                            updateUIForPostType();
                        } else {
                            const err = await response.json();
                            statusDiv.textContent = 'Error: ' + (err.error_description || err.error);
                            statusDiv.className = 'error';
                        }
                    } catch (e) {
                        statusDiv.textContent = 'Network error during publish.';
                        statusDiv.className = 'error';
                    }

                    document.getElementById('btn-publish').disabled = false;
                });
            </script>
        </div>
        <?php
        $content = ob_get_clean();
        if (file_exists($adminLayoutPath)) {
            include $adminLayoutPath;
        } else {
            echo $content;
        }
    }
}
