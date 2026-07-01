<?php

define('PREREG_FOTO_DIR', 'uploads/preregistros/fotos');
define('PREREG_CSF_DIR', 'uploads/preregistros/csf');
define('PREREG_UPLOAD_MAX', 3 * 1024 * 1024);

function preregistro_ensure_schema(PDO $pdo): void
{
    catalog_ensure_schema($pdo);

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'especialidades', 'inscripcion_abierta', 'TINYINT(1) NOT NULL DEFAULT 1', 'visible');
        plantel_ensure_column($pdo, 'especialidades', 'fecha_apertura_prevista', 'DATE NULL', 'inscripcion_abierta');
        plantel_ensure_column($pdo, 'preregistros', 'id_alumno_vinculado', 'INT UNSIGNED NULL', 'id_especialidad');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS preregistros (
            id_preregistro INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_usuario_registro INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NULL,
            estado ENUM(\'activo\',\'pendiente\',\'perdido\',\'inscrito\') NOT NULL DEFAULT \'activo\',
            categoria_perdido VARCHAR(40) NULL,
            motivo_perdido TEXT NULL,
            foto VARCHAR(255) NULL,
            nombres VARCHAR(120) NOT NULL,
            apellido_paterno VARCHAR(80) NOT NULL,
            apellido_materno VARCHAR(80) NULL,
            fecha_nacimiento DATE NULL,
            edad TINYINT UNSIGNED NULL,
            medio_entero ENUM(
                \'redes_sociales\',\'publicidad\',\'cartas\',\'pasando\',\'recomendado\',\'otro\'
            ) NOT NULL DEFAULT \'otro\',
            medio_entero_otro VARCHAR(120) NULL,
            domicilio VARCHAR(200) NULL,
            colonia VARCHAR(120) NULL,
            municipio VARCHAR(120) NULL,
            telefono VARCHAR(30) NULL,
            telefono2 VARCHAR(30) NULL,
            email VARCHAR(160) NULL,
            codigo_postal VARCHAR(10) NULL,
            ocupacion VARCHAR(120) NULL,
            grado_estudios ENUM(
                \'primaria\',\'secundaria\',\'preparatoria\',\'universidad\',\'otros\'
            ) NULL,
            padre_tutor VARCHAR(160) NULL,
            objetivo_inscripcion TEXT NULL,
            enfermedad_cronica TINYINT(1) NOT NULL DEFAULT 0,
            enfermedad_detalle VARCHAR(200) NULL,
            observaciones TEXT NULL,
            tiene_apartado TINYINT(1) NOT NULL DEFAULT 0,
            monto_apartado DECIMAL(12,2) NULL,
            requiere_factura TINYINT(1) NOT NULL DEFAULT 0,
            factura_rfc VARCHAR(20) NULL,
            factura_curp VARCHAR(22) NULL,
            factura_telefono VARCHAR(30) NULL,
            factura_razon_social VARCHAR(200) NULL,
            factura_correo VARCHAR(160) NULL,
            factura_domicilio_fiscal VARCHAR(255) NULL,
            factura_constancia_path VARCHAR(255) NULL,
            factura_datos_pendientes TINYINT(1) NOT NULL DEFAULT 0,
            espera_apertura_curso TINYINT(1) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            fecha_estado DATETIME NULL,
            PRIMARY KEY (id_preregistro),
            KEY idx_prereg_plantel_estado (id_plantel, estado),
            KEY idx_prereg_especialidad (id_especialidad),
            KEY idx_prereg_creado (creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS preregistro_alertas (
            id_alerta INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_preregistro INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            tipo ENUM(
                \'curso_no_abierto\',\'curso_abierto_seguimiento\',\'factura_incompleta\',\'general\'
            ) NOT NULL,
            mensaje VARCHAR(500) NOT NULL,
            leida TINYINT(1) NOT NULL DEFAULT 0,
            resuelta TINYINT(1) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_alerta),
            KEY idx_alert_prereg (id_preregistro),
            KEY idx_alert_plantel_activa (id_plantel, resuelta, leida)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    try {
        $pdo->exec("UPDATE especialidades SET inscripcion_abierta = 0 WHERE clave = 'VERANO'");
    } catch (PDOException $e) {
        // columna aún no existe en instalaciones antiguas
    }

    plantel_ensure_column($pdo, 'preregistros', 'categoria_pendiente', 'VARCHAR(40) NULL', 'motivo_perdido');
    plantel_ensure_column($pdo, 'preregistros', 'motivo_pendiente', 'TEXT NULL', 'categoria_pendiente');
    plantel_ensure_column($pdo, 'preregistros', 'fecha_recordatorio', 'DATE NULL', 'motivo_pendiente');
    plantel_ensure_column($pdo, 'preregistros', 'folio_apartado', 'VARCHAR(30) NULL', 'monto_apartado');
    plantel_ensure_column($pdo, 'preregistros', 'fecha_apartado', 'DATETIME NULL', 'folio_apartado');
    plantel_ensure_column($pdo, 'preregistros', 'forma_pago_apartado', 'VARCHAR(40) NULL', 'fecha_apartado');
    plantel_ensure_column($pdo, 'preregistro_alertas', 'fecha_programada', 'DATE NULL', 'mensaje');
    plantel_ensure_column($pdo, 'preregistros', 'edad_requiere_autorizacion', 'TINYINT(1) NOT NULL DEFAULT 0', 'factura_datos_pendientes');
    plantel_ensure_column($pdo, 'preregistros', 'id_usuario_asesor', 'INT UNSIGNED NULL COMMENT \'Asesor que recibe comisión\'', 'id_usuario_registro');
    plantel_ensure_column($pdo, 'preregistros', 'id_entrevista_origen', 'INT UNSIGNED NULL', 'id_usuario_asesor');
    plantel_ensure_column($pdo, 'preregistros', 'comision_cncm', 'TINYINT(1) NOT NULL DEFAULT 0', 'id_entrevista_origen');
    preregistro_ensure_medio_entero_enum($pdo);
    if (function_exists('escuelas_ensure_schema')) {
        escuelas_ensure_schema($pdo);
    }
    plantel_ensure_column($pdo, 'preregistros', 'id_escuela_origen', 'INT UNSIGNED NULL', 'medio_entero_otro');
}

/** Gerente/supervisor puede reasignar comisión o vincular entrevista. */
function preregistro_puede_reasignar_comision(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['gerente', 'supervisor', 'admin', 'director'], true);
}

/** @return array{id:int|null,cncm:bool,origen:string} */
function preregistro_resolver_comision(array $row, ?array $entrevista = null): array
{
    if (!empty($row['comision_cncm'])) {
        return ['id' => null, 'cncm' => true, 'origen' => 'cncm'];
    }
    $idExplicito = (int) ($row['id_usuario_asesor'] ?? 0);
    if ($idExplicito > 0) {
        return ['id' => $idExplicito, 'cncm' => false, 'origen' => 'manual'];
    }
    $idEnt = (int) ($row['id_entrevista_origen'] ?? 0);
    if ($idEnt > 0 && $entrevista === null && isset($row['entrevista_asesor_id'])) {
        $idAsesorEnt = (int) $row['entrevista_asesor_id'];
        if ($idAsesorEnt > 0) {
            return ['id' => $idAsesorEnt, 'cncm' => false, 'origen' => 'entrevista'];
        }
    }
    if ($idEnt > 0 && is_array($entrevista)) {
        $idAsesorEnt = (int) ($entrevista['id_usuario_asesor'] ?? 0);
        if ($idAsesorEnt > 0) {
            return ['id' => $idAsesorEnt, 'cncm' => false, 'origen' => 'entrevista'];
        }
    }
    $idReg = (int) ($row['id_usuario_registro'] ?? 0);

    return ['id' => $idReg > 0 ? $idReg : null, 'cncm' => false, 'origen' => 'registro'];
}

/** Id del asesor que recibe comisión (0 = CNCM). */
function preregistro_id_asesor_comision(PDO $pdo, int $idPreregistro): int
{
    preregistro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, e.id_usuario_asesor AS entrevista_asesor_id
         FROM preregistros p
         LEFT JOIN asesor_entrevistas e ON e.id_entrevista = p.id_entrevista_origen
         WHERE p.id_preregistro = ? LIMIT 1'
    );
    $st->execute([$idPreregistro]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return 0;
    }
    $res = preregistro_resolver_comision($row);

    return $res['cncm'] ? 0 : (int) ($res['id'] ?? 0);
}

