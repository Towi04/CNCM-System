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

    document.addEventListener('DOMContentLoaded', () => {
        initPasswordToggles(document);

        const form = document.getElementById('form-login');
        const loading = document.getElementById('loading-area');
        if (form && loading) {
            form.addEventListener('submit', () => {
                loading.style.display = 'block';
                const btn = form.querySelector('.btn-login');
                if (btn) btn.disabled = true;
            });
        }
    });
})();
