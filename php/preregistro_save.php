<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada. Vuelva a iniciar sesión.'], 401);
    exit;
}

if (!preregistro_puede_acceder()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

$id = (int) ($_POST['id_preregistro'] ?? 0);
$idPlantel = plantel_scope_id($pdo);
$postPlantel = (int) ($_POST['id_plantel'] ?? $idPlantel);
if ($postPlantel > 0 && $postPlantel !== $idPlantel) {
    hay_json_response(['status' => 'error', 'message' => 'La sede del registro no coincide con el plantel activo'], 403);
    exit;
}
$userId = (int) ($_SESSION['user_id'] ?? 0);

$nombres = trim((string) ($_POST['nombres'] ?? ''));
$apPat = trim((string) ($_POST['apellido_paterno'] ?? ''));
if ($nombres === '' || $apPat === '') {
    hay_json_response(['status' => 'error', 'message' => 'Nombre y apellido paterno son obligatorios']);
    exit;
}

$fechaNac = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
$fechaNac = $fechaNac !== '' ? $fechaNac : null;
$edad = preregistro_calcular_edad($fechaNac);

$medio = $_POST['medio_entero'] ?? 'otro';
$mediosValidos = array_keys(preregistro_labels()['medio_entero']);
if (!in_array($medio, $mediosValidos, true)) {
    $medio = 'otro';
}

$idEscuelaOrigen = (int) ($_POST['id_escuela_origen'] ?? 0) ?: null;
if ($medio === 'cartas') {
    if (function_exists('escuelas_ensure_schema')) {
        escuelas_ensure_schema($pdo);
    }
    if ($idEscuelaOrigen <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Seleccione la escuela de origen cuando el medio es cartas']);
        exit;
    }
    $stEsc = $pdo->prepare('SELECT 1 FROM escuelas_externas WHERE id_escuela = ? AND id_plantel = ? AND activo = 1 LIMIT 1');
    $stEsc->execute([$idEscuelaOrigen, $idPlantel]);
    if (!$stEsc->fetchColumn()) {
        hay_json_response(['status' => 'error', 'message' => 'Escuela de origen no válida']);
        exit;
    }
} else {
    $idEscuelaOrigen = null;
}

$grado = $_POST['grado_estudios'] ?? null;
$gradosValidos = array_keys(preregistro_labels()['grado_estudios']);
if ($grado !== null && $grado !== '' && !in_array($grado, $gradosValidos, true)) {
    $grado = null;
}

$data = [
    'id_plantel' => $idPlantel,
    'id_usuario_registro' => $userId,
    'id_especialidad' => (int) ($_POST['id_especialidad'] ?? 0) ?: null,
    'nombres' => $nombres,
    'apellido_paterno' => $apPat,
    'apellido_materno' => trim((string) ($_POST['apellido_materno'] ?? '')),
    'fecha_nacimiento' => $fechaNac,
    'edad' => $edad,
    'medio_entero' => $medio,
    'medio_entero_otro' => trim((string) ($_POST['medio_entero_otro'] ?? '')),
    'id_escuela_origen' => $idEscuelaOrigen,
    'domicilio' => trim((string) ($_POST['domicilio'] ?? '')),
    'colonia' => trim((string) ($_POST['colonia'] ?? '')),
    'municipio' => trim((string) ($_POST['municipio'] ?? '')),
    'telefono' => trim((string) ($_POST['telefono'] ?? '')),
    'telefono2' => trim((string) ($_POST['telefono2'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'codigo_postal' => trim((string) ($_POST['codigo_postal'] ?? '')),
    'ocupacion' => trim((string) ($_POST['ocupacion'] ?? '')),
    'grado_estudios' => $grado ?: null,
    'padre_tutor' => trim((string) ($_POST['padre_tutor'] ?? '')),
    'objetivo_inscripcion' => trim((string) ($_POST['objetivo_inscripcion'] ?? '')),
    'enfermedad_cronica' => isset($_POST['enfermedad_cronica']) ? 1 : 0,
    'enfermedad_detalle' => trim((string) ($_POST['enfermedad_detalle'] ?? '')),
    'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
    'requiere_factura' => isset($_POST['requiere_factura']) ? 1 : 0,
    'factura_rfc' => strtoupper(trim((string) ($_POST['factura_rfc'] ?? ''))),
    'factura_curp' => strtoupper(trim((string) ($_POST['factura_curp'] ?? ''))),
    'factura_telefono' => trim((string) ($_POST['factura_telefono'] ?? $_POST['telefono'] ?? '')),
    'factura_razon_social' => trim((string) ($_POST['factura_razon_social'] ?? '')),
    'factura_correo' => trim((string) ($_POST['factura_correo'] ?? $_POST['email'] ?? '')),
    'factura_domicilio_fiscal' => trim((string) ($_POST['factura_domicilio_fiscal'] ?? '')),
];

if (!empty($data['id_especialidad'])) {
    catalog_ensure_especialidad_operativo($pdo);
    $stEsp = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? AND activo = 1 LIMIT 1');
    $stEsp->execute([$data['id_especialidad']]);
    $espRow = $stEsp->fetch(PDO::FETCH_ASSOC);
    if (!$espRow) {
        hay_json_response(['status' => 'error', 'message' => 'Especialidad no válida']);
        exit;
    }
}

$avisoEdad = '';

try {
    $fotoPath = null;
    $csfPath = null;

    $tieneApartado = 0;
    $montoApartado = null;

    if ($id > 0) {
        $old = $pdo->prepare(
            'SELECT foto, factura_constancia_path, tiene_apartado, monto_apartado, estado,
                    id_alumno_vinculado, factura_datos_pendientes
             FROM preregistros WHERE id_preregistro = ? AND id_plantel = ?'
        );
        $old->execute([$id, $idPlantel]);
        $prev = $old->fetch(PDO::FETCH_ASSOC);
        if (!$prev) {
            hay_json_response(['status' => 'error', 'message' => 'Pre-registro no encontrado']);
            exit;
        }
        $idAlumnoInscrito = preregistro_redirect_alumno_id($prev);
        if ($idAlumnoInscrito > 0 && !preregistro_puede_editar($prev)) {
            hay_json_response([
                'status' => 'ok',
                'message' => 'Este prospecto ya está inscrito. Abriendo el perfil del alumno.',
                'seccion' => 'alumno_detalle',
                'params' => 'id=' . $idAlumnoInscrito,
                'id_preregistro' => $id,
            ]);
            exit;
        }
        $fotoPath = $prev['foto'];
        $csfPath = $prev['factura_constancia_path'];
        $tieneApartado = (int) $prev['tiene_apartado'];
        $montoApartado = $prev['monto_apartado'];
    }

    if (!empty($_POST['foto_sesion']) && (string) $_POST['foto_sesion'] === '1') {
        $pendiente = preregistro_foto_sesion_tomar();
        if ($pendiente) {
            preregistro_borrar_archivo($fotoPath);
            $fotoPath = $pendiente;
        }
    } elseif (!empty($_FILES['foto']['tmp_name'])) {
        $up = preregistro_guardar_archivo($_FILES['foto'], PREREG_FOTO_DIR, 'foto');
        if (!$up['ok']) {
            hay_json_response(['status' => 'error', 'message' => $up['message']]);
            exit;
        }
        if ($up['path']) {
            preregistro_borrar_archivo($fotoPath);
            $fotoPath = $up['path'];
        }
    }

    if (!empty($_FILES['factura_constancia']['tmp_name'])) {
        $up = preregistro_guardar_archivo($_FILES['factura_constancia'], PREREG_CSF_DIR, 'csf');
        if (!$up['ok']) {
            hay_json_response(['status' => 'error', 'message' => $up['message']]);
            exit;
        }
        if ($up['path']) {
            preregistro_borrar_archivo($csfPath);
            $csfPath = $up['path'];
        }
    }

    $data['foto'] = $fotoPath;
    $data['factura_constancia_path'] = $csfPath;

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE preregistros SET
                id_especialidad=?, nombres=?, apellido_paterno=?, apellido_materno=?,
                fecha_nacimiento=?, edad=?, medio_entero=?, medio_entero_otro=?, id_escuela_origen=?,
                domicilio=?, colonia=?, municipio=?, telefono=?, telefono2=?, email=?, codigo_postal=?,
                ocupacion=?, grado_estudios=?, padre_tutor=?, objetivo_inscripcion=?,
                enfermedad_cronica=?, enfermedad_detalle=?, observaciones=?,
                tiene_apartado=?, monto_apartado=?, requiere_factura=?,
                factura_rfc=?, factura_curp=?, factura_telefono=?, factura_razon_social=?,
                factura_correo=?, factura_domicilio_fiscal=?, foto=?, factura_constancia_path=?
             WHERE id_preregistro=? AND id_plantel=?'
        );
        $stmt->execute([
            $data['id_especialidad'], $data['nombres'], $data['apellido_paterno'], $data['apellido_materno'],
            $data['fecha_nacimiento'], $data['edad'], $data['medio_entero'], $data['medio_entero_otro'], $data['id_escuela_origen'],
            $data['domicilio'], $data['colonia'], $data['municipio'], $data['telefono'], $data['telefono2'],
            $data['email'], $data['codigo_postal'], $data['ocupacion'], $data['grado_estudios'],
            $data['padre_tutor'], $data['objetivo_inscripcion'], $data['enfermedad_cronica'],
            $data['enfermedad_detalle'], $data['observaciones'], $tieneApartado, $montoApartado,
            $data['requiere_factura'], $data['factura_rfc'], $data['factura_curp'], $data['factura_telefono'],
            $data['factura_razon_social'], $data['factura_correo'], $data['factura_domicilio_fiscal'],
            $fotoPath, $csfPath, $id, $idPlantel,
        ]);
        $idPrereg = $id;
    } else {
        $telDup = preg_replace('/\D+/', '', (string) ($data['telefono'] ?? ''));
        if ($telDup !== '') {
            $dup = $pdo->prepare(
                'SELECT id_preregistro FROM preregistros
                 WHERE id_plantel = ? AND nombres = ? AND apellido_paterno = ?
                   AND REPLACE(REPLACE(REPLACE(telefono, \' \', \'\'), \'-\', \'\'), \'+\', \'\') LIKE ?
                   AND creado_en >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                 ORDER BY id_preregistro DESC LIMIT 1'
            );
            $dup->execute([$idPlantel, $data['nombres'], $data['apellido_paterno'], '%' . $telDup . '%']);
            $dupId = (int) $dup->fetchColumn();
            if ($dupId > 0) {
                hay_json_response([
                    'status' => 'ok',
                    'message' => 'Pre-registro guardado',
                    'seccion' => 'pre_registro_alumnos',
                    'id_preregistro' => $dupId,
                    'duplicado_evitado' => true,
                ]);
                exit;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO preregistros (
                id_plantel, id_usuario_registro, id_especialidad, nombres, apellido_paterno, apellido_materno,
                fecha_nacimiento, edad, medio_entero, medio_entero_otro, id_escuela_origen, domicilio, colonia, municipio,
                telefono, telefono2, email, codigo_postal, ocupacion, grado_estudios, padre_tutor,
                objetivo_inscripcion, enfermedad_cronica, enfermedad_detalle, observaciones,
                tiene_apartado, monto_apartado, requiere_factura, factura_rfc, factura_curp,
                factura_telefono, factura_razon_social, factura_correo, factura_domicilio_fiscal,
                foto, factura_constancia_path
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $idPlantel, $userId, $data['id_especialidad'], $data['nombres'], $data['apellido_paterno'],
            $data['apellido_materno'], $data['fecha_nacimiento'], $data['edad'], $data['medio_entero'],
            $data['medio_entero_otro'], $data['id_escuela_origen'], $data['domicilio'], $data['colonia'], $data['municipio'],
            $data['telefono'], $data['telefono2'], $data['email'], $data['codigo_postal'],
            $data['ocupacion'], $data['grado_estudios'], $data['padre_tutor'], $data['objetivo_inscripcion'],
            $data['enfermedad_cronica'], $data['enfermedad_detalle'], $data['observaciones'],
            $tieneApartado, $montoApartado, $data['requiere_factura'],
            $data['factura_rfc'], $data['factura_curp'], $data['factura_telefono'],
            $data['factura_razon_social'], $data['factura_correo'], $data['factura_domicilio_fiscal'],
            $fotoPath, $csfPath,
        ]);
        $idPrereg = (int) $pdo->lastInsertId();
    }

    $row = array_merge($data, [
        'id_preregistro' => $idPrereg,
        'factura_constancia_path' => $csfPath,
    ]);
    preregistro_evaluar_alertas_guardado($pdo, $idPrereg, $row);

    if ($id <= 0 && function_exists('gerente_notificar_preregistro_nuevo')) {
        gerente_notificar_preregistro_nuevo($pdo, $idPlantel, $idPrereg, $userId);
    }

    $idEntrevista = (int) ($_POST['id_entrevista'] ?? 0);
    if ($idEntrevista > 0 && function_exists('asesor_entrevista_vincular_preregistro')) {
        asesor_entrevista_vincular_preregistro($pdo, $idEntrevista, $idPrereg, $idPlantel);
        if (function_exists('preregistro_aplicar_entrevista_comision')) {
            preregistro_aplicar_entrevista_comision($pdo, $idPrereg, $idEntrevista, $idPlantel);
        }
    }

    if (preregistro_puede_reasignar_comision() && array_key_exists('id_usuario_asesor_comision', $_POST)) {
        $rawAsesor = trim((string) $_POST['id_usuario_asesor_comision']);
        if ($rawAsesor !== '') {
            $idAsesorPost = (int) $rawAsesor;
            if ($idAsesorPost === 0) {
                preregistro_asignar_comision($pdo, $idPrereg, $idPlantel, [
                    'comision_cncm' => true,
                    'id_entrevista' => $idEntrevista,
                    'motivo' => trim((string) ($_POST['motivo_comision'] ?? '')),
                ]);
            } else {
                preregistro_asignar_comision($pdo, $idPrereg, $idPlantel, [
                    'comision_cncm' => false,
                    'id_usuario_asesor' => $idAsesorPost,
                    'id_entrevista' => $idEntrevista,
                    'motivo' => trim((string) ($_POST['motivo_comision'] ?? '')),
                ]);
            }
        }
    }

    $msgOk = 'Pre-registro guardado';
    if ($avisoEdad !== '') {
        $msgOk .= '. ' . $avisoEdad;
    }
    if ((int) ($data['requiere_factura'] ?? 0) && count(preregistro_factura_campos_pendientes($row)) > 0) {
        $msgOk .= ' Recepción debe solicitar los datos de facturación al prospecto.';
    }

    hay_json_response([
        'status' => 'ok',
        'message' => $msgOk,
        'seccion' => 'pre_registro_alumnos',
        'id_preregistro' => $idPrereg,
        'aviso_edad' => $avisoEdad,
    ]);
} catch (PDOException $e) {
    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
}
