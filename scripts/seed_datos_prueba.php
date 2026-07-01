<?php
/**
 * Datos de prueba: usuarios, profesores, grupos ING, alumnos.
 * CLI: php scripts/seed_datos_prueba.php
 * Web: php/seed_datos_prueba_run.php?confirm=1
 */
declare(strict_types=1);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (!defined('HAY_SKIP_SCHEMA_BOOTSTRAP')) {
        define('HAY_SKIP_SCHEMA_BOOTSTRAP', false);
    }
    require __DIR__ . '/../config.php';
}

if (!function_exists('auth_ensure_email_column')) {
    require_once __DIR__ . '/../php/auth_helpers.php';
}

define('SEED_RUNNING', true);
const SEED_PASSWORD = '1234';
const SEED_TAG = 'seed_prueba_2025';

$passHash = password_hash(SEED_PASSWORD, PASSWORD_BCRYPT);

function seed_log(string $msg): void
{
    echo $msg . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
}

function seed_fail(string $msg): void
{
    seed_log('ERROR: ' . $msg);
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException($msg);
    }
    exit(1);
}

/** @return array<string, int> */
function seed_plantel_ids(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query('SELECT id_plantel, slug FROM planteles WHERE activo = 1')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['slug']] = (int) $r['id_plantel'];
    }
    return $out;
}

function seed_usuario(
    PDO $pdo,
    string $username,
    string $nombre,
    string $apellido,
    string $rol,
    int $idPlantel,
    string $departamento,
    string $passHash
): int {
    auth_ensure_email_column($pdo);

    $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $existing = $st->fetchColumn();
    if ($existing) {
        seed_log("  · Usuario ya existe: {$username}");
        return (int) $existing;
    }

    $email = $username . '@' . INSTITUTIONAL_EMAIL_DOMAIN;
    $pdo->prepare(
        'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, departamento, id_plantel, fecha_creacion)
         VALUES (?,?,?,?,?,?,?,?,NOW())'
    )->execute([$nombre, $apellido, $username, $email, $passHash, $rol, $departamento, $idPlantel]);

    $id = (int) $pdo->lastInsertId();
    seed_log("  + Usuario {$username} ({$rol}) — {$nombre} {$apellido}");
    return $id;
}

function seed_grupo_clave_simple(PDO $pdo, int $idPlantel, string $prefijo): string
{
    if (function_exists('grupo_clave_generar')) {
        try {
            $gen = grupo_clave_generar($pdo, $idPlantel, 'I', 'S', false, false);
            return $gen['clave'];
        } catch (Throwable $e) {
            seed_log('  (aviso clave auto: ' . $e->getMessage() . ')');
        }
    }
    $n = (int) $pdo->query(
        "SELECT COUNT(*) FROM grupos WHERE clave LIKE " . $pdo->quote($prefijo . '%')
    )->fetchColumn();
    return $prefijo . (100 + $n + random_int(1, 50));
}

function seed_crear_grupo_ing(
    PDO $pdo,
    int $idPlantel,
    int $idProfesor,
    int $idEsp,
    ?int $idFase
): ?array {
    $clave = seed_grupo_clave_simple($pdo, $idPlantel, 'IS');

    try {
        $pdo->prepare(
            'INSERT INTO grupos (
                id_plantel, clave, fecha_inicio, id_profesor, id_especialidad, id_fase_actual,
                codigo_area, codigo_horario, es_extensivo, es_personalizado, aula
            ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, 0, 0, ?)'
        )->execute([
            $idPlantel, $clave, $idProfesor, $idEsp, $idFase, 'I', 'S', 'Aula demo ' . SEED_TAG,
        ]);
    } catch (PDOException $e) {
        $pdo->prepare(
            'INSERT INTO grupos (id_plantel, clave, fecha_inicio, id_profesor, aula)
             VALUES (?, ?, CURDATE(), ?, ?)'
        )->execute([$idPlantel, $clave, $idProfesor, 'Aula demo ' . SEED_TAG]);
        if ($idEsp > 0) {
            try {
                $pdo->prepare('UPDATE grupos SET id_especialidad = ? WHERE clave = ? AND id_plantel = ?')
                    ->execute([$idEsp, $clave, $idPlantel]);
            } catch (PDOException $e2) {
                // columna opcional
            }
        }
    }

    $idGrupo = (int) $pdo->lastInsertId();
    if ($idGrupo <= 0) {
        return null;
    }
    seed_log("  + Grupo {$clave}");
    return ['id_grupo' => $idGrupo, 'clave' => $clave];
}

