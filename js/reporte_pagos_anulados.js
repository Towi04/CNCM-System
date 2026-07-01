(function () {
  'use strict';

  const $ = (sel) => document.querySelector(sel);
  const fmt = (n) => '$' + Number(n || 0).toFixed(2);

  function hoy() {
    return new Date().toISOString().slice(0, 10);
  }

  function inicioMes() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01';
  }

  function inicioSemana() {
    const d = new Date();
    const day = d.getDay();
    const diff = day === 0 ? 6 : day - 1;
    d.setDate(d.getDate() - diff);
    return d.toISOString().slice(0, 10);
  }

  function inicioAnio() {
    return new Date().getFullYear() + '-01-01';
  }

  function labelTipo(t) {
    return { anular: 'Anulación', editar_monto: 'Edición monto', editar_concepto: 'Edición concepto' }[t] || t;
  }

  async function cargar() {
    const desde = $('#rpa-desde')?.value || inicioMes();
    const hasta = $('#rpa-hasta')?.value || hoy();
    const res = await fetch('php/pago_supervisor_api.php?accion=reporte&desde=' + encodeURIComponent(desde) + '&hasta=' + encodeURIComponent(hasta));
    const data = await res.json();
    if (!data.ok) {
      alert(data.message || 'Error al cargar');
      return;
    }
    const r = data.data || {};
    const sum = r.resumen || {};
    const elRes = $('#rpa-resumen');
    if (elRes) {
      elRes.textContent =
        'Total movimientos: ' + (sum.total || 0) +
        ' · Anulaciones: ' + (sum.anulaciones || 0) +
        ' · Ediciones: ' + (sum.ediciones || 0);
    }

    const tbody = $('#rpa-tabla tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    (r.filas || []).forEach((f) => {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + (f.creado_en || '').replace(' ', '<br>') + '</td>' +
        '<td>' + labelTipo(f.tipo) + '</td>' +
        '<td>' + (f.numero_control || '') + '</td>' +
        '<td>' + (f.alumno_nombre || '') + '</td>' +
        '<td>' + (f.tipo || '') + ' · ' + (f.concepto || '') + '</td>' +
        '<td>' + fmt(f.monto) + '</td>' +
        '<td>' + (f.motivo || '') + '</td>' +
        '<td>' + (f.usuario_nombre || '') + '</td>';
      tbody.appendChild(tr);
    });
  }

  function bindEvents() {
    if (!$('#rpa-desde')) return;
    $('#rpa-buscar')?.addEventListener('click', cargar);
    $('#rpa-mes')?.addEventListener('click', () => {
      $('#rpa-desde').value = inicioMes();
      $('#rpa-hasta').value = hoy();
      cargar();
    });
    $('#rpa-semana')?.addEventListener('click', () => {
      $('#rpa-desde').value = inicioSemana();
      $('#rpa-hasta').value = hoy();
      cargar();
    });
    $('#rpa-anio')?.addEventListener('click', () => {
      $('#rpa-desde').value = inicioAnio();
      $('#rpa-hasta').value = hoy();
      cargar();
    });
  }

  window.hayReportePagosAnuladosInit = function hayReportePagosAnuladosInit() {
    if (!$('#rpa-desde')) return;
    $('#rpa-desde').value = inicioMes();
    $('#rpa-hasta').value = hoy();
    bindEvents();
    cargar();
  };

  if (document.getElementById('rpa-desde')) {
    window.hayReportePagosAnuladosInit();
  }
})();
