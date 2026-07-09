<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantelSesion = plantel_scope_id($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'alumnos') {
    $q = trim($_GET['q'] ?? '');
    $idPlantel = $idPlantelSesion;
    $sql = "SELECT id_alumno, numero_control, matricula,
            CONCAT(nombres, ' ', apellido_paterno, ' ', IFNULL(apellido_materno,'')) AS nombre_completo
            FROM alumnos
            WHERE id_plantel = ? AND estado = 'activo'";
    $params = [$idPlantel];
    if ($q !== '') {
        $sql .= ' AND (numero_control LIKE ? OR matricula LIKE ? OR nombres LIKE ? OR apellido_paterno LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $sql .= ' ORDER BY apellido_paterno, nombres LIMIT 40';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    hay_json_response(['status' => 'ok', 'alumnos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'preregistros') {
    $q = trim($_GET['q'] ?? '');
    $rows = preregistro_listar($pdo, $idPlantelSesion, [
        'estado' => 'activo',
        'q' => $q !== '' ? $q : null,
    ]);
    $out = [];
    foreach (array_slice($rows, 0, 40) as $r) {
        $out[] = [
            'id_preregistro' => (int) $r['id_preregistro'],
            'nombre' => trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? '') . ' ' . ($r['apellido_materno'] ?? '')),
            'telefono' => $r['telefono'] ?? '',
            'tiene_apartado' => (int) ($r['tiene_apartado'] ?? 0),
            'monto_apartado' => $r['monto_apartado'],
            'especialidad_nombre' => $r['especialidad_nombre'] ?? '',
        ];
    }
    hay_json_response(['status' => 'ok', 'preregistros' => $out]);
    exit;
}

