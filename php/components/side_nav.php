<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../config.php';
}
/** @var PDO $pdo */
if (!function_exists('rbac_cap')) {
    require_once __DIR__ . '/../rbac_helper.php';
}
if (isset($pdo) && $pdo instanceof PDO && function_exists('rbac_db_cargar_sesion_rol') && !empty($_SESSION['user_id'])) {
    rbac_db_cargar_sesion_rol($pdo, rbac_rol_efectivo());
}
require_once __DIR__ . '/../menu_config.php';
rbac_reparar_sesion_legacy();

$displayName = !empty($_SESSION['fullname'])
    ? $_SESSION['fullname']
    : trim(($_SESSION['nombre'] ?? 'Usuario') . ' ' . ($_SESSION['apellido'] ?? ''));

$n = !empty($_SESSION['nombre']) ? mb_substr($_SESSION['nombre'], 0, 1) : 'U';
$a = !empty($_SESSION['apellido']) ? mb_substr($_SESSION['apellido'], 0, 1) : '';
$iniciales = strtoupper($n . $a);

$rolRaw = rbac_rol_efectivo();
$rolLabel = strtoupper(rbac_etiqueta_rol());
if (rbac_esta_simulando_rol()) {
    $rolLabel .= ' (vista)';
}

$esAlumno = (rbac_rol_efectivo() === 'alumno');

