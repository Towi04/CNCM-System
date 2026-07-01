(function () {
  const cfg = window.HAY_REPORTE_INSCRITOS || {};
  const api = cfg.api || 'php/reporte_inscritos_api.php';
  let bound = false;

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  async function cargar() {
    let url = api + '?';
    const desde = document.getElementById('ri-desde')?.value;
    const hasta = document.getElementById('ri-hasta')?.value;
    if (desde) url += 'desde=' + encodeURIComponent(desde) + '&';
    if (hasta) url += 'hasta=' + encodeURIComponent(hasta) + '&';
    if (!cfg.esAsesor) {
      const a = document.getElementById('ri-asesor')?.value;
      if (a) url += 'id_usuario_asesor=' + encodeURIComponent(a) + '&';
    }
    const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
    const d = await r.json();
    if (d.status === 'error') {
      const res = document.getElementById('ri-resumen');
      if (res) {
        res.className = 'catalog-alert catalog-alert--error';
        res.textContent = d.message || 'Error al cargar inscritos.';
      }
      return;
    }
    const res = document.getElementById('ri-resumen');
    if (res && d.resumen) {
      res.className = 'catalog-alert catalog-alert--ok';
      res.textContent =
        'Total: ' + d.resumen.total +
        ' · Con referido: ' + d.resumen.con_referido +
        ' · Sin referido: ' + d.resumen.sin_referido;
    }
    const tb = document.querySelector('#ri-tabla tbody');
    if (!tb) return;
    tb.innerHTML = '';
    const tabla = document.getElementById('ri-tabla');
    if (tabla && window.HayDataTable?.destroyIn) {
      window.HayDataTable.destroyIn(tabla.closest('.catalog-table-wrap') || tabla.parentElement);
    }
    (d.filas || []).forEach((f) => {
      const tr = document.createElement('tr');
      const fecha = (f.fecha_alta || '').slice(0, 10);
      tr.innerHTML =
        '<td>' + fecha + '</td>' +
        '<td>' + esc(f.numero_control) + '</td>' +
        '<td>' + esc(f.nombre) + '</td>' +
        '<td>' + esc(f.especialidad || '—') + '</td>' +
        '<td>' + esc(f.asesor_nombre || '—') + '</td>' +
        '<td>' + (f.id_referido ? 'Sí' : 'No') + '</td>' +
        '<td>' + (f.referidor_control ? esc(f.referidor_control + ' ' + (f.referidor_nombre || '')) : '—') + '</td>' +
        '<td>' + (f.monto_beneficio ? esc(Number(f.monto_beneficio).toFixed(2)) : '—') + '</td>';
      tb.appendChild(tr);
    });
    if (tabla && window.HayDataTable && (d.filas || []).length > 0) {
      window.HayDataTable.init('#ri-tabla', {
        order: [[0, 'desc']],
        scrollX: false,
        pageLength: 25,
      });
    }
  }

  function bindEvents() {
    if (bound) return;
    const btn = document.getElementById('ri-buscar');
    if (!btn) return;
    btn.addEventListener('click', cargar);
    bound = true;
  }

  window.hayReporteInscritosInit = function hayReporteInscritosInit() {
    bound = false;
    bindEvents();
    cargar().catch(() => {
      const res = document.getElementById('ri-resumen');
      if (res) {
        res.className = 'catalog-alert catalog-alert--error';
        res.textContent = 'Error de conexión al cargar inscritos.';
      }
    });
  };

  if (document.getElementById('ri-buscar')) {
    window.hayReporteInscritosInit();
  }
})();
