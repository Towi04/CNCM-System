<?php
require_once __DIR__ . '/../config.php';
/** @var PDO $pdo */
grupo_apertura_sync_estados($pdo);
$idPlantel = plantel_scope_id($pdo);
$rolEfectivo = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
$idProfesorFiltro = ($rolEfectivo === 'profesor') ? (int) ($_SESSION['user_id'] ?? 0) : 0;

$sql = "
  SELECT
    g.id_grupo,
    g.clave,
    g.fecha_inicio,
    g.codigo_horario,
    g.id_fase_actual,
    g.fusiones_total,
    g.fusion_desfase,
    g.id_especialidad,
    g.estado_apertura,
    g.pospuestos,
    (SELECT COUNT(*) FROM alumno_grupos ag
     INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
     WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos,
    (
      SELECT COUNT(*) FROM grupo_plan_periodo gp
      WHERE gp.id_grupo = g.id_grupo AND gp.pendiente_retomar = 1
    ) AS plan_pendientes,
    (
      SELECT COUNT(DISTINCT asi.id_alumno)
      FROM asistencias asi
      WHERE asi.id_grupo = g.id_grupo
        AND asi.presente = 1
        AND asi.fecha >= (CURRENT_DATE - INTERVAL 30 DAY)
    ) AS activos_30d
  FROM grupos g
  WHERE g.id_plantel = ?
";
$params = [$idPlantel];
if ($idProfesorFiltro > 0) {
    $sql .= ' AND ' . grupo_docente_sql_filtro_profesor('g');
    grupo_docente_bind_filtro_profesor($idProfesorFiltro, $params);
}
$sql .= ' ORDER BY g.fecha_inicio DESC, g.clave ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$puedePlan = function_exists('grupo_plan_puede_editar') && grupo_plan_puede_editar();
$puedeCal = true;
$puedeAvance = function_exists('grupo_avance_puede_gestionar') && grupo_avance_puede_gestionar();
$puedeGraduacion = function_exists('graduacion_puede_decidir') && graduacion_puede_decidir();
$puedeApertura = function_exists('grupo_apertura_puede_gestionar') && grupo_apertura_puede_gestionar();
$puedeMoodle = function_exists('moodle_inscripcion_puede_gestionar') && moodle_inscripcion_puede_gestionar();
$puedeDocentes = function_exists('grupo_docente_puede_gestionar') && grupo_docente_puede_gestionar();

// Avance/graduación automática: proceso pesado; se ejecuta vía API/cron, no en cada carga de la vista.

$faseCache = [];
$riesgoTotal = $puedeAvance ? count(grupo_avance_listar_riesgo_plantel($pdo, $idPlantel)) : 0;
$gradPendientes = $puedeGraduacion ? count(graduacion_listar_alertas($pdo, $idPlantel, 'pendiente')) : 0;
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">

<div class="result-container">
  <div class="result-header">
    <h2>Grupos — <?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? ''); ?></h2>
    <div class="disc-actions">
      <button class="primary" type="button" onclick="cargarSeccion('grupo_nuevo')">Nuevo grupo</button>
      <?php if ($puedeApertura): ?>
        <button type="button" class="secondary" onclick="cargarSeccion('grupo_apertura')">Apertura de grupos</button>
      <?php endif; ?>
      <button type="button" onclick="cargarSeccion('alumnos')">Listas de alumnos</button>
      <button type="button" onclick="cargarSeccion('asistencia')">Tomar asistencia</button>
      <button type="button" onclick="cargarSeccion('planeaciones')">Planeaciones</button>
      <?php if ($puedeAvance): ?>
        <button type="button" class="secondary" id="btn-avance-auto-grupos" title="Revisar avance por 4 sesiones lectivas">Sincronizar avance</button>
        <?php if ($riesgoTotal > 0): ?>
          <button type="button" class="warning" onclick="cargarSeccion('academico_riesgo')">
            Riesgo académico (<?php echo $riesgoTotal; ?>)
          </button>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($puedeGraduacion): ?>
        <button type="button" class="info" onclick="cargarSeccion('graduacion_alertas')">
          Graduación (<?php echo (int) $gradPendientes; ?>)
        </button>
      <?php endif; ?>
      <?php if (profesor_portal_es_profesor()): ?>
        <button type="button" onclick="cargarSeccion('profesor_portal')">Mi portal docente</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="patron-desc">
    <?php if (empty($grupos)): ?>
      <p>No hay grupos registrados.</p>
    <?php else: ?>
      <div class="hist-lines">
        <?php foreach ($grupos as $g):
          $pos = academico_posicion_grupo($pdo, $g);
          $idFase = (int) ($g['id_fase_actual'] ?? 0);
          $faseLbl = '';
          if ($idFase > 0) {
              if (!isset($faseCache[$idFase])) {
                  $fs = $pdo->prepare('SELECT clave_fase, nombre_fase FROM especialidad_fases WHERE id_fase = ?');
                  $fs->execute([$idFase]);
                  $faseCache[$idFase] = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
              }
              $f = $faseCache[$idFase];
              $faseLbl = $f['clave_fase'] ?? $f['nombre_fase'] ?? '';
          }
          $listoAvance = $puedeAvance && grupo_avance_debe_avanzar_por_tiempo($pdo, $g);
        ?>
          <div class="hist-line" style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px;">
            <div>
              <?php echo grupo_clave_html($g); ?>
              <span style="color:#666;"> · Inicio <?php echo htmlspecialchars((string)$g['fecha_inicio']); ?></span>
              <?php
              $estApert = (string) ($g['estado_apertura'] ?? 'programado');
              if ($estApert !== 'iniciado' && function_exists('grupo_apertura_etiqueta_estado')):
                $colorEst = match ($estApert) {
                    'autorizado' => '#2e7d32',
                    'pendiente_autorizacion' => '#e65100',
                    default => '#888',
                };
              ?>
                <span style="color:<?php echo $colorEst; ?>; font-size:0.85rem;">
                  · <?php echo htmlspecialchars(grupo_apertura_etiqueta_estado($estApert)); ?>
                </span>
              <?php endif; ?>
              <?php if ((int)($g['pospuestos'] ?? 0) > 0): ?>
                <span style="color:#e65100; font-size:0.8rem;"> · Posp. <?php echo (int)$g['pospuestos']; ?>×</span>
              <?php endif; ?>
              <span style="color:#888;"> · Alumnos <?php echo (int)$g['total_alumnos']; ?></span>
              <?php if ($faseLbl !== ''): ?>
                <span style="color:#2e7d32; font-size:0.85rem;"> · Parcial <strong><?php echo htmlspecialchars($faseLbl); ?></strong></span>
              <?php endif; ?>
              <span style="color:#5c6bc0; font-size:0.85rem;">
                · Sesión <?php echo (int)$pos['semanas_lectivas']; ?> · Sem <?php echo (int)$pos['semana_parcial']; ?>/4
              </span>
              <?php if ($listoAvance): ?>
                <span class="grupo-plan-tag" style="background:#fff3e0; color:#e65100;" title="Listo para avanzar al siguiente parcial">Avance pendiente</span>
              <?php endif; ?>
              <?php if ((int)$g['plan_pendientes'] > 0): ?>
                <span class="grupo-plan-tag grupo-plan-tag--retomar" title="Temas pendientes de retomar">Retomar <?php echo (int)$g['plan_pendientes']; ?></span>
              <?php endif; ?>
            </div>
            <div class="fase-acciones">
              <?php if ($puedeDocentes): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit" title="Docentes del grupo"
                  onclick="cargarSeccion('grupo_docentes', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                  <i class="fas fa-chalkboard-teacher"></i>
                </button>
              <?php endif; ?>
              <?php if ($puedeCal && calificaciones_puede_capturar_grupo($pdo, (int) $g['id_grupo'])): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit" title="Calificaciones del parcial"
                  onclick="cargarSeccion('grupo_calificaciones', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                  <i class="fas fa-clipboard-list"></i>
                </button>
              <?php endif; ?>
              <?php if ($puedePlan): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit" title="Plan de parciales"
                  onclick="cargarSeccion('grupo_plan', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                  <i class="fas fa-calendar-check"></i>
                </button>
              <?php endif; ?>
              <button type="button" class="btn-icon-only btn-icon-only--muted btn-lista-asistencia-pdf"
                title="Imprimir lista de asistencia"
                data-id="<?php echo (int)$g['id_grupo']; ?>"
                data-clave="<?php echo htmlspecialchars((string)$g['clave'], ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-print"></i>
              </button>
              <?php if ($puedeAvance): ?>
                <button type="button" class="btn-icon-only btn-icon-only--ok btn-avanzar-grupo" title="Avanzar al siguiente parcial"
                  data-id="<?php echo (int)$g['id_grupo']; ?>">
                  <i class="fas fa-forward"></i>
                </button>
              <?php endif; ?>
              <button type="button" class="btn-icon-only btn-icon-only--muted" title="Ver alumnos"
                onclick="cargarSeccion('alumnos'); window.__grupoSeleccionado=<?php echo (int)$g['id_grupo']; ?>;">
                <i class="fas fa-users"></i>
              </button>
              <?php if ($puedeMoodle): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit btn-moodle-grupo" title="Inscribir grupo en Moodle"
                  data-id="<?php echo (int)$g['id_grupo']; ?>"
                  data-clave="<?php echo htmlspecialchars((string)$g['clave'], ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fas fa-chalkboard"></i>
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($puedeAvance): ?>
<script>
(function () {
  document.getElementById('btn-avance-auto-grupos')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'procesar_plantel');
    try {
      const { data } = await hayFetchJson('php/grupo_avance_api.php', { method: 'POST', body: fd });
      alert(data.message || 'Listo');
      if (data.seccion) cargarSeccion(data.seccion);
    } catch (e) { alert(e.message); }
  });
  document.querySelectorAll('.btn-avanzar-grupo').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('¿Avanzar este grupo al siguiente parcial? Se evaluará riesgo académico de alumnos sin ≥ 6.')) return;
      const fd = new FormData();
      fd.append('action', 'avanzar_grupo');
      fd.append('id_grupo', btn.dataset.id);
      try {
        const { data } = await hayFetchJson('php/grupo_avance_api.php', { method: 'POST', body: fd });
        alert(data.message || '');
        if (data.seccion) cargarSeccion(data.seccion);
      } catch (e) { alert(e.message); }
    });
  });
})();
</script>
<?php endif; ?>

