/* Navegación — sidebar CNCM, flyouts rojos, plantel, carga AJAX */

const MOBILE_BP = 768;

function isMobile() {
    return window.innerWidth <= MOBILE_BP;
}

function getSidebar() {
    return document.getElementById('sidebar');
}

function getOverlay() {
    return document.getElementById('sidebar-overlay');
}

function closeMobileSidebar() {
    const sidebar = getSidebar();
    const overlay = getOverlay();
    if (!sidebar) return;
    sidebar.classList.remove('mobile-open');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
}

function openMobileSidebar() {
    const sidebar = getSidebar();
    const overlay = getOverlay();
    if (!sidebar) return;
    sidebar.classList.add('mobile-open');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAllFlyouts() {
    document.querySelectorAll('.menu-flyout').forEach((f) => f.classList.remove('is-visible'));
    document.querySelectorAll('.nav-item.has-flyout').forEach((n) => n.classList.remove('is-active'));
    const container = document.getElementById('menu-flyouts');
    if (container) container.setAttribute('aria-hidden', 'true');
}

function positionFlyout(flyout, anchorEl) {
    if (!flyout || !anchorEl) return;
    const rect = anchorEl.getBoundingClientRect();
    const maxH = window.innerHeight - 16;
    let top = rect.top;
    const flyoutH = flyout.offsetHeight || 280;
    if (top + flyoutH > maxH) {
        top = Math.max(8, maxH - flyoutH);
    }
    flyout.style.top = `${top}px`;
}

function openFlyout(flyoutId, navItem) {
    const flyout = document.getElementById(flyoutId);
    if (!flyout) return;

    const wasOpen = flyout.classList.contains('is-visible') && navItem.classList.contains('is-active');
    closeAllFlyouts();
    if (wasOpen) return;

    navItem.classList.add('is-active');
    flyout.classList.add('is-visible');
    const container = document.getElementById('menu-flyouts');
    if (container) container.setAttribute('aria-hidden', 'false');
    positionFlyout(flyout, navItem);

    if (navItem.dataset.title) {
        setPageHeader(navItem.dataset.title, navItem.dataset.breadcrumb || navItem.dataset.title);
    }
}

function setPageHeader(title, breadcrumb) {
    const titleEl = document.getElementById('page-title');
    const crumbEl = document.getElementById('breadcrumb');
    if (titleEl && title) titleEl.textContent = title;
    if (crumbEl && breadcrumb) crumbEl.textContent = breadcrumb.toUpperCase();
}

function closeSidebarUserDropdown() {
    const dd = document.getElementById('sidebar-user-dropdown');
    const trigger = document.getElementById('sidebar-user-trigger');
    if (dd) dd.classList.remove('active');
    if (trigger) trigger.classList.remove('is-open');
}

function closePlantelDropdown() {
    const dd = document.getElementById('plantel-dropdown');
    if (dd) dd.classList.remove('active');
}

async function cambiarPlantel(plantelId) {
    const fd = new FormData();
    fd.append('plantel_id', plantelId);
    const res = await fetch('php/cambiar_plantel.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'fetch' },
    });
    const data = await res.json();
    if (data.status !== 'ok') {
        console.error(data.message || 'No se pudo cambiar el plantel');
        return;
    }
    const label = document.getElementById('plantel-label');
    if (label) label.textContent = data.plantel_nombre;
    document.querySelectorAll('.plantel-option').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.plantelId === plantelId);
    });
    closePlantelDropdown();
    hayApplyPlantelFondo(data);
    if (window.__haySeccionActual?.pagina) {
        cargarSeccion(window.__haySeccionActual.pagina, window.__haySeccionActual.queryParams);
    } else {
        cargarSeccion('inicio_panel');
    }
}

