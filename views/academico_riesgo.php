<?php
require_once __DIR__ . '/../config.php';
if (!grupo_avance_puede_gestionar()) {
    echo '<div class="alert">Solo coordinación puede ver alertas de riesgo académico.</div>';
    return;
}

$lista = grupo_avance_listar_riesgo_plantel($pdo);
$labelsAcepto = ['' => '—', '1' => 'Aceptó cambio de grupo', '0' => 'Rechazó / no cambió'];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2>Riesgo académico</h2>
    <p style="color:#666; margin:0;">Alumnos que avanzaron de parcial sin calificación aprobatoria (≥ 6). Registre el seguimiento de coordinación.</p>
  </div>

  <div id="respuesta-riesgo" class="catalog-alert" style="display:none;"></div>

  <?php if ($lista === []): ?>
    <p class="catalog-empty">No hay alumnos en riesgo pendientes de seguimiento.</p>
  <?php else: ?>
    <div class="catalog-table-wrap">
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Grupo</th>
            <th>Alumno</th>
            <th>Parcial actual</th>
            <th>Última cal.</th>
            <th>Seguimiento</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lista as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['grupo_clave'] ?? ''); ?></td>
              <td>
                <a href="#" onclick="cargarSeccion('alumnos'); return false;"><?php echo htmlspecialchars($r['nombre_completo'] ?? ''); ?></a>
                <br><small style="color:#888;">#<?php echo htmlspecialchars($r['numero_control'] ?? ''); ?></small>
              </td>
              <td><?php echo htmlspecialchars($r['clave_fase'] ?? $r['nombre_fase'] ?? '—'); ?></td>
              <td>
                <?php if ($r['promedio'] !== null): ?>
                  <?php echo htmlspecialchars((string) $r['promedio']); ?>
                  <?php echo (int)($r['aprobado'] ?? 0) ? '✓' : '✗'; ?>
                <?php else: ?>
                  <span style="color:#c62828;">Sin captura</span>
                <?php endif; ?>
              </td>
              <td>
                <form class="form-resolver-riesgo" data-id-alumno="<?php echo (int)$r['id_alumno']; ?>" data-id-grupo="<?php echo (int)$r['id_grupo']; ?>">
                  <textarea name="nota" rows="2" placeholder="Orientación, cambio de grupo, etc." required style="width:100%; margin-bottom:6px;"></textarea>
                  <select name="alumno_acepto_cambio" style="width:100%; margin-bottom:6px;">
                    <?php foreach ($labelsAcepto as $k => $v): ?>
                      <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="primary" style="width:100%;">Marcar atendido</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const msg = document.getElementById('respuesta-riesgo');
  document.querySelectorAll('.form-resolver-riesgo').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData();
      fd.append('action', 'resolver_riesgo');
      fd.append('id_alumno', form.dataset.idAlumno);
      fd.append('id_grupo', form.dataset.idGrupo);
      fd.append('nota', form.querySelector('[name=nota]').value);
      fd.append('alumno_acepto_cambio', form.querySelector('[name=alumno_acepto_cambio]').value);
      try {
        const { data } = await hayFetchJson('php/grupo_avance_api.php', { method: 'POST', body: fd });
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
