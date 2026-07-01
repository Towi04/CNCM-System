<?php

/**
 * Bandeja única de aprobaciones (director / coordinación).
 * Agrega permisos de profesores, autorizaciones de inscripción y apertura de grupos.
 */

function bandeja_aprobaciones_tiene_alguna_accion(): bool
{
    return (function_exists('profesor_portal_puede_revisar_permisos') && profesor_portal_puede_revisar_permisos())
        || (function_exists('inscripcion_protocolo_puede_autorizar') && inscripcion_protocolo_puede_autorizar())
        || (function_exists('grupo_apertura_puede_gestionar') && grupo_apertura_puede_gestionar());
}

function bandeja_aprobaciones_puede_ver(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
    if (!in_array($rol, ['director', 'supervisor', 'coordinador', 'coordinacion', 'gerente', 'admin'], true)) {
        return false;
    }

    return bandeja_aprobaciones_tiene_alguna_accion();
}

/** @return array{total:int, permisos:int, inscripciones:int, grupos:int} */
function bandeja_aprobaciones_resumen(PDO $pdo, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $items = bandeja_aprobaciones_listar($pdo, $idPlantel);
    $permisos = 0;
    $inscripciones = 0;
    $grupos = 0;
    foreach ($items as $it) {
        $tipo = (string) ($it['tipo'] ?? '');
        if ($tipo === 'permiso_profesor') {
            $permisos++;
        } elseif ($tipo === 'inscripcion') {
            $inscripciones++;
        } elseif ($tipo === 'grupo_apertura') {
            $grupos++;
        }
    }

    return [
        'total' => count($items),
        'permisos' => $permisos,
        'inscripciones' => $inscripciones,
        'grupos' => $grupos,
    ];
}

