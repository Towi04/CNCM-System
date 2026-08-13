<?php
require_once __DIR__ . '/../config.php';

if (!reporte_financiero_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para el corte de caja.</div>';
    return;
}

$hoy = date('Y-m-d');
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$billetesDenoms = [1000, 500, 200, 100, 50, 20];
$monedasDenoms = [10, 5, 2, 1, 0.50];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/reporte_ventas.css?v=20260813'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap rep-vent-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-coins"></i> Corte de caja</h2>
    <p style="color:#666;"><?php echo htmlspecialchars($plantelNombre); ?> · Entrega diaria por cuenta contable (A: tarjeta/transfer/factura · B: efectivo sin factura)</p>
  </div>

  <div class="rep-vent-corte-bar">
    <label class="rep-vent-fecha-label rep-vent-fecha-label--inline">
      Fecha del corte
      <input type="date" id="corte-fecha" value="<?php echo htmlspecialchars($hoy); ?>">
    </label>
    <div class="rep-vent-cuentas" id="corte-cuentas">
      <button type="button" data-cuenta="A">Cuenta A</button>
      <button type="button" class="active" data-cuenta="B">Cuenta B</button>
    </div>
    <button type="button" class="secondary" id="corte-recargar"><i class="fas fa-sync"></i> Recalcular</button>
  </div>

  <p id="corte-loading" hidden><i class="fas fa-spinner fa-spin"></i> Cargando…</p>

  <div class="corte-caja-layout">
    <div class="rep-vent-panel-main corte-caja-panel">
      <h3 style="margin:0 0 4px; text-align:center; text-transform:uppercase; letter-spacing:0.04em;">Entrega de corte diario</h3>
      <p class="sub" style="text-align:center; color:#666; margin-bottom:16px; font-size:0.9rem;"><?php echo htmlspecialchars(strtoupper($plantelNombre)); ?> · COLEGIATURAS</p>

      <table class="rep-vent-corte-table">
        <tbody id="corte-tbody">
          <tr><th>Ingreso (sistema)</th><td id="corte-ingreso">$ 0.00</td></tr>
          <tr><th>Retiros</th><td><input type="number" id="corte-retiros" min="0" step="0.01" value="0"></td></tr>
          <tr><th>Terminal (tarjeta)</th><td id="corte-terminal">$ 0.00</td></tr>
          <tr><th>Transferencia</th><td id="corte-transferencia">$ 0.00</td></tr>
          <tr><th>Billetes</th><td><input type="number" id="corte-billetes" min="0" step="0.01" value="0"></td></tr>
          <tr><th>Monedas</th><td><input type="number" id="corte-monedas" min="0" step="0.01" value="0"></td></tr>
          <tr><th>Comprobantes</th><td><input type="number" id="corte-comprobantes" min="0" step="0.01" value="0"></td></tr>
        </tbody>
        <tbody id="corte-filas-extra"></tbody>
        <tbody>
          <tr>
            <th colspan="2" style="background:#fff; border:none; padding-top:4px;">
              <button type="button" class="secondary corte-btn-add-fila" id="corte-add-fila" title="Agregar fila personalizada">
                <i class="fas fa-plus"></i> Agregar concepto
              </button>
            </th>
          </tr>
          <tr><th>Notas</th><td><input type="text" id="corte-notas" placeholder="Observaciones" style="width:100%;"></td></tr>
        </tbody>
      </table>

      <p class="rep-vent-corte-subtotal">Diferencia efectivo: <span id="corte-diferencia">$ 0.00</span></p>
      <p class="rep-vent-corte-entregar">Entregar: <span id="corte-entregar">$ 0.00</span></p>

      <div class="rep-vent-corte-actions">
        <button type="button" class="primary" id="corte-guardar"><i class="fas fa-save"></i> Guardar corte</button>
        <button type="button" class="secondary" id="corte-confirmar-recibo" disabled>
          <i class="fas fa-signature"></i> Confirmar recibo
        </button>
        <a href="#" data-seccion="reporte_ventas" class="secondary" style="display:inline-flex; align-items:center; padding:8px 12px; text-decoration:none;">Ver reporte de ventas</a>
      </div>

      <p id="corte-msg" style="margin-top:10px; font-size:0.9rem; color:#666;"></p>
      <p id="corte-recibo-estado" class="corte-recibo-estado" hidden></p>
    </div>

    <aside class="rep-vent-panel-main corte-caja-calc">
      <h3 style="margin:0 0 8px; font-size:1rem; text-transform:uppercase; letter-spacing:0.04em;">
        <i class="fas fa-calculator"></i> Contador de efectivo
      </h3>
      <p style="margin:0 0 12px; color:#666; font-size:0.85rem;">Capture cantidades; el total llena billetes y monedas del corte.</p>

      <div class="corte-calc-grid">
        <div>
          <h4 class="corte-calc-heading">Billetes</h4>
          <table class="corte-calc-table">
            <thead>
              <tr><th>Denom.</th><th>Cant.</th><th>Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach ($billetesDenoms as $d): ?>
              <tr>
                <td>$ <?php echo number_format($d, 0); ?></td>
                <td>
                  <input type="number" class="corte-denom-qty" data-valor="<?php echo htmlspecialchars((string) $d); ?>" data-grupo="billetes" min="0" step="1" value="0">
                </td>
                <td class="corte-denom-sub" data-valor="<?php echo htmlspecialchars((string) $d); ?>">$ 0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr><th colspan="2">Total billetes</th><th id="corte-calc-billetes">$ 0.00</th></tr>
            </tfoot>
          </table>
        </div>
        <div>
          <h4 class="corte-calc-heading">Monedas</h4>
          <table class="corte-calc-table">
            <thead>
              <tr><th>Denom.</th><th>Cant.</th><th>Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach ($monedasDenoms as $d):
                  $key = $d < 1 ? number_format($d, 2, '.', '') : (string) (int) $d;
                  $label = $d < 1 ? number_format($d, 2) : number_format($d, 0);
              ?>
              <tr>
                <td>$ <?php echo htmlspecialchars($label); ?></td>
                <td>
                  <input type="number" class="corte-denom-qty" data-valor="<?php echo htmlspecialchars($key); ?>" data-grupo="monedas" min="0" step="1" value="0">
                </td>
                <td class="corte-denom-sub" data-valor="<?php echo htmlspecialchars($key); ?>">$ 0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr><th colspan="2">Total monedas</th><th id="corte-calc-monedas">$ 0.00</th></tr>
            </tfoot>
          </table>
        </div>
      </div>
      <p class="corte-calc-total">Efectivo contado: <strong id="corte-calc-total">$ 0.00</strong></p>
    </aside>
  </div>
