<?php

require_once __DIR__ . '/../config.php';

if (!documento_puede_marcar_pagada()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';

    return;

}

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/documento_emitido.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="catalog-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-file-invoice-dollar"></i> Constancias pendientes de pago</h2>

    <p style="color:#666;">Marque como pagada cuando el alumno cubra el producto en caja, o cobre desde <strong>Punto de venta</strong> (aparece en pagos pendientes al seleccionar al alumno). Se generará el PDF con QR automáticamente.</p>

  </div>



  <div class="catalog-toolbar">

    <button type="button" class="primary" id="btn-doc-rec-cargar"><i class="fas fa-sync"></i> Actualizar</button>

  </div>



  <div class="catalog-table-wrap">

    <table class="catalog-table" id="doc-rec-tabla">

      <thead>

        <tr>

          <th>Folio</th>

          <th>Alumno</th>

          <th>Control</th>

          <th>Producto</th>

          <th>Precio</th>

          <th>Solicitada</th>

          <th></th>

        </tr>

      </thead>

      <tbody><tr><td colspan="7" style="color:#888;">Cargando…</td></tr></tbody>

    </table>

  </div>

</div>



<script>

window.HAY_DOC_RECEPCION = <?php echo json_encode(['api' => hay_asset_url('php/documento_api.php')], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/constancia_recepcion.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>

