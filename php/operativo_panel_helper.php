<?php

/**
 * KPIs operativos para panel de inicio (recepciÃ³n / director).
 * Reutiliza helpers existentes; no duplica lÃ³gica de negocio.
 */

/** @return list<array{clave:string, titulo:string, valor:string, enlace?:string, query?:string, prioridad?:string}> */
function operativo_panel_kpis(PDO $pdo, int $idPlantel, string $perfil): array
{
    $kpis = [];

    if (in_array($perfil, ['recepcion', 'director'], true)) {
        if (function_exists('documento_contar_pendientes_plantel')) {
            $nConst = documento_contar_pendientes_plantel($pdo, $idPlantel);
            if ($nConst > 0) {
                $kpis[] = [
                    'clave' => 'constancias',
                    'titulo' => 'Constancias por cobrar',
                    'valor' => (string) $nConst,
                    'enlace' => 'piso_operativo',
                    'prioridad' => 'alta',
                ];
            }
        }
    }

    if ($perfil === 'recepcion') {
        $kpis = array_merge($kpis, operativo_panel_kpis_caja($pdo, $idPlantel));
    }

    if ($perfil === 'director') {
        $kpis = array_merge($kpis, operativo_panel_kpis_caja($pdo, $idPlantel));
        $kpis = array_merge($kpis, operativo_panel_kpis_director($pdo, $idPlantel));
    }

    return $kpis;
}

/** @return list<array<string, string>> */
function operativo_panel_kpis_caja(PDO $pdo, int $idPlantel): array
{
    $out = [];
    if (function_exists('notificaciones_inscripcion_por_vencer')) {
        $n = count(notificaciones_inscripcion_por_vencer($pdo, $idPlantel));
        if ($n > 0) {
            $out[] = [
                'clave' => 'insc_vencer',
                'titulo' => 'Inscripciones por vencer',
                'valor' => (string) $n,
                'enlace' => 'consulta_adeudo',
                'prioridad' => 'media',
            ];
        }
    }
    if (function_exists('cola_facturacion_contar') && cola_facturacion_puede_ver()) {
        $n = cola_facturacion_contar($pdo, $idPlantel);
        if ($n > 0) {
            $out[] = [
                'clave' => 'facturas',
                'titulo' => 'Cola de facturación',
                'valor' => (string) $n,
                'enlace' => 'cola_facturacion',
                'prioridad' => 'alta',
            ];
        }
    }
    if (function_exists('documento_contar_pendientes_entrega_plantel')) {
        $nEnt = documento_contar_pendientes_entrega_plantel($pdo, $idPlantel);
        if ($nEnt > 0) {
            $out[] = [
                'clave' => 'entrega_doc',
                'titulo' => 'Documentos por entregar',
                'valor' => (string) $nEnt,
                'enlace' => 'piso_operativo',
                'query' => 'tab=entrega',
                'prioridad' => 'alta',
            ];
        }
    }

    if (function_exists('reporte_financiero_puede_ver') && reporte_financiero_puede_ver()) {
        $corteHoy = function_exists('reporte_corte_caja_obtener')
            ? reporte_corte_caja_obtener($pdo, $idPlantel, date('Y-m-d'), 'B')
            : null;
        $out[] = [
            'clave' => 'corte_caja',
            'titulo' => 'Corte de caja (hoy)',
            'valor' => $corteHoy ? 'Listo' : 'Pendiente',
            'enlace' => 'corte_caja',
            'prioridad' => $corteHoy ? 'normal' : 'media',
        ];
    }

    return $out;
}

