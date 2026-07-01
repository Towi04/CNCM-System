<?php
declare(strict_types=1);

/**
 * Sube el libro de inglés (Student Book + Workbook en un solo PDF).
 *
 * Copie el PDF en uploads/libros_pendientes/ y ejecute:
 *   php scripts/subir_libro_ing.php
 *
 * O con ruta explícita:
 *   php scripts/subir_libro_ing.php "D:\Libros\ING.pdf" 2025.1
 *
 * Página manual donde empieza el Workbook (si auto-detect falla):
 *   php scripts/subir_libro_ing.php uploads/libros_pendientes/ING.pdf 2025.1 87
 */
require_once dirname(__DIR__) . '/config.php';

$pdfPath = $argv[1] ?? '';
$etiqueta = trim($argv[2] ?? date('Y') . '.1');
$paginaWorkbookManual = isset($argv[3]) && is_numeric($argv[3]) ? (int) $argv[3] : 0;
$indexar = !in_array(strtolower($argv[4] ?? ''), ['0', 'no', 'skip'], true);

$pendientesDir = dirname(__DIR__) . '/uploads/libros_pendientes';

if ($pdfPath === '') {
    $candidatos = [];
    if (is_dir($pendientesDir)) {
        foreach (scandir($pendientesDir) ?: [] as $f) {
            if (preg_match('/\.pdf$/i', $f)) {
                $candidatos[] = $pendientesDir . DIRECTORY_SEPARATOR . $f;
            }
        }
    }
    if (count($candidatos) === 1) {
        $pdfPath = $candidatos[0];
        echo "PDF detectado: $pdfPath\n";
    } elseif (count($candidatos) > 1) {
        usort($candidatos, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $pdfPath = $candidatos[0];
        echo "Varios PDF en libros_pendientes; usando el más reciente:\n  $pdfPath\n";
    }
}

if ($pdfPath !== '' && !preg_match('/^[a-zA-Z]:[\\\\\\/]|^\\\\/', $pdfPath) && !str_starts_with($pdfPath, '/')) {
    $relTry = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($pdfPath, '/'));
    if (is_readable($relTry)) {
        $pdfPath = $relTry;
    }
}

if ($pdfPath === '' || !is_readable($pdfPath)) {
    fwrite(STDERR, "No se encontró PDF.\n\n");
    fwrite(STDERR, "1) Copie su libro ING (Student Book + Workbook) a:\n");
    fwrite(STDERR, "   uploads/libros_pendientes/\n\n");
    fwrite(STDERR, "2) Ejecute: php scripts/subir_libro_ing.php\n\n");
    fwrite(STDERR, "O: php scripts/subir_libro_ing.php \"RUTA\\al\\archivo.pdf\" 2025.1\n");
    fwrite(STDERR, "O con página Workbook: php scripts/subir_libro_ing.php \"RUTA\" 2025.1 87\n");
    exit(1);
}

academico_libro_ensure_schema($pdo);

$st = $pdo->query("SELECT id_especialidad, clave, nombre FROM especialidades WHERE clave = 'ING' AND activo = 1 LIMIT 1");
$esp = $st->fetch(PDO::FETCH_ASSOC);
if (!$esp) {
    $st = $pdo->query(
        "SELECT id_especialidad, clave, nombre FROM especialidades
         WHERE activo = 1 AND (clave LIKE 'ING%' OR nombre LIKE '%ingl%')
         ORDER BY orden ASC LIMIT 1"
    );
    $esp = $st->fetch(PDO::FETCH_ASSOC);
}
if (!$esp) {
    fwrite(STDERR, "No se encontró especialidad ING activa.\n");
    exit(1);
}

$idEsp = (int) $esp['id_especialidad'];
$tipo = 'studentbook';
$titulo = 'ING Student Book + Workbook';

