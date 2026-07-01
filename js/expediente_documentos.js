/**
 * Expediente documental — UI compartida (mi expediente, requisitos, consulta).
 */
const hayExpediente = (function () {
  function msg(el, text, ok) {
    if (!el) return;
    el.textContent = text;
    el.style.display = text ? 'block' : 'none';
    el.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--err');
  }

  function estadoBadge(est) {
    const map = {
      pendiente: '#f0ad4e',
      aprobado: '#28a745',
      rechazado: '#dc3545',
      exento: '#17a2b8',
    };
    const c = map[est] || '#666';
    return `<span style="background:${c};color:#fff;padding:2px 8px;border-radius:6px;font-size:12px;">${est || 'sin entrega'}</span>`;
  }

  function renderItems(container, items, stream, opts) {
    if (!items.length) {
      container.innerHTML = '<p style="color:#666;">No hay documentos requeridos para este perfil.</p>';
      return;
    }
    let html = '<table class="catalog-table"><thead><tr><th>Documento</th><th>Estado</th><th>Puntaje</th><th>Archivo</th><th>Acciones</th></tr></thead><tbody>';
    items.forEach(({ requisito: r, entrega: e, id_hay_area: idHayArea, area_nombre: areaNom }) => {
      const idEnt = e ? e.id_entrega : 0;
      const est = e ? e.estado : '';
      const punt = e && e.puntaje != null ? e.puntaje : '—';
      const ver = idEnt && e.ruta
        ? `<a href="${stream}?id=${idEnt}" target="_blank" rel="noopener">Ver</a>`
        : '—';
      let acc = '';
      if (opts.upload) {
        const idArea = item.id_hay_area || 0;
        acc += `<form class="exp-upload-form" data-req="${r.id_requisito}" data-area="${idArea}" style="display:inline;">
          <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="max-width:160px;">
          <button type="submit" class="secondary">Subir</button>
        </form>`;
      }
      if (opts.eval && idEnt) {
        acc += `<button type="button" class="primary exp-eval-btn" data-id="${idEnt}" data-est="aprobado">Aprobar</button>`;
        acc += `<button type="button" class="secondary exp-eval-btn" data-id="${idEnt}" data-est="rechazado">Rechazar</button>`;
        acc += `<button type="button" class="secondary exp-sync-btn" data-id="${idEnt}">Sync Moodle</button>`;
      }
      if (e && e.comentario_rechazo) {
        acc += `<p style="font-size:12px;color:#a00;margin:4px 0 0;">${e.comentario_rechazo}</p>`;
      }
      html += `<tr>
        <td><strong>${r.nombre}</strong>${areaNom ? '' : ''}<br><small>${r.descripcion || ''}</small>
          ${r.tipo_verificacion === 'certificacion' ? '<br><em>Certificación — puede omitir examen Moodle</em>' : ''}
        </td>
        <td>${estadoBadge(est)}</td>
        <td>${punt}${e && e.origen_puntaje ? ' (' + e.origen_puntaje + ')' : ''}</td>
        <td>${ver}</td>
        <td>${acc}</td>
      </tr>`;
    });
    html += '</tbody></table>';
    container.innerHTML = html;
  }

  async function postForm(api, fd) {
    const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    return res.json();
  }

  function initMi(cfg) {
    const lista = document.getElementById('exp-mi-lista');
    const sel = document.getElementById('exp-mi-entidad');
    const msgEl = document.getElementById('exp-mi-msg');

    async function load() {
      if (!sel || !lista) return;
      const [tipo, id] = sel.value.split(':');
      const url = `${cfg.api}?action=list_mine&tipo_entidad=${encodeURIComponent(tipo)}&id_entidad=${encodeURIComponent(id)}`;
      const data = await fetch(url, { credentials: 'same-origin' }).then((r) => r.json());
      if (data.status !== 'ok' || !data.expedientes || !data.expedientes[0]) {
        msg(msgEl, data.message || 'No se pudo cargar', false);
        return;
      }
      renderItems(lista, data.expedientes[0].items, cfg.stream, { upload: true });
      lista.querySelectorAll('.exp-upload-form').forEach((form) => {
        form.addEventListener('submit', async (ev) => {
          ev.preventDefault();
          const fd = new FormData(form);
          fd.append('action', 'upload');
          fd.append('id_requisito', form.dataset.req);
          fd.append('id_hay_area', form.dataset.area || '0');
          fd.append('tipo_entidad', tipo);
          fd.append('id_entidad', id);
          const d = await postForm(cfg.api, fd);
          msg(msgEl, d.message, d.status === 'ok');
          if (d.status === 'ok') load();
        });
      });
    }

    sel?.addEventListener('change', load);
    load();
  }

  function initRequisitos(cfg) {
    const dlg = document.getElementById('exp-req-dialog');
    const form = document.getElementById('exp-req-form');
    const msgEl = document.getElementById('exp-req-msg');

    document.getElementById('exp-req-nuevo')?.addEventListener('click', () => {
      form.reset();
      document.getElementById('exp-req-id').value = '0';
      document.getElementById('exp-req-titulo').textContent = 'Nuevo requisito';
      document.getElementById('exp-req-oblig').checked = true;
      document.getElementById('exp-req-activo').checked = true;
      dlg.showModal();
    });

    document.getElementById('exp-req-cancel')?.addEventListener('click', () => dlg.close());

    document.querySelectorAll('.exp-req-edit').forEach((btn) => {
      btn.addEventListener('click', () => {
        const r = JSON.parse(btn.dataset.row);
        document.getElementById('exp-req-id').value = r.id_requisito;
        document.getElementById('exp-req-clave').value = r.clave;
        document.getElementById('exp-req-nombre').value = r.nombre;
        document.getElementById('exp-req-desc').value = r.descripcion || '';
        document.getElementById('exp-req-cat').value = r.categoria;
        document.getElementById('exp-req-tipo').value = r.tipo_verificacion;
        document.getElementById('exp-req-moodle').value = r.moodle_course_id || '';
        document.getElementById('exp-req-umbral').value = r.umbral_aprobacion || 70;
        document.getElementById('exp-req-orden').value = r.orden || 100;
        let roles = r.roles_json;
        if (roles && typeof roles === 'string') {
          try { roles = JSON.parse(roles); } catch (_) { roles = []; }
        }
        document.getElementById('exp-req-roles').value = Array.isArray(roles) ? roles.join(',') : '';
        document.getElementById('exp-req-oblig').checked = !!+r.obligatorio;
        document.getElementById('exp-req-activo').checked = !!+r.activo;
        document.getElementById('exp-req-titulo').textContent = 'Editar requisito';
        dlg.showModal();
      });
    });

    form?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(form);
      if (!document.getElementById('exp-req-oblig').checked) fd.set('obligatorio', '0');
      if (!document.getElementById('exp-req-activo').checked) fd.set('activo', '0');
      const d = await postForm(cfg.api, fd);
      msg(msgEl, d.message, d.status === 'ok');
      if (d.status === 'ok' && typeof cargarSeccion === 'function') {
        cargarSeccion('expediente_requisitos');
      }
    });
  }

  function initConsulta(cfg) {
    const resBox = document.getElementById('exp-cons-resultados');
    const det = document.getElementById('exp-cons-detalle');
    const msgEl = document.getElementById('exp-cons-msg');
    const tipoIn = document.getElementById('exp-cons-tipo');
    const idIn = document.getElementById('exp-cons-id');

    async function loadDetalle(tipo, id) {
      if (!tipo || !id) return;
      const url = `${cfg.api}?action=consulta&tipo_entidad=${encodeURIComponent(tipo)}&id_entidad=${id}`;
      const data = await fetch(url, { credentials: 'same-origin' }).then((r) => r.json());
      if (data.status !== 'ok') {
        msg(msgEl, data.message, false);
        return;
      }
      renderItems(det, data.items, cfg.stream, { eval: cfg.puedeEvaluar });
      bindEvalActions();
    }

    function bindEvalActions() {
      det.querySelectorAll('.exp-eval-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const est = btn.dataset.est;
          let puntaje = null;
          let com = '';
          if (est === 'aprobado') {
            const p = prompt('Puntaje a registrar (opcional, ej. 85):');
            if (p !== null && p.trim() !== '') puntaje = parseFloat(p);
          } else {
            com = prompt('Motivo del rechazo (se inscribirá en examen Moodle si está configurado):') || '';
          }
          const fd = new FormData();
          fd.append('action', 'evaluar');
          fd.append('id_entrega', btn.dataset.id);
          fd.append('estado', est);
          if (puntaje != null) fd.append('puntaje', String(puntaje));
          if (est === 'rechazado') fd.append('comentario', com || '');
          const d = await postForm(cfg.api, fd);
          msg(msgEl, d.message, d.status === 'ok');
          if (d.status === 'ok') loadDetalle(tipoIn.value, idIn.value);
        });
      });
      det.querySelectorAll('.exp-sync-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const fd = new FormData();
          fd.append('action', 'sync_moodle');
          fd.append('id_entrega', btn.dataset.id);
          const d = await postForm(cfg.api, fd);
          msg(msgEl, d.message, d.status === 'ok');
          if (d.status === 'ok') loadDetalle(tipoIn.value, idIn.value);
        });
      });
    }

    document.getElementById('exp-cons-buscar')?.addEventListener('click', async () => {
      const q = document.getElementById('exp-cons-q')?.value.trim();
      if (!q) return;
      const data = await fetch(`${cfg.api}?action=buscar&q=${encodeURIComponent(q)}`, { credentials: 'same-origin' }).then((r) => r.json());
      if (!data.results || !data.results.length) {
        resBox.innerHTML = '<p>No hay resultados.</p>';
        return;
      }
      resBox.innerHTML = data.results.map((r) =>
        `<button type="button" class="secondary exp-pick" data-tipo="${r.tipo}" data-id="${r.id}" style="margin:4px;">${r.label}</button>`
      ).join('');
      resBox.querySelectorAll('.exp-pick').forEach((b) => {
        b.addEventListener('click', () => {
          tipoIn.value = b.dataset.tipo;
          idIn.value = b.dataset.id;
          loadDetalle(b.dataset.tipo, b.dataset.id);
        });
      });
    });

    if (tipoIn.value && idIn.value) {
      loadDetalle(tipoIn.value, idIn.value);
    }
  }

  return { initMi, initRequisitos, initConsulta };
})();
