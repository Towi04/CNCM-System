<?php
require_once __DIR__ . '/../config.php';
$id = (int) ($_GET['id'] ?? 0);
$idPlantel = plantel_scope_id($pdo);

try {
    if (function_exists('alumno_ensure_schema')) {
        alumno_ensure_schema($pdo);
    }
    if (function_exists('pago_migrate_alumno_pagos_columns')) {
        pago_migrate_alumno_pagos_columns($pdo);
    }
    if (function_exists('pago_migrate_pago_auditoria')) {
        pago_migrate_pago_auditoria($pdo);
    }
    if (function_exists('pago_migrate_alumno_inscripcion_global')) {
        pago_migrate_alumno_inscripcion_global($pdo);
    }
    if (function_exists('academico_ensure_schema')) {
        academico_ensure_schema($pdo);
    }
    if (function_exists('asistencia_ensure_schema')) {
        asistencia_ensure_schema($pdo);
    }
    if (function_exists('usuario_suspension_ensure_schema')) {
        usuario_suspension_ensure_schema($pdo);
    }
} catch (Throwable $e) {
    error_log('alumno_detalle schema: ' . $e->getMessage());
}

if (alumno_portal_es_alumno()) {
    $idPropio = alumno_portal_id_sesion();
    if ($idPropio <= 0 || ($id > 0 && $id !== $idPropio)) {
        echo '<div class="alert">Solo puede consultar su propio expediente.</div>';
        return;
    }
    if ($id <= 0) {
        $id = $idPropio;
    }
}

try {
    $a = alumno_obtener($pdo, $id, $idPlantel);
} catch (Throwable $e) {
    error_log('alumno_detalle obtener: ' . $e->getMessage());
    echo '<div class="alert">Error al cargar el alumno. Intente de nuevo o contacte a soporte.</div>';
    echo '<p style="color:#666;font-size:0.85rem;">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    return;
}

if (!$a) {
    echo '<div class="alert">Alumno no encontrado.</div>';
    return;
}

