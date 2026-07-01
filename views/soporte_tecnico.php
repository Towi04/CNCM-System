<?php
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="catalog-alert catalog-alert--error">Inicie sesión para usar soporte.</div>';
    return;
}

$esAlumno = function_exists('alumno_portal_es_alumno') && alumno_portal_es_alumno();
$puedeAlumno = $esAlumno && alumno_portal_puede_ver();
$plantel = htmlspecialchars($_SESSION['plantel_nombre'] ?? 'CNCM');
$misReportes = soporte_listar_recientes($pdo, (int) $_SESSION['user_id'], 8);
$apiUrl = hay_asset_url('php/soporte_api.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($puedeAlumno): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>

<div class="catalog-wrap soporte-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-life-ring"></i> Soporte<?php echo $puedeAlumno ? '' : ' técnico'; ?></h2>
    <p style="color:#666; margin:0;">Reporte errores del sistema o envíe sugerencias de mejora. Los supervisores recibirán su mensaje.</p>
  </div>

  <?php if ($puedeAlumno): ?>
    <p style="color:#666;">¿Problemas con el portal, Moodle o tus pagos? También puedes usar estas opciones rápidas.</p>
    <div class="ap-portal-grid" style="margin:16px 0;">
      <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_chat')">
        <i class="fas fa-comments" style="color:#6a1b9a;"></i>
        <strong style="display:block;margin-top:6px;">Escribir a recepción</strong>
        <small style="color:#666;">Chat en tiempo real</small>
      </button>
      <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_estado_cuenta','id=<?php echo (int) alumno_portal_id_sesion(); ?>')">
        <i class="fas fa-file-invoice-dollar" style="color:#2e7d32;"></i>
        <strong style="display:block;margin-top:6px;">Dudas de pagos</strong>
        <small style="color:#666;">Estado de cuenta</small>
      </button>
    </div>
  <?php endif; ?>

  <form id="form-soporte" class="catalog-toolbar" style="flex-direction:column; align-items:stretch; gap:12px; max-width:720px;">
    <div class="field">
      <label>Tipo de mensaje</label>
      <select name="tipo" id="soporte-tipo">
        <option value="error">Error en el sistema</option>
        <option value="sugerencia">Sugerencia de mejora</option>
      </select>
    </div>
    <div class="field">
      <label>Describe el problema o sugerencia</label>
      <textarea name="mensaje" id="soporte-mensaje" rows="6" required maxlength="4000"
        placeholder="Indique qué estaba haciendo, qué esperaba y qué ocurrió…"></textarea>
    </div>
    <div class="field">
      <label>Capturas de pantalla (opcional, hasta <?php echo (int) SOPORTE_MAX_ADJUNTOS; ?> imágenes)</label>
      <input type="file" name="adjuntos[]" id="soporte-adjuntos" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
      <p style="font-size:0.82rem; color:#777; margin:6px 0 0;">JPG, PNG, WebP o GIF · máx. 5 MB c/u</p>
      <div id="soporte-preview" class="soporte-preview"></div>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <button type="submit" class="primary" id="btn-soporte-enviar"><i class="fas fa-paper-plane"></i> Enviar reporte</button>
    </div>
    <p id="soporte-msg" style="margin:0; font-size:0.9rem;"></p>
  </form>

  <?php if ($misReportes !== []): ?>
  <section style="margin-top:24px; max-width:720px;">
    <h3 style="font-size:1rem; margin:0 0 10px;"><i class="fas fa-history"></i> Tus reportes recientes</h3>
    <ul class="soporte-historial">
      <?php foreach ($misReportes as $rep):
        $tipoLbl = ($rep['tipo'] ?? '') === 'sugerencia' ? 'Sugerencia' : 'Error';
        $fecha = date('d/m/Y H:i', strtotime($rep['creado_en']));
        $adj = [];
        if (!empty($rep['adjuntos_json'])) {
            $adj = json_decode($rep['adjuntos_json'], true) ?: [];
        }
      ?>
      <li>
        <strong><?php echo htmlspecialchars($tipoLbl); ?></strong>
        <span style="color:#888; font-size:0.82rem;"> · <?php echo htmlspecialchars($fecha); ?></span>
        <p style="margin:4px 0 0; color:#444;"><?php echo nl2br(htmlspecialchars(mb_strimwidth($rep['mensaje'], 0, 300, '…'))); ?></p>
        <?php if ($adj !== []): ?>
          <div class="soporte-historial__imgs">
            <?php foreach ($adj as $path): ?>
              <a href="<?php echo htmlspecialchars(hay_asset_url($path)); ?>" target="_blank" rel="noopener">
                <img src="<?php echo htmlspecialchars(hay_asset_url($path)); ?>" alt="Adjunto">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($puedeAlumno): ?>
    <button type="button" class="secondary" style="margin-top:16px;" onclick="cargarSeccion('alumno_portal_inicio')">← Volver al inicio</button>
  <?php endif; ?>
</div>

<style>
.soporte-preview { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
.soporte-preview img { width:72px; height:72px; object-fit:cover; border-radius:6px; border:1px solid #ddd; }
.soporte-historial { list-style:none; margin:0; padding:0; }
.soporte-historial li { border:1px solid #e8e8e8; border-radius:8px; padding:10px 12px; margin-bottom:8px; background:#fafafa; }
.soporte-historial__imgs { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.soporte-historial__imgs img { width:56px; height:56px; object-fit:cover; border-radius:4px; border:1px solid #ddd; }
</style>

<script>
(function() {
  const form = document.getElementById('form-soporte');
  const msg = document.getElementById('soporte-msg');
  const preview = document.getElementById('soporte-preview');
  const api = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;

  document.getElementById('soporte-adjuntos')?.addEventListener('change', (ev) => {
    if (!preview) return;
    preview.innerHTML = '';
    [...(ev.target.files || [])].slice(0, <?php echo (int) SOPORTE_MAX_ADJUNTOS; ?>).forEach((file) => {
      if (!file.type.startsWith('image/')) return;
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      preview.appendChild(img);
    });
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn-soporte-enviar');
    const texto = document.getElementById('soporte-mensaje')?.value?.trim() || '';
    if (!texto) {
      if (msg) { msg.style.color = '#c62828'; msg.textContent = 'Escriba el mensaje.'; }
      return;
    }
    if (btn) btn.disabled = true;
    if (msg) { msg.style.color = '#666'; msg.textContent = 'Enviando…'; }
    try {
      const fd = new FormData(form);
      fd.append('action', 'enviar');
      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (msg) {
        msg.style.color = d.status === 'ok' ? '#2e7d32' : '#c62828';
        msg.textContent = d.message || (d.status === 'ok' ? 'Enviado' : 'Error');
      }
      if (d.status === 'ok') {
        form.reset();
        if (preview) preview.innerHTML = '';
        setTimeout(() => cargarSeccion('soporte_tecnico'), 1200);
      }
    } catch (err) {
      if (msg) { msg.style.color = '#c62828'; msg.textContent = 'No se pudo enviar. Intente de nuevo.'; }
    } finally {
      if (btn) btn.disabled = false;
    }
  });
})();
</script>
