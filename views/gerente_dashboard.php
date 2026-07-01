<?php
require_once __DIR__ . '/../config.php';
if (!gerente_puede_panel() && !rbac_cap('menu_gerente_dashboard')) {
    echo '<div class="alert">Sin permiso para el panel gerente.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$podio = gerente_podio_asesores($pdo, null);
$top = array_slice($podio['items'] ?? [], 0, 5);
$captacion = gerente_reporte_captacion($pdo, $idPlantel, date('Y-m-01'), date('Y-m-d'));
$alertasGerente = gerente_notificaciones_panel($pdo, $idPlantel);
$bandeja = [];
if (!empty($_SESSION['user_id']) && function_exists('notificaciones_usuario_bd')) {
    $bandeja = notificaciones_usuario_bd($pdo, (int) $_SESSION['user_id'], 8);
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-chart-line"></i> Panel gerente</h2>
    <p style="color:#666;">Resumen de captación del plantel y ranking semanal de asesores (todos los planteles en el podio).</p>
  </div>

  <div class="catalog-toolbar" style="gap:12px; flex-wrap:wrap;">
    <button type="button" class="primary" onclick="cargarSeccion('gerente_reportes_captacion')">
      <i class="fas fa-chart-pie"></i> Reportes de captación
    </button>
    <button type="button" onclick="cargarSeccion('gerente_reporte_geografico')">
      <i class="fas fa-map-marked-alt"></i> Reporte geográfico
    </button>
    <button type="button" onclick="cargarSeccion('gerente_reporte_proyeccion')">
      <i class="fas fa-lightbulb"></i> Proyección de demanda
    </button>
    <button type="button" onclick="cargarSeccion('gerente_podio')">
      <i class="fas fa-trophy"></i> Ver podio completo
    </button>
    <button type="button" onclick="cargarSeccion('gerente_reporte_pendientes')">
      <i class="fas fa-tasks"></i> Pendientes del plantel
    </button>
    <button type="button" onclick="cargarSeccion('asesor_entrevistas')">
      <i class="fas fa-handshake"></i> Entrevistas del equipo
    </button>
  </div>

  <?php if (!empty($alertasGerente) || !empty($bandeja)): ?>
  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3><i class="fas fa-bell"></i> Alertas del plantel</h3>
    <ul style="margin:8px 0 0; padding-left:20px;">
      <?php foreach ($bandeja as $b): ?>
        <li style="margin-bottom:6px;">
          <strong><?php echo htmlspecialchars($b['titulo'] ?? ''); ?></strong>:
          <?php echo htmlspecialchars($b['mensaje'] ?? ''); ?>
        </li>
      <?php endforeach; ?>
      <?php foreach (array_slice($alertasGerente, 0, 6) as $a): ?>
        <li style="margin-bottom:6px;">
          <strong><?php echo htmlspecialchars($a['titulo'] ?? ''); ?></strong>:
          <?php echo htmlspecialchars($a['mensaje'] ?? ''); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; margin-top:20px;">
    <div class="welcome-card" style="padding:16px;">
      <h3 style="margin:0 0 8px;">Inscripciones del mes</h3>
      <p style="font-size:2rem; margin:0; color:#1565c0;">
        <?php
        $totalMes = 0;
        foreach ($captacion['inscripciones_dia'] ?? [] as $d) {
            $totalMes += (int) $d['total'];
        }
        echo (int) $totalMes;
        ?>
      </p>
    </div>
    <div class="welcome-card" style="padding:16px;">
      <h3 style="margin:0 0 8px;">Entrevistas del mes</h3>
      <p style="font-size:2rem; margin:0; color:#2e7d32;">
        <?php
        $entMes = 0;
        foreach ($captacion['entrevistas_dia'] ?? [] as $d) {
            $entMes += (int) $d['total'];
        }
        echo (int) $entMes;
        ?>
      </p>
    </div>
    <div class="welcome-card" style="padding:16px;">
      <h3 style="margin:0 0 8px;">Semana del podio</h3>
      <p style="margin:0; color:#666;">
        <?php echo htmlspecialchars($podio['desde'] ?? ''); ?> — <?php echo htmlspecialchars($podio['hasta'] ?? ''); ?>
      </p>
    </div>
  </div>

  <div id="gerente-podio" class="welcome-card" style="margin-top:20px; padding:16px;">
    <h3><i class="fas fa-trophy" style="color:#ffc107;"></i> Top asesores de la semana</h3>
    <?php if (empty($top)): ?>
      <p style="color:#888;">Aún no hay actividad registrada esta semana.</p>
    <?php else: ?>
      <ol style="margin:12px 0 0; padding-left:20px;">
        <?php foreach ($top as $i => $row): ?>
          <li style="margin-bottom:8px;">
            <strong><?php echo htmlspecialchars(trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''))); ?></strong>
            <span style="color:#666;"> · <?php echo htmlspecialchars($row['plantel'] ?? ''); ?></span>
            <br>
            <small>
              <?php echo (int) ($row['entrevistas'] ?? 0); ?> entrevistas ·
              <?php echo (int) ($row['preregistros'] ?? 0); ?> pre-registros ·
              <?php echo (int) ($row['inscritos'] ?? 0); ?> inscritos
              (<?php echo (int) ($row['puntos'] ?? 0); ?> pts)
            </small>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>

  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3>Origen de inscritos (mes actual)</h3>
    <?php if (empty($captacion['origen'])): ?>
      <p style="color:#888;">Sin datos aún.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($captacion['origen'] as $o): ?>
          <li><?php echo htmlspecialchars($o['origen']); ?>: <strong><?php echo (int) $o['total']; ?></strong></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p style="color:#888; font-size:0.85rem; margin-top:12px;">
      Recibirá alertas en Inicio y en su bandeja cuando haya inscripciones o pre-registros del equipo.
    </p>
  </div>
</div>
