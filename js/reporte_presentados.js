(function () {
  const cfg = window.HAY_REPORTE_PRESENTADOS || {};
  const api = cfg.api || 'php/reporte_presentados_api.php';
  let bound = false;

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  function sliceDate(v) {
    return String(v ?? '').slice(0, 10);
  }

  async function cargar() {
    let url = api + '?';
    const desde = document.getElementById('rp-desde')?.value;
    const hasta = document.getElementById('rp-hasta')?.value;
    const asesor = document.getElementById('rp-asesor')?.value;
    if (desde) url += 'desde=' + encodeURIComponent(desde) + '&';
    if (hasta) url += 'hasta=' + encodeURIComponent(hasta) + '&';
    if (asesor) url += 'id_usuario_asesor=' + encodeURIComponent(asesor) + '&';

    const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
    const d = await r.json();
    const res = document.getElementById('rp-resumen');
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
        'Grupos con horario: ' + (d.resumen.grupos ?? 0) +
        (Number(d.resumen.grupos_sin_horario || 0) > 0
          ? ' · Sin horario: ' + d.resumen.grupos_sin_horario
          : '') +
        ' · Inscritos: ' + (d.resumen.total_inscritos ?? 0) +
        ' · Presentados: ' + (d.resumen.total_presentados ?? 0) +
        ' · %: ' + (d.resumen.pct_presentados ?? 0);
    }

    const tb = document.querySelector('#rp-tabla tbody');
    if (!tb) return;
    const tabla = document.getElementById('rp-tabla');
    if (tabla && window.HayDataTable?.destroyIn) {
      window.HayDataTable.destroyIn(tabla.closest('.catalog-table-wrap') || tabla.parentElement);
    }
    tb.innerHTML = '';
    (d.filas || []).forEach((f) => {
      const tr = document.createElement('tr');
      const pres = Number(f.presentado) === 1;
      tr.innerHTML =
        '<td>' + esc(f.grupo_clave) + '</td>' +
        '<td>' + sliceDate(f.fecha_inicio_grupo) + '</td>' +
        '<td>' + sliceDate(f.primer_dia_clase) + '</td>' +
        '<td>' + esc(f.numero_control) + '</td>' +
        '<td>' + esc(f.nombre) + '</td>' +
        '<td>' + sliceDate(f.fecha_inscripcion) + '</td>' +
        '<td>' + esc(f.asesor_nombre || '—') + '</td>' +
        '<td class="' + (pres ? 'rp-presentado-si' : 'rp-presentado-no') + '">' + (pres ? 'Sí' : 'No') + '</td>';
      tb.appendChild(tr);
    });
    if (tabla && window.HayDataTable && (d.filas || []).length > 0) {
      window.HayDataTable.init('#rp-tabla', { order: [[1, 'asc']], scrollX: false, pageLength: 25 });
    }
  }

  function bindEvents() {
    if (bound) return;
    document.getElementById('rp-buscar')?.addEventListener('click', cargar);
    bound = true;
  }

  window.hayReportePresentadosInit = function hayReportePresentadosInit() {
    bound = false;
    bindEvents();
    const desde = document.getElementById('rp-desde');
    const hasta = document.getElementById('rp-hasta');
    if (desde && !desde.value) desde.value = new Date().toISOString().slice(0, 8) + '01';
    if (hasta && !hasta.value) {
      const d = new Date();
      d.setMonth(d.getMonth() + 3);
      hasta.value = d.toISOString().slice(0, 10);
    }
    cargar().catch(() => {
      const res = document.getElementById('rp-resumen');
      if (res) {
        res.className = 'catalog-alert catalog-alert--error';
        res.textContent = 'Error de conexión.';
      }
    });
  };

  if (document.getElementById('rp-buscar')) {
    window.hayReportePresentadosInit();
  }
})();
