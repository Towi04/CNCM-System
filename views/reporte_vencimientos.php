<?php
require_once __DIR__ . '/../config.php';
if (!reporte_cartera_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para este reporte.</div>';
    return;
}
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$hoy = date('Y-m-d');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/reporte_cartera.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-exclamation-triangle"></i> Reporte de vencimientos</h2>
    <p style="color:#666;"><?php echo htmlspecialchars($plantelNombre); ?> · Alumnos con colegiatura vencida · Semáforo por meses de atraso</p>
  </div>

  <div class="catalog-toolbar reporte-cartera-filtros" style="flex-wrap:wrap; gap:12px;">
    <div>
      <label>Fecha de corte</label>
      <input type="date" id="venc-fecha" value="<?php echo htmlspecialchars($hoy); ?>">
    </div>
    <div>
      <label>Especialidad</label>
      <select id="venc-esp"><option value="">Todas</option></select>
    </div>
    <div>
      <label>Grupo</label>
      <select id="venc-grupo"><option value="">Todos</option></select>
    </div>
    <div>
      <label>Semáforo</label>
      <select id="venc-semaforo">
        <option value="">Todos</option>
        <option value="amarillo">Amarillo</option>
        <option value="naranja">Naranja</option>
        <option value="rojo">Rojo</option>
      </select>
    </div>
    <div>
      <label>Forma pago</label>
      <select id="venc-forma">
        <option value="">Todas</option>
        <option value="mensual">Mensual</option>
        <option value="semanal">Semanal</option>
      </select>
    </div>
    <div>
      <label>Buscar</label>
      <input type="search" id="venc-q" placeholder="Nombre, control o grupo">
    </div>
    <div style="align-self:flex-end; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="primary" id="btn-venc-cargar"><i class="fas fa-sync"></i> Actualizar</button>
      <button type="button" class="secondary" id="btn-venc-export"><i class="fas fa-file-csv"></i> Exportar CSV</button>
      <button type="button" class="secondary" id="btn-venc-print"><i class="fas fa-print"></i> Imprimir</button>
    </div>
  </div>

  <div class="reporte-cartera-leyenda">
    <span class="reporte-cartera-semaforo semaforo-amarillo">1 mes sin pagar</span>
    <span class="reporte-cartera-semaforo semaforo-naranja">2–3 meses</span>
    <span class="reporte-cartera-semaforo semaforo-rojo">Más de 3 meses — cobrar ya</span>
  </div>

  <div id="venc-resumen" class="reporte-cartera-resumen"></div>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="venc-tabla">
      <thead>
        <tr>
          <th>Semáforo</th>
          <th>Control</th>
          <th>Alumno</th>
          <th>Grupo</th>
          <th>Teléfono</th>
          <th>Forma pago</th>
          <th>Periodos</th>
          <th>Más antiguo</th>
          <th>Adeudo</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="10" style="color:#888;">Cargando…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
window.HAY_REPORTE_CARTERA = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_cartera_api.php'),
    'export' => hay_asset_url('php/reporte_cartera_export.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_vencimientos.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
