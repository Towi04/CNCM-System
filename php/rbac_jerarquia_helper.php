<?php

/**
 * Jerarquía CNCM: asesor → gerente / coordinador → recepción → director → supervisor.
 * Fuente única para mapa legacy y sincronización en BD.
 */

/** Privilegios base del asesor (nivel más bajo en ventas). */
function rbac_cap_asesor(): array
{
    return [
        'menu_ventas',
        'menu_preregistro',
        'menu_entrevistas',
        'menu_grupos_fases',
        'menu_ubicacion_asesor',
        'menu_cert_preregistro',
        'menu_calendario_consulta',
        'menu_reporte_inscritos',
        'menu_asesor_preinicio',
        'menu_comisiones_consulta',
        'menu_podio_ventas',
        'menu_mi_evaluacion',
        'menu_matriz_entrenamiento',
        'menu_soporte',
        'inscripcion_solicitar_autorizacion',
        'descuento_inscripcion_asesor',
    ];
}

/** Gerente de ventas: asesor + administración comercial. */
function rbac_cap_ventas_admin(): array
{
    return [
        'menu_comisiones_admin',
        'menu_gerente_dashboard',
        'menu_gerente_reportes',
        'menu_gerente_pendientes',
        'menu_gerente_perdidos',
        'menu_gerente_cartas',
        'menu_gerente_escuelas',
        'menu_reporte_escuelas',
        'menu_reporte_presentados',
        'menu_gerente_hay',
        'menu_gerente_matriz',
        'hay_eval_gestionar',
        'cartas_definir_minimo',
        'descuento_inscripcion_gerente',
    ];
}

/** Coordinación académica (sin ventas ni caja). */
function rbac_cap_academico(): array
{
    return [
        'menu_academico',
        'menu_alumnos',
        'menu_grupos',
        'menu_especialidades',
        'menu_asistencia',
        'menu_grupo_plan',
        'menu_asesorias',
        'menu_examenes',
        'menu_hay',
        'hay_eval_gestionar',
        'calificaciones_editar_coordinacion',
        'planeaciones_revisar',
        'profesor_contratar',
        'permiso_docente_proponer',
        'inscripcion_autorizar_edad',
        'inscripcion_autorizar_ubicacion',
        'grupo_autorizar_apertura',
        'reporte_academico_ver',
        'menu_tutor',
        'tutor_usar',
        'asistencia_lista_grupo',
        'asistencia_checada',
        'menu_mi_evaluacion',
        'menu_matriz_entrenamiento',
        'menu_mi_expediente',
        'expediente_consultar',
        'expediente_evaluar',
        'menu_soporte',
        'menu_calendario_consulta',
        'asesoria_agendar',
        'asesoria_agenda_dia',
        'asesoria_calendario',
        'asesoria_tabulador',
        'asesoria_autorizar_mismo_dia',
    ];
}

/** Recepción / caja: alumnos, cobranza y consulta de personal (sin académico ni ventas). */
function rbac_cap_recepcion(): array
{
    return array_merge(rbac_cap_caja(), [
        'menu_alumnos',
        'menu_mi_evaluacion',
        'menu_matriz_entrenamiento',
        'menu_mi_expediente',
        'expediente_consultar',
        'menu_calendario_consulta',
        'admin_usuarios',
    ]);
}

/** Recepción / caja: pagos y finanzas. */
function rbac_cap_caja(): array
{
    return [
        'menu_caja',
        'menu_consulta_adeudo',
        'menu_punto_venta',
        'menu_venta_productos',
        'menu_certificaciones',
        'menu_reportes',
        'menu_soporte',
        'cobro_precio_lista',
        'ticket_apoyo_educativo',
        'curso_personalizado_gestionar',
        'asistencia_movil',
        'asistencia_eliminar_registro',
        'inscripcion_solicitar_autorizacion',
        'asesoria_agendar',
        'asesoria_agenda_dia',
        'asesoria_calendario',
    ];
}

/** Director de plantel (sin configuraciones exclusivas de supervisión). */
function rbac_cap_director_extra(): array
{
    return [
        'menu_admin',
        'menu_examenes',
        'menu_hay',
        'hay_eval_gestionar',
        'hay_matriz_marcar',
        'menu_calendario',
        'menu_calendario_admin',
        'admin_catalogo',
        'admin_usuarios',
        'admin_colegiaturas_descuento',
        'descuento_inscripcion_director',
        'grupo_autorizar_apertura',
        'permiso_docente_aprobar_final',
        'asistencia_puntualidad',
        'asistencia_personal_manual',
        'asistencia_config_huella',
        'menu_marketing_banners',
        'menu_soporte',
        'menu_mi_expediente',
        'expediente_consultar',
        'expediente_evaluar',
        'asesoria_tabulador',
        'asesoria_autorizar_mismo_dia',
        'asesoria_calendario',
    ];
}
function rbac_cap_solo_supervisor(): array
{
    return [
        'catalogo_editar_costos',
        'admin_planteles',
        'admin_roles',
        'hay_eval_configurar',
        'seed_datos',
        'cartas_excepcion_minimo',
        'menu_supervisor_acuerdo',
        'pago_supervisor_editar',
        'menu_reporte_pagos_anulados',
        'expediente_requisitos_admin',
    ];
}