echo "=== Subir libro ING ===\n";
echo "Especialidad: {$esp['clave']} — {$esp['nombre']}\n";
echo "Archivo: $pdfPath\n";
echo "Tamaño: " . round((filesize($pdfPath) ?: 0) / 1048576, 2) . " MB\n";

$stLib = $pdo->prepare('SELECT id_libro FROM academico_libro WHERE id_especialidad = ? AND tipo = ? LIMIT 1');
$stLib->execute([$idEsp, $tipo]);
$idLibro = (int) $stLib->fetchColumn();

if ($idLibro <= 0) {
    $res = academico_libro_crear($pdo, $idEsp, $tipo, $titulo);
    if (empty($res['ok'])) {
        fwrite(STDERR, ($res['message'] ?? 'Error') . "\n");
        exit(1);
    }
    $idLibro = (int) $res['id_libro'];
    echo "Catálogo creado: $titulo\n";
} else {
    $pdo->prepare('UPDATE academico_libro SET titulo = ? WHERE id_libro = ?')->execute([$titulo, $idLibro]);
    echo "Catálogo existente: $titulo (id=$idLibro)\n";
}

$dir = academico_libro_root_abs() . DIRECTORY_SEPARATOR . $idLibro;
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    fwrite(STDERR, "No se pudo crear carpeta uploads/libros/$idLibro\n");
    exit(1);
}

$safeEtiqueta = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $etiqueta) ?: 'v';
$rel = ACADEMICO_LIBRO_DIR . '/' . $idLibro . '/' . $safeEtiqueta . '.pdf';
$abs = academico_libro_path_abs($rel);

if (!copy($pdfPath, $abs)) {
    fwrite(STDERR, "No se pudo copiar el PDF.\n");
    exit(1);
}

$hash = hash_file('sha256', $abs) ?: null;
$paginas = academico_libro_pdf_num_paginas($abs);
$pdftotext = academico_libro_pdftotext_disponible();

$paginaWb = $paginaWorkbookManual;
if ($paginaWb <= 0 && $pdftotext && $paginas > 0) {
    echo "Detectando inicio del Workbook…\n";
    $paginaWb = academico_libro_detectar_pagina_workbook($abs, $paginas) ?? 0;
    if ($paginaWb > 0) {
        echo "Workbook detectado en página $paginaWb\n";
    } else {
        echo "No se detectó 'Workbook' automáticamente. Tras subir, puede indicar la página manualmente.\n";
    }
}

$pdo->prepare(
    'INSERT INTO academico_libro_version (id_libro, etiqueta, ruta_pdf, num_paginas, hash_sha256, pagina_inicio_workbook, activo_alumno, activo_rag, estado_indexacion)
     VALUES (?, ?, ?, ?, ?, ?, 0, 0, \'pendiente\')'
)->execute([$idLibro, $etiqueta, $rel, $paginas, $hash, $paginaWb > 0 ? $paginaWb : null]);
$idVersion = (int) $pdo->lastInsertId();

academico_libro_version_activar($pdo, $idVersion, 'alumno');
academico_libro_version_activar($pdo, $idVersion, 'rag');

echo "\nVersión: $etiqueta (id_version=$idVersion)\n";
echo "Páginas: " . ($paginas ?? '?') . "\n";
echo "Activa para alumnos y Tutor IA.\n";

if ($indexar && $pdftotext) {
    echo "\nIndexando páginas + embeddings (puede tardar varios minutos)…\n";
    $idx = academico_libro_version_indexar($pdo, $idVersion, true);
    echo $idx['message'] . "\n";
    if (empty($idx['ok'])) {
        exit(1);
    }
} elseif (!$pdftotext) {
    echo "\npdftotext no instalado: alumnos ya pueden leer en Mis libros.\n";
    echo "Para indexar Tutor IA instale Poppler y ejecute:\n";
    echo "  php scripts/libro_indexar_version.php $idVersion\n";
}

echo "\n✓ Listo. Verifique: alumno → Mis libros · Tutor IA (tras indexar).\n";
exit(0);
