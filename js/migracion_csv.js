(function () {
  if (window.__hayMigCsvInit) return;
  window.__hayMigCsvInit = true;

  const cfg = window.HAY_MIGCSV || {};
  const api = cfg.api || 'php/migracion_csv_api.php';
  const idPlantel = cfg.id_plantel || 0;

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

  function renderPreview(headers, rows, total) {
    const thead = $('migcsv-thead');
    const tbody = $('migcsv-tbody');
    thead.innerHTML = '';
    const trh = document.createElement('tr');
    (headers || []).forEach((h) => {
      const th = document.createElement('th');
      th.textContent = h;
      trh.appendChild(th);
    });
    thead.appendChild(trh);
    tbody.innerHTML = '';
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="' + (headers.length || 1) + '" style="color:#888;">Sin filas</td></tr>';
      return;
    }
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      headers.forEach((h) => {
        const td = document.createElement('td');
        const v = row[h] == null ? '' : String(row[h]);
        td.textContent = v.length > 80 ? v.slice(0, 80) + '…' : v;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    if (total > rows.length) {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td colspan="' + headers.length + '" style="color:#888;">Mostrando ' +
        rows.length + ' de ' + total + ' filas</td>';
      tbody.appendChild(tr);
    }
  }

  $('migcsv-preview')?.addEventListener('click', async () => {
    const file = $('migcsv-file')?.files?.[0];
    if (!file) {
      showMsg('Seleccione un archivo CSV', false);
      return;
    }
    const fd = new FormData();
    fd.append('action', 'preview');
    fd.append('csv', file);
    try {
      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await r.json();
      if (data.status !== 'ok') {
        showMsg(data.message || 'Error', false);
        return;
      }
      showMsg('Vista previa: ' + data.total_filas + ' fila(s)', true);
      renderPreview(data.headers || [], data.muestra || [], data.total_filas || 0);
    } catch (e) {
      showMsg('Error de red', false);
    }
  });

  $('migcsv-import')?.addEventListener('click', async () => {
    const file = $('migcsv-file')?.files?.[0];
    const tipo = $('migcsv-tipo')?.value || '';
    if (!file) {
      showMsg('Seleccione un archivo CSV', false);
      return;
    }
    if (!confirm('¿Importar «' + file.name + '» como ' + tipo + '?')) return;
    const fd = new FormData();
    fd.append('action', 'import');
    fd.append('tipo', tipo);
    fd.append('id_plantel', String(idPlantel));
    fd.append('csv', file);
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

  $('migcsv-provision')?.addEventListener('click', async () => {
    if (!confirm('¿Crear correos institucionales y usuarios Moodle para lo ya importado (plantel actual)?')) return;
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