/** Actualiza imagen de fondo del área principal según plantel activo. */
function hayApplyPlantelFondo(data) {
    const main = document.getElementById('main-content');
    if (!main) return;

    const slug = data?.plantel_slug || '';
    document.body.dataset.plantelSlug = slug;

    main.classList.remove(
        'plantel-fondo',
        'plantel-fondo--salamanca',
        'plantel-fondo--celaya',
        'plantel-fondo--guerrero',
        'plantel-fondo--fuentes'
    );
    main.style.removeProperty('background-image');
    main.style.removeProperty('--plantel-fondo-url');

    const url = data?.plantel_fondo_url || '';
    if (url) {
        const clases = (data.plantel_fondo_clases || ('plantel-fondo plantel-fondo--' + slug)).split(/\s+/).filter(Boolean);
        clases.forEach((c) => main.classList.add(c));
        main.style.setProperty('--plantel-fondo-url', "url('" + url.replace(/'/g, "\\'") + "')");
        main.dataset.plantelFondo = url;
    } else {
        delete main.dataset.plantelFondo;
    }
}

window.hayApplyPlantelFondo = hayApplyPlantelFondo;

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
        });
    });
}

window.initPasswordToggles = initPasswordToggles;

function bindNavItem(el) {
    if (el.dataset.bound === '1') return;
    el.dataset.bound = '1';

    if (el.classList.contains('has-flyout')) {
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            const flyoutId = el.dataset.flyout;
            if (flyoutId) openFlyout(flyoutId, el);
        });
        return;
    }

    const seccion = el.dataset.seccion;
    if (!seccion) return;

    el.addEventListener('click', () => {
        closeAllFlyouts();
        if (el.dataset.title) {
            setPageHeader(el.dataset.title, el.dataset.breadcrumb || el.dataset.title);
        }
        let query = null;
        if (el.dataset.query) {
            query = el.dataset.query;
        }
        cargarSeccion(seccion, query);
    });
}

