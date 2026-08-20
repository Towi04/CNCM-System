<?php
require_once __DIR__ . '/../config.php';
rbac_guard_seccion('alumno_cambio_plantel');

$idPlantel = plantel_scope_id($pdo);
$plantelActual = plantel_find($pdo, $idPlantel);
$puedeSolicitar = alumno_plantel_transfer_puede_solicitar();
$idAlumnoInicial = (int) ($_GET['id_alumno'] ?? 0);
$alumnoInicial = null;
if ($idAlumnoInicial > 0 && $puedeSolicitar) {
    $st = $pdo->prepare(
        'SELECT id_alumno, numero_control,
                TRIM(CONCAT(COALESCE(NULLIF(nombres, \'\'), nombre, \'\'), \' \',
                            COALESCE(apellido_paterno, apellido, \'\'), \' \',
                            COALESCE(apellido_materno, \'\'))) AS alumno
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumnoInicial, $idPlantel]);
    $alumnoInicial = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<style>
.acp-grid{display:grid;grid-template-columns:minmax(280px,420px) 1fr;gap:18px}.acp-card{background:#fff;border:1px solid #ddd;border-radius:10px;padding:18px}.acp-card h3{margin-top:0}.acp-form label{display:block;font-weight:600;margin-top:12px}.acp-form input,.acp-form select,.acp-form textarea{box-sizing:border-box;width:100%;padding:9px;margin-top:5px}.acp-resultados button{display:block;width:100%;text-align:left;margin-top:6px;padding:9px}.acp-alumno-seleccionado{padding:10px;background:#e8f5e9;border-radius:6px;margin-top:8px}.acp-table{width:100%;border-collapse:collapse}.acp-table th,.acp-table td{padding:9px;border-bottom:1px solid #e5e5e5;text-align:left;vertical-align:top}.acp-badge{display:inline-block;border-radius:12px;padding:3px 8px;background:#eee;font-size:.82rem}.acp-badge--pendiente{background:#fff3cd}.acp-badge--autorizado{background:#d4edda}.acp-badge--rechazado,.acp-badge--cancelado{background:#f8d7da}.acp-msg{margin:10px 0;padding:10px;border-radius:6px}.acp-msg--ok{background:#d4edda}.acp-msg--error{background:#f8d7da}.acp-actions button{margin:2px}@media(max-width:900px){.acp-grid{grid-template-columns:1fr}.acp-table{display:block;overflow:auto}}
</style>

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-exchange-alt"></i> Cambio de plantel</h2>
    <p>Plantel actual: <strong><?php echo htmlspecialchars((string) ($plantelActual['nombre'] ?? '')); ?></strong>. El plantel destino debe autorizar antes de migrar al alumno.</p>
  </div>
  <div id="acp-msg" hidden></div>

  <div class="acp-grid">
    <?php if ($puedeSolicitar): ?>
    <section class="acp-card">
      <h3>Solicitar traslado</h3>
      <form id="acp-form" class="acp-form">
        <label for="acp-control">Número de control</label>
        <div style="display:flex;gap:6px">
          <input id="acp-control" type="search" value="<?php echo htmlspecialchars((string) ($alumnoInicial['numero_control'] ?? '')); ?>" placeholder="Escriba el control">
          <button type="button" id="acp-buscar">Buscar</button>
        </div>
        <div id="acp-resultados" class="acp-resultados"></div>
        <div id="acp-seleccion" class="acp-alumno-seleccionado" <?php echo $alumnoInicial ? '' : 'hidden'; ?>>
          <strong id="acp-seleccion-nombre"><?php echo htmlspecialchars((string) ($alumnoInicial['alumno'] ?? '')); ?></strong>
          <span id="acp-seleccion-control"><?php echo htmlspecialchars((string) ($alumnoInicial['numero_control'] ?? '')); ?></span>
        </div>
        <input type="hidden" id="acp-id-alumno" value="<?php echo (int) ($alumnoInicial['id_alumno'] ?? 0); ?>">

        <label for="acp-destino">Plantel destino</label>
        <select id="acp-destino" required><option value="">Cargando planteles…</option></select>

        <label for="acp-motivo">Motivo</label>
        <textarea id="acp-motivo" rows="3" maxlength="2000" placeholder="Motivo del cambio" required></textarea>
        <button type="submit" class="primary" style="margin-top:12px">Enviar solicitud</button>
      </form>
    </section>
    <?php endif; ?>

    <section class="acp-card">
      <h3>Solicitudes entrantes pendientes</h3>
      <div id="acp-entrantes">Cargando…</div>
    </section>
  </div>

  <section class="acp-card" style="margin-top:18px">
    <h3>Historial del plantel</h3>
    <div id="acp-historial">Cargando…</div>
  </section>
</div>

<script>
window.HAY_ALUMNO_CAMBIO_PLANTEL = <?php echo json_encode([
    'api' => hay_asset_url('php/alumno_plantel_transfer_api.php'),
    'puedeSolicitar' => $puedeSolicitar,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/alumno_cambio_plantel.js?v=20260820'), ENT_QUOTES, 'UTF-8'); ?>"></script>
