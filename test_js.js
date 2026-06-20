                const fqdn = 'http://localhost';
                const micropubUrl = fqdn + '/micropub';
                const authUrl = fqdn + '/auth';
                const tokenUrl = fqdn + '/token';
                const redirectUri = 'http://localhost';
                
                const authSection = {};
                const composeSection = {};
                const statusDiv = {};
                
                let accessToken = '123';

                // Generate random string for PKCE and State
                function generateRandomString(length) {
                    return '';
                }

                // SHA256 base64url encoded for PKCE
                async function generateCodeChallenge(codeVerifier) {
                    return '';
                }

                function showCompose() {
                }

                // --- Post Type UI Logic ---
                const postTypeSelect = {};
                const groupTitle = {};
                const groupContent = {};
                const labelContent = {};
                const groupPhoto = {};

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
                
                // postTypeSelect.addEventListener('change', updateUIForPostType);
                // updateUIForPostType(); // Init

                // --- Upload & Gallery Logic ---
                const photoInput = {};
                const progressDiv = {};
                const galleryDiv = {};
                let uploadedPhotos = [];

                function renderGallery() {
                    const type = postTypeSelect.value;
                    
                    uploadedPhotos.forEach((url, index) => {
                        if (type === 'article') {
                            const mdDiv = {};
                            mdDiv.innerHTML = '<small style="color:var(--text-secondary);">Markdown link (click to copy):</small><br><input type="text" readonly value="![](' + url + ')" style="padding:0.4rem;font-size:0.75rem;margin-top:0.25rem;cursor:pointer;background:rgba(0,0,0,0.3);border:1px solid var(--border);color:var(--accent);width:100%;border-radius:6px;" onclick="this.select();document.execCommand(\'copy\');">';
                        }
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
