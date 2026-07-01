<?php
declare(strict_types=1);

/**
 * Crea entradas de catálogo de libros por especialidad (sin PDF).
 * Uso: php scripts/seed_libros_catalogo.php
 */
require_once dirname(__DIR__) . '/config.php';

academico_libro_ensure_schema($pdo);

$map = [
    'studentbook' => 'Student Book',
    'workbook' => 'Workbook',
];

$esps = $pdo->query('SELECT id_especialidad, clave, nombre FROM especialidades WHERE activo = 1')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$creados = 0;
foreach ($esps as $e) {
    $id = (int) $e['id_especialidad'];
    $clave = (string) ($e['clave'] ?? '');
    foreach ($map as $tipo => $suffix) {
        $titulo = trim($clave . ' ' . $suffix);
        $res = academico_libro_crear($pdo, $id, $tipo, $titulo);
        if (!empty($res['ok'])) {
            $creados++;
            echo "OK: $titulo\n";
        }
    }
}
echo "Creados: $creados (omitidos si ya existían)\n";
