<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

/**
 * Presentation view component that renders the Micropub web publishing client.
 * Estética: Lambe-Lambe Periférico, Dissidência Travesti & Resistência DIY.
 */
class MicropubClientView
{
    /**
     * Renders the Micropub web posting client interface wrapped in the admin layout.
     *
     * @param string $fqdn Fully qualified domain name of the site.
     * @return string Rendered HTML.
     */
    public static function render(string $fqdn): string
    {
        $fqdnClean = rtrim($fqdn, '/');
        ob_start();
        ?>
        <style>
            .micropub-wrapper {
                max-width: 820px;
                margin: 0 auto;
                padding: 2.5rem 1.5rem;
                font-family: 'Outfit', sans-serif;
                color: var(--text-light, #f4efe6);
            }

            .pasteboard-card {
                background: var(--lambe-surface, #1e1f25);
                border: 3px solid #000000;
                box-shadow: 6px 6px 0px #000000;
                padding: 2.5rem;
                position: relative;
            }

            .pasteboard-card::before {
                content: '';
                display: block;
                height: 6px;
                width: 100%;
                background: repeating-linear-gradient(45deg, #ffe600, #ffe600 10px, #000000 10px, #000000 20px);
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
            }

            .pasteboard-header {
                border-bottom: 2px solid #000000;
                padding-bottom: 1.25rem;
                margin-bottom: 2rem;
            }

            .pasteboard-header h1 {
                margin: 0;
                font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
                font-size: 2.4rem;
                letter-spacing: 0.05em;
                color: var(--lambe-paper);
                text-transform: uppercase;
            }

            .pasteboard-header .subtitle {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.82rem;
                color: var(--tint-yellow, #ffe600);
                margin: 6px 0 0 0;
                letter-spacing: 0.05em;
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-group label {
                display: block;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.8rem;
                font-weight: 800;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.08em;
                margin-bottom: 6px;
            }

            .type-pill-group {
                display: flex;
                gap: 8px;
                margin-bottom: 1.5rem;
                flex-wrap: wrap;
            }

            .type-pill {
                padding: 8px 16px;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.85rem;
                font-weight: 800;
                text-transform: uppercase;
                border: 2px solid #000;
                background: #141518;
                color: var(--text-muted);
                cursor: pointer;
                box-shadow: 2px 2px 0 #000;
                transition: all 0.1s;
            }

            .type-pill:hover {
                background: var(--lambe-paper);
                color: #000;
            }

            .type-pill.active {
                background: var(--tint-yellow);
                color: #000;
                border-color: #000;
                box-shadow: 3px 3px 0 #000;
                transform: translate(-1px, -1px);
            }

            #photo-gallery {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }

            .photo-item {
                display: flex;
                flex-direction: column;
                background: #141518;
                border: 2px solid #000;
                padding: 0.75rem;
                box-shadow: 2px 2px 0 #000;
            }

            .btn-publish {
                width: 100%;
                padding: 16px;
                font-family: 'Archivo Black', sans-serif;
                font-size: 1.1rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                background: var(--tint-magenta, #ff007f);
                color: #000000;
                border: 2px solid #000000;
                box-shadow: 5px 5px 0px #000000;
                cursor: pointer;
                transition: all 0.1s ease;
                margin-top: 1rem;
            }

            .btn-publish:hover {
                background: #ff2a93;
                transform: translate(-2px, -2px);
                box-shadow: 7px 7px 0px #000000;
            }

            .btn-publish:active {
                transform: translate(3px, 3px);
                box-shadow: 1px 1px 0px #000000;
            }

            .btn-publish:disabled {
                opacity: 0.6;
                cursor: wait;
                transform: none;
            }

            #status {
                margin-top: 1.5rem;
                padding: 14px 18px;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.9rem;
                font-weight: 700;
                border: 2px solid #000;
                box-shadow: 3px 3px 0 #000;
                display: none;
            }

            .status-success {
                background: var(--tint-green, #00ff66);
                color: #000;
            }

            .status-error {
                background: var(--tint-red, #ff2244);
                color: #fff;
            }
        </style>

        <div class="micropub-wrapper">
            <div class="pasteboard-card">
                <span class="tape-label">PRANCHETA DE SERIGRAFIA</span>

                <header class="pasteboard-header">
                    <h1>Indieinabox Publisher</h1>
                    <p class="subtitle">// DISPARAR POST PARA: <strong><?= htmlspecialchars($fqdnClean) ?></strong></p>
                </header>

                <div id="compose-section">
                    <div class="type-pill-group">
                        <button type="button" class="type-pill active" onclick="selectPostType('note')">📝 NOTA RÁPIDA</button>
                        <button type="button" class="type-pill" onclick="selectPostType('article')">📰 ARTIGO COMPLETO</button>
                        <button type="button" class="type-pill" onclick="selectPostType('photo')">📷 FOTO / MÍDIA</button>
                    </div>

                    <form id="post-form">
                        <select id="post-type" name="post-type" style="display: none;">
                            <option value="note">Note</option>
                            <option value="article">Article</option>
                            <option value="photo">Photo</option>
                        </select>

                        <div class="form-group" id="group-title" style="display: none;">
                            <label for="post-title">TÍTULO DO ARTIGO</label>
                            <input type="text" id="post-title" name="name" placeholder="Digite o título da publicação..." style="width: 100%;">
                        </div>

                        <div class="form-group" id="group-content">
                            <label for="post-content" id="label-content">CONTEÚDO (MARKDOWN)</label>
                            <textarea id="post-content" name="content" placeholder="O que está acontecendo na trincheira?" style="width: 100%; min-height: 160px; line-height: 1.6;"></textarea>
                        </div>

                        <div class="form-group" id="group-tags">
                            <label for="post-tags">CATEGORIAS / TAGS (SEPARADAS POR VÍRGULA)</label>
                            <input type="text" id="post-tags" name="category" placeholder="resistencia, fanzine, indieweb" style="width: 100%;">
                        </div>

                        <div class="form-group" id="group-photo">
                            <label for="post-photo">FOTOS & ANEXOS DE MÍDIA</label>
                            <input type="file" id="post-photo" accept="image/*" multiple style="width: 100%;">
                        </div>

                        <div class="form-group" style="display:none;" id="upload-progress">
                            <span class="badge-stamp badge-yellow">ENVIANDO IMAGEM PARA SERVIDOR... ⏳</span>
                        </div>

                        <div id="photo-gallery"></div>

                        <button type="submit" id="btn-publish" class="btn-publish">ESTAMPAR POST // PUBLICAR</button>
                        <div id="status"></div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const fqdn = <?= json_encode($fqdnClean) ?>;
            const micropubUrl = fqdn + '/micropub';
            
            const composeSection = document.getElementById('compose-section');
            const statusDiv = document.getElementById('status');

            // --- Post Type UI Logic ---
            const postTypeSelect = document.getElementById('post-type');
            const groupTitle = document.getElementById('group-title');
            const groupContent = document.getElementById('group-content');
            const labelContent = document.getElementById('label-content');
            const groupPhoto = document.getElementById('group-photo');

            function selectPostType(type) {
                postTypeSelect.value = type;
                document.querySelectorAll('.type-pill').forEach(btn => {
                    btn.classList.remove('active');
                });
                if (type === 'note') document.querySelectorAll('.type-pill')[0].classList.add('active');
                if (type === 'article') document.querySelectorAll('.type-pill')[1].classList.add('active');
                if (type === 'photo') document.querySelectorAll('.type-pill')[2].classList.add('active');
                updateUIForPostType();
            }

            function updateUIForPostType() {
                const type = postTypeSelect.value;
                if (type === 'article') {
                    groupTitle.style.display = 'block';
                    groupPhoto.style.display = 'block';
                    labelContent.textContent = 'CONTEÚDO (MARKDOWN / HTML)';
                } else if (type === 'note') {
                    groupTitle.style.display = 'none';
                    groupPhoto.style.display = 'none';
                    labelContent.textContent = 'CONTEÚDO DA NOTA';
                } else if (type === 'photo') {
                    groupTitle.style.display = 'none';
                    groupPhoto.style.display = 'block';
                    labelContent.textContent = 'LEGENDA DA FOTO (OPCIONAL)';
                }
                renderGallery();
            }
            
            postTypeSelect.addEventListener('change', updateUIForPostType);

            // --- Upload & Gallery Logic ---
            const photoInput = document.getElementById('post-photo');
            const progressDiv = document.getElementById('upload-progress');
            const galleryDiv = document.getElementById('photo-gallery');
            let uploadedPhotos = [];

            updateUIForPostType();

            function renderGallery() {
                galleryDiv.innerHTML = '';
                const type = postTypeSelect.value;
                
                uploadedPhotos.forEach((url, index) => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'photo-item';
                    
                    const topRow = document.createElement('div');
                    topRow.style.cssText = 'display:flex; align-items:center; gap:1rem;';
                    
                    const img = document.createElement('img');
                    img.src = url;
                    img.style.cssText = 'width:60px; height:60px; object-fit:cover; border:2px solid #000;';
                    
                    const controls = document.createElement('div');
                    controls.style.cssText = 'display:flex; gap:0.5rem; margin-left:auto;';
                    
                    const btnUp = document.createElement('button');
                    btnUp.type = 'button';
                    btnUp.className = 'btn-stamp';
                    btnUp.innerHTML = '↑';
                    btnUp.style.cssText = 'padding:4px 8px; font-size:0.8rem;';
                    btnUp.disabled = index === 0;
                    btnUp.onclick = () => movePhoto(index, -1);
                    
                    const btnDown = document.createElement('button');
                    btnDown.type = 'button';
                    btnDown.className = 'btn-stamp';
                    btnDown.innerHTML = '↓';
                    btnDown.style.cssText = 'padding:4px 8px; font-size:0.8rem;';
                    btnDown.disabled = index === uploadedPhotos.length - 1;
                    btnDown.onclick = () => movePhoto(index, 1);

                    const btnRemove = document.createElement('button');
                    btnRemove.type = 'button';
                    btnRemove.className = 'btn-stamp btn-stamp-red';
                    btnRemove.innerHTML = '✕';
                    btnRemove.style.cssText = 'padding:4px 8px; font-size:0.8rem;';
                    btnRemove.onclick = () => removePhoto(index);

                    controls.appendChild(btnUp);
                    controls.appendChild(btnDown);
                    controls.appendChild(btnRemove);

                    topRow.appendChild(img);
                    topRow.appendChild(controls);
                    itemDiv.appendChild(topRow);

                    if (type === 'article') {
                        const snippetRow = document.createElement('div');
                        snippetRow.style.cssText = 'margin-top:0.6rem; font-family:"JetBrains Mono", monospace; font-size:0.78rem; color:var(--text-muted); display:flex; gap:0.5rem; align-items:center;';
                        const snippet = '![imagem](' + url + ')';
                        snippetRow.innerHTML = '<span>Markdown: <code style="color:var(--tint-yellow);">' + snippet + '</code></span>';
                        const btnCopy = document.createElement('button');
                        btnCopy.type = 'button';
                        btnCopy.className = 'btn-stamp';
                        btnCopy.innerText = 'Copiar';
                        btnCopy.style.cssText = 'padding:2px 8px; font-size:0.7rem;';
                        btnCopy.onclick = () => {
                            navigator.clipboard.writeText(snippet);
                            btnCopy.innerText = 'Copiado!';
                            setTimeout(() => btnCopy.innerText = 'Copiar', 2000);
                        };
                        snippetRow.appendChild(btnCopy);
                        itemDiv.appendChild(snippetRow);
                    }

                    galleryDiv.appendChild(itemDiv);
                });
            }

            function movePhoto(index, offset) {
                const target = index + offset;
                if (target < 0 || target >= uploadedPhotos.length) return;
                const temp = uploadedPhotos[index];
                uploadedPhotos[index] = uploadedPhotos[target];
                uploadedPhotos[target] = temp;
                renderGallery();
            }

            function removePhoto(index) {
                uploadedPhotos.splice(index, 1);
                renderGallery();
            }

            photoInput.addEventListener('change', async () => {
                if (!photoInput.files.length) return;
                
                progressDiv.style.display = 'block';
                document.getElementById('btn-publish').disabled = true;

                for (let i = 0; i < photoInput.files.length; i++) {
                    const file = photoInput.files[i];
                    const formData = new FormData();
                    formData.append('file', file);

                    try {
                        const response = await fetch(fqdn + '/micropub/media', {
                            method: 'POST',
                            credentials: 'include',
                            body: formData
                        });

                        if (response.status === 201) {
                            const location = response.headers.get('Location');
                            if (location) {
                                uploadedPhotos.push(location);
                            }
                        } else {
                            const err = await response.json().catch(() => ({}));
                            alert('Falha no upload de ' + file.name + ': ' + (err.error_description || err.error || 'Erro no servidor'));
                        }
                    } catch (e) {
                        alert('Erro de conexão ao enviar ' + file.name);
                    }
                }
                
                photoInput.value = '';
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

                statusDiv.textContent = 'ESTAMPANDO POST NO FEED...';
                statusDiv.className = 'status-success';
                statusDiv.style.display = 'block';
                document.getElementById('btn-publish').disabled = true;

                try {
                    const response = await fetch(micropubUrl, {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.status === 201 || response.status === 202) {
                        const location = response.headers.get('Location');
                        statusDiv.innerHTML = '✓ POST PUBLICADO COM SUCESSO! <a href="' + location + '" target="_blank" style="color:#000; text-decoration:underline; font-weight:800; margin-left:10px;">[VER POST ↗]</a>';
                        statusDiv.className = 'status-success';
                        statusDiv.style.display = 'block';
                        document.getElementById('post-form').reset();
                        uploadedPhotos = [];
                        renderGallery();
                        updateUIForPostType();
                    } else {
                        const err = await response.json().catch(() => ({}));
                        statusDiv.textContent = 'Erro: ' + (err.error_description || err.error || 'Falha ao publicar');
                        statusDiv.className = 'status-error';
                        statusDiv.style.display = 'block';
                    }
                } catch (e) {
                    statusDiv.textContent = 'Erro de rede durante publicação.';
                    statusDiv.className = 'status-error';
                    statusDiv.style.display = 'block';
                }

                document.getElementById('btn-publish').disabled = false;
            });
        </script>
        <?php
        $inner = (string) ob_get_clean();
        return AdminLayoutView::render($inner, 'micropub', $fqdnClean);
    }
}
