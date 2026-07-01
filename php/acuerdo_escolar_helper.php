<?php



/**

 * Acuerdo escolar versionado: publicación por supervisor y aceptación obligatoria del alumno.

 */



function acuerdo_escolar_puede_publicar(): bool

{

    if (function_exists('rbac_cap') && rbac_cap('menu_supervisor_acuerdo')) {

        return true;

    }



    return function_exists('rbac_rol_real') && rbac_rol_real() === 'supervisor';

}



function acuerdo_escolar_ensure_schema(PDO $pdo): void

{

    if (!function_exists('plantel_ensure_column')) {

        return;

    }



    $pdo->exec(

        'CREATE TABLE IF NOT EXISTS acuerdo_escolar_version (

            id_acuerdo_version INT UNSIGNED NOT NULL AUTO_INCREMENT,

            version_label VARCHAR(40) NOT NULL,

            contenido MEDIUMTEXT NOT NULL,

            vigente_desde DATE NULL,

            activo_para_nuevos TINYINT(1) NOT NULL DEFAULT 0,

            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            id_usuario INT UNSIGNED NULL,

            PRIMARY KEY (id_acuerdo_version),

            KEY idx_aev_activo (activo_para_nuevos, id_acuerdo_version)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'

    );



    $pdo->exec(

        'CREATE TABLE IF NOT EXISTS alumno_acuerdo_aceptacion (

            id_aceptacion INT UNSIGNED NOT NULL AUTO_INCREMENT,

            id_alumno INT UNSIGNED NOT NULL,

            id_acuerdo_version INT UNSIGNED NOT NULL,

            fecha_aceptacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            ip VARCHAR(45) NULL,

            id_usuario INT UNSIGNED NULL,

            PRIMARY KEY (id_aceptacion),

            UNIQUE KEY uq_aaa_alumno_version (id_alumno, id_acuerdo_version),

            KEY idx_aaa_version (id_acuerdo_version)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'

    );



    plantel_ensure_column($pdo, 'alumnos', 'acuerdo_pendiente_version', 'INT UNSIGNED NULL', 'perfil_completado_en');



    if (hay_meta_get($pdo, 'acuerdo_escolar_v1_seeded') !== '1') {

        acuerdo_escolar_seed_inicial($pdo);

        hay_meta_set($pdo, 'acuerdo_escolar_v1_seeded', '1');

    }

}



function acuerdo_escolar_seed_inicial(PDO $pdo): void

{

    $chk = $pdo->query('SELECT id_acuerdo_version FROM acuerdo_escolar_version LIMIT 1');

    if ((int) ($chk->fetchColumn() ?: 0) > 0) {

        return;

    }

    $texto = "ACUERDO ESCOLAR CNCM\n\n"

        . "El alumno y/o tutor declara conocer y aceptar el reglamento interno, políticas de convivencia, "

        . "pagos, asistencia y uso de plataformas digitales del plantel.\n\n"

        . "La dirección podrá publicar nuevas versiones; al ingresar al portal deberá aceptar la versión vigente.";

    $pdo->prepare(

        'INSERT INTO acuerdo_escolar_version (version_label, contenido, vigente_desde, activo_para_nuevos)

         VALUES (?, ?, CURDATE(), 1)'

    )->execute(['v1', $texto]);

}



/** @return array<string, mixed>|null */

function acuerdo_version_activo_nuevos(PDO $pdo): ?array

{

    acuerdo_escolar_ensure_schema($pdo);

    $st = $pdo->query(

        'SELECT * FROM acuerdo_escolar_version

         WHERE activo_para_nuevos = 1

         ORDER BY id_acuerdo_version DESC LIMIT 1'

    );

    $r = $st->fetch(PDO::FETCH_ASSOC);



    return $r ?: null;

}



/** @return list<array<string, mixed>> */

function acuerdo_escolar_listar(PDO $pdo): array