/** Evita error JS si un avatar legacy (default_avatar.png) no carga en dashboard antiguo. */
/** Actualiza la foto del menú lateral tras subir o quitar avatar en Mi perfil. */
function hayUpdateSidebarAvatar(url) {
    const wrap = document.querySelector('.sidebar-user-avatar');
    if (!wrap) return;

    let img = wrap.querySelector('.sidebar-user-photo');
    if (url) {
        const resolved = typeof window.hayResolveAssetUrl === 'function'
            ? window.hayResolveAssetUrl(url)
            : url;
        if (!img) {
            img = document.createElement('img');
            img.className = 'sidebar-user-photo';
            img.alt = '';
            img.onerror = function () {
                this.style.display = 'none';
            };
            wrap.appendChild(img);
        }
        const src = resolved + (resolved.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
        img.src = src;
        img.style.display = '';
        wrap.classList.add('has-photo');
    } else if (img) {
        img.remove();
        wrap.classList.remove('has-photo');
    }
}

window.hayUpdateSidebarAvatar = hayUpdateSidebarAvatar;

function initAvatarImages() {
    document.querySelectorAll('img').forEach((img) => {
        const src = img.getAttribute('src') || '';
        if (!/default_avatar\.(png|jpe?g|gif|webp)$/i.test(src)) {
            return;
        }
        img.addEventListener(
            'error',
            () => {
                img.style.display = 'none';
            },
            { once: true }
        );
    });
}

function bindFlyoutItems() {
    document.querySelectorAll('.flyout-list li[data-seccion]').forEach((li) => {
        if (li.dataset.bound === '1') return;
        li.dataset.bound = '1';
        li.addEventListener('click', (e) => {
            e.stopPropagation();
            const seccion = li.dataset.seccion;
            if (!seccion) return;
            if (li.dataset.title) {
                setPageHeader(li.dataset.title, li.dataset.breadcrumb || li.dataset.title);
            }
            closeAllFlyouts();
            let queryParams = null;
            if (li.dataset.query) {
                queryParams = {};
                li.dataset.query.split('&').forEach((part) => {
                    const [k, v] = part.split('=');
                    if (k) queryParams[k] = decodeURIComponent(v || '');
                });
            }
            cargarSeccion(seccion, queryParams);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initAvatarImages();
    document.querySelectorAll('.sidebar-menu .nav-item').forEach(bindNavItem);
    bindFlyoutItems();

    const btnMobile = document.getElementById('btn-menu-mobile');
    if (btnMobile) {
        btnMobile.addEventListener('click', (e) => {
            e.stopPropagation();
            const sidebar = getSidebar();
            if (!sidebar) return;
            if (sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });
    }

    const overlay = getOverlay();
    if (overlay) overlay.addEventListener('click', () => {
        closeMobileSidebar();
        closeAllFlyouts();
    });

    const userTrigger = document.getElementById('sidebar-user-trigger');
    const userDropdown = document.getElementById('sidebar-user-dropdown');
    if (userTrigger && userDropdown) {
        userTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = userDropdown.classList.toggle('active');
            userTrigger.classList.toggle('is-open', open);
        });
        userDropdown.querySelectorAll('a[data-seccion]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                closeSidebarUserDropdown();
                const query = link.dataset.query || null;
                cargarSeccion(link.getAttribute('data-seccion'), query);
            });
        });
    }

    const plantelSelect = document.getElementById('plantel-select');
    const plantelDropdown = document.getElementById('plantel-dropdown');
    if (plantelSelect && plantelDropdown) {
        plantelSelect.addEventListener('click', (e) => {
            e.stopPropagation();
            plantelDropdown.classList.toggle('active');
        });
        plantelDropdown.querySelectorAll('.plantel-option').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                cambiarPlantel(btn.dataset.plantelId);
            });
        });
    }

    document.addEventListener('click', () => {
        closeSidebarUserDropdown();
        closePlantelDropdown();
        if (!isMobile()) closeAllFlyouts();
    });

    window.addEventListener('resize', () => {
        document.querySelectorAll('.menu-flyout.is-visible').forEach((flyout) => {
            const id = flyout.dataset.flyoutId;
            const nav = document.querySelector(`.nav-item[data-flyout="${id}"]`);
            if (nav) positionFlyout(flyout, nav);
        });
    });

    initPasswordToggles(document);

    const mainHome = document.getElementById('main-content');
    if (mainHome) {
        window.__hayHomeHtml = mainHome.innerHTML;
    }

    const homeState = hayNavSerialize('inicio_panel', null);
    hayNavUpdateHistory(homeState, true);

    window.addEventListener('popstate', (ev) => {
        const state = ev.state && ev.state.pagina ? ev.state : hayNavStateFromUrl();
        __hayNavFromPopstate = true;
        try {
            if (!state.pagina || state.pagina === 'inicio_panel') {
                const contenedor = document.getElementById('main-content');
                if (contenedor && window.__hayHomeHtml) {
                    contenedor.innerHTML = window.__hayHomeHtml;
                    window.__haySeccionActual = homeState;
                    hayNavUpdateHistory(homeState, true);
                    setPageHeader('Inicio', 'INICIO');
                }
                return;
            }
            cargarSeccion(state.pagina, state.queryParams, { skipHistory: true });
        } finally {
            __hayNavFromPopstate = false;
        }
    });

    const urlState = hayNavStateFromUrl();
    if (urlState.pagina && urlState.pagina !== 'inicio_panel') {
        cargarSeccion(urlState.pagina, urlState.queryParams, { replaceHistory: true });
    }
});

function hayNavSerialize(pagina, queryParams) {
    let qp = null;
    if (queryParams instanceof URLSearchParams) {
        qp = {};
        queryParams.forEach((v, k) => {
            qp[k] = v;
        });
    } else if (queryParams && typeof queryParams === 'object') {
        qp = {};
        Object.keys(queryParams).forEach((k) => {
            if (k) qp[k] = String(queryParams[k]);
        });
    }
    return { pagina, queryParams: qp };
}

