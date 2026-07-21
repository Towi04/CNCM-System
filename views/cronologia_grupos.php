<?php
require_once __DIR__ . '/../config.php';
if (!cronologia_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$especialidades = $pdo->query('SELECT id_especialidad, nombre, clave FROM especialidades WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$profesores = $pdo->prepare(
    "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido
     FROM usuarios u INNER JOIN grupos g ON g.id_profesor = u.id_usuario
     WHERE g.id_plantel = ? ORDER BY u.nombre"
);
$profesores->execute([$idPlantel]);
$listaProf = $profesores->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/cronologia.css?v=20260721'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap cron-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-table"></i> Cronología de grupos</h2>
    <p style="color:#666;">Vista planilla: fase por semana, alumnos, profesor y filtros por especialidad o estado del grupo.</p>
  </div>

  <div class="catalog-toolbar cron-toolbar">
    <div>
      <label>Especialidad</label>
      <select id="cron-esp">
        <option value="">Todas</option>
        <?php foreach ($especialidades as $e): ?>
        <option value="<?php echo (int)$e['id_especialidad']; ?>"><?php echo htmlspecialchars(($e['clave'] ?? '') . ' — ' . ($e['nombre'] ?? '')); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Estado del grupo</label>
      <select id="cron-estado">
        <option value="">Todos</option>
        <option value="activo" selected>Activos / en curso</option>
        <option value="fin_curso">Fin de curso (sin alumnos)</option>
      </select>
    </div>
    <div>
      <label>Profesor</label>
      <select id="cron-prof">
        <option value="">Todos</option>
        <?php foreach ($listaProf as $p): ?>
        <option value="<?php echo (int)$p['id_usuario']; ?>"><?php echo htmlspecialchars(trim($p['nombre'].' '.$p['apellido'])); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Clave contiene</label>
      <input type="search" id="cron-q" placeholder="Ej. IF28">
    </div>
    <div>
      <label>Sem. atrás</label>
      <input type="number" id="cron-atras" value="6" min="0" max="26" style="width:60px;">
    </div>
    <div>
      <label>Sem. adelante</label>
      <input type="number" id="cron-adelante" value="14" min="4" max="52" style="width:60px;">
    </div>
    <div style="align-self:flex-end;">
      <button type="button" class="primary" id="btn-cron-generar"><i class="fas fa-sync"></i> Actualizar</button>
      <button type="button" class="secondary" id="btn-cron-pdf" style="margin-left:6px;"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
    </div>
  </div>

  <div class="cron-vista-tabs">
    <button type="button" class="cron-vista-tab active" data-vista="matriz"><i class="fas fa-table"></i> Planilla</button>
    <button type="button" class="cron-vista-tab" data-vista="tarjetas"><i class="fas fa-list"></i> Detalle por grupo</button>
  </div>

  <p id="cron-total" class="cron-total"></p>

  <div id="cron-planilla-wrap" class="cron-planilla-wrap">
    <div id="cron-planilla" class="cron-planilla">
      <p style="color:#888;padding:20px;">Cargando cronología…</p>
    </div>
  </div>

  <div id="cron-tarjetas" class="cron-tarjetas" style="display:none;"></div>
</div>

<script>
window.HAY_CRONOLOGIA_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/cronologia_api.php'),
    'pdf' => hay_asset_url('php/cronologia_pdf.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/cronologia.js?v=20260721'), ENT_QUOTES, 'UTF-8'); ?>"></script>
