<?php
declare(strict_types=1);

/**
 * Importa filas a academico_material desde CSV.
 * Uso: php scripts/importar_material_csv.php ruta/archivo.csv
 *
 * Cabeceras esperadas (mínimo titulo):
 * tipo,id_especialidad,id_fase,semana,pagina_inicio,pagina_fin,titulo,descripcion,contenido_texto,ruta_archivo,moodle_url,moodle_course_id,moodle_cm_id,etiquetas
 */
require_once dirname(__DIR__) . '/config.php';

$archivo = $argv[1] ?? '';
if ($archivo === '' || !is_readable($archivo)) {
    fwrite(STDERR, "Uso: php scripts/importar_material_csv.php archivo.csv\n");
    exit(1);
}

academico_material_ensure_schema($pdo);

$fh = fopen($archivo, 'rb');
if ($fh === false) {
    fwrite(STDERR, "No se pudo abrir: $archivo\n");
    exit(1);
}

$headers = fgetcsv($fh);
if ($headers === false) {
    fwrite(STDERR, "CSV vacío\n");
    exit(1);
}
$headers = array_map(static fn ($h) => trim((string) $h), $headers);

$st = $pdo->prepare(
    'INSERT INTO academico_material
     (tipo, id_especialidad, id_fase, semana, pagina_inicio, pagina_fin, titulo, descripcion,
      contenido_texto, ruta_archivo, moodle_url, moodle_course_id, moodle_cm_id, etiquetas, activo)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
);

$tiposValidos = [
    'libro_alumno', 'libro_profesor', 'workbook', 'studentbook', 'guia_profesor',
    'moodle_actividad', 'pdf_fragmento', 'otro',
];

$n = 0;
$errores = 0;
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) === 1 && trim((string) $row[0]) === '') {
        continue;
    }
    $data = [];
    foreach ($headers as $i => $h) {
        $data[$h] = $row[$i] ?? '';
    }
    $titulo = trim((string) ($data['titulo'] ?? ''));
    if ($titulo === '') {
        $errores++;
        continue;
    }
    $tipo = trim((string) ($data['tipo'] ?? 'otro'));
    if (!in_array($tipo, $tiposValidos, true)) {
        $tipo = 'otro';
    }

    $intOrNull = static function ($v): ?int {
        $v = trim((string) $v);
        return $v === '' ? null : (int) $v;
    };

    try {
        $st->execute([
            $tipo,
            $intOrNull($data['id_especialidad'] ?? null),
            $intOrNull($data['id_fase'] ?? null),
            $intOrNull($data['semana'] ?? null),
            $intOrNull($data['pagina_inicio'] ?? null),
            $intOrNull($data['pagina_fin'] ?? null),
            mb_substr($titulo, 0, 220),
            trim((string) ($data['descripcion'] ?? '')) ?: null,
            trim((string) ($data['contenido_texto'] ?? '')) ?: null,
            trim((string) ($data['ruta_archivo'] ?? '')) ?: null,
            trim((string) ($data['moodle_url'] ?? '')) ?: null,
            $intOrNull($data['moodle_course_id'] ?? null),
            $intOrNull($data['moodle_cm_id'] ?? null),
            trim((string) ($data['etiquetas'] ?? '')) ?: null,
        ]);
        $n++;
    } catch (Throwable $e) {
        $errores++;
        fwrite(STDERR, 'Error fila ' . ($n + $errores) . ': ' . $e->getMessage() . "\n");
    }
}
fclose($fh);

echo "Importados: $n | Errores/omitidos: $errores\n";