function hayNavUpdateHistory(state, replace) {
    if (!window.history || typeof window.history.pushState !== 'function') return;
    const url = new URL(window.location.href);
    const home = !state.pagina || state.pagina === 'inicio_panel';
    if (home) {
        url.searchParams.delete('s');
        url.searchParams.delete('q');
    } else {
        url.searchParams.set('s', state.pagina);
        if (state.queryParams && Object.keys(state.queryParams).length) {
            url.searchParams.set('q', new URLSearchParams(state.queryParams).toString());
        } else {
            url.searchParams.delete('q');
        }
    }
    const href = url.pathname + url.search + url.hash;
    if (replace) {
        window.history.replaceState(state, '', href);
    } else {
        window.history.pushState(state, '', href);
    }
}

function hayNavStateFromUrl() {
    const url = new URL(window.location.href);
    const pagina = url.searchParams.get('s') || 'inicio_panel';
    const q = url.searchParams.get('q');
    if (!q) return hayNavSerialize(pagina, null);
    const qp = {};
    new URLSearchParams(q).forEach((v, k) => {
        qp[k] = v;
    });
    return hayNavSerialize(pagina, qp);
}

let __hayNavFromPopstate = false;

/** Cierra y quita modales de la vista anterior al cambiar de sección. */
function hayCleanupOrphanModals() {
    if (typeof window.hayForceCloseAllModals === 'function') {
        window.hayForceCloseAllModals();
    }
    if (window.HayInscripcionWizard?.close) {
        try { window.HayInscripcionWizard.close(); } catch (e) { /* ignore */ }
    }

    const persistent = window.HAY_PERSISTENT_MODAL_IDS || new Set([
        'modal-inscripcion-wizard',
        'modal-producto',
        'modal-movimiento',
    ]);
    const portalSel = '.catalog-modal, .cert-modal, .rep-vent-modal, .asist-rondin-modal';

    document.querySelectorAll('body > ' + portalSel).forEach((el) => {
        if (!el.id || !persistent.has(el.id)) el.remove();
    });

    const main = document.getElementById('main-content');
    if (main) {
        main.querySelectorAll(portalSel).forEach((el) => {
            if (!el.id || !persistent.has(el.id)) el.remove();
        });
        persistent.forEach((id) => {
            const inMain = main.querySelector('#' + id);
            const onBody = document.body.querySelector('#' + id);
            if (inMain) {
                if (!onBody) document.body.appendChild(inMain);
                else inMain.remove();
            }
        });
    }

    persistent.forEach((id) => {
        document.querySelectorAll('#' + id).forEach((el, idx) => {
            if (idx > 0) el.remove();
        });
    });

    if (window.HayInscripcionWizard?.wireModal) {
        window.HayInscripcionWizard.wireModal();
    }
}

function hayDisableBrowserAutofill(root) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('input[type="search"], input[data-hay-no-autofill]').forEach((inp) => {
        inp.setAttribute('autocomplete', 'off');
        inp.setAttribute('autocorrect', 'off');
        inp.setAttribute('autocapitalize', 'off');
        inp.setAttribute('spellcheck', 'false');
        if (!inp.name || inp.name === 'q' || inp.name === 'search') {
            inp.name = 'hay_q_' + (inp.id || 'x').replace(/[^a-z0-9_-]/gi, '');
        }
    });
}

