<?php
/**
 * Panel de comisiones en expediente de certificación.
 * Variables definidas en certificacion_expediente_comisiones.php antes del include.
 */
$sol = $sol ?? [];
$puedeEditar = $puedeEditar ?? false;
$historial = $historial ?? [];
$precio = isset($precio) ? (float) $precio : catalog_money($sol['precio_cobrado'] ?? $sol['precio'] ?? 0);
$comA = isset($comA) ? (float) $comA : catalog_money($sol['comision_asesor'] ?? 0);
$comG = isset($comG) ? (float) $comG : catalog_money($sol['comision_gerente'] ?? 0);
$idSolicitud = (int) ($idSolicitud ?? $sol['id_solicitud'] ?? 0);
$idPago = (int) ($idPago ?? $sol['id_pago'] ?? 0);
$saveUrl = $saveUrl ?? (function_exists('hay_asset_url') ? hay_asset_url('php/certificacion_comision_save.php') : '');
$partialUrl = $partialUrl ?? (function_exists('hay_asset_url') ? hay_asset_url('php/certificacion_expediente_comisiones.php') : '');
?>
<div class="cert-block cert-block--comisiones" id="cert-panel-comisiones" data-id-solicitud="<?php echo $idSolicitud; ?>">
  <h4>Comisiones y precio</h4>
  <?php if ($idPago > 0): ?>
    <p class="catalog-alert catalog-alert--ok" style="margin:0 0 10px;">
      Pagado en caja (pago #<?php echo $idPago; ?>). Las comisiones vigentes son las registradas al cobrar.
    </p>
  <?php else: ?>
    <p style="font-size:0.88rem; color:#666; margin:0 0 10px;">
      Pendiente de cobro en punto de venta. Al cobrar se aplicarán estas comisiones al asesor.
    </p>
  <?php endif; ?>

  <?php if ($puedeEditar): ?>
  <form method="post" action="<?php echo htmlspecialchars($saveUrl, ENT_QUOTES, 'UTF-8'); ?>"
        class="cert-form-comisiones" data-partial-url="<?php echo htmlspecialchars($partialUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="id_solicitud" value="<?php echo $idSolicitud; ?>">
    <div class="cert-form-grid">
      <div>
        <label>Precio acordado</label>
        <input type="number" name="precio_cobrado" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $precio, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div>
        <label>Comisión asesor</label>
        <input type="number" name="comision_asesor" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $comA, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div>
        <label>Sobrecomisión gerente</label>
        <input type="number" name="comision_gerente" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $comG, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div class="full">
        <label>Motivo del cambio</label>
        <input type="text" name="motivo" placeholder="Ej. ajuste por promoción" style="width:100%;">
      </div>
    </div>
    <button type="submit" class="secondary">Guardar comisiones</button>
    <span class="cert-com-msg" style="margin-left:8px; font-size:0.9rem;"></span>
  </form>
  <?php else: ?>
  <table class="catalog-table" style="margin-top:8px;">
    <tr><th>Precio</th><td><?php echo htmlspecialchars(catalog_format_mxn($precio), ENT_QUOTES, 'UTF-8'); ?></td></tr>
    <tr><th>Comisión asesor</th><td><?php echo htmlspecialchars(catalog_format_mxn($comA), ENT_QUOTES, 'UTF-8'); ?></td></tr>
    <tr><th>Sobrecom. gerente</th><td><?php echo htmlspecialchars(catalog_format_mxn($comG), ENT_QUOTES, 'UTF-8'); ?></td></tr>
  </table>
  <?php endif; ?>

  <details style="margin-top:12px;" open>
    <summary><strong>Historial de cambios</strong> (<?php echo count($historial); ?>)</summary>
    <?php if ($historial === []): ?>
      <p style="color:#888; font-size:0.9rem;">Sin cambios registrados.</p>
    <?php else: ?>
    <table class="catalog-table" style="margin-top:8px; font-size:0.88rem;">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Precio</th>
          <th>Asesor</th>
          <th>Gerente</th>
          <th>Usuario</th>
          <th>Motivo</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($historial as $h): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) ($h['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars(catalog_format_mxn((float) ($h['precio_cobrado'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars(catalog_format_mxn((float) ($h['comision_asesor'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars(catalog_format_mxn((float) ($h['comision_gerente'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($h['usuario_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($h['motivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </details>
</div>
