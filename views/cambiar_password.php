<?php

require_once __DIR__ . '/_bootstrap.php';

if (!isset($_SESSION['user_id'])) {

    echo '<div class="alert">Sesión no válida.</div>';

    return;

}



$obligatorio = !empty($_SESSION['debe_cambiar_password']);

$apiUrl = hay_asset_url('php/cambiar_password.php');

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/resultados.css'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="result-container">

  <div class="result-header">

    <h2><i class="fas fa-key"></i> <?php echo $obligatorio ? 'Defina su contraseña' : 'Cambiar contraseña'; ?></h2>

  </div>



  <?php if ($obligatorio): ?>

  <div class="welcome-card" style="text-align:left; max-width:520px; margin-bottom:14px; background:#fff8e1; border:1px solid #ffe082;">

    <p style="margin:0; color:#5c4a00;">

      <i class="fas fa-info-circle"></i>

      Es su <strong>primer acceso</strong> con contraseña automática. Elija una contraseña personal antes de usar el sistema.

    </p>

  </div>

  <?php endif; ?>



  <div class="welcome-card" style="text-align:left; max-width:440px;">

    <form id="form-cambiar-password" novalidate>

      <div class="field" style="margin-bottom:14px;">

        <label for="password_actual"><?php echo $obligatorio ? 'Contraseña temporal (la que le dieron)' : 'Contraseña actual'; ?></label>

        <div class="password-input-wrap">

          <input type="password" id="password_actual" name="password_actual" required autocomplete="current-password">

          <button type="button" class="btn-toggle-password" data-target="password_actual" aria-label="Mostrar contraseña" title="Mostrar contraseña">

            <i class="fas fa-eye" aria-hidden="true"></i>

          </button>

        </div>

      </div>

      <div class="field" style="margin-bottom:14px;">

        <label for="password_nueva">Nueva contraseña</label>

        <div class="password-input-wrap">

          <input type="password" id="password_nueva" name="password_nueva" required minlength="6" autocomplete="new-password">

          <button type="button" class="btn-toggle-password" data-target="password_nueva" aria-label="Mostrar contraseña" title="Mostrar contraseña">

            <i class="fas fa-eye" aria-hidden="true"></i>

          </button>

        </div>

      </div>

      <div class="field" style="margin-bottom:18px;">

        <label for="password_confirm">Confirmar nueva contraseña</label>

        <div class="password-input-wrap">

          <input type="password" id="password_confirm" name="password_confirm" required minlength="6" autocomplete="new-password">

          <button type="button" class="btn-toggle-password" data-target="password_confirm" aria-label="Mostrar contraseña" title="Mostrar contraseña">

            <i class="fas fa-eye" aria-hidden="true"></i>

          </button>

        </div>

      </div>

      <button type="submit" class="btn-guardar" id="btn-guardar-password">Guardar contraseña</button>

      <div id="respuesta-password" style="display:none; margin-top:14px;"></div>

    </form>

  </div>

</div>



<script>

(function () {

  const api = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;

  const obligatorio = <?php echo $obligatorio ? 'true' : 'false'; ?>;

  const form = document.getElementById('form-cambiar-password');

  const msg = document.getElementById('respuesta-password');

  const btn = document.getElementById('btn-guardar-password');



  form?.addEventListener('submit', async (e) => {

    e.preventDefault();

    if (msg) { msg.style.display = 'none'; }

    const fd = new FormData(form);

    if (btn) { btn.disabled = true; }

    try {

      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });

      const d = await r.json();

      if (msg) {

        msg.style.display = 'block';

        msg.className = d.status === 'ok' ? 'alert alert-success' : 'alert alert-error';

        msg.textContent = d.message || (d.status === 'ok' ? 'Contraseña guardada' : 'Error');

      }

      if (d.status === 'ok') {

        window.HAY_DEBE_CAMBIAR_PASSWORD = false;

        document.body.classList.remove('hay-debe-cambiar-password');

        let destino = <?php echo json_encode(rbac_rol_efectivo() === 'alumno' ? 'alumno_portal_inicio' : 'inicio_panel'); ?>;

        if (obligatorio && d.debe_aceptar_acuerdo) {
          destino = 'alumno_acuerdo_aceptar';
          window.HAY_DEBE_ACEPTAR_ACUERDO = true;
          document.body.classList.add('hay-debe-aceptar-acuerdo');
        } else if (obligatorio && d.debe_completar_perfil) {
          destino = 'alumno_perfil_gustos';
          window.HAY_DEBE_COMPLETAR_PERFIL = true;
          document.body.classList.add('hay-debe-completar-perfil');
        }

        setTimeout(() => {

          if (typeof cargarSeccion === 'function') {

            cargarSeccion(destino);

          } else {

            location.reload();

          }

        }, 800);

      }

    } catch (err) {

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'alert alert-error';

        msg.textContent = 'No se pudo guardar. Verifique su conexión.';

      }

    } finally {

      if (btn) btn.disabled = false;

    }

  });



  if (obligatorio && typeof initPasswordToggles === 'function') {

    initPasswordToggles(form?.closest('.result-container') || document);

  }

})();

</script>

