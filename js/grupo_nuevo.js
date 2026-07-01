(function () {
  const cfg = window.HAY_GRUPO_NUEVO || {};
  const api = cfg.api || 'php/grupo_nuevo_api.php';

  const HORAS_DEFECTO = {
    M: { inicio: '08:00', duracion: 2 },
    V: { inicio: '18:00', duracion: 2 },
    S: { inicio: '08:00', duracion: 4 },
    D: { inicio: '09:00', duracion: 4 },
  };

  let finEditadoManual = false;
  let bound = false;

  function $(id) {
    return document.getElementById(id);
  }

  function tipoGrupo() {
    return $('tipo-grupo')?.value || 'regular';
  }

  function horarioCodigo() {
    return $('codigo-horario')?.value || 'S';
  }

  function sumarHoras(hora, horas) {
    const p = String(hora || '08:00').split(':');
    const d = new Date();
    d.setHours(parseInt(p[0], 10) || 0, parseInt(p[1], 10) || 0, 0, 0);
    d.setTime(d.getTime() + horas * 3600000);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    return hh + ':' + mm;
  }

  function aplicarHorarioSugerido() {
    const h = horarioCodigo();
    const def = HORAS_DEFECTO[h] || HORAS_DEFECTO.S;
    const ini = $('gn-hora-inicio');
    const fin = $('gn-hora-fin');
    if (ini && !ini.dataset.userTouched) ini.value = def.inicio;
    if (fin && !finEditadoManual) fin.value = sumarHoras(ini?.value || def.inicio, def.duracion);
    toggleDiasUI();
  }

  function toggleDiasUI() {
    const h = horarioCodigo();
    const wrapLv = $('wrap-dias-semana');
    const wrapFin = $('wrap-dia-finde');
    if (!wrapLv || !wrapFin) return;
    if (h === 'M' || h === 'V') {
      wrapLv.style.display = '';
      wrapFin.style.display = 'none';
    } else {
      wrapLv.style.display = 'none';
      wrapFin.style.display = '';
      wrapFin.textContent = h === 'S'
        ? 'Clase los sábados (un día por semana).'
        : 'Clase los domingos (un día por semana).';
    }
  }

  function areaCodigo() {
    return $('codigo-area')?.value || 'I';
  }

  function requiereMultiDocente() {
    const t = tipoGrupo();
    const area = areaCodigo();
    if (area === 'PA' || area === 'PE') return true;
    if (t === 'personalizado' || t === 'extensivo') return true;
    return false;
  }

  function profOptionsHtml(selected) {
    const profs = cfg.profesores || [];
    let html = '<option value="">— Sin asignar —</option>';
    profs.forEach((p) => {
      const sel = String(selected) === String(p.id) ? ' selected' : '';
      html += '<option value="' + p.id + '"' + sel + '>' + escHtml(p.label) + '</option>';
    });
    return html;
  }

  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function renderDocentesMulti() {
    const wrap = $('wrap-docentes-multi');
    const simple = $('wrap-profesor-simple');
    const tabla = $('gn-docentes-tabla');
    if (!wrap || !tabla) return;
    const multi = requiereMultiDocente();
    wrap.style.display = multi ? 'block' : 'none';
    if (simple) simple.style.display = multi ? 'none' : 'block';
    if (!multi) return;

    const materias = cfg.materiasPrep || {};
    const area = areaCodigo();
    const keys = area === 'PA' || area === 'PE' ? Object.keys(materias) : [];
    if (tabla.dataset.built === area && tabla.childElementCount > 0) return;
    tabla.innerHTML = '';
    tabla.dataset.built = area;

    const addRow = (clave, nombre, idx) => {
      const row = document.createElement('div');
      row.className = 'gn-docente-row';
      row.style.cssText = 'display:grid; grid-template-columns: 1fr 1.4fr auto; gap:8px; align-items:center;';
      const isPrep = clave !== '' && materias[clave];
      row.innerHTML =
        (isPrep
          ? '<input type="hidden" name="docente_materia[]" value="' + escHtml(nombre) + '"><span><strong>' + escHtml(nombre) + '</strong></span>'
          : '<input type="text" name="docente_materia[]" placeholder="Materia" value="' + escHtml(nombre) + '" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:8px;">') +
        '<select name="docente_profesor[]" class="gn-docente-prof" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:8px;">' +
        profOptionsHtml('') +
        '</select>' +
        '<label style="white-space:nowrap; font-size:0.85rem;"><input type="radio" name="docente_titular_idx" value="' + idx + '"' + (idx === 0 ? ' checked' : '') + '> Titular</label>';
      tabla.appendChild(row);
    };

    if (keys.length) {
      keys.forEach((k, i) => addRow(k, materias[k], i));
    } else {
      addRow('', '', 0);
    }
  }

  function addMateriaRow() {
    const tabla = $('gn-docentes-tabla');
    if (!tabla) return;
    const idx = tabla.querySelectorAll('.gn-docente-row').length;
    const row = document.createElement('div');
    row.className = 'gn-docente-row';
    row.style.cssText = 'display:grid; grid-template-columns: 1fr 1.4fr auto; gap:8px; align-items:center;';
    row.innerHTML =
      '<input type="text" name="docente_materia[]" placeholder="Materia" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:8px;">' +
      '<select name="docente_profesor[]" class="gn-docente-prof" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:8px;">' +
      profOptionsHtml('') +
      '</select>' +
      '<label style="white-space:nowrap; font-size:0.85rem;"><input type="radio" name="docente_titular_idx" value="' + idx + '"> Titular</label>';
    tabla.appendChild(row);
  }

  function toggleTipoUI() {
    const t = tipoGrupo();
    const per = t === 'personalizado';
    const ext = t === 'extensivo';
    const area = $('codigo-area')?.value || 'I';
    const infantil = area === 'K' && !per && !ext;
    $('wrap-per').style.display = per ? 'block' : 'none';
    $('wrap-area').style.display = per ? 'none' : 'block';
    $('wrap-horario').style.display = per ? 'none' : 'block';
    $('wrap-horas').style.display = per ? 'none' : 'grid';
    $('wrap-dias-semana').style.display = per ? 'none' : ($('wrap-dias-semana').style.display || 'none');
    $('wrap-dia-finde').style.display = per ? 'none' : ($('wrap-dia-finde').style.display || 'none');
    const hint = $('gn-infantil-hint');
    if (hint) hint.style.display = infantil ? 'block' : 'none';
    renderDocentesMulti();
    refreshPreview();
    cargarFases();
  }

  async function refreshPreview() {
    const prev = $('clave-preview');
    const hint = $('clave-prefijo-hint');
    if (!prev) return;
    const params = new URLSearchParams({
      action: 'preview_clave',
      tipo_grupo: tipoGrupo(),
      codigo_area: $('codigo-area')?.value || 'I',
      codigo_horario: horarioCodigo(),
      nombre_personalizado: $('nombre-per')?.value || '',
    });
    try {
      const { data } = await hayFetchJson(api + '?' + params.toString());
      if (data.status === 'ok' && data.preview) {
        prev.value = data.preview.clave || '';
        if (hint) {
          if (data.preview.es_pareja_infantil) {
            hint.textContent = 'Prefijo pareja infantil · siguiente: ' + (data.preview.clave_ingles || '') + ' + ' + (data.preview.clave_computacion || '');
          } else {
            hint.textContent = 'Prefijo: ' + (data.preview.prefijo || '');
          }
        }
      }
    } catch (e) {
      prev.value = '?';
    }
  }

  async function cargarFases() {
    const sel = $('gn-fase-select');
    const hid = $('gn-id-especialidad');
    if (!sel) return;
    const area = $('codigo-area')?.value || 'I';
    try {
      const { data } = await hayFetchJson(api + '?action=fases_especialidad&codigo_area=' + encodeURIComponent(area));
      if (data.status !== 'ok') return;
      if (hid) hid.value = data.id_especialidad || '';
      sel.innerHTML = '';
      (data.fases || []).forEach((f, idx) => {
        const o = document.createElement('option');
        o.value = f.id_fase;
        o.textContent = (f.clave_fase ? f.clave_fase + ' — ' : '') + (f.nombre_fase || 'Fase');
        if (idx === 0) o.dataset.primera = '1';
        sel.appendChild(o);
      });
      if (!data.fases || !data.fases.length) {
        sel.innerHTML = '<option value="">Sin fases configuradas</option>';
      }
    } catch (e) {
      sel.innerHTML = '<option value="">Error al cargar fases</option>';
    }
  }

  function bindEvents() {
    if (bound) return;
    const wrap = $('grupo-nuevo-wrap');
    if (!wrap) return;
    bound = true;

    ['tipo-grupo', 'codigo-area', 'codigo-horario', 'nombre-per'].forEach((id) => {
      $(id)?.addEventListener('change', () => {
        if (id === 'codigo-horario') {
          finEditadoManual = false;
          $('gn-hora-inicio')?.removeAttribute('data-user-touched');
          aplicarHorarioSugerido();
        }
        if (id === 'tipo-grupo') toggleTipoUI();
        else refreshPreview();
        if (id === 'codigo-area') {
          cargarFases();
          const tabla = $('gn-docentes-tabla');
          if (tabla) {
            delete tabla.dataset.built;
          }
          renderDocentesMulti();
        }
      });
      $(id)?.addEventListener('input', () => {
        refreshPreview();
      });
    });

    $('gn-hora-inicio')?.addEventListener('change', () => {
      $('gn-hora-inicio').dataset.userTouched = '1';
      if (!finEditadoManual) {
        const h = horarioCodigo();
        const def = HORAS_DEFECTO[h] || HORAS_DEFECTO.S;
        $('gn-hora-fin').value = sumarHoras($('gn-hora-inicio').value, def.duracion);
      }
    });

    $('gn-hora-fin')?.addEventListener('change', () => {
      finEditadoManual = true;
    });

    $('gn-grupo-avanzado')?.addEventListener('change', function () {
      const wrapF = $('wrap-fase-avanzada');
      const sel = $('gn-fase-select');
      if (wrapF) wrapF.style.display = this.checked ? 'block' : 'none';
      if (sel) {
        if (this.checked) sel.setAttribute('required', 'required');
        else sel.removeAttribute('required');
      }
    });

    $('gn-btn-add-materia')?.addEventListener('click', addMateriaRow);

    $('form-grupo-nuevo')?.addEventListener('submit', function (ev) {
      const t = tipoGrupo();
      if (t !== 'personalizado') {
        const h = horarioCodigo();
        if ((h === 'M' || h === 'V') && !document.querySelector('.gn-dia-lv:checked')) {
          ev.preventDefault();
          alert('Seleccione al menos un día de lunes a viernes.');
          return;
        }
      }
      if ($('gn-grupo-avanzado')?.checked && !($('gn-fase-select')?.value)) {
        ev.preventDefault();
        alert('Seleccione la fase inicial del grupo avanzado.');
      }
    });
  }

  window.hayGrupoNuevoInit = function hayGrupoNuevoInit() {
    bound = false;
    finEditadoManual = false;
    bindEvents();
    const fi = $('gn-fecha-inicio');
    if (fi && !fi.value) fi.value = new Date().toISOString().slice(0, 10);
    toggleTipoUI();
    aplicarHorarioSugerido();
    cargarFases();
    refreshPreview();
  };

  if ($('grupo-nuevo-wrap')) {
    window.hayGrupoNuevoInit();
  }
})();
