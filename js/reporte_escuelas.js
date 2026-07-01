(function () {
  const cfg = window.HAY_REPORTE_ESCUELAS || {};
  const api = cfg.api || 'php/escuelas_api.php';
  let bound = false;

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  async function cargar() {
    let url = api + '?accion=reporte&';
    const desde = document.getElementById('re-desde')?.value;
    const hasta = document.getElementById('re-hasta')?.value;
    const escuela = document.getElementById('re-escuela')?.value;
    if (desde) url += 'desde=' + encodeURIComponent(desde) + '&';
    if (hasta) url += 'hasta=' + encodeURIComponent(hasta) + '&';
    if (escuela) url += 'id_escuela=' + encodeURIComponent(escuela) + '&';

    const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
    const d = await r.json();
    const res = document.getElementById('re-resumen');
    if (d.status === 'error') {
      if (res) {
        res.className = 'catalog-alert catalog-alert--error';
        res.textContent = d.message || 'Error al cargar.';
      }
      return;
    }
    if (res && d.resumen) {
      res.className = 'catalog-alert catalog-alert--ok';
      res.textContent =
        'Escuelas: ' + (d.resumen.escuelas ?? 0) +
        ' · Visitas: ' + (d.resumen.visitas ?? 0) +
        ' · Cartas: ' + (d.resumen.cartas_entregadas ?? 0) +
        ' · Pre-registros: ' + (d.resumen.preregistros ?? 0) +
        ' · Inscritos: ' + (d.resumen.inscritos ?? 0);
    }

    const tb = document.querySelector('#re-tabla tbody');
    if (!tb) return;
    const tabla = document.getElementById('re-tabla');
    if (tabla && window.HayDataTable?.destroyIn) {
      window.HayDataTable.destroyIn(tabla.closest('.catalog-table-wrap') || tabla.parentElement);
    }
    tb.innerHTML = '';
    (d.filas || []).forEach((f) => {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + esc(f.escuela) + '</td>' +
        '<td>' + esc(f.municipio || '—') + '</td>' +
        '<td>' + esc(f.visitas) + '</td>' +
        '<td>' + esc(f.cartas_entregadas) + '</td>' +
        '<td>' + esc(f.preregistros) + '</td>' +
        '<td>' + esc(f.inscritos) + '</td>';
      tb.appendChild(tr);
    });
    if (tabla && window.HayDataTable && (d.filas || []).length > 0) {
      window.HayDataTable.init('#re-tabla', { order: [[0, 'asc']], scrollX: false, pageLength: 25 });
    }
  }

  function bindEvents() {
    if (bound) return;
    document.getElementById('re-buscar')?.addEventListener('click', cargar);
    bound = true;
  }

  window.hayReporteEscuelasInit = function hayReporteEscuelasInit() {
    bound = false;
    bindEvents();
    const desde = document.getElementById('re-desde');
    const hasta = document.getElementById('re-hasta');
    if (desde && !desde.value) desde.value = new Date().toISOString().slice(0, 8) + '01';
    if (hasta && !hasta.value) hasta.value = new Date().toISOString().slice(0, 10);
    cargar().catch(() => {
      const res = document.getElementById('re-resumen');
      if (res) {
        res.className = 'catalog-alert catalog-alert--error';
        res.textContent = 'Error de conexión.';
      }
    });
  };

  if (document.getElementById('re-buscar')) {
    window.hayReporteEscuelasInit();
  }
})();