require_once __DIR__ . '/../avatar_helper.php';
$avatarUrl = user_avatar_public_url($_SESSION['avatar'] ?? null);
$puedeSimularRol = rbac_puede_simular_rol();
?>
<nav class="side-nav" id="sidebar" aria-label="Menú principal">
    <div class="sidebar-brand">
        <img src="src/logobco.png" alt="Grupo Educativo CNCM" class="sidebar-logo">
    </div>

    <div class="sidebar-user" id="sidebar-user-trigger">
        <div class="sidebar-user-avatar<?php echo $avatarUrl ? ' has-photo' : ''; ?>">
            <span class="sidebar-user-iniciales" aria-hidden="true"><?php echo htmlspecialchars($iniciales); ?></span>
            <?php if ($avatarUrl): ?>
                <img
                    src="<?php echo htmlspecialchars($avatarUrl); ?>?t=<?php echo time(); ?>"
                    alt=""
                    class="sidebar-user-photo"
                    onload="this.closest('.sidebar-user-avatar')?.classList.add('has-photo')"
                    onerror="this.closest('.sidebar-user-avatar')?.classList.remove('has-photo'); this.remove();"
                >
            <?php endif; ?>
        </div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name"><?php echo htmlspecialchars($displayName); ?></span>
            <span class="sidebar-user-rol" id="sidebar-user-rol"><?php echo htmlspecialchars($rolLabel); ?></span>
        </div>
        <i class="fas fa-chevron-down sidebar-user-arrow" aria-hidden="true"></i>
        <div class="sidebar-user-dropdown" id="sidebar-user-dropdown">
            <a href="#" data-seccion="perfil"><i class="fas fa-user-circle"></i> Mi perfil</a>
            <?php if ($puedeSimularRol): ?>
            <a href="#" data-seccion="perfil" data-perfil-focus="rol"><i class="fas fa-eye"></i> Cambiar vista de rol</a>
            <?php endif; ?>
        <?php if (!empty($_SESSION['id_alumno_link'])): ?>
            <a href="#" data-seccion="alumno_mi_perfil">
                <i class="fas fa-id-card"></i> Mi perfil (alumno)
            </a>
        <?php endif; ?>
            <a href="#" data-seccion="cambiar_password"><i class="fas fa-key"></i> Cambiar contraseña</a>
            <div class="dropdown-divider"></div>
            <a href="<?php echo htmlspecialchars(function_exists('hay_asset_url') ? hay_asset_url('php/logout.php') : 'php/logout.php', ENT_QUOTES, 'UTF-8'); ?>" class="logout-link"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
        </div>
    </div>

    <ul class="sidebar-menu">
        <?php if ($esAlumno): ?>
        <li class="section-title">Mi portal</li>
        <li class="nav-item" data-seccion="alumno_portal_inicio" data-title="Inicio" data-breadcrumb="INICIO">
            <i class="fas fa-home"></i>
            <span class="nav-text">Inicio</span>
        </li>
        <li class="nav-item" data-seccion="alumno_mis_calificaciones" data-title="Mis calificaciones" data-breadcrumb="MIS CALIFICACIONES">
            <i class="fas fa-star"></i>
            <span class="nav-text">Mis calificaciones</span>
        </li>
        <li class="nav-item" data-seccion="alumno_mis_cursos" data-title="Mis cursos Moodle" data-breadcrumb="MIS CURSOS">
            <i class="fas fa-laptop"></i>
            <span class="nav-text">Mis cursos</span>
        </li>
        <li class="nav-item" data-seccion="alumno_mis_libros" data-title="Mis libros" data-breadcrumb="MIS LIBROS">
            <i class="fas fa-book-open"></i>
            <span class="nav-text">Mis libros</span>
        </li>
        <li class="nav-item" data-seccion="alumno_mis_cuentas" data-title="Cuentas digitales" data-breadcrumb="CUENTAS DIGITALES">
            <i class="fas fa-cloud"></i>
            <span class="nav-text">Cuentas digitales</span>
        </li>
        <?php
        $idAlumnoPortal = function_exists('alumno_portal_id_sesion')
            ? alumno_portal_id_sesion()
            : (int) ($_SESSION['id_alumno_link'] ?? 0);
        if ($idAlumnoPortal > 0): ?>
        <li class="nav-item" data-seccion="alumno_estado_cuenta" data-query="id=<?php echo (int) $idAlumnoPortal; ?>" data-title="Mis pagos" data-breadcrumb="MIS PAGOS">
            <i class="fas fa-file-invoice-dollar"></i>
            <span class="nav-text">Mis pagos</span>
        </li>
        <?php endif; ?>
        <li class="nav-item" data-seccion="alumno_chat" data-title="Mensajes" data-breadcrumb="MENSAJES">
            <i class="fas fa-comments"></i>
            <span class="nav-text">Mensajes</span>
        </li>
        <li class="nav-item" data-seccion="profesor_360_mis_resultados" data-title="Evaluar profesores" data-breadcrumb="EVALUAR PROFESORES">
            <i class="fas fa-star"></i>
            <span class="nav-text">Evaluar profesores</span>
        </li>
        <?php if (function_exists('tutor_puede_usar') && tutor_puede_usar()): ?>
        <li class="nav-item" data-seccion="tutor_chat" data-title="Tutor IA" data-breadcrumb="TUTOR IA">
            <i class="fas fa-robot"></i>
            <span class="nav-text">Tutor IA</span>
        </li>
        <?php endif; ?>
        <li class="nav-item" data-seccion="alumno_promociones" data-title="Promociones" data-breadcrumb="PROMOCIONES">
            <i class="fas fa-gift"></i>
            <span class="nav-text">Promociones</span>
        </li>
        <li class="nav-item" data-seccion="alumno_mi_perfil" data-title="Mi perfil" data-breadcrumb="MI PERFIL">
            <i class="fas fa-id-card"></i>
            <span class="nav-text">Mi perfil</span>
        </li>
        <li class="nav-item" data-seccion="alumno_solicitar_constancia" data-title="Solicitar constancia" data-breadcrumb="SOLICITAR CONSTANCIA">
            <i class="fas fa-file-certificate"></i>
            <span class="nav-text">Solicitar constancia</span>
        </li>
        <li class="nav-item" data-seccion="mi_expediente_documentos" data-title="Mi expediente" data-breadcrumb="MI EXPEDIENTE">
            <i class="fas fa-folder-open"></i>
            <span class="nav-text">Mi expediente (SEP)</span>
        </li>
        <li class="nav-item" data-seccion="soporte_tecnico" data-title="Soporte" data-breadcrumb="SOPORTE">
            <i class="fas fa-life-ring"></i>
            <span class="nav-text">Soporte</span>
        </li>
        <?php else: ?>
        <?php
        $esCandidatoDocente = false;
        if (isset($pdo) && $pdo instanceof PDO && function_exists('docente_prospecto_es_candidato_usuario')) {
            try {
                $esCandidatoDocente = (bool) docente_prospecto_es_candidato_usuario($pdo, (int) ($_SESSION['user_id'] ?? 0));
            } catch (Throwable $e) {
                error_log('side_nav docente_prospecto: ' . $e->getMessage());
            }
        }
        ?>
        <?php if ($esCandidatoDocente): ?>
        <li class="section-title">Candidato docente</li>
        <li class="nav-item" data-seccion="docente_candidato_portal" data-title="Mi proceso" data-breadcrumb="MI PROCESO">
            <i class="fas fa-user-tie"></i>
            <span class="nav-text">Mi proceso de selección</span>
        </li>
        <li class="nav-item" data-seccion="mi_expediente_documentos" data-title="Mis documentos" data-breadcrumb="MIS DOCUMENTOS">
            <i class="fas fa-folder-open"></i>
            <span class="nav-text">Mis documentos</span>
        </li>
        <li class="nav-item" data-seccion="cambiar_password" data-title="Contraseña" data-breadcrumb="CONTRASEÑA">
            <i class="fas fa-key"></i>
            <span class="nav-text">Cambiar contraseña</span>
        </li>
        <li class="nav-item" data-seccion="soporte_tecnico" data-title="Soporte" data-breadcrumb="SOPORTE">
            <i class="fas fa-life-ring"></i>
            <span class="nav-text">Soporte</span>
        </li>
        <?php else: ?>

        <?php
        if (function_exists('menu_cncm_render_items_safe')) {
            menu_cncm_render_items_safe();
        } elseif (function_exists('menu_cncm_render_items')) {
            menu_cncm_render_items();
        }
        ?>

        <?php
        $menuCompacto = function_exists('menu_cncm_usa_menu_compacto') && menu_cncm_usa_menu_compacto();
        $rolNav = rbac_rol_efectivo();
        if (!$menuCompacto && (!function_exists('menu_cncm_vista_por_rol') || !menu_cncm_vista_por_rol())):
        ?>

        <?php if (rbac_cap('menu_asesorias') && in_array($rolNav, ['profesor', 'director', 'supervisor'], true)): ?>
        <li class="nav-item" data-seccion="asesorias" data-title="Calendario asesorías" data-breadcrumb="CALENDARIO ASESORÍAS">
            <i class="fas fa-calendar-alt"></i>
            <span class="nav-text">Calendario asesorías</span>
        </li>
        <?php endif; ?>

        <?php if ((rbac_cap('menu_examenes') || rbac_cap('menu_hay')) && in_array($rolNav, ['director', 'gerente', 'supervisor'], true)): ?>
        <li class="section-title">Evaluación HAY</li>
        <?php endif; ?>

        <?php if (rbac_cap('menu_examenes') && in_array($rolNav, ['director', 'gerente', 'supervisor'], true)): ?>
        <li class="nav-item" data-seccion="examen_disc" data-title="DISC" data-breadcrumb="DISC">
            <i class="fas fa-clipboard-list"></i>
            <span class="nav-text">DISC</span>
        </li>

        <li class="nav-item has-flyout" data-flyout="flyout-examenes" data-title="Generar exámenes" data-breadcrumb="EXÁMENES">
            <i class="fas fa-file-alt"></i>
            <span class="nav-text">Generar exámenes</span>
            <i class="fas fa-chevron-down arrow"></i>
        </li>
        <?php endif; ?>

        <?php if (rbac_cap('menu_hay') && in_array($rolNav, ['director', 'gerente', 'supervisor'], true)): ?>
        <li class="nav-item has-flyout" data-flyout="flyout-hay" data-title="Evaluación HAY" data-breadcrumb="EVALUACIÓN HAY">
            <i class="fas fa-layer-group"></i>
            <span class="nav-text">Evaluación HAY</span>
            <i class="fas fa-chevron-down arrow"></i>
        </li>
        <?php endif; ?>

        <?php endif; ?>

        <?php endif; ?>

        <?php endif; ?>
    </ul>

    <button type="button" id="toggle-sidebar" class="btn-toggle-sidebar-mobile" aria-label="Abrir menú" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
