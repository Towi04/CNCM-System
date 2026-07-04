<?php
/**
 * Asistente de migración legado → Portal CNCM con previsualización por fases.
 */

/** @return list<array{id:string,titulo:string,descripcion:string,import_key?:string,requiere_equiv?:bool}> */
function legacy_migracion_fases(): array
{
    return [
        [
            'id' => 'verificar',
            'titulo' => '1. Verificación',
            'descripcion' => 'Conexión al legado, conteos y equivalencias pendientes.',
        ],
        [
            'id' => 'equivalencias',
            'titulo' => '2. Equivalencias',
            'descripcion' => 'Planteles y especialidades del sistema anterior → catálogo actual.',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'usuarios',
            'titulo' => '3. Personal',
            'descripcion' => 'Usuarios del legado (profesores, recepción, asesores, etc.).',
            'import_key' => 'usuarios',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'grupos',
            'titulo' => '4. Grupos',
            'descripcion' => 'Grupos con clave CNCM nueva; la clave anterior queda en clave_anterior.',
            'import_key' => 'grupos',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'preregistros',
            'titulo' => '5. Pre-registros',
            'descripcion' => 'Prospectos que aún no eran alumnos inscritos.',
            'import_key' => 'preregistros',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'alumnos',
            'titulo' => '6. Alumnos',
            'descripcion' => 'Alumnos activos con número de control.',
            'import_key' => 'alumnos',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'alumno_grupos',
            'titulo' => '7. Inscripciones a grupos',
            'descripcion' => 'Relación alumno ↔ grupo (alumnos_grupos).',
            'import_key' => 'alumno_grupos',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'alumno_especialidades',
            'titulo' => '8. Especialidades por alumno',
            'descripcion' => 'Tarifas y forma de pago por especialidad.',
            'import_key' => 'alumno_especialidades',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'pagos',
            'titulo' => '9. Pagos históricos',
            'descripcion' => 'Abonos y pagos del legado como alumno_pagos.',
            'import_key' => 'pagos',
            'requiere_equiv' => true,
        ],
        [
            'id' => 'asistencias',
            'titulo' => '10. Asistencias',
            'descripcion' => 'Registros de asistencia por alumno y grupo.',
            'import_key' => 'asistencias',
            'requiere_equiv' => true,
        ],
    ];
}

function legacy_migracion_puede(): bool
{
    $rol = function_exists('rbac_rol_real') ? rbac_rol_real() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['supervisor', 'gerente', 'director'], true);
}

/** @return array<string, mixed> */
function legacy_migracion_estado(PDO $hay, ?PDO $leg): array
{
    legacy_import_ensure_schema($hay);
    $out = [
        'legacy_conectado' => $leg instanceof PDO,
        'legacy_db' => defined('LEGACY_DB_NAME') ? LEGACY_DB_NAME : '',
        'conteos_legado' => [],
        'conteos_hay' => [],
        'mapas' => [],
        'equivalencias_pendientes' => [],
        'listo_para_datos' => false,
    ];

    if ($leg instanceof PDO) {
        foreach (['sucursales', 'especialidades', 'grupos', 'users', 'alumnos', 'alumnos_grupos', 'pagos', 'abonos', 'asistencias'] as $tbl) {
            if (legacy_import_table_exists($leg, $tbl)) {
                $out['conteos_legado'][$tbl] = (int) $leg->query('SELECT COUNT(*) FROM `' . $tbl . '`')->fetchColumn();
            }
        }
        $out['equivalencias_pendientes'] = legacy_migracion_equiv_pendientes($hay, $leg);
    }

    foreach (['planteles', 'especialidades', 'grupos', 'alumnos', 'usuarios', 'alumno_pagos', 'asistencias'] as $tbl) {
        if (legacy_import_table_exists($hay, $tbl)) {
            $out['conteos_hay'][$tbl] = (int) $hay->query('SELECT COUNT(*) FROM `' . $tbl . '`')->fetchColumn();
        }
    }

    try {
        $maps = $hay->query('SELECT entidad, COUNT(*) AS n FROM hay_legacy_map GROUP BY entidad')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($maps as $m) {
            $out['mapas'][$m['entidad']] = (int) $m['n'];
        }
    } catch (Throwable $e) {
    }

    $pend = $out['equivalencias_pendientes'];
    $out['listo_para_datos'] = $leg instanceof PDO
        && ($pend['planteles_sin_map'] ?? 0) === 0
        && ($pend['especialidades_sin_map'] ?? 0) === 0;

    return $out;
}

