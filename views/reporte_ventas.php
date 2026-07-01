<?php
require_once __DIR__ . '/../config.php';
if (!reporte_financiero_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para este reporte.</div>';
    return;
}

$hoy = date('Y-m-d');
$rangoDia = reporte_financiero_rango_modo('dia', $hoy);
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/reporte_ventas.css?v=20260625'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap rep-vent-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-cash-register"></i> Reporte de ventas <?php echo date('Y'); ?></h2>
    <p style="color:#666;"><?php echo htmlspecialchars($plantelNombre); ?> · Cuentas A (tarjeta/transfer/factura) y B (efectivo sin factura)</p>
  </div>

  <div class="rep-vent-tabs-periodo" id="rep-vent-modo-tabs">
    <button type="button" class="active" data-modo="dia">Día</button>
    <button type="button" data-modo="semana">Semanal</button>
    <button type="button" data-modo="mes">Mensual</button>
    <button type="button" data-modo="anio">Anual</button>
  </div>

  <div class="rep-vent-nav-fecha">
    <button type="button" class="rep-vent-fecha-nav-btn" id="rep-vent-prev" title="Anterior"><i class="fas fa-chevron-left"></i></button>
    <label class="rep-vent-fecha-label">
      <span id="rep-vent-etiqueta"><?php echo htmlspecialchars($rangoDia['etiqueta']); ?></span>
      <input type="date" id="rep-vent-fecha" value="<?php echo htmlspecialchars($hoy); ?>" style="margin-left:8px;">
    </label>
    <button type="button" class="rep-vent-fecha-nav-btn" id="rep-vent-next" title="Siguiente"><i class="fas fa-chevron-right"></i></button>
  </div>

  <div class="rep-vent-layout">
    <div class="rep-vent-panel-main">
      <div class="rep-vent-toolbar">
        <div class="rep-vent-cuentas" id="rep-vent-cuentas">
          <button type="button" class="active" data-cuenta="A">A</button>
          <button type="button" data-cuenta="B">B</button>
          <button type="button" class="rep-vent-toggle-b" id="rep-vent-solo-a" hidden>Solo cuenta A</button>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <button type="button" class="secondary" id="rep-vent-excel"><i class="fas fa-file-excel"></i> Excel</button>
          <label>Buscar: <input type="search" id="rep-vent-buscar" placeholder="Folio, control, alumno…"></label>
        </div>
      </div>

      <p id="rep-vent-loading" hidden><i class="fas fa-spinner fa-spin"></i> Cargando…</p>

      <div class="catalog-table-wrap">
        <table class="catalog-table rep-vent-tabla" id="rep-vent-tabla">
          <thead>
            <tr>
              <th>Folio</th>
              <th>Fecha abono</th>
              <th>No. control</th>
              <th>Alumno</th>
              <th>Concepto</th>
              <th>Grupo</th>
              <th>Recibido por</th>
              <th>Forma de pago</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <aside class="rep-vent-sidebar">
      <button type="button" class="rep-vent-btn-corte" id="rep-vent-btn-corte"><i class="fas fa-sync-alt"></i> Corte de caja</button>
      <div class="rep-vent-total-card">
        <small>TOTAL</small>
        <strong id="rep-vent-total">$ 0.00</strong>
      </div>
    </aside>
  </div>
</div>

<div class="rep-vent-modal" id="rep-vent-modal-corte" hidden>
  <div class="rep-vent-modal-box">
    <h3>Entrega de corte diario</h3>
    <p class="sub" id="rep-vent-corte-sub"><?php echo htmlspecialchars(strtoupper($plantelNombre)); ?> · COLEGIATURAS</p>
    <table class="rep-vent-corte-table">
      <tbody>
        <tr><th>Ingreso (sistema)</th><td id="rep-corte-ingreso">$ 0.00</td></tr>
        <tr><th>Retiros</th><td><input type="number" id="rep-corte-retiros" min="0" step="0.01" value="0"></td></tr>
        <tr><th>Terminal (tarjeta)</th><td id="rep-corte-terminal">$ 0.00</td></tr>
        <tr><th>Transferencia</th><td id="rep-corte-transferencia">$ 0.00</td></tr>
        <tr><th>Billetes</th><td><input type="number" id="rep-corte-billetes" min="0" step="0.01" value="0"></td></tr>
        <tr><th>Monedas</th><td><input type="number" id="rep-corte-monedas" min="0" step="0.01" value="0"></td></tr>
        <tr><th>Comprobantes</th><td><input type="number" id="rep-corte-comprobantes" min="0" step="0.01" value="0"></td></tr>
        <tr><th>Notas</th><td><input type="text" id="rep-corte-notas" placeholder="Observaciones"></td></tr>
      </tbody>
    </table>
    <p class="rep-vent-corte-subtotal">Diferencia efectivo: <span id="rep-corte-diferencia">$ 0.00</span></p>
    <p class="rep-vent-corte-entregar">Entregar: <span id="rep-corte-entregar">$ 0.00</span></p>
    <div class="rep-vent-corte-actions">
      <button type="button" class="secondary" id="rep-corte-cancelar">Cancelar</button>
      <button type="button" class="primary" id="rep-corte-guardar">Guardar corte</button>
    </div>
    <p id="rep-corte-msg" style="margin-top:10px; font-size:0.9rem; color:#666;"></p>
  </div>
</div>

<script>
window.HAY_REP_VENT_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_financiero_api.php'),
    'ticketBase' => hay_asset_url('views/ticket_pago.php'),
    'plantel' => $plantelNombre,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_ventas.js?v=20260608'), ENT_QUOTES, 'UTF-8'); ?>"></script>
