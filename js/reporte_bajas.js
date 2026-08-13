(function () {
  const cfg = window.HAY_REPORTE_BAJAS || {};
  const api = cfg.api || 'php/reporte_bajas_api.php';
  const $ = (id) => document.getElementById(id);

  function esc(value) {
    const d = document.createElement('div');
    d.textContent = value == null ? '' : String(value);
    return d.innerHTML;
  }

  function msg(text, ok) {
    const el = $('rep-bajas-msg');
    if (!el) return;
    el.hidden = !text;
    el.textContent = text || '';
    el.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
  }

  function fmtFecha(value) {
    if (!value) return '—';
    return String(value).slice(0, 10);
  }

  function render(data) {
    const resumen = data.resumen || {};
    const rango = data.rango || {};
    const box = $('rep-bajas-resumen');
    if (box) {
      box.textContent = (rango.label || '') + ' · Total: ' + (resumen.total || 0)
        + ' · Temporales: ' + (resumen.temporales || 0)
        + ' · Definitivas: ' + (resumen.definitivas || 0);
    }
    const tbody = $('rep-bajas-tabla')?.querySelector('tbody');
    if (!tbody) return;
    const items = data.items || [];
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="color:#888;">Sin bajas en el periodo.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map((a) => {
      const nombre = a.nombre_completo || '';
      const grupo = [a.grupo_clave, a.especialidad].filter(Boolean).join(' · ');
      return '<tr>'
        + '<td>' + esc(fmtFecha(a.fecha_baja_temporal)) + '</td>'
        + '<td><strong>' + esc(nombre) + '</strong><br><small>#' + esc(a.numero_control || '—') + '</small></td>'
        + '<td>' + esc(a.tipo_baja_label || '') + '</td>'
        + '<td>' + esc(fmtFecha(a.inscripcion_vigente_hasta)) + '</td>'
        + '<td>' + esc(a.motivo_baja_temporal || '—') + '</td>'
        + '<td>' + esc(grupo || '—') + '</td>'
        + '</tr>';
    }).join('');
  }

  async function cargar() {
    const periodo = $('rep-bajas-periodo')?.value || 'mes';
    const params = new URLSearchParams({ action: 'listar', periodo });
    if (periodo === 'rango') {
      if ($('rep-bajas-desde')?.value) params.set('desde', $('rep-bajas-desde').value);
      if ($('rep-bajas-hasta')?.value) params.set('hasta', $('rep-bajas-hasta').value);
    }
    msg('Cargando reporte...', true);
    try {
      const { data } = await hayFetchJson(api + '?' + params.toString());
      if (data.status !== 'ok') throw new Error(data.message || 'No se pudo cargar');
      render(data);
      msg('', true);
    } catch (e) {
      msg(e.message || 'Error al cargar reporte', false);
    }
  }

  $('rep-bajas-filtrar')?.addEventListener('click', cargar);
  $('rep-bajas-periodo')?.addEventListener('change', () => {
    const rango = $('rep-bajas-periodo').value === 'rango';
    if ($('rep-bajas-desde')) $('rep-bajas-desde').disabled = !rango;
    if ($('rep-bajas-hasta')) $('rep-bajas-hasta').disabled = !rango;
  });
  if ($('rep-bajas-desde')) $('rep-bajas-desde').disabled = true;
  if ($('rep-bajas-hasta')) $('rep-bajas-hasta').disabled = true;
  cargar();
})();