/** @return list<array<string, string>> */
function operativo_panel_kpis_director(PDO $pdo, int $idPlantel): array
{
    $out = [];

    if (function_exists('bandeja_aprobaciones_resumen') && bandeja_aprobaciones_puede_ver()) {
        $res = bandeja_aprobaciones_resumen($pdo, $idPlantel);
        if ($res['total'] > 0) {
            $out[] = [
                'clave' => 'bandeja_aprobaciones',
                'titulo' => 'Bandeja de aprobaciones',
                'valor' => (string) $res['total'],
                'enlace' => 'bandeja_aprobaciones',
                'prioridad' => 'alta',
            ];
        }
    }

    nomina_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM nomina_liquidacion WHERE id_plantel = ? AND estado = 'borrador'"
    );
    $st->execute([$idPlantel]);
    $nNom = (int) $st->fetchColumn();
    if ($nNom > 0) {
        $out[] = [
            'clave' => 'nomina',
            'titulo' => 'NÃ³minas en borrador',
            'valor' => (string) $nNom,
            'enlace' => 'director_nomina',
            'prioridad' => 'media',
        ];
    }

    if (function_exists('suplencia_listar')) {
        $nSup = count(suplencia_listar($pdo, $idPlantel));
        if ($nSup > 0) {
            $out[] = [
                'clave' => 'suplencias',
                'titulo' => 'Suplencias activas',
                'valor' => (string) $nSup,
                'enlace' => 'director_nomina',
                'query' => 'tab=suplencias',
                'prioridad' => 'media',
            ];
        }
    }

    if (function_exists('notificaciones_cartera_vencida')) {
        $n = count(notificaciones_cartera_vencida($pdo, $idPlantel));
        if ($n > 0) {
            $out[] = [
                'clave' => 'cartera',
                'titulo' => 'Cartera vencida',
                'valor' => (string) min($n, 99) . ($n > 99 ? '+' : ''),
                'enlace' => 'reporte_vencimientos',
                'prioridad' => 'alta',
            ];
        }
    }

    return $out;
}

function operativo_busqueda_puede(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('alumno_portal_es_vista_simulada') && alumno_portal_es_vista_simulada()) {
        return true;
    }
    if (function_exists('notificaciones_perfil_usuario')) {
        $p = notificaciones_perfil_usuario();
        if (in_array($p, ['recepcion', 'director'], true)) {
            return true;
        }
    }
    if (function_exists('rbac_cap')) {
        return rbac_cap('menu_consulta_adeudo') || rbac_cap('menu_punto_venta');
    }

    return false;
}