</nav>

<div class="menu-flyouts" id="menu-flyouts" aria-hidden="true">
    <?php
    $rolFly = rbac_rol_efectivo();
    $esRecepcion = ($rolFly === 'admin');
    $esCoordinador = ($rolFly === 'coordinador');
    $esProfesor = ($rolFly === 'profesor');
    ?>
    <?php if (rbac_cap('menu_admin') && !$esRecepcion): ?>
    <div class="menu-flyout menu-flyout--wide menu-flyout--cols3" id="flyout-admin" data-flyout-id="flyout-admin">
        <div class="flyout-title">Administración</div>
        <div class="flyout-grid flyout-grid--3">
            <ul class="flyout-list">
                <?php if (function_exists('bandeja_aprobaciones_puede_ver') && bandeja_aprobaciones_puede_ver()): ?>
                <li data-seccion="bandeja_aprobaciones" data-title="Bandeja de aprobaciones" data-breadcrumb="BANDEJA APROBACIONES">Bandeja de aprobaciones</li>
                <?php endif; ?>
                <?php if (function_exists('profesor_portal_puede_revisar_permisos') && profesor_portal_puede_revisar_permisos()): ?>
                <li data-seccion="profesor_permisos_admin" data-title="Permisos profesores" data-breadcrumb="PERMISOS PROFESORES">Permisos de profesores</li>
                <?php endif; ?>
                <?php if (function_exists('rbac_puede_centro_permisos') && rbac_puede_centro_permisos()): ?>
                <li data-seccion="admin_roles" data-title="Roles y permisos" data-breadcrumb="ROLES Y PERMISOS">Roles y permisos</li>
                <?php endif; ?>
                <?php if (rbac_cap('admin_usuarios') && (function_exists('profesor_eval_puede_gestionar') ? profesor_eval_puede_gestionar() : false)): ?>
                <li data-seccion="calificar_usuario" data-title="Evaluación 360 profesores" data-breadcrumb="EVALUACIÓN 360">Evaluación 360 profesores</li>
                <?php endif; ?>
                <?php if (rbac_cap('admin_usuarios')): ?>
                <li data-seccion="ver_usuarios" data-title="Ver usuarios" data-breadcrumb="USUARIOS">Ver usuarios</li>
                <li data-seccion="docente_prospectos" data-title="Reclutamiento docente" data-breadcrumb="RECLUTAMIENTO DOCENTE">Reclutamiento docente</li>
                <li data-seccion="docente_bolsa" data-title="Bolsa de candidatos" data-breadcrumb="BOLSA CANDIDATOS">Bolsa de candidatos</li>
                <li data-seccion="crear_usuario" data-title="Nuevo usuario" data-breadcrumb="NUEVO USUARIO">Nuevo usuario</li>
                <?php endif; ?>
            </ul>
            <ul class="flyout-list">
                <?php if (rbac_cap('admin_planteles')): ?>
                <li data-seccion="admin_planteles" data-title="Planteles" data-breadcrumb="PLANTELES">Planteles</li>
                <?php if (function_exists('aula_puede_gestionar') && aula_puede_gestionar()): ?>
                <li data-seccion="admin_mapa_plantel" data-title="Mapa de aulas" data-breadcrumb="MAPA AULAS">Mapa de aulas</li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (rbac_cap('admin_catalogo')): ?>
                <li data-seccion="admin_especialidades" data-title="Especialidades" data-breadcrumb="ESPECIALIDADES">Especialidades</li>
                <li data-seccion="admin_productos" data-title="Productos" data-breadcrumb="PRODUCTOS">Productos</li>
                <?php endif; ?>
                <?php if (function_exists('curso_personalizado_puede_gestionar') && curso_personalizado_puede_gestionar()): ?>
                <li data-seccion="curso_personalizado_admin" data-title="Cursos personalizados" data-breadcrumb="CURSOS PERSONALIZADOS">Cursos personalizados</li>
                <?php endif; ?>
                <?php if (function_exists('combo_puede_administrar') && combo_puede_administrar()): ?>
                <li data-seccion="admin_colegiatura_combos" data-title="Colegiaturas con descuento" data-breadcrumb="COLEGIATURAS DESCUENTO">Colegiaturas con descuento</li>
                <?php if (function_exists('ubicacion_examen_puede_administrar') && ubicacion_examen_puede_administrar()): ?>
                <li data-seccion="admin_ubicacion_examenes" data-title="Exámenes ubicación Moodle" data-breadcrumb="EXÁMENES UBICACIÓN">Exámenes ubicación (Moodle)</li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (function_exists('acuerdo_escolar_puede_publicar') && acuerdo_escolar_puede_publicar()): ?>
                <li data-seccion="supervisor_acuerdo_escolar" data-title="Acuerdo escolar" data-breadcrumb="ACUERDO ESCOLAR">Acuerdo escolar</li>
                <?php endif; ?>
            </ul>
            <ul class="flyout-list">
                <?php if (rbac_cap('menu_calendario_consulta')): ?>
                <li data-seccion="calendario_consulta" data-title="Vista combinada" data-breadcrumb="CALENDARIO INSTITUCIONAL">Vista combinada (todas las áreas)</li>
                <?php endif; ?>
                <?php if (rbac_cap('menu_calendario')): ?>
                <li data-seccion="admin_calendario" data-title="Calendarios escolares" data-breadcrumb="CALENDARIOS ESCOLARES">Editar calendarios escolares</li>
                <?php endif; ?>
                <?php if (rbac_cap('menu_calendario_admin')): ?>
                <li data-seccion="admin_calendario_admin" data-title="Calendario administrativo" data-breadcrumb="CALENDARIO ADMIN">Calendario administrativo</li>
                <?php endif; ?>
                <?php if (function_exists('marketing_puede_administrar') && marketing_puede_administrar()): ?>
                <li data-seccion="admin_marketing_banners" data-title="Banners portal alumno" data-breadcrumb="BANNERS ALUMNO">Banners portal alumno</li>
                <?php endif; ?>
                <?php if (function_exists('nomina_puede_gestionar') && nomina_puede_gestionar()): ?>
                <li data-seccion="director_nomina" data-title="Nómina del personal" data-breadcrumb="NÓMINA">Nómina del personal</li>
                <?php endif; ?>
                <?php if (function_exists('documento_puede_configurar_plantillas') && documento_puede_configurar_plantillas()): ?>
                <li data-seccion="admin_documento_plantillas" data-title="Plantillas documentos" data-breadcrumb="PLANTILLAS DOC">Plantillas constancias/diplomas</li>
                <?php endif; ?>
                <?php if (function_exists('operativo_piso_puede_ver') && operativo_piso_puede_ver()): ?>
                <li data-seccion="piso_operativo" data-title="Piso operativo" data-breadcrumb="PISO OPERATIVO">Piso operativo</li>
                <?php endif; ?>
                <?php if (function_exists('cola_facturacion_puede_ver') && cola_facturacion_puede_ver()): ?>
                <li data-seccion="cola_facturacion" data-title="Cola de facturación" data-breadcrumb="COLA FACTURACIÓN">Cola de facturación</li>
                <?php endif; ?>
                <?php if (function_exists('documento_puede_marcar_pagada') && documento_puede_marcar_pagada()): ?>
                <li data-seccion="constancia_recepcion" data-title="Constancias pendientes" data-breadcrumb="CONSTANCIAS">Constancias pendientes</li>
                <?php endif; ?>
                <?php if (function_exists('documento_puede_mostrador') && documento_puede_mostrador()): ?>
                <li data-seccion="documento_mostrador" data-title="Mostrador documentos" data-breadcrumb="MOSTRADOR DOC">Mostrador de documentos</li>
                <?php endif; ?>
                <li data-seccion="admin_configuracion" data-title="Configuración" data-breadcrumb="CONFIGURACIÓN">Configuración</li>
                <?php if (function_exists('legacy_migracion_puede') && legacy_migracion_puede()): ?>
                <li data-seccion="legacy_migracion" data-title="Migración legado" data-breadcrumb="MIGRACIÓN LEGADO">Asistente migración legado</li>
                <li data-seccion="legacy_mapeo" data-title="Equivalencias legado" data-breadcrumb="MAPEO LEGADO">Equivalencias legado → sistema</li>
                <li data-seccion="legacy_mapeo_grupos" data-title="Grupos: sustituir especialidad" data-breadcrumb="GRUPOS / ESP. LEGADO">Grupos: sustituir especialidad</li>
                <li data-seccion="legacy_import_admin" data-title="Importar legado" data-breadcrumb="IMPORTAR LEGADO">Importar datos legado</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_especialidades')):
        $espFlyoutItems = menu_cncm_flyout_especialidades_items($pdo);
        if (function_exists('grupo_avance_puede_gestionar') && grupo_avance_puede_gestionar()) {
            $espFlyoutItems[] = ['seccion' => 'academico_riesgo', 'title' => 'Riesgo académico', 'breadcrumb' => 'RIESGO ACADÉMICO', 'text' => 'Riesgo académico'];
        }
        if (function_exists('graduacion_puede_decidir') && graduacion_puede_decidir()) {
            $espFlyoutItems[] = ['seccion' => 'graduacion_alertas', 'title' => 'Alertas graduación', 'breadcrumb' => 'ALERTAS GRADUACIÓN', 'text' => 'Alertas graduación'];
        }
        $espFlyoutCount = count($espFlyoutItems);
        $espFlyoutUse3 = $espFlyoutCount > 12;
        if ($espFlyoutUse3) {
            $espChunk = (int) ceil($espFlyoutCount / 3);
            $espFlyoutCols = [
                array_slice($espFlyoutItems, 0, $espChunk),
                array_slice($espFlyoutItems, $espChunk, $espChunk),
                array_slice($espFlyoutItems, $espChunk * 2),
            ];
        } else {
            $espFlyoutHalf = max(1, (int) ceil($espFlyoutCount / 2));
            $espFlyoutCols = [
                array_slice($espFlyoutItems, 0, $espFlyoutHalf),
                array_slice($espFlyoutItems, $espFlyoutHalf),
            ];
        }
    ?>
    <div class="menu-flyout menu-flyout--wide<?php echo $espFlyoutUse3 ? ' menu-flyout--cols3' : ''; ?>" id="flyout-especialidades" data-flyout-id="flyout-especialidades">
        <div class="flyout-title">Especialidades</div>
        <div class="flyout-grid<?php echo $espFlyoutUse3 ? ' flyout-grid--3' : ''; ?>">
            <?php foreach ($espFlyoutCols as $espFlyoutCol): ?>
            <ul class="flyout-list">
                <?php foreach ($espFlyoutCol as $mi): ?>
                <li data-seccion="<?php echo htmlspecialchars($mi['seccion']); ?>"
                    <?php if (!empty($mi['query'])): ?>data-query="<?php echo htmlspecialchars($mi['query']); ?>"<?php endif; ?>
                    data-title="<?php echo htmlspecialchars($mi['title']); ?>"
                    data-breadcrumb="<?php echo htmlspecialchars($mi['breadcrumb']); ?>"><?php echo htmlspecialchars($mi['text']); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_grupos') || (function_exists('menu_cncm_vista_por_rol') && menu_cncm_vista_por_rol())): ?>
    <div class="menu-flyout" id="flyout-grupos-profesor" data-flyout-id="flyout-grupos-profesor">
        <div class="flyout-title">Grupos — profesor</div>
        <ul class="flyout-list">
            <li data-seccion="grupos" data-title="Ver grupos" data-breadcrumb="GRUPOS">Ver mis grupos</li>
            <li data-seccion="planeaciones" data-title="Planeaciones" data-breadcrumb="PLANEACIONES">Planeaciones</li>
            <?php if (function_exists('tutor_puede_usar') && tutor_puede_usar()): ?>
            <li data-seccion="tutor_chat" data-title="Tutor IA" data-breadcrumb="TUTOR IA">Tutor IA</li>
            <?php endif; ?>
            <li data-seccion="grupo_plan" data-title="Plan de parciales" data-breadcrumb="PLAN PARCIALES">Plan de parciales (grupo)</li>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_grupos')): ?>
    <div class="menu-flyout" id="flyout-grupos" data-flyout-id="flyout-grupos">
        <div class="flyout-title">Grupos</div>
        <ul class="flyout-list">
            <li data-seccion="grupos" data-title="Ver grupos" data-breadcrumb="GRUPOS">Ver grupos</li>
            <?php if (function_exists('grupo_apertura_puede_gestionar') && grupo_apertura_puede_gestionar()): ?>
            <li data-seccion="grupo_apertura" data-title="Apertura de grupos" data-breadcrumb="APERTURA GRUPOS">Apertura de grupos</li>
            <?php endif; ?>
            <?php if (function_exists('profesor_portal_puede_revisar_permisos') && profesor_portal_puede_revisar_permisos()): ?>
            <li data-seccion="profesor_permisos_admin" data-title="Permisos profesores" data-breadcrumb="PERMISOS PROFESORES">Permisos de profesores</li>
            <?php endif; ?>
            <li data-seccion="cronologia_grupos" data-title="Cronología de fases" data-breadcrumb="CRONOLOGÍA GRUPOS">Cronología de fases</li>
            <li data-seccion="grupo_fusion_plan" data-title="Planificación de fusiones" data-breadcrumb="FUSIONES GRUPOS">Planificación de fusiones</li>
            <li data-seccion="grupo_nuevo" data-title="Nuevo grupo" data-breadcrumb="NUEVO GRUPO">Nuevo grupo</li>
            <?php if (function_exists('supervisor_grupos_historico_puede_ver') && supervisor_grupos_historico_puede_ver()): ?>
            <li data-seccion="supervisor_grupos_historico" data-title="Carga histórica de grupos" data-breadcrumb="CARGA HISTÓRICA">Carga histórica de grupos</li>
            <?php endif; ?>
            <?php if (rbac_cap('menu_grupo_plan')): ?>
            <li data-seccion="grupo_plan" data-title="Plan de parciales" data-breadcrumb="PLAN PARCIALES">Plan de parciales (grupo)</li>
            <?php endif; ?>
            <li data-seccion="planeaciones" data-title="Planeaciones" data-breadcrumb="PLANEACIONES">Planeaciones</li>
            <?php if (function_exists('planeacion_puede_revisar') && planeacion_puede_revisar()): ?>
            <li data-seccion="planeaciones_revision" data-title="Revisar planeaciones" data-breadcrumb="REVISAR PLANEACIONES">Revisar planeaciones</li>
            <?php endif; ?>
            <?php if (function_exists('tutor_puede_usar') && tutor_puede_usar()): ?>
            <li data-seccion="tutor_chat" data-title="Tutor IA" data-breadcrumb="TUTOR IA">Tutor IA</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_asistencia')): ?>
    <div class="menu-flyout" id="flyout-asistencia" data-flyout-id="flyout-asistencia">
        <div class="flyout-title">Asistencias</div>
        <ul class="flyout-list">
            <li data-seccion="asistencia" data-title="Panel de grupos" data-breadcrumb="ASISTENCIAS">Panel de grupos / lista</li>
            <?php if (function_exists('asistencia_puede_checada') && asistencia_puede_checada()): ?>
            <li data-seccion="asistencia_checada" data-title="Registrar asistencia" data-breadcrumb="REGISTRAR ASISTENCIA">Registrar asistencia (lector)</li>
            <?php endif; ?>
            <li data-seccion="asistencia_faltantes" data-title="Rondín de asistencia" data-breadcrumb="RONDÍN ASISTENCIA">Rondín (sin huella)</li>
            <li data-seccion="asistencia_registros" data-title="Registros de checada" data-breadcrumb="REGISTROS CHECADA">Registros y correcciones</li>
            <?php if (function_exists('asistencia_puede_ver_puntualidad') && asistencia_puede_ver_puntualidad()): ?>
            <li data-seccion="asistencia_puntualidad" data-title="Puntualidad personal" data-breadcrumb="PUNTUALIDAD">Puntualidad personal</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_reportes') && !$esCoordinador && !$esProfesor): ?>
    <div class="menu-flyout menu-flyout--wide" id="flyout-reportes" data-flyout-id="flyout-reportes">
        <div class="flyout-title">Reportes</div>
        <div class="flyout-grid">
            <ul class="flyout-list">
                <li data-seccion="reporte_ventas" data-title="Reporte de Ventas" data-breadcrumb="REPORTE VENTAS">Reporte de Ventas</li>
                <li data-seccion="corte_caja" data-title="Corte de caja" data-breadcrumb="CORTE DE CAJA">Corte de caja</li>
                <li data-seccion="reporte_ventas_productos" data-title="Reporte de Ventas (Productos)" data-breadcrumb="REPORTE VENTAS PRODUCTOS">Reporte de Ventas (Productos)</li>
                <li data-seccion="reporte_vencimientos" data-title="Reporte de Vencimientos" data-breadcrumb="REPORTE VENCIMIENTOS">Reporte de Vencimientos</li>
                <li data-seccion="reporte_proyeccion" data-title="Reporte de Proyección" data-breadcrumb="REPORTE PROYECCIÓN">Reporte de Proyección</li>
            </ul>
            <ul class="flyout-list">
                <li data-seccion="reporte_apoyos_inscripcion" data-title="Apoyos a la inscripción" data-breadcrumb="REPORTE APOYOS">Reporte de Apoyos a la inscripción</li>
                <li data-seccion="reporte_programados" data-title="Reporte de Programados" data-breadcrumb="REPORTE PROGRAMADOS">Reporte de Programados</li>
                <?php if (in_array($rolFly, ['asesor', 'gerente', 'director', 'supervisor', 'admin'], true)): ?>
                <li data-seccion="reporte_inscritos" data-title="Reporte de Inscritos" data-breadcrumb="REPORTE INSCRITOS">Reporte de Inscritos</li>
                <?php endif; ?>
                <?php if (function_exists('reporte_academico_puede_ver') && reporte_academico_puede_ver() && in_array($rolFly, ['director', 'supervisor'], true)): ?>
                <li data-seccion="reporte_semanal" data-title="Reporte semanal" data-breadcrumb="REPORTE SEMANAL">Reporte semanal asistencia</li>
                <li data-seccion="reporte_resumen_academico" data-title="Resumen académico" data-breadcrumb="RESUMEN ACADÉMICO">Resumen académico por grupo</li>
                <li data-seccion="alumno_calificaciones" data-title="Calificaciones por fase" data-breadcrumb="CALIFICACIONES ALUMNO">Calificaciones por fase</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_examenes') || rbac_cap('menu_hay') || rbac_cap('hay_eval_configurar') || rbac_cap('hay_eval_gestionar') || (function_exists('menu_cncm_vista_por_rol') && menu_cncm_vista_por_rol())): ?>
    <div class="menu-flyout" id="flyout-coordinador-academico" data-flyout-id="flyout-coordinador-academico">
        <div class="flyout-title">Exámenes y evaluación</div>
        <ul class="flyout-list">
            <li data-seccion="examen_disc" data-title="DISC" data-breadcrumb="DISC">DISC</li>
            <li data-seccion="examen_generar" data-title="Generar — Inglés" data-breadcrumb="GENERAR EXAMEN">Generar examen — Inglés</li>
            <li data-seccion="examen_banco_ingles" data-title="Banco — Inglés" data-breadcrumb="BANCO INGLÉS">Banco de preguntas — Inglés</li>
            <li data-seccion="examen_calificar" data-title="Calificar — Escanear" data-breadcrumb="CALIFICAR EXAMEN">Calificar — escaneo OMR</li>
            <?php if (function_exists('hay_eval_puede_configurar') && hay_eval_puede_configurar()): ?>
            <li data-seccion="hay_config_rubrica" data-title="Configurar HAY" data-breadcrumb="CONFIG HAY">Configurar evaluación HAY</li>
            <?php endif; ?>
            <?php if (function_exists('hay_eval_puede_gestionar') && hay_eval_puede_gestionar()): ?>
            <li data-seccion="hay_evaluacion_admin" data-title="Evaluar personal" data-breadcrumb="EVAL PERSONAL">Evaluar personal</li>
            <li data-seccion="hay_matriz_admin" data-title="Matriz capacitación" data-breadcrumb="MATRIZ ADMIN">Matriz de entrenamiento (admin)</li>
            <?php endif; ?>
            <?php if (function_exists('profesor_eval_puede_gestionar') && profesor_eval_puede_gestionar()): ?>
            <li data-seccion="calificar_usuario" data-title="Evaluación 360 profesores" data-breadcrumb="EVALUACIÓN 360">Evaluación 360 profesores</li>
            <?php endif; ?>
            <?php if (function_exists('profesor_360_puede_gestionar') && profesor_360_puede_gestionar()): ?>
            <li data-seccion="profesor_360_ciclos" data-title="Ciclos evaluación 360" data-breadcrumb="CICLOS 360">Ciclos evaluación 360</li>
            <li data-seccion="docente_prospectos" data-title="Reclutamiento docente" data-breadcrumb="RECLUTAMIENTO DOCENTE">Reclutamiento docente</li>
            <li data-seccion="docente_rubricas" data-title="Rúbricas docente" data-breadcrumb="RÚBRICAS DOCENTE">Rúbricas de evaluación</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_examenes')): ?>
    <div class="menu-flyout" id="flyout-examenes" data-flyout-id="flyout-examenes">
        <div class="flyout-title">Exámenes</div>
        <ul class="flyout-list">
            <li data-seccion="examen_generar" data-title="Generar — Inglés" data-breadcrumb="GENERAR EXAMEN">Generar — Inglés</li>
            <li data-seccion="examen_banco_ingles" data-title="Banco — Inglés" data-breadcrumb="BANCO INGLÉS">Banco — Inglés</li>
            <li data-seccion="examen_calificar" data-title="Calificar — Escanear" data-breadcrumb="CALIFICAR EXAMEN">Calificar — Escanear</li>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (rbac_cap('menu_hay') || rbac_cap('hay_eval_configurar') || rbac_cap('hay_eval_gestionar')): ?>
    <div class="menu-flyout" id="flyout-hay" data-flyout-id="flyout-hay">
        <div class="flyout-title">Evaluación HAY</div>
        <ul class="flyout-list">
            <?php if (function_exists('hay_eval_puede_configurar') && hay_eval_puede_configurar()): ?>
            <li data-seccion="hay_config_rubrica" data-title="Configurar HAY" data-breadcrumb="CONFIG HAY">Configurar evaluación</li>
            <?php endif; ?>
            <?php if (function_exists('hay_eval_puede_gestionar') && hay_eval_puede_gestionar()): ?>
            <li data-seccion="hay_evaluacion_admin" data-title="Evaluar personal" data-breadcrumb="EVAL PERSONAL">Evaluar personal</li>
            <li data-seccion="hay_matriz_admin" data-title="Matriz capacitación" data-breadcrumb="MATRIZ ADMIN">Matriz de entrenamiento (admin)</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
