<?php

/**
 * Catálogo de planteles (desde BD). Mantener compatibilidad slug => nombre.
 *
 * @return array<string, string>
 */
function planteles_array(PDO $pdo): array
{
    return plantel_catalog_nombres($pdo);
}
