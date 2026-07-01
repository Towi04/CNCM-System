<?php
/**
 * Banners promocionales para el portal del alumno.
 */

function marketing_ensure_schema(PDO $pdo): void
{
    if (function_exists('tour_ensure_schema')) {
        tour_ensure_schema($pdo);
    }
}

/** @return list<array<string, mixed>> */
function marketing_banners_admin_listar(PDO $pdo): array
{
    marketing_ensure_schema($pdo);
    try {
        $st = $pdo->query(
            'SELECT * FROM marketing_banner ORDER BY orden ASC, id_banner DESC'
        );

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function marketing_banner_guardar(PDO $pdo, array $data): array
{
    marketing_ensure_schema($pdo);
    $titulo = trim((string) ($data['titulo'] ?? ''));
    if ($titulo === '') {
        return ['ok' => false, 'message' => 'Indique el título'];
    }

    $id = (int) ($data['id_banner'] ?? 0);
    $imagen = trim((string) ($data['imagen_url'] ?? ''));
    $enlace = trim((string) ($data['enlace_url'] ?? ''));
    $alt = trim((string) ($data['texto_alt'] ?? ''));
    $audiencia = in_array($data['audiencia'] ?? '', ['alumno', 'todos', 'staff'], true)
        ? (string) $data['audiencia'] : 'alumno';
    $activo = (int) ($data['activo'] ?? 1) ? 1 : 0;
    $orden = (int) ($data['orden'] ?? 0);
    $desde = trim((string) ($data['vigente_desde'] ?? ''));
    $hasta = trim((string) ($data['vigente_hasta'] ?? ''));
    $desdeSql = $desde !== '' ? $desde : null;
    $hastaSql = $hasta !== '' ? $hasta : null;

    if ($id > 0) {
        $st = $pdo->prepare(
            'UPDATE marketing_banner SET titulo=?, imagen_url=?, enlace_url=?, texto_alt=?,
             audiencia=?, activo=?, orden=?, vigente_desde=?, vigente_hasta=? WHERE id_banner=?'
        );
        $st->execute([$titulo, $imagen ?: null, $enlace ?: null, $alt ?: null, $audiencia, $activo, $orden, $desdeSql, $hastaSql, $id]);
        if ($st->rowCount() === 0) {
            return ['ok' => false, 'message' => 'Banner no encontrado'];
        }

        return ['ok' => true, 'id_banner' => $id];
    }

    $st = $pdo->prepare(
        'INSERT INTO marketing_banner (titulo, imagen_url, enlace_url, texto_alt, audiencia, activo, orden, vigente_desde, vigente_hasta)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([$titulo, $imagen ?: null, $enlace ?: null, $alt ?: null, $audiencia, $activo, $orden, $desdeSql, $hastaSql]);

    return ['ok' => true, 'id_banner' => (int) $pdo->lastInsertId()];
}

function marketing_banner_eliminar(PDO $pdo, int $idBanner): array
{
    marketing_ensure_schema($pdo);
    if ($idBanner <= 0) {
        return ['ok' => false, 'message' => 'ID inválido'];
    }
    $st = $pdo->prepare('DELETE FROM marketing_banner WHERE id_banner = ?');
    $st->execute([$idBanner]);

    return ['ok' => true];
}

function marketing_puede_administrar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_marketing_banners');
}
