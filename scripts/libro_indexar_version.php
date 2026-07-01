<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$idVersion = (int) ($argv[1] ?? 0);
if ($idVersion <= 0) {
    fwrite(STDERR, "Uso: php scripts/libro_indexar_version.php ID_VERSION\n");
    exit(1);
}

$res = academico_libro_version_indexar($pdo, $idVersion, true);
echo ($res['message'] ?? '') . "\n";
if (!empty($res['paginas'])) {
    echo "Páginas: {$res['paginas']}, chunks: {$res['chunks']}, embeddings: {$res['embeddings']}\n";
}
exit($res['ok'] ? 0 : 1);
