(function () {
  const cfg = window.__hayVentasComisionAdmin || {};
  const api = cfg.api || 'php/ventas_comision_api.php';
  const soloLectura = cfg.soloLectura === true;

  let reglasItems = [];
  let ocultarComGerente = false;

  function $(id) {
    return document.getElementById(id);
  }

  function aplicarOcultarGerente() {
    const on = ocultarComGerente;
    document.querySelectorAll('.vc-col-gerente').forEach((el) => {
      el.style.display = on ? 'none' : '';
    });
    document.querySelectorAll('#vc-tabla-esp tbody td:nth-child(3)').forEach((el) => {
      el.style.display = on ? 'none' : '';
    });
    document.querySelectorAll('.vc-field-gerente').forEach((el) => {
      el.style.display = on ? 'none' : '';
    });
    const tabGer = $('vc-tab-gerente');
    const panelGer = $('vc-panel-gerente');
    if (tabGer) tabGer.style.display = on ? 'none' : '';
    if (on && panelGer && panelGer.style.display !== 'none') {
      showPanel('reglas');
    }
    const wrap = $('vc-admin-wrap');
    if (wrap) wrap.classList.toggle('vc-oculta-gerente', on);
  }

  function aplicarSoloLectura() {
    if (!soloLectura) return;
    const wrap = $('vc-admin-wrap');
    if (wrap) wrap.classList.add('vc-solo-lectura');
    $('vc-nuevo-tabulador')?.remove();
    $('vc-override-form')?.querySelector('#vc-guardar-override')?.remove();
    $('vc-tabulador-form')?.remove();
    $('vc-regla-guardar')?.remove();
    document.querySelectorAll('.vc-edit-regla').forEach((btn) => btn.remove());
  }

  function showMsg(text, ok) {
    const el = $('vc-admin-msg');
    if (!el) return;
    el.hidden = false;
    el.textContent = text;
    el.className = ok ? 'catalog-alert catalog-alert--ok' : 'catalog-alert catalog-alert--error';
  }

  function showPanel(name) {
    ['reglas', 'tabulador', 'override', 'gerente'].forEach((p) => {
      const el = $('vc-panel-' + p);
      if (el) el.style.display = p === name ? '' : 'none';
    });
    document.querySelectorAll('.vc-tab').forEach((b) => {
      b.classList.toggle('primary', b.dataset.tab === name);
      b.classList.toggle('active', b.dataset.tab === name);
    });
  }

  function openModalRegla() {
    const modal = $('vc-modal-regla');
    if (modal) modal.classList.add('is-open');
  }

  function closeModalRegla() {
    const modal = $('vc-modal-regla');
    if (modal) modal.classList.remove('is-open');
  }

  function openRegla(id) {
    const e = reglasItems.find((x) => parseInt(x.id_especialidad, 10) === id);
    if (!e) return;
    $('vc-regla-id').value = id;
    $('vc-regla-titulo').textContent = 'Reglas: ' + (e.nombre || '');
    $('vc-regla-tipo').value = e.ventas_tipo_comision || 'fija';
    $('vc-regla-ca').value = e.ventas_comision_asesor || 0;
    $('vc-regla-cap').value = e.ventas_comision_asesor_pct ?? '';
    $('vc-regla-cg').value = e.ventas_comision_gerente || 0;
    $('vc-regla-cgp').value = e.ventas_comision_gerente_pct ?? '';
    $('vc-regla-tab').checked = parseInt(e.ventas_cuenta_tabulador, 10) === 1;
    $('vc-regla-motivo').value = '';
    openModalRegla();
  }

  async function loadReglas() {
    const { data } = await hayFetchJson(api + '?action=especialidades_reglas');
    if (data.status !== 'ok') throw new Error(data.message || 'Error al cargar reglas');
    reglasItems = data.items || [];
    const tbody = $('vc-tabla-esp')?.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    reglasItems.forEach((e) => {
      const tr = document.createElement('tr');
      const ca = e.ventas_tipo_comision === 'pct_inscripcion' || e.ventas_tipo_comision === 'personalizado_pct'
        ? (e.ventas_comision_asesor_pct || '10') + '%'
        : '$' + Number(e.ventas_comision_asesor || 0).toFixed(2);
      tr.innerHTML =
        '<td>' + (e.nombre || e.clave) + '</td>' +
        '<td>' + ca + '</td>' +
        '<td>$' + Number(e.ventas_comision_gerente || 0).toFixed(2) + '</td>' +
        '<td>' + (parseInt(e.ventas_cuenta_tabulador, 10) ? 'Sí' : 'No') + '</td>' +
        '<td>' + (e.ventas_tipo_comision || 'fija') + '</td>' +
        '<td>' + (soloLectura ? '—' : '<button type="button" class="vc-edit-regla" data-id="' + e.id_especialidad + '">Editar</button>') + '</td>';
      tbody.appendChild(tr);
    });
  }

  async function loadTabuladores() {
    const { data } = await hayFetchJson(api + '?action=tabuladores_listar');
    if (data.status !== 'ok') throw new Error(data.message || 'Error al cargar tabuladores');
    const tbody = $('vc-tabla-tab')?.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    const sel = $('vc-ov-tab');
    if (sel) sel.innerHTML = '<option value="">— Seleccione —</option>';
    (data.items || []).forEach((t) => {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + t.nombre + '</td><td>' + t.periodo + '</td>' +
        '<td>' + t.vigente_desde + (t.vigente_hasta ? ' → ' + t.vigente_hasta : '') + '</td>' +
        '<td>' + (t.num_tramos || 0) + '</td><td></td>';
      tbody.appendChild(tr);
      if (sel) {
        const o = document.createElement('option');
        o.value = t.id_tabulador;
        o.textContent = t.nombre + ' (' + t.periodo + ')';
        sel.appendChild(o);
      }
    });
  }

  function addTramoRow(min, max, monto) {
    const div = document.createElement('div');
    div.className = 'vc-tramo-row';
    div.style.cssText = 'display:flex; gap:8px; margin:6px 0; flex-wrap:wrap;';
    div.innerHTML =
      'De <input type="number" class="vc-t-min" value="' + (min ?? 0) + '" min="0" style="width:70px;">' +
      ' a <input type="number" class="vc-t-max" value="' + (max ?? '') + '" placeholder="∞" style="width:70px;">' +
      ' inscrip. → $ <input type="number" class="vc-t-monto" value="' + (monto ?? 0) + '" step="0.01" style="width:100px;">';
    $('vc-tramos')?.appendChild(div);
  }

  async function loadOverrides() {
    const { data } = await hayFetchJson(api + '?action=overrides_listar');
    if (data.status !== 'ok') throw new Error(data.message || 'Error al cargar autorizaciones');
    const tbody = $('vc-tabla-ov')?.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    (data.items || []).forEach((o) => {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + (o.asesor_nombre || 'Todos') + '</td>' +
        '<td>' + o.fecha_desde + ' — ' + o.fecha_hasta + '</td>' +
        '<td>' + (o.tabulador_nombre || o.id_tabulador || '—') + '</td>' +
        '<td>' + (o.motivo || '') + '</td>';
      tbody.appendChild(tr);
    });
  }

  async function loadAsesores() {
    const { data } = await hayFetchJson(api + '?action=asesores_plantel');
    if (data.status !== 'ok') return;
    const sel = $('vc-ov-asesor');
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">— Todos —</option>';
    (data.items || []).forEach((u) => {
      const o = document.createElement('option');
      o.value = u.id_usuario;
      o.textContent = ((u.nombre || '') + ' ' + (u.apellido || '')).trim();
      sel.appendChild(o);
    });
    if (current) sel.value = current;
  }

  async function buscarGerente() {
    const periodo = $('vc-ger-periodo').value;
    const fecha = $('vc-ger-fecha').value;
    let url = api + '?action=liquidacion_gerente&periodo=' + encodeURIComponent(periodo);
    if (fecha) url += '&fecha=' + encodeURIComponent(fecha);
    const { data } = await hayFetchJson(url);
    if (data.status !== 'ok') throw new Error(data.message || 'Error al consultar');
    const d = data.data || {};
    $('vc-ger-resumen').innerHTML = '<strong>Total sobrecomisión: ' + (d.total_fmt || '') + '</strong> · ' + (d.periodo_label || '');
    const tbody = $('vc-tabla-ger')?.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    (d.por_asesor || []).forEach((r) => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + (r.asesor || '') + '</td><td>' + r.ops + '</td><td>$' + Number(r.total_gerente || 0).toFixed(2) + '</td>';
      tbody.appendChild(tr);
    });
  }

  function onWrapClick(ev) {
    const tab = ev.target.closest('.vc-tab');
    if (tab) {
      const name = tab.dataset.tab;
      showPanel(name);
      if (name === 'tabulador') loadTabuladores().catch((e) => showMsg(e.message, false));
      if (name === 'override') {
        Promise.all([loadTabuladores(), loadOverrides()]).catch((e) => showMsg(e.message, false));
      }
      if (name === 'gerente') buscarGerente().catch((e) => showMsg(e.message, false));
      return;
    }

    const edit = ev.target.closest('.vc-edit-regla');
    if (edit && !soloLectura) {
      openRegla(parseInt(edit.dataset.id, 10));
    }
  }

  function bindEvents() {
    const wrap = $('vc-admin-wrap');
    if (!wrap) return;
    if (wrap.dataset.vcBound === '1') return;
    wrap.dataset.vcBound = '1';

    wrap.addEventListener('click', onWrapClick);

    $('vc-nuevo-tabulador')?.addEventListener('click', () => {
      const form = $('vc-tabulador-form');
      if (form) form.hidden = false;
      const tramos = $('vc-tramos');
      if (tramos) tramos.innerHTML = '';
      addTramoRow(0, 4, 800);
      addTramoRow(5, 7, 1000);
      addTramoRow(8, '', 1200);
      if ($('vc-tab-desde') && !$('vc-tab-desde').value) {
        $('vc-tab-desde').value = new Date().toISOString().slice(0, 10);
      }
      form?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    $('vc-add-tramo')?.addEventListener('click', () => addTramoRow(0, '', 0));

    $('vc-guardar-tabulador')?.addEventListener('click', async () => {
      const tramos = [];
      document.querySelectorAll('.vc-tramo-row').forEach((row) => {
        tramos.push({
          min: row.querySelector('.vc-t-min')?.value,
          max: row.querySelector('.vc-t-max')?.value,
          monto: row.querySelector('.vc-t-monto')?.value,
        });
      });
      const fd = new FormData();
      fd.append('nombre', $('vc-tab-nombre').value || 'Tabulador');
      fd.append('periodo', $('vc-tab-periodo').value);
      fd.append('vigente_desde', $('vc-tab-desde').value);
      fd.append('cerrar_anteriores', $('vc-tab-cerrar').checked ? '1' : '');
      fd.append('tramos', JSON.stringify(tramos));
      try {
        const { data } = await hayFetchJson(api + '?action=tabulador_guardar', { method: 'POST', body: fd });
        if (data.status === 'ok') {
          showMsg(data.message || 'Tabulador guardado.', true);
          const form = $('vc-tabulador-form');
          if (form) form.hidden = true;
          await loadTabuladores();
        } else {
          showMsg(data.message || 'No se pudo guardar.', false);
        }
      } catch (e) {
        showMsg(e.message || 'Error al guardar tabulador.', false);
      }
    });

    $('vc-guardar-override')?.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('id_usuario_asesor', $('vc-ov-asesor').value);
      fd.append('fecha_desde', $('vc-ov-desde').value);
      fd.append('fecha_hasta', $('vc-ov-hasta').value);
      fd.append('periodo', $('vc-ov-periodo').value);
      fd.append('id_tabulador', $('vc-ov-tab').value);
      fd.append('motivo', $('vc-ov-motivo').value);
      try {
        const { data } = await hayFetchJson(api + '?action=override_guardar', { method: 'POST', body: fd });
        if (data.status === 'ok') {
          showMsg(data.message || 'Autorización registrada.', true);
          await loadOverrides();
        } else {
          showMsg(data.message || 'No se pudo registrar.', false);
        }
      } catch (e) {
        showMsg(e.message || 'Error al registrar autorización.', false);
      }
    });

    $('vc-regla-guardar')?.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('id_especialidad', $('vc-regla-id').value);
      fd.append('ventas_tipo_comision', $('vc-regla-tipo').value);
      fd.append('ventas_comision_asesor', $('vc-regla-ca').value);
      fd.append('ventas_comision_asesor_pct', $('vc-regla-cap').value);
      fd.append('ventas_comision_gerente', $('vc-regla-cg').value);
      fd.append('ventas_comision_gerente_pct', $('vc-regla-cgp').value);
      if ($('vc-regla-tab').checked) fd.append('ventas_cuenta_tabulador', '1');
      fd.append('motivo', $('vc-regla-motivo').value);
      try {
        const { data } = await hayFetchJson(api + '?action=regla_guardar', { method: 'POST', body: fd });
        if (data.status === 'ok') {
          showMsg(data.message || 'Reglas guardadas.', true);
          closeModalRegla();
          await loadReglas();
        } else {
          showMsg(data.message || 'No se pudo guardar.', false);
        }
      } catch (e) {
        showMsg(e.message || 'Error al guardar reglas.', false);
      }
    });

    $('vc-regla-cerrar')?.addEventListener('click', closeModalRegla);

    $('vc-ger-buscar')?.addEventListener('click', () => {
      buscarGerente().catch((e) => showMsg(e.message, false));
    });

    $('vc-ocultar-com-gerente')?.addEventListener('change', (ev) => {
      ocultarComGerente = !!ev.target.checked;
      aplicarOcultarGerente();
    });
  }

  window.hayVentasComisionAdminInit = function hayVentasComisionAdminInit() {
    if (!$('vc-admin-wrap')) return;
    closeModalRegla();
    const msg = $('vc-admin-msg');
    if (msg) msg.hidden = true;
    aplicarSoloLectura();
    bindEvents();
    const gf = $('vc-ger-fecha');
    if (gf && !gf.value) gf.value = new Date().toISOString().slice(0, 10);
    showPanel('reglas');
    loadReglas()
      .then(() => aplicarOcultarGerente())
      .catch((e) => showMsg(e.message || 'Error al cargar especialidades.', false));
    loadAsesores().catch(() => {});
  };
})();
