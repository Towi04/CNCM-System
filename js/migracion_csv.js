(function () {
  // Al navegar con cargarSeccion se reinyecta el HTML; no usar un flag global
  // permanente o los botones quedan sin listeners (Importar no dispara la API).
  const wrap = document.getElementById('migcsv-msg')?.closest('.catalog-wrap');
  if (!wrap) return;
  if (wrap.dataset.migcsvBound === '1') return;
  wrap.dataset.migcsvBound = '1';

  const cfg = window.HAY_MIGCSV || {};
  const api = cfg.api || 'php/migracion_csv_api.php';
  const idPlantel = cfg.id_plantel || 0;
  const defaultRoles = cfg.roles || [
    'profesor', 'asesor', 'gerente', 'coordinador',
    'recepcion', 'imagen', 'marketing',
    'admin', 'director', 'supervisor', 'manuales',
  ];
  const defaultRoleLabels = cfg.role_labels || {};

  /** @type {{headers:string[], rows:object[], total:number, offset:number, limit:number, roles:string[], roleLabels:object, especialidades?:object[], profesores?:object[], tipo?:string}|null} */
  let previewState = null;
  /** Overrides por email (staff) — persisten al paginar / reentrar */
  if (!window.__hayMigCsvOverrides) {
    window.__hayMigCsvOverrides = Object.create(null);
  }
  const overrides = window.__hayMigCsvOverrides;
  /** Overrides por clave_legado o id_grupo_legado (grupos) */
  if (!window.__hayMigCsvGrupoOverrides) {
    window.__hayMigCsvGrupoOverrides = Object.create(null);
  }
  const grupoOverrides = window.__hayMigCsvGrupoOverrides;

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

  function setBusy(busy, label) {
    ['migcsv-import', 'migcsv-preview', 'migcsv-provision', 'migcsv-revertir-grupos', 'migcsv-template', 'migcsv-export-legado'].forEach((id) => {
      const el = $(id);
      if (el) el.disabled = !!busy;
    });
    if (busy) {
      showMsg(label || 'Trabajando… no cierre ni cambie de sección.', true);
    }
  }

  function grupoBaseKey(row) {
    const clave = String(row.clave_legado || '').trim();
    if (clave) return clave;
    return String(row.id_grupo_legado || '').trim();
  }

  function profOverrideKey(row) {
    const base = grupoBaseKey(row);
    const line = row._line != null ? String(row._line) : (row.fila != null ? String(row.fila) : '');
    if (base && line) return base + '@L' + line;
    if (line) return 'L' + line;
    return base;
  }

  function applyOverrideToRow(row) {
    const email = String(row.email || '').toLowerCase();
    const ov = overrides[email];
    let out = row;
    if (ov) {
      out = Object.assign({}, out, {
        rol: ov.rol != null && ov.rol !== '' ? ov.rol : out.rol,
        nombres: ov.nombres != null && ov.nombres !== '' ? ov.nombres : out.nombres,
        apellido: ov.apellido != null && ov.apellido !== '' ? ov.apellido : out.apellido,
      });
    }
    const idLeg = grupoBaseKey(out);
    const gov = idLeg ? grupoOverrides[idLeg] : null;
    if (gov && gov.clave_especialidad) {
      out = Object.assign({}, out, { clave_especialidad: gov.clave_especialidad });
      if (out.esp_estado === 'falta' || out.esp_estado === 'desconocida') {
        out = Object.assign({}, out, {
          esp_estado: 'ok',
          accion: 'crear (esp. definida)',
          clave_nueva_sugerida: out.clave_nueva_sugerida || '(se genera al importar)',
        });
      }
    }
    const pKey = profOverrideKey(out);
    const pov = pKey ? grupoOverrides[pKey] : null;
    if (pov && pov.id_profesor) {
      const pid = String(pov.id_profesor);
      const profesores = (previewState && previewState.profesores) || [];
      let nombre = out.profesor_sugerido || '';
      for (let i = 0; i < profesores.length; i++) {
        if (String(profesores[i].id_usuario) === pid) {
          nombre = profesores[i].nombre || nombre;
          break;
        }
      }
      out = Object.assign({}, out, {
        profesor_id: pid,
        profesor_sugerido: nombre,
        prof_estado: 'ok',
      });
    }
    return out;
  }

  function setOverride(email, field, value) {
    const key = String(email || '').toLowerCase();
    if (!key) return;
    if (!overrides[key]) overrides[key] = {};
    overrides[key][field] = value;
  }

  function setGrupoOverride(idLegado, field, value) {
    const key = String(idLegado || '').trim();
    if (!key) return;
    if (!grupoOverrides[key]) grupoOverrides[key] = {};
    grupoOverrides[key][field] = value;
  }

  async function refreshEstado() {
    const el = $('migcsv-estado');
    const legEl = $('migcsv-legado-status');
    const selSuc = $('migcsv-sucursal');
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
      const leg = data.legado || {};
      if (legEl) {
        if (leg.ok) {
          legEl.innerHTML = 'Legado conectado: <code>' + (leg.db || 'ok') + '</code>. Elija tipo, pegue las <strong>claves</strong> de grupo y descargue.';
          legEl.style.color = '#0a7a32';
        } else {
          legEl.innerHTML = 'Sin legado: ' + (leg.error || 'configure LEGACY_DB_* en config.local.php') +
            '. Puede seguir subiendo CSV manualmente.';
          legEl.style.color = '#c47b00';
        }
      }
      if (selSuc && Array.isArray(leg.sucursales)) {
        const cur = selSuc.value || '0';
        selSuc.innerHTML = '<option value="0">Todas</option>';
        leg.sucursales.forEach((s) => {
          const opt = document.createElement('option');
          opt.value = String(s.id || '');
          opt.textContent = (s.id || '') + ' — ' + (s.nombre || '');
          selSuc.appendChild(opt);
        });
        selSuc.value = cur;
      }
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

        if (h === 'clave_especialidad' && (previewState.tipo === 'grupos' || headers.indexOf('esp_estado') >= 0)) {
          const idLeg = grupoBaseKey(row);
          const especialidades = (previewState && previewState.especialidades) || [];
          const sel = document.createElement('select');
          sel.className = 'migcsv-esp';
          sel.style.minWidth = '160px';
          const opt0 = document.createElement('option');
          opt0.value = '';
          opt0.textContent = '— Seleccionar —';
          sel.appendChild(opt0);
          const actual = String(row.clave_especialidad || '').toUpperCase();
          let found = false;
          especialidades.forEach((e) => {
            const clave = String(e.clave || e).toUpperCase();
            const nombre = e.nombre || clave;
            const opt = document.createElement('option');
            opt.value = clave;
            opt.textContent = clave + ' — ' + nombre;
            if (clave === actual) {
              opt.selected = true;
              found = true;
            }
            sel.appendChild(opt);
          });
          if (actual && !found) {
            const opt = document.createElement('option');
            opt.value = actual;
            opt.textContent = actual + ' (CSV)';
            opt.selected = true;
            sel.appendChild(opt);
          }
          if (row.esp_estado === 'falta' || row.esp_estado === 'desconocida') {
            sel.style.borderColor = '#c47b00';
            sel.title = 'Especialidad no identificada: elija una de HAY. Se generará clave nueva y se guardará la legado.';
          }
          sel.addEventListener('change', () => {
            if (!idLeg) {
              showMsg('Esta fila no tiene clave_legado; no se puede guardar el override.', false);
              return;
            }
            setGrupoOverride(idLeg, 'clave_especialidad', sel.value);
            // refrescar colores/acción en la fila
            renderPreview();
          });
          td.appendChild(sel);
        } else if (h === 'profesor_sugerido' && (previewState.tipo === 'grupos' || headers.indexOf('prof_estado') >= 0)) {
          const profesores = (previewState && previewState.profesores) || [];
          const sel = document.createElement('select');
          sel.className = 'migcsv-prof';
          sel.style.minWidth = '200px';
          sel.title = 'Profesor HAY. Por defecto el más parecido al CSV (ignora acentos/mayúsculas).';
          const opt0 = document.createElement('option');
          opt0.value = '';
          opt0.textContent = '— Sin profesor —';
          sel.appendChild(opt0);
          const actualId = row.profesor_id != null && row.profesor_id !== '' ? String(row.profesor_id) : '';
          let found = false;
          profesores.forEach((p) => {
            const id = String(p.id_usuario || '');
            const opt = document.createElement('option');
            opt.value = id;
            const user = p.username ? ' (' + p.username + ')' : '';
            opt.textContent = (p.nombre || ('#' + id)) + user;
            if (id && id === actualId) {
              opt.selected = true;
              found = true;
            }
            sel.appendChild(opt);
          });
          if (actualId && !found) {
            const opt = document.createElement('option');
            opt.value = actualId;
            opt.textContent = (row.profesor_sugerido || ('#' + actualId)) + ' (sugerido)';
            opt.selected = true;
            sel.appendChild(opt);
          }
          if (row.prof_estado === 'sin_match' || row.prof_estado === 'vacio') {
            sel.style.borderColor = '#c47b00';
          } else if (row.prof_estado === 'aproximado') {
            sel.style.borderColor = '#0b57d0';
            sel.title = 'Coincidencia aproximada con el CSV — verifique antes de importar.';
          }
          sel.addEventListener('change', () => {
            const key = profOverrideKey(row);
            if (!key) {
              showMsg('No se pudo guardar el profesor de esta fila.', false);
              return;
            }
            setGrupoOverride(key, 'id_profesor', sel.value);
            renderPreview();
          });
          td.appendChild(sel);
        } else if (h === 'rol') {
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
          if (h === 'esp_estado' && (v === 'falta' || v === 'desconocida')) {
            td.style.color = '#c47b00';
            td.style.fontWeight = '600';
          }
          if (h === 'prof_estado') {
            if (v === 'sin_match' || v === 'vacio') {
              td.style.color = '#c47b00';
              td.style.fontWeight = '600';
              td.title = 'No hubo coincidencia clara; elija el profesor en el desplegable.';
            } else if (v === 'aproximado') {
              td.style.color = '#0b57d0';
              td.title = 'Nombre parecido (acentos/mayúsculas/orden). Confirme en el desplegable.';
            } else if (v === 'ok') {
              td.style.color = '#0a7a32';
            }
          }
          if (h === 'grupo_estado') {
            if (v === 'faltante' || v === 'plantel') {
              td.style.color = '#b00020';
              td.style.fontWeight = '600';
              td.title = v === 'faltante'
                ? 'Esa clave de grupo no está en el mapa: reimporte 03_grupos incluyendo esa clave_legado.'
                : 'Plantel del alumno no resuelto.';
            } else if (v === 'ok') {
              td.style.color = '#0a7a32';
            }
          }
          if (h === 'profesor_csv' && v) {
            td.title = 'Texto original del CSV';
          }
          if (h === 'clave_legado' && v) {
            td.title = 'Clave legado: se guardará en grupos.clave_anterior';
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
        especialidades: data.especialidades || [],
        profesores: data.profesores || [],
        tipo: tipo,
      };
      // Preselección: si hay match fuzzy y el usuario no ha tocado la fila, guardar
      // override para que Importar use exactamente lo mostrado en el desplegable.
      if (tipo === 'grupos' && previewState.rows.length) {
        previewState.rows.forEach((raw) => {
          const key = profOverrideKey(raw);
          if (!key) return;
          if (grupoOverrides[key] && Object.prototype.hasOwnProperty.call(grupoOverrides[key], 'id_profesor')) {
            return;
          }
          if (raw.profesor_id) {
            setGrupoOverride(key, 'id_profesor', String(raw.profesor_id));
          }
        });
      }
      let text = data.message || ('Vista previa: ' + previewState.total + ' fila(s)');
      if (data.grupos_faltantes && data.grupos_faltantes.length) {
        text += ' — faltan grupos: ' + data.grupos_faltantes.slice(0, 10).join(', ')
          + (data.grupos_faltantes.length > 10 ? '…' : '');
      } else if (data.errores && data.errores.length) {
        text += ' — ' + data.errores.slice(0, 2).join('; ');
      }
      showMsg(text, !(data.grupos_faltantes && data.grupos_faltantes.length));
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
    let aviso = '¿Importar «' + file.name + '» como ' + tipo + '?';
    if (tipo === 'staff_emails') {
      aviso = '¿Importar personal y provisionar Google/Moodle?\nSe creará el correo en Google Workspace si no existe.\nSe usarán los roles/nombres que editó en la vista previa.\nCon ~100 filas puede tardar varios minutos.';
    } else if (tipo === 'grupos') {
      aviso = '¿Importar grupos?\nSe crearán con clave HAY nueva según la especialidad (CSV o la que eligió en la tabla).\nEl profesor será el del desplegable (por defecto el más parecido al CSV).\nLa clave_legado se guarda en clave_anterior y en el mapa de migración (el id interno es opcional).';
    } else if (tipo === 'alumnos') {
      aviso = '¿Importar alumnos?\nSe vincularán a los grupos ya migrados por clave_legado y se creará (si falta) usuario HAY, correo Google Workspace y Moodle con las mismas reglas del sistema (NC como usuario / correo institucional).';
    }
    if (!confirm(aviso)) return;
    const fd = new FormData();
    fd.append('action', 'import');
    fd.append('tipo', tipo);
    fd.append('id_plantel', String(idPlantel));
    fd.append('csv', file);
    if (tipo === 'staff_emails') {
      fd.append('overrides', JSON.stringify(overrides));
    } else if (tipo === 'grupos') {
      fd.append('overrides', JSON.stringify(grupoOverrides));
    }
    setBusy(true, 'Importando… (Google/Moodle por fila). Espere; en Network debe aparecer migracion_csv_api.php');
    try {
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const data = await r.json();
      let text = data.message || '';
      if (data.errores && data.errores.length) {
        text += ' — ' + data.errores.slice(0, 3).join('; ');
      }
      showMsg(text || (data.status === 'ok' ? 'Importación terminada' : 'Error al importar'), data.status === 'ok');
      refreshEstado();
    } catch (e) {
      showMsg('Error de red al importar (timeout o conexión). Revise Network → migracion_csv_api.php', false);
    } finally {
      setBusy(false);
    }
  });

  function updateTemplateLabel() {
    const tipo = $('migcsv-tipo')?.value || 'staff_emails';
    const label = $('migcsv-template-label');
    if (!label) return;
    const names = {
      staff_emails: 'Ejemplo personal correos',
      personal: 'Ejemplo 01 personal',
      aulas: 'Ejemplo 02 aulas',
      grupos: 'Ejemplo 03 grupos',
      alumnos: 'Ejemplo 04 alumnos',
      pagos: 'Ejemplo 05 pagos',
    };
    label.textContent = names[tipo] || 'CSV de ejemplo';
  }

  $('migcsv-tipo')?.addEventListener('change', updateTemplateLabel);
  updateTemplateLabel();

  $('migcsv-template')?.addEventListener('click', () => {
    const tipo = $('migcsv-tipo')?.value || 'staff_emails';
    const qs = new URLSearchParams({
      action: 'csv_ejemplo',
      tipo: tipo,
      id_plantel: String(idPlantel || 0),
    });
    window.location.href = api + '?' + qs.toString();
  });

  const FILTRO_KEY = 'hay_migcsv_filtro_grupos';
  const FILTRO_AL_KEY = 'hay_migcsv_filtro_alumnos';
  try {
    const saved = localStorage.getItem(FILTRO_KEY);
    if (saved && $('migcsv-filtro')) $('migcsv-filtro').value = saved;
    const savedAl = localStorage.getItem(FILTRO_AL_KEY);
    if (savedAl && $('migcsv-filtro-alumnos')) $('migcsv-filtro-alumnos').value = savedAl;
  } catch (e) { /* ignore */ }

  $('migcsv-filtro')?.addEventListener('change', () => {
    try {
      localStorage.setItem(FILTRO_KEY, $('migcsv-filtro').value || '');
    } catch (e) { /* ignore */ }
  });
  $('migcsv-filtro-alumnos')?.addEventListener('change', () => {
    try {
      localStorage.setItem(FILTRO_AL_KEY, $('migcsv-filtro-alumnos').value || '');
    } catch (e) { /* ignore */ }
  });

  /** Extrae id_alumno_legado de un CSV (columna o única columna). */
  function extractIdsAlumnoFromCsvText(text) {
    const lines = String(text || '').replace(/^\uFEFF/, '').split(/\r?\n/).filter((l) => l.trim() !== '');
    if (!lines.length) return [];
    const parseRow = (line) => {
      const out = [];
      let cur = '';
      let q = false;
      for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (ch === '"') {
          q = !q;
          continue;
        }
        if (ch === ',' && !q) {
          out.push(cur.trim());
          cur = '';
          continue;
        }
        cur += ch;
      }
      out.push(cur.trim());
      return out;
    };
    const header = parseRow(lines[0]).map((h) => h.toLowerCase());
    let col = header.indexOf('id_alumno_legado');
    let start = 1;
    if (col < 0) {
      // Sin encabezado: una columna de ids
      if (header.length === 1 && /^\d+$/.test(header[0])) {
        col = 0;
        start = 0;
      } else {
        return [];
      }
    }
    const ids = [];
    const seen = Object.create(null);
    for (let i = start; i < lines.length; i++) {
      const cols = parseRow(lines[i]);
      const v = String(cols[col] || '').trim();
      if (!v || !/^\d+$/.test(v) || seen[v]) continue;
      seen[v] = true;
      ids.push(v);
    }
    return ids;
  }

  $('migcsv-filtro-alumnos-file')?.addEventListener('change', async (ev) => {
    const file = ev.target?.files?.[0];
    if (!file) return;
    try {
      const text = await file.text();
      const ids = extractIdsAlumnoFromCsvText(text);
      if (!ids.length) {
        showMsg('No se encontró columna id_alumno_legado en el CSV.', false);
        return;
      }
      const ta = $('migcsv-filtro-alumnos');
      if (ta) {
        ta.value = ids.join('\n');
        try {
          localStorage.setItem(FILTRO_AL_KEY, ta.value);
        } catch (e) { /* ignore */ }
      }
      showMsg('Cargados ' + ids.length + ' id_alumno_legado desde ' + file.name, true);
    } catch (e) {
      showMsg('No se pudo leer el CSV de alumnos.', false);
    }
  });

  $('migcsv-export-legado')?.addEventListener('click', () => {
    const tipo = $('migcsv-tipo')?.value || 'grupos';
    const filtro = ($('migcsv-filtro')?.value || '').trim();
    const filtroAl = ($('migcsv-filtro-alumnos')?.value || '').trim();
    const idSuc = $('migcsv-sucursal')?.value || '0';
    if (['grupos', 'alumnos'].indexOf(tipo) >= 0 && !filtro) {
      showMsg('Pegue ids o claves de grupos (uno por línea) para exportar ' + tipo + ' desde el legado.', false);
      return;
    }
    if (tipo === 'pagos' && !filtroAl && !filtro) {
      showMsg('Para pagos pegue id_alumno_legado (o suba el CSV de alumnos), o use el filtro de grupos.', false);
      return;
    }
    try {
      localStorage.setItem(FILTRO_KEY, filtro);
      localStorage.setItem(FILTRO_AL_KEY, filtroAl);
    } catch (e) { /* ignore */ }
    const qs = new URLSearchParams({
      action: 'export_legado',
      tipo: tipo === 'staff_emails' ? 'personal' : tipo,
      id_sucursal: String(idSuc),
      id_plantel: String(idPlantel || 0),
      filtro: filtro,
      filtro_alumnos: filtroAl,
    });
    showMsg('Descargando CSV desde la BD legado del servidor…', true);
    window.location.href = api + '?' + qs.toString();
  });

  $('migcsv-provision')?.addEventListener('click', async () => {
    if (!confirm('¿Crear correos institucionales (si faltan) y usuarios Moodle para lo ya importado (plantel actual)?')) return;
    const fd = new FormData();
    fd.append('action', 'provision');
    fd.append('id_plantel', String(idPlantel));
    fd.append('limite', '200');
    setBusy(true, 'Provisionando cuentas… no cierre la sección.');
    try {
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const data = await r.json();
      let text = data.message || '';
      if (data.errores && data.errores.length) {
        text += ' — ' + data.errores.slice(0, 2).join('; ');
      }
      showMsg(text, data.status === 'ok');
      refreshEstado();
    } catch (e) {
      showMsg('Error de red en provisión', false);
    } finally {
      setBusy(false);
    }
  });

  $('migcsv-revertir-grupos')?.addEventListener('click', async () => {
    const aviso =
      'Esto BORRA del plantel actual los grupos creados por Migración CSV,\n' +
      'quita sus vínculos con alumnos y REINICIA el contador de claves (IS1, IK1…).\n\n' +
      'Luego podrá volver a importar 03_grupos limpio.\n\n' +
      '¿Continuar?';
    if (!confirm(aviso)) return;
    const typed = window.prompt('Escriba REVERTIR para confirmar (mayúsculas):', '');
    if (String(typed || '').trim().toUpperCase() !== 'REVERTIR') {
      showMsg('Cancelado: debe escribir REVERTIR', false);
      return;
    }
    const borrarAl = confirm(
      '¿También borrar alumnos (y pagos) creados por esta migración en el plantel?\n\n' +
      'Aceptar = sí, dejar limpio para reintentar alumnos.\n' +
      'Cancelar = solo grupos (los alumnos quedan desvinculados).'
    );
    const fd = new FormData();
    fd.append('action', 'revertir_grupos');
    fd.append('id_plantel', String(idPlantel));
    fd.append('confirm', 'REVERTIR');
    fd.append('borrar_alumnos', borrarAl ? '1' : '0');
    setBusy(true, 'Revirtiendo grupos migrados…');
    try {
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const data = await r.json();
      let text = data.message || '';
      if (data.errores && data.errores.length) {
        text += ' — ' + data.errores.slice(0, 3).join('; ');
      }
      showMsg(text, data.status === 'ok');
      refreshEstado();
    } catch (e) {
      showMsg('Error de red al revertir grupos', false);
    } finally {
      setBusy(false);
    }
  });

  refreshEstado();
})();
