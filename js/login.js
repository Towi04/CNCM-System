(function () {
    function initPasswordToggles(root) {
        const scope = root || document;
        scope.querySelectorAll('.btn-toggle-password').forEach((btn) => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            const id = btn.getAttribute('data-target');
            const input = id
                ? document.getElementById(id)
                : btn.closest('.password-input-wrap')?.querySelector('input');
            if (!input) return;

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                btn.classList.toggle('is-visible', !visible);
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', visible);
                    icon.classList.toggle('fa-eye-slash', !visible);
                }
                btn.setAttribute(
                    'aria-label',
                    visible ? 'Mostrar contrase\u00f1a' : 'Ocultar contrase\u00f1a'
                );
            });
        });
    }

    function go(url) {
        const dest = url && String(url).trim() ? String(url) : '/';
        try {
            window.location.replace(dest);
        } catch (e) {
            window.location.href = dest;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initPasswordToggles(document);

        const form = document.getElementById('form-login');
        const loading = document.getElementById('loading-area');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            // Evita quedarse en /php/login_process.php tras un 303 que el navegador no sigue.
            e.preventDefault();
            if (loading) loading.style.display = 'block';
            const btn = form.querySelector('.btn-login');
            if (btn) btn.disabled = true;

            const action = form.getAttribute('action') || '/php/login_process.php';
            const fd = new FormData(form);
            fd.append('ajax', '1');

            try {
                const r = await fetch(action, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'fetch',
                    },
                });
                let data = null;
                const ct = (r.headers.get('content-type') || '').toLowerCase();
                if (ct.includes('application/json')) {
                    data = await r.json();
                } else {
                    // Respuesta HTML de respaldo: ir al panel o al login.
                    const text = await r.text();
                    const m = text.match(/url=(["']?)(\/[^"'>\s]+)\1/i);
                    go(m && m[2] ? m[2] : '/');
                    return;
                }
                const dest = (data && (data.redirect || data.redirect_abs)) || '/';
                go(dest);
            } catch (err) {
                if (loading) loading.style.display = 'none';
                if (btn) btn.disabled = false;
                alert('No se pudo conectar al servidor de login. Intente de nuevo.');
            }
        });

        window.addEventListener('pageshow', () => {
            if (loading) loading.style.display = 'none';
            const btn = form.querySelector('.btn-login');
            if (btn) btn.disabled = false;
        });
    });
})();
