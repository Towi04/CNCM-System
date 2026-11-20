<?php
require_once __DIR__ . '/../config.php';
/** @var PDO $pdo */
grupo_apertura_sync_estados($pdo);
$idPlantel = plantel_scope_id($pdo);
$rolEfectivo = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
$idProfesorFiltro = ($rolEfectivo === 'profesor') ? (int) ($_SESSION['user_id'] ?? 0) : 0;

$q = trim((string) ($_GET['q'] ?? ''));
$filtroEstado = trim((string) ($_GET['estado'] ?? ''));
$estadosOk = ['programado', 'pendiente_autorizacion', 'autorizado', 'iniciado'];
if ($filtroEstado !== '' && !in_array($filtroEstado, $estadosOk, true)) {
    $filtroEstado = '';
}
$perPageOpts = [25, 50, 100];
$perPage = (int) ($_GET['per_page'] ?? 25);
if (!in_array($perPage, $perPageOpts, true)) {
    $perPage = 25;
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ' WHERE g.id_plantel = ?';
$params = [$idPlantel];
if ($idProfesorFiltro > 0) {
    $where .= ' AND ' . grupo_docente_sql_filtro_profesor('g');
    grupo_docente_bind_filtro_profesor($idProfesorFiltro, $params);
}
if ($q !== '') {
    $where .= ' AND (g.clave LIKE ? OR IFNULL(g.clave_anterior,\'\') LIKE ? OR CAST(g.id_grupo AS CHAR) = ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $q;
}
if ($filtroEstado !== '') {
    $where .= ' AND g.estado_apertura = ?';
    $params[] = $filtroEstado;
}

$sqlCount = 'SELECT COUNT(*) FROM grupos g' . $where;
$stCount = $pdo->prepare($sqlCount);
$stCount->execute($params);
$totalGrupos = (int) $stCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalGrupos / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

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
  {$where}
  ORDER BY g.fecha_inicio DESC, g.clave ASC
  LIMIT {$perPage} OFFSET {$offset}
";
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
$puedeCredenciales = function_exists('credencial_puede_generar') && credencial_puede_generar();

// Avance/graduación automática: proceso pesado; se ejecuta vía API/cron, no en cada carga de la vista.

$faseCache = [];
$riesgoTotal = $puedeAvance ? count(grupo_avance_listar_riesgo_plantel($pdo, $idPlantel)) : 0;
$gradPendientes = $puedeGraduacion ? count(graduacion_listar_alertas($pdo, $idPlantel, 'pendiente')) : 0;

$qsBase = [];
if ($q !== '') {
    $qsBase['q'] = $q;
}
if ($filtroEstado !== '') {
    $qsBase['estado'] = $filtroEstado;
}
if ($perPage !== 25) {
    $qsBase['per_page'] = (string) $perPage;
}
$buildGruposQs = static function (array $extra) use ($qsBase): string {
    $all = array_merge($qsBase, $extra);
    return http_build_query($all);
};
$desde = $totalGrupos === 0 ? 0 : ($offset + 1);
$hasta = min($offset + $perPage, $totalGrupos);
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css?v=20260721">

<div class="result-container">
  <div class="result-header">
    <h2>Grupos — <?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? ''); ?></h2>
    <div class="disc-actions">
      <button class="primary" type="button" onclick="cargarSeccion('grupo_nuevo')">Nuevo grupo</button>
      <?php if (function_exists('grupo_division_puede') && grupo_division_puede()): ?>
        <button type="button" class="secondary" onclick="cargarSeccion('grupo_division')" title="Dividir grupos grandes por edad">
          Dividir grupo
        </button>
      <?php endif; ?>
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

  <form class="grupos-toolbar" id="grupos-filtros" onsubmit="return false;" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:12px 0 8px;">
    <label style="display:flex; gap:6px; align-items:center; margin:0;">
      <span style="color:#555; font-size:0.9rem;">Buscar</span>
      <input type="search" name="q" id="grupos-q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
        placeholder="Clave, clave anterior o id"
        style="min-width:180px; padding:6px 10px; border:1px solid #ccc; border-radius:8px;">
    </label>
    <label style="display:flex; gap:6px; align-items:center; margin:0;">
      <span style="color:#555; font-size:0.9rem;">Estado</span>
      <select id="grupos-estado" style="padding:6px 8px; border:1px solid #ccc; border-radius:8px;">
        <option value="">Todos</option>
        <?php foreach ($estadosOk as $est): ?>
          <option value="<?php echo htmlspecialchars($est); ?>" <?php echo $filtroEstado === $est ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars(function_exists('grupo_apertura_etiqueta_estado') ? grupo_apertura_etiqueta_estado($est) : $est); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="display:flex; gap:6px; align-items:center; margin:0;">
      <span style="color:#555; font-size:0.9rem;">Por página</span>
      <select id="grupos-per-page" style="padding:6px 8px; border:1px solid #ccc; border-radius:8px;">
        <?php foreach ($perPageOpts as $pp): ?>
          <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="button" class="secondary" id="grupos-btn-filtrar">Filtrar</button>
    <?php if ($q !== '' || $filtroEstado !== ''): ?>
      <button type="button" class="secondary" id="grupos-btn-limpiar">Limpiar</button>
    <?php endif; ?>
    <span style="color:#666; font-size:0.9rem; margin-left:auto;">
      <?php if ($totalGrupos === 0): ?>
        0 grupos
      <?php else: ?>
        <?php echo (int) $desde; ?>–<?php echo (int) $hasta; ?> de <?php echo (int) $totalGrupos; ?>
        · pág. <?php echo (int) $page; ?>/<?php echo (int) $totalPages; ?>
      <?php endif; ?>
    </span>
  </form>

  <div class="patron-desc">
    <?php if (empty($grupos)): ?>
      <p><?php echo ($q !== '' || $filtroEstado !== '') ? 'No hay grupos con esos filtros.' : 'No hay grupos registrados.'; ?></p>
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
                <button type="button" class="btn-icon-only btn-icon-only--edit"
                  title="Asignar docentes del grupo"
                  data-tip="Asignar docentes del grupo (titular / por materia)"
                  aria-label="Asignar docentes del grupo"
                  onclick="cargarSeccion('grupo_docentes', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                  <i class="fas fa-chalkboard-teacher"></i>
                </button>
              <?php endif; ?>
              <?php if ($puedeCal && calificaciones_puede_capturar_grupo($pdo, (int) $g['id_grupo'])): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit"
                  title="Calificaciones del parcial"
                  data-tip="Capturar calificaciones del parcial actual"
                  aria-label="Calificaciones del parcial"
                  onclick="cargarSeccion('grupo_calificaciones', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                  <i class="fas fa-clipboard-list"></i>
                </button>
              <?php endif; ?>
              <?php if ($puedePlan): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit"
                  title="Plan de parciales"
                  data-tip="Ver o editar el plan de parciales del grupo"
                  aria-label="Plan de parciales"
                  onclick="cargarSeccion('grupo_plan', 'id_grupo=<?php echo (int)$g['id_grupo']; ?>')">
                  <i class="fas fa-calendar-check"></i>
                </button>
              <?php endif; ?>
              <?php if ($puedeApertura): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit btn-ajustar-fecha-grupo"
                  title="Ajustar fecha de inicio"
                  data-tip="Cambiar fecha de inicio (corrige adeudo tras migración)"
                  aria-label="Ajustar fecha de inicio"
                  data-id="<?php echo (int)$g['id_grupo']; ?>"
                  data-fecha="<?php echo htmlspecialchars((string)$g['fecha_inicio'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-clave="<?php echo htmlspecialchars((string)$g['clave'], ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fas fa-calendar-day"></i>
                </button>
              <?php endif; ?>
              <button type="button" class="btn-icon-only btn-icon-only--muted btn-lista-asistencia-pdf"
                title="Imprimir lista de asistencia"
                data-tip="Imprimir lista de asistencia (horizontal)"
                aria-label="Imprimir lista de asistencia"
                data-id="<?php echo (int)$g['id_grupo']; ?>"
                data-clave="<?php echo htmlspecialchars((string)$g['clave'], ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-print"></i>
              </button>
              <?php if ($puedeCredenciales && (int) ($g['total_alumnos'] ?? 0) > 0): ?>
                <button type="button" class="btn-icon-only btn-icon-only--muted btn-credenciales-grupo-pdf"
                  title="Credenciales del grupo"
                  data-tip="Generar e imprimir credenciales de todos los alumnos activos del grupo"
                  aria-label="Credenciales del grupo"
                  data-id="<?php echo (int)$g['id_grupo']; ?>"
                  data-clave="<?php echo htmlspecialchars((string)$g['clave'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-total="<?php echo (int) ($g['total_alumnos'] ?? 0); ?>">
                  <i class="fas fa-id-card"></i>
                </button>
              <?php endif; ?>
              <?php if ($puedeAvance): ?>
                <button type="button" class="btn-icon-only btn-icon-only--ok btn-avanzar-grupo"
                  title="Avanzar al siguiente parcial"
                  data-tip="Avanzar el grupo al siguiente parcial"
                  aria-label="Avanzar al siguiente parcial"
                  data-id="<?php echo (int)$g['id_grupo']; ?>">
                  <i class="fas fa-forward"></i>
                </button>
              <?php endif; ?>
              <?php if ($puedeMoodle): ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit btn-moodle-grupo"
                  title="Inscribir grupo en Moodle"
                  data-tip="Inscribir alumnos activos del grupo en Moodle"
                  aria-label="Inscribir grupo en Moodle"
                  data-id="<?php echo (int)$g['id_grupo']; ?>"
                  data-clave="<?php echo htmlspecialchars((string)$g['clave'], ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fas fa-chalkboard"></i>
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="grupos-pager" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:center; margin-top:14px;">
          <?php if ($page > 1): ?>
            <button type="button" class="secondary" onclick="cargarSeccion('grupos', '<?php echo htmlspecialchars($buildGruposQs(['page' => 1]), ENT_QUOTES, 'UTF-8'); ?>')">« Primero</button>
            <button type="button" class="secondary" onclick="cargarSeccion('grupos', '<?php echo htmlspecialchars($buildGruposQs(['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>')">‹ Anterior</button>
          <?php endif; ?>
          <?php
            $winStart = max(1, $page - 2);
            $winEnd = min($totalPages, $page + 2);
            for ($p = $winStart; $p <= $winEnd; $p++):
          ?>
            <button type="button" class="<?php echo $p === $page ? 'primary' : 'secondary'; ?>"
              onclick="cargarSeccion('grupos', '<?php echo htmlspecialchars($buildGruposQs(['page' => $p]), ENT_QUOTES, 'UTF-8'); ?>')">
              <?php echo (int) $p; ?>
            </button>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <button type="button" class="secondary" onclick="cargarSeccion('grupos', '<?php echo htmlspecialchars($buildGruposQs(['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>')">Siguiente ›</button>
            <button type="button" class="secondary" onclick="cargarSeccion('grupos', '<?php echo htmlspecialchars($buildGruposQs(['page' => $totalPages]), ENT_QUOTES, 'UTF-8'); ?>')">Último »</button>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  function gruposNavigate(page) {
    const q = (document.getElementById('grupos-q')?.value || '').trim();
    const estado = document.getElementById('grupos-estado')?.value || '';
    const perPage = document.getElementById('grupos-per-page')?.value || '25';
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (estado) params.set('estado', estado);
    if (perPage && perPage !== '25') params.set('per_page', perPage);
    if (page && page > 1) params.set('page', String(page));
    const qs = params.toString();
    cargarSeccion('grupos', qs || null);
  }
  document.getElementById('grupos-btn-filtrar')?.addEventListener('click', () => gruposNavigate(1));
  document.getElementById('grupos-btn-limpiar')?.addEventListener('click', () => {
    const qEl = document.getElementById('grupos-q');
    const eEl = document.getElementById('grupos-estado');
    if (qEl) qEl.value = '';
    if (eEl) eEl.value = '';
    gruposNavigate(1);
  });
  document.getElementById('grupos-q')?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      gruposNavigate(1);
    }
  });
  document.getElementById('grupos-per-page')?.addEventListener('change', () => gruposNavigate(1));
  document.getElementById('grupos-estado')?.addEventListener('change', () => gruposNavigate(1));
})();
</script>

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

  document.querySelectorAll('.btn-credenciales-grupo-pdf').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idGrupo = btn.dataset.id;
      if (!idGrupo) return;
      const clave = btn.dataset.clave || 'grupo';
      const total = parseInt(btn.dataset.total || '0', 10) || 0;
      const ok = window.confirm(
        '¿Generar e imprimir las credenciales de los ' + total + ' alumno(s) activos del grupo ' + clave + '?\n\n'
        + 'Se abrirá un PDF con frente y reverso de cada alumno (plantilla activa del plantel).'
      );
      if (!ok) return;
      const url = new URL(<?php echo json_encode(hay_asset_url('php/credencial_pdf.php'), JSON_UNESCAPED_UNICODE); ?>, window.location.href);
      url.searchParams.set('id_grupo', idGrupo);
      window.open(url.toString(), '_blank', 'noopener');
    });
  });

  document.querySelectorAll('.btn-ajustar-fecha-grupo').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const idGrupo = btn.dataset.id;
      if (!idGrupo) return;
      const actual = btn.dataset.fecha || '';
      const clave = btn.dataset.clave || 'grupo';
      const nueva = window.prompt(
        'Nueva fecha de inicio para ' + clave + ' (AAAA-MM-DD).\nActual: ' + actual + '\nEsto recalcula el adeudo de colegiatura.',
        actual
      );
      if (nueva === null) return;
      const motivo = window.prompt('Motivo del ajuste (opcional):', 'Ajuste tras migración') || '';
      const fd = new FormData();
      fd.append('action', 'ajustar_fecha');
      fd.append('id_grupo', idGrupo);
      fd.append('nueva_fecha', nueva.trim());
      fd.append('motivo', motivo);
      try {
        const { data } = await hayFetchJson('php/grupo_apertura_api.php', { method: 'POST', body: fd });
        alert(data.message || '');
        if (data.status === 'ok') cargarSeccion('grupos');
      } catch (e) {
        alert(e.message || 'No se pudo ajustar la fecha');
      }
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
