<?php
require_once __DIR__ . '/_bootstrap.php';

if (!grupo_docente_puede_gestionar()) {
    echo '<p>Sin permiso para asignar docentes al grupo.</p>';
    return;
}

$idGrupo = (int) ($_GET['id_grupo'] ?? 0);
$idPlantel = plantel_scope_id($pdo);
if ($idGrupo <= 0 || !plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
    echo '<p>Grupo no válido.</p>';
    return;
}

$st = $pdo->prepare('SELECT clave FROM grupos WHERE id_grupo = ? LIMIT 1');
$st->execute([$idGrupo]);
$clave = (string) ($st->fetchColumn() ?: '');
$api = hay_asset_url('php/grupo_docente_api.php');
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="result-container" id="grupo-docentes-wrap">
  <div class="result-header">
    <h2>Docentes del grupo <?php echo htmlspecialchars($clave); ?></h2>
    <div class="disc-actions">
      <button type="button" onclick="cargarSeccion('grupos')">Volver a grupos</button>
    </div>
  </div>

  <p style="color:#666; max-width:720px;">
    Aquí se asignan los <strong>profesores que imparten este grupo</strong> (titular y, si aplica, por materia).
    No es un historial de suplencias: es la plantilla actual de docentes del grupo para reportes, nómina y planeación.
    El selector incluye <strong>todos los docentes del plantel</strong> (sin filtrar por área HAY).
  </p>

  <form id="form-grupo-docentes" style="margin-top:16px;">
    <input type="hidden" name="id_grupo" value="<?php echo $idGrupo; ?>">
    <div id="gd-tabla" style="display:flex; flex-direction:column; gap:10px; max-width:900px;"></div>
    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
      <button type="button" class="secondary" id="gd-btn-add">+ Agregar materia</button>
      <button type="submit" class="primary">Guardar docentes</button>
    </div>
  </form>
</div>

<script>
window.HAY_GRUPO_DOCENTES = <?php echo json_encode(['api' => $api, 'id_grupo' => $idGrupo], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/grupo_docentes.js?v=20260721'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>if (window.hayGrupoDocentesInit) window.hayGrupoDocentesInit();</script>
