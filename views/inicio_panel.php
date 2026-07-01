<?php



require_once __DIR__ . '/../config.php';



if (!isset($pdo) || !($pdo instanceof PDO)) {

    require_once __DIR__ . '/_bootstrap.php';

}



$items = [];

$kpis = [];

$perfil = 'general';

$mostrarBusqueda = false;

$labels = [

    'director' => 'Vista director',

    'recepcion' => 'Vista recepción',

    'ventas' => 'Vista ventas',

    'gerente' => 'Vista gerente',

    'coordinador' => 'Vista coordinación',

    'profesor' => 'Vista docente',

    'supervisor' => 'Vista supervisión',

    'general' => 'Avisos',

];



try {

    $idPlantel = plantel_id_activo();

    $perfil = notificaciones_perfil_usuario();

    $items = notificaciones_panel_inicio($pdo, $idPlantel);

    if (!empty($_SESSION['user_id']) && function_exists('notificaciones_usuario_bd')) {

        $items = array_merge(notificaciones_usuario_bd($pdo, (int) $_SESSION['user_id'], 15), $items);

    }

    if (function_exists('operativo_panel_kpis') && in_array($perfil, ['recepcion', 'director'], true)) {

        $kpis = operativo_panel_kpis($pdo, $idPlantel, $perfil);

    }

    $mostrarBusqueda = function_exists('operativo_busqueda_puede')

        && operativo_busqueda_puede()

        && in_array($perfil, ['recepcion', 'director'], true);

} catch (Throwable $e) {

    error_log('inicio_panel: ' . $e->getMessage());

}

?>

<link rel="stylesheet" href="css/inicio_panel.css">



<div class="inicio-panel">

  <h2 class="inicio-panel__title"><i class="fas fa-th-large"></i> Panel — <?php echo htmlspecialchars($labels[$perfil] ?? $perfil); ?></h2>



  <?php if ($mostrarBusqueda): ?>

  <div class="inicio-panel__buscar-wrap">

    <h3 class="inicio-panel__subtitle"><i class="fas fa-search"></i> Búsqueda rápida de alumno</h3>

    <p class="inicio-panel__buscar-hint">Número de control, nombre o matrícula — adeudo, grupos, pagos y documentos.</p>

    <div class="inicio-panel__buscar-bar">

      <input type="search" id="inicio-buscar-q" class="inicio-panel__buscar-input" placeholder="Ej. 12557 o apellido…" autocomplete="off">

      <button type="button" class="primary" id="inicio-buscar-btn"><i class="fas fa-search"></i> Buscar</button>

    </div>

    <div id="inicio-buscar-sug" class="inicio-panel__sug" hidden></div>

    <div id="inicio-buscar-resultado" class="inicio-panel__resultado" hidden></div>

  </div>

  <?php endif; ?>



  <?php if ($kpis !== []): ?>

    <div class="inicio-panel__kpis">

      <?php foreach ($kpis as $k): ?>

        <button type="button" class="inicio-panel__kpi inicio-panel__kpi--<?php echo htmlspecialchars($k['prioridad'] ?? 'media'); ?>"

          data-seccion="<?php echo htmlspecialchars($k['enlace'] ?? ''); ?>"

          <?php if (!empty($k['query'])): ?>data-query="<?php echo htmlspecialchars($k['query']); ?>"<?php endif; ?>>

          <span class="inicio-panel__kpi-val"><?php echo htmlspecialchars($k['valor'] ?? '0'); ?></span>

          <span class="inicio-panel__kpi-lbl"><?php echo htmlspecialchars($k['titulo'] ?? ''); ?></span>

        </button>

      <?php endforeach; ?>

    </div>

  <?php endif; ?>



  <h3 class="inicio-panel__subtitle"><i class="fas fa-bell"></i> Avisos pendientes</h3>



  <?php if (empty($items)): ?>

    <p class="inicio-panel__empty">No hay alertas pendientes para tu perfil en este plantel.</p>

  <?php else: ?>

    <ul class="inicio-panel__list">

      <?php foreach ($items as $it): ?>

        <li class="inicio-panel__item inicio-panel__item--<?php echo htmlspecialchars($it['prioridad']); ?>">

          <div class="inicio-panel__item-head">

            <span class="inicio-panel__tipo"><?php echo htmlspecialchars($it['titulo']); ?></span>

            <?php if (!empty($it['enlace'])): ?>

              <button type="button" class="inicio-panel__link" data-seccion="<?php echo htmlspecialchars(strtok($it['enlace'], '&')); ?>"

                data-query="<?php echo htmlspecialchars($it['enlace']); ?>">Ver</button>

            <?php endif; ?>

          </div>

          <p class="inicio-panel__msg"><?php echo htmlspecialchars($it['mensaje']); ?></p>

        </li>

      <?php endforeach; ?>

    </ul>

  <?php endif; ?>

</div>



<script>

window.HAY_INICIO_PANEL = <?php echo json_encode([

    'api' => hay_asset_url('php/operativo_panel_api.php'),

], JSON_UNESCAPED_UNICODE); ?>;

</script>