<script>
(function () {
  document.querySelectorAll('.btn-lista-asistencia-pdf').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idGrupo = btn.dataset.id;
      if (!idGrupo) return;
      const clave = btn.dataset.clave || 'grupo';
      const incluirTelefonos = window.confirm('¿Incluir números de teléfono de los alumnos en la lista de asistencia?');
      const semanaActual = <?php echo (int) date('W'); ?>;
      const respuesta = window.prompt('¿A partir de qué número de semana debe iniciar la lista?', String(semanaActual));
      if (respuesta === null) return;
      const semana = parseInt(respuesta, 10);
      if (!Number.isInteger(semana) || semana < 1 || semana > 53) {
        alert('Indique una semana válida entre 1 y 53.');
        return;
      }
      const url = new URL('<?php echo htmlspecialchars(hay_asset_url('php/asistencia_lista_pdf.php'), ENT_QUOTES, 'UTF-8'); ?>', window.location.href);
      url.searchParams.set('id_grupo', idGrupo);
      url.searchParams.set('semana_inicio', String(semana));
      url.searchParams.set('telefonos', incluirTelefonos ? '1' : '0');
      url.searchParams.set('filename', 'lista_' + clave);
      window.open(url.toString(), '_blank', 'noopener');
    });
  });
})();
</script>

