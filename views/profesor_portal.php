<?php
require_once __DIR__ . '/../config.php';
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);
$esProfesor = profesor_portal_es_profesor();
$puedeVer = $esProfesor || in_array(rbac_rol_efectivo(), ['supervisor', 'gerente', 'admin'], true);

if (!$puedeVer) {
    echo '<div class="alert">Portal disponible para profesores.</div>';
    return;
}

$idProf = $esProfesor ? $idUsuario : (int) ($_GET['id_profesor'] ?? $idUsuario);
$grupos = profesor_portal_grupos($pdo, $idProf);
$permisos = $esProfesor ? profesor_portal_mis_permisos($pdo, $idUsuario) : [];
$dias = ['0' => 'Dom', '1' => 'Lun', '2' => 'Mar', '3' => 'Mié', '4' => 'Jue', '5' => 'Vie', '6' => 'Sáb'];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">

<div class="catalog-wrap">
  <h2>Mi portal docente</h2>
  <p style="color:#666;">Horario de grupos, aulas asignadas, accesos rápidos y solicitud de permisos.</p>
  <?php if (function_exists('academico_alumno_portal_puede') && academico_alumno_portal_puede()): ?>
  <p><button type="button" class="secondary" onclick="cargarSeccion('academico_portal_alumno')"><i class="fas fa-bullhorn"></i> Publicar avisos / responder mensajes</button></p>
  <?php endif; ?>

  <div id="respuesta-portal" class="catalog-alert" style="display:none;"></div>

  <section style="margin:24px 0;">
    <h3>Mis grupos</h3>
    <?php if ($grupos === []): ?>
      <p>No tiene grupos asignados en este plantel.</p>
    <?php else: ?>
      <div class="catalog-table-wrap">
        <table class="catalog-table">
          <thead>
            <tr>
              <th>Clave</th>
              <th>Especialidad</th>
              <th>Aula</th>
              <th>Parcial</th>
              <th>Horario</th>
              <th>Sesión</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($grupos as $g):
              $pos = $g['posicion'] ?? [];
              $h = strtoupper($g['codigo_horario'] ?? 'S');
              $dia = grupo_dia_clase_semana($h);
              $horarioTxt = ($g['horario_texto'] ?? '') !== ''
                ? $g['horario_texto']
                : ($dia >= 0 ? ($dias[(string)$dia] ?? '') . ' · código ' . $h : 'Entre semana · ' . $h);
            ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($g['clave'] ?? ''); ?></strong></td>
                <td><?php echo htmlspecialchars($g['esp_nombre'] ?? ''); ?></td>
                <td>
                  <?php if (!empty($g['aula_label'])): ?>
                    <strong><?php echo htmlspecialchars($g['aula_codigo'] ?? $g['aula'] ?? ''); ?></strong>
                    <?php if (!empty($g['aula_nombre']) && ($g['aula_nombre'] ?? '') !== ($g['aula_codigo'] ?? '')): ?>
                      <br><span style="color:#666;font-size:0.85rem;"><?php echo htmlspecialchars($g['aula_nombre']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['aula_piso'])): ?>
                      <br><span style="color:#888;font-size:0.8rem;"><?php echo htmlspecialchars($g['aula_piso']); ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="color:#888;">Por asignar</span>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($g['clave_fase'] ?? $g['nombre_fase'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($horarioTxt); ?></td>
                <td>S<?php echo (int)($pos['semanas_lectivas'] ?? 0); ?> · sem <?php echo (int)($pos['semana_parcial'] ?? 1); ?>/4</td>
                <td class="fase-acciones">
                  <button type="button" class="btn-icon-only btn-icon-only--edit" title="Ponderación del parcial"
                    onclick="cargarSeccion('grupo_ponderacion', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                    <i class="fas fa-balance-scale"></i>
                  </button>
                  <button type="button" class="btn-icon-only btn-icon-only--edit" title="Calificaciones"
                    onclick="cargarSeccion('grupo_calificaciones', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                    <i class="fas fa-clipboard-list"></i>
                  </button>
                  <button type="button" class="btn-icon-only btn-icon-only--muted" title="Asistencia"
                    onclick="cargarSeccion('asistencia'); window.__grupoSeleccionado=<?php echo (int)$g['id_grupo']; ?>;">
                    <i class="fas fa-clipboard-check"></i>
                  </button>
                  <button type="button" class="btn-icon-only btn-icon-only--muted" title="Planeación"
                    onclick="cargarSeccion('planeaciones')">
                    <i class="fas fa-book"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php
  $evalCerrada = $esProfesor ? profesor_eval_ultima_cerrada($pdo, $idUsuario, plantel_scope_id($pdo)) : null;
  $mesesEval = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
  ?>
  <?php if ($esProfesor && $evalCerrada): ?>
  <section class="pe-portal-readonly" style="margin:24px 0;">
    <h3>Mi evaluación 360</h3>
    <p style="color:#666;">
      Periodo <?php echo htmlspecialchars($mesesEval[(int)$evalCerrada['mes']] ?? ''); ?>
      <?php echo (int) $evalCerrada['anio']; ?> — solo lectura
    </p>
    <div class="pe-total" style="margin-bottom:12px;">
      Puntaje: <strong><?php echo (int) $evalCerrada['puntos_total']; ?></strong>
      / <?php echo (int) profesor_eval_max_posible(); ?>
      — <?php echo htmlspecialchars($evalCerrada['nivel'] ?? ''); ?>
    </div>
    <details open>
      <summary style="cursor:pointer; font-weight:600;">Métricas automáticas</summary>
      <ul class="pe-readonly-list">
        <?php foreach (profesor_eval_criterios_auto() as $c):
          $cod = $c['codigo'];
          $m = $evalCerrada['metricas_auto'][$cod] ?? [];
          $pts = (int) ($m['puntos'] ?? $m['puntos_sugeridos'] ?? 0);
        ?>
        <li><?php echo htmlspecialchars($c['nombre']); ?>: <?php echo (float) ($m['valor_pct'] ?? 0); ?>% — <strong><?php echo $pts; ?></strong> pts</li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php foreach (profesor_eval_rubrica_categorias() as $cat): ?>
    <details>
      <summary style="cursor:pointer; font-weight:600;"><?php echo htmlspecialchars($cat['titulo']); ?></summary>
      <ul class="pe-readonly-list">
        <?php foreach ($cat['items'] as $c):
          $pts = (int) ($evalCerrada['criterios_manual'][$c['codigo']] ?? 0);
        ?>
        <li><?php echo htmlspecialchars($c['nombre']); ?>: <strong><?php echo $pts; ?></strong> / <?php echo (int) $c['maximo']; ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endforeach; ?>
    <?php if (!empty($evalCerrada['observaciones'])): ?>
      <p style="margin-top:12px;"><strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($evalCerrada['observaciones'])); ?></p>
    <?php endif; ?>
  </section>
  <?php elseif ($esProfesor): ?>
  <section style="margin:16px 0; color:#666;">
    <p><em>Aún no hay una evaluación 360 cerrada para usted en este plantel.</em></p>
  </section>
  <?php endif; ?>

  <?php if ($esProfesor): ?>
  <section style="margin:24px 0;">
    <h3>Documentos y planeación</h3>
    <ul>
      <li><a href="#" onclick="cargarSeccion('planeaciones'); return false;">Planeaciones de clase</a></li>
      <li><a href="#" onclick="cargarSeccion('calendario_consulta'); return false;">Calendario institucional</a></li>
    </ul>
  </section>

  <section style="margin:24px 0; max-width:520px;">
    <h3>Solicitar permiso / falta</h3>
    <form id="form-permiso-prof" data-no-global-ajax="1">
      <div style="margin-bottom:10px;">
        <label>Desde</label>
        <input type="date" name="fecha_inicio" required style="width:100%; padding:8px;">
      </div>
      <div style="margin-bottom:10px;">
        <label>Hasta</label>
        <input type="date" name="fecha_fin" required style="width:100%; padding:8px;">
      </div>
      <div style="margin-bottom:10px;">
        <label>Motivo</label>
        <textarea name="motivo" rows="3" required style="width:100%; padding:8px;" placeholder="Motivo del permiso"></textarea>
      </div>
      <button type="submit" class="primary">Enviar solicitud</button>
    </form>

    <?php if ($permisos !== []): ?>
      <h4 style="margin-top:20px;">Mis solicitudes</h4>
      <ul>
        <?php foreach ($permisos as $p): ?>
          <li>
            <?php echo date('d/m/Y', strtotime($p['fecha_inicio'])); ?>
            – <?php echo date('d/m/Y', strtotime($p['fecha_fin'])); ?>:
            <strong><?php echo htmlspecialchars($p['estado']); ?></strong>
            <?php if (!empty($p['comentario_revision'])): ?>
              — <?php echo htmlspecialchars($p['comentario_revision']); ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>

<?php if ($esProfesor): ?>
<script>
(function () {
  const form = document.getElementById('form-permiso-prof');
  const msg = document.getElementById('respuesta-portal');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action', 'solicitar_permiso');
    try {
      const { data } = await hayFetchJson('php/profesor_portal_api.php', { method: 'POST', body: fd });
      if (msg) {
        msg.style.display = 'block';
        msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
        msg.textContent = data.message || '';
      }
      if (data.status === 'ok') {
        form.reset();
        if (data.seccion) cargarSeccion(data.seccion);
      }
    } catch (err) {
      if (msg) {
        msg.style.display = 'block';
        msg.className = 'catalog-alert catalog-alert--error';
        msg.textContent = err.message;
      }
    }
  });
})();
</script>
<?php endif; ?>