/** Portal del profesor (solo sus grupos y alumnos). */
function rbac_cap_profesor(): array
{
    return [
        'menu_asistencia',
        'menu_grupos',
        'menu_asesorias',
        'menu_examenes',
        'menu_mi_evaluacion',
        'menu_matriz_entrenamiento',
        'menu_soporte',
        'menu_calendario_consulta',
        'asistencia_lista_grupo',
        'asistencia_checada',
        'menu_mi_expediente',
    ];
}

/** Portal del alumno (solo su información). */
function rbac_cap_alumno(): array
{
    return [
        'menu_alumno_portal',
        'menu_alumno_calificaciones',
        'menu_alumno_estado_cuenta',
        'menu_alumno_promociones',
        'menu_alumno_cursos',
        'menu_alumno_chat',
        'menu_alumno_perfil',
        'menu_alumno_cuentas',
        'menu_soporte',
        'menu_mi_expediente',
    ];
}

/** @return list<string> */
function rbac_merge_caps(array ...$grupos): array
{
    $out = [];
    foreach ($grupos as $g) {
        foreach ($g as $c) {
            $out[$c] = true;
        }
    }

    return array_keys($out);
}

/** Privilegios por defecto del rol según jerarquía (cuando role_privilegios está vacío). */
function rbac_jerarquia_privilegios_fallback(string $claveRol): array
{
    if (!function_exists('rbac_normalizar_rol_clave')) {
        $claveRol = strtolower(trim($claveRol));
    } else {
        $claveRol = rbac_normalizar_rol_clave($claveRol);
    }
    $mapa = rbac_db_mapa_jerarquia();
    if (!isset($mapa[$claveRol])) {
        return [];
    }
    $privs = $mapa[$claveRol];
    if (!empty($privs['__acceso_total__'])) {
        return function_exists('rbac_privilegios_catalogo')
            ? array_keys(rbac_privilegios_catalogo())
            : [];
    }

    return array_values(array_filter($privs, static fn($p) => is_string($p) && $p !== ''));
}

/** Mapa rol → privilegios (semilla y fallback). */
function rbac_db_mapa_jerarquia(): array
{
    $asesor = rbac_cap_asesor();
    $acad = rbac_cap_academico();
    $ventas = rbac_cap_ventas_admin();
    $caja = rbac_cap_caja();
    $dir = rbac_cap_director_extra();

    return [
        'alumno' => rbac_cap_alumno(),
        'asesor' => $asesor,
        'gerente' => rbac_merge_caps($asesor, $ventas),
        'coordinador' => rbac_cap_academico(),
        'coordinacion' => rbac_cap_academico(),
        'admin' => rbac_cap_recepcion(),
        'profesor' => rbac_cap_profesor(),
        'director' => rbac_merge_caps($asesor, $acad, $caja, $ventas, $dir),
        'supervisor' => ['__acceso_total__' => true],
    ];
}

/**
 * Garantiza role_privilegios poblados (p. ej. tras 008/009 manual que borró el flag).
 * Solo debe llamarse desde hay_bootstrap_schema, no en cada vista AJAX.
 */
function rbac_db_asegurar_jerarquia_roles(PDO $pdo): void
{
    if (!function_exists('hay_meta_get') || !function_exists('rbac_rol_por_clave')) {
        return;
    }

    $flag = hay_meta_get($pdo, 'rbac_jerarquia_v3_done');
    $asesor = rbac_rol_por_clave($pdo, 'asesor');
    $privCount = 0;
    if ($asesor) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM role_privilegios WHERE id_rol = ?');
        $st->execute([(int) $asesor['id_rol']]);
        $privCount = (int) $st->fetchColumn();
    }

    if ($flag === '1' && $privCount > 0) {
        if (function_exists('rbac_db_reparar_supervisor')) {
            rbac_db_reparar_supervisor($pdo);
        }

        return;
    }

    if ($flag === '1' && $privCount === 0) {
        hay_meta_set($pdo, 'rbac_jerarquia_v3_done', '0');
    }

    rbac_db_sincronizar_jerarquia_roles($pdo);
    if (function_exists('rbac_db_reparar_supervisor')) {
        rbac_db_reparar_supervisor($pdo);
    }
}

/** Reemplaza privilegios de roles del sistema según la jerarquía actual. */
function rbac_db_sincronizar_jerarquia_roles(PDO $pdo): void
{
    if (!function_exists('hay_meta_get') || hay_meta_get($pdo, 'rbac_jerarquia_v3_done') === '1') {
        return;
    }

    $mapa = rbac_db_mapa_jerarquia();
    $ins = $pdo->prepare('INSERT INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
    $del = $pdo->prepare('DELETE FROM role_privilegios WHERE id_rol = ?');

    foreach ($mapa as $clave => $privs) {
        if (!empty($privs['__acceso_total__'])) {
            continue;
        }
        $rol = rbac_rol_por_clave($pdo, $clave);
        if (!$rol || !(int) ($rol['es_sistema'] ?? 0)) {
            continue;
        }
        $idRol = (int) $rol['id_rol'];
        $del->execute([$idRol]);
        foreach ($privs as $p) {
            if (is_string($p) && $p !== '') {
                $ins->execute([$idRol, $p]);
            }
        }
    }

    hay_meta_set($pdo, 'rbac_jerarquia_v3_done', '1');
}