/** @return list<array{id_alumno:int, numero_control:string, nombre_completo:string}> */
function operativo_busqueda_sugerencias(PDO $pdo, string $q, int $idPlantel, int $limite = 8): array
{
    $q = trim($q);
    if ($q === '' || strlen($q) < 2) {
        return [];
    }
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        'SELECT id_alumno, numero_control, matricula,
                CONCAT(nombres, \' \', apellido_paterno, \' \', COALESCE(apellido_materno,\'\')) AS nombre_completo
         FROM alumnos
         WHERE id_plantel = ? AND estado = \'activo\'
           AND (numero_control LIKE ? OR matricula LIKE ? OR nombres LIKE ?
                OR apellido_paterno LIKE ? OR CONCAT(nombres, \' \', apellido_paterno) LIKE ?)
         ORDER BY apellido_paterno, nombres
         LIMIT ' . max(1, min(15, $limite))
    );
    $st->execute([$idPlantel, $like, $like, $like, $like, $like]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function operativo_busqueda_rapida_alumno(PDO $pdo, string $q, int $idPlantel): array
{
    if (!operativo_busqueda_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $q = trim($q);
    if ($q === '') {
        return ['ok' => false, 'message' => 'Indique nÃºmero de control o nombre del alumno'];
    }
    if (!function_exists('pago_buscar_alumno_control')) {
        return ['ok' => false, 'message' => 'BÃºsqueda no disponible'];
    }

    $al = pago_buscar_alumno_control($pdo, $q, $idPlantel);
    if (!$al) {
        $sug = operativo_busqueda_sugerencias($pdo, $q, $idPlantel, 5);
        if ($sug !== []) {
            return [
                'ok' => false,
                'message' => 'Varios alumnos coinciden; elija uno de la lista',
                'sugerencias' => $sug,
            ];
        }

        return ['ok' => false, 'message' => 'Alumno no encontrado en este plantel'];
    }

    $idAlumno = (int) $al['id_alumno'];
    $ec = pago_estado_cuenta($pdo, $idAlumno);
    $adeudo = 0.0;
    $pendientes = [];

    if (!empty($ec['ok'])) {
        foreach ($ec['inscripciones'] ?? [] as $ins) {
            $adeudo += (float) ($ins['adeudo'] ?? 0);
        }
        foreach ($ec['lineas_adeudo'] ?? [] as $ln) {
            if (count($pendientes) >= 4) {
                break;
            }
            $saldo = (float) ($ln['saldo'] ?? 0);
            if ($saldo <= 0.009) {
                continue;
            }
            $pendientes[] = [
                'concepto' => (string) ($ln['detalle'] ?? $ln['periodo'] ?? 'Colegiatura'),
                'saldo' => round($saldo, 2),
                'saldo_fmt' => catalog_format_mxn($saldo),
            ];
        }
    }

    $nConst = 0;
    if (function_exists('documento_cobros_pendientes_alumno')) {
        foreach (documento_cobros_pendientes_alumno($pdo, $idAlumno, $idPlantel) as $doc) {
            $m = catalog_money($doc['precio'] ?? 0);
            if ($m <= 0.009) {
                continue;
            }
            $adeudo += $m;
            $nConst++;
            if (count($pendientes) < 5) {
                $pendientes[] = [
                    'concepto' => 'Constancia â€” folio ' . ($doc['folio'] ?? ''),
                    'saldo' => $m,
                    'saldo_fmt' => catalog_format_mxn($m),
                ];
            }
        }
    }

    if (function_exists('certificacion_cobros_pendientes_alumno')) {
        foreach (certificacion_cobros_pendientes_alumno($pdo, $idAlumno, $idPlantel) as $cert) {
            $m = catalog_money($cert['precio_cobrado'] ?? 0);
            if ($m <= 0.009) {
                continue;
            }
            $adeudo += $m;
            if (count($pendientes) < 5) {
                $pendientes[] = [
                    'concepto' => 'CertificaciÃ³n â€” ' . ($cert['certificacion'] ?? 'Cert'),
                    'saldo' => $m,
                    'saldo_fmt' => catalog_format_mxn($m),
                ];
            }
        }
    }

    $ultimoPago = null;
    if (function_exists('pago_listar_alumno')) {
        $pagos = pago_listar_alumno($pdo, $idAlumno);
        if ($pagos !== []) {
            $last = $pagos[count($pagos) - 1];
            $ultimoPago = [
                'fecha' => (string) ($last['creado_en'] ?? ''),
                'fecha_fmt' => !empty($last['creado_en'])
                    ? date('d/m/Y H:i', strtotime((string) $last['creado_en']))
                    : 'â€”',
                'monto' => round((float) ($last['monto'] ?? 0), 2),
                'monto_fmt' => catalog_format_mxn((float) ($last['monto'] ?? 0)),
                'concepto' => pago_label_tipo((string) ($last['tipo'] ?? ''))
                    . (!empty($last['periodo_ref']) ? ' Â· ' . $last['periodo_ref'] : ''),
                'forma' => (string) ($last['forma_pago'] ?? ''),
            ];
        }
    }

    $grupos = function_exists('alumno_portal_grupos_activos')
        ? alumno_portal_grupos_activos($pdo, $idAlumno)
        : [];

    $documentos = [];
    if (function_exists('documento_mostrador_listar_alumno')) {
        foreach (documento_mostrador_listar_alumno($pdo, $idAlumno, $idPlantel, 4) as $doc) {
            $documentos[] = [
                'folio' => $doc['folio'] ?? '',
                'tipo' => $doc['tipo_label'] ?? '',
                'estado' => $doc['estado_label'] ?? '',
                'pdf_url' => $doc['pdf_url'] ?? null,
            ];
        }
    }

    $control = (string) ($al['numero_control'] ?? $al['matricula'] ?? '');

    return [
        'ok' => true,
        'alumno' => [
            'id_alumno' => $idAlumno,
            'nombre' => (string) ($al['nombre_completo'] ?? ''),
            'numero_control' => $control,
            'estado' => (string) ($al['estado'] ?? ''),
            'especialidad' => (string) ($al['especialidad_nombre'] ?? ''),
        ],
        'grupos' => array_map(static function (array $g): string {
            $txt = (string) ($g['clave'] ?? '');
            if (!empty($g['especialidad'])) {
                $txt .= ' â€” ' . $g['especialidad'];
            }
            if (!empty($g['profesor'])) {
                $txt .= ' (' . $g['profesor'] . ')';
            }

            return $txt;
        }, $grupos),
        'adeudo_total' => round($adeudo, 2),
        'adeudo_fmt' => catalog_format_mxn($adeudo),
        'tiene_adeudo' => $adeudo > 0.009,
        'pendientes' => $pendientes,
        'constancias_pendientes' => $nConst,
        'ultimo_pago' => $ultimoPago,
        'documentos' => $documentos,
        'puede_pos' => function_exists('rbac_cap') && rbac_cap('menu_punto_venta'),
        'puede_mostrador' => function_exists('documento_puede_mostrador') && documento_puede_mostrador(),
        'control' => $control,
    ];
}
