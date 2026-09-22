// Indie in a Box - Interactive Documentation Engine
document.addEventListener('DOMContentLoaded', () => {
    // 1. Image Lightbox Zoom
    const lightbox = document.createElement('div');
    lightbox.className = 'lightbox-modal';
    lightbox.innerHTML = '<img src="" alt="Enlarged screenshot preview">';
    document.body.appendChild(lightbox);

    const lightboxImg = lightbox.querySelector('img');

    document.querySelectorAll('.screenshot-img-wrap img').forEach(img => {
        img.addEventListener('click', () => {
            lightboxImg.src = img.src;
            lightboxImg.alt = img.alt || 'Screenshot preview';
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    lightbox.addEventListener('click', () => {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox.classList.contains('active')) {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // 2. Code Block Copy to Clipboard
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', async () => {
            const codeBlock = btn.closest('.code-block');
            const codeEl = codeBlock.querySelector('pre');
            if (!codeEl) return;

            const textToCopy = codeEl.innerText.trim();
            try {
                await navigator.clipboard.writeText(textToCopy);
                const originalText = btn.innerText;
                btn.innerText = 'COPIED!';
                btn.style.color = 'var(--neon-green)';
                btn.style.borderColor = 'var(--neon-green)';
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.style.color = '';
                    btn.style.borderColor = '';
                }, 2000);
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
        });
    });

    // 3. Tab Switching
    document.querySelectorAll('.tab-buttons').forEach(group => {
        const buttons = group.querySelectorAll('.tab-btn');
        const container = group.closest('.tabs-container') || group.parentElement;
        const panels = container.querySelectorAll('.tab-panel');

        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-tab');
                buttons.forEach(b => b.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));

                btn.classList.add('active');
                const targetPanel = container.querySelector(`#${targetId}`);
                if (targetPanel) {
                    targetPanel.classList.add('active');
                }
            });
        });
    });

    // 4. Mobile Sidebar Navigation Toggle
    const mobileToggle = document.querySelector('.mobile-nav-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            mobileToggle.innerText = sidebar.classList.contains('open') ? '✕ CLOSE' : '☰ MENU';
        });

        // Close when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== mobileToggle) {
                sidebar.classList.remove('open');
                mobileToggle.innerText = '☰ MENU';
            }
        });
    }
});
