<?php
require_once __DIR__ . '/_bootstrap.php';
/** @var PDO $pdo */
require_once __DIR__ . '/../php/grupo_apertura_helper.php';

if (!grupo_apertura_puede_gestionar()) {
    echo '<p>Sin permiso para gestionar apertura de grupos.</p>';
    return;
}

grupo_apertura_sync_estados($pdo);
$grupos = grupo_apertura_listar_pendientes($pdo);
$diasPreaviso = GRUPO_APERTURA_DIAS_PREAVISO;
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="result-container">
  <div class="result-header">
    <h2>Apertura de grupos</h2>
    <p style="margin:8px 0 0; color:#555; max-width:720px;">
      <?php echo (int) $diasPreaviso; ?> días antes del inicio programado, el director o coordinador debe
      <strong>autorizar la apertura</strong> (si hay alumnos suficientes) o <strong>posponer</strong> el grupo.
      Al posponer, la fecha de inicio y las colegiaturas anticipadas se recalculan al nuevo periodo
      (mes proporcional si el grupo inicia a mitad de mes).
    </p>
    <div class="disc-actions" style="margin-top:12px;">
      <button type="button" class="secondary" onclick="cargarSeccion('grupos')">Ver todos los grupos</button>
      <button type="button" id="btn-refrescar-apertura">Actualizar</button>
    </div>
  </div>

  <?php if (empty($grupos)): ?>
    <p class="patron-desc">No hay grupos pendientes de autorización con fecha futura.</p>
  <?php else: ?>
    <table class="ec-table" style="width:100%; margin-top:16px;">
      <thead>
        <tr>
          <th>Grupo</th>
          <th>Inicio</th>
          <th>Alumnos</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grupos as $g):
          $idG = (int) $g['id_grupo'];
          $min = (int) ($g['min_alumnos'] ?? 0);
          $tot = (int) ($g['total_alumnos'] ?? 0);
          $cumple = grupo_apertura_cumple_minimo($g);
          $estado = (string) ($g['estado_apertura'] ?? 'programado');
        ?>
          <tr data-id-grupo="<?php echo $idG; ?>">
            <td>
              <strong><?php echo htmlspecialchars((string) $g['clave']); ?></strong><br>
              <span style="color:#666; font-size:0.85rem;"><?php echo htmlspecialchars((string) ($g['especialidad_nombre'] ?? '')); ?></span>
              <?php if ((int) ($g['pospuestos'] ?? 0) > 0): ?>
                <br><span style="color:#e65100; font-size:0.8rem;">Pospuesto <?php echo (int) $g['pospuestos']; ?> vez(ces)</span>
              <?php endif; ?>
            </td>
            <td><?php echo date('d/m/Y', strtotime($g['fecha_inicio'])); ?></td>
            <td>
              <?php echo $tot; ?>
              <?php if ($min > 0): ?>
                <span style="color:<?php echo $cumple ? '#2e7d32' : '#c62828'; ?>;"> / mín. <?php echo $min; ?></span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars(grupo_apertura_etiqueta_estado($estado)); ?></td>
            <td style="white-space:nowrap;">
              <button type="button" class="btn-autorizar-grupo" data-id="<?php echo $idG; ?>"
                <?php echo $cumple ? '' : 'disabled title="No alcanza el mínimo de alumnos"'; ?>>
                Autorizar
              </button>
              <button type="button" class="secondary btn-posponer-grupo" data-id="<?php echo $idG; ?>"
                data-fecha="<?php echo htmlspecialchars((string) $g['fecha_inicio']); ?>">
                Posponer
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
(function () {
  const api = 'php/grupo_apertura_api.php';

  document.getElementById('btn-refrescar-apertura')?.addEventListener('click', () => {
    cargarSeccion('grupo_apertura');
  });

  document.querySelectorAll('.btn-autorizar-grupo').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('¿Autorizar la apertura de este grupo en la fecha programada?')) return;
      const fd = new FormData();
      fd.append('action', 'autorizar');
      fd.append('id_grupo', btn.dataset.id);
      try {
        const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
        alert(data.message || 'Listo');
        if (data.status === 'ok') cargarSeccion('grupo_apertura');
      } catch (e) { alert(e.message); }
    });
  });

  document.querySelectorAll('.btn-posponer-grupo').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const fechaActual = btn.dataset.fecha || '';
      const nueva = prompt(
        'Nueva fecha de inicio (AAAA-MM-DD).\nActual: ' + fechaActual
        + '\n\nLas colegiaturas ya pagadas se moverán al nuevo mes/semanas de inicio.',
        ''
      );
      if (!nueva || !nueva.trim()) return;
      const motivo = prompt('Motivo del posponimiento (opcional):', 'No se alcanzó el mínimo de alumnos') || '';
      const fd = new FormData();
      fd.append('action', 'posponer');
      fd.append('id_grupo', btn.dataset.id);
      fd.append('nueva_fecha', nueva.trim());
      fd.append('motivo', motivo);
      try {
        const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
        alert(data.message || 'Listo');
        if (data.status === 'ok') cargarSeccion('grupo_apertura');
      } catch (e) { alert(e.message); }
    });
  });
})();
</script>