/** @return array{planteles_sin_map:int,especialidades_sin_map:int,detalles:list<string>} */
function legacy_migracion_equiv_pendientes(PDO $hay, PDO $leg): array
{
    $plSin = 0;
    $espSin = 0;
    $detalles = [];

    $suc = $leg->query('SELECT id, nombre FROM sucursales ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($suc as $row) {
        $lid = (int) $row['id'];
        if (legacy_resolve_hay_id($hay, 'plantel', $lid) === null) {
            $plSin++;
            if (count($detalles) < 8) {
                $detalles[] = 'Plantel legado #' . $lid . ' «' . trim((string) $row['nombre']) . '» sin equivalencia';
            }
        }
    }

    $esp = $leg->query('SELECT id, nombre FROM especialidades ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($esp as $row) {
        $lid = (int) $row['id'];
        if (legacy_resolve_hay_id($hay, 'especialidad', $lid) === null) {
            $espSin++;
            if (count($detalles) < 16) {
                $detalles[] = 'Especialidad legado #' . $lid . ' «' . trim((string) $row['nombre']) . '» sin equivalencia';
            }
        }
    }

    return [
        'planteles_sin_map' => $plSin,
        'especialidades_sin_map' => $espSin,
        'detalles' => $detalles,
    ];
}

/** @return array<string, mixed> */
function legacy_migracion_preview(PDO $hay, PDO $leg, string $fase): array
{
    legacy_import_ensure_schema($hay);
    hay_bootstrap_schema($hay);

    if ($fase === 'verificar') {
        return legacy_migracion_preview_verificar($hay, $leg);
    }
    if ($fase === 'equivalencias') {
        return legacy_migracion_preview_equivalencias($hay, $leg);
    }

    $faseImport = null;
    foreach (legacy_migracion_fases() as $def) {
        if (($def['id'] ?? '') === $fase) {
            $faseImport = $def['import_key'] ?? null;
            break;
        }
    }
    if ($faseImport === null) {
        return ['status' => 'error', 'message' => 'Fase desconocida: ' . $fase];
    }

    $muestras = legacy_migracion_muestras($hay, $leg, $faseImport, 40);
    $res = legacy_import_run($hay, $leg, $faseImport, true);
    $stats = $res[$faseImport] ?? ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
    $pend = legacy_migracion_equiv_pendientes($hay, $leg);

    return [
        'status' => 'ok',
        'fase' => $fase,
        'import_key' => $faseImport,
        'resumen' => [
            'insertar' => (int) ($stats['inserted'] ?? 0),
            'omitir' => (int) ($stats['skipped'] ?? 0),
            'error' => (int) ($stats['errors'] ?? 0),
        ],
        'muestras' => $muestras,
        'advertencias' => $pend['detalles'],
        'listo' => ($pend['planteles_sin_map'] ?? 0) === 0 && ($pend['especialidades_sin_map'] ?? 0) === 0,
    ];
}

/** @return array<string, mixed> */
function legacy_migracion_preview_verificar(PDO $hay, PDO $leg): array
{
    $estado = legacy_migracion_estado($hay, $leg);
    $advertencias = $estado['equivalencias_pendientes']['detalles'] ?? [];
    if (!$estado['legacy_conectado']) {
        $advertencias[] = 'No hay conexión al legado. Configure LEGACY_DB_* en config.local.php';
    }

    return [
        'status' => 'ok',
        'fase' => 'verificar',
        'estado' => $estado,
        'advertencias' => $advertencias,
        'listo' => $estado['listo_para_datos'],
        'muestras' => [],
        'resumen' => ['insertar' => 0, 'omitir' => 0, 'error' => 0],
    ];
}

