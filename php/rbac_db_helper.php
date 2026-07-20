<?php

/**
 * Roles y privilegios en base de datos (complementa rbac_helper.php).
 */

function rbac_db_ensure_schema(PDO $pdo): void
{
    static $enProgreso = false;
    static $completado = false;
    if ($completado || $enProgreso) {
        return;
    }
    if (defined('HAY_SKIP_SCHEMA_BOOTSTRAP') && HAY_SKIP_SCHEMA_BOOTSTRAP === true) {
        return;
    }
    if (function_exists('hay_schema_ddl_habilitado') && !hay_schema_ddl_habilitado($pdo)) {
        $completado = true;
        return;
    }
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    $enProgreso = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS roles (
            id_rol INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clave VARCHAR(40) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            descripcion TEXT NULL,
            acceso_total TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1=todos los privilegios\',
            es_sistema TINYINT(1) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_rol),
            UNIQUE KEY uq_roles_clave (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS role_privilegios (
            id_rol INT UNSIGNED NOT NULL,
            privilegio VARCHAR(64) NOT NULL,
            PRIMARY KEY (id_rol, privilegio),
            KEY idx_rp_priv (privilegio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS role_planteles (
            id_rol INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            PRIMARY KEY (id_rol, id_plantel),
            KEY idx_rpl_plantel (id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS usuario_privilegios (
            id_usuario INT UNSIGNED NOT NULL,
            privilegio VARCHAR(64) NOT NULL,
            tipo ENUM(\'otorgar\',\'denegar\') NOT NULL DEFAULT \'otorgar\',
            vigente_hasta DATE NULL,
            motivo VARCHAR(255) NULL,
            id_usuario_otorga INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_usuario, privilegio),
            KEY idx_up_priv (privilegio),
            KEY idx_up_vigencia (vigente_hasta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS usuario_planteles (
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            vigente_hasta DATE NULL,
            motivo VARCHAR(255) NULL,
            id_usuario_otorga INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_usuario, id_plantel),
            KEY idx_upl_plantel (id_plantel),
            KEY idx_upl_vigencia (vigente_hasta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    plantel_ensure_column($pdo, 'usuarios', 'id_rol', 'INT UNSIGNED NULL', 'rol');
    plantel_ensure_column(
        $pdo,
        'roles',
        'alcance_planteles',
        "VARCHAR(20) NOT NULL DEFAULT 'solo_usuario' COMMENT 'solo_usuario|lista|todos'",
        'acceso_total'
    );
    plantel_ensure_column($pdo, 'roles', 'departamento_default', 'VARCHAR(40) NULL', 'alcance_planteles');

    rbac_db_seed_roles($pdo);
    rbac_db_seed_alcance_planteles($pdo);
    rbac_db_seed_departamentos_roles($pdo);
    rbac_db_reparar_roles_sistema($pdo);
    try {
        $pdo->exec(
            "UPDATE usuarios SET codigo_huella = CAST(id_usuario AS CHAR)
             WHERE rol <> 'alumno' AND (codigo_huella IS NULL OR codigo_huella = '')"
        );
    } catch (PDOException $e) {
        // columna ausente en instalaciones muy antiguas
    }
    $completado = true;
    $enProgreso = false;
}

/** Alcance de sedes: solo_usuario = plantel asignado al usuario; lista = role_planteles; todos = todas las sedes. */
function rbac_alcance_planteles_opciones(): array
{
    return [
        'solo_usuario' => 'Solo el plantel del usuario',
        'lista' => 'Sedes seleccionadas (lista)',
        'todos' => 'Todas las sedes activas',
    ];
}

/** Catálogo de privilegios (clave => etiqueta, grupo). */
function rbac_privilegios_catalogo(): array
{
    return [
        'menu_ventas' => ['label' => 'Menú ventas y captación', 'grupo' => 'Ventas'],
        'menu_caja' => ['label' => 'Menú caja y cobranza', 'grupo' => 'Caja'],
        'menu_academico' => ['label' => 'Menú académico', 'grupo' => 'Académico'],
        'menu_preregistro' => ['label' => 'Pre-registro alumnos', 'grupo' => 'Ventas y alumnos'],
        'menu_entrevistas' => ['label' => 'Entrevistas (asesor)', 'grupo' => 'Ventas y alumnos'],
        'menu_grupos_fases' => ['label' => 'Grupos por fase', 'grupo' => 'Ventas y alumnos'],
        'menu_ubicacion_asesor' => ['label' => 'Ubicación (asesor)', 'grupo' => 'Ventas y alumnos'],
        'menu_cert_preregistro' => ['label' => 'Cert. pre-registro', 'grupo' => 'Ventas y alumnos'],
        'menu_reporte_inscritos' => ['label' => 'Reporte de inscritos', 'grupo' => 'Ventas y alumnos'],
        'menu_reporte_presentados' => ['label' => 'Reporte de presentados', 'grupo' => 'Ventas y alumnos'],
        'menu_asesor_preinicio' => ['label' => 'Contacto pre-inicio (asesor)', 'grupo' => 'Ventas y alumnos'],
        'menu_comisiones_consulta' => ['label' => 'Consulta de comisiones', 'grupo' => 'Ventas y alumnos'],
        'menu_comisiones_admin' => ['label' => 'Administrar comisiones', 'grupo' => 'Ventas y alumnos'],
        'menu_alumnos' => ['label' => 'Alumnos', 'grupo' => 'Ventas y alumnos'],
        'menu_consulta_adeudo' => ['label' => 'Consulta de adeudo', 'grupo' => 'Ventas y alumnos'],
        'menu_asistencia' => ['label' => 'Menú asistencias', 'grupo' => 'Académico'],
        'menu_grupos' => ['label' => 'Grupos', 'grupo' => 'Académico'],
        'menu_gestion_aulas' => ['label' => 'Catálogo de aulas', 'grupo' => 'Académico'],
        'menu_rol_aulas_gestionar' => ['label' => 'Gestionar rol de aulas', 'grupo' => 'Académico'],
        'menu_rol_aulas_consulta' => ['label' => 'Consultar rol de aulas', 'grupo' => 'Académico'],
        'menu_grupo_plan' => ['label' => 'Plan de parciales', 'grupo' => 'Académico'],
        'menu_especialidades' => ['label' => 'Especialidades / fases', 'grupo' => 'Académico'],
        'menu_examenes' => ['label' => 'Exámenes', 'grupo' => 'Académico'],
        'menu_hay' => ['label' => 'Programa HAY', 'grupo' => 'Académico'],
        'hay_eval_configurar' => ['label' => 'Configurar evaluación HAY', 'grupo' => 'Personal HAY'],
        'hay_eval_gestionar' => ['label' => 'Evaluar personal HAY', 'grupo' => 'Personal HAY'],
        'hay_matriz_marcar' => ['label' => 'Marcar capacitaciones HAY', 'grupo' => 'Personal HAY'],
        'menu_matriz_entrenamiento' => ['label' => 'Matriz de entrenamiento', 'grupo' => 'Personal HAY'],
        'menu_asesorias' => ['label' => 'Disponibilidad asesorías', 'grupo' => 'Académico'],
        'asesoria_agendar' => ['label' => 'Agendar asesoría', 'grupo' => 'Académico'],
        'asesoria_agenda_dia' => ['label' => 'Agenda asesorías del día', 'grupo' => 'Académico'],
        'asesoria_tabulador' => ['label' => 'Tabulador asesorías', 'grupo' => 'Académico'],
        'asesoria_calendario' => ['label' => 'Calendario asesorías', 'grupo' => 'Académico'],
        'asesoria_autorizar_mismo_dia' => ['label' => 'Autorizar asesoría mismo día', 'grupo' => 'Académico'],
        'menu_calendario_consulta' => ['label' => 'Calendario institucional (consulta)', 'grupo' => 'Calendario'],
        'menu_calendario' => ['label' => 'Calendarios escolares (edición)', 'grupo' => 'Calendario'],
        'menu_calendario_admin' => ['label' => 'Calendario administrativo', 'grupo' => 'Calendario'],
        'menu_punto_venta' => ['label' => 'Punto de venta', 'grupo' => 'Caja'],
        'menu_venta_productos' => ['label' => 'Venta de productos', 'grupo' => 'Caja'],
        'menu_certificaciones' => ['label' => 'Certificaciones', 'grupo' => 'Caja'],
        'menu_reportes' => ['label' => 'Reportes', 'grupo' => 'Reportes'],
        'menu_gerente_dashboard' => ['label' => 'Panel gerente ventas', 'grupo' => 'Ventas'],
        'menu_gerente_reportes' => ['label' => 'Reportes captación (gerente)', 'grupo' => 'Ventas'],
        'menu_gerente_proyeccion' => ['label' => 'Proyección captación (gerente)', 'grupo' => 'Ventas'],
        'menu_gerente_pendientes' => ['label' => 'Pendientes del plantel', 'grupo' => 'Ventas'],
        'menu_gerente_perdidos' => ['label' => 'Reporte no inscritos (gerente)', 'grupo' => 'Ventas'],
        'menu_gerente_hay' => ['label' => 'Portal HAY gerente', 'grupo' => 'Personal HAY'],
        'menu_gerente_matriz' => ['label' => 'Matriz equipo (gerente)', 'grupo' => 'Personal HAY'],
        'menu_gerente_cartas' => ['label' => 'Designar cartas (nómina)', 'grupo' => 'Ventas'],
        'menu_gerente_escuelas' => ['label' => 'Catálogo escuelas (cartas)', 'grupo' => 'Ventas'],
        'menu_reporte_escuelas' => ['label' => 'Reporte escuelas / cartas', 'grupo' => 'Ventas'],
        'menu_podio_ventas' => ['label' => 'Podio de asesores', 'grupo' => 'Ventas'],
        'menu_alumno_portal' => ['label' => 'Portal alumno (inicio)', 'grupo' => 'Portal alumno'],
        'menu_alumno_calificaciones' => ['label' => 'Alumno: mis calificaciones', 'grupo' => 'Portal alumno'],
        'menu_alumno_promociones' => ['label' => 'Alumno: promociones', 'grupo' => 'Portal alumno'],
        'menu_alumno_cursos' => ['label' => 'Alumno: cursos Moodle', 'grupo' => 'Portal alumno'],
        'menu_alumno_chat' => ['label' => 'Alumno: mensajes / chat', 'grupo' => 'Portal alumno'],
        'menu_alumno_perfil' => ['label' => 'Alumno: mi perfil', 'grupo' => 'Portal alumno'],
        'menu_alumno_cuentas' => ['label' => 'Alumno: cuentas digitales', 'grupo' => 'Portal alumno'],
        'menu_tutor' => ['label' => 'Tutor académico IA', 'grupo' => 'Académico'],
        'tutor_usar' => ['label' => 'Usar tutor académico', 'grupo' => 'Académico'],
        'tutor_administrar' => ['label' => 'Administrar tutores IA', 'grupo' => 'Académico'],
        'menu_marketing_banners' => ['label' => 'Marketing: banners alumno', 'grupo' => 'Administración'],
        'menu_admin' => ['label' => 'Menú administración', 'grupo' => 'Administración'],
        'menu_mi_evaluacion' => ['label' => 'Mi evaluación / calificaciones', 'grupo' => 'Personal'],
        'menu_alumno_estado_cuenta' => ['label' => 'Alumno: estado de cuenta / pagos', 'grupo' => 'Portal alumno'],
        'menu_soporte' => ['label' => 'Soporte técnico', 'grupo' => 'Personal'],
        'reporte_academico_ver' => ['label' => 'Reportes académicos', 'grupo' => 'Académico'],
        'catalogo_editar_costos' => ['label' => 'Editar tarifas en catálogo (supervisión)', 'grupo' => 'Solo supervisión'],
        'inscripcion_autorizar_edad' => ['label' => 'Autorizar inscripción por edad', 'grupo' => 'Académico'],
        'inscripcion_autorizar_ubicacion' => ['label' => 'Autorizar inscripción por ubicación', 'grupo' => 'Académico'],
        'grupo_autorizar_apertura' => ['label' => 'Autorizar o posponer apertura de grupos', 'grupo' => 'Académico'],
        'inscripcion_solicitar_autorizacion' => ['label' => 'Solicitar autorización de inscripción', 'grupo' => 'Ventas'],
        'descuento_inscripcion_asesor' => ['label' => 'Descuento inscripción (asesor)', 'grupo' => 'Ventas'],
        'descuento_inscripcion_gerente' => ['label' => 'Descuento inscripción (gerente ventas)', 'grupo' => 'Ventas'],
        'descuento_inscripcion_director' => ['label' => 'Descuento inscripción (director)', 'grupo' => 'Caja'],
        'cartas_definir_minimo' => ['label' => 'Definir mínimo promoción cartas', 'grupo' => 'Ventas'],
        'cartas_excepcion_minimo' => ['label' => 'Excepción mínimo cartas (supervisión)', 'grupo' => 'Solo supervisión'],
        'cobro_precio_lista' => ['label' => 'Cobrar precio referencia (sin apoyo)', 'grupo' => 'Caja'],
        'ticket_apoyo_educativo' => ['label' => 'Ticket con desglose apoyo educativo', 'grupo' => 'Caja'],
        'curso_personalizado_gestionar' => ['label' => 'Cursos personalizados', 'grupo' => 'Caja'],
        'calificaciones_editar_coordinacion' => ['label' => 'Editar calificaciones (coordinación)', 'grupo' => 'Académico'],
        'planeaciones_revisar' => ['label' => 'Revisar planeaciones', 'grupo' => 'Académico'],
        'profesor_contratar' => ['label' => 'Contratar profesores', 'grupo' => 'Académico'],
        'permiso_docente_proponer' => ['label' => 'Proponer permisos docentes', 'grupo' => 'Académico'],
        'permiso_docente_aprobar_final' => ['label' => 'Aprobar permisos docentes', 'grupo' => 'Administración'],
        'admin_catalogo' => ['label' => 'Catálogo (esp. y productos)', 'grupo' => 'Administración'],
        'admin_colegiaturas_descuento' => ['label' => 'Colegiaturas con descuento', 'grupo' => 'Administración'],
        'menu_supervisor_acuerdo' => ['label' => 'Acuerdo escolar (supervisión)', 'grupo' => 'Administración'],
        'pago_supervisor_editar' => ['label' => 'Anular/editar pagos (supervisión)', 'grupo' => 'Caja'],
        'menu_reporte_pagos_anulados' => ['label' => 'Reporte pagos anulados', 'grupo' => 'Reportes'],
        'admin_usuarios' => ['label' => 'Usuarios del personal', 'grupo' => 'Administración'],
        'admin_planteles' => ['label' => 'Planteles', 'grupo' => 'Administración'],
        'admin_roles' => ['label' => 'Roles y privilegios', 'grupo' => 'Administración'],
        'seed_datos' => ['label' => 'Semillas / datos de prueba', 'grupo' => 'Administración'],
        'asistencia_lista_grupo' => ['label' => 'Lista de asistencia por grupo', 'grupo' => 'Asistencia'],
        'asistencia_movil' => ['label' => 'Rondín móvil', 'grupo' => 'Asistencia'],
        'asistencia_puntualidad' => ['label' => 'Puntualidad personal', 'grupo' => 'Asistencia'],
        'asistencia_personal_manual' => ['label' => 'Asistencia manual personal', 'grupo' => 'Asistencia'],
        'asistencia_config_huella' => ['label' => 'Configuración huella', 'grupo' => 'Asistencia'],
        'asistencia_checada' => ['label' => 'Registrar checada', 'grupo' => 'Asistencia'],
        'asistencia_eliminar_registro' => ['label' => 'Eliminar registro asistencia', 'grupo' => 'Asistencia'],
        'menu_mi_expediente' => ['label' => 'Mi expediente documental', 'grupo' => 'Personal'],
        'expediente_requisitos_admin' => ['label' => 'Configurar requisitos documentales', 'grupo' => 'Administración'],
        'expediente_consultar' => ['label' => 'Consultar expedientes documentales', 'grupo' => 'Académico'],
        'expediente_evaluar' => ['label' => 'Evaluar documentos / certificaciones', 'grupo' => 'Académico'],
    ];
}

/** Mapa legacy rol => privilegios (para semilla inicial). */
function rbac_db_mapa_legacy(): array
{
    return function_exists('rbac_db_mapa_jerarquia')
        ? rbac_db_mapa_jerarquia()
        : [];
}

function rbac_db_seed_roles(PDO $pdo): void
{
    rbac_db_ensure_schema($pdo);
    $labels = rbac_roles_etiquetas();
    $map = rbac_db_mapa_legacy();
    $orden = 0;

    foreach ($labels as $clave => $nombre) {
        $st = $pdo->prepare('SELECT id_rol FROM roles WHERE clave = ? LIMIT 1');
        $st->execute([$clave]);
        $idRol = (int) ($st->fetchColumn() ?: 0);
        $accesoTotal = $clave === 'supervisor' ? 1 : 0;

        if ($idRol <= 0) {
            $pdo->prepare(
                'INSERT INTO roles (clave, nombre, descripcion, acceso_total, es_sistema, activo, orden)
                 VALUES (?,?,?,?,1,1,?)'
            )->execute([
                $clave,
                $nombre,
                'Rol del sistema',
                $accesoTotal,
                $orden++,
            ]);
            $idRol = (int) $pdo->lastInsertId();
        }

        $privs = $map[$clave] ?? [];
        if (!empty($privs['__acceso_total__'])) {
            continue;
        }
        $existentes = $pdo->prepare('SELECT privilegio FROM role_privilegios WHERE id_rol = ?');
        $existentes->execute([$idRol]);
        $ya = $existentes->fetchAll(PDO::FETCH_COLUMN);
        if ($ya !== []) {
            continue;
        }
        $ins = $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
        foreach ($privs as $p) {
            $ins->execute([$idRol, $p]);
        }
    }

    rbac_db_sync_usuarios_id_rol($pdo);
}

/** Departamento institucional sugerido por rol (calendario / notificaciones). */
function rbac_departamento_para_rol(PDO $pdo, string $claveRol, int $idRol = 0): string
{
    $claveRol = strtolower(trim($claveRol));
    if ($idRol > 0) {
        $rol = rbac_rol_por_id($pdo, $idRol);
        $dep = trim((string) ($rol['departamento_default'] ?? ''));
        if ($dep !== '') {
            return $dep;
        }
    }
    $rol = rbac_rol_por_clave($pdo, $claveRol);
    $dep = trim((string) ($rol['departamento_default'] ?? ''));
    if ($dep !== '') {
        return $dep;
    }

    return match ($claveRol) {
        'asesor' => 'ventas',
        'profesor' => 'ingles',
        'admin', 'gerente', 'supervisor' => 'administrativo',
        default => 'administrativo',
    };
}

function rbac_db_seed_departamentos_roles(PDO $pdo): void
{
    $map = [
        'supervisor' => 'administrativo',
        'director' => 'administrativo',
        'gerente' => 'ventas',
        'coordinador' => 'ingles',
        'admin' => 'administrativo',
        'asesor' => 'ventas',
        'profesor' => 'ingles',
        'alumno' => 'administrativo',
    ];
    $st = $pdo->prepare(
        "UPDATE roles SET departamento_default = ? WHERE clave = ? AND (departamento_default IS NULL OR departamento_default = '')"
    );
    foreach ($map as $clave => $dep) {
        $st->execute([$dep, $clave]);
    }
}

/** Semilla alcance de planteles en roles del sistema (solo si aún no se personalizó). */
function rbac_db_seed_alcance_planteles(PDO $pdo): void
{
    $defaults = [
        'supervisor' => 'todos',
        'director' => 'solo_usuario',
        'gerente' => 'todos',
        'coordinador' => 'solo_usuario',
        'admin' => 'solo_usuario',
        'asesor' => 'solo_usuario',
        'profesor' => 'solo_usuario',
        'alumno' => 'solo_usuario',
    ];
    $up = $pdo->prepare('UPDATE roles SET alcance_planteles = ? WHERE id_rol = ?');
    foreach ($defaults as $clave => $alcance) {
        $rol = rbac_rol_por_clave($pdo, $clave);
        if (!$rol) {
            continue;
        }
        $actual = (string) ($rol['alcance_planteles'] ?? '');
        if ($actual !== '' && $actual !== 'solo_usuario') {
            continue;
        }
        if (!in_array($clave, ['supervisor', 'gerente', 'admin', 'asesor', 'profesor', 'alumno'], true) && $actual !== '') {
            continue;
        }
        $up->execute([$alcance, (int) $rol['id_rol']]);
    }
}

/** Agrega privilegios al rol sin quitar los existentes (útil tras ampliar el mapa legacy). */
function rbac_db_agregar_privilegios_rol(PDO $pdo, string $claveRol, array $privilegios): void
{
    $rol = rbac_rol_por_clave($pdo, $claveRol);
    if (!$rol) {
        return;
    }
    $ins = $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
    foreach ($privilegios as $p) {
        $p = trim((string) $p);
        if ($p !== '') {
            $ins->execute([(int) $rol['id_rol'], $p]);
        }
    }
}

/** Supervisor: acceso_total en roles + todos los privilegios del catálogo en role_privilegios. */
function rbac_db_reparar_supervisor(PDO $pdo): void
{
    if (!rbac_db_tablas_listas($pdo)) {
        return;
    }
    $super = rbac_rol_por_clave($pdo, 'supervisor');
    if (!$super) {
        return;
    }
    $idRol = (int) $super['id_rol'];
    $pdo->prepare('UPDATE roles SET acceso_total = 1, alcance_planteles = ? WHERE id_rol = ?')
        ->execute(['todos', $idRol]);

    $chk = $pdo->prepare(
        'SELECT 1 FROM role_privilegios WHERE id_rol = ? AND privilegio = ? LIMIT 1'
    );
    $chk->execute([$idRol, 'menu_preregistro']);
    if ($chk->fetchColumn()) {
        return;
    }

    $ins = $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
    foreach (array_keys(rbac_privilegios_catalogo()) as $priv) {
        $ins->execute([$idRol, $priv]);
    }
}

/** Quita privilegios incorrectos en roles del sistema (p. ej. asesor sin checada ni adeudo). */
function rbac_db_reparar_roles_sistema(PDO $pdo): void
{
    if (function_exists('rbac_db_sincronizar_jerarquia_roles')) {
        rbac_db_sincronizar_jerarquia_roles($pdo);
    }

    $quitarAsesor = [
        'menu_consulta_adeudo', 'menu_alumnos', 'menu_punto_venta', 'menu_asistencia',
        'menu_grupos', 'admin_roles', 'admin_planteles', 'catalogo_editar_costos',
    ];
    $rol = rbac_rol_por_clave($pdo, 'asesor');
    if ($rol) {
        $del = $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ? AND privilegio = ?');
        foreach ($quitarAsesor as $p) {
            $del->execute([(int) $rol['id_rol'], $p]);
        }
    }

    $quitarCoord = [
        'menu_preregistro', 'menu_entrevistas', 'menu_grupos_fases', 'menu_ubicacion_asesor',
        'menu_cert_preregistro', 'menu_reporte_inscritos', 'menu_comisiones_consulta', 'menu_comisiones_admin',
        'menu_podio_ventas', 'menu_asesor_preinicio', 'menu_ventas', 'menu_caja', 'menu_consulta_adeudo',
        'menu_punto_venta', 'menu_venta_productos', 'menu_reportes', 'descuento_inscripcion_asesor',
    ];
    $coord = rbac_rol_por_clave($pdo, 'coordinador');
    if ($coord) {
        $delC = $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ? AND privilegio = ?');
        foreach ($quitarCoord as $p) {
            $delC->execute([(int) $coord['id_rol'], $p]);
        }
        $insC = $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
        foreach (['menu_examenes', 'menu_hay', 'hay_eval_gestionar'] as $p) {
            $insC->execute([(int) $coord['id_rol'], $p]);
        }
    }

    $quitarAdmin = [
        'menu_preregistro', 'menu_entrevistas', 'menu_grupos_fases', 'menu_ubicacion_asesor',
        'menu_cert_preregistro', 'menu_reporte_inscritos', 'menu_comisiones_consulta', 'menu_comisiones_admin',
        'menu_podio_ventas', 'menu_asesor_preinicio', 'menu_ventas', 'menu_academico', 'menu_grupos',
        'menu_especialidades', 'menu_asistencia', 'menu_examenes', 'menu_hay', 'hay_eval_gestionar',
        'hay_eval_configurar', 'menu_gerente_dashboard', 'menu_admin',
    ];
    $admin = rbac_rol_por_clave($pdo, 'admin');
    if ($admin) {
        $delA = $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ? AND privilegio = ?');
        foreach ($quitarAdmin as $p) {
            $delA->execute([(int) $admin['id_rol'], $p]);
        }
        $insA = $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
        foreach (rbac_cap_recepcion() as $p) {
            $insA->execute([(int) $admin['id_rol'], $p]);
        }
    }

    $prof = rbac_rol_por_clave($pdo, 'profesor');
    if ($prof) {
        $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ? AND privilegio = ?')
            ->execute([(int) $prof['id_rol'], 'menu_hay']);
        $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ? AND privilegio = ?')
            ->execute([(int) $prof['id_rol'], 'hay_eval_gestionar']);
    }

    foreach (['director', 'gerente', 'coordinador', 'admin'] as $claveRol) {
        $r = rbac_rol_por_clave($pdo, $claveRol);
        if (!$r) {
            continue;
        }
        foreach (rbac_cap_solo_supervisor() as $p) {
            $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ? AND privilegio = ?')
                ->execute([(int) $r['id_rol'], $p]);
        }
    }

    $super = rbac_rol_por_clave($pdo, 'supervisor');
    if ($super) {
        $pdo->prepare('UPDATE roles SET acceso_total = 1, alcance_planteles = ? WHERE id_rol = ?')
            ->execute(['todos', (int) $super['id_rol']]);
        foreach (rbac_cap_solo_supervisor() as $p) {
            $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)')
                ->execute([(int) $super['id_rol'], $p]);
        }
        $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)')
            ->execute([(int) $super['id_rol'], 'admin_colegiaturas_descuento']);
    }

    $gerente = rbac_rol_por_clave($pdo, 'gerente');
    if ($gerente) {
        $idGerente = (int) $gerente['id_rol'];
        $quitarGerente = ['menu_reportes', 'menu_gerente_proyeccion', 'hay_eval_configurar'];
        $agregarGerente = ['hay_eval_gestionar'];
        $delG = $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ? AND privilegio = ?');
        foreach ($quitarGerente as $p) {
            $delG->execute([$idGerente, $p]);
        }
        $insG = $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
        foreach ($agregarGerente as $p) {
            $insG->execute([$idGerente, $p]);
        }
    }
}

/** @return list<int> */
function rbac_rol_planteles_ids(PDO $pdo, int $idRol): array
{
    if ($idRol <= 0) {
        return [];
    }
    $st = $pdo->prepare('SELECT id_plantel FROM role_planteles WHERE id_rol = ? ORDER BY id_plantel');
    $st->execute([$idRol]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function rbac_rol_guardar_planteles(PDO $pdo, int $idRol, string $alcance, array $idsPlantel): void
{
    $pdo->prepare('UPDATE roles SET alcance_planteles = ? WHERE id_rol = ?')
        ->execute([$alcance, $idRol]);
    $pdo->prepare('DELETE FROM role_planteles WHERE id_rol = ?')->execute([$idRol]);
    if ($alcance !== 'lista') {
        return;
    }
    $ins = $pdo->prepare('INSERT IGNORE INTO role_planteles (id_rol, id_plantel) VALUES (?,?)');
    foreach (array_unique(array_map('intval', $idsPlantel)) as $idPl) {
        if ($idPl > 0) {
            $ins->execute([$idRol, $idPl]);
        }
    }
}

function rbac_db_sync_usuarios_id_rol(PDO $pdo): void
{
    $pdo->exec(
        'UPDATE usuarios u
         INNER JOIN roles r ON r.clave = u.rol AND r.activo = 1
         SET u.id_rol = r.id_rol
         WHERE u.rol IS NOT NULL AND u.rol != \'\' AND (u.id_rol IS NULL OR u.id_rol = 0)'
    );
}

/** @return list<array<string, mixed>> */
function rbac_roles_listar(PDO $pdo, bool $soloActivos = true): array
{
    rbac_db_ensure_schema($pdo);
    $sql = 'SELECT * FROM roles';
    if ($soloActivos) {
        $sql .= ' WHERE activo = 1';
    }
    $sql .= ' ORDER BY orden, nombre';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function rbac_rol_por_clave(PDO $pdo, string $clave): ?array
{
    $clave = strtolower(trim($clave));
    if ($clave === '') {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM roles WHERE clave = ? LIMIT 1');
    $st->execute([$clave]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    return $r ?: null;
}

/** @return array<string, mixed>|null */
function rbac_rol_por_id(PDO $pdo, int $idRol): ?array
{
    if ($idRol <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM roles WHERE id_rol = ? LIMIT 1');
    $st->execute([$idRol]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    return $r ?: null;
}

/** @return list<string> */
function rbac_rol_privilegios(PDO $pdo, int $idRol): array
{
    $st = $pdo->prepare('SELECT privilegio FROM role_privilegios WHERE id_rol = ? ORDER BY privilegio');
    $st->execute([$idRol]);

    return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/** Indica si las tablas RBAC ya existen (sin ejecutar migraciones). */
function rbac_db_tablas_listas(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo->query('SELECT 1 FROM roles LIMIT 1');
        $cache = true;
    } catch (Throwable $e) {
        $cache = false;
    }

    return $cache;
}

/** Carga privilegios del rol en sesión (tras login). */
function rbac_db_cargar_sesion_rol(PDO $pdo, string $claveRol): void
{
    $claveRol = function_exists('rbac_normalizar_rol_clave')
        ? rbac_normalizar_rol_clave($claveRol)
        : strtolower(trim($claveRol));
    if (!rbac_db_tablas_listas($pdo)) {
        $_SESSION['rbac_caps'] = [];
        $_SESSION['rbac_acceso_total'] = ($claveRol === 'supervisor') ? 1 : 0;
        $_SESSION['rbac_alcance_planteles'] = $claveRol === 'supervisor' ? 'todos' : 'solo_usuario';
        $_SESSION['rbac_planteles_ids'] = [];
        rbac_usuario_privilegios_cargar($pdo, (int) ($_SESSION['user_id'] ?? 0));

        return;
    }
    $rol = rbac_rol_por_clave($pdo, $claveRol);
    if (!$rol) {
        $_SESSION['rbac_caps'] = function_exists('rbac_jerarquia_privilegios_fallback')
            ? rbac_jerarquia_privilegios_fallback($claveRol)
            : [];
        $_SESSION['rbac_acceso_total'] = ($claveRol === 'supervisor') ? 1 : 0;
        $_SESSION['rbac_alcance_planteles'] = $claveRol === 'supervisor' ? 'todos' : 'solo_usuario';
        rbac_usuario_privilegios_cargar($pdo, (int) ($_SESSION['user_id'] ?? 0));
        return;
    }
    $esSupervisor = $claveRol === 'supervisor' || (int) ($rol['acceso_total'] ?? 0) === 1;
    $_SESSION['rbac_acceso_total'] = $esSupervisor ? 1 : (int) ($rol['acceso_total'] ?? 0);
    $_SESSION['rbac_alcance_planteles'] = $esSupervisor
        ? 'todos'
        : (string) ($rol['alcance_planteles'] ?? 'solo_usuario');
    $_SESSION['rbac_planteles_ids'] = rbac_rol_planteles_ids($pdo, (int) $rol['id_rol']);
    if ($_SESSION['rbac_acceso_total']) {
        $_SESSION['rbac_caps'] = array_keys(rbac_privilegios_catalogo());
        $_SESSION['rbac_alcance_planteles'] = 'todos';
        rbac_usuario_privilegios_cargar($pdo, (int) ($_SESSION['user_id'] ?? 0));
        return;
    }
    $caps = rbac_rol_privilegios($pdo, (int) $rol['id_rol']);
    if (function_exists('rbac_jerarquia_privilegios_fallback')) {
        $fallback = rbac_jerarquia_privilegios_fallback($claveRol);
        if ($fallback !== []) {
            $caps = array_values(array_unique(array_merge($fallback, $caps)));
        } elseif ($caps === []) {
            $caps = $fallback;
        }
    }
    $_SESSION['rbac_caps'] = $caps;
    if ($claveRol === 'supervisor') {
        $_SESSION['rbac_alcance_planteles'] = 'todos';
    }
    rbac_usuario_privilegios_cargar($pdo, (int) ($_SESSION['user_id'] ?? 0));
}

/** Carga excepciones por usuario (otorgar / denegar) en sesión. */
function rbac_usuario_privilegios_cargar(PDO $pdo, int $idUsuario): void
{
    $_SESSION['rbac_usuario_otorga'] = [];
    $_SESSION['rbac_usuario_deniega'] = [];
    if ($idUsuario <= 0 || !rbac_db_tablas_listas($pdo)) {
        return;
    }
    try {
        $st = $pdo->prepare(
            "SELECT privilegio, tipo FROM usuario_privilegios
             WHERE id_usuario = ? AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())"
        );
        $st->execute([$idUsuario]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $p = (string) ($row['privilegio'] ?? '');
            if ($p === '') {
                continue;
            }
            if (($row['tipo'] ?? '') === 'denegar') {
                $_SESSION['rbac_usuario_deniega'][] = $p;
            } else {
                $_SESSION['rbac_usuario_otorga'][] = $p;
            }
        }
    } catch (PDOException $e) {
        // tabla aún no existe
    }
}

/** null = sin excepción; true/false = otorgado o denegado explícitamente. */
function rbac_usuario_cap_override(string $cap): ?bool
{
    if (in_array($cap, $_SESSION['rbac_usuario_deniega'] ?? [], true)) {
        return false;
    }
    if (in_array($cap, $_SESSION['rbac_usuario_otorga'] ?? [], true)) {
        return true;
    }

    return null;
}

/** @return list<array<string, mixed>> */
function rbac_usuario_privilegios_listar(PDO $pdo, int $idUsuario): array
{
    rbac_db_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM usuario_privilegios WHERE id_usuario = ? ORDER BY privilegio'
    );
    $st->execute([$idUsuario]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rbac_usuario_puede_gestionar_privilegios(): bool
{
    if (rbac_rol_real() === 'supervisor') {
        return true;
    }

    return rbac_cap('admin_usuarios');
}

/** Privilegios que solo la supervisión puede asignar a personas. */
function rbac_privilegios_restringidos_asignacion(): array
{
    return rbac_cap_solo_supervisor();
}

/** @return array{ok:bool,message:string} */
function rbac_usuario_privilegios_guardar(PDO $pdo, int $idUsuario, array $items): array
{
    rbac_db_ensure_schema($pdo);
    if ($idUsuario <= 0) {
        return ['ok' => false, 'message' => 'Usuario inválido'];
    }
    if (!rbac_usuario_puede_gestionar_privilegios()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }

    $catalogo = array_keys(rbac_privilegios_catalogo());
    $restringidos = rbac_privilegios_restringidos_asignacion();
    $esSupervisor = rbac_rol_real() === 'supervisor';

    $pdo->prepare('DELETE FROM usuario_privilegios WHERE id_usuario = ?')->execute([$idUsuario]);
    $ins = $pdo->prepare(
        'INSERT INTO usuario_privilegios (id_usuario, privilegio, tipo, vigente_hasta, motivo, id_usuario_otorga)
         VALUES (?,?,?,?,?,?)'
    );

    foreach ($items as $it) {
        $priv = trim((string) ($it['privilegio'] ?? ''));
        if ($priv === '' || !in_array($priv, $catalogo, true)) {
            continue;
        }
        if (!$esSupervisor && in_array($priv, $restringidos, true)) {
            continue;
        }
        $tipo = ($it['tipo'] ?? '') === 'denegar' ? 'denegar' : 'otorgar';
        $hasta = trim((string) ($it['vigente_hasta'] ?? '')) ?: null;
        $motivo = trim((string) ($it['motivo'] ?? '')) ?: null;
        $ins->execute([
            $idUsuario,
            $priv,
            $tipo,
            $hasta,
            $motivo,
            (int) ($_SESSION['user_id'] ?? 0) ?: null,
        ]);
    }

    rbac_usuario_sync_permisos_personalizados($pdo, $idUsuario);

    if ((int) ($_SESSION['user_id'] ?? 0) === $idUsuario) {
        rbac_usuario_privilegios_cargar($pdo, $idUsuario);
    }

    return ['ok' => true, 'message' => 'Privilegios personalizados guardados'];
}

/** @return list<array<string, mixed>> */
function rbac_usuario_planteles_listar(PDO $pdo, int $idUsuario): array
{
    rbac_db_ensure_schema($pdo);
    try {
        $st = $pdo->prepare(
            'SELECT up.*, p.nombre AS plantel_nombre, p.slug AS plantel_slug
             FROM usuario_planteles up
             INNER JOIN planteles p ON p.id_plantel = up.id_plantel
             WHERE up.id_usuario = ?
             ORDER BY p.orden, p.nombre'
        );
        $st->execute([$idUsuario]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/** @return array{ok:bool,message:string} */
function rbac_usuario_planteles_guardar(PDO $pdo, int $idUsuario, array $items): array
{
    rbac_db_ensure_schema($pdo);
    if ($idUsuario <= 0) {
        return ['ok' => false, 'message' => 'Usuario inválido'];
    }
    if (!rbac_usuario_puede_gestionar_privilegios()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }

    $stU = $pdo->prepare('SELECT rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $stU->execute([$idUsuario]);
    $rolUsuario = strtolower(trim((string) ($stU->fetchColumn() ?: '')));
    if (!in_array($rolUsuario, plantel_roles_con_apoyo_temporal(), true)) {
        return ['ok' => false, 'message' => 'Sedes temporales solo aplican a asesor, recepción o coordinador'];
    }

    $home = plantel_usuario_home_id($pdo, $idUsuario);
    $pdo->prepare('DELETE FROM usuario_planteles WHERE id_usuario = ?')->execute([$idUsuario]);
    $ins = $pdo->prepare(
        'INSERT INTO usuario_planteles (id_usuario, id_plantel, vigente_hasta, motivo, id_usuario_otorga)
         VALUES (?,?,?,?,?)'
    );

    foreach ($items as $it) {
        $idPl = (int) ($it['id_plantel'] ?? 0);
        if ($idPl <= 0 || $idPl === $home) {
            continue;
        }
        $p = plantel_find($pdo, $idPl);
        if (!$p || (int) $p['activo'] !== 1) {
            continue;
        }
        $hasta = trim((string) ($it['vigente_hasta'] ?? '')) ?: null;
        $motivo = trim((string) ($it['motivo'] ?? '')) ?: null;
        $ins->execute([
            $idUsuario,
            $idPl,
            $hasta,
            $motivo,
            (int) ($_SESSION['user_id'] ?? 0) ?: null,
        ]);
    }

    return ['ok' => true, 'message' => 'Sedes temporales actualizadas'];
}

function rbac_db_cap_en_sesion(string $cap): bool
{
    if (!empty($_SESSION['rbac_acceso_total'])) {
        return true;
    }
    $caps = $_SESSION['rbac_caps'] ?? null;
    if (is_array($caps)) {
        return in_array($cap, $caps, true);
    }

    return false;
}

/** Comprueba privilegio: sesión → BD → mapa legacy. */
function rbac_db_cap(PDO $pdo, string $cap, string $rolEfectivo): bool
{
    if (function_exists('rbac_normalizar_rol_clave')) {
        $rolEfectivo = rbac_normalizar_rol_clave($rolEfectivo);
    } else {
        $rolEfectivo = strtolower(trim($rolEfectivo));
    }
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if ($rolEfectivo === 'supervisor') {
        return true;
    }

    if (rbac_db_cap_en_sesion($cap)) {
        return true;
    }

    static $cache = [];
    $key = $rolEfectivo . '|' . $cap;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $rol = rbac_rol_por_clave($pdo, $rolEfectivo);
    if (!$rol) {
        $cache[$key] = false;
        return false;
    }
    if ((int) ($rol['acceso_total'] ?? 0) === 1) {
        $cache[$key] = true;
        return true;
    }

    $st = $pdo->prepare(
        'SELECT 1 FROM role_privilegios WHERE id_rol = ? AND privilegio = ? LIMIT 1'
    );
    $st->execute([(int) $rol['id_rol'], $cap]);
    $cache[$key] = (bool) $st->fetchColumn();

    return $cache[$key];
}

function rbac_puede_administrar_roles(): bool
{
    return rbac_rol_real() === 'supervisor' || rbac_rol_efectivo() === 'supervisor';
}

/** @return array{ok:bool,message:string,id_rol?:int} */
function rbac_rol_guardar(PDO $pdo, array $data): array
{
    rbac_db_ensure_schema($pdo);
    $id = (int) ($data['id_rol'] ?? 0);
    $nombre = trim((string) ($data['nombre'] ?? ''));
    $clave = strtolower(trim((string) ($data['clave'] ?? '')));
    $clave = preg_replace('/[^a-z0-9_]/', '_', $clave) ?? '';
    $descripcion = trim((string) ($data['descripcion'] ?? ''));
    $accesoTotal = !empty($data['acceso_total']) ? 1 : 0;
    $activo = !isset($data['activo']) || !empty($data['activo']) ? 1 : 0;
    $alcancePl = (string) ($data['alcance_planteles'] ?? 'solo_usuario');
    if (!array_key_exists($alcancePl, rbac_alcance_planteles_opciones())) {
        $alcancePl = 'solo_usuario';
    }
    $plantelesIds = $data['planteles'] ?? [];
    if (is_string($plantelesIds)) {
        $plantelesIds = json_decode($plantelesIds, true) ?: [];
    }
    $privilegios = $data['privilegios'] ?? [];
    if (is_string($privilegios)) {
        $privilegios = json_decode($privilegios, true) ?: [];
    }
    $catalogo = array_keys(rbac_privilegios_catalogo());
    $privilegios = array_values(array_intersect($catalogo, array_map('strval', $privilegios)));

    if ($nombre === '') {
        return ['ok' => false, 'message' => 'El nombre del rol es obligatorio'];
    }

    if ($id > 0) {
        $ant = rbac_rol_por_id($pdo, $id);
        if (!$ant) {
            return ['ok' => false, 'message' => 'Rol no encontrado'];
        }
        if ((int) ($ant['es_sistema'] ?? 0) && $clave !== '' && $clave !== $ant['clave']) {
            return ['ok' => false, 'message' => 'No se puede cambiar la clave de un rol del sistema'];
        }
        if ($clave === '') {
            $clave = $ant['clave'];
        }
        $pdo->prepare(
            'UPDATE roles SET nombre = ?, descripcion = ?, acceso_total = ?, activo = ?, alcance_planteles = ? WHERE id_rol = ?'
        )->execute([$nombre, $descripcion ?: null, $accesoTotal, $activo, $alcancePl, $id]);
    } else {
        if ($clave === '') {
            return ['ok' => false, 'message' => 'La clave del rol es obligatoria (solo letras, números y _)'];
        }
        $dup = $pdo->prepare('SELECT id_rol FROM roles WHERE clave = ? LIMIT 1');
        $dup->execute([$clave]);
        if ($dup->fetchColumn()) {
            return ['ok' => false, 'message' => 'Esa clave de rol ya existe'];
        }
        $pdo->prepare(
            'INSERT INTO roles (clave, nombre, descripcion, acceso_total, es_sistema, activo, orden, alcance_planteles)
             VALUES (?,?,?,?,0,?,999,?)'
        )->execute([$clave, $nombre, $descripcion ?: null, $accesoTotal, $activo, $alcancePl]);
        $id = (int) $pdo->lastInsertId();
    }

    if ($accesoTotal) {
        $alcancePl = 'todos';
    }
    rbac_rol_guardar_planteles($pdo, $id, $alcancePl, $plantelesIds);

    $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ?')->execute([$id]);
    if (!$accesoTotal) {
        $ins = $pdo->prepare('INSERT INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
        foreach ($privilegios as $p) {
            $ins->execute([$id, $p]);
        }
    }

    return ['ok' => true, 'message' => 'Rol guardado', 'id_rol' => $id, 'clave' => $clave];
}

/** Valida que el rol exista y esté activo; devuelve clave. */
function rbac_validar_rol_usuario(PDO $pdo, string $claveRol): ?string
{
    $rol = rbac_rol_por_clave($pdo, $claveRol);
    if (!$rol || !(int) ($rol['activo'] ?? 0)) {
        return null;
    }

    return (string) $rol['clave'];
}

/** Roles asignables al crear usuario (excluye alumno). */
function rbac_roles_para_formulario(PDO $pdo): array
{
    if (!rbac_db_tablas_listas($pdo)) {
        rbac_db_ensure_schema($pdo);
    }
    try {
        $st = $pdo->query(
            "SELECT id_rol, clave, nombre, es_sistema, departamento_default FROM roles WHERE activo = 1 AND clave != 'alumno' ORDER BY orden, nombre"
        );
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }
    if ($rows === []) {
        rbac_db_ensure_schema($pdo);
        try {
            $st = $pdo->query(
                "SELECT id_rol, clave, nombre, es_sistema, departamento_default FROM roles WHERE activo = 1 AND clave != 'alumno' ORDER BY orden, nombre"
            );
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            try {
                $st = $pdo->query(
                    "SELECT id_rol, clave, nombre, es_sistema FROM roles WHERE activo = 1 AND clave != 'alumno' ORDER BY nombre"
                );
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                $rows = [];
            }
        }
    }
    if (rbac_rol_real() !== 'supervisor') {
        $rows = array_values(array_filter($rows, static fn ($r) => ($r['clave'] ?? '') !== 'supervisor'));
    }

    return $rows;
}

/** Acceso al centro de roles y permisos (roles del sistema o personal). */
function rbac_puede_centro_permisos(): bool
{
    return rbac_puede_administrar_roles() || rbac_usuario_puede_gestionar_privilegios();
}

function rbac_usuario_ensure_permisos_flag(PDO $pdo): void
{
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'usuarios', 'permisos_personalizados', 'TINYINT(1) NOT NULL DEFAULT 0', 'id_rol');
    }
}

function rbac_usuario_tiene_permisos_personalizados(PDO $pdo, int $idUsuario): bool
{
    rbac_db_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT COUNT(*) FROM usuario_privilegios WHERE id_usuario = ?');
    $st->execute([$idUsuario]);

    return (int) $st->fetchColumn() > 0;
}

function rbac_usuario_sync_permisos_personalizados(PDO $pdo, int $idUsuario): void
{
    rbac_usuario_ensure_permisos_flag($pdo);
    $flag = rbac_usuario_tiene_permisos_personalizados($pdo, $idUsuario) ? 1 : 0;
    $pdo->prepare('UPDATE usuarios SET permisos_personalizados = ? WHERE id_usuario = ?')->execute([$flag, $idUsuario]);
}

/** @return list<array<string, mixed>> */
function rbac_personal_listar(PDO $pdo, ?int $idPlantel = null, string $q = ''): array
{
    rbac_db_ensure_schema($pdo);
    rbac_usuario_ensure_permisos_flag($pdo);
    $params = [];
    $sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.username, u.email, u.rol, u.id_rol,
                   u.suspendido, u.permisos_personalizados,
                   p.nombre AS plantel_nombre,
                   COALESCE(r.nombre, u.rol) AS rol_nombre,
                   (SELECT COUNT(*) FROM usuario_privilegios up WHERE up.id_usuario = u.id_usuario) AS num_overrides
            FROM usuarios u
            LEFT JOIN planteles p ON p.id_plantel = u.id_plantel
            LEFT JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.rol != 'alumno'";
    if ($idPlantel !== null && $idPlantel > 0) {
        $sql .= ' AND u.id_plantel = ?';
        $params[] = $idPlantel;
    }
    if ($q !== '') {
        $sql .= ' AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $sql .= ' ORDER BY u.nombre, u.apellido LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['permisos_personalizados'] = (int) ($row['permisos_personalizados'] ?? 0)
            || (int) ($row['num_overrides'] ?? 0) > 0;
    }
    unset($row);

    return $rows;
}

/** @return array<string, mixed>|null */
function rbac_personal_detalle(PDO $pdo, int $idUsuario): ?array
{
    rbac_db_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT u.*, p.nombre AS plantel_nombre, r.nombre AS rol_nombre, r.clave AS rol_clave_ref
         FROM usuarios u
         LEFT JOIN planteles p ON p.id_plantel = u.id_plantel
         LEFT JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return null;
    }
    $idRol = (int) ($u['id_rol'] ?? 0);
    $rolPrivs = $idRol > 0 ? rbac_rol_privilegios($pdo, $idRol) : [];
    $rol = $idRol > 0 ? rbac_rol_por_id($pdo, $idRol) : null;
    if ($rol && (int) ($rol['acceso_total'] ?? 0)) {
        $rolPrivs = array_keys(rbac_privilegios_catalogo());
    }
    $overrides = rbac_usuario_privilegios_listar($pdo, $idUsuario);

    return [
        'usuario' => $u,
        'rol' => $rol,
        'privilegios_rol' => $rolPrivs,
        'overrides' => $overrides,
        'permisos_personalizados' => rbac_usuario_tiene_permisos_personalizados($pdo, $idUsuario),
    ];
}

/** @return array{ok:bool,message:string} */
function rbac_usuario_cambiar_rol(PDO $pdo, int $idUsuario, int $idRol): array
{
    rbac_db_ensure_schema($pdo);
    if ($idUsuario <= 0 || $idRol <= 0) {
        return ['ok' => false, 'message' => 'Datos inválidos'];
    }
    if (!rbac_usuario_puede_gestionar_privilegios()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $rol = rbac_rol_por_id($pdo, $idRol);
    if (!$rol || !(int) ($rol['activo'] ?? 0)) {
        return ['ok' => false, 'message' => 'Rol no válido'];
    }
    if (rbac_rol_real() !== 'supervisor' && ($rol['clave'] ?? '') === 'supervisor') {
        return ['ok' => false, 'message' => 'No puede asignar el rol supervisor'];
    }
    $dept = trim((string) ($rol['departamento_default'] ?? ''));
    $pdo->prepare(
        'UPDATE usuarios SET id_rol = ?, rol = ?, departamento = COALESCE(NULLIF(?, \'\'), departamento) WHERE id_usuario = ?'
    )->execute([$idRol, $rol['clave'], $dept, $idUsuario]);

    return ['ok' => true, 'message' => 'Rol actualizado a ' . ($rol['nombre'] ?? '')];
}