function seed_crear_alumno(
    PDO $pdo,
    int $idPlantel,
    int $idGrupo,
    int $idEsp,
    string $nombres,
    string $apPaterno,
    string $apMaterno,
    string $passHash
): void {
    $nc = alumno_generar_numero_control($pdo, $idPlantel);
    $apellido = trim($apPaterno . ' ' . $apMaterno);

    $pdo->prepare(
        'INSERT INTO alumnos (
            id_plantel, id_grupo, numero_control, nombres, apellido_paterno, apellido_materno,
            nombre, apellido, estado, forma_pago, id_especialidad, fecha_alta
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,CURDATE())'
    )->execute([
        $idPlantel, $idGrupo, $nc, $nombres, $apPaterno, $apMaterno,
        $nombres, $apellido, 'activo', 'mensual', $idEsp ?: null,
    ]);
    $idAlumno = (int) $pdo->lastInsertId();

    try {
        $pdo->prepare(
            'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio) VALUES (?, ?, 1, CURDATE())
             ON DUPLICATE KEY UPDATE activo = 1'
        )->execute([$idAlumno, $idGrupo]);
    } catch (PDOException $e) {
        // tabla opcional
    }

    if ($idEsp > 0 && function_exists('pago_crear_inscripcion')) {
        try {
            pago_crear_inscripcion($pdo, $idAlumno, $idEsp, 'mensual', date('Y-m-d'));
        } catch (Throwable $e) {
            seed_log('    (aviso inscripción: ' . $e->getMessage() . ')');
        }
    }

    if (function_exists('usuario_crear_cuenta_alumno')) {
        try {
            $cuenta = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
            if (!empty($cuenta['ok']) && !empty($cuenta['id_usuario'])) {
                $pdo->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 0 WHERE id_usuario = ?')
                    ->execute([$passHash, $cuenta['id_usuario']]);
            }
        } catch (Throwable $e) {
            seed_log('    (aviso usuario alumno: ' . $e->getMessage() . ')');
        }
    }

    seed_log("    · Alumno #{$nc} {$nombres} {$apPaterno}");
}

// ——— Ejecución principal ———
seed_log('=== Seed datos de prueba CNCM ===');
seed_log('Contraseña: ' . SEED_PASSWORD);
seed_log('');

try {
    hay_bootstrap_schema($pdo);
} catch (Throwable $e) {
    seed_log('Aviso bootstrap: ' . $e->getMessage());
}

$planteles = seed_plantel_ids($pdo);
foreach (['guerrero', 'fuentes', 'salamanca', 'celaya'] as $slug) {
    if (empty($planteles[$slug])) {
        seed_fail("Falta plantel en BD: {$slug}");
    }
}

$idIng = (int) $pdo->query("SELECT id_especialidad FROM especialidades WHERE clave = 'ING' AND activo = 1 LIMIT 1")->fetchColumn();
if ($idIng <= 0) {
    seed_fail('No existe especialidad ING activa. Cree la especialidad ING primero.');
}

$idFaseA1 = 0;
try {
    $stF = $pdo->prepare('SELECT id_fase FROM especialidad_fases WHERE id_especialidad = ? AND activo = 1 ORDER BY orden LIMIT 1');
    $stF->execute([$idIng]);
    $idFaseA1 = (int) $stF->fetchColumn();
} catch (PDOException $e) {
    seed_log('Sin fases en catálogo (opcional): ' . $e->getMessage());
}

seed_log('--- Personal por plantel ---');

