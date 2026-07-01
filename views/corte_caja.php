<?php

require_once __DIR__ . '/../config.php';

if (!reporte_financiero_puede_ver()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para el corte de caja.</div>';

    return;

}

$hoy = date('Y-m-d');

$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/reporte_ventas.css?v=20260625'), ENT_QUOTES, 'UTF-8'); ?>">



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



  <div class="rep-vent-panel-main" style="max-width:560px;">

    <h3 style="margin:0 0 4px; text-align:center; text-transform:uppercase; letter-spacing:0.04em;">Entrega de corte diario</h3>

    <p class="sub" style="text-align:center; color:#666; margin-bottom:16px; font-size:0.9rem;"><?php echo htmlspecialchars(strtoupper($plantelNombre)); ?> · COLEGIATURAS</p>



    <p id="corte-loading" hidden><i class="fas fa-spinner fa-spin"></i> Cargando…</p>



    <table class="rep-vent-corte-table">

      <tbody>

        <tr><th>Ingreso (sistema)</th><td id="corte-ingreso">$ 0.00</td></tr>

        <tr><th>Retiros</th><td><input type="number" id="corte-retiros" min="0" step="0.01" value="0"></td></tr>

        <tr><th>Terminal (tarjeta)</th><td id="corte-terminal">$ 0.00</td></tr>

        <tr><th>Transferencia</th><td id="corte-transferencia">$ 0.00</td></tr>

        <tr><th>Billetes</th><td><input type="number" id="corte-billetes" min="0" step="0.01" value="0"></td></tr>

        <tr><th>Monedas</th><td><input type="number" id="corte-monedas" min="0" step="0.01" value="0"></td></tr>

        <tr><th>Comprobantes</th><td><input type="number" id="corte-comprobantes" min="0" step="0.01" value="0"></td></tr>

        <tr><th>Notas</th><td><input type="text" id="corte-notas" placeholder="Observaciones" style="width:100%;"></td></tr>

      </tbody>

    </table>



    <p class="rep-vent-corte-subtotal">Diferencia efectivo: <span id="corte-diferencia">$ 0.00</span></p>

    <p class="rep-vent-corte-entregar">Entregar: <span id="corte-entregar">$ 0.00</span></p>



    <div class="rep-vent-corte-actions">

      <button type="button" class="primary" id="corte-guardar"><i class="fas fa-save"></i> Guardar corte</button>

      <a href="#" data-seccion="reporte_ventas" class="secondary" style="display:inline-flex; align-items:center; padding:8px 12px; text-decoration:none;">Ver reporte de ventas</a>

    </div>



    <p id="corte-msg" style="margin-top:10px; font-size:0.9rem; color:#666;"></p>

  </div>

</div>



<script>

window.HAY_CORTE_CONFIG = <?php echo json_encode([

    'api' => hay_asset_url('php/reporte_financiero_api.php'),

    'plantel' => $plantelNombre,

], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/corte_caja.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>

