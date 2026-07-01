(function () {
  const cfg = window.__hayVentasComisionConsulta || {};
  const api = cfg.api || 'php/ventas_comision_api.php';
  let bound = false;

  function fmt(n) {
    if (typeof n === 'string' && n.indexOf('$') >= 0) return n;
    return '$' + Number(n || 0).toFixed(2);
  }

  function showError(msg) {
    const res = document.getElementById('vc-resumen');
    if (!res) return;
    res.className = 'catalog-alert catalog-alert--error';
    res.textContent = msg || 'Error al cargar comisiones.';
  }

  async function cargar() {
    const periodo = document.getElementById('vc-periodo')?.value || 'semana';
    const fecha = document.getElementById('vc-fecha')?.value || '';
    let url = api + '?action=liquidacion_asesor&periodo=' + encodeURIComponent(periodo);
    if (fecha) url += '&fecha=' + encodeURIComponent(fecha);
    if (!cfg.esAsesor) {
      const idA = document.getElementById('vc-asesor')?.value;
      if (idA) url += '&id_usuario_asesor=' + encodeURIComponent(idA);
    }
    const { data } = await hayFetchJson(url);
    if (data.status !== 'ok') throw new Error(data.message || 'Error');
    const d = data.data || {};
    const res = document.getElementById('vc-resumen');
    if (res) {
      res.className = 'catalog-alert catalog-alert--ok';
      res.innerHTML =
        '<strong>' + (d.asesor || 'Asesor') + '</strong> · ' + (d.periodo_label || '') +
        '<br>Inscripciones para tabulador: <strong>' + (d.conteo_tabulador || 0) + '</strong>' +
        '<br>Sueldo base estimado: <strong>' + (d.sueldo_base_fmt || fmt(d.sueldo_base)) + '</strong>' +
        ' · Comisiones totales: <strong>' + (d.comisiones_total_fmt || fmt(d.comisiones_total)) + '</strong>' +
        '<br>Total estimado: <strong>' + (d.total_estimado_fmt || fmt(d.total_estimado)) + '</strong>';
    }
    const desg = document.getElementById('vc-desglose');
    if (desg) {
      const dt = d.desglose_tipo || {};
      const ins = dt.inscripcion || { ops: 0, comision_fmt: fmt(0) };
      const cert = dt.certificacion || { ops: 0, comision_fmt: fmt(0) };
      const per = dt.personalizado || { ops: 0, comision_fmt: fmt(0) };
      desg.innerHTML =
        '<span><strong>Inscripciones:</strong> ' + (ins.ops || 0) + ' · ' + (ins.comision_fmt || fmt(ins.comision)) + '</span>' +
        '<span><strong>Certificaciones:</strong> ' + (cert.ops || 0) + ' · ' + (cert.comision_fmt || fmt(cert.comision)) + '</span>' +
        '<span><strong>Personalizados:</strong> ' + (per.ops || 0) + ' · ' + (per.comision_fmt || fmt(per.comision)) + '</span>';
    }
    const tabla = document.getElementById('vc-tabla-movs');
    const tbody = document.querySelector('#vc-tabla-movs tbody');
    if (!tbody) return;
    if (tabla && window.HayDataTable?.destroyIn) {
      window.HayDataTable.destroyIn(tabla.closest('.catalog-table-wrap') || tabla.parentElement);
    }
    tbody.innerHTML = '';
    (d.movimientos || []).forEach((m) => {
      const tr = document.createElement('tr');
      let ref = m.numero_control || '—';
      if (m.tipo === 'certificacion') {
        ref += ' · ' + (m.cert_nombre || 'Certificación');
      } else {
        ref += ' · ' + (m.esp_nombre || m.grupo_clave || '');
      }
      tr.innerHTML =
        '<td>' + (m.creado_en || '').replace(' ', '<br>') + '</td>' +
        '<td>' + (m.tipo || '') + '</td>' +
        '<td>' + ref + '</td>' +
        '<td>' + fmt(m.monto_base) + '</td>' +
        '<td>' + fmt(m.comision_asesor) + '</td>' +
        '<td>' + (parseInt(m.cuenta_tabulador, 10) ? 'Sí' : 'No') + '</td>';
      tbody.appendChild(tr);
    });
    if (tabla && window.HayDataTable && (d.movimientos || []).length > 0) {
      window.HayDataTable.init('#vc-tabla-movs', { order: [[0, 'desc']], scrollX: false });
    }
  }

  function bindEvents() {
    if (bound) return;
    const btn = document.getElementById('vc-buscar');
    if (!btn) return;
    btn.addEventListener('click', () => {
      cargar().catch((e) => showError(e.message || 'Error'));
    });
    bound = true;
  }

  window.hayVentasComisionConsultaInit = function hayVentasComisionConsultaInit() {
    bound = false;
    bindEvents();
    const f = document.getElementById('vc-fecha');
    if (f && !f.value) f.value = new Date().toISOString().slice(0, 10);
    cargar().catch((e) => showError(e.message || 'Error al cargar comisiones.'));
  };

  if (document.getElementById('vc-consulta-wrap')) {
    window.hayVentasComisionConsultaInit();
  }
})();
