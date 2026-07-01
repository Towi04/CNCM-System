<?php

/**
 * Limpieza de datos operativos, pruebas e importación legacy.
 * Conserva configuración del sistema (roles, planteles, personal real, rúbrica HAY).
 */

const PURGE_CONFIRM_PHRASE = 'BORRAR DATOS';

/** Tablas que deben quedar vacías tras una purga completa. */
const PURGE_TABLAS_VERIFICAR = [
    'asistencias',
    'asistencia_personal',
    'asistencias_personal',
    'grupos',
    'hay_legacy_map',
    'notificacion_usuario',
    'productos',
    'reporte_semanal_movimiento',
    'alumnos',
    'preregistros',
];

/** @return list<string> */
function purge_tablas_operativas_orden(): array
{
    return [
        'exam_generado_preguntas',
        'exam_calificaciones',
        'exam_generados',
        'hay_eval_respuesta',
        'hay_capacitacion_cumplimiento',
        'hay_eval_periodo',
        'profesor_eval_periodo',
        'certificacion_documentos',
        'certificacion_accesos',
        'certificacion_reagendamientos',
        'certificacion_comision_historial',
        'certificacion_solicitudes',
        'alumno_chat_mensaje',
        'alumno_chat_sala',
        'alumno_calificacion_parcial',
        'alumno_nota_coordinacion',
        'alumno_calificaciones_fase',
        'alumno_notas',
        'alumno_documentos',
        'alumno_moodle_curso',
        'alumno_ubicacion_grupos',
        'alumno_ubicacion',
        'alumno_plan_asignado',
        'alumno_becas',
        'alumno_aviso',
        'alumno_huellas',
        'producto_movimientos',
        'producto_certificacion_campo',
        'producto_certificacion',
        'asistencias',
        'asistencia_personal',
        'asistencias_personal',
        'asistencia_falta_seguimiento',
        'alumno_pagos',
        'ventas_movimiento',
        'reporte_semanal_movimiento',
        'corte_caja',
        'curso_personalizado_pago',
        'preregistro_alertas',
        'inscripcion_referidos',
        'inscripcion_cartas_reparto',
        'inscripcion_autorizacion',
        'grupo_rubrica_parcial',
        'grupo_plan_periodo',
        'grupo_fusion_log',
        'grupo_avance_log',
        'grupo_horarios',
        'planeaciones',
        'asesoria_disp',
        'asesor_entrevistas',
        'prospectos_profesor',
        'docente_prospecto_evento',
        'docente_prospecto',
        'docente_showclass_eval',
        'profesor_permiso_solicitud',
        'graduacion_alerta',
        'notificacion_usuario',
        'usuario_tour',
        'disc_res',
        'password_resets',
        'huella_eventos',
        'huella_codigos',
        'alumno_grupos',
        'alumno_especialidades',
        'alumnos',
        'preregistros',
        'grupos',
        'curso_personalizado',
        'producto_inventario',
        'productos',
        'hay_legacy_map',
        'hay_legacy_import_log',
    ];
}

/** @return array<string, true> */
function purge_tablas_bd(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $st = $pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\''
        );
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $name) {
            $cache[(string) $name] = true;
        }
    } catch (Throwable $e) {
        error_log('purge_tablas_bd: ' . $e->getMessage());
    }

    return $cache;
}

function purge_tabla_existe(PDO $pdo, string $tabla): bool
{
    $tablas = purge_tablas_bd($pdo);

    return isset($tablas[$tabla]);
}

function purge_log(string $msg, ?callable $logger = null): void
{
    if ($logger) {
        $logger($msg);
        return;
    }
    echo $msg . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
}

/**
 * @return array{ok:bool, skipped?:bool, filas:int, error?:string}
 */