$staff = [
    'guerrero' => [
        ['demo.g.deysi', 'Deysi', 'Guerrero', 'gerente', 'Dirección'],
        ['demo.g.mejia', 'Roberto', 'Mejía', 'asesor', 'Ventas'],
        ['demo.g.sharoom', 'Sharoom', 'López', 'profesor', 'Coordinación Inglés'],
        ['demo.g.manuel', 'Manuel', 'Ríos', 'profesor', 'Coordinación Computación y Preparatoria'],
        ['demo.g.karla', 'Karla', 'Núñez', 'admin', 'Recepción'],
    ],
    'fuentes' => [
        ['demo.f.victor', 'Víctor', 'Fuentes', 'gerente', 'Dirección'],
        ['demo.f.lucia', 'Lucía', 'Morales', 'asesor', 'Ventas'],
        ['demo.f.jenny', 'Jenny', 'Ruiz', 'admin', 'Recepción'],
    ],
    'salamanca' => [
        ['demo.s.laura', 'Laura', 'Samudio', 'gerente', 'Dirección'],
        ['demo.s.sarahi', 'Sarahi', 'Delgado', 'asesor', 'Ventas'],
        ['demo.s.janette', 'Janette', 'Vega', 'admin', 'Recepción'],
        ['demo.s.arturo', 'Arturo', 'Mendoza', 'profesor', 'Coordinación Inglés, Computación y Preparatoria'],
    ],
    'celaya' => [
        ['demo.c.lorena', 'Lorena', 'Castillo', 'gerente', 'Dirección'],
        ['demo.c.alejandro', 'Alejandro', 'Torres', 'asesor', 'Ventas'],
        ['demo.c.brenda', 'Brenda', 'Herrera', 'admin', 'Recepción'],
        ['demo.c.carlos', 'Carlos', 'Méndez', 'profesor', 'Coordinación Preparatoria'],
        ['demo.c.jairo', 'Jairo', 'Velasco', 'profesor', 'Coordinación Inglés'],
        ['demo.c.ivan', 'Iván', 'Robles', 'profesor', 'Coordinación Computación'],
    ],
];

foreach ($staff as $slug => $people) {
    $idP = $planteles[$slug];
    seed_log('');
    seed_log("[{$slug}]");
    foreach ($people as [$user, $nom, $ape, $rol, $depto]) {
        seed_usuario($pdo, $user, $nom, $ape, $rol, $idP, $depto, $passHash);
    }
}

seed_log('');
seed_log('--- Profesores de grupo ---');

$profesoresPlantel = [
    'guerrero' => [
        ['demo.g.prof.pedro', 'Pedro', 'Guerrero'],
        ['demo.g.prof.pablo', 'Pablo', 'Guerrero'],
        ['demo.g.prof.penelope', 'Penélope', 'Guerrero'],
    ],
    'fuentes' => [
        ['demo.f.prof.pedro', 'Pedro', 'Fernández'],
        ['demo.f.prof.pablo', 'Pablo', 'Fernández'],
        ['demo.f.prof.patricia', 'Patricia', 'Fernández'],
    ],
    'salamanca' => [
        ['demo.s.prof.pedro', 'Pedro', 'Samudio'],
        ['demo.s.prof.pablo', 'Pablo', 'Samudio'],
        ['demo.s.prof.paula', 'Paula', 'Samudio'],
    ],
    'celaya' => [
        ['demo.c.prof.pedro', 'Pedro', 'Castillo'],
        ['demo.c.prof.pablo', 'Pablo', 'Castillo'],
        ['demo.c.prof.pamela', 'Pamela', 'Castillo'],
    ],
];

$profIds = [];
foreach ($profesoresPlantel as $slug => $profs) {
    $idP = $planteles[$slug];
    seed_log('');
    seed_log("[{$slug}]");
    $profIds[$slug] = [];
    foreach ($profs as [$user, $nom, $ape]) {
        $profIds[$slug][] = seed_usuario($pdo, $user, $nom, $ape, 'profesor', $idP, 'Profesor ING demo', $passHash);
    }
}

