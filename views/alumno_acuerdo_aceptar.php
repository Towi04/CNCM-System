<?php

require_once __DIR__ . '/_bootstrap.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id'])) {

    echo '<div class="alert">Sesión no válida.</div>';

    return;

}



if (!function_exists('alumno_portal_puede_ver') || !alumno_portal_puede_ver()) {

    echo '<div class="alert">Esta sección es solo para alumnos.</div>';

    return;

}



$idAlumno = alumno_portal_id_o_detener();

if ($idAlumno <= 0) {

    return;

}



$obligatorio = function_exists('alumno_debe_aceptar_acuerdo')

    ? alumno_debe_aceptar_acuerdo($pdo, (int) $_SESSION['user_id'])

    : false;

$acuerdo = acuerdo_pendiente_para_alumno($pdo, $idAlumno);

if (!$acuerdo) {

    if ($obligatorio) {

        acuerdo_asignar_alumno($pdo, $idAlumno);

        $acuerdo = acuerdo_pendiente_para_alumno($pdo, $idAlumno);

    }

}

if (!$acuerdo) {

    echo '<div class="alert">No hay acuerdo pendiente. <button type="button" class="primary" onclick="cargarSeccion(\'alumno_portal_inicio\')">Ir al inicio</button></div>';

    return;

}



$apiUrl = hay_asset_url('php/acuerdo_escolar_api.php');

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/resultados.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/supervisor_acuerdo_escolar.css'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="result-container acuerdo-alumno-wrap">

  <div class="result-header">

    <h2><i class="fas fa-file-signature"></i> Acuerdo escolar</h2>

    <?php if ($obligatorio): ?>

      <p style="color:#b45309; margin:8px 0 0;">Debe aceptar el acuerdo para continuar usando el portal.</p>

    <?php endif; ?>

  </div>



  <div class="welcome-card acuerdo-alumno-texto">

    <p class="acuerdo-version-tag">Versión <?php echo htmlspecialchars($acuerdo['version_label'] ?? ''); ?></p>

    <pre class="acuerdo-contenido-alumno"><?php echo htmlspecialchars((string) ($acuerdo['contenido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>

  </div>



  <form id="form-acuerdo-aceptar" class="welcome-card" style="padding:16px; margin-top:16px;">

    <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">

      <input type="checkbox" id="acuerdo-check-acepto" name="acepto" value="1" required style="margin-top:4px;">

      <span>He leído y acepto el acuerdo escolar en su versión vigente. Entiendo que es obligatorio para continuar mis estudios en CNCM.</span>

    </label>

    <div style="margin-top:16px; display:flex; flex-wrap:wrap; gap:10px;">

      <button type="submit" class="primary" id="btn-acuerdo-aceptar">Aceptar y continuar</button>

      <?php if (!$obligatorio): ?>

        <button type="button" class="secondary" onclick="cargarSeccion('alumno_portal_inicio')">Cancelar</button>

      <?php endif; ?>

    </div>

    <div id="acuerdo-aceptar-msg" class="asist-checada-msg" style="display:none; margin-top:12px;"></div>

  </form>

</div>



<script>

(function () {

  const api = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;

  const form = document.getElementById('form-acuerdo-aceptar');

  const msg = document.getElementById('acuerdo-aceptar-msg');

  const btn = document.getElementById('btn-acuerdo-aceptar');



  form?.addEventListener('submit', async function (e) {

    e.preventDefault();

    if (!document.getElementById('acuerdo-check-acepto')?.checked) {

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'asist-checada-msg err';

        msg.textContent = 'Marque la casilla de aceptación.';

      }

      return;

    }

    const fd = new FormData();

    fd.append('action', 'aceptar');

    fd.append('acepto', '1');

    if (btn) btn.disabled = true;

    try {

      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });

      const d = await r.json();

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'asist-checada-msg ' + (d.status === 'ok' ? 'ok' : 'err');

        msg.textContent = d.message || '';

      }

      if (d.status === 'ok') {

        window.HAY_DEBE_ACEPTAR_ACUERDO = false;

        document.body.classList.remove('hay-debe-aceptar-acuerdo');

        const destino = d.debe_completar_perfil ? 'alumno_perfil_gustos' : 'alumno_portal_inicio';

        if (d.debe_completar_perfil) {

          window.HAY_DEBE_COMPLETAR_PERFIL = true;

          document.body.classList.add('hay-debe-completar-perfil');

        }

        setTimeout(() => {

          if (typeof cargarSeccion === 'function') cargarSeccion(destino);

        }, 700);

      }

    } catch (err) {

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'asist-checada-msg err';

        msg.textContent = 'Error de conexión.';

      }

    }

    if (btn) btn.disabled = false;

  });

})();

</script>


