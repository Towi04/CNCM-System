<?php
require_once __DIR__ . '/../config.php';

$puedeConfirmar = pago_transferencia_puede_confirmar();
$puedeVer = pago_transferencia_puede_ver();
if (!$puedeVer) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

$hoy = date('Y-m-d');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-university"></i> Transferencias bancarias</h2>
    <p style="color:#666;margin:0;">
      <?php if ($puedeConfirmar): ?>
        Confirme o rechace depósitos pendientes. Solo las confirmadas cuentan en adeudo y corte del día.
      <?php else: ?>
        Consulte y imprima comprobantes de transferencia como evidencia del corte.
      <?php endif; ?>
    </p>
  </div>

  <div class="catalog-toolbar" style="flex-wrap:wrap; gap:10px;">
    <div class="field"><label>Desde</label><input type="date" id="tr-desde" value="<?php echo htmlspecialchars($hoy); ?>"></div>
    <div class="field"><label>Hasta</label><input type="date" id="tr-hasta" value="<?php echo htmlspecialchars($hoy); ?>"></div>
    <?php if (!$puedeConfirmar): ?>
    <div class="field">
      <label>Estado</label>
      <select id="tr-estado">
        <option value="">Todos</option>
        <option value="pendiente">Pendiente</option>
        <option value="confirmado" selected>Confirmado</option>
        <option value="rechazado">Rechazado</option>
      </select>
    </div>
    <?php endif; ?>
    <button type="button" class="primary" id="tr-buscar">Actualizar</button>
    <button type="button" class="secondary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
  </div>

  <?php if ($puedeConfirmar): ?>
  <h3 style="margin:16px 0 8px;">Cola de confirmación</h3>
  <div id="tr-msg" class="catalog-alert" style="display:none;margin-bottom:12px;"></div>
  <div class="catalog-table-wrap hay-dt-panel">
    <table class="catalog-table" id="tr-tabla-pendientes">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Control</th>
          <th>Alumno</th>
          <th>Monto</th>
          <th>Cuenta</th>
          <th>Folio</th>
          <th>Comprobante</th>
          <th>Registró</th>
          <th class="no-print">Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
  <?php endif; ?>

  <h3 style="margin:20px 0 8px;">Comprobantes / evidencia</h3>
  <div class="catalog-table-wrap hay-dt-panel">
    <table class="catalog-table" id="tr-tabla-comprobantes">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Control</th>
          <th>Alumno</th>
          <th>Monto</th>
          <th>Cuenta</th>
          <th>Estado</th>
          <th>Folio</th>
          <th>Comprobante</th>
          <th>Confirmó</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const puedeConfirmar = <?php echo $puedeConfirmar ? 'true' : 'false'; ?>;
  const msg = document.getElementById('tr-msg');

  function showMsg(text, ok) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text || '';
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  async function api(accion, extra = {}, method = 'GET') {
    const params = new URLSearchParams({ accion, ...extra });
    const opts = { method, headers: { 'X-Requested-With': 'fetch' } };
    let url = 'php/pago_transferencia_api.php';
    if (method === 'GET') {
      url += '?' + params.toString();
    } else {
      opts.body = params;
    }
    const res = await fetch(url, opts);
    return res.json();
  }

  async function cargarPendientes() {
    const tbody = document.querySelector('#tr-tabla-pendientes tbody');
    if (!tbody) return;
    const desde = document.getElementById('tr-desde')?.value || '';
    const hasta = document.getElementById('tr-hasta')?.value || '';
    const data = await api('listar_pendientes', { desde, hasta });
    if (!data.ok) {
      showMsg(data.message || 'Error', false);
      return;
    }
    const rows = data.data || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#666;">Sin transferencias pendientes</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((r) => {
      const comp = r.comprobante_url
        ? `<a href="${esc(r.comprobante_url)}" target="_blank" rel="noopener">Ver</a>`
        : '—';
      return `<tr data-id="${esc(r.id_pago)}">
        <td>${esc(r.creado_en || '')}</td>
        <td>${esc(r.numero_control || r.matricula || '')}</td>
        <td>${esc(r.alumno_nombre_completo || '')}</td>
        <td>${esc(r.monto_fmt || r.monto)}</td>
        <td>${esc(r.cuenta_banco_label || r.cuenta_banco || '')}</td>
        <td>${esc(r.folio || '')}</td>
        <td>${comp}</td>
        <td>${esc(r.registro_nombre || '')}</td>
        <td class="no-print">
          <button type="button" class="primary tr-ok" data-id="${esc(r.id_pago)}">Confirmar</button>
          <button type="button" class="secondary tr-no" data-id="${esc(r.id_pago)}">Rechazar</button>
        </td>
      </tr>`;
    }).join('');
  }

  async function cargarComprobantes() {
    const tbody = document.querySelector('#tr-tabla-comprobantes tbody');
    if (!tbody) return;
    const desde = document.getElementById('tr-desde')?.value || '';
    const hasta = document.getElementById('tr-hasta')?.value || '';
    const estadoEl = document.getElementById('tr-estado');
    const estado = estadoEl ? estadoEl.value : '';
    const data = await api('listar_comprobantes', { desde, hasta, estado });
    if (!data.ok) {
      tbody.innerHTML = `<tr><td colspan="9">${esc(data.message || 'Error')}</td></tr>`;
      return;
    }
    const rows = data.data || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#666;">Sin registros</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((r) => {
      const comp = r.comprobante_url
        ? `<a href="${esc(r.comprobante_url)}" target="_blank" rel="noopener">Ver / imprimir</a>`
        : '—';
      return `<tr>
        <td>${esc(r.creado_en || '')}</td>
        <td>${esc(r.numero_control || r.matricula || '')}</td>
        <td>${esc(r.alumno_nombre_completo || '')}</td>
        <td>${esc(r.monto_fmt || r.monto)}</td>
        <td>${esc(r.cuenta_banco_label || r.cuenta_banco || '')}</td>
        <td>${esc(r.transfer_estado || '')}</td>
        <td>${esc(r.folio || '')}</td>
        <td>${comp}</td>
        <td>${esc(r.confirmo_nombre || '')}</td>
      </tr>`;
    }).join('');
  }

  async function refrescar() {
    if (puedeConfirmar) await cargarPendientes();
    await cargarComprobantes();
  }

  document.getElementById('tr-buscar')?.addEventListener('click', refrescar);

  document.querySelector('#tr-tabla-pendientes')?.addEventListener('click', async (e) => {
    const okBtn = e.target.closest('.tr-ok');
    const noBtn = e.target.closest('.tr-no');
    if (!okBtn && !noBtn) return;
    const id = (okBtn || noBtn).dataset.id;
    if (okBtn) {
      if (!confirm('¿Confirmar que el depósito fue recibido?')) return;
      const data = await api('confirmar', { id_pago: id }, 'POST');
      showMsg(data.message || '', !!data.ok);
      await refrescar();
      return;
    }
    const motivo = prompt('Motivo del rechazo (opcional):') || '';
    const data = await api('rechazar', { id_pago: id, motivo }, 'POST');
    showMsg(data.message || '', !!data.ok);
    await refrescar();
  });

  refrescar();
})();
</script>
