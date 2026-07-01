<?php
declare(strict_types=1);

/**
 * Diagnóstico de temario CNCM para el tutor IA.
 * Uso: php scripts/tutor_diagnostico_temario.php
 */
require_once dirname(__DIR__) . '/config.php';

alumno_perfil_ensure_schema($pdo);
academico_material_ensure_schema($pdo);
fase_ensure_schema($pdo);

echo "=== Diagnóstico temario CNCM (Tutor IA) ===\n\n";

$especialidades = $pdo->query(
    "SELECT e.id_especialidad, e.clave, e.nombre, e.modalidad, e.activo
     FROM especialidades e
     WHERE e.activo = 1
     ORDER BY e.orden ASC, e.clave ASC"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($especialidades === []) {
    echo "No hay especialidades activas.\n";
    exit(1);
}

$totalSemanas = 0;
$totalFases = 0;
$totalMaterial = 0;

try {
    $totalMaterial = (int) $pdo->query('SELECT COUNT(*) FROM academico_material WHERE activo = 1')->fetchColumn();
} catch (Throwable $e) {
    $totalMaterial = -1;
}

foreach ($especialidades as $e) {
    $idEsp = (int) $e['id_especialidad'];
    $stF = $pdo->prepare(
        'SELECT COUNT(*) FROM especialidad_fases WHERE id_especialidad = ? AND activo = 1'
    );
    $stF->execute([$idEsp]);
    $numFases = (int) $stF->fetchColumn();
    $totalFases += $numFases;

    $stS = $pdo->prepare(
        'SELECT COUNT(*) FROM fase_temario_semana s
         INNER JOIN especialidad_fases f ON f.id_fase = s.id_fase AND f.activo = 1
         WHERE f.id_especialidad = ?'
    );
    $stS->execute([$idEsp]);
    $numSemanas = (int) $stS->fetchColumn();
    $totalSemanas += $numSemanas;

    $stDet = $pdo->prepare(
        'SELECT f.clave_fase, f.nombre_fase, COUNT(s.id_semana) AS semanas_registradas
         FROM especialidad_fases f
         LEFT JOIN fase_temario_semana s ON s.id_fase = f.id_fase
         WHERE f.id_especialidad = ? AND f.activo = 1
         GROUP BY f.id_fase, f.clave_fase, f.nombre_fase
         ORDER BY f.orden ASC'
    );
    $stDet->execute([$idEsp]);
    $fases = $stDet->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo str_repeat('-', 60) . "\n";
    echo sprintf("%s — %s (%s)\n", $e['clave'], $e['nombre'], $e['modalidad']);
    echo sprintf("  Fases activas: %d | Semanas en fase_temario_semana: %d\n", $numFases, $numSemanas);

    if ($fases === []) {
        echo "  (sin fases)\n";
        continue;
    }

    foreach ($fases as $f) {
        $flag = ((int) $f['semanas_registradas']) > 0 ? 'OK' : 'VACÍO';
        echo sprintf(
            "    [%s] %s %s — %d semana(s)\n",
            $flag,
            $f['clave_fase'] ?? '',
            $f['nombre_fase'] ?? '',
            (int) $f['semanas_registradas']
        );
    }
}

echo str_repeat('=', 60) . "\n";
echo "Total especialidades: " . count($especialidades) . "\n";
echo "Total fases activas: $totalFases\n";
echo "Total filas semana (temario): $totalSemanas\n";
echo "Materiales indexados (academico_material): " . ($totalMaterial < 0 ? 'tabla no existe' : (string) $totalMaterial) . "\n";

$stAl = $pdo->query(
    'SELECT COUNT(*) AS total,
            SUM(perfil_completado = 1) AS con_perfil
     FROM alumnos WHERE estado = \'activo\''
)->fetch(PDO::FETCH_ASSOC);
if ($stAl) {
    echo "Alumnos activos: {$stAl['total']} | Con perfil de gustos: {$stAl['con_perfil']}\n";
}

echo "\nSi ves [VACÍO], cargue el temario en Especialidades → Fases → semanas.\n";
echo "Para libros/Moodle: importe filas a academico_material (ver docs/TUTOR_MATERIALES_Y_RAG.md).\n";