function purge_tabla_vaciar(PDO $pdo, string $tabla): array
{
    if (!purge_tabla_existe($pdo, $tabla)) {
        return ['ok' => true, 'skipped' => true, 'filas' => 0];
    }
    $safe = str_replace('`', '', $tabla);
    try {
        $pdo->exec('TRUNCATE TABLE `' . $safe . '`');

        return ['ok' => true, 'filas' => 0];
    } catch (Throwable $eTruncate) {
        try {
            $n = $pdo->exec('DELETE FROM `' . $safe . '`');

            return ['ok' => true, 'filas' => $n !== false ? (int) $n : 0];
        } catch (Throwable $eDelete) {
            return [
                'ok' => false,
                'filas' => 0,
                'error' => $eDelete->getMessage(),
            ];
        }
    }
}

/** @return array<string, int> */
function purge_contar_restantes(PDO $pdo, array $tablas): array
{
    $out = [];
    foreach ($tablas as $tabla) {
        if (!purge_tabla_existe($pdo, $tabla)) {
            continue;
        }
        $safe = str_replace('`', '', $tabla);
        try {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM `' . $safe . '`')->fetchColumn();
            if ($n > 0) {
                $out[$tabla] = $n;
            }
        } catch (Throwable $e) {
            $out[$tabla] = -1;
        }
    }

    return $out;
}

/**
 * @return array{ok:bool, message:string, filas:int, detalle:list<string>, errores:list<string>, restantes:array<string,int>}
 */
function purge_datos_operativos(PDO $pdo, array $opts = [], ?callable $logger = null): array
{
    $incluirLegacyCatalogo = !empty($opts['legacy_catalogo']);
    $soloDemo = !empty($opts['solo_demo']);
    $detalle = [];
    $errores = [];
    $total = 0;

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET SESSION sql_safe_updates=0');

        if ($soloDemo) {
            $total += purge_solo_demo($pdo, $detalle, $errores, $logger);
        } else {
            $vistas = [];
            foreach (purge_tablas_operativas_orden() as $tabla) {
                if (isset($vistas[$tabla])) {
                    continue;
                }
                $vistas[$tabla] = true;
                $res = purge_tabla_vaciar($pdo, $tabla);
                if (!empty($res['skipped'])) {
                    continue;
                }
                if (!$res['ok']) {
                    $errores[] = "{$tabla}: " . ($res['error'] ?? 'error desconocido');
                    purge_log("  ! {$tabla}: " . ($res['error'] ?? 'error'), $logger);
                    continue;
                }
                if (($res['filas'] ?? 0) > 0) {
                    $detalle[] = "{$tabla}: {$res['filas']}";
                    purge_log("  − {$tabla}: {$res['filas']} fila(s)", $logger);
                } else {
                    purge_log("  − {$tabla}: vaciada", $logger);
                    $detalle[] = "{$tabla}: vaciada";
                }
                $total += max(0, (int) ($res['filas'] ?? 0));
            }

            if (purge_tabla_existe($pdo, 'grupo_clave_secuencia')) {
                purge_tabla_vaciar($pdo, 'grupo_clave_secuencia');
                purge_log('  − grupo_clave_secuencia: reiniciado', $logger);
            }

            $nUsu = purge_usuarios_alumnos_y_demo($pdo);
            if ($nUsu > 0) {
                $detalle[] = "usuarios (alumnos/demo): {$nUsu}";
                purge_log("  − usuarios alumnos/demo: {$nUsu}", $logger);
                $total += $nUsu;
            }

            if ($incluirLegacyCatalogo) {
                $total += purge_legacy_catalogo($pdo, $detalle, $errores, $logger);
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $restantes = purge_contar_restantes($pdo, PURGE_TABLAS_VERIFICAR);
        if ($restantes !== [] && !$soloDemo) {
            foreach ($restantes as $t => $n) {
                $errores[] = "Quedaron datos en {$t}: {$n} fila(s)";
                purge_log("  ! Aún hay datos en {$t}: {$n}", $logger);
            }
        }

        $ok = $errores === [];

        return [
            'ok' => $ok,
            'message' => $soloDemo
                ? 'Datos de prueba eliminados.'
                : ($ok
                    ? 'Datos operativos eliminados. Se conservaron roles, planteles, especialidades y personal real.'
                    : 'Purga terminada con advertencias; revise las tablas listadas abajo.'),
            'filas' => $total,
            'detalle' => $detalle,
            'errores' => $errores,
            'restantes' => $restantes,
        ];
    } catch (Throwable $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $ignored) {
        }

        return [
            'ok' => false,
            'message' => 'Error al purgar: ' . $e->getMessage(),
            'filas' => $total,
            'detalle' => $detalle,
            'errores' => array_merge($errores, [$e->getMessage()]),
            'restantes' => purge_contar_restantes($pdo, PURGE_TABLAS_VERIFICAR),
        ];
    }
}