if ($action === 'pendientes') {
    $idAlumno = (int) ($_GET['id_alumno'] ?? 0);
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    if ($idAlumno <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno requerido']);
        exit;
    }
    $ec = pago_estado_cuenta($pdo, $idAlumno);
    if (empty($ec['ok'])) {
        hay_json_response(['status' => 'error', 'message' => $ec['message'] ?? 'Sin datos']);
        exit;
    }
    $lineas = [];
    $total = 0.0;
    $formaPago = 'mensual';
    foreach ($ec['inscripciones'] ?? [] as $ins) {
        if ($idEsp > 0 && (int) ($ins['id_especialidad'] ?? 0) !== $idEsp) {
            continue;
        }
        $formaPago = $ins['forma_pago'] ?? 'mensual';
        foreach ($ins['lineas_pendientes'] ?? [] as $ln) {
            $saldo = (float) ($ln['saldo'] ?? 0);
            if ($saldo <= 0.009) {
                continue;
            }
            $periodo = $ln['periodo'] ?? '';
            $fechaLim = '';
            if (preg_match('/^\d{4}-\d{2}$/', (string) $periodo)) {
                $fechaLim = (new DateTimeImmutable($periodo . '-01'))->modify('last day of this month')->format('Y-m-d');
            } elseif (preg_match('/^\d{4}-W\d+$/i', (string) $periodo)) {
                $fechaLim = date('Y-m-d');
            }
            $lineas[] = [
                'concepto' => $ln['detalle'] ?? $periodo,
                'periodo' => $periodo,
                'monto' => (float) ($ln['monto_esperado'] ?? 0),
                'saldo' => $saldo,
                'fecha_limite' => $fechaLim,
                'status' => 'Pendiente',
                'tipo' => $ln['tipo'] ?? 'mensualidad',
                'id_especialidad' => (int) ($ln['id_especialidad'] ?? 0),
            ];
            $total += $saldo;
        }
    }

    if (function_exists('certificacion_cobros_pendientes_alumno')) {
        foreach (certificacion_cobros_pendientes_alumno($pdo, $idAlumno, $idPlantelSesion) as $cert) {
            $saldoCert = catalog_money($cert['precio_cobrado'] ?? 0);
            if ($saldoCert <= 0.009) {
                continue;
            }
            $lineas[] = [
                'concepto' => 'Certificación — ' . ($cert['certificacion'] ?? 'Cert'),
                'periodo' => '',
                'monto' => $saldoCert,
                'saldo' => $saldoCert,
                'fecha_limite' => date('Y-m-d'),
                'status' => 'Pendiente cobro',
                'tipo' => 'certificacion',
                'id_especialidad' => 0,
                'id_solicitud_cert' => (int) ($cert['id_solicitud'] ?? 0),
                'id_producto' => (int) ($cert['id_producto'] ?? 0),
                'comision_asesor' => catalog_money($cert['comision_asesor'] ?? 0),
                'comision_gerente' => catalog_money($cert['comision_gerente'] ?? 0),
            ];
            $total += $saldoCert;
        }
    }

    if (function_exists('documento_cobros_pendientes_alumno')) {
        foreach (documento_cobros_pendientes_alumno($pdo, $idAlumno, $idPlantelSesion) as $doc) {
            $saldoDoc = catalog_money($doc['precio'] ?? 0);
            if ($saldoDoc <= 0.009) {
                continue;
            }
            $lineas[] = [
                'concepto' => 'Constancia — folio ' . ($doc['folio'] ?? ''),
                'periodo' => '',
                'monto' => $saldoDoc,
                'saldo' => $saldoDoc,
                'fecha_limite' => date('Y-m-d'),
                'status' => 'Pendiente cobro',
                'tipo' => 'constancia',
                'id_especialidad' => 0,
                'id_documento' => (int) ($doc['id_documento'] ?? 0),
                'id_producto' => (int) ($doc['id_producto'] ?? 0),
            ];
            $total += $saldoDoc;
        }
    }

    $idEscuelaOrigen = 0;
    $escuelaOrigenNombre = '';
    if (function_exists('plantel_column_exists') && plantel_column_exists($pdo, 'alumnos', 'id_escuela_origen')) {
        $stAl = $pdo->prepare(
            'SELECT a.id_escuela_origen, ee.nombre AS escuela_nombre
             FROM alumnos a
             LEFT JOIN escuelas_externas ee ON ee.id_escuela = a.id_escuela_origen
             WHERE a.id_alumno = ? LIMIT 1'
        );
        $stAl->execute([$idAlumno]);
        $alRow = $stAl->fetch(PDO::FETCH_ASSOC) ?: [];
        $idEscuelaOrigen = (int) ($alRow['id_escuela_origen'] ?? 0);
        $escuelaOrigenNombre = (string) ($alRow['escuela_nombre'] ?? '');
    }

    hay_json_response([
        'status' => 'ok',
        'lineas' => $lineas,
        'total_pendiente' => round($total, 2),
        'forma_pago' => $formaPago,
        'id_escuela_origen' => $idEscuelaOrigen,
        'escuela_origen_nombre' => $escuelaOrigenNombre,
        'inscripciones' => array_map(static function ($ins) {
            $idEspIns = 0;
            foreach ($ins['lineas_pendientes'] ?? [] as $ln) {
                $idEspIns = (int) ($ln['id_especialidad'] ?? 0);
                if ($idEspIns > 0) {
                    break;
                }
            }

            return [
                'id_especialidad' => $idEspIns,
                'especialidad' => $ins['especialidad'] ?? '',
                'adeudo' => $ins['adeudo'] ?? 0,
            ];
        }, $ec['inscripciones'] ?? []),
    ]);
    exit;
}

if ($action === 'apartado_preregistro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!preregistro_puede_acceder()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
        exit;
    }
    $idPrereg = (int) ($_POST['id_preregistro'] ?? 0);
    $monto = catalog_money($_POST['monto'] ?? $_POST['monto_apartado'] ?? 0);
    $idPlantel = $idPlantelSesion;
    if ($idPrereg <= 0 || $monto <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Indica un monto de apartado válido']);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT id_preregistro, estado, id_alumno_vinculado FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1'
    );
    $stmt->execute([$idPrereg, $idPlantel]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        hay_json_response(['status' => 'error', 'message' => 'Pre-registro no encontrado']);
        exit;
    }
    if (in_array($row['estado'], ['perdido', 'inscrito'], true)) {
        hay_json_response(['status' => 'error', 'message' => 'No se puede registrar apartado en este estado']);
        exit;
    }
    $pdo->prepare(
        'UPDATE preregistros SET tiene_apartado = 1, monto_apartado = ? WHERE id_preregistro = ? AND id_plantel = ?'
    )->execute([$monto, $idPrereg, $idPlantel]);
    if (!empty($row['id_alumno_vinculado'] ?? null)) {
        preregistro_aplicar_apartado_a_alumno($pdo, $idPrereg);
    }
    hay_json_response([
        'status' => 'ok',
        'message' => 'Apartado registrado: ' . catalog_format_mxn($monto),
    ]);
    exit;
}