{

    acuerdo_escolar_ensure_schema($pdo);

    $st = $pdo->query(

        'SELECT v.*,

                (SELECT COUNT(*) FROM alumno_acuerdo_aceptacion a WHERE a.id_acuerdo_version = v.id_acuerdo_version) AS num_aceptaciones

         FROM acuerdo_escolar_version v

         ORDER BY v.id_acuerdo_version DESC'

    );



    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

}



function acuerdo_alumno_acepto_version(PDO $pdo, int $idAlumno, int $idVersion): bool

{

    if ($idAlumno <= 0 || $idVersion <= 0) {

        return false;

    }

    acuerdo_escolar_ensure_schema($pdo);

    $st = $pdo->prepare(

        'SELECT 1 FROM alumno_acuerdo_aceptacion

         WHERE id_alumno = ? AND id_acuerdo_version = ? LIMIT 1'

    );

    $st->execute([$idAlumno, $idVersion]);



    return (bool) $st->fetchColumn();

}



/** Asigna acuerdo vigente al inscribir o al publicar nueva versión. */

function acuerdo_asignar_alumno(PDO $pdo, int $idAlumno, ?int $idVersion = null): int

{

    acuerdo_escolar_ensure_schema($pdo);

    if ($idVersion === null || $idVersion <= 0) {

        $activo = acuerdo_version_activo_nuevos($pdo);

        $idVersion = $activo ? (int) $activo['id_acuerdo_version'] : 0;

    }

    if ($idVersion <= 0) {

        return 0;

    }

    if (acuerdo_alumno_acepto_version($pdo, $idAlumno, $idVersion)) {

        $pdo->prepare('UPDATE alumnos SET acuerdo_pendiente_version = NULL WHERE id_alumno = ? AND acuerdo_pendiente_version = ?')

            ->execute([$idAlumno, $idVersion]);



        return $idVersion;

    }

    $pdo->prepare('UPDATE alumnos SET acuerdo_pendiente_version = ? WHERE id_alumno = ?')

        ->execute([$idVersion, $idAlumno]);



    return $idVersion;

}



function alumno_debe_aceptar_acuerdo(PDO $pdo, int $userId): bool

{

    if ($userId <= 0) {

        return false;

    }

    if (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() !== 'alumno') {

        return false;

    }

    $idAlumno = function_exists('alumno_perfil_id_desde_usuario')

        ? alumno_perfil_id_desde_usuario($pdo, $userId)

        : 0;

    if ($idAlumno <= 0) {

        return false;

    }

    acuerdo_escolar_ensure_schema($pdo);

    $st = $pdo->prepare('SELECT acuerdo_pendiente_version FROM alumnos WHERE id_alumno = ? LIMIT 1');

    $st->execute([$idAlumno]);

    $pend = (int) ($st->fetchColumn() ?: 0);

    if ($pend <= 0) {

        return false;

    }



    return !acuerdo_alumno_acepto_version($pdo, $idAlumno, $pend);

}



/** @return array<string, mixed>|null */

function acuerdo_pendiente_para_alumno(PDO $pdo, int $idAlumno): ?array

{

    if ($idAlumno <= 0) {

        return null;

    }

    acuerdo_escolar_ensure_schema($pdo);

    $st = $pdo->prepare('SELECT acuerdo_pendiente_version FROM alumnos WHERE id_alumno = ? LIMIT 1');

    $st->execute([$idAlumno]);

    $idVer = (int) ($st->fetchColumn() ?: 0);

    if ($idVer <= 0 || acuerdo_alumno_acepto_version($pdo, $idAlumno, $idVer)) {

        return null;

    }

    $stV = $pdo->prepare('SELECT * FROM acuerdo_escolar_version WHERE id_acuerdo_version = ? LIMIT 1');

    $stV->execute([$idVer]);



    return $stV->fetch(PDO::FETCH_ASSOC) ?: null;

}



/** @return array<string, mixed>|null Última aceptación firmada. */

function acuerdo_ultima_aceptacion_alumno(PDO $pdo, int $idAlumno): ?array