function preregistro_etiqueta_comision(PDO $pdo, array $row): string
{
    $res = preregistro_resolver_comision($row);
    if ($res['cncm']) {
        return 'CNCM';
    }
    $id = (int) ($res['id'] ?? 0);
    if ($id <= 0) {
        return '—';
    }
    $st = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return 'Asesor #' . $id;
    }

    return trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
}

/** @return list<array{id_usuario:int,nombre:string,activo:bool}> */
function preregistro_asesores_comision_opciones(PDO $pdo, int $idPlantel): array
{
    $out = [['id_usuario' => 0, 'nombre' => 'CNCM (sin comisión a asesor)', 'activo' => true]];
    $equipo = function_exists('gerente_asesores_plantel') ? gerente_asesores_plantel($pdo, $idPlantel) : [];
    foreach ($equipo as $a) {
        $out[] = [
            'id_usuario' => (int) $a['id_usuario'],
            'nombre' => trim(($a['nombre'] ?? '') . ' ' . ($a['apellido'] ?? '')),
            'activo' => true,
        ];
    }
    $st = $pdo->prepare(
        "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido,
                (u.suspendido IS NULL OR u.suspendido = 0) AS activo
         FROM usuarios u
         INNER JOIN preregistros p ON p.id_usuario_asesor = u.id_usuario OR p.id_usuario_registro = u.id_usuario
         WHERE p.id_plantel = ? AND u.rol IN ('asesor','gerente','ventas')
         ORDER BY u.nombre, u.apellido"
    );
    $st->execute([$idPlantel]);
    $ids = array_column(array_slice($out, 1), 'id_usuario');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $id = (int) $u['id_usuario'];
        if (in_array($id, $ids, true)) {
            continue;
        }
        $out[] = [
            'id_usuario' => $id,
            'nombre' => trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')),
            'activo' => (bool) ($u['activo'] ?? true),
        ];
        $ids[] = $id;
    }

    return $out;
}

/** @return list<array<string,mixed>> */
function preregistro_entrevistas_buscar(PDO $pdo, int $idPlantel, string $q, int $limite = 15): array
{
    asesor_ensure_schema($pdo);
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $like = '%' . $q . '%';
    $digits = preg_replace('/\D/', '', $q);
    $st = $pdo->prepare(
        'SELECT e.id_entrevista, e.nombres, e.apellido_paterno, e.apellido_materno, e.telefono,
                e.estado, e.creado_en, e.id_preregistro,
                CONCAT(u.nombre, \' \', u.apellido) AS asesor_nombre, e.id_usuario_asesor
         FROM asesor_entrevistas e
         INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_asesor
         WHERE e.id_plantel = ?
           AND (e.nombres LIKE ? OR e.apellido_paterno LIKE ? OR e.apellido_materno LIKE ?
                OR e.telefono LIKE ? OR CONCAT(u.nombre, \' \', u.apellido) LIKE ?'
        . ($digits !== '' ? ' OR REPLACE(REPLACE(e.telefono,\'-\',\'\'),\' \',\'\') LIKE ?' : '')
        . ')
         ORDER BY e.creado_en DESC
         LIMIT ' . max(1, min(30, $limite))
    );
    $params = [$idPlantel, $like, $like, $like, $like, $like];
    if ($digits !== '') {
        $params[] = '%' . $digits . '%';
    }
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static function (array $e): array {
        $nombre = trim(($e['nombres'] ?? '') . ' ' . ($e['apellido_paterno'] ?? '') . ' ' . ($e['apellido_materno'] ?? ''));

        return [
            'id_entrevista' => (int) $e['id_entrevista'],
            'nombre' => $nombre,
            'telefono' => $e['telefono'] ?? '',
            'asesor_nombre' => $e['asesor_nombre'] ?? '',
            'id_usuario_asesor' => (int) ($e['id_usuario_asesor'] ?? 0),
            'estado' => $e['estado'] ?? '',
            'fecha' => !empty($e['creado_en']) ? date('d/m/Y', strtotime($e['creado_en'])) : '',
            'id_preregistro_vinculado' => (int) ($e['id_preregistro'] ?? 0),
        ];
    }, $rows);
}

function preregistro_sync_alumno_asesor(PDO $pdo, int $idPrereg): void
{
    if (!function_exists('alumno_ensure_schema')) {
        return;
    }
    alumno_ensure_schema($pdo);
    preregistro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, e.id_usuario_asesor AS entrevista_asesor_id, p.id_alumno_vinculado
         FROM preregistros p
         LEFT JOIN asesor_entrevistas e ON e.id_entrevista = p.id_entrevista_origen
         WHERE p.id_preregistro = ? LIMIT 1'
    );
    $st->execute([$idPrereg]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['id_alumno_vinculado'])) {
        return;
    }
    $res = preregistro_resolver_comision($row);
    $idAlumno = (int) $row['id_alumno_vinculado'];
    $idAsesor = $res['cncm'] ? null : ($res['id'] > 0 ? (int) $res['id'] : null);
    $pdo->prepare('UPDATE alumnos SET id_usuario_asesor = ? WHERE id_alumno = ?')
        ->execute([$idAsesor, $idAlumno]);
}

function preregistro_sync_movimientos_comision(PDO $pdo, int $idPrereg): void
{
    if (!function_exists('ventas_comision_ensure_schema')) {
        return;
    }
    ventas_comision_ensure_schema($pdo);
    $idAsesor = preregistro_id_asesor_comision($pdo, $idPrereg);
    $st = $pdo->prepare('SELECT id_movimiento, monto_base, regla_snapshot FROM ventas_movimiento WHERE id_preregistro = ?');
    $st->execute([$idPrereg]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $mov) {
        $monto = (float) ($mov['monto_base'] ?? 0);
        $comA = 0.0;
        if ($idAsesor > 0 && function_exists('ventas_calcular_comisiones')) {
            $snap = json_decode((string) ($mov['regla_snapshot'] ?? ''), true);
            $idEsp = (int) ($snap['id_especialidad'] ?? 0);
            $calc = ventas_calcular_comisiones($pdo, $idEsp, $monto, ($snap['tipo'] ?? '') === 'personalizado');
            $comA = (float) ($calc['comision_asesor'] ?? 0);
        }
        $pdo->prepare(
            'UPDATE ventas_movimiento SET id_usuario_asesor = ?, comision_asesor = ? WHERE id_movimiento = ?'
        )->execute([max(0, $idAsesor), $comA, (int) $mov['id_movimiento']]);
    }
}

/**
 * Reasignar comisión, marcar CNCM o vincular entrevista.
 *
 * @param array{id_usuario_asesor?:int|null,comision_cncm?:bool,id_entrevista?:int,motivo?:string} $data
 * @return array{ok:bool,message:string}
 */
function preregistro_asignar_comision(PDO $pdo, int $idPrereg, int $idPlantel, array $data): array
{
    if (!preregistro_puede_reasignar_comision()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    preregistro_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idPrereg, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'Pre-registro no encontrado'];
    }

    $cncm = !empty($data['comision_cncm']);
    $idAsesor = $cncm ? null : (((int) ($data['id_usuario_asesor'] ?? 0)) ?: null);
    $idEntrevista = (int) ($data['id_entrevista'] ?? 0);
    $motivo = trim((string) ($data['motivo'] ?? ''));

    if (!$cncm && $idAsesor === null && $idEntrevista <= 0) {
        return ['ok' => false, 'message' => 'Indique asesor, CNCM o entrevista a vincular'];
    }

    if ($idEntrevista > 0) {
        $stE = $pdo->prepare(
            'SELECT * FROM asesor_entrevistas WHERE id_entrevista = ? AND id_plantel = ? LIMIT 1'
        );
        $stE->execute([$idEntrevista, $idPlantel]);
        $ent = $stE->fetch(PDO::FETCH_ASSOC);
        if (!$ent) {
            return ['ok' => false, 'message' => 'Entrevista no encontrada'];
        }
        $idPreregOtro = (int) ($ent['id_preregistro'] ?? 0);
        if ($idPreregOtro > 0 && $idPreregOtro !== $idPrereg) {
            return ['ok' => false, 'message' => 'Esa entrevista ya está vinculada a otro pre-registro'];
        }
        if (!$cncm && $idAsesor === null) {
            $idAsesor = (int) ($ent['id_usuario_asesor'] ?? 0) ?: null;
        }
        asesor_entrevista_vincular_preregistro($pdo, $idEntrevista, $idPrereg, $idPlantel);
    }

    if ($idAsesor !== null && $idAsesor > 0) {
        $stU = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stU->execute([$idAsesor]);
        if (!$stU->fetchColumn()) {
            return ['ok' => false, 'message' => 'Asesor no válido'];
        }
    }

    $pdo->prepare(
        'UPDATE preregistros SET id_usuario_asesor = ?, comision_cncm = ?, id_entrevista_origen = ?
         WHERE id_preregistro = ?'
    )->execute([
        $idAsesor,
        $cncm ? 1 : 0,
        $idEntrevista > 0 ? $idEntrevista : ($row['id_entrevista_origen'] ?? null),
        $idPrereg,
    ]);

    if ($motivo !== '') {
        $obs = trim((string) ($row['observaciones'] ?? ''));
        $nota = '[Comisión ' . date('d/m/Y H:i') . '] ' . $motivo;
        $nueva = $obs !== '' ? $obs . "\n" . $nota : $nota;
        $pdo->prepare('UPDATE preregistros SET observaciones = ? WHERE id_preregistro = ?')
            ->execute([$nueva, $idPrereg]);
    }

    preregistro_sync_alumno_asesor($pdo, $idPrereg);
    preregistro_sync_movimientos_comision($pdo, $idPrereg);

    return ['ok' => true, 'message' => 'Comisión actualizada'];
}