function cargarSeccion(pagina, queryParams, navOpts) {
    const opts = navOpts || {};
    if (window.HAY_DEBE_CAMBIAR_PASSWORD && pagina !== 'cambiar_password') {
        pagina = 'cambiar_password';
        queryParams = null;
    } else if (window.HAY_DEBE_ACEPTAR_ACUERDO && pagina !== 'alumno_acuerdo_aceptar' && pagina !== 'cambiar_password') {
        pagina = 'alumno_acuerdo_aceptar';
        queryParams = null;
    } else if (window.HAY_DEBE_COMPLETAR_PERFIL && pagina !== 'alumno_perfil_gustos' && pagina !== 'cambiar_password' && pagina !== 'alumno_acuerdo_aceptar') {
        pagina = 'alumno_perfil_gustos';
        queryParams = null;
    } else if (window.HAY_SUSPENSION_PORTAL === 'adeudo') {
        const permitidas = ['alumno_cuenta_suspendida', 'alumno_mi_perfil', 'perfil', 'cambiar_password'];
        if (!permitidas.includes(pagina)) {
            pagina = 'alumno_cuenta_suspendida';
            queryParams = null;
        }
    }
    if (pagina === 'asistencia_movil') {
        pagina = 'asistencia_faltantes';
    }
    const contenedor = document.getElementById('main-content');
    if (!contenedor) return;

    const state = hayNavSerialize(pagina, queryParams);
    window.__haySeccionActual = state;

    if (!opts.skipHistory) {
        hayNavUpdateHistory(state, !!opts.replaceHistory);
    }

    if (isMobile()) closeMobileSidebar();
    closeAllFlyouts();
    hayCleanupOrphanModals();

    if (pagina === 'inicio_panel') {
        if (window.__hayHomeHtml) {
            hayDestroySectionTables(contenedor);
            contenedor.innerHTML = window.__hayHomeHtml;
            hayDisableBrowserAutofill(contenedor);
            if (typeof setPageHeader === 'function') {
                setPageHeader('Inicio', 'INICIO');
            }
            if (typeof window.hayTourMaybeForSection === 'function') {
                window.hayTourMaybeForSection('inicio_panel');
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        return;
    }

    let rutaFinal = 'views/' + pagina + '.php';
    if (queryParams) {
        let qs = '';
        if (queryParams instanceof URLSearchParams) {
            qs = queryParams.toString();
        } else if (typeof queryParams === 'string') {
            qs = queryParams;
        } else if (typeof queryParams === 'object') {
            qs = new URLSearchParams(queryParams).toString();
        }
        if (qs) rutaFinal += (rutaFinal.includes('?') ? '&' : '?') + qs;
    }
    rutaFinal = hayResolveAssetUrl(rutaFinal);

    hayDestroySectionTables(contenedor);
    contenedor.innerHTML = '<div class="hay-section-loading" style="padding:24px;color:#666;">Cargando…</div>';

    const cargarHtml = () => {
    const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => ctrl.abort(), 90000) : null;
    fetch(rutaFinal, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'fetch' },
        signal: ctrl ? ctrl.signal : undefined,
    })
        .then(async (response) => {
            const html = await response.text();
            if (!response.ok) {
                const detalle = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
                throw new Error('Error ' + response.status + ' en ' + rutaFinal + (detalle ? ': ' + detalle : ''));
            }
            return html;
        })
        .then(async (html) => {
            hayDestroySectionTables(contenedor);
            contenedor.innerHTML = html;
            hayDisableBrowserAutofill(contenedor);
            if (typeof window.hayPortalModals === 'function') {
                window.hayPortalModals(contenedor);
            }
            await ejecutarScripts(contenedor);
            if (typeof window.hayPortalModals === 'function') {
                window.hayPortalModals(contenedor);
            }
            if (typeof window.hayTourMaybeForSection === 'function') {
                window.hayTourMaybeForSection(pagina);
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch((err) => {
            console.error('Error AJAX:', err);
            let det = err && err.message ? String(err.message) : 'Error desconocido';
            if (err && err.name === 'AbortError') {
                det = 'Tiempo de espera agotado al cargar ' + rutaFinal;
            }
            contenedor.innerHTML = '<div class="alert">Error al cargar la sección: ' + det.replace(/</g, '&lt;') + '</div>';
        })
        .finally(() => {
            if (timer) clearTimeout(timer);
        });
    };

    hayReleaseFingerprintThen(cargarHtml);
}

function hayReleaseFingerprintThen(fn) {
    if (!window.HayFingerprintReader?.releaseAll) {
        fn();
        return;
    }
    const timeout = new Promise((resolve) => setTimeout(resolve, 600));
    Promise.race([
        window.HayFingerprintReader.releaseAll().catch(() => {}),
        timeout,
    ]).finally(fn);
}

window.cargarSeccion = cargarSeccion;
window.cambiarPlantel = cambiarPlantel;

function hayResolveAssetUrl(src) {
    if (!src) return src;
    if (/^https?:\/\//i.test(src) || src.startsWith('//')) return src;
    const root = window.HAY_WEB_ROOT || '';
    const rootPath = root.replace(/\/$/, '');
    if (src.startsWith('/')) {
        if (rootPath && rootPath !== '' && !src.startsWith(rootPath + '/') && src !== rootPath) {
            return rootPath + src;
        }
        return src;
    }
    return root + src.replace(/^\//, '');
}

window.hayResolveAssetUrl = hayResolveAssetUrl;

function hayDestroySectionTables(contenedor) {
    if (window.HayDataTable?.destroyIn && contenedor) {
        window.HayDataTable.destroyIn(contenedor);
    }
}

function hayLoadExternalScript(src) {
    if (!src) return Promise.resolve();
    const resolved = hayResolveAssetUrl(src);
    const abs = new URL(resolved, window.location.href).href;
    const existing = document.querySelector(`script[src="${abs}"], script[src="${resolved}"], script[src="${src}"]`);
    if (existing) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const el = document.createElement('script');
        el.src = resolved;
        el.async = false;
        el.onload = () => resolve();
        el.onerror = () => reject(new Error('No se pudo cargar el script (404): ' + resolved));
        document.head.appendChild(el);
    });
}

function hayRunInlineScript(code) {
    const el = document.createElement('script');
    el.textContent = code;
    document.body.appendChild(el);
    document.body.removeChild(el);
}

async function ejecutarScripts(contenedor) {
    const scripts = [...contenedor.querySelectorAll('script')];
    for (const script of scripts) {
        try {
            if (script.src) {
                await hayLoadExternalScript(script.src);
            } else if (script.textContent.trim()) {
                hayRunInlineScript(script.textContent);
            }
        } catch (err) {
            console.error('Error cargando script de sección:', err);
            const aviso = document.createElement('div');
            aviso.className = 'alert';
            aviso.style.marginBottom = '12px';
            aviso.textContent = err.message || 'Error al cargar un script de la sección.';
            contenedor.prepend(aviso);
        }
    }
    initPasswordToggles(contenedor);
}

window.hayFetchJson = async function hayFetchJson(url, options = {}) {
    const resolvedUrl = hayResolveAssetUrl(url);
    const res = await fetch(resolvedUrl, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'fetch', ...(options.headers || {}) },
        ...options,
    });
    const ct = (res.headers.get('content-type') || '').toLowerCase();
    if (!ct.includes('application/json')) {
        const text = await res.text();
        const plain = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
        let hint = `Error del servidor (${res.status}).`;
        if (res.status === 403) {
            hint = 'Acceso denegado (403). Si adjuntó foto, pruebe subir archivo JPG o sin foto; el firewall del hosting puede bloquear imágenes muy grandes.';
        } else if (res.status === 401) {
            hint = 'Sesión expirada. Vuelva a iniciar sesión.';
        }
        throw new Error(plain ? `${hint} ${plain}` : hint);
    }
    const data = await res.json();
    return { res, data };
}