{

    if ($idAlumno <= 0) {

        return null;

    }

    acuerdo_escolar_ensure_schema($pdo);

    $st = $pdo->prepare(

        'SELECT a.*, v.version_label, v.contenido

         FROM alumno_acuerdo_aceptacion a

         INNER JOIN acuerdo_escolar_version v ON v.id_acuerdo_version = a.id_acuerdo_version

         WHERE a.id_alumno = ?

         ORDER BY a.fecha_aceptacion DESC

         LIMIT 1'

    );

    $st->execute([$idAlumno]);

    $row = $st->fetch(PDO::FETCH_ASSOC);



    return $row ?: null;

}



function acuerdo_registrar_aceptacion(PDO $pdo, int $idAlumno, int $idUsuario, ?string $ip = null): array

{

    acuerdo_escolar_ensure_schema($pdo);

    $pend = acuerdo_pendiente_para_alumno($pdo, $idAlumno);

    if (!$pend) {

        return ['ok' => false, 'message' => 'No hay acuerdo pendiente por aceptar'];

    }

    $idVer = (int) $pend['id_acuerdo_version'];

    $pdo->prepare(

        'INSERT INTO alumno_acuerdo_aceptacion (id_alumno, id_acuerdo_version, ip, id_usuario)

         VALUES (?,?,?,?)

         ON DUPLICATE KEY UPDATE fecha_aceptacion = NOW(), ip = VALUES(ip), id_usuario = VALUES(id_usuario)'

    )->execute([$idAlumno, $idVer, $ip, $idUsuario > 0 ? $idUsuario : null]);

    $pdo->prepare('UPDATE alumnos SET acuerdo_pendiente_version = NULL WHERE id_alumno = ?')

        ->execute([$idAlumno]);



    return [

        'ok' => true,

        'message' => 'Acuerdo escolar aceptado correctamente.',

        'id_acuerdo_version' => $idVer,

        'version_label' => $pend['version_label'] ?? '',

    ];

}



/** Publica nueva versión y marca re-aceptación a alumnos activos. */

function acuerdo_publicar_nueva_version(PDO $pdo, string $label, string $contenido, ?int $idPlantel = null): array

{

    if (!acuerdo_escolar_puede_publicar()) {

        return ['ok' => false, 'message' => 'Sin permiso'];

    }

    acuerdo_escolar_ensure_schema($pdo);

    $label = trim($label) !== '' ? trim($label) : ('v' . date('Y.m'));

    $contenido = trim($contenido);

    if ($contenido === '') {

        return ['ok' => false, 'message' => 'Escriba el texto del acuerdo escolar'];

    }



    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);

    $pdo->exec('UPDATE acuerdo_escolar_version SET activo_para_nuevos = 0');

    $pdo->prepare(

        'INSERT INTO acuerdo_escolar_version (version_label, contenido, vigente_desde, activo_para_nuevos, id_usuario)

         VALUES (?,?,CURDATE(),1,?)'

    )->execute([$label, $contenido, $idUsuario > 0 ? $idUsuario : null]);

    $idNuevo = (int) $pdo->lastInsertId();



    $sql = "UPDATE alumnos SET acuerdo_pendiente_version = ?

            WHERE estado = 'activo' AND (acuerdo_pendiente_version IS NULL OR acuerdo_pendiente_version <> ?)";

    $params = [$idNuevo, $idNuevo];

    if ($idPlantel !== null && $idPlantel > 0) {

        $sql .= ' AND id_plantel = ?';

        $params[] = $idPlantel;

    }

    $pdo->prepare($sql)->execute($params);



    $stCount = $pdo->prepare('SELECT COUNT(*) FROM alumnos WHERE acuerdo_pendiente_version = ?');

    $stCount->execute([$idNuevo]);

    $marcados = (int) $stCount->fetchColumn();



    return [

        'ok' => true,

        'message' => 'Acuerdo publicado. ' . $marcados . ' alumno(s) deberán aceptar la nueva versión.',

        'id_acuerdo_version' => $idNuevo,

        'version_label' => $label,

        'alumnos_marcados' => $marcados,

    ];

}