/** @return list<array<string, mixed>> */
function bandeja_aprobaciones_listar(PDO $pdo, ?int $idPlantel = null, ?string $filtro = null): array
{
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $items = [];

    if (
        ($filtro === null || $filtro === 'permiso_profesor')
        && function_exists('profesor_portal_puede_revisar_permisos')
        && profesor_portal_puede_revisar_permisos()
    ) {
        foreach (profesor_portal_permisos_pendientes($pdo, $idPlantel) as $p) {
            $idProf = (int) ($p['id_usuario'] ?? 0);
            $grupos = function_exists('profesor_portal_grupos')
                ? profesor_portal_grupos($pdo, $idProf, $idPlantel)
                : [];
            $nombre = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
            $fi = (string) ($p['fecha_inicio'] ?? '');
            $ff = (string) ($p['fecha_fin'] ?? '');
            $items[] = [
                'id' => 'permiso_' . (int) $p['id_solicitud'],
                'tipo' => 'permiso_profesor',
                'tipo_label' => 'Permiso profesor',
                'prioridad' => 'alta',
                'fecha' => $fi,
                'fecha_fmt' => ($fi !== '' ? date('d/m/Y', strtotime($fi)) : '—')
                    . ' – ' . ($ff !== '' ? date('d/m/Y', strtotime($ff)) : '—'),
                'titulo' => $nombre !== '' ? $nombre : 'Profesor #' . $idProf,
                'subtitulo' => trim((string) ($p['motivo'] ?? '')),
                'detalle' => $grupos !== []
                    ? 'Grupos: ' . implode(', ', array_column($grupos, 'clave'))
                    : '',
                'payload' => [
                    'id_solicitud' => (int) $p['id_solicitud'],
                    'id_profesor' => $idProf,
                    'fecha_inicio' => substr($fi, 0, 10),
                    'fecha_fin' => substr($ff, 0, 10),
                    'motivo' => (string) ($p['motivo'] ?? ''),
                    'grupos' => array_map(static function (array $g): array {
                        return [
                            'id_grupo' => (int) ($g['id_grupo'] ?? 0),
                            'clave' => (string) ($g['clave'] ?? ''),
                        ];
                    }, $grupos),
                    'puede_suplencia' => function_exists('suplencia_puede_gestionar') && suplencia_puede_gestionar(),
                ],
            ];
        }
    }

    if (
        ($filtro === null || $filtro === 'inscripcion')
        && function_exists('inscripcion_protocolo_puede_autorizar')
        && inscripcion_protocolo_puede_autorizar()
    ) {
        foreach (inscripcion_protocolo_pendientes($pdo, $idPlantel) as $a) {
            $tipoRaw = (string) ($a['tipo'] ?? '');
            $tipoLabel = $tipoRaw === 'ubicacion' ? 'Ubicación' : ($tipoRaw === 'edad' ? 'Edad' : ucfirst($tipoRaw));
            $nombre = trim(($a['nombres'] ?? '') . ' ' . ($a['apellido_paterno'] ?? ''));
            $creado = (string) ($a['creado_en'] ?? '');
            $items[] = [
                'id' => 'inscripcion_' . (int) $a['id_auth'],
                'tipo' => 'inscripcion',
                'tipo_label' => 'Inscripción · ' . $tipoLabel,
                'prioridad' => 'alta',
                'fecha' => $creado !== '' ? substr($creado, 0, 10) : '',
                'fecha_fmt' => $creado !== '' ? date('d/m/Y H:i', strtotime($creado)) : '—',
                'titulo' => $nombre !== '' ? $nombre : 'Alumno',
                'subtitulo' => trim((string) ($a['motivo'] ?? '')),
                'detalle' => 'Grupo ' . ($a['grupo_clave'] ?? '—')
                    . (!empty($a['numero_control']) ? ' · #' . $a['numero_control'] : ''),
                'payload' => [
                    'id_auth' => (int) $a['id_auth'],
                    'tipo' => $tipoRaw,
                    'numero_control' => (string) ($a['numero_control'] ?? ''),
                    'grupo_clave' => (string) ($a['grupo_clave'] ?? ''),
                ],
            ];
        }
    }

    if (
        ($filtro === null || $filtro === 'grupo_apertura')
        && function_exists('grupo_apertura_puede_gestionar')
        && grupo_apertura_puede_gestionar()
    ) {
        if (function_exists('grupo_apertura_sync_estados')) {
            grupo_apertura_sync_estados($pdo, $idPlantel);
        }
        foreach (grupo_apertura_listar_pendientes($pdo, $idPlantel) as $g) {
            $estado = (string) ($g['estado_apertura'] ?? 'programado');
            if ($estado !== 'pendiente_autorizacion') {
                continue;
            }
            $min = (int) ($g['min_alumnos'] ?? 0);
            $tot = (int) ($g['total_alumnos'] ?? 0);
            $cumple = function_exists('grupo_apertura_cumple_minimo') && grupo_apertura_cumple_minimo($g);
            $fi = (string) ($g['fecha_inicio'] ?? '');
            $items[] = [
                'id' => 'grupo_' . (int) $g['id_grupo'],
                'tipo' => 'grupo_apertura',
                'tipo_label' => 'Apertura de grupo',
                'prioridad' => 'alta',
                'fecha' => $fi,
                'fecha_fmt' => $fi !== '' ? date('d/m/Y', strtotime($fi)) : '—',
                'titulo' => (string) ($g['clave'] ?? 'Grupo'),
                'subtitulo' => (string) ($g['especialidad_nombre'] ?? ''),
                'detalle' => $tot . ($min > 0 ? ' alumnos (mín. ' . $min . ')' : ' alumnos')
                    . ((int) ($g['pospuestos'] ?? 0) > 0 ? ' · pospuesto ' . (int) $g['pospuestos'] . ' vez(ces)' : ''),
                'payload' => [
                    'id_grupo' => (int) $g['id_grupo'],
                    'fecha_inicio' => $fi,
                    'total_alumnos' => $tot,
                    'min_alumnos' => $min,
                    'cumple_minimo' => $cumple,
                    'estado' => $estado,
                    'estado_label' => function_exists('grupo_apertura_etiqueta_estado')
                        ? grupo_apertura_etiqueta_estado($estado)
                        : $estado,
                ],
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        $pa = (string) ($a['prioridad'] ?? 'normal');
        $pb = (string) ($b['prioridad'] ?? 'normal');
        $ord = ['alta' => 0, 'media' => 1, 'normal' => 2];
        $ca = $ord[$pa] ?? 3;
        $cb = $ord[$pb] ?? 3;
        if ($ca !== $cb) {
            return $ca <=> $cb;
        }
        $fa = (string) ($a['fecha'] ?? '');
        $fb = (string) ($b['fecha'] ?? '');
        if ($fa === $fb) {
            return strcmp((string) ($a['titulo'] ?? ''), (string) ($b['titulo'] ?? ''));
        }
        if ($fa === '') {
            return 1;
        }
        if ($fb === '') {
            return -1;
        }

        return strcmp($fa, $fb);
    });

    return $items;
}