/** Al crear pre-registro desde entrevista: asigna comisión al asesor de la entrevista. */
function preregistro_aplicar_entrevista_comision(PDO $pdo, int $idPrereg, int $idEntrevista, int $idPlantel): void
{
    asesor_ensure_schema($pdo);
    preregistro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_usuario_asesor FROM asesor_entrevistas
         WHERE id_entrevista = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idEntrevista, $idPlantel]);
    $idAsesor = (int) $st->fetchColumn();
    if ($idAsesor <= 0) {
        return;
    }
    $pdo->prepare(
        'UPDATE preregistros SET id_entrevista_origen = ?, id_usuario_asesor = ?, comision_cncm = 0
         WHERE id_preregistro = ? AND id_plantel = ?'
    )->execute([$idEntrevista, $idAsesor, $idPrereg, $idPlantel]);
}

function preregistro_ensure_medio_entero_enum(PDO $pdo): void
{
    try {
        $pdo->exec(
            "ALTER TABLE preregistros MODIFY COLUMN medio_entero
             ENUM('redes_sociales','publicidad','cartas','pasando','recomendado','crm','cita_crm','otro')
             NOT NULL DEFAULT 'otro'"
        );
    } catch (PDOException $e) {
        // ya aplicado o sin permisos DDL
    }
}

/** Roles que pueden ver pre-registros. */
function preregistro_roles_permitidos(): array
{
    return ['supervisor', 'admin', 'gerente', 'asesor', 'director', 'coordinador', 'recepcion', 'ventas'];
}

/** Asegura menu_preregistro (y catálogo) en role_privilegios del supervisor. */
function preregistro_asegurar_privilegios_supervisor(PDO $pdo): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    try {
        $st = $pdo->query(
            "SELECT id_rol FROM roles WHERE clave = 'supervisor' LIMIT 1"
        );
        $idRol = (int) $st->fetchColumn();
        if ($idRol <= 0) {
            return;
        }

        $pdo->prepare('UPDATE roles SET acceso_total = 1, alcance_planteles = ? WHERE id_rol = ?')
            ->execute(['todos', $idRol]);

        $chk = $pdo->prepare(
            'SELECT 1 FROM role_privilegios WHERE id_rol = ? AND privilegio = ? LIMIT 1'
        );
        $chk->execute([$idRol, 'menu_preregistro']);
        if ($chk->fetchColumn()) {
            return;
        }

        $privs = function_exists('rbac_privilegios_catalogo')
            ? array_keys(rbac_privilegios_catalogo())
            : [
                'menu_ventas', 'menu_preregistro', 'menu_entrevistas', 'menu_grupos_fases',
                'menu_cert_preregistro', 'menu_reporte_inscritos', 'menu_comisiones_consulta',
                'menu_comisiones_admin', 'menu_caja', 'menu_consulta_adeudo', 'menu_punto_venta',
                'menu_venta_productos', 'menu_certificaciones', 'menu_reportes', 'menu_alumnos',
                'menu_academico', 'menu_grupos', 'menu_especialidades', 'menu_asistencia',
                'menu_admin', 'menu_mi_evaluacion', 'menu_matriz_entrenamiento', 'menu_soporte',
            ];
        $ins = $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?,?)');
        foreach ($privs as $priv) {
            $ins->execute([$idRol, $priv]);
        }
    } catch (Throwable $e) {
        error_log('preregistro_asegurar_privilegios_supervisor: ' . $e->getMessage());
    }
}

/**
 * Ver pre-registros — consulta BD directamente (no depende solo de la sesión RBAC).
 */
function preregistro_puede_acceder(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }

    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);
    if ($idUsuario <= 0) {
        return false;
    }

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    try {
        preregistro_asegurar_privilegios_supervisor($pdo);

        $st = $pdo->prepare(
            'SELECT u.rol, r.clave AS rol_clave, COALESCE(r.acceso_total, 0) AS acceso_total
             FROM usuarios u
             LEFT JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = ? LIMIT 1'
        );
        $st->execute([$idUsuario]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return false;
        }

        if ((int) ($u['acceso_total'] ?? 0) === 1) {
            if (function_exists('rbac_reparar_sesion_desde_cuenta_bd')) {
                rbac_reparar_sesion_desde_cuenta_bd($pdo, $idUsuario);
            } elseif (function_exists('rbac_supervisor_aplicar_sesion')) {
                rbac_supervisor_aplicar_sesion();
            }

            return true;
        }

        $rolesOk = preregistro_roles_permitidos();
        $rolTexto = strtolower(trim((string) ($u['rol'] ?? '')));
        if ($rolTexto === 'recepcion' || $rolTexto === 'caja') {
            $rolTexto = 'admin';
        }
        if ($rolTexto === 'ventas') {
            $rolTexto = 'asesor';
        }
        $rolClave = strtolower(trim((string) ($u['rol_clave'] ?? '')));
        if (in_array($rolTexto, $rolesOk, true) || in_array($rolClave, $rolesOk, true)) {
            return true;
        }

        $stPriv = $pdo->prepare(
            'SELECT 1 FROM role_privilegios rp
             INNER JOIN usuarios u ON u.id_rol = rp.id_rol
             WHERE u.id_usuario = ? AND rp.privilegio = ? LIMIT 1'
        );
        $stPriv->execute([$idUsuario, 'menu_preregistro']);
        if ($stPriv->fetchColumn()) {
            return true;
        }
    } catch (Throwable $e) {
        error_log('preregistro_puede_acceder: ' . $e->getMessage());
    }

    if (function_exists('rbac_cap') && rbac_cap('menu_preregistro')) {
        return true;
    }
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(preregistro_roles_permitidos())) {
        return true;
    }

    return false;
}

/** Cobrar apartado o completar inscripción (solo recepción / supervisión; no asesor ni gerente). */
function preregistro_puede_cobrar(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    if (in_array($rol, ['asesor', 'gerente'], true)) {
        return false;
    }
    if (function_exists('rbac_cap') && rbac_cap('preregistro_cobrar')) {
        return true;
    }
    if (function_exists('rbac_acceso_total') && rbac_acceso_total()) {
        return true;
    }

    return in_array($rol, ['admin', 'supervisor'], true);
}

/** Completar datos fiscales, quitar marca de factura o activar factura post-inscripción. */
function preregistro_puede_gestionar_factura(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['admin', 'supervisor'], true);
}

/** Resaltar fila en lista (factura pendiente solo para recepción). */
function preregistro_fila_destacada(array $p): bool
{
    if ((int) ($p['alertas_activas'] ?? 0) > 0 || (int) ($p['espera_apertura_curso'] ?? 0)) {
        return true;
    }

    return (int) ($p['factura_datos_pendientes'] ?? 0) === 1 && preregistro_puede_gestionar_factura();
}

/**
 * Valida usuario/contraseña de quien autoriza excepciones (edad, etc.).
 *
 * @return array<string, mixed>
 */