try {
    $gruposHist = alumno_grupos_historial($pdo, $id);
} catch (Throwable $e) {
    $gruposHist = [];
}
$gruposActivos = array_filter($gruposHist, fn($g) => (int)$g['activo'] === 1);
try {
    $pagos = function_exists('pago_supervisor_puede') && pago_supervisor_puede()
        ? pago_listar_alumno_todos($pdo, $id)
        : alumno_pagos_lista($pdo, $id, (int)($a['id_especialidad'] ?? 0) ?: null);
} catch (Throwable $e) {
    error_log('alumno_detalle pagos: ' . $e->getMessage());
    $pagos = [];
}
$puedeEditarPagos = function_exists('pago_supervisor_puede') && pago_supervisor_puede();
try {
    $calificaciones = alumno_calificaciones_fase($pdo, $id);
} catch (Throwable $e) {
    $calificaciones = [];
}
$espList = [];
try {
    $especialidadesAlumno = $pdo->prepare(
        'SELECT DISTINCT e.id_especialidad, e.nombre FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         INNER JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE ag.id_alumno = ? AND e.id_especialidad IS NOT NULL'
    );
    $especialidadesAlumno->execute([$id]);
    $espList = $especialidadesAlumno->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $espList = [];
}
try {
    $inscripcionesColeg = pago_inscripciones_alumno($pdo, $id);
} catch (Throwable $e) {
    $inscripcionesColeg = [];
}
$certSolicitudes = [];
try {
    $certSolicitudes = function_exists('certificacion_solicitudes_alumno')
        ? certificacion_solicitudes_alumno($pdo, $id)
        : [];
} catch (Throwable $e) {
    $certSolicitudes = [];
}
$pagosProductos = array_filter($pagos, static fn($p) => ($p['tipo'] ?? '') === 'producto');
$certEstados = function_exists('certificacion_estados_etiquetas') ? certificacion_estados_etiquetas() : [];
$kidsIds = ['ingles' => 0, 'computacion' => 0];
try {
    $kidsIds = combo_ids_kids($pdo);
} catch (Throwable $e) {
    // ignore
}
$gruposKidsIng = [];
$gruposKidsComp = [];
if ($kidsIds['ingles'] > 0) {
    try {
        $st = $pdo->prepare('SELECT id_grupo, clave FROM grupos WHERE id_plantel = ? AND id_especialidad = ? ORDER BY clave');
        $st->execute([$idPlantel, $kidsIds['ingles']]);
        $gruposKidsIng = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $gruposKidsIng = [];
    }
}
if ($kidsIds['computacion'] > 0) {
    try {
        $st = $pdo->prepare('SELECT id_grupo, clave FROM grupos WHERE id_plantel = ? AND id_especialidad = ? ORDER BY clave');
        $st->execute([$idPlantel, $kidsIds['computacion']]);
        $gruposKidsComp = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $gruposKidsComp = [];
    }
}
$todasEsp = [];
try {
    $todasEsp = $pdo->query('SELECT id_especialidad, clave, nombre FROM especialidades WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $todasEsp = [];
}
$gruposTodosList = [];
try {
    $gruposTodos = $pdo->prepare('SELECT g.id_grupo, g.clave, e.nombre AS esp FROM grupos g LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad WHERE g.id_plantel = ? ORDER BY g.clave');
    $gruposTodos->execute([$idPlantel]);
    $gruposTodosList = $gruposTodos->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $gruposTodosList = [];
}
$ubicacionesHist = [];
try {
    $ubicacionesHist = function_exists('ubicacion_resumen_alumno') ? ubicacion_resumen_alumno($pdo, $id) : [];
} catch (Throwable $e) {
    $ubicacionesHist = [];
}
$ubicacionActivaPorEsp = [];
foreach ($ubicacionesHist as $ub) {
    if (in_array($ub['estado'], ['pendiente', 'autorizado'], true)) {
        $ubicacionActivaPorEsp[(int) $ub['id_especialidad']] = $ub;
    }
}

$alertaInscripcion = '';
if (($a['estado'] ?? '') === 'baja' && !empty($a['inscripcion_vigente_hasta'])) {
    $dias = (int) ((strtotime($a['inscripcion_vigente_hasta']) - time()) / 86400);
    if ($dias >= 0 && $dias <= 45) {
        $alertaInscripcion = 'Inscripción vigente hasta ' . date('d/m/Y', strtotime($a['inscripcion_vigente_hasta']))
            . ' (' . $dias . ' días). Contactar para que regrese antes de pagar inscripción de nuevo.';
    } elseif ($dias < 0) {
        $alertaInscripcion = 'La inscripción caducó el ' . date('d/m/Y', strtotime($a['inscripcion_vigente_hasta'])) . '. Deberá pagar inscripción nuevamente.';
    }
}

$iniciales = mb_strtoupper(
    mb_substr($a['nombres'] ?? $a['nombre'] ?? 'A', 0, 1) .
    mb_substr($a['apellido_paterno'] ?? $a['apellido'] ?? '', 0, 1)
);
$fotoUrl = alumno_foto_public_url($a['foto'] ?? null);
if ($fotoUrl === null && !empty($a['id_preregistro'])) {
    $prSt = $pdo->prepare('SELECT foto FROM preregistros WHERE id_preregistro = ? LIMIT 1');
    $prSt->execute([(int) $a['id_preregistro']]);
    $prFoto = trim((string) ($prSt->fetchColumn() ?: ''));
    if ($prFoto !== '') {
        $copiada = alumno_foto_copiar_desde_preregistro($prFoto, $id);
        if ($copiada) {
            alumno_foto_asignar($pdo, $id, $copiada);
            $fotoUrl = alumno_foto_public_url($copiada);
        }
    }
}
$tieneFoto = $fotoUrl !== null;
$puedeEditarFoto = function_exists('usuario_puede_gestionar_alumnos') && usuario_puede_gestionar_alumnos();
$puedeEditarDatosAlumno = function_exists('alumno_datos_puede_editar') && alumno_datos_puede_editar();
$puedeCambioDrasticoNombre = function_exists('alumno_nombre_puede_cambio_drastico') && alumno_nombre_puede_cambio_drastico();
$puedeTarifaSupervisor = function_exists('alumno_tarifa_supervisor_puede') && alumno_tarifa_supervisor_puede();
$control = $a['numero_control'] ?? $a['matricula'] ?? $id;
$puedeSuspenderAlumno = function_exists('usuario_suspension_puede_gestionar_alumno') && usuario_suspension_puede_gestionar_alumno();
$uAlumnoSusp = null;
if (!empty($a['id_usuario'])) {
    $uAlumnoSusp = usuario_por_id($pdo, (int) $a['id_usuario']);
}
$suspApiAlumno = hay_asset_url('php/usuario_suspension_api.php');
?>
<link rel="stylesheet" href="css/alumnos.css">
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">
<link rel="stylesheet" href="css/ubicacion.css">

<script>
  setPageHeader('Detalle alumno', 'INICIO / ALUMNOS / DETALLE ALUMNO');
</script>

<div style="margin-bottom:12px;">
  <button type="button" onclick="cargarSeccion('alumnos')">← Volver a alumnos</button>
</div>

<div class="alumno-detalle-layout">
  <aside>
    <div class="alumno-credencial">
      <div class="alumno-credencial__foto-wrap" id="alumno-foto-wrap">
        <span class="alumno-credencial__iniciales" id="alumno-foto-iniciales"><?php echo htmlspecialchars($iniciales); ?></span>
        <?php if ($tieneFoto): ?>
          <img
            src="<?php echo htmlspecialchars($fotoUrl); ?>?t=<?php echo time(); ?>"
            alt="Foto del alumno"
            class="alumno-credencial__foto-img"
            id="alumno-foto-img"
          >
        <?php endif; ?>
      </div>
      <?php if ($puedeEditarFoto): ?>
      <div class="alumno-foto-actions">
        <form id="form-alumno-foto" action="php/alumno_foto_upload.php" method="POST" enctype="multipart/form-data" data-no-global-ajax>
          <input type="hidden" name="id_alumno" value="<?php echo (int) $id; ?>">
          <label class="alumno-foto-btn">
            <i class="fas fa-camera"></i> <?php echo $tieneFoto ? 'Cambiar foto' : 'Subir foto'; ?>
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
          </label>
        </form>
        <?php if ($tieneFoto): ?>
        <form id="form-alumno-foto-remove" action="php/alumno_foto_upload.php" method="POST" data-no-global-ajax>
          <input type="hidden" name="id_alumno" value="<?php echo (int) $id; ?>">
          <input type="hidden" name="action" value="remove">
          <button type="submit" class="alumno-foto-btn alumno-foto-btn--remove">Quitar</button>
        </form>
        <?php endif; ?>
      </div>
      <div id="alumno-foto-msg" class="alumno-foto-msg" style="display:none;" role="status"></div>
      <?php endif; ?>
      <div class="alumno-credencial__control"><?php echo htmlspecialchars((string)$control); ?></div>
      <?php if (!empty($a['codigo_huella'])): ?>
        <div style="font-size:0.8rem; margin-top:4px;"><i class="fas fa-fingerprint"></i> ID lector <?php echo htmlspecialchars($a['codigo_huella']); ?></div>
      <?php endif; ?>
      <div class="alumno-credencial__nombre"><?php echo htmlspecialchars($a['nombre_completo']); ?></div>
      <div style="font-size:0.85rem; opacity:0.9;">Alumno · <?php echo htmlspecialchars(alumno_estado_label($a['estado'] ?? 'activo')); ?></div>
      <div class="alumno-credencial__grupos">
        <strong>Grupos activos:</strong><br>
        <?php if (empty($gruposActivos)): ?>
          Sin grupos activos
        <?php else: ?>
          <?php foreach ($gruposActivos as $g): ?>
            · <?php echo htmlspecialchars($g['clave']); ?><?php if (!empty($g['especialidad_nombre'])): ?> (<?php echo htmlspecialchars($g['especialidad_nombre']); ?>)<?php endif; ?><br>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <button type="button" class="primary" style="margin-top:14px; width:100%;" onclick="cargarSeccion('alumno_estado_cuenta', 'id=<?php echo (int)$id; ?>')">
        Estado de cuenta / Adeudo
      </button>
      <?php if (function_exists('usuario_puede_gestionar_alumnos') && usuario_puede_gestionar_alumnos() && !empty($a['id_usuario'])): ?>
        <button type="button" class="secondary" style="margin-top:8px; width:100%;" id="btn-reset-pass-alumno">
          Restablecer contraseña portal (Cncm*<?php echo htmlspecialchars((string) $control); ?>)
        </button>
      <?php endif; ?>
      <button type="button" style="margin-top:8px; width:100%;" id="btn-inscribir-esp-alumno">+ Especialidad / grupo</button>
      <button type="button" style="margin-top:8px; width:100%;" id="btn-inscribir-kids-alumno">Inscripción infantil</button>
      <?php if (function_exists('moodle_inscripcion_puede_gestionar') && moodle_inscripcion_puede_gestionar()): ?>
        <button type="button" class="secondary" style="margin-top:8px; width:100%;" id="btn-moodle-inscribir-alumno">
          <i class="fas fa-chalkboard"></i> Inscribir a curso Moodle
        </button>
      <?php endif; ?>
      <?php if (function_exists('ubicacion_puede_evaluar') && ubicacion_puede_evaluar()): ?>
        <button type="button" style="margin-top:8px; width:100%;" onclick="cargarSeccion('ubicacion_coordinacion')">
          Bandeja ubicación
        </button>
      <?php endif; ?>
    </div>
  </aside>

  <?php foreach ($ubicacionActivaPorEsp as $idEspUb => $ubRow):
    $cls = $ubRow['estado'] === 'pendiente' ? 'ub-alumno-banner' : 'ub-alumno-banner ub-alumno-banner--ok';
    $grps = array_column($ubRow['grupos_autorizados'] ?? [], 'clave');
  ?>
    <div class="<?php echo $cls; ?>" style="grid-column:1/-1;">
      <strong><i class="fas fa-map-signs"></i> Ubicación — <?php echo htmlspecialchars($ubRow['esp_nombre']); ?></strong>
      (<?php echo htmlspecialchars(ubicacion_estados_etiquetas()[$ubRow['estado']] ?? $ubRow['estado']); ?>)
      <?php if ($ubRow['nivel_detectado']): ?> · Nivel <?php echo htmlspecialchars($ubRow['nivel_detectado']); ?><?php endif; ?>
      <?php if ($grps): ?><br>Grupos autorizados: <strong><?php echo htmlspecialchars(implode(', ', $grps)); ?></strong><?php endif; ?>
      <?php if ($ubRow['estado'] === 'pendiente'): ?>
        <br><span style="color:#e65100;">Recepción no puede inscribir en esta especialidad hasta que coordinación autorice grupos.</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if ($alertaInscripcion !== ''): ?>
    <div class="catalog-alert catalog-alert--error" style="margin-bottom:14px; grid-column:1/-1;">
      <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($alertaInscripcion); ?>
    </div>
  <?php endif; ?>

  <div class="alumno-tabs">
    <nav class="alumno-tabs__nav" id="alumno-tabs-nav">
      <button type="button" class="is-active" data-tab="documentos">Documentos</button>
      <button type="button" data-tab="pagos">Historial de pagos</button>
      <button type="button" data-tab="info">Información del alumno</button>
      <button type="button" data-tab="cuentas">Cuentas digitales</button>
      <button type="button" data-tab="asistencias">Asistencias</button>
      <button type="button" data-tab="productos">Productos</button>
      <button type="button" data-tab="notas">Notas</button>
      <button type="button" data-tab="calificaciones">Calificaciones por fase</button>
      <button type="button" data-tab="historial">Historial de grupos</button>
      <?php if ($puedeTarifaSupervisor): ?>
      <button type="button" data-tab="colegiatura-supervisor">Colegiatura (supervisor)</button>
      <?php endif; ?>
    </nav>

    <div class="alumno-tabs__panel is-active" id="tab-documentos">
      <p style="color:#666;">Documentación del expediente (contratos, identificación, etc.).</p>
      <?php
      $docRows = [];
      try {
          $docs = $pdo->prepare('SELECT * FROM alumno_documentos WHERE id_alumno = ? ORDER BY creado_en DESC');
          $docs->execute([$id]);
          $docRows = $docs->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
          $docRows = [];
      }
      ?>
      <?php if (empty($docRows)): ?>
        <p>No hay documentos registrados.</p>
      <?php else: ?>
        <ul><?php foreach ($docRows as $d): ?>
          <li><?php echo htmlspecialchars($d['tipo'] . ' — ' . $d['nombre']); ?></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>

    <div class="alumno-tabs__panel" id="tab-pagos">
      <?php
      $ecPagos = pago_estado_cuenta($pdo, $id);
      if ($ecPagos['ok']):
      ?>
        <div style="background:#fff8e1; border:1px solid #ffe082; padding:12px; border-radius:8px; margin-bottom:14px;">
          <strong>Adeudo colegiatura (hoy):</strong>
          <span style="font-size:1.3rem; color:#c62828; font-weight:800;">
            <?php echo catalog_format_mxn($ecPagos['resumen']['adeudo_colegiatura']); ?>
          </span>
          · Pagado: <?php echo catalog_format_mxn($ecPagos['resumen']['colegiatura_pagada']); ?>
          · Debería: <?php echo catalog_format_mxn($ecPagos['resumen']['colegiatura_esperada']); ?>
          <button type="button" class="primary" style="margin-left:10px;" onclick="cargarSeccion('alumno_estado_cuenta', 'id=<?php echo (int)$id; ?>')">Ver / Imprimir</button>
        </div>
        <?php if ($puedeSuspenderAlumno && !empty($a['id_usuario'])): ?>
        <?php
          $suspAdeudo = $uAlumnoSusp && usuario_suspension_esta_activa($uAlumnoSusp)
              && usuario_suspension_tipo($uAlumnoSusp) === USUARIO_SUSPENSION_PORTAL_ADEUDO;
        ?>
        <div id="alumno-susp-adeudo-panel" style="background:#fce4ec; border:1px solid #f48fb1; padding:14px; border-radius:8px; margin-bottom:14px;">
          <h4 style="margin:0 0 8px;"><i class="fas fa-user-lock"></i> Acceso al portal</h4>
          <p style="font-size:0.88rem; margin:0 0 10px; color:#555;">
            Suspender por adeudo: el alumno <strong>sí puede iniciar sesión</strong> pero verá un aviso y no tendrá acceso al tutor IA ni a funciones académicas hasta regularizar.
          </p>
          <?php if ($suspAdeudo): ?>
            <p style="margin:0 0 10px; color:#c62828; font-weight:600;">
              Suspendido por adeudo
              <?php if (!empty($uAlumnoSusp['suspension_motivo'])): ?>
                — <?php echo htmlspecialchars($uAlumnoSusp['suspension_motivo']); ?>
              <?php endif; ?>
            </p>
            <button type="button" class="primary" id="btn-alumno-reactivar-acceso">Rehabilitar acceso completo</button>
          <?php else: ?>
            <input type="text" id="alumno-susp-motivo" placeholder="Motivo (opcional)" style="width:100%;max-width:400px;padding:8px;margin-bottom:8px;border:1px solid #ddd;border-radius:8px;">
            <button type="button" class="secondary" id="btn-alumno-susp-adeudo" style="background:#ffcdd2;">Suspender por adeudo</button>
          <?php endif; ?>
          <div id="alumno-susp-msg" style="display:none;margin-top:10px;padding:8px;border-radius:6px;"></div>
        </div>
        <?php endif; ?>
      <?php endif; ?>
      <?php if (count($espList) > 1): ?>
        <label>Especialidad:</label>
        <select id="filtro-esp-pagos" style="margin-bottom:12px; padding:8px;">
          <option value="">Todas</option>
          <?php foreach ($espList as $e): ?>
            <option value="<?php echo (int)$e['id_especialidad']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <?php if (empty($pagos)): ?>
        <p>No hay pagos registrados.</p>
      <?php else: ?>
        <table class="alumno-pagos-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Especialidad</th>
              <th>Folio</th>
              <th>Pago</th>
              <th>Forma pago</th>
              <th>Cubrió</th>
              <th>Recibió</th>
              <?php if ($puedeEditarPagos): ?><th>Acciones</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pagos as $p): ?>
              <?php $anulado = ($p['estado'] ?? 'activo') === 'anulado'; ?>
              <tr data-esp="<?php echo (int)($p['id_especialidad'] ?? 0); ?>"<?php echo $anulado ? ' style="opacity:.65;background:#fff5f5;"' : ''; ?>>
                <td><?php echo date('d/m/Y H:i', strtotime($p['creado_en'])); ?></td>
                <td><?php echo htmlspecialchars($p['especialidad_nombre'] ?? '—'); ?></td>
                <td class="folio"><?php echo htmlspecialchars($p['folio'] ?? '—'); ?></td>
                <td><?php echo number_format((float)$p['monto'], 2); ?><?php if ($anulado): ?> <span style="color:#c62828;">(anulado)</span><?php endif; ?></td>
                <td><?php echo htmlspecialchars($p['forma_pago'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($p['cubrio'] ?? $p['concepto'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($p['recibio_nombre'] ?? '—'); ?></td>
                <?php if ($puedeEditarPagos): ?>
                <td>
                  <?php if (!$anulado): ?>
                  <button type="button" class="secondary btn-pago-anular" data-id="<?php echo (int)$p['id_pago']; ?>" style="font-size:12px;padding:4px 8px;">Anular</button>
                  <button type="button" class="secondary btn-pago-editar" data-id="<?php echo (int)$p['id_pago']; ?>" data-monto="<?php echo (float)$p['monto']; ?>" data-concepto="<?php echo htmlspecialchars($p['concepto'] ?? '', ENT_QUOTES); ?>" style="font-size:12px;padding:4px 8px;">Editar</button>
                  <?php else: ?>
                  <small><?php echo htmlspecialchars($p['anulado_motivo'] ?? ''); ?></small>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="alumno-tabs__panel" id="tab-info">
      <?php if (huella_puede_editar_alumno()): ?>
      <div class="huella-pin-card" style="margin-bottom:20px; padding:16px; background:#e8f4fd; border:1px solid #90caf9; border-radius:10px;">
        <h3 style="margin:0 0 8px; font-size:1rem;"><i class="fas fa-fingerprint"></i> Huella digital (U.areU 5300)</h3>
        <p style="margin:0 0 12px; font-size:0.88rem; color:#444;">
          Lector: <strong>HID U.areU 5300</strong>. Si no tiene huella registrada, recepción marca su asistencia
          en <a href="#" onclick="cargarSeccion('asistencia_faltantes'); return false;">Rondín de asistencia</a>
          con el número de control (<?php echo htmlspecialchars($a['numero_control'] ?? ''); ?>).
        </p>
        <?php if (!empty($a['huella_registrada'])): ?>
          <p style="color:#2e7d32; font-size:0.88rem; margin:0 0 10px;">
            <i class="fas fa-check-circle"></i> Huella registrada
            <?php if (!empty($a['huella_registrada_en'])): ?>
              · <?php echo date('d/m/Y H:i', strtotime($a['huella_registrada_en'])); ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <button type="button" class="primary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_huella_enroll', 'id=<?php echo (int)$id; ?>')">
          <i class="fas fa-fingerprint"></i> Registrar / actualizar huella
        </button>
      </div>
      <?php elseif (!empty($a['codigo_huella'])): ?>
      <p style="margin-bottom:16px;"><i class="fas fa-fingerprint"></i> ID lector: <strong><?php echo htmlspecialchars($a['codigo_huella']); ?></strong></p>
      <?php endif; ?>

      <?php if ($puedeEditarDatosAlumno): ?>
      <div class="welcome-card" style="padding:16px; margin-bottom:18px;">
        <h3 style="margin-top:0;">Actualizar datos del alumno</h3>
        <p style="color:#666; font-size:0.9rem; margin-top:0;">
          Puede corregir datos de contacto y dirección libremente. En el nombre, recepción/coordinación solo puede hacer correcciones menores
          (acentos o errores de captura). Cambios drásticos requieren dirección o supervisión.
          <?php if ($puedeCambioDrasticoNombre): ?><strong>Su rol puede autorizar cambios drásticos de nombre.</strong><?php endif; ?>
        </p>
        <form id="form-alumno-datos" class="catalog-form-grid" data-no-global-ajax>
          <input type="hidden" name="id_alumno" value="<?php echo (int) $id; ?>">
          <label>Nombres
            <input type="text" name="nombres" required value="<?php echo htmlspecialchars($a['nombres'] ?? $a['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Apellido paterno
            <input type="text" name="apellido_paterno" required value="<?php echo htmlspecialchars($a['apellido_paterno'] ?? $a['apellido'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Apellido materno
            <input type="text" name="apellido_materno" value="<?php echo htmlspecialchars($a['apellido_materno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Fecha de nacimiento
            <input type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars($a['fecha_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Teléfono
            <input type="text" name="telefono" value="<?php echo htmlspecialchars($a['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Teléfono alterno
            <input type="text" name="telefono2" value="<?php echo htmlspecialchars($a['telefono2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label class="full">Correo
            <input type="email" name="email" value="<?php echo htmlspecialchars($a['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label class="full">Domicilio
            <input type="text" name="domicilio" value="<?php echo htmlspecialchars($a['domicilio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Colonia
            <input type="text" name="colonia" value="<?php echo htmlspecialchars($a['colonia'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Municipio
            <input type="text" name="municipio" value="<?php echo htmlspecialchars($a['municipio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Código postal
            <input type="text" name="codigo_postal" maxlength="10" value="<?php echo htmlspecialchars($a['codigo_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <div class="full" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <button type="submit" class="primary">Guardar datos</button>
            <span id="alumno-datos-msg" style="font-size:0.9rem;"></span>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <div class="prereg-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <div><strong>Asesor que inscribió</strong><br><?php echo htmlspecialchars($a['asesor_nombre'] ?? '—'); ?></div>
        <div><strong>Especialidad principal</strong><br><?php echo htmlspecialchars($a['especialidad_nombre'] ?? '—'); ?></div>
        <div><strong>Forma de pago</strong><br><?php echo ($a['forma_pago'] ?? '') === 'semanal' ? 'Semanal' : 'Mensual'; ?></div>
        <div><strong>Modo infantil</strong><br><?php
          $modoK = $a['inscripcion_kids_modo'] ?? '';
          echo $modoK === 'dual' ? 'Inglés + Computación' : ($modoK === 'solo_ingles' ? 'Solo inglés' : ($modoK === 'solo_computacion' ? 'Solo computación' : '—'));
        ?></div>
        <div><strong>Teléfono</strong><br><?php echo htmlspecialchars($a['telefono'] ?? '—'); ?></div>
        <div><strong>Teléfono alterno</strong><br><?php echo htmlspecialchars($a['telefono2'] ?? '—'); ?></div>
        <div><strong>Correo</strong><br><?php echo htmlspecialchars($a['email'] ?? '—'); ?></div>
        <div><strong>Fecha de nacimiento</strong><br><?php echo htmlspecialchars($a['fecha_nacimiento'] ?? '—'); ?></div>
        <div class="full-width"><strong>Domicilio</strong><br><?php
          $dom = trim(($a['domicilio'] ?? '') . ' ' . ($a['colonia'] ?? '') . ' ' . ($a['municipio'] ?? '') . ' ' . ($a['codigo_postal'] ?? ''));
          echo htmlspecialchars($dom !== '' ? $dom : '—');
        ?></div>
        <div><strong>Fecha de alta</strong><br><?php echo htmlspecialchars($a['fecha_alta'] ?? '—'); ?></div>
        <div><strong>Estado</strong><br><?php echo htmlspecialchars(alumno_estado_label($a['estado'] ?? 'activo')); ?></div>
        <?php if (!empty($a['inscripcion_vigente_hasta'])): ?>
          <div><strong>Inscripción vigente hasta</strong><br><?php echo date('d/m/Y', strtotime($a['inscripcion_vigente_hasta'])); ?></div>
        <?php endif; ?>
        <?php if (!empty($a['motivo_baja_temporal'])): ?>
          <div class="full-width"><strong>Motivo baja temporal</strong><br><?php echo htmlspecialchars($a['motivo_baja_temporal']); ?></div>
        <?php endif; ?>
        <?php if (!empty($a['id_usuario'])): ?>
          <div class="full-width">
            <strong>Portal alumno</strong><br>
            Usuario: <code><?php echo htmlspecialchars($a['numero_control'] ?? ''); ?></code>
            · Contraseña: <code><?php echo 'Cncm*' . htmlspecialchars($a['numero_control'] ?? ''); ?></code>
          </div>
        <?php endif; ?>
      </div>

      <h3 style="margin-top:20px;">Colegiaturas congeladas (por especialidad)</h3>
      <?php if (empty($inscripcionesColeg)): ?>
        <p>Sin inscripciones a especialidad registradas.</p>
      <?php else: ?>
        <table class="alumno-pagos-table">
          <thead><tr><th>Especialidad</th><th>Inscripción</th><th>Mensual</th><th>Pronto pago</th><th>Regla combo</th></tr></thead>
          <tbody>
            <?php foreach ($inscripcionesColeg as $ic): ?>
              <?php
                $rn = null;
                if (!empty($ic['id_regla_combo'])) {
                    $st = $pdo->prepare('SELECT nombre FROM reglas_colegiatura_combo WHERE id_regla = ?');
                    $st->execute([(int)$ic['id_regla_combo']]);
                    $rn = $st->fetchColumn();
                }
              ?>
              <tr>
                <td><?php echo htmlspecialchars($ic['especialidad_nombre']); ?></td>
                <td><?php echo catalog_format_mxn($ic['costo_inscripcion']); ?></td>
                <td><?php echo catalog_format_mxn($ic['costo_mensualidad']); ?></td>
                <td><?php echo catalog_format_mxn($ic['costo_pronto_pago']); ?></td>
                <td><?php echo $rn ? htmlspecialchars($rn) : '—'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div style="margin-top:20px; padding:16px; background:#f5f5f5; border-radius:10px;">
        <h3 style="margin-top:0;">Baja temporal</h3>
        <p style="color:#666; font-size:0.9rem;">
          La inscripción pagada se respeta <strong><?php echo pago_inscripcion_vigencia_meses_alumno($pdo, $id); ?> meses</strong>
          <?php if (pago_inscripcion_vigencia_meses_alumno($pdo, $id) === PAGO_INSCRIPCION_VIGENCIA_PREP_ABIERTA_MESES): ?>
            (prepa abierta).
          <?php else: ?>
            (demás especialidades).
          <?php endif; ?>
          Use baja temporal para pausar clases; el sistema alertará en inicio cuando la vigencia esté por caducar.
        </p>
        <?php if (($a['estado'] ?? '') === 'baja'): ?>
          <button type="button" class="primary" id="btn-reactivar-alumno">Reactivar alumno</button>
        <?php else: ?>
          <label>Motivo</label>
          <textarea id="baja-motivo" rows="2" style="width:100%; margin-bottom:8px;" placeholder="Ej. viaje, salud, trabajo…"></textarea>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="primary" id="btn-baja-temporal" style="background:#e65100;">Registrar baja temporal</button>
            <button type="button" id="btn-baja-definitiva" style="background:#b71c1c; color:#fff;">Baja definitiva</button>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($gruposActivos) && (alumno_grupo_acciones_puede() || reporte_semanal_puede_ver())): ?>
      <div style="margin-top:20px; padding:16px; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:10px;">
        <h3 style="margin-top:0;"><i class="fas fa-exchange-alt"></i> Grupo, cambio de horario y fin de curso</h3>
        <p style="color:#555; font-size:0.88rem; margin-bottom:12px;">
          Al cambiar de grupo, la asistencia se registra en el nuevo horario y sale del anterior (+C / −C en reporte semanal).
        </p>
        <?php foreach ($gruposActivos as $gAct):
          $idGrupoAct = (int) $gAct['id_grupo'];
          $opcionesCambio = alumno_grupos_para_cambio($pdo, $id, $idGrupoAct, $idPlantel);
        ?>
        <div class="alumno-grupo-accion-card" style="margin-bottom:14px; padding:12px; background:#fff; border-radius:8px; border:1px solid #eee;">
          <strong><?php echo htmlspecialchars($gAct['clave']); ?></strong>
          <?php if (!empty($gAct['especialidad_nombre'])): ?>
            · <?php echo htmlspecialchars($gAct['especialidad_nombre']); ?>
          <?php endif; ?>
          <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
            <?php if (!empty($opcionesCambio)): ?>
            <div style="flex:1; min-width:200px;">
              <label style="font-size:0.8rem;">Cambiar a grupo</label>
              <select class="sel-cambio-grupo" data-grupo-actual="<?php echo $idGrupoAct; ?>" style="width:100%; padding:8px;">
                <option value="">— Elegir nuevo horario —</option>
                <?php foreach ($opcionesCambio as $og): ?>
                <option value="<?php echo (int) $og['id_grupo']; ?>">
                  <?php echo htmlspecialchars($og['clave'] . ($og['profesor'] ? ' · ' . trim($og['profesor']) : '')); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="button" class="secondary btn-cambio-grupo" data-grupo-actual="<?php echo $idGrupoAct; ?>">Cambiar horario</button>
            <?php endif; ?>
            <button type="button" class="primary btn-fin-curso" data-grupo="<?php echo $idGrupoAct; ?>" style="background:#1565c0;">
              <i class="fas fa-graduation-cap"></i> Fin de curso
            </button>
          </div>
        </div>
        <?php endforeach; ?>
        <p id="alumno-grupo-acc-msg" class="asist-checada-msg" style="margin-top:8px;"></p>
      </div>
      <?php endif; ?>
    </div>

    <div class="alumno-tabs__panel" id="tab-cuentas">
      <div id="alumno-cuentas-panel" style="max-width:920px;">
        <p style="color:#666; margin-bottom:16px;">
          Google Workspace, portal HAY y Moodle. Al inscribirse se crean automáticamente;
          desde aquí puede verificar, crear las faltantes, restablecer contraseñas e inscribir cursos Moodle.
        </p>
        <div id="alumno-cuentas-loading" style="padding:20px; color:#666;">Cargando estado de cuentas…</div>
        <div id="alumno-cuentas-content" style="display:none;"></div>
        <div id="alumno-cuentas-msg" class="asist-checada-msg" style="margin-top:12px; display:none;"></div>
      </div>
    </div>

    <div class="alumno-tabs__panel" id="tab-asistencias">
      <p>Resumen de asistencias del alumno.</p>
      <?php
      $asiRows = [];
      try {
          $asi = $pdo->prepare(
            'SELECT asi.fecha, asi.presente, g.clave FROM asistencias asi
             INNER JOIN grupos g ON g.id_grupo = asi.id_grupo
             WHERE asi.id_alumno = ? ORDER BY asi.fecha DESC LIMIT 40'
          );
          $asi->execute([$id]);
          $asiRows = $asi->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
          $asiRows = [];
      }
      ?>
      <?php if (empty($asiRows)): ?>
        <p>Sin registros de asistencia.</p>
      <?php else: ?>
        <table class="alumno-pagos-table">
          <thead><tr><th>Fecha</th><th>Grupo</th><th>Presente</th></tr></thead>
          <tbody>
            <?php foreach ($asiRows as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['fecha']); ?></td>
                <td><?php echo htmlspecialchars($row['clave']); ?></td>
                <td><?php echo (int)$row['presente'] ? 'Sí' : 'No'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="alumno-tabs__panel" id="tab-productos">
      <?php if (!empty($certSolicitudes)): ?>
        <h4 style="margin-top:0;">Certificaciones solicitadas</h4>
        <table class="catalog-table" style="margin-bottom:20px;">
          <thead>
            <tr><th>Certificación</th><th>Estado</th><th>Examen</th><th>Solicitud</th></tr>
          </thead>
          <tbody>
            <?php foreach ($certSolicitudes as $cs): ?>
            <tr>
              <td><?php echo htmlspecialchars($cs['certificacion'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($certEstados[$cs['estado'] ?? ''] ?? ($cs['estado'] ?? '')); ?></td>
              <td><?php echo !empty($cs['fecha_examen']) ? date('d/m/Y', strtotime($cs['fecha_examen'])) : '—'; ?></td>
              <td><?php echo date('d/m/Y', strtotime($cs['creado_en'] ?? 'now')); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if (!empty($pagosProductos)): ?>
        <h4>Productos pagados</h4>
        <table class="catalog-table">
          <thead>
            <tr><th>Fecha</th><th>Concepto</th><th>Monto</th><th>Folio</th></tr>
          </thead>
          <tbody>
            <?php foreach ($pagosProductos as $pp): ?>
            <tr>
              <td><?php echo date('d/m/Y', strtotime($pp['creado_en'] ?? 'now')); ?></td>
              <td><?php echo htmlspecialchars($pp['concepto'] ?? 'Producto'); ?></td>
              <td><?php echo catalog_format_mxn((float)($pp['monto'] ?? 0)); ?></td>
              <td><?php echo htmlspecialchars($pp['folio'] ?? '—'); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif (empty($certSolicitudes)): ?>
        <p>Sin productos ni certificaciones registradas.</p>
      <?php endif; ?>

      <?php if (function_exists('certificacion_puede_acceder') && certificacion_puede_acceder()): ?>
        <p style="margin-top:16px;">
          <button type="button" class="primary" onclick="typeof cargarSeccion==='function'&&cargarSeccion('certificaciones')">
            Ir a módulo Certificaciones
          </button>
        </p>
      <?php endif; ?>
    </div>

    <div class="alumno-tabs__panel" id="tab-notas">
      <?php
      $notaRows = [];
      try {
          $notas = $pdo->prepare(
            'SELECT n.*, CONCAT(u.nombre, " ", u.apellido) AS autor FROM alumno_notas n
             LEFT JOIN usuarios u ON u.id_usuario = n.id_usuario
             WHERE n.id_alumno = ? ORDER BY n.creado_en DESC'
          );
          $notas->execute([$id]);
          $notaRows = $notas->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
          $notaRows = [];
      }
      ?>
      <?php if (empty($notaRows)): ?>
        <p>Sin notas internas.</p>
      <?php else: ?>
        <?php foreach ($notaRows as $n): ?>
          <div style="border-bottom:1px solid #eee; padding:10px 0;">
            <small><?php echo date('d/m/Y H:i', strtotime($n['creado_en'])); ?> · <?php echo htmlspecialchars($n['autor'] ?? ''); ?></small>
            <p style="margin:4px 0 0;"><?php echo nl2br(htmlspecialchars($n['nota'])); ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="alumno-tabs__panel" id="tab-calificaciones">
      <?php if (empty($calificaciones)): ?>
        <p>No hay fases definidas para las especialidades de este alumno. Configúralas en administración.</p>
      <?php else: ?>
        <table class="alumno-pagos-table">
          <thead><tr><th>Especialidad</th><th>Fase</th><th>Calificación</th><th>Observaciones</th></tr></thead>
          <tbody>
            <?php foreach ($calificaciones as $c): ?>
              <tr>
                <td><?php echo htmlspecialchars($c['especialidad_nombre']); ?></td>
                <td><?php echo htmlspecialchars($c['nombre_fase']); ?></td>
                <td><?php echo $c['calificacion'] !== null ? htmlspecialchars((string)$c['calificacion']) : '—'; ?></td>
                <td><?php echo htmlspecialchars($c['observaciones'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="alumno-tabs__panel" id="tab-historial">
      <table class="alumno-pagos-table">
        <thead>
          <tr><th>Grupo</th><th>Especialidad</th><th>Inicio</th><th>Baja</th><th>Estado</th></tr>
        </thead>
        <tbody>
          <?php foreach ($gruposHist as $g): ?>
            <tr>
              <td><?php echo htmlspecialchars($g['clave']); ?></td>
              <td><?php echo htmlspecialchars($g['especialidad_nombre'] ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($g['fecha_inicio']); ?></td>
              <td><?php echo $g['fecha_baja'] ? htmlspecialchars($g['fecha_baja']) : '—'; ?></td>
              <td><?php echo (int)$g['activo'] ? 'Activo' : 'Inactivo'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($puedeTarifaSupervisor): ?>
    <div class="alumno-tabs__panel" id="tab-colegiatura-supervisor">
      <div id="alumno-tarifa-supervisor-panel" style="max-width:960px;">
        <p style="color:#666; margin-bottom:16px;">
          Define una colegiatura solo para este alumno: beneficio especial, corrección de tarifa, vigencia temporal,
          resto del curso o condonación de adeudo pendiente.
        </p>
        <div id="alumno-tarifa-supervisor-msg" class="asist-checada-msg" style="display:none; margin-bottom:12px;"></div>
        <div id="alumno-tarifa-supervisor-list"></div>
        <h4 style="margin-top:24px;">Historial de ajustes</h4>
        <div id="alumno-tarifa-supervisor-hist"></div>
        <h4 style="margin-top:24px;">Condonaciones de adeudo</h4>
        <div id="alumno-tarifa-condonaciones"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="catalog-modal" id="modal-inscribir-esp">
  <div class="catalog-modal__dialog">
    <h3>Inscribir a otra especialidad / grupo</h3>
    <p style="color:#666; font-size:0.9rem;">Al agregar una 2.ª especialidad se aplicará automáticamente la regla de colegiatura combinada si existe. Después elegirá grupo y registrará el pago de inscripción en el asistente.</p>
    <label>Especialidad</label>
    <select id="esp-nueva-id" style="width:100%; padding:8px; margin-bottom:8px;">
      <?php foreach ($todasEsp as $e): ?>
        <option value="<?php echo (int)$e['id_especialidad']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
      <?php endforeach; ?>
    </select>
    <div id="esp-ubicacion-aviso" class="ub-alumno-banner" style="display:none; margin-bottom:10px;"></div>
    <label>Grupo (opcional)</label>
    <select id="esp-nueva-grupo" style="width:100%; padding:8px; margin-bottom:12px;">
      <option value="">— Sin grupo —</option>
      <?php foreach ($gruposTodosList as $g): ?>
        <option value="<?php echo (int)$g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave'] . ($g['esp'] ? ' (' . $g['esp'] . ')' : '')); ?></option>
      <?php endforeach; ?>
    </select>
    <div class="catalog-modal__actions">
      <button type="button" id="esp-nueva-cancel">Cancelar</button>
      <button type="button" class="primary" id="esp-nueva-save">Inscribir</button>
    </div>
  </div>
</div>

<div class="catalog-modal" id="modal-moodle-alumno">
  <div class="catalog-modal__dialog" style="max-width:520px;">
    <h3>Inscribir a curso Moodle</h3>
    <p style="color:#666; font-size:0.9rem;">Seleccione el curso. Si el alumno no tiene cuenta Moodle, se creará automáticamente.</p>
    <?php
    $espMoodle = $espList !== [] ? $espList : $todasEsp;
    if (count($espMoodle) > 1): ?>
    <label>Especialidad (para registro)</label>
    <select id="moodle-alumno-esp" style="width:100%; padding:8px; margin-bottom:8px;">
      <?php foreach ($espMoodle as $e): ?>
        <option value="<?php echo (int)$e['id_especialidad']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
      <?php endforeach; ?>
    </select>
    <?php else: ?>
    <input type="hidden" id="moodle-alumno-esp" value="<?php echo (int)($espMoodle[0]['id_especialidad'] ?? $a['id_especialidad'] ?? 0); ?>">
    <?php endif; ?>
    <label>Curso Moodle</label>
    <select id="moodle-alumno-curso" style="width:100%; padding:8px; margin-bottom:12px;">
      <option value="">— Cargando cursos… —</option>
    </select>
    <div class="catalog-modal__actions">
      <button type="button" id="moodle-alumno-cancel">Cancelar</button>
      <button type="button" class="primary" id="moodle-alumno-save">Inscribir</button>
    </div>
  </div>
</div>

<div class="catalog-modal" id="modal-inscribir-kids">
  <div class="catalog-modal__dialog">
    <h3>Inscripción infantil</h3>
    <p style="color:#666; font-size:0.9rem;">Puede inscribirse a <strong>ambas</strong> materias o solo una. Cada materia tiene calificación por fase.</p>
    <label>Grupo inglés kids (opcional)</label>
    <select id="kids-grupo-ing" style="width:100%; padding:8px; margin-bottom:8px;">
      <option value="">— No inscribir inglés —</option>
      <?php foreach ($gruposKidsIng as $g): ?>
        <option value="<?php echo (int)$g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave']); ?></option>
      <?php endforeach; ?>
    </select>
    <label>Grupo computación kids (opcional)</label>
    <select id="kids-grupo-comp" style="width:100%; padding:8px; margin-bottom:12px;">
      <option value="">— No inscribir computación —</option>
      <?php foreach ($gruposKidsComp as $g): ?>
        <option value="<?php echo (int)$g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave']); ?></option>
      <?php endforeach; ?>
    </select>
    <div class="catalog-modal__actions">
      <button type="button" id="kids-cancel">Cancelar</button>
      <button type="button" class="primary" id="kids-save">Inscribir</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/modal_inscripcion_wizard.php'; ?>

<link rel="stylesheet" href="css/punto_venta.css">
<script src="js/inscripcion_wizard.js?v=20260624"></script>
<?php if ($puedeTarifaSupervisor): ?>
<script>
  window.HayAlumnoTarifaSupervisor = { idAlumno: <?php echo (int) $id; ?>, api: 'php/alumno_tarifa_supervisor_api.php' };
</script>
<script src="js/alumno_tarifa_supervisor.js?v=20260623"></script>
<?php endif; ?>
<script>
(function () {
  const idAlumno = <?php echo (int)$id; ?>;
  const alumnoControl = <?php echo json_encode((string) $control, JSON_UNESCAPED_UNICODE); ?>;

  document.getElementById('form-alumno-datos')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    const msg = document.getElementById('alumno-datos-msg');
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    if (msg) {
      msg.textContent = 'Guardando...';
      msg.style.color = '#666';
    }
    try {
      const { data } = await hayFetchJson('php/alumno_datos_api.php', { method: 'POST', body: new FormData(form) });
      if (msg) {
        msg.textContent = data.message || '';
        msg.style.color = data.status === 'ok' ? '#2e7d32' : '#c62828';
      }
      if (data.status === 'ok') {
        setTimeout(() => cargarSeccion('alumno_detalle', 'id=' + idAlumno), 700);
      }
    } catch (err) {
      if (msg) {
        msg.textContent = err.message || 'No se pudieron guardar los datos.';
        msg.style.color = '#c62828';
      }
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  async function filtrarGruposPorUbicacion() {
    const selEsp = document.getElementById('esp-nueva-id');
    const selGr = document.getElementById('esp-nueva-grupo');
    if (!selEsp || !selGr) return;
    const idEsp = selEsp.value;
    const r = await fetch('php/ubicacion_api.php?action=grupos_permitidos&id_alumno=' + idAlumno + '&id_especialidad=' + idEsp);
    const d = await r.json();
    const aviso = document.getElementById('esp-ubicacion-aviso');
    Array.from(selGr.options).forEach((o) => {
      if (o.value === '') return;
      o.disabled = false;
      o.hidden = false;
    });
    if (!d.restringido) {
      if (aviso) aviso.style.display = 'none';
      return;
    }
    if (aviso) {
      aviso.style.display = 'block';
      if (d.ubicacion?.estado === 'pendiente') {
        aviso.textContent = 'Ubicación pendiente: no puede inscribir hasta autorización de coordinación.';
        aviso.className = 'ub-alumno-banner';
      } else if (d.id_grupos && d.id_grupos.length) {
        aviso.textContent = 'Solo grupos autorizados por ubicación están habilitados.';
        aviso.className = 'ub-alumno-banner ub-alumno-banner--ok';
      } else {
        aviso.textContent = 'Sin grupos autorizados aún.';
        aviso.className = 'ub-alumno-banner ub-alumno-banner--warn';
      }
    }
    const permitidos = new Set((d.id_grupos || []).map(String));
    Array.from(selGr.options).forEach((o) => {
      if (o.value === '') return;
      const ok = permitidos.has(o.value);
      o.disabled = !ok;
      if (!ok) o.hidden = true;
    });
  }

  document.getElementById('btn-inscribir-esp-alumno')?.addEventListener('click', () => {
    document.getElementById('modal-inscribir-esp')?.classList.add('is-open');
    filtrarGruposPorUbicacion();
  });
  document.getElementById('esp-nueva-id')?.addEventListener('change', filtrarGruposPorUbicacion);

  const moodleApi = 'php/moodle_inscripcion_api.php';
  async function cargarCursosMoodleAlumno() {
    const sel = document.getElementById('moodle-alumno-curso');
    const idEsp = document.getElementById('moodle-alumno-esp')?.value || '';
    if (!sel) return;
    sel.innerHTML = '<option value="">— Cargando… —</option>';
    try {
      const url = moodleApi + '?action=cursos' + (idEsp ? '&id_especialidad=' + encodeURIComponent(idEsp) : '');
      const { data } = await hayFetchJson(url);
      const cursos = data.cursos || [];
      if (!cursos.length) {
        sel.innerHTML = '<option value="">— Sin cursos Moodle disponibles —</option>';
        return;
      }
      sel.innerHTML = '<option value="">— Elija curso —</option>' +
        cursos.map((c) => '<option value="' + c.id + '">' +
          (c.fullname || c.shortname || ('Curso #' + c.id)) +
          (c.fase ? ' · ' + c.fase : '') + '</option>').join('');
    } catch (e) {
      sel.innerHTML = '<option value="">Error: ' + (e.message || 'Moodle') + '</option>';
    }
  }

  document.getElementById('btn-moodle-inscribir-alumno')?.addEventListener('click', async () => {
    document.getElementById('modal-moodle-alumno')?.classList.add('is-open');
    await cargarCursosMoodleAlumno();
  });
  document.getElementById('moodle-alumno-esp')?.addEventListener('change', cargarCursosMoodleAlumno);
  document.getElementById('moodle-alumno-cancel')?.addEventListener('click', () => {
    document.getElementById('modal-moodle-alumno')?.classList.remove('is-open');
  });
  document.getElementById('moodle-alumno-save')?.addEventListener('click', async () => {
    const courseId = document.getElementById('moodle-alumno-curso')?.value;
    if (!courseId) { alert('Seleccione un curso'); return; }
    const fd = new FormData();
    fd.append('action', 'inscribir_alumno');
    fd.append('id_alumno', idAlumno);
    fd.append('course_id', courseId);
    const idEsp = document.getElementById('moodle-alumno-esp')?.value;
    if (idEsp) fd.append('id_especialidad', idEsp);
    try {
      const { data } = await hayFetchJson(moodleApi, { method: 'POST', body: fd });
      alert(data.message || 'Listo');
      if (data.status === 'ok') {
        document.getElementById('modal-moodle-alumno')?.classList.remove('is-open');
        cargarSeccion('alumno_detalle', 'id=' + idAlumno);
      }
    } catch (e) { alert(e.message); }
  });

  document.getElementById('esp-nueva-cancel')?.addEventListener('click', () => {
    document.getElementById('modal-inscribir-esp')?.classList.remove('is-open');
  });
  document.getElementById('esp-nueva-save')?.addEventListener('click', async () => {
    const idEsp = document.getElementById('esp-nueva-id').value;
    const fd = new FormData();
    fd.append('id_alumno', idAlumno);
    fd.append('id_especialidad', idEsp);
    fd.append('id_grupo', '0');
    const r = await fetch('php/alumno_inscribir_especialidad.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    if (data.status !== 'ok') {
      alert(data.message);
      return;
    }
    document.getElementById('modal-inscribir-esp')?.classList.remove('is-open');
    if (window.HayInscripcionWizard) {
      HayInscripcionWizard.openFromAlumno(idAlumno, parseInt(idEsp, 10), 0, () => {
        cargarSeccion('alumno_detalle', 'id=' + idAlumno);
      });
    } else {
      alert(data.message);
      cargarSeccion('alumno_detalle', 'id=' + idAlumno);
    }
  });

  document.getElementById('btn-inscribir-kids-alumno')?.addEventListener('click', () => {
    document.getElementById('modal-inscribir-kids')?.classList.add('is-open');
  });
  document.getElementById('kids-cancel')?.addEventListener('click', () => {
    document.getElementById('modal-inscribir-kids')?.classList.remove('is-open');
  });
  document.getElementById('kids-save')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('id_alumno', idAlumno);
    fd.append('id_grupo_ingles', document.getElementById('kids-grupo-ing').value);
    fd.append('id_grupo_computacion', document.getElementById('kids-grupo-comp').value);
    const r = await fetch('php/alumno_inscribir_kids.php', { method: 'POST', body: fd });
    const data = await r.json();
    alert(data.message);
    if (data.status === 'ok') cargarSeccion('alumno_detalle', 'id=' + idAlumno);
  });

  async function estadoAlumno(accion, extra) {
    const fd = new FormData();
    fd.append('id_alumno', idAlumno);
    fd.append('accion', accion);
    if (extra) Object.keys(extra).forEach(k => fd.append(k, extra[k]));
    const r = await fetch('php/alumno_estado_save.php', { method: 'POST', body: fd });
    return r.json();
  }

  document.getElementById('btn-baja-temporal')?.addEventListener('click', async () => {
    const motivo = document.getElementById('baja-motivo')?.value?.trim();
    if (!motivo) { alert('Indica el motivo'); return; }
    const data = await estadoAlumno('baja_temporal', { motivo });
    alert(data.message);
    if (data.status === 'ok') cargarSeccion('alumno_detalle', 'id=' + idAlumno);
  });

  document.getElementById('btn-baja-definitiva')?.addEventListener('click', async () => {
    const motivo = document.getElementById('baja-motivo')?.value?.trim();
    if (!motivo) { alert('Indica el motivo'); return; }
    if (!confirm('¿Baja definitiva? El alumno saldrá de todos sus grupos activos.')) return;
    const data = await estadoAlumno('baja_definitiva', { motivo });
    alert(data.message);
    if (data.status === 'ok') cargarSeccion('alumno_detalle', 'id=' + idAlumno);
  });

  async function alumnoGrupoApi(accion, extra) {
    const fd = new FormData();
    fd.append('id_alumno', idAlumno);
    fd.append('accion', accion);
    Object.keys(extra || {}).forEach((k) => fd.append(k, extra[k]));
    const r = await fetch('php/alumno_grupo_api.php', { method: 'POST', body: fd });
    return r.json();
  }

  document.querySelectorAll('.btn-cambio-grupo').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const idActual = btn.dataset.grupoActual;
      const sel = document.querySelector('.sel-cambio-grupo[data-grupo-actual="' + idActual + '"]');
      const idNuevo = sel?.value;
      if (!idNuevo) { alert('Elija el grupo destino'); return; }
      if (!confirm('¿Confirmar cambio de horario/grupo?')) return;
      const data = await alumnoGrupoApi('cambio_grupo', { id_grupo_nuevo: idNuevo });
      const msg = document.getElementById('alumno-grupo-acc-msg');
      if (msg) { msg.textContent = data.message || ''; msg.className = 'asist-checada-msg ' + (data.status === 'ok' ? 'ok' : 'err'); }
      if (data.status === 'ok') setTimeout(() => cargarSeccion('alumno_detalle', 'id=' + idAlumno), 800);
    });
  });

  document.querySelectorAll('.btn-fin-curso').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const idGrupo = btn.dataset.grupo;
      const nota = prompt('Nota opcional (proyecto aprobado, etc.):') || '';
      if (!confirm('¿Registrar fin de curso en este grupo?')) return;
      const data = await alumnoGrupoApi('fin_curso', { id_grupo: idGrupo, nota });
      const msg = document.getElementById('alumno-grupo-acc-msg');
      if (msg) { msg.textContent = data.message || ''; msg.className = 'asist-checada-msg ' + (data.status === 'ok' ? 'ok' : 'err'); }
      if (data.status === 'ok') setTimeout(() => cargarSeccion('alumno_detalle', 'id=' + idAlumno), 800);
    });
  });
  document.getElementById('btn-reset-pass-alumno')?.addEventListener('click', async () => {
    const pass = 'Cncm*' + alumnoControl;
    if (!confirm('¿Restablecer contraseña a ' + pass + '?')) return;
    const fd = new FormData();
    fd.append('id_alumno', idAlumno);
    const r = await fetch('php/alumno_reset_password.php', { method: 'POST', body: fd });
    const data = await r.json();
    alert(data.message);
  });

  const suspApiAl = <?php echo json_encode($suspApiAlumno, JSON_UNESCAPED_UNICODE); ?>;
  const suspMsgAl = document.getElementById('alumno-susp-msg');
  function suspAlShow(text, ok) {
    if (!suspMsgAl) return;
    suspMsgAl.style.display = 'block';
    suspMsgAl.textContent = text;
    suspMsgAl.style.background = ok ? '#e8f5e9' : '#ffebee';
  }
  document.getElementById('btn-alumno-susp-adeudo')?.addEventListener('click', async () => {
    if (!confirm('¿Suspender el acceso completo de este alumno por adeudo? Podrá entrar pero verá un aviso limitado.')) return;
    const fd = new FormData();
    fd.append('action', 'suspender_alumno_adeudo');
    fd.append('id_alumno', String(idAlumno));
    fd.append('motivo', document.getElementById('alumno-susp-motivo')?.value?.trim() || '');
    try {
      const r = await fetch(suspApiAl, { method: 'POST', body: fd });
      const data = await r.json();
      suspAlShow(data.message || '', data.status === 'ok');
      if (data.status === 'ok') setTimeout(() => cargarSeccion('alumno_detalle', 'id=' + idAlumno), 800);
    } catch (e) { suspAlShow('Error de conexión', false); }
  });
  document.getElementById('btn-alumno-reactivar-acceso')?.addEventListener('click', async () => {
    if (!confirm('¿Rehabilitar el acceso completo del alumno?')) return;
    const fd = new FormData();
    fd.append('action', 'reactivar_alumno');
    fd.append('id_alumno', String(idAlumno));
    try {
      const r = await fetch(suspApiAl, { method: 'POST', body: fd });
      const data = await r.json();
      suspAlShow(data.message || '', data.status === 'ok');
      if (data.status === 'ok') setTimeout(() => cargarSeccion('alumno_detalle', 'id=' + idAlumno), 800);
    } catch (e) { suspAlShow('Error de conexión', false); }
  });

  document.getElementById('btn-reactivar-alumno')?.addEventListener('click', async () => {
    if (!confirm('¿Reactivar alumno?')) return;
    const data = await estadoAlumno('reactivar');
    alert(data.message);
    if (data.status === 'ok') cargarSeccion('alumno_detalle', 'id=' + idAlumno);
  });

  const nav = document.getElementById('alumno-tabs-nav');
  nav?.querySelectorAll('button[data-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      nav.querySelectorAll('button').forEach((b) => b.classList.remove('is-active'));
      document.querySelectorAll('.alumno-tabs__panel').forEach((p) => p.classList.remove('is-active'));
      btn.classList.add('is-active');
      document.getElementById('tab-' + btn.dataset.tab)?.classList.add('is-active');
      if (btn.dataset.tab === 'cuentas') {
        alumnoCuentasCargar();
      }
      if (btn.dataset.tab === 'colegiatura-supervisor' && typeof window.HayAlumnoTarifaSupervisorCargar === 'function') {
        window.HayAlumnoTarifaSupervisorCargar();
      }
    });
  });

  let alumnoCuentasOpciones = null;

  function alumnoCuentasMsg(text, ok) {
    const el = document.getElementById('alumno-cuentas-msg');
    if (!el) return;
    el.style.display = text ? 'block' : 'none';
    el.className = 'asist-checada-msg ' + (ok ? 'ok' : 'err');
    el.textContent = text || '';
  }

  function alumnoCuentasBadge(activo, configurado) {
    if (!configurado) return '<span style="color:#888;">No configurado</span>';
    return activo
      ? '<span style="color:#2e7d32; font-weight:600;"><i class="fas fa-check-circle"></i> Activo</span>'
      : '<span style="color:#c62828; font-weight:600;"><i class="fas fa-times-circle"></i> Pendiente</span>';
  }

  async function alumnoCuentasApi(action, extra) {
    const fd = new FormData();
    fd.append('id_alumno', idAlumno);
    fd.append('action', action);
    Object.keys(extra || {}).forEach((k) => fd.append(k, extra[k]));
    const r = await fetch('php/alumno_cuenta_api.php', { method: 'POST', body: fd });
    return r.json();
  }

  async function alumnoCuentasOpcionesLoad() {
    if (alumnoCuentasOpciones) return alumnoCuentasOpciones;
    const r = await fetch('php/alumno_cuenta_api.php?action=opciones&id_alumno=' + idAlumno);
    alumnoCuentasOpciones = await r.json();
    return alumnoCuentasOpciones;
  }

  function alumnoCuentasRender(est) {
    const box = document.getElementById('alumno-cuentas-content');
    if (!box || !est) return;
    const g = est.google || {};
    const h = est.hay || {};
    const m = est.moodle || {};
    const pass = est.password_inicial || ('Cncm*' + alumnoControl);
    const puede = !!est.puede_gestionar;
    const btn = (label, servicio, tipo) => puede
      ? `<button type="button" class="secondary alumno-cuenta-btn" data-tipo="${tipo}" data-servicio="${servicio}" style="margin:4px 6px 4px 0;">${label}</button>`
      : '';

    let cursosHtml = '';
    if ((m.cursos || []).length) {
      cursosHtml = '<ul style="margin:8px 0 0 18px;">' + m.cursos.map((c) =>
        '<li>' + (c.fullname || c.shortname) + ' <code>#' + c.id + '</code></li>'
      ).join('') + '</ul>';
    } else {
      cursosHtml = '<p style="margin:8px 0 0; color:#888; font-size:0.88rem;">Sin cursos Moodle visibles para este usuario.</p>';
    }

    box.innerHTML = `
      <p style="margin-bottom:14px;"><strong>Correo institucional:</strong> ${est.email_institucional || '—'}
        · <strong>Contraseña inicial:</strong> <code>${pass}</code></p>
      ${puede ? `<p style="margin-bottom:16px;">${btn('Crear las 3 cuentas', 'all', 'provisionar')}
        ${btn('Restablecer las 3 contraseñas', 'all', 'reset')}</p>` : ''}
      <div style="display:grid; grid-template-columns:1fr; gap:14px;">
        <div style="padding:16px; border:1px solid #ddd; border-radius:10px; background:#fff;">
          <h3 style="margin:0 0 8px;"><i class="fab fa-google"></i> Google Workspace</h3>
          <p style="margin:0 0 8px;">${alumnoCuentasBadge(g.activo, g.configurado)} · ${g.email || ''}</p>
          <p style="margin:0; font-size:0.88rem; color:#555;">${g.mensaje || ''}</p>
          <div style="margin-top:10px;">${btn('Crear Google', 'google', 'provisionar')}${btn('Reset Google', 'google', 'reset')}</div>
        </div>
        <div style="padding:16px; border:1px solid #ddd; border-radius:10px; background:#fff;">
          <h3 style="margin:0 0 8px;"><i class="fas fa-user"></i> Portal HAY</h3>
          <p style="margin:0 0 8px;">${alumnoCuentasBadge(h.activo, true)} · Usuario: <code>${h.username || alumnoControl}</code></p>
          <p style="margin:0; font-size:0.88rem; color:#555;">${h.mensaje || ''}</p>
          <div style="margin-top:10px;">${btn('Crear HAY', 'hay', 'provisionar')}${btn('Reset HAY', 'hay', 'reset')}</div>
        </div>
        <div style="padding:16px; border:1px solid #ddd; border-radius:10px; background:#fff;">
          <h3 style="margin:0 0 8px;"><i class="fas fa-graduation-cap"></i> Moodle</h3>
          <p style="margin:0 0 8px;">${alumnoCuentasBadge(m.activo, m.configurado)} · ${m.username ? 'User: <code>' + m.username + '</code>' : ''} ${m.id_moodle ? '· ID ' + m.id_moodle : ''}</p>
          <p style="margin:0; font-size:0.88rem; color:#555;">${m.mensaje || ''}</p>
          <div style="margin-top:10px;">${btn('Crear Moodle', 'moodle', 'provisionar')}${btn('Reset Moodle', 'moodle', 'reset')}</div>
          <strong style="display:block; margin-top:14px;">Cursos inscritos</strong>${cursosHtml}
        </div>
      </div>
      ${puede ? `
      <div style="margin-top:20px; padding:16px; border:1px solid #ffe082; border-radius:10px; background:#fff8e1;">
        <h3 style="margin:0 0 8px;"><i class="fas fa-link"></i> Vincular cuentas existentes</h3>
        <p style="font-size:0.88rem; color:#555; margin:0 0 12px;">
          Si el alumno ya tiene Google o Moodle, indique correo/username/ID Moodle. Opcionalmente unifique el username en HAY y Moodle.
        </p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; max-width:720px;">
          <label>Correo Google existente<br>
            <input type="email" id="alumno-vinc-google" value="${g.email || est.email_institucional || ''}" style="width:100%; padding:8px;" placeholder="usuario@cncm.edu.mx">
          </label>
          <label>Moodle (username, correo o ID)<br>
            <input type="text" id="alumno-vinc-moodle" value="${m.username || ''}" style="width:100%; padding:8px;" placeholder="14580 o 2923">
          </label>
          <label>Username unificado (HAY)<br>
            <input type="text" id="alumno-vinc-user" value="${h.username || alumnoControl || ''}" style="width:100%; padding:8px;">
          </label>
          <label style="display:flex; align-items:center; gap:8px; margin-top:22px;">
            <input type="checkbox" id="alumno-vinc-sync-moodle" checked>
            Aplicar mismo username en Moodle
          </label>
        </div>
        <div style="margin-top:12px;">
          <button type="button" class="secondary" id="btn-alumno-vinc-buscar">Buscar en Google/Moodle</button>
          <button type="button" class="primary" id="btn-alumno-vinc-guardar">Vincular y unificar</button>
        </div>
        <pre id="alumno-vinc-preview" style="display:none; margin-top:12px; padding:10px; background:#fff; border:1px solid #ddd; border-radius:8px; font-size:0.82rem; white-space:pre-wrap;"></pre>
      </div>
      <div style="margin-top:20px; padding:16px; border:1px solid #90caf9; border-radius:10px; background:#e8f4fd;">
        <h3 style="margin:0 0 12px;"><i class="fas fa-book"></i> Inscribir curso Moodle</h3>
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
          <label>Examen ubicación<br>
            <select id="alumno-cuenta-examen" style="min-width:220px; padding:8px;"><option value="">— Seleccione —</option></select>
          </label>
          <button type="button" class="primary" id="btn-alumno-enrol-examen">Inscribir examen</button>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-top:12px;">
          <label>Curso Moodle (ID interno)<br>
            <input type="number" id="alumno-cuenta-curso-id" min="2" placeholder="ej. 168" style="width:120px; padding:8px;">
          </label>
          <label>o del listado<br>
            <select id="alumno-cuenta-curso-sel" style="min-width:220px; padding:8px;"><option value="">— Curso —</option></select>
          </label>
          <button type="button" class="primary" id="btn-alumno-enrol-curso">Inscribir curso</button>
        </div>
      </div>` : ''}
    `;

    box.querySelectorAll('.alumno-cuenta-btn').forEach((b) => {
      b.addEventListener('click', async () => {
        const servicio = b.dataset.servicio;
        const tipo = b.dataset.tipo;
        const accion = tipo === 'reset' ? 'restablecer' : 'crear';
        if (!confirm('¿' + accion.charAt(0).toUpperCase() + accion.slice(1) + ' cuenta ' + servicio + '?')) return;
        alumnoCuentasMsg('Procesando…', true);
        const data = await alumnoCuentasApi(tipo, { servicio });
        alumnoCuentasMsg(data.message || '', data.status === 'ok');
        if (data.status === 'ok' && data.estado) {
          alumnoCuentasRender(data.estado);
        } else {
          alumnoCuentasCargar();
        }
      });
    });

    if (puede) {
      alumnoCuentasOpcionesLoad().then((opt) => {
        const selEx = document.getElementById('alumno-cuenta-examen');
        (opt.examenes || []).forEach((ex) => {
          const o = document.createElement('option');
          o.value = ex.id_examen;
          o.textContent = (ex.nombre || 'Examen') + ' (' + (ex.esp_nombre || '') + ')';
          selEx?.appendChild(o);
        });
        const selC = document.getElementById('alumno-cuenta-curso-sel');
        (opt.cursos || []).forEach((c) => {
          const o = document.createElement('option');
          o.value = c.id;
          o.textContent = (c.fullname || c.shortname) + ' #' + c.id;
          selC?.appendChild(o);
        });
      });

      document.getElementById('btn-alumno-enrol-examen')?.addEventListener('click', async () => {
        const idEx = document.getElementById('alumno-cuenta-examen')?.value;
        if (!idEx) { alert('Seleccione un examen'); return; }
        if (!confirm('¿Inscribir al curso del examen seleccionado?')) return;
        const data = await alumnoCuentasApi('enrol_examen', { id_examen: idEx });
        alumnoCuentasMsg(data.message || '', data.status === 'ok');
        if (data.estado) alumnoCuentasRender(data.estado);
      });

      document.getElementById('btn-alumno-enrol-curso')?.addEventListener('click', async () => {
        let courseId = parseInt(document.getElementById('alumno-cuenta-curso-id')?.value || '0', 10);
        const sel = document.getElementById('alumno-cuenta-curso-sel')?.value;
        if (!courseId && sel) courseId = parseInt(sel, 10);
        if (courseId <= 1) { alert('Indique ID de curso Moodle'); return; }
        if (!confirm('¿Inscribir al curso Moodle #' + courseId + '?')) return;
        const data = await alumnoCuentasApi('enrol_curso', { course_id: courseId });
        alumnoCuentasMsg(data.message || '', data.status === 'ok');
        if (data.estado) alumnoCuentasRender(data.estado);
      });

      document.getElementById('btn-alumno-vinc-buscar')?.addEventListener('click', async () => {
        const data = await alumnoCuentasApi('buscar', {
          google_email: document.getElementById('alumno-vinc-google')?.value || '',
          moodle_ref: document.getElementById('alumno-vinc-moodle')?.value || '',
        });
        const pre = document.getElementById('alumno-vinc-preview');
        if (pre) {
          pre.style.display = 'block';
          pre.textContent = JSON.stringify(data.resultado || data, null, 2);
        }
      });

      document.getElementById('btn-alumno-vinc-guardar')?.addEventListener('click', async () => {
        if (!confirm('¿Vincular las cuentas indicadas con este alumno?')) return;
        alumnoCuentasMsg('Vinculando…', true);
        const data = await alumnoCuentasApi('vincular', {
          google_email: document.getElementById('alumno-vinc-google')?.value || '',
          moodle_ref: document.getElementById('alumno-vinc-moodle')?.value || '',
          username_unificado: document.getElementById('alumno-vinc-user')?.value || '',
          sync_moodle_username: document.getElementById('alumno-vinc-sync-moodle')?.checked ? '1' : '',
        });
        alumnoCuentasMsg(data.message || '', data.status === 'ok');
        if (data.estado) alumnoCuentasRender(data.estado);
      });
    }
  }

  async function alumnoCuentasCargar() {
    const loading = document.getElementById('alumno-cuentas-loading');
    const content = document.getElementById('alumno-cuentas-content');
    if (loading) loading.style.display = 'block';
    if (content) content.style.display = 'none';
    try {
      const r = await fetch('php/alumno_cuenta_api.php?action=estado&id_alumno=' + idAlumno);
      const data = await r.json();
      if (loading) loading.style.display = 'none';
      if (content) content.style.display = 'block';
      if (data.status === 'ok' && data.estado) {
        alumnoCuentasRender(data.estado);
      } else {
        alumnoCuentasMsg(data.message || 'No se pudo cargar', false);
      }
    } catch (e) {
      if (loading) loading.style.display = 'none';
      alumnoCuentasMsg('Error de conexión', false);
    }
  }

  document.getElementById('filtro-esp-pagos')?.addEventListener('change', (e) => {
    const v = e.target.value;
    document.querySelectorAll('#tab-pagos tbody tr').forEach((tr) => {
      if (!v) { tr.style.display = ''; return; }
      tr.style.display = tr.dataset.esp === v ? '' : 'none';
    });
  });

  document.querySelectorAll('.btn-pago-anular').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const motivo = prompt('Motivo de la anulación (obligatorio):');
      if (!motivo || !motivo.trim()) return;
      const fd = new FormData();
      fd.append('accion', 'anular');
      fd.append('id_pago', btn.dataset.id || '');
      fd.append('motivo', motivo.trim());
      const r = await fetch('php/pago_supervisor_api.php', { method: 'POST', body: fd });
      const data = await r.json();
      alert(data.message || (data.ok ? 'Listo' : 'Error'));
      if (data.ok && typeof cargarSeccion === 'function') {
        cargarSeccion('alumno_detalle', 'id=' + idAlumno);
      }
    });
  });

  document.querySelectorAll('.btn-pago-editar').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const monto = prompt('Nuevo monto:', btn.dataset.monto || '');
      if (monto === null) return;
      const concepto = prompt('Concepto:', btn.dataset.concepto || '');
      if (concepto === null) return;
      const motivo = prompt('Motivo de la edición (obligatorio):');
      if (!motivo || !motivo.trim()) return;
      const fd = new FormData();
      fd.append('accion', 'editar');
      fd.append('id_pago', btn.dataset.id || '');
      fd.append('monto', monto);
      fd.append('concepto', concepto);
      fd.append('motivo', motivo.trim());
      const r = await fetch('php/pago_supervisor_api.php', { method: 'POST', body: fd });
      const data = await r.json();
      alert(data.message || (data.ok ? 'Listo' : 'Error'));
      if (data.ok && typeof cargarSeccion === 'function') {
        cargarSeccion('alumno_detalle', 'id=' + idAlumno);
      }
    });
  });

  const fotoMsg = document.getElementById('alumno-foto-msg');
  const fotoWrap = document.getElementById('alumno-foto-wrap');

  function showFotoMsg(text, ok) {
    if (!fotoMsg) return;
    fotoMsg.style.display = 'block';
    fotoMsg.className = 'alumno-foto-msg ' + (ok ? 'ok' : 'err');
    fotoMsg.textContent = text;
  }

  function setAlumnoFoto(url) {
    if (!fotoWrap) return;
    let img = document.getElementById('alumno-foto-img');
    if (url) {
      const resolved = typeof window.hayResolveAssetUrl === 'function'
        ? window.hayResolveAssetUrl(url)
        : url;
      if (!img) {
        img = document.createElement('img');
        img.id = 'alumno-foto-img';
        img.className = 'alumno-credencial__foto-img';
        img.alt = 'Foto del alumno';
        fotoWrap.appendChild(img);
      }
      img.src = resolved + (resolved.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
      fotoWrap.classList.add('has-photo');
    } else if (img) {
      img.remove();
      fotoWrap.classList.remove('has-photo');
    }
  }

  const formAlumnoFoto = document.getElementById('form-alumno-foto');
  if (formAlumnoFoto) {
    const fileInput = formAlumnoFoto.querySelector('input[type="file"]');
    fileInput?.addEventListener('change', () => {
      if (fileInput.files && fileInput.files[0]) formAlumnoFoto.requestSubmit();
    });
    formAlumnoFoto.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAlumnoFoto);
      try {
        let data;
        if (typeof window.hayFetchJson === 'function') {
          const out = await window.hayFetchJson(formAlumnoFoto.action, { method: 'POST', body: fd });
          data = out.data;
        } else {
          const res = await fetch(formAlumnoFoto.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
          data = await res.json();
        }
        const ok = data.status === 'ok';
        showFotoMsg(data.message || (ok ? 'Foto actualizada' : 'Error'), ok);
        if (ok) {
          setAlumnoFoto(data.foto_url || null);
          fileInput.value = '';
        }
      } catch (err) {
        showFotoMsg(err.message || 'Error al subir la foto', false);
      }
    });
  }

  document.getElementById('form-alumno-foto-remove')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!confirm('¿Quitar la foto del alumno?')) return;
    const fd = new FormData(e.target);
    try {
      const res = await fetch(e.target.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      showFotoMsg(data.message || '', data.status === 'ok');
      if (data.status === 'ok') setAlumnoFoto(null);
    } catch (err) {
      showFotoMsg('Error al quitar la foto', false);
    }
  });

  if (document.getElementById('alumno-foto-img')) {
    fotoWrap?.classList.add('has-photo');
  }
})();
</script>