async function submitAjaxForm(form) {
    const contenedor = document.getElementById('main-content');
    const rawAction = form.getAttribute('action') || window.location.href;
    const url = typeof window.hayResolveAssetUrl === 'function'
        ? window.hayResolveAssetUrl(rawAction)
        : rawAction;
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    const datos = new FormData(form);

    const res = await fetch(url, {
        method,
        body: datos,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'fetch' },
    });

    const ct = (res.headers.get('content-type') || '').toLowerCase();
    if (!res.ok && !ct.includes('application/json')) {
        const errText = await res.text();
        const msgBox = form.querySelector('#respuesta-registro')
            || document.getElementById('respuesta-registro')
            || form.querySelector('#respuesta-password')
            || document.getElementById('respuesta-password');
        const short = errText.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 280);
        if (msgBox) {
            msgBox.style.display = 'block';
            msgBox.className = 'mensaje-error';
            msgBox.textContent = 'Error del servidor (' + res.status + '). ' + (short || 'Revise el log de PHP.');
        } else {
            alert('Error del servidor (' + res.status + '). ' + (short || 'Revise permisos y log de PHP.'));
        }
        return;
    }
    if (ct.includes('application/json')) {
        const data = await res.json();
        const ok = data && (data.status === 'success' || data.status === 'ok');
        if (data && Object.prototype.hasOwnProperty.call(data, 'avatar_url')) {
            hayUpdateSidebarAvatar(data.avatar_url || null);
        }
        const msgAvatar = form.querySelector('#respuesta-avatar') || document.getElementById('respuesta-avatar');
        if (msgAvatar && data && (data.message || data.status)) {
            msgAvatar.style.display = 'block';
            msgAvatar.className = 'perfil-avatar-msg ' + (ok ? 'ok' : 'err');
            msgAvatar.textContent = data.message || (ok ? 'Listo' : 'Error');
        }
        if (data && data.seccion && ok) {
            cargarSeccion(data.seccion);
            if (data.seccion === 'resultado_disc' && data.id) {
                window.cargarResultadoDisc(data.id);
            }
            return;
        }
        const msgBox = form.querySelector('#respuesta-registro')
            || document.getElementById('respuesta-registro')
            || form.querySelector('#respuesta-password')
            || document.getElementById('respuesta-password');
        if (msgBox && data && (data.message || data.status)) {
            msgBox.style.display = 'block';
            const ok = data.status === 'success' || data.status === 'ok';
            msgBox.className = ok ? 'mensaje-exito' : 'mensaje-error';
            msgBox.style.background = ok ? '#e8f5e9' : '#ffebee';
            msgBox.style.color = ok ? '#2e7d32' : '#c62828';
            msgBox.textContent = data.message || (ok ? 'Listo' : 'Error');
        } else if (!res.ok && data && data.message) {
            alert(data.message);
        }
        if (data && (data.status === 'success' || data.status === 'ok')) {
            try { form.reset(); } catch (e) { /* ignore */ }
        }
        return;
    }

    if (contenedor) {
        contenedor.innerHTML = await res.text();
        await ejecutarScripts(contenedor);
    }
}

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-global-ajax')) return;
    if (form.id === 'form-avatar-upload' || form.id === 'form-avatar-remove') return;
    const rawAction = (form.getAttribute('action') || '').trim();
    if (!rawAction || rawAction === '#') return;
    const contenedor = document.getElementById('main-content');
    if (!contenedor || !contenedor.contains(form)) return;
    e.preventDefault();
    submitAjaxForm(form).catch((err) => console.error(err));
}, true);

