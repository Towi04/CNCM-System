<?php

require_once __DIR__ . '/../config.php';

if (!nomina_puede_gestionar()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para gestionar nómina.</div>';

    return;

}

$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$hoy = date('Y-m-d');
$idLiqUrl = (int) ($_GET['id_liquidacion'] ?? 0);
$tabInicial = trim((string) ($_GET['tab'] ?? ''));
$supPrefill = [
    'tab' => $tabInicial,
    'sup_titular' => (int) ($_GET['sup_titular'] ?? 0),
    'sup_grupo' => (int) ($_GET['sup_grupo'] ?? 0),
    'sup_desde' => trim((string) ($_GET['sup_desde'] ?? '')),
    'sup_hasta' => trim((string) ($_GET['sup_hasta'] ?? '')),
    'sup_notas' => trim((string) ($_GET['sup_notas'] ?? '')),
];

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/director_nomina.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="catalog-wrap nomina-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-money-check-alt"></i> Nómina del personal</h2>

    <p style="color:#666;"><?php echo htmlspecialchars($plantelNombre); ?> · Salario fijo, por hora, tabulador asesor o nivel HAY · Transición gradual al sistema HAY</p>

  </div>



  <div class="nomina-tabs" id="nomina-tabs">
    <button type="button" class="active" data-tab="liquidacion">Generar nómina</button>
    <button type="button" data-tab="suplencias">Suplencias de grupos</button>
    <button type="button" data-tab="config">Configurar personal</button>
  </div>



  <div class="nomina-panel" id="nomina-panel-liquidacion">

    <div class="catalog-toolbar nomina-toolbar">

      <div>

        <label>Tipo de periodo</label>

        <select id="nom-tipo-periodo">

          <option value="semana">Semana (profesores / asesores)</option>

          <option value="quincena" selected>Quincena (administrativos)</option>

          <option value="mes">Mes completo</option>

        </select>

      </div>

      <div>

        <label>Fecha de referencia</label>

        <input type="date" id="nom-fecha" value="<?php echo htmlspecialchars($hoy); ?>">

      </div>

      <div class="nomina-rango" id="nom-rango-preview"></div>

      <div style="align-self:flex-end; display:flex; gap:8px; flex-wrap:wrap;">

        <button type="button" class="primary" id="btn-nom-generar"><i class="fas fa-calculator"></i> Generar / recalcular</button>

      </div>

    </div>



    <div class="nomina-layout">

      <aside class="nomina-historial">

        <h3>Liquidaciones</h3>

        <ul id="nom-lista-liq" class="nomina-liq-list">

          <li style="color:#888;">Cargando…</li>

        </ul>

      </aside>



      <section class="nomina-detalle" id="nom-detalle">

        <p class="nomina-placeholder">Seleccione o genere una liquidación para ver el detalle.</p>

      </section>

    </div>

  </div>



  <div class="nomina-panel" id="nomina-panel-config" hidden>

    <p style="color:#666; margin-bottom:12px;">
      Configure la nómina <strong>principal</strong> (quincena, tabulador, HAY) y la <strong>tarifa de docencia</strong> por hora.
      Si la persona imparte clases además de su rol administrativo, el sistema suma ambos conceptos al generar nómina semanal o quincenal.
      <button type="button" class="linkish" id="btn-nom-hay">Ir a configuración HAY</button>
    </p>
    <div class="catalog-table-wrap">
      <table class="catalog-table" id="nom-config-tabla">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Rol</th>
            <th>Pago principal</th>
            <th>Monto fijo</th>
            <th>Área / nivel HAY</th>
            <th>Tarifa docencia ($/hr)</th>
            <th>Notas</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="8" style="color:#888;">Cargando personal…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="nomina-panel" id="nomina-panel-suplencias" hidden>
    <p style="color:#666; margin-bottom:12px;">
      Registre cuando un profesor falta y otro cubre el grupo. Defina si se paga solo al suplente, a ambos (evento/apoyo) o solo apoyo al titular.
    </p>
    <div class="catalog-toolbar nomina-toolbar" id="sup-form">
      <input type="hidden" id="sup-id" value="">
      <div>
        <label>Grupo</label>
        <select id="sup-grupo"><option value="">Seleccione…</option></select>
      </div>
      <div>
        <label>Profesor titular</label>
        <select id="sup-titular"><option value="">—</option></select>
      </div>
      <div>
        <label>Profesor suplente</label>
        <select id="sup-suplente"><option value="">—</option></select>
      </div>
      <div>
        <label>Desde</label>
        <input type="date" id="sup-desde" value="<?php echo htmlspecialchars($hoy); ?>">
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" id="sup-hasta" value="<?php echo htmlspecialchars($hoy); ?>">
      </div>
      <div>
        <label>Motivo</label>
        <select id="sup-motivo"></select>
      </div>
      <div>
        <label>Regla de pago</label>
        <select id="sup-regla"></select>
      </div>
      <div>
        <label>Concepto apoyo titular</label>
        <input type="text" id="sup-concepto" placeholder="Ej. Apoyo viaje prepa">
      </div>
      <div>
        <label>Monto fijo titular</label>
        <input type="number" id="sup-monto" step="0.01" placeholder="Opcional">
      </div>
      <div>
        <label>Horas titular</label>
        <input type="number" id="sup-horas" step="0.25" placeholder="Opcional">
      </div>
      <div class="sup-notas-wrap">
        <label>Notas</label>
        <textarea id="sup-notas" rows="2" placeholder="Detalle libre: quién avisó, contexto del evento, etc."></textarea>
      </div>
      <div style="align-self:flex-end;">
        <button type="button" class="primary" id="btn-sup-guardar">Guardar suplencia</button>
        <button type="button" class="secondary" id="btn-sup-limpiar">Limpiar</button>
      </div>
    </div>
    <div class="catalog-table-wrap">
      <table class="catalog-table" id="sup-tabla">
        <thead>
          <tr>
            <th>Grupo</th>
            <th>Titular</th>
            <th>Suplente</th>
            <th>Periodo</th>
            <th>Regla</th>
            <th>Apoyo</th>
            <th>Notas</th>
            <th></th>
          </tr>
        </thead>
        <tbody><tr><td colspan="8" style="color:#888;">Cargando…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<script>
window.HAY_NOMINA = <?php echo json_encode([
    'api' => hay_asset_url('php/nomina_api.php'),
    'export' => hay_asset_url('php/nomina_export.php'),
    'pdf' => hay_asset_url('php/nomina_pdf.php'),
    'puede_ajustar' => nomina_puede_ajustar_manual(),
    'id_liquidacion' => $idLiqUrl,
    'sup_prefill' => $supPrefill,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/director_nomina.js?v=20260625'), ENT_QUOTES, 'UTF-8'); ?>"></script>