</div>

<div class="rep-vent-modal" id="corte-modal-recibo" hidden>
  <div class="rep-vent-modal-box" style="max-width:420px;">
    <h3>Confirmar recibo</h3>
    <p class="sub">Quien recibe el dinero debe ingresar su usuario y contraseña</p>
    <label style="display:block; margin-bottom:10px;">
      Usuario
      <input type="text" id="corte-recibo-usuario" autocomplete="username" style="width:100%; margin-top:4px; padding:8px; box-sizing:border-box;">
    </label>
    <label style="display:block; margin-bottom:14px;">
      Contraseña
      <input type="password" id="corte-recibo-password" autocomplete="current-password" style="width:100%; margin-top:4px; padding:8px; box-sizing:border-box;">
    </label>
    <p id="corte-recibo-msg" style="font-size:0.9rem; color:#c62828; min-height:1.2em;"></p>
    <div class="rep-vent-corte-actions">
      <button type="button" class="secondary" id="corte-recibo-cancelar">Cancelar</button>
      <button type="button" class="primary" id="corte-recibo-enviar"><i class="fas fa-check"></i> Confirmar</button>
    </div>
  </div>
</div>

<script>
window.HAY_CORTE_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_financiero_api.php'),
    'plantel' => $plantelNombre,
    'billetes' => $billetesDenoms,
    'monedas' => $monedasDenoms,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/corte_caja.js?v=20260813'), ENT_QUOTES, 'UTF-8'); ?>"></script>
