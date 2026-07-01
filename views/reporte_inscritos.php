<?php

require_once __DIR__ . '/../config.php';

if (!reporte_inscritos_puede_ver()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';

    return;

}

$esAsesor = rbac_rol_efectivo() === 'asesor';

?>

<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-user-check"></i> Reporte de inscritos</h2>

    <p style="color:#666; margin:0;">

      <?php echo $esAsesor

        ? 'Sus alumnos dados de alta e inscritos, incluyendo cu�ntos vinieron recomendados por otro alumno.'

        : 'Alumnos inscritos por plantel; filtre por asesor si aplica.'; ?>

    </p>

  </div>



  <div class="catalog-toolbar">

    <?php if (!$esAsesor): ?>

    <div class="field">

      <label>Asesor (pre-registro)</label>

      <input type="number" id="ri-asesor" placeholder="ID usuario" min="0">

    </div>

    <?php endif; ?>

    <div class="field"><label>Desde</label><input type="date" id="ri-desde"></div>

    <div class="field"><label>Hasta</label><input type="date" id="ri-hasta"></div>

    <button type="button" class="primary" id="ri-buscar">Actualizar</button>

  </div>



  <div id="ri-resumen" class="catalog-alert catalog-alert--ok" style="margin-bottom:12px;"></div>



  <div class="catalog-table-wrap hay-dt-panel">

    <table class="catalog-table" id="ri-tabla">

      <thead>

        <tr>

          <th>Fecha alta</th>

          <th>Control</th>

          <th>Nombre</th>

          <th>Especialidad</th>

          <th>Asesor</th>

          <th>Referido</th>

          <th>Referidor</th>

          <th>Beneficio</th>

        </tr>

      </thead>

      <tbody></tbody>

    </table>

  </div>

</div>

<script>

window.HAY_REPORTE_INSCRITOS = <?php echo json_encode([

    'api' => hay_asset_url('php/reporte_inscritos_api.php'),

    'esAsesor' => $esAsesor,

], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_inscritos.js?v=20260612'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<script>if (window.hayReporteInscritosInit) window.hayReporteInscritosInit();</script>

