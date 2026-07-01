<?php

/**
 * Integración FingerJet — galería para servicio local en PC de recepción.
 *
 * El matching 1:N con ~600 alumnos debe ejecutarse en Windows (SDK HID) en la PC
 * donde está el lector, no en el servidor PHP Linux.
 */

define('HUELLA_FORMATO_FMD', 'dp_fmd');

/** @return array{enabled: bool, mode: string, matcher_url: string, gallery_api: string} */
function huella_fingerjet_config(): array
{
    $enabled = defined('HAY_FINGERJET_ENABLED') && HAY_FINGERJET_ENABLED;
    $mode = defined('HAY_FINGERJET_MODE') ? (string) HAY_FINGERJET_MODE : 'auto';
    if (!in_array($mode, ['off', 'auto', 'required'], true)) {
        $mode = 'auto';
    }
    $matcherUrl = defined('HAY_FINGERJET_MATCHER_URL')
        ? rtrim((string) HAY_FINGERJET_MATCHER_URL, '/')
        : 'http://127.0.0.1:8765';

    return [
        'enabled' => $enabled,
        'mode' => $enabled ? $mode : 'off',
        'matcher_url' => $matcherUrl,
        'gallery_api' => hay_asset_url('php/huella_matcher_api.php'),
    ];
}

function huella_matcher_api_key_valida(): bool
{
    if (!defined('HAY_FINGERJET_MATCHER_KEY') || HAY_FINGERJET_MATCHER_KEY === '') {
        return false;
    }
    $key = $_POST['matcher_key'] ?? $_GET['matcher_key'] ?? $_SERVER['HTTP_X_HAY_MATCHER_KEY'] ?? '';

    return hash_equals((string) HAY_FINGERJET_MATCHER_KEY, (string) $key);
}

function huella_fingerjet_ensure_schema(PDO $pdo): void
{
    huella_ensure_schema($pdo);
    plantel_ensure_column($pdo, 'alumno_huellas', 'template_fmd', 'MEDIUMTEXT NULL', 'template_data');
    plantel_ensure_column($pdo, 'alumno_huellas', 'fmd_formato', 'VARCHAR(30) NULL', 'template_fmd');
}

/**
 * Galería de huellas activas por plantel (para sincronizar en el servicio FingerJet local).
 *
 * @return array{ok: bool, id_plantel: int, generado_en: string, total: int, items: list<array<string, mixed>>}
 */
function huella_matcher_galeria(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT h.id_huella, h.id_alumno, h.codigo_huella, h.formato, h.template_data,
                h.template_fmd, h.fmd_formato, h.dedo,
                a.numero_control,
                CONCAT(COALESCE(a.apellido_paterno, a.apellido, \'\'), \' \', COALESCE(a.nombres, a.nombre, \'\')) AS nombre
         FROM alumno_huellas h
         INNER JOIN alumnos a ON a.id_alumno = h.id_alumno AND a.id_plantel = h.id_plantel
         WHERE h.id_plantel = ? AND h.activo = 1 AND a.estado = \'activo\'
         ORDER BY h.id_huella ASC'
    );
    $st->execute([$idPlantel]);
    $items = [];

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tpl = json_decode((string) ($row['template_data'] ?? ''), true);
        $muestras = is_array($tpl) ? ($tpl['muestras'] ?? []) : [];
        if (!is_array($muestras)) {
            $muestras = [];
        }

        $items[] = [
            'id_huella' => (int) $row['id_huella'],
            'id_alumno' => (int) $row['id_alumno'],
            'codigo_huella' => (string) $row['codigo_huella'],
            'numero_control' => (string) ($row['numero_control'] ?? ''),
            'nombre' => trim((string) ($row['nombre'] ?? '')),
            'dedo' => (string) ($row['dedo'] ?? ''),
            'formato' => (string) ($row['formato'] ?? HUELLA_FORMATO_INTERMEDIATE),
            'muestras' => array_values(array_filter($muestras, static fn($m) => is_string($m) && trim($m) !== '')),
            'template_fmd' => $row['template_fmd'] ?? null,
            'fmd_formato' => $row['fmd_formato'] ?? null,
        ];
    }

    return [
        'ok' => true,
        'id_plantel' => $idPlantel,
        'generado_en' => date('c'),
        'total' => count($items),
        'items' => $items,
    ];
}

/**
 * Guarda plantilla FMD generada por FingerJet (servicio local tras enrolamiento).
 *
 * @return array{ok: bool, message: string}
 */
function huella_registrar_fmd(
    PDO $pdo,
    int $idAlumno,
    int $idPlantel,
    string $fmdBase64,
    string $fmdFormato = 'DP_FMD'
): array {
    if (!huella_puede_enrolar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $fmdBase64 = trim($fmdBase64);
    if ($fmdBase64 === '') {
        return ['ok' => false, 'message' => 'Plantilla FMD vacía'];
    }

    $st = $pdo->prepare(
        'SELECT id_huella FROM alumno_huellas WHERE id_alumno = ? AND id_plantel = ? AND activo = 1 LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $idHuella = (int) $st->fetchColumn();
    if ($idHuella <= 0) {
        return ['ok' => false, 'message' => 'No hay huella activa para este alumno'];
    }

    $pdo->prepare(
        'UPDATE alumno_huellas SET template_fmd = ?, fmd_formato = ?, formato = ? WHERE id_huella = ?'
    )->execute([$fmdBase64, $fmdFormato, HUELLA_FORMATO_FMD, $idHuella]);

    return ['ok' => true, 'message' => 'Plantilla FingerJet (FMD) actualizada'];
}

/** Config para JS de checada (sin claves secretas). */
function huella_fingerjet_config_js(int $idPlantel): array
{
    $cfg = huella_fingerjet_config();

    return [
        'enabled' => $cfg['enabled'],
        'mode' => $cfg['mode'],
        'matcher_url' => $cfg['matcher_url'],
        'id_plantel' => $idPlantel,
        'gallery_api' => $cfg['gallery_api'],
    ];
}
