/**
 * Tour guiado HAY — mensajes flotantes por vista.
 */
(function () {
    'use strict';

    let activeTour = null;

    function el(tag, cls, html) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (html != null) n.innerHTML = html;
        return n;
    }

    function destroyTour() {
        if (!activeTour) return;
        ['overlay', 'spotlight', 'popover'].forEach((k) => {
            if (activeTour[k] && activeTour[k].parentNode) {
                activeTour[k].parentNode.removeChild(activeTour[k]);
            }
        });
        activeTour = null;
    }

    function positionSpotlight(target, spotlight, popover) {
        if (target) {
            const r = target.getBoundingClientRect();
            const pad = 6;
            spotlight.style.display = 'block';
            spotlight.style.top = (r.top - pad) + 'px';
            spotlight.style.left = (r.left - pad) + 'px';
            spotlight.style.width = (r.width + pad * 2) + 'px';
            spotlight.style.height = (r.height + pad * 2) + 'px';
            popover.style.top = Math.min(r.bottom + 12, window.innerHeight - 200) + 'px';
            popover.style.left = Math.min(r.left, window.innerWidth - 360) + 'px';
        } else {
            spotlight.style.display = 'none';
            popover.style.top = '50%';
            popover.style.left = '50%';
            popover.style.transform = 'translate(-50%, -50%)';
        }
    }

    function renderStep(pasos, index, tourKey) {
        destroyTour();
        const paso = pasos[index];
        if (!paso) return;

        const overlay = el('div', 'hay-tour-overlay');
        const spotlight = el('div', 'hay-tour-spotlight');
        const popover = el('div', 'hay-tour-popover');
        const target = paso.selector ? document.querySelector(paso.selector) : null;

        popover.innerHTML =
            '<h4>' + (paso.title || 'Asistente HAY') + '</h4>' +
            '<p>' + (paso.body || '') + '</p>' +
            '<div class="hay-tour-actions">' +
            '<span class="hay-tour-step-label">' + (index + 1) + ' / ' + pasos.length + '</span>' +
            '<span>' +
            (index > 0 ? '<button type="button" data-act="prev">Anterior</button> ' : '') +
            '<button type="button" data-act="skip">Omitir</button> ' +
            (index < pasos.length - 1
                ? '<button type="button" class="primary" data-act="next">Siguiente</button>'
                : '<button type="button" class="primary" data-act="finish">Entendido</button>') +
            '</span></div>';

        document.body.appendChild(overlay);
        document.body.appendChild(spotlight);
        document.body.appendChild(popover);

        positionSpotlight(target, spotlight, popover);
        activeTour = { overlay, spotlight, popover, pasos, index, tourKey };

        const onResize = () => positionSpotlight(target, spotlight, popover);
        window.addEventListener('resize', onResize);
        activeTour.onResize = onResize;

        popover.querySelectorAll('button[data-act]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const act = btn.getAttribute('data-act');
                if (act === 'prev') {
                    renderStep(pasos, index - 1, tourKey);
                } else if (act === 'next') {
                    renderStep(pasos, index + 1, tourKey);
                } else {
                    finishTour(tourKey);
                }
            });
        });

        overlay.addEventListener('click', () => finishTour(tourKey));
    }

    function finishTour(tourKey) {
        if (activeTour && activeTour.onResize) {
            window.removeEventListener('resize', activeTour.onResize);
        }
        destroyTour();
        const fd = new FormData();
        fd.append('action', 'completar');
        fd.append('tour_key', tourKey);
        fetch('php/tour_api.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
    }

    function startTour(tourKey) {
        fetch('php/tour_api.php?action=pasos&tour_key=' + encodeURIComponent(tourKey), {
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.status !== 'ok' || !data.pasos || !data.pasos.length) return;
                if (data.completado && !window.__hayTourForce) return;
                renderStep(data.pasos, 0, tourKey);
            })
            .catch(() => {});
    }

    window.hayTourStart = function (tourKey, force) {
        window.__hayTourForce = !!force;
        startTour(tourKey || 'inicio');
    };

    window.hayTourMaybeForSection = function (seccion) {
        const map = {
            inicio_panel: 'inicio',
            gerente_dashboard: 'gerente_dashboard',
            alumno_portal_inicio: 'alumno_portal',
        };
        const key = map[seccion];
        if (key) {
            setTimeout(() => startTour(key), 400);
        }
    };

    window.hayTourResetAll = function () {
        const fd = new FormData();
        fd.append('action', 'reset');
        return fetch('php/tour_api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then((r) => r.json());
    };
})();
