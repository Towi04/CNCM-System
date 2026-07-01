(function () {

  'use strict';



  function tutorId(v) {

    const n = parseInt(String(v ?? ''), 10);

    return Number.isFinite(n) ? n : 0;

  }



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  function truncarTitulo(texto, max) {

    const t = String(texto ?? '').trim();

    if (t === '') return 'Conversación';

    if (t.length <= max) return t;

    return t.slice(0, max - 1) + '…';

  }



  function tituloConversacion(c) {

    const titulo = (c.titulo || '').trim();

    if (titulo && titulo !== 'Nueva conversación') {

      return truncarTitulo(titulo, 72);

    }

    const ultimo = (c.ultimo_mensaje || '').trim();

    if (ultimo) return truncarTitulo(ultimo, 72);

    return c.tutor_nombre || 'Conversación';

  }



  function renderMarkdown(text) {

    if (!text) return '';

    if (typeof marked !== 'undefined') {

      marked.setOptions({ breaks: true, gfm: true });

      const html = marked.parse(text);

      const wrap = document.createElement('div');

      wrap.innerHTML = html;

      wrap.querySelectorAll('pre code').forEach((block) => {

        if (typeof hljs !== 'undefined') hljs.highlightElement(block);

      });

      return wrap.innerHTML;

    }

    return esc(text).replace(/\n/g, '<br>');

  }



  window.hayTutorChatInit = function hayTutorChatInit(rootEl) {

    const root = rootEl || document.getElementById('tutor-app');

    if (!root) return;



    const cfg = window.HAY_TUTOR_CONFIG || {};

    const apiBase = cfg.api || 'php/tutor_api.php';



    function apiUrl(action, params) {

      const resolved = typeof window.hayResolveAssetUrl === 'function'

        ? window.hayResolveAssetUrl(apiBase)

        : apiBase;

      const url = new URL(resolved, window.location.href);

      if (action) url.searchParams.set('action', action);

      if (params) {

        Object.keys(params).forEach((k) => {

          if (params[k] != null && params[k] !== '') url.searchParams.set(k, params[k]);

        });

      }

      return url.toString();

    }

    let csrf = cfg.csrf || '';

    let tutores = [];

    let conversaciones = [];

    let tutorActivo = null;

    let convActiva = null;

    let soloUnTutor = false;

    let mostrarArchivadas = false;



    const $ = (id) => root.querySelector('#' + id);



    async function apiGet(action, params) {

      const r = await fetch(apiUrl(action, params), {

        credentials: 'same-origin',

        headers: { 'X-Requested-With': 'fetch' },

        cache: 'no-store',

      });

      const raw = await r.text();

      let data = {};

      if (raw) {

        try {

          data = JSON.parse(raw);

        } catch (e) {

          throw new Error('Error del servidor (' + r.status + '). Revise que las migraciones del tutor estén aplicadas.');

        }

      } else if (!r.ok) {

        throw new Error('Error del servidor (' + r.status + ').');

      }

      return data;

    }



    async function ensureCsrf() {

      try {

        const csrfRes = await apiGet('csrf');

        if (csrfRes.csrf) csrf = csrfRes.csrf;

      } catch (e) {

        if (cfg.csrf) csrf = cfg.csrf;

      }

    }



    async function apiPost(action, data) {

      await ensureCsrf();

      const fd = new FormData();

      fd.append('action', action);

      fd.append('csrf', csrf);

      Object.keys(data || {}).forEach((k) => fd.append(k, data[k]));

      const r = await fetch(apiUrl(''), {

        method: 'POST',

        body: fd,

        credentials: 'same-origin',

        headers: {

          'X-Requested-With': 'fetch',

          'X-Tutor-CSRF': csrf,

        },

        cache: 'no-store',

      });

      const raw = await r.text();

      let json = {};

      if (raw) {

        try {

          json = JSON.parse(raw);

        } catch (e) {

          const snippet = raw ? raw.replace(/\s+/g, ' ').trim().slice(0, 180) : '';

          throw new Error(

            'Error del servidor (' + r.status + ').'

            + (snippet ? ' ' + snippet : ' Revise migraciones del tutor y OPENROUTER_API_KEY.')

          );

        }

      } else if (!r.ok) {

        throw new Error('Error del servidor (' + r.status + ').');

      }

      return json;

    }



    function appendMessage(role, text) {

      const box = $('tutor-messages');

      if (!box) return;

      const div = document.createElement('div');

      div.className = 'tutor-msg tutor-msg--' + role;

      if (role === 'assistant') {

        div.innerHTML = renderMarkdown(text);

      } else {

        div.textContent = text;

      }

      box.appendChild(div);

      box.scrollTop = box.scrollHeight;

    }



    function showChat(show) {

      const empty = $('tutor-chat-empty');

      const active = $('tutor-chat-active');

      if (empty) empty.hidden = show;

      if (active) active.hidden = !show;

    }



    function setTyping(on) {

      const typing = $('tutor-typing');

      const send = $('tutor-send');

      if (typing) typing.hidden = !on;

      if (send) send.disabled = on || !cfg.iaOk;

    }



    function findTutor(id) {

      const tid = tutorId(id);

      return tutores.find((t) => tutorId(t.id_tutor) === tid) || null;

    }



    function actualizarCabeceraChat() {

      const nameEl = $('tutor-chat-tutor-name');

      const espEl = $('tutor-chat-esp');

      const t = tutorActivo || (convActiva ? findTutor(convActiva.id_tutor) : null);

      if (nameEl) nameEl.textContent = convActiva?.tutor_nombre || t?.nombre || 'Tutor';

      if (espEl) espEl.textContent = convActiva?.especialidad || t?.especialidad || '';

    }



    function renderTutores() {

      const ul = $('tutor-tutor-list');

      if (!ul) return;

      if (!tutores.length) {

        ul.innerHTML = '<li class="tutor-empty">No hay tutores disponibles</li>';

        return;

      }

      ul.innerHTML = tutores.map((t) => {

        const active = tutorActivo && tutorId(tutorActivo.id_tutor) === tutorId(t.id_tutor) ? ' is-active' : '';

        return `<li>

          <button type="button" class="tutor-tutor-item${active}" data-id="${tutorId(t.id_tutor)}">

            <span class="tutor-tutor-item__name">${esc(t.nombre)}</span>

            <span class="tutor-badge">${esc(t.especialidad)}</span>

            <span class="tutor-tutor-item__desc">${esc(t.descripcion || '')}</span>

          </button>

        </li>`;

      }).join('');

      ul.querySelectorAll('.tutor-tutor-item').forEach((btn) => {

        btn.addEventListener('click', () => seleccionarTutor(tutorId(btn.dataset.id)));

      });

    }



    function renderConversaciones() {

      const ul = $('tutor-conv-list');

      if (!ul) return;

      if (!conversaciones.length) {

        ul.innerHTML = '<li class="tutor-empty">' + (mostrarArchivadas ? 'Sin conversaciones archivadas' : 'Sin conversaciones aún') + '</li>';

        return;

      }

      ul.innerHTML = conversaciones.map((c) => {

        const cid = tutorId(c.id_conversacion);

        const active = convActiva && tutorId(convActiva.id_conversacion) === cid ? ' is-active' : '';

        const titulo = tituloConversacion(c);

        const meta = esc(c.tutor_nombre || '') + (c.actualizado_en ? ' · ' + String(c.actualizado_en).slice(0, 10) : '');

        const archiveBtn = mostrarArchivadas ? '' : `<button type="button" class="tutor-btn-icon tutor-conv-archive" data-id="${cid}" title="Archivar conversación" aria-label="Archivar"><i class="fas fa-archive"></i></button>`;

        return `<li class="tutor-conv-row">

          <button type="button" class="tutor-conv-item${active}" data-id="${cid}">

            <span class="tutor-conv-item__title">${esc(titulo)}</span>

            <span class="tutor-conv-item__meta">${meta}</span>

          </button>

          ${archiveBtn}

        </li>`;

      }).join('');

      ul.querySelectorAll('.tutor-conv-item').forEach((btn) => {

        btn.addEventListener('click', () => abrirConversacion(tutorId(btn.dataset.id)));

      });

      ul.querySelectorAll('.tutor-conv-archive').forEach((btn) => {

        btn.addEventListener('click', (e) => {

          e.stopPropagation();

          archivarConversacion(tutorId(btn.dataset.id));

        });

      });

    }



    function setSoloUnTutor(on) {

      soloUnTutor = !!on;

      const sec = root.querySelector('.tutor-sidebar__section--tutores');

      if (sec) sec.hidden = soloUnTutor;

      root.classList.toggle('tutor-app--solo-tutor', soloUnTutor);

      const empty = $('tutor-chat-empty');

      const p = empty?.querySelector('p');

      if (p) {

        p.textContent = soloUnTutor

          ? 'Escriba su pregunta para comenzar con su tutor'

          : 'Seleccione un tutor y escriba su primera pregunta';

      }

    }



    async function cargarTutores() {

      const d = await apiGet('tutores');

      if (d.status !== 'ok') return;

      tutores = d.tutores || [];

      setSoloUnTutor(!!d.solo_un_tutor);

      renderTutores();

      if (soloUnTutor && tutores.length === 1 && !convActiva) {

        tutorActivo = tutores[0];

        renderTutores();

        prepararChatNuevo();

      }

    }



    async function cargarConversaciones() {

      const params = mostrarArchivadas ? { archivadas: '1' } : null;

      const d = await apiGet('conversaciones', params);

      if (d.status !== 'ok') return;

      conversaciones = d.conversaciones || [];

      renderConversaciones();

    }



    function prepararChatNuevo() {

      convActiva = null;

      actualizarCabeceraChat();

      const box = $('tutor-messages');

      if (box) box.innerHTML = '';

      showChat(true);

      $('tutor-input')?.focus();

    }



    function seleccionarTutor(idTutor) {

      tutorActivo = findTutor(idTutor);

      renderTutores();

      if (!tutorActivo) return;

      prepararChatNuevo();

      closeMobileSidebar();

    }



    async function abrirConversacion(idConv) {

      const d = await apiGet('conversacion', { id_conversacion: idConv });

      if (d.status !== 'ok') {

        alert(d.message || 'Error al cargar conversación');

        return;

      }

      convActiva = d.conversacion;

      tutorActivo = findTutor(convActiva.id_tutor) || tutorActivo;

      renderConversaciones();

      renderTutores();

      actualizarCabeceraChat();



      const box = $('tutor-messages');

      if (box) box.innerHTML = '';

      (d.mensajes || []).forEach((m) => {

        const role = m.role || m.rol || '';

        if (role === 'user' || role === 'assistant') {

          appendMessage(role, m.mensaje);

        }

      });



      showChat(true);

      $('tutor-input')?.focus();

      closeMobileSidebar();

    }



    async function archivarConversacion(idConv) {

      if (!confirm('¿Archivar esta conversación? Ya no aparecerá en el historial activo.')) return;

      const d = await apiPost('archivar', { id_conversacion: idConv });

      if (d.status !== 'ok') {

        alert(d.message || 'No se pudo archivar');

        return;

      }

      if (convActiva && tutorId(convActiva.id_conversacion) === idConv) {

        convActiva = null;

        showChat(false);

      }

      await cargarConversaciones();

    }



    async function enviarMensaje(texto) {

      const msg = texto.trim();

      if (!msg) return;



      let idConv = convActiva ? tutorId(convActiva.id_conversacion) : 0;

      const idTutor = tutorActivo ? tutorId(tutorActivo.id_tutor) : 0;



      if (idConv <= 0 && idTutor <= 0) {

        alert('Seleccione un tutor para iniciar la conversación.');

        return;

      }



      appendMessage('user', msg);

      setTyping(true);

      try {

        const payload = { mensaje: msg };

        if (idConv > 0) payload.id_conversacion = idConv;

        else payload.id_tutor = idTutor;



        const d = await apiPost('mensaje', payload);

        if (d.status !== 'ok') {

          appendMessage('assistant', '⚠️ ' + (d.message || 'Error') + (d.hint ? ' ' + d.hint : ''));

          return;

        }



        if (d.id_conversacion && idConv <= 0) {

          convActiva = {

            id_conversacion: tutorId(d.id_conversacion),

            id_tutor: idTutor,

            tutor_nombre: tutorActivo?.nombre || '',

            especialidad: tutorActivo?.especialidad || '',

          };

          actualizarCabeceraChat();

        }



        appendMessage('assistant', d.respuesta || '');

        await cargarConversaciones();

      } catch (err) {

        const txt = err && err.message ? err.message : 'No se pudo contactar al tutor.';

        appendMessage('assistant', '⚠️ ' + txt);

        console.error('Tutor mensaje:', err);

      } finally {

        setTyping(false);

      }

    }



    function closeMobileSidebar() {

      $('tutor-sidebar')?.classList.remove('is-open');

    }



    const compose = $('tutor-compose');

    if (compose && !compose.dataset.tutorBound) {

      compose.dataset.tutorBound = '1';

      compose.addEventListener('submit', (e) => {

        e.preventDefault();

        const inp = $('tutor-input');

        const text = inp?.value || '';

        if (!text.trim()) return;

        if (inp) inp.value = '';

        enviarMensaje(text);

      });

    }



    const input = $('tutor-input');

    if (input && !input.dataset.tutorBound) {

      input.dataset.tutorBound = '1';

      input.addEventListener('keydown', (e) => {

        if (e.key === 'Enter' && !e.shiftKey) {

          e.preventDefault();

          compose?.requestSubmit();

        }

      });

    }



    const refreshBtn = $('tutor-btn-refresh');

    if (refreshBtn && !refreshBtn.dataset.tutorBound) {

      refreshBtn.dataset.tutorBound = '1';

      refreshBtn.addEventListener('click', () => cargarConversaciones());

    }



    const toggleArchBtn = $('tutor-btn-toggle-arch');

    if (toggleArchBtn && !toggleArchBtn.dataset.tutorBound) {

      toggleArchBtn.dataset.tutorBound = '1';

      toggleArchBtn.addEventListener('click', async () => {

        mostrarArchivadas = !mostrarArchivadas;

        toggleArchBtn.classList.toggle('is-active', mostrarArchivadas);

        toggleArchBtn.title = mostrarArchivadas ? 'Ver conversaciones activas' : 'Ver archivadas';

        await cargarConversaciones();

      });

    }



    const mobileToggle = $('tutor-mobile-toggle');

    if (mobileToggle && !mobileToggle.dataset.tutorBound) {

      mobileToggle.dataset.tutorBound = '1';

      mobileToggle.addEventListener('click', () => {

        $('tutor-sidebar')?.classList.toggle('is-open');

      });

    }



    showChat(false);



    (async function init() {

      await ensureCsrf();

      try {

        await cargarConversaciones();

        await cargarTutores();

      } catch (e) {

        const box = $('tutor-tutor-list');

        if (box) box.innerHTML = '<li class="tutor-empty">' + esc(e.message || 'No se pudo cargar el tutor') + '</li>';

        console.error('Tutor init:', e);

      }

    })();

  };

})();

