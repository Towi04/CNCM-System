<?php

require_once __DIR__ . '/../config.php';

if (!profesor_portal_puede_revisar_permisos()) {

    echo '<div class="alert">Sin permiso para revisar permisos de profesores.</div>';

    return;

}



$idPlantel = plantel_scope_id($pdo);

$pendientes = profesor_portal_permisos_pendientes($pdo, $idPlantel);

$puedeSuplencia = function_exists('suplencia_puede_gestionar') && suplencia_puede_gestionar();

?>

<link rel="stylesheet" href="css/admin_catalogo.css">

<link rel="stylesheet" href="css/hay_buttons.css">



<div class="catalog-wrap">

  <h2>Permisos de profesores</h2>

  <p style="color:#666; margin-bottom:16px;">Al aprobar una falta, puede registrar la suplencia del grupo para que la nómina pague al maestro que cubre.</p>

  <div id="respuesta-permisos-admin" class="catalog-alert" style="display:none;"></div>



  <?php if ($pendientes === []): ?>

    <p>No hay solicitudes pendientes.</p>

  <?php else: ?>

    <?php foreach ($pendientes as $p):

      $idProf = (int) ($p['id_usuario'] ?? 0);

      $grupos = profesor_portal_grupos($pdo, $idProf, $idPlantel);

      $notasSup = 'Permiso #' . (int) $p['id_solicitud'] . ': ' . trim((string) ($p['motivo'] ?? ''));

    ?>

      <div class="catalog-card permiso-card" style="margin-bottom:16px; padding:16px; border:1px solid #eee; border-radius:10px;">

        <strong><?php echo htmlspecialchars(trim($p['nombre'] . ' ' . $p['apellido'])); ?></strong>

        <p><?php echo date('d/m/Y', strtotime($p['fecha_inicio'])); ?> – <?php echo date('d/m/Y', strtotime($p['fecha_fin'])); ?></p>

        <p style="color:#444;"><?php echo nl2br(htmlspecialchars($p['motivo'])); ?></p>

        <?php if ($grupos !== []): ?>

          <p style="font-size:0.88rem; color:#666;">Grupos: <?php echo htmlspecialchars(implode(', ', array_column($grupos, 'clave'))); ?></p>

        <?php endif; ?>

        <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">

          <?php if ($puedeSuplencia): ?>

            <button type="button" class="secondary btn-suplencia-permiso"

              data-titular="<?php echo $idProf; ?>"

              data-desde="<?php echo htmlspecialchars(substr((string) $p['fecha_inicio'], 0, 10)); ?>"

              data-hasta="<?php echo htmlspecialchars(substr((string) $p['fecha_fin'], 0, 10)); ?>"

              data-notas="<?php echo htmlspecialchars($notasSup); ?>"

              data-grupo="<?php echo $grupos ? (int) ($grupos[0]['id_grupo'] ?? 0) : 0; ?>">

              <i class="fas fa-exchange-alt"></i> Registrar suplencia

            </button>

          <?php endif; ?>

        </div>

        <form class="form-resolver-permiso" data-id="<?php echo (int)$p['id_solicitud']; ?>" style="margin-top:12px;">

          <input type="text" name="comentario" placeholder="Comentario (opcional)" style="width:100%; margin-bottom:8px; padding:8px;">

          <button type="submit" name="estado" value="aprobado" class="primary">Aprobar</button>

          <button type="submit" name="estado" value="rechazado" class="secondary">Rechazar</button>

        </form>

      </div>

    <?php endforeach; ?>

  <?php endif; ?>

</div>



<script>

(function () {

  const msg = document.getElementById('respuesta-permisos-admin');



  document.querySelectorAll('.btn-suplencia-permiso').forEach((btn) => {

    btn.addEventListener('click', () => {

      if (typeof cargarSeccion !== 'function') return;

      cargarSeccion('director_nomina', {

        tab: 'suplencias',

        sup_titular: btn.dataset.titular || '',

        sup_desde: btn.dataset.desde || '',

        sup_hasta: btn.dataset.hasta || '',

        sup_notas: btn.dataset.notas || '',

        sup_grupo: btn.dataset.grupo || '',

      });

    });

  });



  document.querySelectorAll('.form-resolver-permiso').forEach((form) => {

    form.addEventListener('submit', async (e) => {

      const btn = e.submitter;

      if (!btn || !btn.value) return;

      e.preventDefault();

      const fd = new FormData();

      fd.append('action', 'resolver_permiso');

      fd.append('id_solicitud', form.dataset.id);

      fd.append('estado', btn.value);

      fd.append('comentario', form.querySelector('[name=comentario]').value);

      try {

        const { data } = await hayFetchJson('php/profesor_portal_api.php', { method: 'POST', body: fd });

        if (msg) {

          msg.style.display = 'block';

          msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');

          msg.textContent = data.message || '';

        }

        if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);

      } catch (err) {

        if (msg) {

          msg.style.display = 'block';

          msg.className = 'catalog-alert catalog-alert--error';

          msg.textContent = err.message;

        }

      }

    });

  });

})();

</script>


