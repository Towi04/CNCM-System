<?php
require_once __DIR__ . '/../config.php';
tutor_ensure_schema($pdo);

if (!tutor_puede_usar()) {
    echo '<div class="alert">No tienes permiso para usar el Tutor Académico.</div>';
    return;
}

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$iaOk = function_exists('hay_ai_configured') && hay_ai_configured();
$iaLabel = function_exists('hay_ai_provider_label') ? hay_ai_provider_label() : 'IA';
$csrf = tutor_csrf_token();
$rolEfectivo = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
$hintAcceso = match ($rolEfectivo) {
    'alumno' => 'Solo puede consultar al tutor asignado a su(s) grupo(s) activo(s).',
    'profesor' => 'Acceso a los tutores de las especialidades que imparte.',
    'supervisor', 'admin', 'director', 'coordinador', 'coordinacion' => 'Acceso a todos los tutores institucionales.',
    'asesor', 'gerente', 'recepcion', 'caja' => 'Asistente institucional para consultas generales del sistema.',
    default => 'Las respuestas priorizan el temario institucional de CNCM.',
};
?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/tutor.css?v=20260623c'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">

<div class="tutor-app<?php echo $embed ? ' tutor-app--embed' : ''; ?>" id="tutor-app">
  <?php if (!$embed): ?>
  <header class="tutor-header">
    <div>
      <h2><i class="fas fa-graduation-cap"></i> Tutor Académico Institucional</h2>
      <p class="tutor-header__sub">Apoyo con IA basado en el temario CNCM · <?php echo htmlspecialchars($iaLabel, ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="tutor-header__hint tutor-muted"><?php echo htmlspecialchars($hintAcceso, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <?php if (!$iaOk): ?>
    <div class="tutor-alert tutor-alert--warn">Configure OPENROUTER_API_KEY en config.local.php</div>
    <?php endif; ?>
  </header>
  <?php endif; ?>

  <div class="tutor-layout">
    <aside class="tutor-sidebar" id="tutor-sidebar">
      <div class="tutor-sidebar__section tutor-sidebar__section--tutores">
        <h3>Tutores</h3>
        <ul class="tutor-tutor-list" id="tutor-tutor-list">
          <li class="tutor-loading">Cargando…</li>
        </ul>
      </div>
      <div class="tutor-sidebar__section">
        <div class="tutor-sidebar__head">
          <h3>Conversaciones</h3>
          <div class="tutor-sidebar__actions">
            <button type="button" class="tutor-btn-icon" id="tutor-btn-toggle-arch" title="Ver archivadas"><i class="fas fa-archive"></i></button>
            <button type="button" class="tutor-btn-icon" id="tutor-btn-refresh" title="Actualizar"><i class="fas fa-sync-alt"></i></button>
          </div>
        </div>
        <ul class="tutor-conv-list" id="tutor-conv-list">
          <li class="tutor-empty">Sin conversaciones aún</li>
        </ul>
      </div>
    </aside>

    <main class="tutor-chat" id="tutor-chat">
      <div class="tutor-chat__empty" id="tutor-chat-empty">
        <i class="fas fa-comments"></i>
        <p>Seleccione un tutor y comience una conversación</p>
        <p class="tutor-muted"><?php echo htmlspecialchars($hintAcceso, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>

      <div class="tutor-chat__active" id="tutor-chat-active" hidden>
        <header class="tutor-chat__head">
          <div>
            <strong id="tutor-chat-tutor-name">Tutor</strong>
            <span class="tutor-badge" id="tutor-chat-esp"></span>
          </div>
        </header>
        <div class="tutor-messages" id="tutor-messages" role="log" aria-live="polite"></div>
        <div class="tutor-typing" id="tutor-typing" hidden>
          <span></span><span></span><span></span> Escribiendo…
        </div>
        <form class="tutor-compose" id="tutor-compose">
          <textarea id="tutor-input" rows="2" maxlength="4000" placeholder="Escriba su pregunta…" required></textarea>
          <button type="submit" class="tutor-send" id="tutor-send" <?php echo $iaOk ? '' : 'disabled'; ?>>
            <i class="fas fa-paper-plane"></i>
          </button>
        </form>
      </div>
    </main>
  </div>

  <button type="button" class="tutor-mobile-toggle" id="tutor-mobile-toggle" aria-label="Abrir lista de tutores" title="Tutores y conversaciones">
    <i class="fas fa-bars"></i>
  </button>
</div>

<script>
window.HAY_TUTOR_CONFIG = {
  api: <?php echo json_encode(hay_asset_url('php/tutor_api.php'), JSON_UNESCAPED_UNICODE); ?>,
  csrf: <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>,
  iaOk: <?php echo $iaOk ? 'true' : 'false'; ?>,
  embed: <?php echo $embed ? 'true' : 'false'; ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/tutor.js?v=20260623c'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
if (window.hayTutorChatInit) {
  window.hayTutorChatInit(document.getElementById('tutor-app'));
}
</script>
