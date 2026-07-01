<?php

require_once __DIR__ . '/../config.php';

if (!pago_supervisor_puede()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/reporte_pagos_anulados.css">

<div class="catalog-wrap rpa-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-ban"></i> Pagos anulados / corregidos</h2>
    <p style="color:#666;margin:0;">Movimientos de anulación o edición de pagos realizados por supervisión.</p>
  </div>

  <div class="catalog-toolbar">
    <div class="field"><label>Desde</label><input type="date" id="rpa-desde"></div>
    <div class="field"><label>Hasta</label><input type="date" id="rpa-hasta"></div>
    <button type="button" class="primary" id="rpa-buscar">Actualizar</button>
    <button type="button" class="secondary" id="rpa-mes">Este mes</button>
    <button type="button" class="secondary" id="rpa-semana">Esta semana</button>
    <button type="button" class="secondary" id="rpa-anio">Este año</button>
  </div>

  <div id="rpa-resumen" class="catalog-alert catalog-alert--ok" style="margin-bottom:12px;"></div>

  <div class="catalog-table-wrap hay-dt-panel">
    <table class="catalog-table" id="rpa-tabla">
      <thead>
        <tr>
          <th>Fecha mov.</th>
          <th>Tipo</th>
          <th>Control</th>
          <th>Alumno</th>
          <th>Pago</th>
          <th>Monto</th>
          <th>Motivo</th>
          <th>Usuario</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script src="js/reporte_pagos_anulados.js"></script>
<script>if (typeof window.hayReportePagosAnuladosInit === 'function') window.hayReportePagosAnuladosInit();</script>