$alumnosPorPlantel = [
    'guerrero' => [
        ['Guadalupe', 'García', 'López'], ['Genaro', 'González', 'Martínez'], ['Gustavo', 'Gutiérrez', 'Hernández'],
        ['Gabriela', 'Guerrero', 'Soto'], ['Gael', 'Galván', 'Reyes'], ['Gloria', 'Gómez', 'Vargas'],
        ['Gerardo', 'Gil', 'Mendoza'], ['Griselda', 'Granados', 'Cruz'], ['Gonzalo', 'Guerra', 'Flores'],
        ['Gemma', 'Galindo', 'Ramos'], ['Gisela', 'Gordillo', 'Navarro'], ['Gabino', 'Garza', 'Silva'],
        ['Guillermina', 'Gamboa', 'Ortiz'], ['Gaspar', 'Gálvez', 'Medina'], ['Greta', 'Guevara', 'Campos'],
    ],
    'fuentes' => [
        ['Fernando', 'Fernández', 'Luna'], ['Felipe', 'Flores', 'Aguilar'], ['Florencia', 'Fuentes', 'Ríos'],
        ['Fabián', 'Franco', 'Serrano'], ['Fátima', 'Farías', 'Mora'], ['Francisco', 'Figueroa', 'León'],
        ['Fabiola', 'Félix', 'Cortés'], ['Fausto', 'Frías', 'Pacheco'], ['Fernanda', 'Fajardo', 'Valdez'],
        ['Federico', 'Fonseca', 'Zamora'], ['Flavia', 'Fuente', 'Nájera'], ['Fortunato', 'Fierro', 'Salinas'],
        ['Filomena', 'Franco', 'Tovar'], ['Fidel', 'Fregoso', 'Vega'], ['Frida', 'Falcon', 'Yáñez'],
    ],
    'salamanca' => [
        ['Santiago', 'Samudio', 'Luna'], ['Sandra', 'Salazar', 'Nieto'], ['Samuel', 'Sánchez', 'Ochoa'],
        ['Silvia', 'Soto', 'Paredes'], ['Saúl', 'Solís', 'Quintero'], ['Susana', 'Segura', 'Rosales'],
        ['Salvador', 'Serrano', 'Tapia'], ['Sofía', 'Sandoval', 'Uribe'], ['Sergio', 'Santana', 'Valencia'],
        ['Selena', 'Sierra', 'Wong'], ['Simón', 'Sosa', 'Xochitl'], ['Sabina', 'Suárez', 'Zavala'],
        ['Santos', 'Sarabia', 'Acosta'], ['Sara', 'Salinas', 'Becerra'], ['Sebastián', 'Soria', 'Carrillo'],
    ],
    'celaya' => [
        ['Carlos', 'Castillo', 'Aguilar'], ['Carmen', 'Cervantes', 'Bravo'], ['Cecilia', 'Contreras', 'Díaz'],
        ['César', 'Carrillo', 'Escobar'], ['Clara', 'Cortés', 'Fuentes'], ['Cristina', 'Campos', 'Gómez'],
        ['Cuauhtémoc', 'Cabrera', 'Huerta'], ['Catalina', 'Calderón', 'Ibarra'], ['Ciro', 'Cisneros', 'Juárez'],
        ['Concepción', 'Colín', 'Keller'], ['Cornelio', 'Corona', 'Lara'], ['Cynthia', 'Cuellar', 'Márquez'],
        ['Clemente', 'Chávez', 'Nava'], ['Claudia', 'Cordero', 'Oliva'], ['César', 'Carrasco', 'Ponce'],
    ],
];

seed_log('');
seed_log('--- Grupos ING y alumnos ---');

foreach ($profesoresPlantel as $slug => $_) {
    $idP = $planteles[$slug];
    $alumnos = $alumnosPorPlantel[$slug];
    seed_log('');
    seed_log("[{$slug}]");

    $chk = $pdo->prepare('SELECT COUNT(*) FROM grupos WHERE id_plantel = ? AND aula LIKE ?');
    $chk->execute([$idP, '%' . SEED_TAG . '%']);
    if ((int) $chk->fetchColumn() >= 3) {
        seed_log('  (Ya hay 3 grupos demo — omitiendo creación)');
        continue;
    }

    for ($g = 0; $g < 3; $g++) {
        $idProf = $profIds[$slug][$g] ?? $profIds[$slug][0];
        try {
            $grupo = seed_crear_grupo_ing($pdo, $idP, $idProf, $idIng, $idFaseA1 ?: null);
        } catch (Throwable $e) {
            seed_log('  ERROR grupo: ' . $e->getMessage());
            continue;
        }
        if (!$grupo) {
            continue;
        }
        for ($a = 0; $a < 5; $a++) {
            $idx = $g * 5 + $a;
            try {
                seed_crear_alumno($pdo, $idP, $grupo['id_grupo'], $idIng, $alumnos[$idx][0], $alumnos[$idx][1], $alumnos[$idx][2], $passHash);
            } catch (Throwable $e) {
                seed_log('    ERROR alumno: ' . $e->getMessage());
            }
        }
    }
}

seed_log('');
seed_log('=== Listo ===');
seed_log('Login personal: demo.g.deysi / demo.s.laura / etc. — contraseña ' . SEED_PASSWORD);
seed_log('Login alumnos: número de control — contraseña ' . SEED_PASSWORD);
