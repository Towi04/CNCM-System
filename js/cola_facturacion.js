(function () {
  const cfg = window.HAY_COLA_FACT_CONFIG || {};
  const api = cfg.api || 'php/cola_facturacion_api.php';
  const focusId = parseInt(cfg.focusId, 10) || 0;

  const elLista = document.getElementById('cola-fact-lista');
  const elLoading = document.getElementById('cola-fact-loading');
  const elMsg = document.getElementById('cola-fact-msg');
  const elTotal = document.getElementById('cola-fact-total');

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function showMsg(ok, text) {
    if (!elMsg) return;
    elMsg.style.display = 'block';
    elMsg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    elMsg.textContent = text || '';
  }

  function setLoading(on) {
    if (elLoading) elLoading.hidden = !on;
    if (elLista) elLista.style.opacity = on ? '0.5' : '1';
  }

  function missingClass(faltan, label) {
    return faltan.includes(label) ? ' is-missing' : '';
  }

  function renderItem(it) {
    const faltan = it.campos_faltantes || [];
    const id = it.id_preregistro;
    const open = focusId === id ? ' is-open is-focus' : '';
    let meta = it.estado_label || it.estado || '';
    if (it.numero_control) meta += ' · #' + it.numero_control;
    if (it.esp_nombre) meta += ' · ' + it.esp_nombre;
    if (it.telefono) meta += ' · Tel. ' + it.telefono;

    let html = '<article class="cola-fact-item' + open + '" data-id="' + id + '">';
    html += '<div class="cola-fact-item__head" data-toggle="' + id + '">';
    html += '<div><h3>' + esc(it.nombre || 'Sin nombre') + '</h3>';
    html += '<div class="cola-fact-item__meta">' + esc(meta) + '</div></div>';
    html += '<div class="cola-fact-item__meta">' + (faltan.length ? faltan.length + ' campo(s) pendiente(s)' : '') + '</div>';
    html += '</div>';

    if (it.campos_faltantes_txt) {
      html += '<p class="cola-fact-item__faltan"><i class="fas fa-exclamation-triangle"></i> Faltan: ' + esc(it.campos_faltantes_txt) + '</p>';
    }

    html += '<div class="cola-fact-item__body">';
    html += '<form class="cola-fact-form" data-id="' + id + '">';
    html += '<div class="cola-fact-grid">';

    const fields = [
      ['factura_rfc', 'RFC', 'text', 'RFC'],
      ['factura_curp', 'CURP', 'text', 'CURP'],
      ['factura_razon_social', 'Razón social', 'text', 'Razón social'],
      ['factura_correo', 'Correo fiscal', 'email', 'Correo fiscal'],
      ['factura_telefono', 'Teléfono fiscal', 'tel', ''],
      ['factura_domicilio_fiscal', 'Domicilio fiscal', 'text', 'Domicilio fiscal'],
    ];

    fields.forEach(([key, label, type, missingLabel]) => {
      const ml = missingLabel || label;
      html += '<div><label class="' + missingClass(faltan, ml).trim() + '">' + esc(label) + '</label>';
      html += '<input type="' + type + '" name="' + key + '" value="' + esc(it[key] || '') + '" class="' + missingClass(faltan, ml).trim() + '"></div>';
    });

    html += '<div style="grid-column:1/-1;"><label class="' + missingClass(faltan, 'Constancia de situación fiscal').trim() + '">Constancia de situación fiscal (PDF/JPG)</label>';
    html += '<input type="file" name="factura_constancia" accept="image/jpeg,image/png,image/webp,application/pdf">';
    if (it.factura_constancia_url) {
      html += '<p style="margin:6px 0 0; font-size:0.85rem;"><a href="' + esc(it.factura_constancia_url) + '" target="_blank" rel="noopener">Ver constancia actual</a></p>';
    }
    html += '</div></div>';

    html += '<div class="cola-fact-acciones">';
    html += '<button type="submit" class="primary"><i class="fas fa-save"></i> Guardar datos</button>';
    html += '<button type="button" class="secondary btn-cola-ficha" data-id="' + id + '"><i class="fas fa-user-edit"></i> Ficha completa</button>';
    if (it.id_alumno > 0 && it.numero_control) {
      html += '<button type="button" class="secondary btn-cola-alumno" data-control="' + esc(it.numero_control) + '"><i class="fas fa-id-card"></i> Ver alumno</button>';
    }
    html += '<button type="button" class="secondary btn-cola-quitar" data-id="' + id + '" style="margin-left:auto;">Quitar solicitud de factura</button>';
    html += '</div></form></div></article>';

    return html;
  }

  function renderItems(items) {
    if (!elLista) return;
    if (!items || items.length === 0) {
      elLista.innerHTML = '<div class="cola-fact-vacio"><i class="fas fa-check-circle"></i> No hay solicitudes de factura pendientes.</div>';
      return;
    }
    elLista.innerHTML = items.map(renderItem).join('');

    if (focusId > 0) {
      const el = elLista.querySelector('.cola-fact-item[data-id="' + focusId + '"]');
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }

  async function cargar() {
    setLoading(true);
    try {
      const { data } = await hayFetchJson(api + '?action=listar');
      if (data.status !== 'ok') {
        showMsg(false, data.message || 'Error al cargar');
        return;
      }
      if (elTotal) elTotal.textContent = String(data.total ?? 0);
      renderItems(data.items || []);
    } catch (e) {
      showMsg(false, e.message || 'Error de red');
    } finally {
      setLoading(false);
    }
  }

  async function guardar(form) {
    const fd = new FormData(form);
    fd.append('action', 'guardar');
    fd.append('id_preregistro', form.dataset.id || '');
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      await cargar();
    }
  }

  async function quitar(id) {
    if (!confirm('¿Quitar la solicitud de factura de este registro? No se borran los datos ya capturados.')) {
      return;
    }
    const fd = new FormData();
    fd.append('action', 'quitar');
    fd.append('id_preregistro', id);
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      await cargar();
    }
  }

  function bindEvents() {
    document.getElementById('cola-fact-refrescar')?.addEventListener('click', cargar);

    elLista?.addEventListener('click', (e) => {
      const toggle = e.target.closest('[data-toggle]');
      if (toggle && !e.target.closest('form') && !e.target.closest('button') && !e.target.closest('a') && !e.target.closest('input')) {
        toggle.closest('.cola-fact-item')?.classList.toggle('is-open');
        return;
      }

      const ficha = e.target.closest('.btn-cola-ficha');
      if (ficha && typeof cargarSeccion === 'function') {
        cargarSeccion('pre_registro_nuevo', new URLSearchParams({ id: ficha.dataset.id || '' }));
        return;
      }

      const alumno = e.target.closest('.btn-cola-alumno');
      if (alumno && typeof cargarSeccion === 'function') {
        cargarSeccion('consulta_adeudo', new URLSearchParams({ control: alumno.dataset.control || '' }));
        return;
      }

      const quitarBtn = e.target.closest('.btn-cola-quitar');
      if (quitarBtn) {
        quitar(quitarBtn.dataset.id || '');
      }
    });

    elLista?.addEventListener('submit', (e) => {
      const form = e.target.closest('.cola-fact-form');
      if (!form) return;
      e.preventDefault();
      guardar(form);
    });
  }

  bindEvents();
  cargar();
})();
