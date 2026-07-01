<?php

require_once __DIR__ . '/../config.php';
if (!asistencia_puede_tomar() && !asistencia_puede_eliminar_registro()) {

    echo '<div class="alert">No autorizado.</div>';

    return;

}



$puedeEliminar = asistencia_puede_eliminar_registro();

$fecha = trim($_GET['fecha'] ?? date('Y-m-d'));

$idPlantel = plantel_scope_id($pdo);

$grupos = $pdo->prepare('SELECT id_grupo, clave FROM grupos WHERE id_plantel = ? ORDER BY clave');

$grupos->execute([$idPlantel]);

$listaGrupos = $grupos->fetchAll(PDO::FETCH_ASSOC);

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/asistencia.css'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="asist-wrap">

  <div class="asist-checada-header" style="margin-bottom:16px;">

    <div>

      <h2 style="margin:0;"><i class="fas fa-list-alt"></i> Registros de checada</h2>

      <p class="asist-checada-sub">Consulte quien ya checó, filtre por horario y corrija errores.</p>

    </div>

    <div class="asist-checada-header__actions">

      <?php if (asistencia_puede_checada()): ?>

      <button type="button" class="primary" onclick="cargarSeccion('asistencia_checada')">

        <i class="fas fa-fingerprint"></i> Registrar asistencia

      </button>

      <?php endif; ?>

      <button type="button" class="secondary" onclick="cargarSeccion('asistencia_faltantes')">Ver faltantes</button>

      <button type="button" onclick="cargarSeccion('asistencia')"><i class="fas fa-arrow-left"></i> Asistencias</button>

    </div>

  </div>



  <div class="asist-toolbar">

    <div>

      <label>Fecha</label>

      <input type="date" id="reg-fecha" value="<?php echo htmlspecialchars($fecha); ?>">

    </div>

    <div>

      <label>Vista</label>

      <select id="reg-vista">

        <option value="checados" selected>Ya checaron</option>

        <option value="faltantes">Faltantes (por grupo)</option>

        <option value="todos">Todos</option>

      </select>

    </div>

    <div>

      <label>Tipo</label>

      <select id="reg-tipo">

        <option value="ambos">Alumnos y personal</option>

        <option value="alumno">Solo alumnos</option>

        <option value="personal">Solo personal</option>

      </select>

    </div>

    <div>

      <label>Grupo</label>

      <select id="reg-grupo">

        <option value="">Todos</option>

        <?php foreach ($listaGrupos as $g): ?>

        <option value="<?php echo (int)$g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave']); ?></option>

        <?php endforeach; ?>

      </select>

    </div>

    <div>

      <label>Hora desde</label>

      <input type="time" id="reg-hora-desde">

    </div>

    <div>

      <label>Hora hasta</label>

      <input type="time" id="reg-hora-hasta">

    </div>

    <div style="flex:1; min-width:140px;">

      <label>Buscar</label>

      <input type="search" id="reg-buscar" placeholder="Nombre o control">

    </div>

    <div>

      <button type="button" class="primary" id="btn-reg-filtrar">Actualizar</button>

    </div>

  </div>



  <?php if ($puedeEliminar): ?>

  <div class="catalog-alert catalog-alert--warn" style="display:block; margin-bottom:14px;">

    En la vista <strong>Ya checaron</strong> puede <strong>eliminar</strong> un registro erróneo con <i class="fas fa-trash"></i>.

  </div>

  <?php endif; ?>



  <div id="reg-total" style="margin-bottom:10px; color:#666; font-size:0.9rem;"></div>



  <div id="reg-faltantes-wrap"></div>



  <div id="reg-checados-wrap">

    <h3 class="reg-seccion-titulo" id="titulo-alumnos">Alumnos</h3>

    <div class="asist-reg-tabla-wrap">

      <table class="asist-punt-tabla" id="tabla-reg-alumnos">

        <thead>

          <tr>

            <th>Hora</th>

            <th>No. control</th>

            <th>Nombre</th>

            <th>Grupo</th>

            <th>Origen</th>

            <?php if ($puedeEliminar): ?><th></th><?php endif; ?>

          </tr>

        </thead>

        <tbody></tbody>

      </table>

    </div>



    <h3 class="reg-seccion-titulo" id="titulo-personal">Personal</h3>

    <div class="asist-reg-tabla-wrap">

      <table class="asist-punt-tabla" id="tabla-reg-personal">

        <thead>

          <tr>

            <th>Entrada</th>

            <th>Salida</th>

            <th>Nombre</th>

            <th>Rol</th>

            <th>Origen</th>

            <?php if ($puedeEliminar): ?><th></th><?php endif; ?>

          </tr>

        </thead>

        <tbody></tbody>

      </table>

    </div>

  </div>

</div>



<script>

window.HAY_REGISTROS_CONFIG = <?php echo json_encode([

    'api' => hay_asset_url('php/asistencia_registros_api.php'),

    'puede_eliminar' => $puedeEliminar,

    'vista_default' => 'checados',

    'tipo_default' => 'ambos',

], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/asistencia_registros.js?v=20260601'), ENT_QUOTES, 'UTF-8'); ?>"></script>