function preregistro_verificar_autorizador(PDO $pdo, string $usuario, string $password): array
{
    $usuario = trim($usuario);
    $password = (string) $password;
    if ($password === '') {
        return ['ok' => false, 'message' => 'La contraseña del autorizador es obligatoria'];
    }

    require_once __DIR__ . '/auth_helpers.php';
    $u = null;
    if ($usuario !== '') {
        $u = auth_find_user_by_login($pdo, $usuario);
    } elseif (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
        $st = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $st->execute([(int) $_SESSION['user_id']]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$u || !password_verify($password, (string) ($u['password'] ?? ''))) {
        return ['ok' => false, 'message' => 'Autorización rechazada'];
    }
    $rol = $u['rol'] ?? '';
    if (!in_array($rol, ['admin', 'gerente', 'supervisor'], true)) {
        return [
            'ok' => false,
            'message' => 'Solo dirección (gerente/admin) o supervisor pueden autorizar esta inscripción',
        ];
    }
    if ((int) ($u['suspendido'] ?? 0)) {
        return ['ok' => false, 'message' => 'El usuario autorizador está inactivo'];
    }

    return [
        'ok' => true,
        'id_usuario' => (int) $u['id_usuario'],
        'nombre' => trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')),
        'rol' => $rol,
    ];
}

/** @return array{requiere: bool, mensaje: string} */
function preregistro_edad_requiere_autorizacion(PDO $pdo, array $pr): array
{
    if ((int) ($pr['edad_requiere_autorizacion'] ?? 0) === 1) {
        return [
            'requiere' => true,
            'mensaje' => 'La edad del prospecto no cumple el rango del curso; se requiere autorización al cobrar la inscripción.',
        ];
    }
    $idEsp = (int) ($pr['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        return ['requiere' => false, 'mensaje' => ''];
    }
    $st = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEsp]);
    $esp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$esp) {
        return ['requiere' => false, 'mensaje' => ''];
    }
    $edad = isset($pr['edad']) ? (int) $pr['edad'] : preregistro_calcular_edad($pr['fecha_nacimiento'] ?? null);
    $val = catalog_validar_edad_especialidad($esp, $edad);
    if ($val['ok']) {
        return ['requiere' => false, 'mensaje' => ''];
    }

    return ['requiere' => true, 'mensaje' => $val['message'] ?? 'Edad fuera de rango'];
}

function preregistro_calcular_edad(?string $fecha): ?int
{
    if (!$fecha) {
        return null;
    }
    try {
        $nac = new DateTime($fecha);
        $hoy = new DateTime('today');
        return (int) $nac->diff($hoy)->y;
    } catch (Exception $e) {
        return null;
    }
}

function preregistro_upload_dir(string $subdir): string
{
    $dir = dirname(__DIR__) . '/' . $subdir;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/** Foto subida en paso previo (evita base64 en el POST principal). */
function preregistro_foto_sesion_limpiar(): void
{
    $prev = $_SESSION['preregistro_foto_tmp'] ?? null;
    if (is_array($prev) && !empty($prev['path'])) {
        preregistro_borrar_archivo((string) $prev['path']);
    }
    unset($_SESSION['preregistro_foto_tmp']);
}

function preregistro_foto_sesion_asignar(string $path): void
{
    preregistro_foto_sesion_limpiar();
    $_SESSION['preregistro_foto_tmp'] = [
        'path' => $path,
        'ts' => time(),
        'uid' => (int) ($_SESSION['user_id'] ?? 0),
    ];
}

function preregistro_foto_sesion_tomar(): ?string
{
    $tmp = $_SESSION['preregistro_foto_tmp'] ?? null;
    unset($_SESSION['preregistro_foto_tmp']);
    if (!is_array($tmp) || empty($tmp['path'])) {
        return null;
    }
    $maxAge = 3600;
    if ((time() - (int) ($tmp['ts'] ?? 0)) > $maxAge) {
        preregistro_borrar_archivo((string) $tmp['path']);
        return null;
    }
    if ((int) ($tmp['uid'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)) {
        return null;
    }
    $path = ltrim(str_replace('\\', '/', (string) $tmp['path']), '/');
    if (strpos($path, PREREG_FOTO_DIR . '/') !== 0) {
        return null;
    }
    $abs = dirname(__DIR__) . '/' . $path;
    if (!is_file($abs)) {
        return null;
    }
    return $path;
}

function preregistro_guardar_foto_base64(string $dataUrl): array
{
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,#i', $dataUrl, $m)) {
        return ['ok' => false, 'message' => 'Formato de imagen de cámara no válido'];
    }
    $ext = strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl), true);
    if ($raw === false || strlen($raw) < 100) {
        return ['ok' => false, 'message' => 'No se pudo procesar la foto'];
    }
    if (strlen($raw) > PREREG_UPLOAD_MAX) {
        return ['ok' => false, 'message' => 'La foto supera 3 MB'];
    }
    $name = 'foto_cam_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = preregistro_upload_dir(PREREG_FOTO_DIR) . '/' . $name;
    if (file_put_contents($dest, $raw) === false) {
        return ['ok' => false, 'message' => 'No se pudo guardar la foto'];
    }
    return ['ok' => true, 'path' => PREREG_FOTO_DIR . '/' . $name];
}

function preregistro_guardar_archivo(array $file, string $subdir, string $prefix): array
{
    $dir = preregistro_upload_dir($subdir);
    $basename = $prefix . '_' . bin2hex(random_bytes(8));
    $res = hay_upload_guardar(
        $file,
        $dir,
        $basename,
        HAY_UPLOAD_MIME_IMAGE_PDF,
        PREREG_UPLOAD_MAX,
        false
    );
    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['message'] ?? 'No se pudo guardar'];
    }
    if (empty($res['filename'])) {
        return ['ok' => true, 'path' => null];
    }

    return ['ok' => true, 'path' => $subdir . '/' . $res['filename']];
}

function preregistro_borrar_archivo(?string $path): void
{
    if (!$path) {
        return;
    }
    $rel = ltrim(str_replace('\\', '/', $path), '/');
    if (strpos($rel, 'uploads/preregistros/') !== 0) {
        return;
    }
    $abs = dirname(__DIR__) . '/' . $rel;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

function preregistro_icono_factura_pendiente_html(array $p): string
{
    if (!(int) ($p['requiere_factura'] ?? 0) || !(int) ($p['factura_datos_pendientes'] ?? 0)) {
        return '';
    }
    $id = (int) ($p['id_preregistro'] ?? 0);
    if ($id <= 0) {
        return '';
    }

    if (preregistro_puede_gestionar_factura()) {
        return ' <button type="button" class="prereg-factura-flag" title="Datos de factura pendientes — clic para completar" '
            . 'onclick="cargarSeccion(\'cola_facturacion\', \'id=' . $id . '\')">'
            . '<i class="fas fa-file-invoice" aria-hidden="true"></i></button>';
    }

    return ' <span class="prereg-factura-flag" title="Solicita factura — recepción dará seguimiento">'
        . '<i class="fas fa-file-invoice" aria-hidden="true"></i></span>';
}

function preregistro_factura_campos_pendientes(array $row): array
{
    if (!(int) ($row['requiere_factura'] ?? 0)) {
        return [];
    }
    $faltan = [];
    foreach ([
        'factura_rfc' => 'RFC',
        'factura_curp' => 'CURP',
        'factura_razon_social' => 'Razón social',
        'factura_correo' => 'Correo fiscal',
        'factura_domicilio_fiscal' => 'Domicilio fiscal',
    ] as $k => $label) {
        if (trim((string) ($row[$k] ?? '')) === '') {
            $faltan[] = $label;
        }
    }
    if (empty($row['factura_constancia_path'])) {
        $faltan[] = 'Constancia de situación fiscal';
    }
    return $faltan;
}

function preregistro_notificar_factura_recepcion(
    PDO $pdo,
    int $idPrereg,
    int $idPlantel,
    array $faltan
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    if (!$pdo->inTransaction()) {
        academico_ensure_schema($pdo);
    }

    $ctx = preregistro_contexto_notificacion($pdo, $idPrereg);
    $params = 'id=' . $ctx['id_preregistro'];
    $titulo = 'Datos de factura pendientes';
    $det = $ctx['nombre'] !== '' ? $ctx['nombre'] : 'Prospecto';
    if ($ctx['control'] !== '') {
        $det = 'No. ' . $ctx['control'] . ' — ' . $det;
    }
    $msg = $det . '. Solicite RFC, razón social y constancia al prospecto.';
    if ($faltan !== []) {
        $msg .= ' Faltan: ' . implode(', ', $faltan) . '.';
    }

    foreach (preregistro_usuarios_recepcion_plantel($pdo, $idPlantel) as $idRec) {
        academico_notificar_usuario(
            $pdo,
            $idRec,
            'factura_pendiente',
            $titulo,
            $msg,
            'cola_facturacion',
            $params
        );
    }
}

function preregistro_crear_alerta(
    PDO $pdo,
    int $idPrereg,
    int $idPlantel,
    string $tipo,
    string $mensaje,
    ?string $fechaProgramada = null
): void {
    if ($tipo === 'factura_incompleta') {
        preregistro_notificar_factura_recepcion($pdo, $idPrereg, $idPlantel, []);
        return;
    }

    $dup = $pdo->prepare(
        'SELECT id_alerta FROM preregistro_alertas
         WHERE id_preregistro = ? AND tipo = ? AND resuelta = 0 AND mensaje = ? LIMIT 1'
    );
    $dup->execute([$idPrereg, $tipo, $mensaje]);
    if ($dup->fetchColumn()) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO preregistro_alertas (id_preregistro, id_plantel, tipo, mensaje, fecha_programada)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$idPrereg, $idPlantel, $tipo, mb_substr($mensaje, 0, 500), $fechaProgramada]);

    preregistro_notificar_dashboard_alerta($pdo, $idPrereg, $idPlantel, $tipo, $mensaje);
}

