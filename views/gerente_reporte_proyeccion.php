<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_gerente_proyeccion')) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$desde = trim($_GET['desde'] ?? date('Y-m-d', strtotime('-90 days')));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$rep = gerente_reporte_proyeccion($pdo, $idPlantel, $desde, $hasta);
$labelsPerd = preregistro_labels()['categoria_perdido'] ?? [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-lightbulb"></i> Proyección de demanda</h2>
    <p style="color:#666;">Sugerencias según pre-registros, inscripciones recientes y pendientes del plantel.</p>
  </div>

  <form class="catalog-toolbar" onsubmit="event.preventDefault(); cargarSeccion('gerente_reporte_proyeccion','desde='+encodeURIComponent(document.getElementById('gp-desde').value)+'&hasta='+encodeURIComponent(document.getElementById('gp-hasta').value));">
    <div>
      <label>Desde</label>
      <input type="date" id="gp-desde" value="<?php echo htmlspecialchars($desde); ?>">
    </div>
    <div>
      <label>Hasta</label>
      <input type="date" id="gp-hasta" value="<?php echo htmlspecialchars($hasta); ?>">
    </div>
    <div style="align-self:end;">
      <button type="submit" class="primary">Actualizar</button>
    </div>
  </form>

  <div class="welcome-card" style="margin-top:16px; padding:16px; border-left:4px solid #ffc107;">
    <h3 style="margin:0 0 12px;"><i class="fas fa-magic"></i> Sugerencias</h3>
    <ul style="margin:0; padding-left:20px;">
      <?php foreach ($rep['sugerencias'] ?? [] as $s): ?>
        <li style="margin-bottom:8px;"><?php echo htmlspecialchars($s); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; margin-top:16px;">
    <div class="welcome-card" style="padding:16px;">
      <h3>Cursos más solicitados</h3>
      <?php if (empty($rep['cursos_demandados'])): ?>
        <p style="color:#888;">Sin datos en el periodo.</p>
      <?php else: ?>
        <table class="catalog-table">
          <thead><tr><th>Curso</th><th>Interesados</th><th>Estado</th></tr></thead>
          <tbody>
          <?php foreach ($rep['cursos_demandados'] as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['curso'] ?? ''); ?></td>
              <td><strong><?php echo (int) ($c['total'] ?? 0); ?></strong></td>
              <td><?php echo (int) ($c['abierto'] ?? 1) ? 'Abierto' : 'Cerrado'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="welcome-card" style="padding:16px;">
      <h3>Esperan apertura de curso</h3>
      <?php if (empty($rep['espera_apertura'])): ?>
        <p style="color:#888;">Nadie en lista de espera.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($rep['espera_apertura'] as $e): ?>
            <li>
              <strong><?php echo htmlspecialchars($e['curso'] ?? ''); ?></strong>:
              <?php echo (int) ($e['total'] ?? 0); ?> prospecto(s)
              <?php if (!empty($e['apertura_prevista'])): ?>
                · previsto <?php echo date('d/m/Y', strtotime($e['apertura_prevista'])); ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
    <div class="welcome-card" style="padding:16px;">
      <h3>Horarios con más inscripciones</h3>
      <?php if (empty($rep['horarios_populares'])): ?>
        <p style="color:#888;">Sin inscripciones con horario en el periodo.</p>
      <?php else: ?>
        <table class="catalog-table">
          <thead><tr><th>Horario</th><th>Inscritos</th></tr></thead>
          <tbody>
          <?php foreach ($rep['horarios_populares'] as $h): ?>
            <tr>
              <td><?php echo htmlspecialchars($h['etiqueta'] ?? ''); ?></td>
              <td><?php echo (int) ($h['total'] ?? 0); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="welcome-card" style="padding:16px;">
      <h3>Motivos de no inscripción</h3>
      <?php if (empty($rep['motivos_perdido'])): ?>
        <p style="color:#888;">Sin registros perdidos en el periodo.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($rep['motivos_perdido'] as $m): ?>
            <li><?php echo htmlspecialchars($labelsPerd[$m['motivo'] ?? ''] ?? $m['motivo'] ?? ''); ?>: <strong><?php echo (int) ($m['total'] ?? 0); ?></strong></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($rep['sin_horario'])): ?>
  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3>Pendientes sin horario compatible (<?php echo count($rep['sin_horario']); ?>)</h3>
    <div class="catalog-table-wrap">
      <table class="catalog-table">
        <thead><tr><th>Prospecto</th><th>Curso</th><th>Asesor</th><th>Notas</th></tr></thead>
        <tbody>
        <?php foreach ($rep['sin_horario'] as $s): ?>
          <tr>
            <td><?php echo htmlspecialchars(trim(($s['nombres'] ?? '') . ' ' . ($s['apellido_paterno'] ?? ''))); ?></td>
            <td><?php echo htmlspecialchars($s['curso'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($s['asesor'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars(mb_substr((string) ($s['motivo_pendiente'] ?? ''), 0, 60)); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