window.cargarResultadoDisc = function cargarResultadoDisc(id) {
    const contenedor = document.getElementById('main-content');
    if (!contenedor) return;
    if (isMobile()) closeMobileSidebar();
    contenedor.innerHTML = '<div class="result-container"><div class="result-header"><h2>Resultado DISC</h2><p style="color:#666;">Cargando...</p></div></div>';
    fetch('views/resultado_disc.php?id=' + encodeURIComponent(id) + '&t=' + Date.now(), {
        cache: 'no-store',
        headers: { 'X-Requested-With': 'fetch' },
    })
        .then((r) => {
            if (!r.ok) throw new Error('No se pudo cargar resultado DISC');
            return r.text();
        })
        .then(async (html) => {
            hayDestroySectionTables(contenedor);
            contenedor.innerHTML = html;
            await ejecutarScripts(contenedor);
            setPageHeader('Resultado DISC', 'DISC');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch((err) => {
            console.error(err);
            contenedor.innerHTML = '<div class="alert">Error al cargar resultado DISC.</div>';
        });
};

window.cargarUltimoResultadoDisc = function cargarUltimoResultadoDisc(id) {
    window.cargarResultadoDisc(id);
};

window.cargarSeccion = cargarSeccion;
window.setPageHeader = setPageHeader;
