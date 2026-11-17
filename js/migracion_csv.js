(function () {
  if (window.__hayMigCsvInit) return;
  window.__hayMigCsvInit = true;

  const cfg = window.HAY_MIGCSV || {};
  const api = cfg.api || 'php/migracion_csv_api.php';
  const idPlantel = cfg.id_plantel || 0;
  const defaultRoles = cfg.roles || [
    'profesor', 'asesor', 'gerente', 'coordinador',
    'recepcion', 'imagen', 'marketing',
    'admin', 'director', 'supervisor', 'manuales',
  ];
  const defaultRoleLabels = cfg.role_labels || {};

  /** @type {{headers:string[], rows:object[], total:number, offset:number, limit:number, roles:string[], roleLabels:object}|null} */
  let previewState = null;
  /** Overrides por email (persisten al cambiar de página) */
  const overrides = Object.create(null);

  function $(id) {
    return document.getElementById(id);
  }

  function showMsg(text, ok) {
    const el = $('migcsv-msg');
    if (!el) return;
    el.style.display = 'block';
    el.textContent = text || '';
    el.className = 'catalog-alert ' + (ok ? 'ok' : 'err');
  }

  function applyOverrideToRow(row) {
    const email = String(row.email || '').toLowerCase();
    const ov = overrides[email];
    if (!ov) return row;
    return Object.assign({}, row, {
      rol: ov.rol != null && ov.rol !== '' ? ov.rol : row.rol,
      nombres: ov.nombres != null && ov.nombres !== '' ? ov.nombres : row.nombres,
      apellido: ov.apellido != null && ov.apellido !== '' ? ov.apellido : row.apellido,
    });
  }

  function setOverride(email, field, value) {
    const key = String(email || '').toLowerCase();
    if (!key) return;
    if (!overrides[key]) overrides[key] = {};
    overrides[key][field] = value;
  }

  async function refreshEstado() {
    const el = $('migcsv-estado');
    try {
      const fd = new FormData();
      fd.append('action', 'estado');
      fd.append('id_plantel', String(idPlantel));
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await r.json();
      if (data.status !== 'ok') {
        el.textContent = data.message || 'No se pudo cargar estado';
        return;
      }
      const m = data.mapas || {};
      el.innerHTML =
        'Mapas: usuarios <strong>' + (m.usuario || 0) + '</strong> · ' +
        'grupos <strong>' + (m.grupo || 0) + '</strong> · ' +
        'kids CK <strong>' + (m.grupo_comp || 0) + '</strong> · ' +
        'alumnos <strong>' + (m.alumno || 0) + '</strong> · ' +
        'pagos <strong>' + (m.pago || 0) + '</strong>';
    } catch (e) {
      el.textContent = 'Error de red al cargar estado';
    }
  }

  function updatePager() {
    const info = $('migcsv-pager-info');
    const prev = $('migcsv-prev');
    const next = $('migcsv-next');
    if (!previewState) {
      if (info) info.textContent = '';
      if (prev) prev.disabled = true;
      if (next) next.disabled = true;
      return;
    }
    const from = previewState.total ? previewState.offset + 1 : 0;
    const to = Math.min(previewState.offset + previewState.rows.length, previewState.total);
    if (info) {
      info.textContent = 'Mostrando ' + from + '–' + to + ' de ' + previewState.total;
    }
    if (prev) prev.disabled = previewState.offset <= 0;
    if (next) next.disabled = previewState.offset + previewState.limit >= previewState.total;
  }

  function renderPreview() {
    const thead = $('migcsv-thead');
    const tbody = $('migcsv-tbody');
    if (!thead || !tbody || !previewState) return;
    const headers = previewState.headers || [];
    const roles = previewState.roles || defaultRoles;
    thead.innerHTML = '';
    const trh = document.createElement('tr');
    headers.forEach((h) => {
      const th = document.createElement('th');
      th.textContent = h;
      th.style.whiteSpace = 'nowrap';
      th.style.textAlign = 'left';
      th.style.padding = '8px 10px';
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    tbody.innerHTML = '';
    const rows = previewState.rows || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="' + (headers.length || 1) + '" style="color:#888;">Sin filas</td></tr>';
      updatePager();
      return;
    }
    rows.forEach((raw) => {
      const row = applyOverrideToRow(raw);
      const tr = document.createElement('tr');
      const email = String(row.email || '');
      headers.forEach((h) => {
        const td = document.createElement('td');
        td.style.whiteSpace = 'nowrap';
        td.style.textAlign = 'left';
        td.style.padding = '8px 10px';
        td.style.verticalAlign = 'middle';

        if (h === 'rol') {
          const sel = document.createElement('select');
          sel.className = 'migcsv-rol';
          sel.dataset.email = email;
          const labels = (previewState && previewState.roleLabels) || defaultRoleLabels;
          roles.forEach((r) => {
            const opt = document.createElement('option');
            opt.value = r;
            opt.textContent = labels[r] ? (labels[r] + ' (' + r + ')') : r;
            if (r === String(row.rol || 'profesor')) opt.selected = true;
            sel.appendChild(opt);
          });
          if (roles.indexOf(String(row.rol || '')) < 0 && row.rol) {
            const opt = document.createElement('option');
            opt.value = row.rol;
            opt.textContent = labels[row.rol] ? (labels[row.rol] + ' (' + row.rol + ')') : row.rol;
            opt.selected = true;
            sel.appendChild(opt);
          }
          sel.addEventListener('change', () => setOverride(email, 'rol', sel.value));
          td.appendChild(sel);
        } else if (h === 'nombres' || h === 'apellido') {
          const inp = document.createElement('input');
          inp.type = 'text';
          inp.value = row[h] == null ? '' : String(row[h]);
          inp.style.minWidth = '110px';
          inp.style.maxWidth = '180px';
          inp.dataset.email = email;
          inp.dataset.field = h;
          if (String(row.fuente_nombre || '') === 'email') {
            inp.title = 'Nombre inferido del correo — edítelo antes de importar si Google lo va a crear';
            inp.style.borderColor = '#c47b00';
          }
          inp.addEventListener('change', () => setOverride(email, h === 'nombres' ? 'nombres' : 'apellido', inp.value.trim()));
          inp.addEventListener('blur', () => setOverride(email, h === 'nombres' ? 'nombres' : 'apellido', inp.value.trim()));
          td.appendChild(inp);
        } else {
          const v = row[h] == null ? '' : String(row[h]);
          td.textContent = v.length > 120 ? v.slice(0, 120) + '…' : v;
          if (h === 'google' && /^error/i.test(v)) {
            td.style.color = '#b00020';
            td.title = v;
          } else if (h === 'google' && v === 'existe') {
            td.style.color = '#0a7a32';
          } else if (h === 'google' && v === 'crear') {
            td.style.color = '#0b57d0';
          }
          if (h === 'fuente_nombre' && v === 'email') {
            td.style.color = '#c47b00';
            td.title = 'Sin Google ni legado: se usó el correo. Edite nombres/apellido en esta tabla.';
          }
          if (h === 'accion' && /^ERROR/i.test(v)) {
            td.style.color = '#b00020';
          }
        }
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    updatePager();
  }

  async function loadPreview(offset) {
    const file = $('migcsv-file')?.files?.[0];
    const tipo = $('migcsv-tipo')?.value || '';
    if (!file) {
      showMsg('Seleccione un archivo CSV', false);
      return;
    }
    const limit = parseInt($('migcsv-page-size')?.value || '50', 10) || 50;
    const fd = new FormData();
    fd.append('action', 'preview');
    fd.append('tipo', tipo);
    fd.append('id_plantel', String(idPlantel));
    fd.append('offset', String(Math.max(0, offset || 0)));
    fd.append('limit', String(limit));
    fd.append('csv', file);
    showMsg('Cargando vista previa…', true);
    try {
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await r.json();
      if (data.status !== 'ok') {
        showMsg(data.message || 'Error', false);
        return;
      }
      previewState = {
        headers: data.headers || [],
        rows: data.muestra || [],
        total: data.total_filas || 0,
        offset: data.offset || 0,
        limit: data.limit || limit,
        roles: data.roles || defaultRoles,
        roleLabels: data.role_labels || defaultRoleLabels,
      };
      let text = data.message || ('Vista previa: ' + previewState.total + ' fila(s)');
      if (data.errores && data.errores.length) {
        text += ' — ' + data.errores.slice(0, 2).join('; ');
      }
      showMsg(text, true);
      renderPreview();
    } catch (e) {
      showMsg('Error de red', false);
    }
  }

  $('migcsv-preview')?.addEventListener('click', () => loadPreview(0));
  $('migcsv-prev')?.addEventListener('click', () => {
    if (!previewState) return;
    loadPreview(Math.max(0, previewState.offset - previewState.limit));
  });
  $('migcsv-next')?.addEventListener('click', () => {
    if (!previewState) return;
    loadPreview(previewState.offset + previewState.limit);
  });
  $('migcsv-page-size')?.addEventListener('change', () => loadPreview(0));

  $('migcsv-import')?.addEventListener('click', async () => {
    const file = $('migcsv-file')?.files?.[0];
    const tipo = $('migcsv-tipo')?.value || '';
    if (!file) {
      showMsg('Seleccione un archivo CSV', false);
      return;
    }
    const aviso = tipo === 'staff_emails'
      ? '¿Importar personal y provisionar Google/Moodle?\nSe creará el correo en Google Workspace si no existe.\nSe usarán los roles/nombres que editó en la vista previa.'
      : '¿Importar «' + file.name + '» como ' + tipo + '?';
    if (!confirm(aviso)) return;
    const fd = new FormData();
    fd.append('action', 'import');
    fd.append('tipo', tipo);
    fd.append('id_plantel', String(idPlantel));
    fd.append('csv', file);
    if (tipo === 'staff_emails') {
      fd.append('overrides', JSON.stringify(overrides));
    }
    try {
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await r.json();
      let text = data.message || '';
      if (data.errores && data.errores.length) {
        text += ' — ' + data.errores.slice(0, 3).join('; ');
      }
      showMsg(text, data.status === 'ok');
      refreshEstado();
    } catch (e) {
      showMsg('Error de red al importar', false);
    }
  });

  $('migcsv-template')?.addEventListener('click', () => {
    window.location.href = api + '?action=staff_template';
  });

  $('migcsv-provision')?.addEventListener('click', async () => {
    if (!confirm('¿Crear correos institucionales (si faltan) y usuarios Moodle para lo ya importado (plantel actual)?')) return;
    const fd = new FormData();
    fd.append('action', 'provision');
    fd.append('id_plantel', String(idPlantel));
    fd.append('limite', '200');
    try {
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await r.json();
      let text = data.message || '';
      if (data.errores && data.errores.length) {
        text += ' — ' + data.errores.slice(0, 2).join('; ');
      }
      showMsg(text, data.status === 'ok');
      refreshEstado();
    } catch (e) {
      showMsg('Error de red en provisión', false);
    }
  });

  refreshEstado();
})();
