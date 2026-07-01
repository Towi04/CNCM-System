<?php
require_once __DIR__ . '/../config.php';
if (!grupo_fusion_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para planificar fusiones.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$especialidades = grupo_fusion_listar_especialidades($pdo, $idPlantel);
$idEspDefault = (int) ($_GET['id_especialidad'] ?? ($especialidades[0]['id_especialidad'] ?? 0));

$profesores = $pdo->prepare(
    "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido
     FROM usuarios u INNER JOIN grupos g ON g.id_profesor = u.id_usuario
     WHERE g.id_plantel = ? ORDER BY u.nombre"
);
$profesores->execute([$idPlantel]);
$listaProf = $profesores->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/grupo_fusion.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap gf-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-object-group"></i> Planificación de fusiones</h2>
    <p style="color:#666;">
      Vista por fases del plan de estudios. Los grupos con
      <strong><?php echo GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO; ?> o menos</strong> alumnos activos se marcan para considerar fusión.
      Seleccione dos grupos para simular, guarde el plan y actívelo cuando se lleve a cabo la fusión.
      En modo <strong>Infantil dual</strong> se planifican fusiones paralelas de inglés y computación.
    </p>
  </div>

  <div class="catalog-toolbar gf-toolbar">
    <div>
      <label>Modo</label>
      <select id="gf-modo">
        <option value="simple" selected>Por especialidad</option>
        <option value="kids_dual">Infantil dual (ING-K + COMP-K)</option>
      </select>
    </div>
    <div id="gf-esp-wrap">
      <label>Especialidad</label>
      <select id="gf-esp" required>
        <?php if ($especialidades === []): ?>
        <option value="">— Sin grupos —</option>
        <?php else: ?>
        <?php foreach ($especialidades as $e): ?>
        <option value="<?php echo (int)$e['id_especialidad']; ?>" <?php echo (int)$e['id_especialidad'] === $idEspDefault ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars(($e['clave'] ?? '') . ' — ' . ($e['nombre'] ?? '')); ?>
        </option>
        <?php endforeach; ?>
        <?php endif; ?>
      </select>
    </div>
    <div>
      <label>Estado</label>
      <select id="gf-estado">
        <option value="activo" selected>Activos</option>
        <option value="">Todos</option>
        <option value="fin_curso">Fin de curso</option>
      </select>
    </div>
    <div>
      <label>Profesor</label>
      <select id="gf-prof">
        <option value="">Todos</option>
        <?php foreach ($listaProf as $p): ?>
        <option value="<?php echo (int)$p['id_usuario']; ?>"><?php echo htmlspecialchars(trim($p['nombre'].' '.$p['apellido'])); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Clave contiene</label>
      <input type="search" id="gf-q" placeholder="Ej. IF28">
    </div>
    <div class="gf-check-wrap">
      <label><input type="checkbox" id="gf-solo-rec"> Solo recomendados (≤<?php echo GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO; ?> alumnos)</label>
    </div>
    <div style="align-self:flex-end;">
      <button type="button" class="primary" id="btn-gf-cargar"><i class="fas fa-sync"></i> Actualizar</button>
    </div>
  </div>

  <div class="gf-resumen" id="gf-resumen"></div>

  <div class="gf-planes-panel" id="gf-planes-panel">
    <div class="gf-planes-head">
      <h3><i class="fas fa-clipboard-list"></i> Planes de fusión</h3>
      <button type="button" class="secondary btn-sm" id="btn-gf-planes-refresh"><i class="fas fa-sync"></i> Actualizar</button>
    </div>
    <div id="gf-planes-lista" class="gf-planes-lista">
      <p class="gf-planes-empty">Cargando planes…</p>
    </div>
    <div id="gf-plan-detalle" class="gf-plan-detalle" hidden></div>
  </div>

  <div class="gf-planilla-wrap" id="gf-planilla-wrap">
    <div id="gf-planilla" class="gf-planilla">
      <p style="color:#888;padding:20px;">Seleccione especialidad y pulse Actualizar.</p>
    </div>
    <div id="gf-planilla-comp" class="gf-planilla gf-planilla-comp" hidden></div>
  </div>

  <div class="gf-sim-panel" id="gf-sim-panel">
    <h3><i class="fas fa-compress-arrows-alt"></i> Simulación de fusión</h3>
    <p class="gf-sim-hint" id="gf-sim-hint">Haga clic en dos filas de la planilla (o use los botones A / B) para elegir los grupos.</p>
    <div class="gf-sim-pick">
      <div class="gf-pick-box" id="gf-pick-a">
        <span class="gf-pick-label">Grupo A</span>
        <strong id="gf-pick-a-text">—</strong>
        <button type="button" class="secondary btn-sm" id="gf-clear-a">Quitar</button>
      </div>
      <div class="gf-pick-box" id="gf-pick-b">
        <span class="gf-pick-label">Grupo B</span>
        <strong id="gf-pick-b-text">—</strong>
        <button type="button" class="secondary btn-sm" id="gf-clear-b">Quitar</button>
      </div>
      <div class="gf-pick-dest">
        <label>Fase destino común</label>
        <select id="gf-fase-destino"><option value="">Automática (siguiente fase)</option></select>
      </div>
      <button type="button" class="primary" id="btn-gf-simular" disabled><i class="fas fa-play"></i> Simular fusión</button>
    </div>
    <div id="gf-sim-resultado" class="gf-sim-resultado"></div>
  </div>
</div>

<script>
window.HAY_GRUPO_FUSION_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/grupo_fusion_api.php'),
    'umbral' => GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO,
    'puede_gestionar' => grupo_fusion_puede_gestionar(),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/grupo_fusion.js?v=20260610'), ENT_QUOTES, 'UTF-8'); ?>"></script>
