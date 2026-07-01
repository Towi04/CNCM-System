<?php
require_once __DIR__ . '/../config.php';
$gid = (int) ($_GET['grupo'] ?? 0);
$fecha = trim($_GET['fecha'] ?? '');
if ($gid <= 0 || $fecha === '') {
    echo '<p>Parámetros inválidos.</p>';
    return;
}

if (!plantel_grupo_pertenece($pdo, $gid)) {
    echo '<p>Grupo no pertenece a este plantel.</p>';
    return;
}

$stmt = $pdo->prepare('SELECT clave, aula FROM grupos WHERE id_grupo = ?');
$stmt->execute([$gid]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);
$resumen = asistencia_resumen_grupo($pdo, $gid, $fecha);
$alumnos = asistencia_lista_alumnos_grupo($pdo, $gid, $fecha);
?>

<div class="asist-toma-panel">
  <div class="asist-toma-header">
    <div>
      <h3 style="margin:0;"><?php echo htmlspecialchars($grupo['clave'] ?? ''); ?> — <?php echo date('d/m/Y', strtotime($fecha)); ?></h3>
      <span style="color:#666; font-size:0.88rem;">
        Vista rápida: <?php echo (int)$resumen['presentes']; ?>/<?php echo (int)$resumen['total_alumnos']; ?> presentes
        (<?php echo (int)$resumen['por_huella']; ?> por huella)
      </span>
    </div>
    <div class="asist-contador">
      <div class="asist-contador__item">
        <div class="asist-contador__num"><?php echo (int)$resumen['total_alumnos']; ?></div>
        <div class="asist-contador__label">Lista</div>
      </div>
      <div class="asist-contador__item asist-contador__item--ok">
        <div class="asist-contador__num" id="contador-marcados"><?php echo (int)$resumen['presentes']; ?></div>
        <div class="asist-contador__label">Marcados</div>
      </div>
    </div>
  </div>

  <form id="form-asist-recepcion">
    <input type="hidden" name="id_grupo" value="<?php echo $gid; ?>">
    <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">

    <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="primary" id="btn-marcar-todos">Marcar todos presentes</button>
      <button type="button" id="btn-desmarcar-faltantes">Solo desmarcar faltantes</button>
      <button type="submit" class="primary">Guardar lista recepción</button>
    </div>

    <?php if (empty($alumnos)): ?>
      <p>No hay alumnos activos en este grupo.</p>
    <?php else: ?>
      <?php foreach ($alumnos as $a):
        $idA = (int) $a['id_alumno'];
        $checked = (int) $a['presente'] === 1;
        $esHuella = !empty($a['bloqueado']);
        $rowClass = $esHuella ? 'is-huella' : ($checked ? '' : 'is-ausente');
      ?>
        <div class="asist-alumno-row <?php echo $rowClass; ?>" data-id="<?php echo $idA; ?>">
          <div class="asist-alumno-row__info">
            <div class="asist-alumno-row__nombre">
              <?php echo htmlspecialchars($a['nombre_completo']); ?>
              <?php if ($esHuella): ?>
                <span class="asist-badge-huella">
                  <i class="fas fa-<?php echo ($a['origen'] ?? '') === 'movil' ? 'mobile-alt' : 'fingerprint'; ?>"></i>
                  <?php echo ($a['origen'] ?? '') === 'movil' ? 'Móvil' : 'Huella'; ?>
                  <?php echo asistencia_format_hora($a['hora_llegada']); ?>
                </span>
              <?php elseif ($checked && ($a['origen'] ?? '') === 'recepcion'): ?>
                <span class="asist-badge-recep">Recepción</span>
              <?php endif; ?>
            </div>
            <div class="asist-alumno-row__extra">
              #<?php echo htmlspecialchars((string)($a['numero_control'] ?? $idA)); ?>
              <?php if (!empty($a['codigo_huella'])): ?> · PIN <?php echo htmlspecialchars($a['codigo_huella']); ?><?php endif; ?>
            </div>
          </div>
          <input
            type="checkbox"
            class="asist-check"
            name="presente[<?php echo $idA; ?>]"
            value="1"
            <?php echo $checked ? 'checked' : ''; ?>
            <?php echo $esHuella ? 'disabled' : ''; ?>
            data-huella="<?php echo $esHuella ? '1' : '0'; ?>"
          >
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </form>
</div>

<script>
(function () {
  const form = document.getElementById('form-asist-recepcion');
  const contador = document.getElementById('contador-marcados');

  function actualizarContador() {
    if (!contador) return;
    const n = form.querySelectorAll('.asist-check:checked').length;
    contador.textContent = n;
  }

  form?.querySelectorAll('.asist-check').forEach((chk) => {
    chk.addEventListener('change', actualizarContador);
  });

  document.getElementById('btn-marcar-todos')?.addEventListener('click', () => {
    form.querySelectorAll('.asist-check:not(:disabled)').forEach((c) => { c.checked = true; });
    actualizarContador();
  });

  document.getElementById('btn-desmarcar-faltantes')?.addEventListener('click', () => {
    form.querySelectorAll('.asist-check:not(:disabled)').forEach((c) => { c.checked = false; });
    actualizarContador();
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const res = await fetch('php/asistencia_recepcion_save.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'fetch' },
    });
    const data = await res.json();
    alert(data.message || (data.status === 'ok' ? 'Guardado' : 'Error'));
    if (data.status === 'ok' && data.seccion) {
      const extra = data.params ? Object.fromEntries(new URLSearchParams(data.params)) : {};
      const p = new URLSearchParams();
      p.set('fecha', form.querySelector('[name=fecha]').value);
      if (extra.grupo) p.set('grupo', extra.grupo);
      cargarSeccion(data.seccion, p);
    }
  });
})();
</script>
