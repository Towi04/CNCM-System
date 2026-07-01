/**
 * Modales del panel: portar a body y confirmar al cerrar con clic fuera.
 */
(function () {
    'use strict';

    const HAY_MODAL_EXIT_MSG = '¿Desea salir del formulario? Los cambios no guardados se perderán.';
    const HAY_PERSISTENT_MODAL_IDS = new Set([
        'modal-inscripcion-wizard',
        'modal-producto',
        'modal-movimiento',
    ]);
    const HAY_HIDDEN_MODAL_SELECTOR = '.cert-modal, .rep-vent-modal, .asist-rondin-modal';
    const HAY_PORTAL_SELECTOR = '.catalog-modal, ' + HAY_HIDDEN_MODAL_SELECTOR;

    function hayModalIsOpen(modal) {
        if (!modal || modal.hidden) return false;
        if (modal.classList.contains('is-open')) return true;
        const display = modal.style.display;
        return display === 'flex' || display === 'block';
    }

    function hayAnyModalOpen() {
        if (document.querySelector('.catalog-modal.is-open')) return true;
        const catalogVisible = Array.from(document.querySelectorAll('.catalog-modal')).some(hayModalIsOpen);
        if (catalogVisible) return true;
        return !!document.querySelector(HAY_HIDDEN_MODAL_SELECTOR + ':not([hidden])');
    }

    function haySyncBodyScroll() {
        document.body.style.overflow = hayAnyModalOpen() ? 'hidden' : '';
    }

    function hayCloseModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        if (modal.style.display === 'flex' || modal.style.display === 'block') {
            modal.style.display = 'none';
        }
        haySyncBodyScroll();
        modal.dispatchEvent(new CustomEvent('hay-modal-closed', { bubbles: true }));
    }

    function hayCloseHiddenModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        haySyncBodyScroll();
        modal.dispatchEvent(new CustomEvent('hay-modal-closed', { bubbles: true }));
    }

    function hayRequestCloseModal(modal) {
        if (!modal || modal.dataset.hayNoBackdropClose === '1') return false;
        if (!window.confirm(HAY_MODAL_EXIT_MSG)) return false;
        hayCloseModal(modal);
        return true;
    }

    function hayRequestCloseHiddenModal(modal) {
        if (!modal || modal.dataset.hayNoBackdropClose === '1') return false;
        if (!window.confirm(HAY_MODAL_EXIT_MSG)) return false;
        hayCloseHiddenModal(modal);
        return true;
    }

    function hayForceCloseAllModals() {
        document.querySelectorAll('.catalog-modal').forEach((m) => {
            m.classList.remove('is-open');
            if (m.style.display === 'flex' || m.style.display === 'block') {
                m.style.display = 'none';
            }
        });
        document.querySelectorAll(HAY_HIDDEN_MODAL_SELECTOR).forEach((m) => {
            if (!m.hidden) m.hidden = true;
        });
        haySyncBodyScroll();
    }

    function hayPortalModals(scope) {
        const root = scope || document.getElementById('main-content');
        if (!root) return;
        root.querySelectorAll(HAY_PORTAL_SELECTOR).forEach((m) => {
            const id = m.id;
            if (id && HAY_PERSISTENT_MODAL_IDS.has(id)) return;
            if (id) {
                const existing = document.getElementById(id);
                if (existing && existing !== m) {
                    m.remove();
                    return;
                }
            }
            if (m.parentElement !== document.body) {
                document.body.appendChild(m);
            }
        });
    }

    function hayInitGlobalModals() {
        if (window.__hayGlobalModalsInited) return;
        window.__hayGlobalModalsInited = true;

        document.addEventListener('click', (e) => {
            const t = e.target;
            if (!(t instanceof Element)) return;

            if (t.id === 'modal-inscripcion-wizard') return;

            if (t.classList.contains('catalog-modal') && hayModalIsOpen(t)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                hayRequestCloseModal(t);
                return;
            }

            if (t.classList.contains('cert-modal') || t.classList.contains('rep-vent-modal') || t.classList.contains('asist-rondin-modal')) {
                if (t.hidden) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                hayRequestCloseHiddenModal(t);
            }
        }, true);
    }

    window.HAY_MODAL_EXIT_MSG = HAY_MODAL_EXIT_MSG;
    window.HAY_PERSISTENT_MODAL_IDS = HAY_PERSISTENT_MODAL_IDS;
    window.hayPortalModals = hayPortalModals;
    window.hayCloseModal = hayCloseModal;
    window.hayCloseHiddenModal = hayCloseHiddenModal;
    window.hayRequestCloseModal = hayRequestCloseModal;
    window.hayRequestCloseHiddenModal = hayRequestCloseHiddenModal;
    window.hayForceCloseAllModals = hayForceCloseAllModals;

    hayInitGlobalModals();
})();
