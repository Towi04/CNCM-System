<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !certificacion_puede_acceder()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'catalogo') {
    $q = trim($_GET['q'] ?? '');
    hay_json_response([
        'status' => 'ok',
        'certificaciones' => certificacion_listar_catalogo($pdo, $idPlantel, $q !== '' ? $q : null),
        'tipos_documento' => certificacion_tipos_documento(),
        'estados' => certificacion_estados_etiquetas(),
        'familias' => certificacion_familias(),
        'campos_acceso_labels' => certificacion_campos_acceso_labels(),
    ]);
    exit;
}

if ($action === 'detalle') {
    $id = (int) ($_GET['id_producto'] ?? 0);
    $det = certificacion_obtener_detalle($pdo, $id);
    if (!$det) {
        hay_json_response(['status' => 'error', 'message' => 'Certificación no encontrada']);
        exit;
    }
    $camposAsesor = certificacion_campos_para_producto($pdo, $id, 'asesor');
    if (!certificacion_puede_administrar()) {
        $camposAsesor = certificacion_campos_filtrar_preregistro_asesor($camposAsesor);
    }
    $camposAlumno = certificacion_campos_para_producto($pdo, $id, 'alumno');
    hay_json_response([
        'status' => 'ok',
        'certificacion' => $det,
        'tipos_documento' => certificacion_tipos_documento(),
        'campos_asesor' => $camposAsesor,
        'campos_alumno' => $camposAlumno,
    ]);
    exit;
}

if ($action === 'comision_defaults') {
    $id = (int) ($_GET['id_producto'] ?? 0);
    if ($id <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Producto inválido']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'defaults' => comision_cert_defaults_producto($pdo, $id),
        'solo_lectura' => !certificacion_puede_administrar(),
    ]);
    exit;
}

