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
    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);
    $perfil = notificaciones_perfil_usuario();
    if ($idUsuario > 0 && function_exists('notificaciones_panel_lista_completa')) {
        $items = notificaciones_panel_lista_completa($pdo, $idUsuario, $idPlantel, 50);
    } else {
        $items = notificaciones_panel_inicio($pdo, $idPlantel);
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

$totalAvisos = count($items);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/inicio_panel.css?v=20260701'), ENT_QUOTES, 'UTF-8'); ?>">

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

  <div class="inicio-panel__avisos-head">
    <h3 class="inicio-panel__subtitle"><i class="fas fa-bell"></i> Avisos pendientes<?php if ($totalAvisos > 0): ?> <span class="inicio-panel__count">(<?php echo (int) $totalAvisos; ?>)</span><?php endif; ?></h3>
    <?php if ($totalAvisos > 0): ?>
    <div class="inicio-panel__avisos-actions">
      <button type="button" class="secondary inicio-panel__aviso-bulk" data-notif-accion="marcar_todas" title="Ocultar todos los avisos visibles">Marcar todas leídas</button>
      <button type="button" class="secondary inicio-panel__aviso-bulk" data-notif-accion="archivar_todas" title="Archivar todos los avisos visibles">Archivar todas</button>
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($items)): ?>
    <p class="inicio-panel__empty" id="inicio-panel-empty">No hay alertas pendientes para tu perfil en este plantel.</p>
  <?php else: ?>
    <ul class="inicio-panel__list" id="inicio-panel-list">
      <?php foreach ($items as $it): ?>
        <?php
        $clave = htmlspecialchars((string) ($it['clave'] ?? ''), ENT_QUOTES, 'UTF-8');
        $idNotif = (int) ($it['id_notificacion'] ?? 0);
        ?>
        <li class="inicio-panel__item inicio-panel__item--<?php echo htmlspecialchars($it['prioridad']); ?>"
            data-notif-clave="<?php echo $clave; ?>"
            <?php if ($idNotif > 0): ?>data-notif-id="<?php echo $idNotif; ?>"<?php endif; ?>>
          <div class="inicio-panel__item-head">
            <span class="inicio-panel__tipo"><?php echo htmlspecialchars($it['titulo']); ?></span>
            <div class="inicio-panel__item-actions">
              <?php if (!empty($it['enlace'])): ?>
                <button type="button" class="inicio-panel__link" data-seccion="<?php echo htmlspecialchars(strtok($it['enlace'], '&')); ?>"
                  data-query="<?php echo htmlspecialchars($it['enlace']); ?>">Ver</button>
              <?php endif; ?>
              <button type="button" class="inicio-panel__aviso-btn" data-notif-accion="marcar_leida" title="Marcar como leída"><i class="fas fa-check"></i></button>
              <button type="button" class="inicio-panel__aviso-btn inicio-panel__aviso-btn--muted" data-notif-accion="archivar" title="Archivar"><i class="fas fa-archive"></i></button>
            </div>
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
    'notifApi' => hay_asset_url('php/notificaciones_panel_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