if ($action === 'buscar') {
    $q = trim($_GET['q'] ?? '');
    $al = pago_buscar_alumno_control($pdo, $q, $idPlantelSesion);
    if (!$al) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno no encontrado']);
        exit;
    }
    $ec = pago_estado_cuenta($pdo, (int) $al['id_alumno']);
    $insc = pago_inscripciones_alumno($pdo, (int) $al['id_alumno']);
    $becas = pago_becas_vigentes($pdo, (int) $al['id_alumno'], date('Y-m-d'));
    $promos = $pdo->query('SELECT id_promocion, clave, nombre, tipo, valor FROM promociones_descuento WHERE activo = 1')->fetchAll(PDO::FETCH_ASSOC);
    hay_json_response([
        'status' => 'ok',
        'alumno' => $al,
        'estado_cuenta' => $ec,
        'inscripciones' => $insc,
        'becas' => $becas,
        'promociones' => $promos,
    ]);
    exit;
}

if ($action === 'productos') {
    $rows = catalog_listar_productos($pdo, ['activo' => '1', 'visible' => '1']);
    $idPlantel = plantel_scope_id($pdo);
    foreach ($rows as &$r) {
        $st = $pdo->prepare('SELECT existencia FROM producto_inventario WHERE id_producto = ? AND id_plantel = ?');
        $st->execute([(int) $r['id_producto'], $idPlantel]);
        $r['existencia'] = (int) ($st->fetchColumn() ?: 0);
    }
    unset($r);
    hay_json_response(['status' => 'ok', 'productos' => $rows]);
    exit;
}