function purge_usuarios_alumnos_y_demo(PDO $pdo): int
{
    if (!purge_tabla_existe($pdo, 'usuarios')) {
        return 0;
    }
    $n = 0;
    $st = $pdo->prepare("DELETE FROM usuarios WHERE rol = 'alumno' OR id_alumno IS NOT NULL OR username LIKE 'demo.%'");
    $st->execute();
    $n += $st->rowCount();
    try {
        $pdo->exec('DELETE FROM usuario_privilegios WHERE id_usuario NOT IN (SELECT id_usuario FROM usuarios)');
    } catch (Throwable $e) {
        // best-effort
    }

    return $n;
}

function purge_solo_demo(PDO $pdo, array &$detalle, array &$errores, ?callable $logger): int
{
    $total = 0;
    $ops = [
        "DELETE FROM grupos WHERE aula LIKE '%seed_prueba_2025%'" => 'grupos demo',
        "DELETE FROM alumno_pagos WHERE folio LIKE 'SEED-%' OR concepto LIKE '%seed_operativo_2025%'" => 'pagos demo',
        "DELETE FROM preregistros WHERE observaciones LIKE '%seed_operativo_2025%'" => 'preregistros demo',
        "DELETE FROM profesor_eval_periodo WHERE observaciones LIKE '%seed_operativo_2025%'" => 'eval demo',
        "DELETE FROM usuarios WHERE username LIKE 'demo.%'" => 'usuarios demo',
    ];
    foreach ($ops as $sql => $label) {
        try {
            $n = (int) $pdo->exec($sql);
            if ($n > 0) {
                $detalle[] = "{$label}: {$n}";
                purge_log("  − {$label}: {$n}", $logger);
                $total += $n;
            }
        } catch (Throwable $e) {
            $errores[] = "{$label}: " . $e->getMessage();
            purge_log("  ! {$label}: " . $e->getMessage(), $logger);
        }
    }

    return $total;
}

function purge_legacy_catalogo(PDO $pdo, array &$detalle, array &$errores, ?callable $logger): int
{
    $total = 0;
    $ops = [
        "DELETE FROM especialidades WHERE clave LIKE 'LEG\\_%'" => 'especialidades LEG_*',
        "DELETE FROM productos WHERE clave LIKE 'LEG\\_%'" => 'productos LEG_*',
        "DELETE FROM grupos WHERE codigo_area = 'LEG' OR clave LIKE 'LEG-%'" => 'grupos legacy',
    ];
    foreach ($ops as $sql => $label) {
        try {
            $n = (int) $pdo->exec($sql);
            if ($n > 0) {
                $detalle[] = "{$label}: {$n}";
                purge_log("  − {$label}: {$n}", $logger);
                $total += $n;
            }
        } catch (Throwable $e) {
            $errores[] = "{$label}: " . $e->getMessage();
            purge_log("  ! {$label}: " . $e->getMessage(), $logger);
        }
    }

    return $total;
}

function purge_puede_ejecutar(): bool
{
    return function_exists('rbac_rol_real') && rbac_rol_real() === 'supervisor';
}