/**
 * Usuarios de recepción del plantel (rol admin en plantel).
 *
 * @return list<int>
 */
function preregistro_usuarios_recepcion_plantel(PDO $pdo, int $idPlantel): array
{
    $filtroActivo = function_exists('gerente_sql_usuario_activo')
        ? gerente_sql_usuario_activo('u')
        : '(u.suspendido IS NULL OR u.suspendido = 0)';
    $st = $pdo->prepare(
        "SELECT u.id_usuario FROM usuarios u
         WHERE u.id_plantel = ? AND {$filtroActivo} AND u.rol = 'admin'
         ORDER BY u.id_usuario"
    );
    $st->execute([$idPlantel]);

    return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id_usuario'));
}

/** @return array{nombre: string, control: string, id_preregistro: int} */
function preregistro_contexto_notificacion(PDO $pdo, int $idPrereg): array
{
    $st = $pdo->prepare(
        'SELECT p.id_preregistro, p.nombres, p.apellido_paterno, p.id_alumno_vinculado,
                a.numero_control
         FROM preregistros p
         LEFT JOIN alumnos a ON a.id_alumno = p.id_alumno_vinculado
         WHERE p.id_preregistro = ? LIMIT 1'
    );
    $st->execute([$idPrereg]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'id_preregistro' => (int) ($row['id_preregistro'] ?? $idPrereg),
        'nombre' => trim(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '')),
        'control' => trim((string) ($row['numero_control'] ?? '')),
    ];
}

function preregistro_notificar_dashboard_alerta(
    PDO $pdo,
    int $idPrereg,
    int $idPlantel,
    string $tipo,
    string $mensaje
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    if (!$pdo->inTransaction()) {
        academico_ensure_schema($pdo);
    }

    $ctx = preregistro_contexto_notificacion($pdo, $idPrereg);
    $params = 'id=' . $ctx['id_preregistro'];

    if ($tipo === 'factura_incompleta') {
        $titulo = 'Datos de factura pendientes';
        $det = $ctx['nombre'] !== '' ? $ctx['nombre'] : 'Prospecto';
        if ($ctx['control'] !== '') {
            $det = 'No. ' . $ctx['control'] . ' — ' . $det;
        }
        $msg = $det . '. Solicite RFC, razón social y constancia al prospecto.';
        foreach (preregistro_usuarios_recepcion_plantel($pdo, $idPlantel) as $idRec) {
            academico_notificar_usuario(
                $pdo,
                $idRec,
                'factura_pendiente',
                $titulo,
                $msg,
                'cola_facturacion',
                $params
            );
        }

        return;
    }

    $st = $pdo->prepare('SELECT id_usuario_registro FROM preregistros WHERE id_preregistro = ? LIMIT 1');
    $st->execute([$idPrereg]);
    $idAsesor = (int) $st->fetchColumn();
    if ($idAsesor <= 0) {
        return;
    }

    $titulo = match ($tipo) {
        'curso_abierto_seguimiento' => 'Curso abrió inscripción',
        'curso_no_abierto' => 'Espera apertura de curso',
        default => 'Seguimiento pre-registro',
    };
    $enlace = 'pre_registro_alumnos';
    if (str_contains($mensaje, 'Recordatorio:') || str_contains($mensaje, 'Seguimiento pendiente:')) {
        $enlace = 'pre_registro_alumnos';
    }

    academico_notificar_usuario($pdo, $idAsesor, 'preregistro', $titulo, mb_substr($mensaje, 0, 500), $enlace, $params);
}