if ($action === 'cobrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    if ($idAlumno > 0 && !plantel_enforce_alumno($pdo, $idAlumno, $idPlantelSesion)) {
        hay_json_response(['status' => 'error', 'message' => 'El alumno no pertenece a este plantel']);
        exit;
    }
    $folio = trim($_POST['folio'] ?? '');
    $medioPago = strtolower(trim((string) ($_POST['medio_pago'] ?? '')));
    $forma = trim($_POST['forma_pago'] ?? '');
    if ($forma === '' && $medioPago !== '') {
        $forma = function_exists('operativo_cncm_medio_pago_forma')
            ? operativo_cncm_medio_pago_forma($medioPago)
            : ucfirst($medioPago);
    }
    if ($forma === '') {
        $forma = 'Efectivo';
    }
    $cobroPrecioLista = !empty($_POST['cobro_precio_lista']);
    $montoReferencia = ($_POST['monto_referencia'] ?? '') !== '' ? catalog_money($_POST['monto_referencia']) : null;
    $montoApoyo = ($_POST['monto_apoyo'] ?? '') !== '' ? catalog_money($_POST['monto_apoyo']) : null;
    $motivoPromo = trim($_POST['motivo_descuento'] ?? '');
    $idAutoriza = (int) ($_POST['id_autoriza'] ?? 0) ?: null;
    $distribuir = !empty($_POST['distribuir_periodos']);
    $montoCobro = catalog_money($_POST['monto'] ?? 0);
    $cantPeriodos = max(0, (int) ($_POST['cant_periodos'] ?? 0));
    $idEspCobro = (int) ($_POST['id_especialidad'] ?? 0) ?: null;
    $idSolicitudCert = (int) ($_POST['id_solicitud_cert'] ?? 0);
    $idDocumento = (int) ($_POST['id_documento'] ?? 0);
    $origenCartas = !empty($_POST['origen_cartas']);

    if ($origenCartas && $idAlumno > 0) {
        $stEsc = $pdo->prepare('SELECT id_escuela_origen FROM alumnos WHERE id_alumno = ? LIMIT 1');
        $stEsc->execute([$idAlumno]);
        $idEscuela = (int) $stEsc->fetchColumn();
        if ($idEscuela <= 0) {
            hay_json_response([
                'status' => 'error',
                'message' => 'El alumno no tiene escuela de origen (cartas). Regístrela en pre-registro o expediente.',
            ]);
            exit;
        }
        if (function_exists('escuelas_ultimo_asesor_visita')) {
            $idRepartidor = escuelas_ultimo_asesor_visita($pdo, $idEscuela);
            if ($idRepartidor <= 0) {
                hay_json_response([
                    'status' => 'error',
                    'message' => 'No hay visita de asesor registrada para esa escuela. Regístrela en Escuelas (gerente) antes de cobrar cartas.',
                ]);
                exit;
            }
        }
    }

    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items)) {
        $items = [];
    }

    if ($idSolicitudCert > 0 && $idAlumno > 0) {
        $solCert = certificacion_obtener_solicitud($pdo, $idSolicitudCert, $idPlantelSesion);
        if (!$solCert || (int) ($solCert['id_alumno'] ?? 0) !== $idAlumno) {
            hay_json_response(['status' => 'error', 'message' => 'Certificación no válida para este alumno']);
            exit;
        }
        if (!empty($solCert['id_pago'])) {
            hay_json_response(['status' => 'error', 'message' => 'Esta certificación ya fue pagada']);
            exit;
        }
        $saldoCert = catalog_money($solCert['precio_cobrado'] ?? 0);
        if ($montoCobro + 0.01 < $saldoCert) {
            hay_json_response([
                'status' => 'error',
                'message' => 'El monto debe cubrir la certificación (' . catalog_format_mxn($saldoCert) . ')',
            ]);
            exit;
        }
        $items = [[
            'tipo' => 'otro',
            'monto' => min($montoCobro, $saldoCert),
            'id_especialidad' => null,
            'id_producto' => (int) ($solCert['id_producto'] ?? 0),
            'concepto' => 'Certificación — ' . ($solCert['certificacion'] ?? ''),
            'periodo_ref' => null,
            'id_solicitud_cert' => $idSolicitudCert,
        ]];
    } elseif ($idDocumento > 0 && $idAlumno > 0) {
        documento_ensure_schema($pdo);
        $stDoc = $pdo->prepare(
            'SELECT d.*, COALESCE(p.precio, 0) AS precio FROM alumno_documento d
             LEFT JOIN productos p ON p.id_producto = d.id_producto
             WHERE d.id_documento = ? AND d.id_plantel = ? AND d.id_alumno = ? AND d.tipo = \'constancia\''
        );
        $stDoc->execute([$idDocumento, $idPlantelSesion, $idAlumno]);
        $docRow = $stDoc->fetch(PDO::FETCH_ASSOC);
        if (!$docRow || ($docRow['estado'] ?? '') !== 'pendiente_pago') {
            hay_json_response(['status' => 'error', 'message' => 'Constancia no válida para este alumno']);
            exit;
        }
        $saldoDoc = catalog_money($docRow['precio'] ?? 0);
        if ($montoCobro + 0.01 < $saldoDoc) {
            hay_json_response([
                'status' => 'error',
                'message' => 'El monto debe cubrir la constancia (' . catalog_format_mxn($saldoDoc) . ')',
            ]);
            exit;
        }
        $items = [[
            'tipo' => 'producto',
            'monto' => min($montoCobro, $saldoDoc),
            'id_especialidad' => null,
            'id_producto' => (int) ($docRow['id_producto'] ?? 0),
            'concepto' => 'Constancia — folio ' . ($docRow['folio'] ?? ''),
            'periodo_ref' => null,
            'id_documento' => $idDocumento,
        ]];
    } elseif ($distribuir && $idAlumno > 0 && $montoCobro > 0) {
        $items = pago_construir_items_cobro(
            $pdo,
            $idAlumno,
            $montoCobro,
            $idEspCobro,
            $cantPeriodos > 0 ? $cantPeriodos : null
        );
    } elseif ($montoCobro > 0 && $items === []) {
        $items = [[
            'tipo' => 'abono',
            'monto' => $montoCobro,
            'id_especialidad' => $idEspCobro,
            'concepto' => 'Abono punto de venta',
            'periodo_ref' => null,
        ]];
    }

    if ($items === []) {
        hay_json_response(['status' => 'error', 'message' => 'Sin conceptos a cobrar']);
        exit;
    }

    $pagos = [];
    $pdfConstancia = null;
    $idDocPagado = 0;
    $ticketInscripcionUrl = null;
    foreach ($items as $it) {
        $idSolCertItem = (int) ($it['id_solicitud_cert'] ?? 0);
        $idDocItem = (int) ($it['id_documento'] ?? 0);
        $res = pago_registrar($pdo, [
            'id_alumno' => $idAlumno,
            'id_especialidad' => (int) ($it['id_especialidad'] ?? 0) ?: $idEspCobro,
            'id_alumno_especialidad' => (int) ($it['id_alumno_especialidad'] ?? 0) ?: null,
            'tipo' => $it['tipo'] ?? 'abono',
            'monto' => $it['monto'] ?? 0,
            'id_producto' => (int) ($it['id_producto'] ?? 0) ?: null,
            'cantidad' => (int) ($it['cantidad'] ?? 1),
            'id_promocion' => (int) ($it['id_promocion'] ?? 0) ?: null,
            'motivo_descuento' => $motivoPromo,
            'id_autoriza' => $idAutoriza,
            'folio' => $folio,
            'forma_pago_efectivo' => $forma,
            'medio_pago' => $medioPago !== '' ? $medioPago : null,
            'cobro_precio_lista' => $cobroPrecioLista,
            'monto_referencia' => $montoReferencia,
            'monto_apoyo' => $montoApoyo,
            'etiqueta_apoyo' => $cobroPrecioLista ? 'Precio lista (sin apoyo)' : 'Apoyo educativo',
            'origen_cartas' => !empty($_POST['origen_cartas']),
            'comision_asesor_manual' => ($_POST['comision_asesor_manual'] ?? '') !== '' ? $_POST['comision_asesor_manual'] : null,
            'comision_gerente_sobre' => ($_POST['comision_gerente_sobre'] ?? '') !== '' ? $_POST['comision_gerente_sobre'] : null,
            'excluir_tabulador' => !empty($_POST['excluir_tabulador']),
            'id_autoriza_director' => (int) ($_POST['id_autoriza_director'] ?? 0) ?: null,
            'concepto' => $it['concepto'] ?? '',
            'periodo_ref' => $it['periodo_ref'] ?? null,
            'id_solicitud_cert' => $idSolCertItem > 0 ? $idSolCertItem : null,
        ]);
        if (!$res['ok']) {
            hay_json_response(['status' => 'error', 'message' => $res['message']]);
            exit;
        }
        if (($it['tipo'] ?? '') === 'inscripcion' && !empty($res['id_pago']) && $ticketInscripcionUrl === null) {
            $ticketInscripcionUrl = hay_asset_url(
                'views/ticket_pago_inscripcion.php?id_pago=' . (int) $res['id_pago'] . '&print=1'
            );
        }
        if ($idSolCertItem > 0 && !empty($res['id_pago'])) {
            certificacion_aplicar_pago(
                $pdo,
                $idSolCertItem,
                (int) $res['id_pago'],
                $idPlantelSesion,
                catalog_money($it['monto'] ?? 0)
            );
        }
        if ($idDocItem > 0 && !empty($res['id_pago']) && function_exists('documento_aplicar_pago_pos')) {
            $docRes = documento_aplicar_pago_pos(
                $pdo,
                $idDocItem,
                (int) $res['id_pago'],
                $idPlantelSesion,
                (int) $_SESSION['user_id']
            );
            if (!empty($docRes['pdf_url'])) {
                $pdfConstancia = $docRes['pdf_url'];
                $idDocPagado = $idDocItem;
            }
        }
        $pagos[] = $res;
    }

    hay_json_response([
        'status' => 'ok',
        'message' => count($pagos) > 1
            ? 'Cobro registrado (' . count($pagos) . ' periodos)'
            : ($idDocPagado > 0 ? 'Constancia pagada y generada' : 'Cobro registrado'),
        'pagos' => $pagos,
        'seccion' => 'punto_venta',
        'pdf_constancia' => $pdfConstancia,
        'ticket_url' => $ticketInscripcionUrl,
        'id_documento' => $idDocPagado > 0 ? $idDocPagado : null,
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
