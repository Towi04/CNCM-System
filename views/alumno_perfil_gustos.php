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

$obligatorio = function_exists('alumno_debe_completar_perfil')
    ? alumno_debe_completar_perfil($pdo, (int) $_SESSION['user_id'])
    : false;
$perfil = alumno_perfil_obtener($pdo, $idAlumno);
$json = $perfil['perfil_intereses_json'] ?? null;
if (is_string($json)) {
    $json = json_decode($json, true);
}
if (!is_array($json)) {
    $json = [];
}

$apiUrl = hay_asset_url('php/alumno_perfil_api.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/resultados.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="result-container">
  <div class="result-header">
    <h2><i class="fas fa-heart"></i> Cuéntanos sobre ti</h2>
  </div>

  <?php if ($obligatorio): ?>
  <div class="welcome-card" style="text-align:left; max-width:560px; margin-bottom:14px; background:#e8f5e9; border:1px solid #a5d6a7;">
    <p style="margin:0; color:#1b5e20;">
      <i class="fas fa-seedling"></i>
      Antes de empezar, comparte un poco sobre tus <strong>gustos e intereses</strong>.
      El Tutor IA y tus profesores podrán usar ejemplos que te resulten más familiares y motivadores.
    </p>
  </div>
  <?php else: ?>
  <p style="color:#666; max-width:560px;">Puedes actualizar tu perfil cuando quieras. Esto ayuda a personalizar el Tutor IA.</p>
  <?php endif; ?>

  <div class="welcome-card" style="text-align:left; max-width:560px;">
    <form id="form-perfil-gustos" novalidate>
      <div class="field" style="margin-bottom:14px;">
        <label for="hobbies">¿Qué te gusta hacer en tu tiempo libre? *</label>
        <textarea id="hobbies" name="hobbies" rows="2" required maxlength="500" placeholder="Ej.: videojuegos, fútbol, dibujar, música, cocinar…"><?php echo htmlspecialchars((string) ($json['hobbies'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>
      <div class="field" style="margin-bottom:14px;">
        <label for="materias_favoritas">Temas o materias que más te interesan *</label>
        <textarea id="materias_favoritas" name="materias_favoritas" rows="2" required maxlength="500" placeholder="Ej.: tecnología, animales, viajes, deportes, ciencia…"><?php echo htmlspecialchars((string) ($json['materias_favoritas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>
      <div class="field" style="margin-bottom:14px;">
        <label for="como_aprende">¿Cómo aprendes mejor?</label>
        <select id="como_aprende" name="como_aprende">
          <?php
          $opts = [
              '' => '— Seleccione —',
              'practica' => 'Practicando y haciendo ejercicios',
              'visual' => 'Con imágenes, videos o diagramas',
              'lectura' => 'Leyendo y tomando notas',
              'oral' => 'Escuchando y conversando',
              'juegos' => 'Con juegos y retos',
          ];
          $sel = (string) ($json['como_aprende'] ?? '');
          foreach ($opts as $v => $label):
          ?>
          <option value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $sel === $v ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:14px;">
        <label for="meta">¿Qué te gustaría lograr con tus estudios? (opcional)</label>
        <input type="text" id="meta" name="meta" maxlength="300" value="<?php echo htmlspecialchars((string) ($json['meta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej.: hablar inglés en el trabajo, aprobar el examen…">
      </div>
      <div class="field" style="margin-bottom:18px;">
        <label for="gustos_libre">Algo más que quieras contarnos (opcional)</label>
        <textarea id="gustos_libre" name="gustos_libre" rows="3" maxlength="800" placeholder="Cualquier detalle que ayude a personalizar tus clases…"><?php echo htmlspecialchars((string) ($perfil['perfil_gustos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>
      <button type="submit" class="btn-guardar" id="btn-guardar-perfil">Guardar mi perfil</button>
      <?php if (!$obligatorio): ?>
      <button type="button" class="secondary" style="margin-left:8px;" onclick="cargarSeccion('alumno_mi_perfil')">Cancelar</button>
      <?php endif; ?>
      <div id="respuesta-perfil" style="display:none; margin-top:14px;"></div>
    </form>
  </div>
</div>

<script>
(function () {
  const api = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;
  const obligatorio = <?php echo $obligatorio ? 'true' : 'false'; ?>;
  const form = document.getElementById('form-perfil-gustos');
  const msg = document.getElementById('respuesta-perfil');
  const btn = document.getElementById('btn-guardar-perfil');

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (msg) msg.style.display = 'none';
    if (btn) btn.disabled = true;
    try {
      const r = await fetch(api, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
      const d = await r.json();
      if (msg) {
        msg.style.display = 'block';
        msg.className = d.status === 'ok' ? 'alert alert-success' : 'alert alert-error';
        msg.textContent = d.message || (d.status === 'ok' ? 'Perfil guardado' : 'Error');
      }
      if (d.status === 'ok') {
        window.HAY_DEBE_COMPLETAR_PERFIL = false;
        document.body.classList.remove('hay-debe-completar-perfil');
        setTimeout(() => {
          if (typeof cargarSeccion === 'function') {
            cargarSeccion('alumno_portal_inicio');
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
})();
</script>