<?php if ($puedeMoodle): ?>
<script>
(function () {
  const moodleApi = 'php/moodle_inscripcion_api.php';
  document.querySelectorAll('.btn-moodle-grupo').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const idGrupo = btn.dataset.id;
      const clave = btn.dataset.clave || 'grupo';
      let cursos = [];
      try {
        const { data } = await hayFetchJson(moodleApi + '?action=cursos_grupo&id_grupo=' + encodeURIComponent(idGrupo));
        cursos = data.cursos || [];
        if (!cursos.length) {
          alert('No hay cursos Moodle disponibles para este grupo.');
          return;
        }
      } catch (e) {
        alert(e.message);
        return;
      }
      const opts = cursos.map((c, i) => (i + 1) + ') ' + (c.fullname || c.shortname || ('#' + c.id))).join('\n');
      const num = prompt('Grupo ' + clave + ' — elija curso Moodle:\n' + opts + '\n\nNúmero:', '1');
      if (!num) return;
      const curso = cursos[parseInt(num, 10) - 1];
      if (!curso) { alert('Opción inválida'); return; }
      if (!confirm('¿Inscribir a todos los alumnos activos de «' + clave + '» en «' + (curso.fullname || curso.shortname) + '»?')) return;
      const fd = new FormData();
      fd.append('action', 'inscribir_grupo');
      fd.append('id_grupo', idGrupo);
      fd.append('course_id', curso.id);
      try {
        const { data } = await hayFetchJson(moodleApi, { method: 'POST', body: fd });
        let msg = data.message || 'Listo';
        if (data.detalle && data.detalle.length) {
          msg += '\n\n' + data.detalle.slice(0, 8).join('\n');
          if (data.detalle.length > 8) msg += '\n…';
        }
        alert(msg);
      } catch (e) { alert(e.message); }
    });
  });
})();
</script>
<?php endif; ?>