/** @return array<string, mixed> */
function legacy_migracion_preview_equivalencias(PDO $hay, PDO $leg): array
{
    $muestras = [];
    $suc = $leg->query('SELECT id, nombre FROM sucursales ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($suc as $row) {
        $lid = (int) $row['id'];
        $idHay = legacy_resolve_hay_id($hay, 'plantel', $lid);
        $muestras[] = [
            'accion' => $idHay ? 'mapear' : 'pendiente',
            'legacy_id' => $lid,
            'legacy_label' => trim((string) $row['nombre']),
            'hay_label' => $idHay ? legacy_migracion_label_plantel($hay, $idHay) : '— Sin equivalencia —',
            'detalle' => $idHay ? 'Plantel #' . $idHay : 'Configure en equivalencias',
        ];
    }
    $esp = $leg->query('SELECT id, nombre FROM especialidades ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($esp as $row) {
        $lid = (int) $row['id'];
        $idHay = legacy_resolve_hay_id($hay, 'especialidad', $lid);
        $muestras[] = [
            'accion' => $idHay ? 'mapear' : 'pendiente',
            'legacy_id' => $lid,
            'legacy_label' => trim((string) $row['nombre']),
            'hay_label' => $idHay ? legacy_migracion_label_especialidad($hay, $idHay) : '— Sin equivalencia —',
            'detalle' => $idHay ? 'Especialidad #' . $idHay : 'Indique reemplazo en equivalencias',
        ];
    }
    $pend = legacy_migracion_equiv_pendientes($hay, $leg);

    return [
        'status' => 'ok',
        'fase' => 'equivalencias',
        'resumen' => [
            'insertar' => 0,
            'omitir' => count($muestras) - $pend['planteles_sin_map'] - $pend['especialidades_sin_map'],
            'error' => $pend['planteles_sin_map'] + $pend['especialidades_sin_map'],
        ],
        'muestras' => array_slice($muestras, 0, 60),
        'advertencias' => $pend['detalles'],
        'listo' => ($pend['planteles_sin_map'] ?? 0) === 0 && ($pend['especialidades_sin_map'] ?? 0) === 0,
    ];
}

function legacy_migracion_label_plantel(PDO $hay, int $id): string
{
    $st = $hay->prepare('SELECT nombre FROM planteles WHERE id_plantel = ? LIMIT 1');
    $st->execute([$id]);
    $n = $st->fetchColumn();

    return $n !== false ? (string) $n : '#' . $id;
}

function legacy_migracion_label_especialidad(PDO $hay, int $id): string
{
    $st = $hay->prepare('SELECT clave, nombre FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return '#' . $id;
    }

    return trim(($r['clave'] ?? '') . ' — ' . ($r['nombre'] ?? ''));
}

function legacy_migracion_label_grupo(PDO $hay, int $id): string
{
    $st = $hay->prepare('SELECT clave, clave_anterior FROM grupos WHERE id_grupo = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return 'Grupo #' . $id;
    }
    $clave = trim((string) ($row['clave'] ?? ''));
    $ant = trim((string) ($row['clave_anterior'] ?? ''));

    return $ant !== '' && $ant !== $clave ? $clave . ' (antes: ' . $ant . ')' : $clave;
}

/**
 * Muestras representativas por fase (sin escribir en BD).
 *
 * @return list<array{accion:string,legacy_id?:int,legacy_label:string,hay_label?:string,detalle?:string}>
 */