if ($action === 'campos_catalogo') {
    certificacion_campos_ensure_schema($pdo);
    $st = $pdo->query(
        'SELECT clave, etiqueta, tipo, categoria FROM certificacion_campo_catalogo WHERE activo = 1 ORDER BY categoria, etiqueta'
    );
    hay_json_response(['status' => 'ok', 'campos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'campos_producto') {
    $id = (int) ($_GET['id_producto'] ?? 0);
    hay_json_response([
        'status' => 'ok',
        'campos' => certificacion_campos_para_producto($pdo, $id),
    ]);
    exit;
}

if ($action === 'guardar_campos_producto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!certificacion_puede_administrar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $id = (int) ($_POST['id_producto'] ?? 0);
    $raw = $_POST['campos'] ?? '[]';
    $campos = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
    certificacion_campos_guardar_producto($pdo, $id, $campos);
    hay_json_response(['status' => 'ok', 'message' => 'Campos guardados']);
    exit;
}

if ($action === 'solicitudes') {
    $estado = trim($_GET['estado'] ?? '');
    $q = trim($_GET['q'] ?? '');
    hay_json_response([
        'status' => 'ok',
        'solicitudes' => certificacion_listar_solicitudes(
            $pdo,
            $idPlantel,
            $estado !== '' ? $estado : null,
            $q !== '' ? $q : null
        ),
        'estados' => certificacion_estados_etiquetas(),
    ]);
    exit;
}

if ($action === 'solicitud') {
    $id = (int) ($_GET['id_solicitud'] ?? 0);
    $sol = certificacion_obtener_solicitud($pdo, $id, $idPlantel);
    if (!$sol) {
        hay_json_response(['status' => 'error', 'message' => 'Solicitud no encontrada']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'solicitud' => $sol,
        'tipos_documento' => certificacion_tipos_documento(),
        'estados' => certificacion_estados_etiquetas(),
        'campos_acceso_labels' => certificacion_campos_acceso_labels(),
        'puede_supervisar' => certificacion_puede_supervisar(),
        'comision_historial' => comision_cert_historial_solicitud($pdo, $id),
    ]);
    exit;
}

if ($action === 'alumnos') {
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT id_alumno, numero_control,
            CONCAT(nombres, ' ', apellido_paterno, ' ', IFNULL(apellido_materno,'')) AS nombre_completo
            FROM alumnos WHERE id_plantel = ? AND estado = 'activo' AND numero_control NOT LIKE 'PUB-%'";
    $params = [$idPlantel];
    if ($q !== '') {
        $sql .= ' AND (numero_control LIKE ? OR nombres LIKE ? OR apellido_paterno LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like]);
    }
    $sql .= ' ORDER BY apellido_paterno, nombres LIMIT 40';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    hay_json_response(['status' => 'ok', 'alumnos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'crear_solicitud' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $datosForm = $_POST['datos_formulario'] ?? null;
        if (is_string($datosForm) && $datosForm !== '') {
            $datosForm = json_decode($datosForm, true) ?: [];
        }
        $res = certificacion_crear_solicitud($pdo, $idPlantel, [
            'id_producto' => (int) ($_POST['id_producto'] ?? 0),
            'id_alumno' => (int) ($_POST['id_alumno'] ?? 0),
            'nombres' => $_POST['nombres'] ?? '',
            'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
            'apellido_materno' => $_POST['apellido_materno'] ?? '',
            'datos_formulario' => is_array($datosForm) ? $datosForm : null,
            'precio_cobrado' => $_POST['precio_cobrado'] ?? null,
            'comision_asesor' => $_POST['comision_asesor'] ?? null,
            'comision_gerente' => $_POST['comision_gerente'] ?? null,
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'fecha_solicitada' => $_POST['fecha_solicitada'] ?? $_POST['fecha_examen'] ?? '',
            'hora_solicitada' => $_POST['hora_solicitada'] ?? '',
            'notas' => $_POST['notas'] ?? '',
        ]);
        hay_json_response($res['ok']
            ? ['status' => 'ok'] + $res
            : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    } catch (Throwable $e) {
        error_log('certificacion crear_solicitud: ' . $e->getMessage());
        hay_json_response([
            'status' => 'error',
            'message' => 'Error al registrar solicitud. Contacte al administrador si persiste.',
        ], 500);
    }
    exit;
}

if ($action === 'actualizar_solicitud' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_solicitud'] ?? 0);
    $res = certificacion_actualizar_solicitud($pdo, $id, $idPlantel, [
        'estado' => $_POST['estado'] ?? '',
        'fecha_examen' => $_POST['fecha_examen'] ?? null,
        'fecha_solicitada' => $_POST['fecha_solicitada'] ?? null,
        'hora_solicitada' => $_POST['hora_solicitada'] ?? null,
        'notas' => $_POST['notas'] ?? null,
    ]);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => $res['message']]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'confirmar_fecha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_solicitud'] ?? 0);
    $res = certificacion_confirmar_fecha($pdo, $id, $idPlantel, [
        'fecha_confirmada' => $_POST['fecha_confirmada'] ?? '',
        'hora_confirmada' => $_POST['hora_confirmada'] ?? '',
        'sede_direccion' => $_POST['sede_direccion'] ?? '',
        'contacto_supervisor' => $_POST['contacto_supervisor'] ?? '',
        'contacto_nombre' => $_POST['contacto_nombre'] ?? '',
    ]);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => $res['message']]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'guardar_accesos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_solicitud'] ?? 0);
    $res = certificacion_guardar_accesos($pdo, $id, $idPlantel, $_POST);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => $res['message']]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'reagendar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_solicitud'] ?? 0);
    $res = certificacion_reagendar($pdo, $id, $idPlantel, [
        'fecha_nueva' => $_POST['fecha_nueva'] ?? '',
        'hora_nueva' => $_POST['hora_nueva'] ?? '',
        'motivo' => $_POST['motivo'] ?? '',
    ]);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => $res['message']]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'subir_documento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_solicitud'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? '');
    if (empty($_FILES['archivo'])) {
        hay_json_response(['status' => 'error', 'message' => 'Archivo requerido']);
        exit;
    }
    $res = certificacion_subir_documento($pdo, $id, $idPlantel, $tipo, $_FILES['archivo']);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => $res['message']]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'guardar_meta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!certificacion_puede_administrar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso para editar catálogo'], 403);
        exit;
    }
    $id = (int) ($_POST['id_producto'] ?? 0);
    $docs = $_POST['documentos_requeridos'] ?? [];
    if (is_string($docs)) {
        $docs = json_decode($docs, true) ?: [];
    }
    $reglamentoPdf = trim($_POST['reglamento_pdf'] ?? '');
    if (!empty($_FILES['reglamento_archivo']['tmp_name'])) {
        $up = certificacion_guardar_archivo($_FILES['reglamento_archivo'], 'reglamentos', 'reg_' . $id);
        if ($up['ok'] && !empty($up['path'])) {
            $reglamentoPdf = $up['path'];
        }
    }
    $res = certificacion_guardar_meta($pdo, $id, [
        'familia' => $_POST['familia'] ?? 'certiport',
        'organismo' => $_POST['organismo'] ?? '',
        'protocolo' => $_POST['protocolo'] ?? '',
        'reglamento_texto' => $_POST['reglamento_texto'] ?? '',
        'reglamento_pdf' => $reglamentoPdf,
        'requiere_reglamento_firmado' => !empty($_POST['requiere_reglamento_firmado']),
        'software_nombre' => $_POST['software_nombre'] ?? '',
        'software_url' => $_POST['software_url'] ?? '',
        'software_instrucciones' => $_POST['software_instrucciones'] ?? '',
        'documentos_requeridos' => $docs,
        'notas_asesor' => $_POST['notas_asesor'] ?? '',
        'comision_asesor_default' => $_POST['comision_asesor_default'] ?? 0,
        'comision_gerente_default' => $_POST['comision_gerente_default'] ?? 0,
    ]);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => $res['message']]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'productos_sin_meta') {
    if (!certificacion_puede_administrar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $st = $pdo->query(
        'SELECT p.id_producto, p.clave, p.nombre, p.precio, p.es_certificacion
         FROM productos p
         WHERE p.activo = 1
         ORDER BY p.nombre ASC'
    );
    hay_json_response(['status' => 'ok', 'productos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