function preregistro_generar_folio_apartado(PDO $pdo, int $idPlantel): string
{
    $pref = 'APR-' . str_pad((string) $idPlantel, 2, '0', STR_PAD_LEFT) . '-';
    $st = $pdo->prepare(
        'SELECT folio_apartado FROM preregistros
         WHERE id_plantel = ? AND folio_apartado LIKE ?
         ORDER BY id_preregistro DESC LIMIT 1'
    );
    $st->execute([$idPlantel, $pref . '%']);
    $ultimo = (string) $st->fetchColumn();
    $seq = 1;
    if ($ultimo !== '' && preg_match('/(\d+)$/', $ultimo, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return $pref . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
}

/** @return array<string, mixed> */
function preregistro_datos_ticket_apartado(PDO $pdo, int $idPrereg, int $idPlantel): ?array
{
    $st = $pdo->prepare(
        'SELECT p.*, e.nombre AS especialidad_nombre,
                CONCAT(u.nombre, \' \', u.apellido) AS asesor_nombre
         FROM preregistros p
         LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
         INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
         WHERE p.id_preregistro = ? AND p.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idPrereg, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !(int) ($row['tiene_apartado'] ?? 0)) {
        return null;
    }

    $plantel = plantel_find($pdo, $idPlantel);

    return [
        'folio' => $row['folio_apartado'] ?? '',
        'fecha' => $row['fecha_apartado'] ?? date('Y-m-d H:i:s'),
        'monto' => (float) ($row['monto_apartado'] ?? 0),
        'monto_fmt' => catalog_format_mxn((float) ($row['monto_apartado'] ?? 0)),
        'prospecto' => preregistro_nombre_completo($row),
        'telefono' => $row['telefono'] ?? '',
        'especialidad' => $row['especialidad_nombre'] ?? '—',
        'asesor' => $row['asesor_nombre'] ?? '',
        'plantel' => $plantel['nombre'] ?? 'CNCM',
        'id_preregistro' => $idPrereg,
        'forma_pago' => $row['forma_pago_apartado'] ?? 'Efectivo',
    ];
}

/**
 * Registra el apartado del pre-registro como pago de inscripción del alumno vinculado.
 *
 * @return array<string, mixed>
 */
function preregistro_aplicar_apartado_a_alumno(PDO $pdo, int $idPrereg): array
{
    $st = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? LIMIT 1');
    $st->execute([$idPrereg]);
    $pr = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pr || !(int) ($pr['tiene_apartado'] ?? 0)) {
        return ['ok' => true, 'aplicado' => false];
    }

    $monto = (float) ($pr['monto_apartado'] ?? 0);
    if ($monto <= 0) {
        return ['ok' => true, 'aplicado' => false];
    }

    $idAlumno = (int) ($pr['id_alumno_vinculado'] ?? 0);
    if ($idAlumno <= 0) {
        return ['ok' => true, 'aplicado' => false, 'pendiente_vinculo' => true];
    }

    $chk = $pdo->prepare(
        "SELECT 1 FROM alumno_pagos
         WHERE id_alumno = ? AND concepto LIKE 'Apartado pre-registro%'
           AND (id_especialidad = ? OR id_especialidad IS NULL)
         LIMIT 1"
    );
    $idEsp = (int) ($pr['id_especialidad'] ?? 0) ?: null;
    $chk->execute([$idAlumno, $idEsp]);
    if ($chk->fetchColumn()) {
        return ['ok' => true, 'aplicado' => false, 'ya_aplicado' => true];
    }

    $idAe = 0;
    if ($idEsp > 0) {
        $idAe = pago_crear_inscripcion(
            $pdo,
            $idAlumno,
            $idEsp,
            'mensual',
            date('Y-m-d'),
            true
        );
    }

    $folio = trim((string) ($pr['folio_apartado'] ?? ''));
    $concepto = 'Apartado pre-registro' . ($folio !== '' ? ' (' . $folio . ')' : '');
    $formaPago = trim((string) ($pr['forma_pago_apartado'] ?? '')) ?: 'Efectivo';

    $res = pago_registrar($pdo, [
        'id_alumno' => $idAlumno,
        'id_especialidad' => $idEsp ?: null,
        'id_alumno_especialidad' => $idAe > 0 ? $idAe : null,
        'tipo' => 'inscripcion',
        'monto' => $monto,
        'concepto' => $concepto,
        'forma_pago_efectivo' => $formaPago,
    ]);

    if (!$res['ok']) {
        return $res;
    }

    if ($idAe > 0 && function_exists('pago_actualizar_inscripcion_cubierta')) {
        pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEsp, $idAe);
    }

    return [
        'ok' => true,
        'aplicado' => true,
        'monto' => $monto,
        'id_pago' => $res['id_pago'] ?? null,
    ];
}

function preregistro_registrar_pendiente(
    PDO $pdo,
    int $idPrereg,
    int $idPlantel,
    string $categoria,
    string $motivo,
    ?string $fechaRecordatorio = null
): void {
    $cats = array_keys(preregistro_labels()['categoria_pendiente']);
    if (!in_array($categoria, $cats, true)) {
        $categoria = 'otro';
    }

    $pdo->prepare(
        'UPDATE preregistros SET estado = \'pendiente\', categoria_pendiente = ?, motivo_pendiente = ?,
         fecha_recordatorio = ?, categoria_perdido = NULL, motivo_perdido = NULL, fecha_estado = NOW()
         WHERE id_preregistro = ? AND id_plantel = ?'
    )->execute([
        $categoria,
        $motivo,
        $fechaRecordatorio !== '' ? $fechaRecordatorio : null,
        $idPrereg,
        $idPlantel,
    ]);

    $st = $pdo->prepare('SELECT nombres, apellido_paterno, telefono FROM preregistros WHERE id_preregistro = ?');
    $st->execute([$idPrereg]);
    $pr = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $nombre = trim(($pr['nombres'] ?? '') . ' ' . ($pr['apellido_paterno'] ?? ''));
    $catLabel = preregistro_labels()['categoria_pendiente'][$categoria] ?? $categoria;
    $msg = 'Seguimiento pendiente: ' . $nombre . ' — ' . $catLabel;
    if ($motivo !== '') {
        $msg .= '. ' . mb_substr($motivo, 0, 200);
    }

    $pdo->prepare(
        'UPDATE preregistro_alertas SET resuelta = 1
         WHERE id_preregistro = ? AND tipo = \'general\' AND mensaje LIKE \'Seguimiento pendiente:%\''
    )->execute([$idPrereg]);

    $fechaAlerta = $fechaRecordatorio;
    if ($fechaAlerta !== null && $fechaAlerta !== '') {
        $hoy = date('Y-m-d');
        if ($fechaAlerta > $hoy) {
            preregistro_crear_alerta($pdo, $idPrereg, $idPlantel, 'general', $msg, $fechaAlerta);
        } else {
            preregistro_crear_alerta($pdo, $idPrereg, $idPlantel, 'general', $msg, null);
        }
    } else {
        preregistro_notificar_dashboard_alerta($pdo, $idPrereg, $idPlantel, 'general', $msg);
    }
}

function preregistro_sync_recordatorios_pendientes(PDO $pdo, int $idPlantel): void
{
    $st = $pdo->prepare(
        'SELECT id_preregistro, nombres, apellido_paterno, categoria_pendiente, motivo_pendiente, fecha_recordatorio
         FROM preregistros
         WHERE id_plantel = ? AND estado = \'pendiente\'
           AND fecha_recordatorio IS NOT NULL AND fecha_recordatorio <= CURDATE()'
    );
    $st->execute([$idPlantel]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $nombre = trim($pr['nombres'] . ' ' . $pr['apellido_paterno']);
        $cat = preregistro_labels()['categoria_pendiente'][$pr['categoria_pendiente'] ?? ''] ?? 'Pendiente';
        $msg = 'Recordatorio: contactar a ' . $nombre . ' (' . $cat . ')';
        if (!empty($pr['motivo_pendiente'])) {
            $msg .= ' — ' . mb_substr($pr['motivo_pendiente'], 0, 150);
        }
        preregistro_crear_alerta($pdo, (int) $pr['id_preregistro'], $idPlantel, 'general', $msg, null);
    }
}

function preregistro_sync_alertas_apertura(PDO $pdo, int $idPlantel): void
{
    $stmt = $pdo->query(
        'SELECT e.id_especialidad, e.nombre FROM especialidades e
         WHERE e.inscripcion_abierta = 1 AND e.activo = 1'
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $esp) {
        $q = $pdo->prepare(
            'SELECT p.id_preregistro, p.nombres, p.apellido_paterno
             FROM preregistros p
             WHERE p.id_plantel = ? AND p.id_especialidad = ? AND p.espera_apertura_curso = 1
               AND p.estado IN (\'activo\',\'pendiente\')'
        );
        $q->execute([$idPlantel, $esp['id_especialidad']]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $pr) {
            $nombre = trim($pr['nombres'] . ' ' . $pr['apellido_paterno']);
            preregistro_crear_alerta(
                $pdo,
                (int) $pr['id_preregistro'],
                $idPlantel,
                'curso_abierto_seguimiento',
                '¡Contactar a ' . $nombre . '! El curso «' . $esp['nombre'] . '» ya abrió inscripción.'
            );
        }
    }
}

function preregistro_evaluar_alertas_guardado(PDO $pdo, int $idPrereg, array $data): void
{
    $idPlantel = (int) $data['id_plantel'];
    $idEsp = (int) ($data['id_especialidad'] ?? 0);

    if ($idEsp > 0) {
        $e = $pdo->prepare('SELECT nombre, inscripcion_abierta FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $e->execute([$idEsp]);
        $esp = $e->fetch(PDO::FETCH_ASSOC);
        if ($esp && !(int) $esp['inscripcion_abierta']) {
            $pdo->prepare('UPDATE preregistros SET espera_apertura_curso = 1 WHERE id_preregistro = ?')->execute([$idPrereg]);
            $nombre = trim($data['nombres'] . ' ' . $data['apellido_paterno']);
            preregistro_crear_alerta(
                $pdo,
                $idPrereg,
                $idPlantel,
                'curso_no_abierto',
                $nombre . ' espera apertura del curso «' . $esp['nombre'] . '».'
            );
        } else {
            $pdo->prepare('UPDATE preregistros SET espera_apertura_curso = 0 WHERE id_preregistro = ?')->execute([$idPrereg]);
        }
    }

    $faltan = preregistro_factura_campos_pendientes($data);
    $pend = count($faltan) > 0 ? 1 : 0;
    $pdo->prepare('UPDATE preregistros SET factura_datos_pendientes = ? WHERE id_preregistro = ?')
        ->execute([$pend, $idPrereg]);

    if ((int) ($data['requiere_factura'] ?? 0) && $pend) {
        preregistro_notificar_factura_recepcion($pdo, $idPrereg, $idPlantel, $faltan);
        $pdo->prepare(
            'UPDATE preregistro_alertas SET resuelta = 1
             WHERE id_preregistro = ? AND tipo = \'factura_incompleta\' AND resuelta = 0'
        )->execute([$idPrereg]);
    } else {
        $pdo->prepare(
            'UPDATE preregistro_alertas SET resuelta = 1
             WHERE id_preregistro = ? AND tipo = \'factura_incompleta\' AND resuelta = 0'
        )->execute([$idPrereg]);
    }
}

function preregistro_actualizar_flag_edad(PDO $pdo, int $idPrereg, int $idPlantel, ?int $idEsp, ?int $edad): void
{
    $requiere = 0;
    $motivo = '';
    if ($idEsp > 0) {
        $st = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $st->execute([$idEsp]);
        $esp = $st->fetch(PDO::FETCH_ASSOC);
        if ($esp) {
            $val = catalog_validar_edad_especialidad($esp, $edad);
            if (!$val['ok']) {
                $requiere = 1;
                $motivo = $val['message'] ?? 'Edad fuera de rango';
            }
        }
    }
    $pdo->prepare('UPDATE preregistros SET edad_requiere_autorizacion = ? WHERE id_preregistro = ?')
        ->execute([$requiere, $idPrereg]);

    $pdo->prepare(
        'UPDATE preregistro_alertas SET resuelta = 1
         WHERE id_preregistro = ? AND tipo = \'general\' AND mensaje LIKE \'%autorización por edad%\''
    )->execute([$idPrereg]);

    if ($requiere) {
        preregistro_crear_alerta(
            $pdo,
            $idPrereg,
            $idPlantel,
            'general',
            'Inscripción requiere autorización por edad al cobrar: ' . $motivo
        );
    }
}

function preregistro_sync_lista_cache(PDO $pdo, int $idPlantel, bool $forzar = false): void
{
    $key = 'hay_prereg_sync_ts_' . $idPlantel;
    $last = (int) ($_SESSION[$key] ?? 0);
    if (!$forzar && $last > 0 && (time() - $last) < 300) {
        return;
    }
    preregistro_sync_alertas_apertura($pdo, $idPlantel);
    preregistro_sync_recordatorios_pendientes($pdo, $idPlantel);
    $_SESSION[$key] = time();
}

/**
 * Lista paginada para DataTables (server-side).
 *
 * @return array{draw:int, recordsTotal:int, recordsFiltered:int, data:array<int, array<int, string>>}
 */
function preregistro_datatable(PDO $pdo, int $idPlantel, array $req): array
{
    $draw = (int) ($req['draw'] ?? 0);
    $start = max(0, (int) ($req['start'] ?? 0));
    $length = (int) ($req['length'] ?? 25);
    if ($length < 10) {
        $length = 10;
    }
    if ($length > 100) {
        $length = 100;
    }

    $search = trim((string) ($req['search']['value'] ?? $req['q'] ?? ''));
    $estado = trim((string) ($req['estado'] ?? ''));

    $orderCol = (int) ($req['order'][0]['column'] ?? 2);
    $orderDir = strtolower((string) ($req['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderMap = [
        1 => 'asesor_nombre',
        2 => 'comision_label',
        3 => 'p.creado_en',
        4 => 'p.nombres',
        5 => 'p.monto_apartado',
        6 => 'p.telefono',
        7 => 'p.email',
        8 => 'p.observaciones',
    ];
    $orderBy = $orderMap[$orderCol] ?? 'p.creado_en';

    $from = 'FROM preregistros p
            INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
            LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
            LEFT JOIN asesor_entrevistas ent ON ent.id_entrevista = p.id_entrevista_origen
            LEFT JOIN usuarios uc ON uc.id_usuario = p.id_usuario_asesor
            LEFT JOIN usuarios ue ON ue.id_usuario = ent.id_usuario_asesor';
    $where = 'WHERE p.id_plantel = ?';
    $params = [$idPlantel];

    if ($estado !== '') {
        $where .= ' AND p.estado = ?';
        $params[] = $estado;
    }
    if ($search !== '') {
        $where .= ' AND (p.nombres LIKE ? OR p.apellido_paterno LIKE ? OR p.apellido_materno LIKE ?
                 OR p.telefono LIKE ? OR p.telefono2 LIKE ? OR p.email LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 6, $like));
    }

    $stTotal = $pdo->prepare('SELECT COUNT(*) FROM preregistros p WHERE p.id_plantel = ?');
    $stTotal->execute([$idPlantel]);
    $recordsTotal = (int) $stTotal->fetchColumn();

    $stFiltered = $pdo->prepare('SELECT COUNT(*) ' . $from . ' ' . $where);
    $stFiltered->execute($params);
    $recordsFiltered = (int) $stFiltered->fetchColumn();

    $sql = 'SELECT p.*,
            CONCAT(u.nombre, \' \', u.apellido) AS asesor_nombre,
            e.nombre AS especialidad_nombre,
            e.inscripcion_abierta,
            ent.id_usuario_asesor AS entrevista_asesor_id,
            CONCAT(uc.nombre, \' \', uc.apellido) AS comision_asesor_nombre,
            CONCAT(ue.nombre, \' \', ue.apellido) AS entrevista_asesor_nombre,
            (SELECT COUNT(*) FROM preregistro_alertas a
             WHERE a.id_preregistro = p.id_preregistro AND a.resuelta = 0) AS alertas_activas
            ' . $from . ' ' . $where . ' ORDER BY ' . ($orderBy === 'comision_label' ? 'p.comision_cncm, uc.nombre' : $orderBy) . ' ' . $orderDir . ', p.id_preregistro DESC
            LIMIT ' . $length . ' OFFSET ' . $start;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = preregistro_labels();

    $data = [];
    foreach ($rows as $p) {
        $data[] = preregistro_datatable_cells($p, $labels);
    }

    return [
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data,
    ];
}

function preregistro_comision_label_from_row(array $p): string
{
    if (!empty($p['comision_cncm'])) {
        return 'CNCM';
    }
    $explicito = trim((string) ($p['comision_asesor_nombre'] ?? ''));
    if ($explicito !== '') {
        return $explicito;
    }
    $ent = trim((string) ($p['entrevista_asesor_nombre'] ?? ''));
    if ($ent !== '') {
        return $ent;
    }

    return trim((string) ($p['asesor_nombre'] ?? ''));
}

/** @return array<int, string> */
function preregistro_datatable_cells(array $p, array $labels): array
{
    $id = (int) $p['id_preregistro'];
    $nombre = mb_strtoupper(preregistro_nombre_completo($p), 'UTF-8');
    $estado = $p['estado'];
    $apartado = (int) $p['tiene_apartado']
        ? catalog_format_mxn((float) ($p['monto_apartado'] ?? 0))
        : '$ 0.00';
    $fecha = date('d/m/Y', strtotime($p['creado_en']));
    $tel = $p['telefono'] ?: ($p['telefono2'] ?? '');
    $obs = $p['observaciones'] ?? '';
    if ($estado === 'perdido' && !empty($p['motivo_perdido'])) {
        $obs = trim($obs . ' · Perdido: ' . $p['motivo_perdido']);
    }
    $rowAlert = preregistro_fila_destacada($p);
    $puedeAcciones = !in_array($estado, ['perdido', 'inscrito'], true);
    $puedeCobrar = preregistro_puede_cobrar();
    $puedeFactura = preregistro_puede_gestionar_factura();
    $puedeComision = preregistro_puede_reasignar_comision();
    $comisionLbl = preregistro_comision_label_from_row($p);
    $capturoLbl = htmlspecialchars(mb_strtoupper($p['asesor_nombre'] ?? '', 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $comisionHtml = htmlspecialchars(mb_strtoupper($comisionLbl, 'UTF-8'), ENT_QUOTES, 'UTF-8');

    $acciones = '<div class="prereg-acciones">';
    if ($puedeComision) {
        $acciones .= '<button type="button" class="btn-icon btn-icon--comision btn-comision-prereg" title="Asignar comisión" data-id="' . $id . '"><i class="fas fa-user-tag"></i></button>';
    }
    if ($puedeAcciones) {
        if ($puedeCobrar) {
            $acciones .= '<button type="button" class="btn-icon btn-icon--ok btn-inscribir-prereg" title="Inscribir" data-id="' . $id . '"><i class="fas fa-user-check"></i></button>';
            $acciones .= '<button type="button" class="btn-icon btn-icon--apartado btn-apartado-prereg" title="Apartado" data-id="' . $id . '" data-monto="' . htmlspecialchars((string) ($p['monto_apartado'] ?? ''), ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-hand-holding-usd"></i></button>';
        }
        $acciones .= '<button type="button" class="btn-icon btn-icon--wait btn-pendiente-prereg" title="Pendiente" data-id="' . $id . '"><i class="fas fa-clock"></i></button>';
        $acciones .= '<button type="button" class="btn-icon btn-icon--del btn-perdido-prereg" title="Perdido" data-id="' . $id . '"><i class="fas fa-trash-alt"></i></button>';
        if ($puedeFactura && (int) ($p['factura_datos_pendientes'] ?? 0)) {
            $acciones .= '<button type="button" class="btn-icon btn-icon--factura" title="Completar datos de factura" onclick="cargarSeccion(\'cola_facturacion\', \'id=' . $id . '\')"><i class="fas fa-file-invoice"></i></button>';
        }
    } elseif ($puedeFactura && (int) ($p['factura_datos_pendientes'] ?? 0)) {
        $acciones .= '<button type="button" class="btn-icon btn-icon--factura" title="Completar datos de factura" onclick="cargarSeccion(\'cola_facturacion\', \'id=' . $id . '\')"><i class="fas fa-file-invoice"></i></button>';
    } else {
        $acciones .= '<span style="color:#999;font-size:0.75rem;">—</span>';
    }
    $nav = preregistro_nav_destino($p);
    if ($nav['seccion'] === 'alumno_detalle') {
        $acciones .= '<button type="button" class="btn-icon btn-icon--ver-alumno" title="Ver alumno inscrito" onclick="cargarSeccion(\'alumno_detalle\', \'' . htmlspecialchars($nav['params'], ENT_QUOTES, 'UTF-8') . '\')"><i class="fas fa-user"></i></button>';
    } else {
        $acciones .= '<button type="button" class="btn-icon btn-icon--edit btn-editar-prereg" title="Editar" data-id="' . $id . '"><i class="fas fa-pen"></i></button>';
    }
    $acciones .= '</div>';

    if ($nav['seccion'] === 'alumno_detalle') {
        $nombreHtml = '<a class="prereg-nombre-link" onclick="cargarSeccion(\'alumno_detalle\', \'' . htmlspecialchars($nav['params'], ENT_QUOTES, 'UTF-8') . '\')">' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</a>';
    } else {
        $nombreHtml = '<a class="prereg-nombre-link btn-editar-prereg" data-id="' . $id . '">' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    if ($estado !== 'activo') {
        $lbl = htmlspecialchars($labels['estado'][$estado] ?? $estado, ENT_QUOTES, 'UTF-8');
        $nombreHtml .= '<br><span class="prereg-badge prereg-badge--estado-' . htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') . '">' . $lbl . '</span>';
    }
    $nombreHtml .= preregistro_icono_factura_pendiente_html($p);

    $row = [
        $acciones,
        $capturoLbl,
        $comisionHtml,
        '<span data-order="' . strtotime($p['creado_en']) . '">' . $fecha . '</span>',
        $nombreHtml,
        '<span class="prereg-apartado" data-order="' . (float) ($p['monto_apartado'] ?? 0) . '">' . htmlspecialchars($apartado, ENT_QUOTES, 'UTF-8') . '</span>',
        htmlspecialchars($tel, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($p['email'] ?? '', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(mb_strimwidth($obs, 0, 120, '…'), ENT_QUOTES, 'UTF-8'),
    ];
    if ($rowAlert) {
        $row['DT_RowClass'] = 'row-alert';
    }
    $row['DT_RowAttr'] = ['data-id' => (string) $id];

    return $row;
}

/** @return array<int, array<string, mixed>> */
function preregistro_listar(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    preregistro_sync_lista_cache($pdo, $idPlantel);

    $sql = 'SELECT p.*,
            CONCAT(u.nombre, \' \', u.apellido) AS asesor_nombre,
            e.nombre AS especialidad_nombre,
            e.inscripcion_abierta,
            ent.id_usuario_asesor AS entrevista_asesor_id,
            CONCAT(uc.nombre, \' \', uc.apellido) AS comision_asesor_nombre,
            CONCAT(ue.nombre, \' \', ue.apellido) AS entrevista_asesor_nombre,
            (SELECT COUNT(*) FROM preregistro_alertas a
             WHERE a.id_preregistro = p.id_preregistro AND a.resuelta = 0) AS alertas_activas
            FROM preregistros p
            INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
            LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
            LEFT JOIN asesor_entrevistas ent ON ent.id_entrevista = p.id_entrevista_origen
            LEFT JOIN usuarios uc ON uc.id_usuario = p.id_usuario_asesor
            LEFT JOIN usuarios ue ON ue.id_usuario = ent.id_usuario_asesor
            WHERE p.id_plantel = ?';
    $params = [$idPlantel];

    if (!empty($filtros['estado'])) {
        $sql .= ' AND p.estado = ?';
        $params[] = $filtros['estado'];
    }
    if (!empty($filtros['q'])) {
        $sql .= ' AND (p.nombres LIKE ? OR p.apellido_paterno LIKE ? OR p.apellido_materno LIKE ?
                 OR p.telefono LIKE ? OR p.email LIKE ?)';
        $like = '%' . $filtros['q'] . '%';
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    $sql .= ' ORDER BY p.creado_en DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<int, array<string, mixed>> */
function preregistro_alertas_plantel(PDO $pdo, int $idPlantel, int $limite = 20): array
{
    $stmt = $pdo->prepare(
        'SELECT a.*, p.nombres, p.apellido_paterno, p.telefono, p.id_preregistro
         FROM preregistro_alertas a
         INNER JOIN preregistros p ON p.id_preregistro = a.id_preregistro
         WHERE a.id_plantel = ? AND a.resuelta = 0
           AND a.tipo <> \'factura_incompleta\'
           AND (a.fecha_programada IS NULL OR a.fecha_programada <= CURDATE())
         ORDER BY a.leida ASC, a.creado_en DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $idPlantel, PDO::PARAM_INT);
    $stmt->bindValue(2, $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Pre-registro ya cerrado como inscrito. */
function preregistro_esta_inscrito(array $row): bool
{
    return ($row['estado'] ?? '') === 'inscrito';
}

/** Id del alumno vinculado (0 si no hay). */
function preregistro_id_alumno_vinculado(array $row): int
{
    return (int) ($row['id_alumno_vinculado'] ?? 0);
}

/** Si está inscrito, id del alumno para abrir perfil; 0 si no aplica. */
function preregistro_redirect_alumno_id(array $row): int
{
    if (!preregistro_esta_inscrito($row)) {
        return 0;
    }

    return preregistro_id_alumno_vinculado($row);
}

/** Edición permitida: activo/pendiente; inscrito solo recepción (factura) o con datos fiscales pendientes. */
function preregistro_puede_editar(array $row): bool
{
    if (!preregistro_esta_inscrito($row)) {
        return true;
    }
    if (preregistro_puede_gestionar_factura()) {
        return true;
    }

    return (int) ($row['factura_datos_pendientes'] ?? 0) === 1;
}

/** Destino de navegación al hacer clic en nombre/editar. */
function preregistro_nav_destino(array $row): array
{
    $idAlumno = preregistro_redirect_alumno_id($row);
    if ($idAlumno > 0 && !preregistro_puede_editar($row)) {
        return ['seccion' => 'alumno_detalle', 'params' => 'id=' . $idAlumno];
    }

    return ['seccion' => 'pre_registro_nuevo', 'params' => 'id=' . (int) ($row['id_preregistro'] ?? 0)];
}

function preregistro_nombre_completo(array $p): string
{
    return trim(
        ($p['nombres'] ?? '') . ' ' .
        ($p['apellido_paterno'] ?? '') . ' ' .
        ($p['apellido_materno'] ?? '')
    );
}

function preregistro_labels(): array
{
    return [
        'medio_entero' => [
            'redes_sociales' => 'Redes sociales',
            'publicidad' => 'Publicidad',
            'cartas' => 'Cartas',
            'pasando' => 'Pasando',
            'recomendado' => 'Recomendado',
            'crm' => 'CRM',
            'cita_crm' => 'Cita de CRM',
            'otro' => 'Otro',
        ],
        'grado_estudios' => [
            'primaria' => 'Primaria',
            'secundaria' => 'Secundaria',
            'preparatoria' => 'Preparatoria',
            'universidad' => 'Universidad',
            'otros' => 'Otros',
        ],
        'categoria_perdido' => [
            'no_contesta' => 'No contesta',
            'precio' => 'Precio / presupuesto',
            'cambio_ciudad' => 'Se mudó / lejos',
            'otra_escuela' => 'Eligió otra escuela',
            'sin_interes' => 'Ya no le interesa',
            'otro' => 'Otro',
        ],
        'categoria_pendiente' => [
            'espera_respuesta' => 'Espera respuesta del prospecto',
            'espera_pago' => 'Espera apartado / pago',
            'evaluando' => 'Evaluando opciones',
            'sin_horario' => 'Sin horario compatible',
            'ubicacion' => 'Pendiente examen de ubicación',
            'otro' => 'Otro',
        ],
        'estado' => [
            'activo' => 'Activo',
            'pendiente' => 'Pendiente',
            'perdido' => 'Perdido',
            'inscrito' => 'Inscrito',
        ],
    ];
}

/** Estados disponibles en el filtro de la lista (sin legacy «activo»). */
function preregistro_estados_filtro(): array
{
    $estados = preregistro_labels()['estado'];
    unset($estados['activo']);

    return $estados;
}