function legacy_migracion_muestras(PDO $hay, PDO $leg, string $faseImport, int $limite = 40): array
{
    $out = [];
    switch ($faseImport) {
        case 'grupos':
            $cols = legacy_import_select_cols($leg, 'grupos', [
                'id', 'id_sucursal', 'clave', 'horario', 'dias', 'horario_texto', 'id_especialidad', 'especialidad', 'fecha_inicio', 'infantil',
            ]);
            $rows = $leg->query("SELECT {$cols} FROM grupos ORDER BY id LIMIT " . ($limite * 3))->fetchAll();
            foreach ($rows as $g) {
                if (count($out) >= $limite) {
                    break;
                }
                $lid = (int) $g['id'];
                if (legacy_map_get($hay, 'grupo', $lid) !== null) {
                    continue;
                }
                $idPlantel = legacy_resolve_hay_id($hay, 'plantel', (int) ($g['id_sucursal'] ?? 0));
                $idEsp = legacy_grupo_resolve_especialidad_hay($hay, $leg, $g);
                $claveLegacy = trim((string) ($g['clave'] ?? 'G' . $lid));
                $hayLabel = '—';
                $detalle = '';
                if ($idPlantel === null) {
                    $accion = 'error';
                    $detalle = 'Plantel sin equivalencia';
                } elseif ($idEsp === null) {
                    $accion = 'error';
                    $detalle = 'Especialidad sin equivalencia';
                } else {
                    $accion = 'insertar';
                    $codigos = legacy_grupo_infer_codigos($hay, $idEsp, $g, $leg);
                    try {
                        $gen = legacy_grupo_generar_clave_hay($hay, $idPlantel, $codigos, true);
                        $hayLabel = (string) ($gen['clave'] ?? '');
                        $detalle = 'Clave anterior: ' . $claveLegacy;
                    } catch (Throwable $e) {
                        $hayLabel = '(generar al aplicar)';
                        $detalle = $e->getMessage();
                    }
                }
                $out[] = [
                    'accion' => $accion,
                    'legacy_id' => $lid,
                    'legacy_label' => $claveLegacy,
                    'hay_label' => $hayLabel,
                    'detalle' => $detalle,
                ];
            }
            break;

        case 'alumnos':
            $del = legacy_import_column_exists($leg, 'alumnos', 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
            $rows = $leg->query('SELECT id, numero_control, nuevo_numero_control, nombres, apellido_paterno, status FROM alumnos' . $del . ' ORDER BY id LIMIT ' . ($limite * 2))->fetchAll();
            foreach ($rows as $a) {
                if (count($out) >= $limite) {
                    break;
                }
                if (!legacy_import_is_alumno_row($a)) {
                    continue;
                }
                $lid = (int) $a['id'];
                $nc = trim((string) ($a['nuevo_numero_control'] ?? $a['numero_control'] ?? ''));
                $nombre = trim(($a['nombres'] ?? '') . ' ' . ($a['apellido_paterno'] ?? ''));
                if (legacy_map_get($hay, 'alumno', $lid) !== null) {
                    $out[] = ['accion' => 'omitir', 'legacy_id' => $lid, 'legacy_label' => $nc . ' ' . $nombre, 'detalle' => 'Ya importado'];
                    continue;
                }
                if ($nc === '') {
                    $out[] = ['accion' => 'error', 'legacy_id' => $lid, 'legacy_label' => $nombre, 'detalle' => 'Sin número de control'];
                    continue;
                }
                $dup = $hay->prepare('SELECT id_alumno FROM alumnos WHERE numero_control = ? LIMIT 1');
                $dup->execute([$nc]);
                if ($dup->fetchColumn()) {
                    $out[] = ['accion' => 'omitir', 'legacy_id' => $lid, 'legacy_label' => $nc, 'detalle' => 'Control ya existe en CNCM'];
                    continue;
                }
                $out[] = ['accion' => 'insertar', 'legacy_id' => $lid, 'legacy_label' => $nc . ' — ' . $nombre, 'hay_label' => 'Nuevo alumno'];
            }
            break;

        case 'usuarios':
            $del = legacy_import_column_exists($leg, 'users', 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
            $rows = $leg->query('SELECT id, nombres, apellido_paterno, email FROM users' . $del . ' ORDER BY id LIMIT ' . $limite)->fetchAll();
            foreach ($rows as $u) {
                $lid = (int) $u['id'];
                $label = trim(($u['nombres'] ?? '') . ' ' . ($u['apellido_paterno'] ?? '')) . ' · ' . ($u['email'] ?? '');
                if (legacy_map_get($hay, 'usuario', $lid) !== null) {
                    $out[] = ['accion' => 'omitir', 'legacy_id' => $lid, 'legacy_label' => $label, 'detalle' => 'Ya mapeado'];
                    continue;
                }
                $out[] = [
                    'accion' => 'insertar',
                    'legacy_id' => $lid,
                    'legacy_label' => $label,
                    'hay_label' => legacy_import_user_role($leg, $lid),
                    'detalle' => 'Rol estimado',
                ];
            }
            break;

        case 'pagos':
            if (legacy_import_table_exists($leg, 'abonos')) {
                $rows = $leg->query(
                    'SELECT a.id, a.monto, p.folio, p.id_alumno
                     FROM abonos a
                     INNER JOIN pagos p ON p.id = a.id_pago
                     ORDER BY a.id DESC LIMIT ' . $limite
                )->fetchAll();
                foreach ($rows as $r) {
                    $idAl = legacy_resolve_hay_id($hay, 'alumno', (int) ($r['id_alumno'] ?? 0));
                    $out[] = [
                        'accion' => $idAl ? 'insertar' : 'error',
                        'legacy_id' => (int) $r['id'],
                        'legacy_label' => 'Folio ' . ($r['folio'] ?? '') . ' · $' . ($r['monto'] ?? ''),
                        'hay_label' => $idAl ? 'alumno #' . $idAl : 'Alumno sin mapa',
                    ];
                }
            }
            break;

        case 'preregistros':
            $del = legacy_import_column_exists($leg, 'alumnos', 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
            $rows = $leg->query(
                'SELECT id, nombres, apellido_paterno, status, id_sucursal, id_especialidad FROM alumnos' . $del . ' ORDER BY id LIMIT ' . ($limite * 2)
            )->fetchAll();
            foreach ($rows as $a) {
                if (count($out) >= $limite) {
                    break;
                }
                if (!legacy_import_is_preregistro_row($a)) {
                    continue;
                }
                $lid = (int) $a['id'];
                $nombre = trim(($a['nombres'] ?? '') . ' ' . ($a['apellido_paterno'] ?? ''));
                if (legacy_map_get($hay, 'preregistro', $lid) !== null) {
                    $out[] = ['accion' => 'omitir', 'legacy_id' => $lid, 'legacy_label' => $nombre, 'detalle' => 'Ya importado'];
                    continue;
                }
                $idPlantel = legacy_resolve_hay_id($hay, 'plantel', (int) ($a['id_sucursal'] ?? 0));
                $idEsp = !empty($a['id_especialidad'])
                    ? legacy_resolve_hay_id($hay, 'especialidad', (int) $a['id_especialidad'])
                    : null;
                if ($idPlantel === null) {
                    $out[] = ['accion' => 'error', 'legacy_id' => $lid, 'legacy_label' => $nombre, 'detalle' => 'Plantel sin equivalencia'];
                    continue;
                }
                $out[] = [
                    'accion' => 'insertar',
                    'legacy_id' => $lid,
                    'legacy_label' => $nombre,
                    'hay_label' => 'Pre-registro · plantel #' . $idPlantel . ($idEsp ? ' · esp. #' . $idEsp : ''),
                ];
            }
            break;

        case 'alumno_grupos':
            if (!legacy_import_table_exists($leg, 'alumnos_grupos')) {
                break;
            }
            $agCols = legacy_import_select_cols($leg, 'alumnos_grupos', [
                'id', 'id_alumno', 'id_grupo', 'fecha_inicio', 'status',
            ]);
            $hasStatus = legacy_import_column_exists($leg, 'alumnos_grupos', 'status');
            $where = $hasStatus ? " WHERE status IS NULL OR status = '' OR status = 'Inscrito'" : '';
            $rows = $leg->query("SELECT {$agCols} FROM alumnos_grupos{$where} ORDER BY id LIMIT " . ($limite * 2))->fetchAll();
            foreach ($rows as $r) {
                if (count($out) >= $limite) {
                    break;
                }
                $idAlLeg = (int) ($r['id_alumno'] ?? 0);
                $idGrLeg = (int) ($r['id_grupo'] ?? 0);
                $idAl = legacy_map_get($hay, 'alumno', $idAlLeg);
                $idGr = legacy_map_get($hay, 'grupo', $idGrLeg);
                $legacyLabel = 'Alumno legado #' . $idAlLeg . ' → Grupo legado #' . $idGrLeg;
                if ($idAl === null || $idGr === null) {
                    $out[] = [
                        'accion' => 'error',
                        'legacy_id' => (int) ($r['id'] ?? 0),
                        'legacy_label' => $legacyLabel,
                        'detalle' => ($idAl === null ? 'Alumno sin mapa' : '') . ($idGr === null ? ' Grupo sin mapa' : ''),
                    ];
                    continue;
                }
                $hayLabel = legacy_migracion_label_grupo($hay, $idGr) . ' ← alumno #' . $idAl;
                $out[] = [
                    'accion' => 'insertar',
                    'legacy_id' => (int) ($r['id'] ?? 0),
                    'legacy_label' => $legacyLabel,
                    'hay_label' => $hayLabel,
                    'detalle' => 'Inicio: ' . substr((string) ($r['fecha_inicio'] ?? ''), 0, 10),
                ];
            }
            break;

        case 'alumno_especialidades':
            if (!legacy_import_table_exists($leg, 'alumnos_especialidades')) {
                break;
            }
            $rows = $leg->query('SELECT id, id_alumno, id_especialidad, forma_pago, monto FROM alumnos_especialidades ORDER BY id LIMIT ' . ($limite * 2))->fetchAll();
            foreach ($rows as $r) {
                if (count($out) >= $limite) {
                    break;
                }
                $idAl = legacy_map_get($hay, 'alumno', (int) ($r['id_alumno'] ?? 0));
                $idEsp = legacy_resolve_hay_id($hay, 'especialidad', (int) ($r['id_especialidad'] ?? 0));
                $legacyLabel = 'Alumno legado #' . (int) ($r['id_alumno'] ?? 0) . ' · Esp. legado #' . (int) ($r['id_especialidad'] ?? 0);
                if ($idAl === null || $idEsp === null) {
                    $out[] = [
                        'accion' => 'error',
                        'legacy_id' => (int) ($r['id'] ?? 0),
                        'legacy_label' => $legacyLabel,
                        'detalle' => ($idAl === null ? 'Alumno sin mapa' : '') . ($idEsp === null ? ' Especialidad sin equivalencia' : ''),
                    ];
                    continue;
                }
                $out[] = [
                    'accion' => 'insertar',
                    'legacy_id' => (int) ($r['id'] ?? 0),
                    'legacy_label' => $legacyLabel,
                    'hay_label' => legacy_migracion_label_especialidad($hay, $idEsp),
                    'detalle' => 'Forma pago: ' . ($r['forma_pago'] ?? 'mensual') . ' · $' . ($r['monto'] ?? 0),
                ];
            }
            break;

        case 'asistencias':
            if (!legacy_import_table_exists($leg, 'asistencias')) {
                break;
            }
            $cols = legacy_import_select_cols($leg, 'asistencias', [
                'id', 'id_alumno', 'id_grupo', 'fecha',
            ]);
            $rows = $leg->query("SELECT {$cols} FROM asistencias ORDER BY id DESC LIMIT " . $limite)->fetchAll();
            foreach ($rows as $r) {
                $idAl = legacy_resolve_hay_id($hay, 'alumno', (int) ($r['id_alumno'] ?? 0));
                $idGr = legacy_resolve_hay_id($hay, 'grupo', (int) ($r['id_grupo'] ?? 0));
                $fecha = substr((string) ($r['fecha'] ?? ''), 0, 10);
                $legacyLabel = 'Alumno #' . (int) ($r['id_alumno'] ?? 0) . ' · ' . $fecha;
                if ($idAl === null || $idGr === null) {
                    $out[] = [
                        'accion' => 'error',
                        'legacy_id' => (int) ($r['id'] ?? 0),
                        'legacy_label' => $legacyLabel,
                        'detalle' => ($idAl === null ? 'Alumno sin mapa' : '') . ($idGr === null ? ' Grupo sin mapa' : ''),
                    ];
                    continue;
                }
                if ($fecha === '' || $fecha === '0000-00-00') {
                    $out[] = ['accion' => 'error', 'legacy_id' => (int) ($r['id'] ?? 0), 'legacy_label' => $legacyLabel, 'detalle' => 'Fecha inválida'];
                    continue;
                }
                $out[] = [
                    'accion' => 'insertar',
                    'legacy_id' => (int) ($r['id'] ?? 0),
                    'legacy_label' => $legacyLabel,
                    'hay_label' => legacy_migracion_label_grupo($hay, $idGr),
                    'detalle' => 'Presente · recepción',
                ];
            }
            break;

        default:
            break;
    }

    return $out;
}

/** @return array<string, mixed> */
function legacy_migracion_aplicar(PDO $hay, PDO $leg, string $fase): array
{
    if (!legacy_migracion_puede()) {
        return ['status' => 'error', 'message' => 'Sin permiso'];
    }
    legacy_import_ensure_schema($hay);
    hay_bootstrap_schema($hay);

    $pend = legacy_migracion_equiv_pendientes($hay, $leg);
    if (($pend['planteles_sin_map'] ?? 0) > 0 || ($pend['especialidades_sin_map'] ?? 0) > 0) {
        if (!in_array($fase, ['verificar', 'equivalencias'], true)) {
            return [
                'status' => 'error',
                'message' => 'Complete equivalencias de planteles y especialidades antes de importar datos.',
                'advertencias' => $pend['detalles'],
            ];
        }
    }

    if ($fase === 'verificar' || $fase === 'equivalencias') {
        return ['status' => 'ok', 'message' => 'Nada que aplicar en esta fase.', 'resultado' => []];
    }

    $importKey = null;
    foreach (legacy_migracion_fases() as $def) {
        if (($def['id'] ?? '') === $fase) {
            $importKey = $def['import_key'] ?? null;
            break;
        }
    }
    if ($importKey === null) {
        return ['status' => 'error', 'message' => 'Fase desconocida'];
    }

    try {
        $resultado = legacy_import_run($hay, $leg, $importKey, false);
        if ($importKey === 'grupos') {
            legacy_import_run($hay, $leg, 'grupos_remap_esp', false);
        }

        return [
            'status' => 'ok',
            'message' => 'Fase «' . $fase . '» aplicada.',
            'resultado' => $resultado,
        ];
    } catch (Throwable $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
