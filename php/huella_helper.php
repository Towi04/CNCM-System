<?php

/**
 * Huellas digitales — U.areU 5300 (DigitalPersona) + rondín recepción.
 */

define('HUELLA_DISPOSITIVO_UAREU', 'uareu_5300');
define('HUELLA_FORMATO_INTERMEDIATE', 'intermediate');

function huella_ensure_schema(PDO $pdo): void
{
    plantel_ensure_column($pdo, 'alumnos', 'huella_registrada', 'TINYINT(1) NOT NULL DEFAULT 0', 'codigo_huella');
    plantel_ensure_column($pdo, 'alumnos', 'huella_registrada_en', 'DATETIME NULL', 'huella_registrada');
    plantel_ensure_column($pdo, 'alumnos', 'huella_dispositivo', 'VARCHAR(60) NULL', 'huella_registrada_en');
    plantel_ensure_column($pdo, 'usuarios', 'huella_registrada', 'TINYINT(1) NOT NULL DEFAULT 0', 'codigo_huella');
    plantel_ensure_column($pdo, 'usuarios', 'huella_registrada_en', 'DATETIME NULL', 'huella_registrada');
    plantel_ensure_column($pdo, 'usuarios', 'huella_dispositivo', 'VARCHAR(60) NULL', 'huella_registrada_en');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_huellas (
            id_huella INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            codigo_huella VARCHAR(40) NOT NULL,
            dedo VARCHAR(24) NOT NULL DEFAULT \'indice_derecho\',
            formato VARCHAR(30) NOT NULL DEFAULT \'intermediate\',
            template_data MEDIUMTEXT NOT NULL,
            dispositivo VARCHAR(60) NOT NULL DEFAULT \'uareu_5300\',
            calidad TINYINT UNSIGNED NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            id_usuario_registro INT UNSIGNED NULL,
            registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_huella),
            KEY idx_ah_alumno (id_alumno),
            KEY idx_ah_codigo (codigo_huella, id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS usuario_huellas (
            id_huella INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            codigo_huella VARCHAR(40) NOT NULL,
            dedo VARCHAR(24) NOT NULL DEFAULT \'indice_derecho\',
            formato VARCHAR(30) NOT NULL DEFAULT \'intermediate\',
            template_data MEDIUMTEXT NOT NULL,
            dispositivo VARCHAR(60) NOT NULL DEFAULT \'uareu_5300\',
            calidad TINYINT UNSIGNED NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            id_usuario_registro INT UNSIGNED NULL,
            registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_huella),
            KEY idx_uh_usuario (id_usuario),
            KEY idx_uh_codigo (codigo_huella, id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** Sin licencia FingerJet: identificación heurística (solo pruebas / planteles pequeños). */
function huella_modo_prueba(): bool
{
    return !(defined('HAY_FINGERJET_ENABLED') && HAY_FINGERJET_ENABLED);
}

/** @return array{min_score: float, min_gap: float} */
function huella_umbral_identificacion(int $totalMuestrasGaleria): array
{
    if (huella_modo_prueba()) {
        if ($totalMuestrasGaleria <= 10) {
            return ['min_score' => 0.28, 'min_gap' => 0.0];
        }
        return ['min_score' => 0.35, 'min_gap' => 0.01];
    }

    return ['min_score' => 0.72, 'min_gap' => 0.05];
}

function huella_puede_enrolar(): bool
{
    return huella_puede_editar_alumno();
}

function huella_puede_enrolar_usuario(): bool
{
    return huella_puede_editar_usuario();
}

/** PIN sugerido: número de control del alumno. */
function huella_sugerir_codigo_alumno(array $alumno): string
{
    $nc = trim((string) ($alumno['numero_control'] ?? ''));
    if ($nc !== '') {
        return huella_normalizar_codigo($nc);
    }

    return huella_normalizar_codigo((string) ($alumno['id_alumno'] ?? ''));
}

/** ID interno sugerido para personal: P + id_usuario. */
function huella_sugerir_codigo_usuario(array $usuario): string
{
    $id = (int) ($usuario['id_usuario'] ?? 0);
    if ($id > 0) {
        return 'P' . $id;
    }

    return huella_normalizar_codigo((string) ($usuario['username'] ?? ''));
}

/** @return array<string, mixed>|null */
function huella_obtener_activa_alumno(PDO $pdo, int $idAlumno): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM alumno_huellas WHERE id_alumno = ? AND activo = 1 ORDER BY id_huella DESC LIMIT 1'
    );
    $st->execute([$idAlumno]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return array<string, mixed> */
function huella_estado_alumno(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $activa = huella_obtener_activa_alumno($pdo, $idAlumno);
    $codigo = trim((string) ($al['codigo_huella'] ?? ''));

    return [
        'ok' => true,
        'id_alumno' => $idAlumno,
        'nombre' => trim(($al['nombres'] ?? $al['nombre'] ?? '') . ' ' . ($al['apellido_paterno'] ?? $al['apellido'] ?? '')),
        'numero_control' => $al['numero_control'] ?? '',
        'codigo_huella' => $codigo,
        'codigo_sugerido' => huella_sugerir_codigo_alumno($al),
        'huella_registrada' => (int) ($al['huella_registrada'] ?? 0) === 1,
        'huella_dispositivo' => $al['huella_dispositivo'] ?? null,
        'huella_registrada_en' => $al['huella_registrada_en'] ?? null,
        'tiene_template' => $activa !== null,
        'dedo' => $activa['dedo'] ?? null,
        'dispositivo' => $activa['dispositivo'] ?? null,
    ];
}

/** @return array<string, mixed>|null */
function huella_obtener_activa_usuario(PDO $pdo, int $idUsuario): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM usuario_huellas WHERE id_usuario = ? AND activo = 1 ORDER BY id_huella DESC LIMIT 1'
    );
    $st->execute([$idUsuario]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return array<string, mixed> */
function huella_estado_usuario(PDO $pdo, int $idUsuario, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, username, codigo_huella, huella_registrada,
                huella_registrada_en, huella_dispositivo, id_plantel
         FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }
    $uPlantel = (int) ($u['id_plantel'] ?? 0);
    if ($uPlantel > 0 && $uPlantel !== $idPlantel && !plantel_es_admin()) {
        return ['ok' => false, 'message' => 'Este usuario no pertenece al plantel activo'];
    }

    $activa = huella_obtener_activa_usuario($pdo, $idUsuario);
    $codigo = trim((string) ($u['codigo_huella'] ?? ''));

    return [
        'ok' => true,
        'id_usuario' => $idUsuario,
        'nombre' => trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')),
        'username' => $u['username'] ?? '',
        'codigo_huella' => $codigo,
        'codigo_sugerido' => huella_sugerir_codigo_usuario($u),
        'huella_registrada' => (int) ($u['huella_registrada'] ?? 0) === 1,
        'huella_registrada_en' => $u['huella_registrada_en'] ?? null,
        'tiene_template' => $activa !== null,
        'dedo' => $activa['dedo'] ?? null,
        'dispositivo' => $activa['dispositivo'] ?? null,
    ];
}

/**
 * Guarda template capturado con U.areU y asigna PIN.
 *
 * @param list<string> $samplesBase64
 * @return array<string, mixed>
 */
function huella_registrar_enrollment(
    PDO $pdo,
    int $idAlumno,
    int $idPlantel,
    array $samplesBase64,
    ?string $codigoHuella = null,
    string $dedo = 'indice_derecho',
    string $dispositivo = HUELLA_DISPOSITIVO_UAREU
): array {
    if (!huella_puede_enrolar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }

    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $samples = array_values(array_filter(array_map('trim', $samplesBase64)));
    if ($samples === []) {
        return ['ok' => false, 'message' => 'No se recibieron muestras de huella'];
    }

    $codigo = $codigoHuella !== null && $codigoHuella !== ''
        ? huella_normalizar_codigo($codigoHuella)
        : huella_sugerir_codigo_alumno($al);

    if ($codigo === '') {
        return ['ok' => false, 'message' => 'No se pudo asignar un ID interno para la huella'];
    }

    $err = huella_validar_codigo_unico($pdo, $codigo, $idPlantel, $idAlumno, null);
    if ($err) {
        return ['ok' => false, 'message' => $err];
    }

    $templateJson = json_encode([
        'version' => 1,
        'formato' => HUELLA_FORMATO_INTERMEDIATE,
        'dispositivo' => $dispositivo,
        'muestras' => $samples,
        'capturado_en' => date('c'),
    ], JSON_UNESCAPED_UNICODE);

    if ($templateJson === false) {
        return ['ok' => false, 'message' => 'Error al procesar la huella'];
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0) ?: null;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE alumno_huellas SET activo = 0 WHERE id_alumno = ?')->execute([$idAlumno]);

        $pdo->prepare(
            'INSERT INTO alumno_huellas (
                id_alumno, id_plantel, codigo_huella, dedo, formato, template_data,
                dispositivo, id_usuario_registro
            ) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $idAlumno,
            $idPlantel,
            $codigo,
            $dedo,
            HUELLA_FORMATO_INTERMEDIATE,
            $templateJson,
            $dispositivo,
            $userId,
        ]);

        $pdo->prepare(
            'UPDATE alumnos SET codigo_huella = ?, huella_registrada = 1,
             huella_registrada_en = NOW(), huella_dispositivo = ?
             WHERE id_alumno = ? AND id_plantel = ?'
        )->execute([$codigo, $dispositivo, $idAlumno, $idPlantel]);

        asistencia_sync_codigo_huella($pdo, 'alumno', $idAlumno, $codigo, $idPlantel);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
    }

    return [
        'ok' => true,
        'message' => 'Huella registrada correctamente',
        'codigo_huella' => $codigo,
        'dispositivo' => $dispositivo,
    ];
}

/**
 * Guarda template de huella para personal.
 *
 * @param list<string> $samplesBase64
 * @return array<string, mixed>
 */
function huella_registrar_enrollment_usuario(
    PDO $pdo,
    int $idUsuario,
    int $idPlantel,
    array $samplesBase64,
    ?string $codigoHuella = null,
    string $dedo = 'indice_derecho',
    string $dispositivo = HUELLA_DISPOSITIVO_UAREU
): array {
    if (!huella_puede_enrolar_usuario()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }

    $est = huella_estado_usuario($pdo, $idUsuario, $idPlantel);
    if (!$est['ok']) {
        return $est;
    }

    $samples = array_values(array_filter(array_map('trim', $samplesBase64)));
    if ($samples === []) {
        return ['ok' => false, 'message' => 'No se recibieron muestras de huella'];
    }

    $codigo = $codigoHuella !== null && $codigoHuella !== ''
        ? huella_normalizar_codigo($codigoHuella)
        : (string) ($est['codigo_sugerido'] ?? '');

    if ($codigo === '') {
        return ['ok' => false, 'message' => 'No se pudo asignar un ID interno para la huella'];
    }

    $err = huella_validar_codigo_unico($pdo, $codigo, $idPlantel, null, $idUsuario);
    if ($err) {
        return ['ok' => false, 'message' => $err];
    }

    $templateJson = json_encode([
        'version' => 1,
        'formato' => HUELLA_FORMATO_INTERMEDIATE,
        'dispositivo' => $dispositivo,
        'muestras' => $samples,
        'capturado_en' => date('c'),
    ], JSON_UNESCAPED_UNICODE);

    if ($templateJson === false) {
        return ['ok' => false, 'message' => 'Error al procesar la huella'];
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0) ?: null;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE usuario_huellas SET activo = 0 WHERE id_usuario = ?')->execute([$idUsuario]);

        $pdo->prepare(
            'INSERT INTO usuario_huellas (
                id_usuario, id_plantel, codigo_huella, dedo, formato, template_data,
                dispositivo, id_usuario_registro
            ) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $idUsuario,
            $idPlantel,
            $codigo,
            $dedo,
            HUELLA_FORMATO_INTERMEDIATE,
            $templateJson,
            $dispositivo,
            $userId,
        ]);

        $pdo->prepare(
            'UPDATE usuarios SET codigo_huella = ?, huella_registrada = 1,
             huella_registrada_en = NOW(), huella_dispositivo = ?
             WHERE id_usuario = ?'
        )->execute([$codigo, $dispositivo, $idUsuario]);

        asistencia_sync_codigo_huella($pdo, 'usuario', $idUsuario, $codigo, $idPlantel);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
    }

    return [
        'ok' => true,
        'message' => 'Huella del personal registrada correctamente',
        'codigo_huella' => $codigo,
        'dispositivo' => $dispositivo,
    ];
}

/** Solo PIN (sin template) — cuando el lector no está disponible en el momento. */
function huella_registrar_pin_manual(
    PDO $pdo,
    int $idAlumno,
    string $codigo,
    int $idPlantel
): array {
    return huella_asignar_alumno($pdo, $idAlumno, $codigo, $idPlantel);
}

/** @return array<string, mixed> */
function huella_config_js(): array
{
    $websdkRel = defined('HAY_DP_WEBSDK_JS') ? HAY_DP_WEBSDK_JS : 'js/vendor/digitalpersona/websdk.client.ui.min.js';
    $fpSdkRel = defined('HAY_DP_FINGERPRINT_JS') ? HAY_DP_FINGERPRINT_JS : 'js/vendor/digitalpersona/fingerprint.sdk.min.js';
    $liteClientUrl = defined('HAY_DP_LITE_CLIENT_URL')
        ? HAY_DP_LITE_CLIENT_URL
        : 'https://digitalpersona.hidglobal.com/lite-client/';

    $projectRoot = dirname(__DIR__);
    $websdkDisk = $projectRoot . '/' . ltrim(str_replace('\\', '/', $websdkRel), '/');
    $fpSdkDisk = $projectRoot . '/' . ltrim(str_replace('\\', '/', $fpSdkRel), '/');

    return [
        'websdk_js' => hay_asset_url($websdkRel),
        'fingerprint_js' => hay_asset_url($fpSdkRel),
        'sdk_files_ok' => is_file($websdkDisk) && is_file($fpSdkDisk),
        'lite_client_url' => $liteClientUrl,
        'dispositivo' => HUELLA_DISPOSITIVO_UAREU,
        'dispositivo_nombre' => 'HID U.areU 5300',
        'api_enroll' => hay_asset_url('php/alumno_huella_enroll_api.php'),
        'api_enroll_usuario' => hay_asset_url('php/usuario_huella_enroll_api.php'),
        'required_scans' => 3,
        'modo_prueba' => huella_modo_prueba(),
        'rondin_seccion' => 'asistencia_faltantes',
    ];
}

/** URL oficial del instalador HID Lite Client. */
function hay_hid_lite_client_url(): string
{
    return defined('HAY_DP_LITE_CLIENT_URL')
        ? HAY_DP_LITE_CLIENT_URL
        : 'https://digitalpersona.hidglobal.com/lite-client/';
}

/**
 * Ruta relativa al instalador local (opcional), si el admin lo colocó en downloads/hid/.
 */
function hay_hid_lite_client_local_path(): ?string
{
    if (defined('HAY_DP_LITE_CLIENT_LOCAL') && HAY_DP_LITE_CLIENT_LOCAL !== '') {
        $custom = ltrim(str_replace('\\', '/', HAY_DP_LITE_CLIENT_LOCAL), '/');
        $abs = dirname(__DIR__) . '/' . $custom;
        if (is_file($abs)) {
            return $custom;
        }
    }

    $dir = dirname(__DIR__) . '/downloads/hid';
    if (!is_dir($dir)) {
        return null;
    }
    $candidates = [
        'HID Authentication Device Client.exe',
        'HID-Authentication-Device-Client.exe',
        'LiteClient.exe',
    ];
    foreach ($candidates as $name) {
        if (is_file($dir . '/' . $name)) {
            return 'downloads/hid/' . $name;
        }
    }
    foreach (glob($dir . '/*.exe') ?: [] as $exe) {
        return 'downloads/hid/' . basename($exe);
    }

    return null;
}

/** @return array{url:string, local_url:?string, local_label:string} */
function hay_hid_lite_client_links(): array
{
    $local = hay_hid_lite_client_local_path();

    return [
        'url' => hay_hid_lite_client_url(),
        'local_url' => $local ? hay_asset_url($local) : null,
        'local_label' => 'Copia local CNCM (sin internet)',
    ];
}

/** Normaliza una muestra intermediate (Base64Url) para comparación. */
function huella_normalizar_muestra(string $sample): string
{
    $s = trim($sample);
    if ($s === '') {
        return '';
    }
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad === 2) {
        $s .= '==';
    } elseif ($pad === 3) {
        $s .= '=';
    }

    return $s;
}

/** Similitud Jaccard sobre shingles de bytes. */
function huella_similitud_shingles(string $ba, string $bb, int $k = 4): float
{
    $lenA = strlen($ba);
    $lenB = strlen($bb);
    if ($lenA < $k || $lenB < $k) {
        return 0.0;
    }

    $setA = [];
    $setB = [];
    for ($i = 0; $i <= $lenA - $k; $i++) {
        $setA[substr($ba, $i, $k)] = true;
    }
    for ($i = 0; $i <= $lenB - $k; $i++) {
        $setB[substr($bb, $i, $k)] = true;
    }

    $inter = count(array_intersect_key($setA, $setB));
    $union = count($setA + $setB);

    return $union > 0 ? $inter / $union : 0.0;
}

/** Correlación simple de histogramas de bytes (0–1). */
function huella_similitud_histograma(string $ba, string $bb): float
{
    if ($ba === '' || $bb === '') {
        return 0.0;
    }

    $ha = array_fill(0, 256, 0);
    $hb = array_fill(0, 256, 0);
    $lenA = strlen($ba);
    $lenB = strlen($bb);
    for ($i = 0; $i < $lenA; $i++) {
        $ha[ord($ba[$i])]++;
    }
    for ($i = 0; $i < $lenB; $i++) {
        $hb[ord($bb[$i])]++;
    }

    $meanA = array_sum($ha) / 256;
    $meanB = array_sum($hb) / 256;
    $num = 0.0;
    $denA = 0.0;
    $denB = 0.0;
    for ($i = 0; $i < 256; $i++) {
        $da = $ha[$i] - $meanA;
        $db = $hb[$i] - $meanB;
        $num += $da * $db;
        $denA += $da * $da;
        $denB += $db * $db;
    }
    if ($denA <= 0 || $denB <= 0) {
        return 0.0;
    }

    $corr = $num / sqrt($denA * $denB);

    return max(0.0, min(1.0, ($corr + 1) / 2));
}

/** Compara dos muestras intermediate (coincidencia exacta o similitud alta). */
function huella_similitud_muestras(string $a, string $b): float
{
    $na = huella_normalizar_muestra($a);
    $nb = huella_normalizar_muestra($b);
    if ($na === '' || $nb === '') {
        return 0.0;
    }
    if ($na === $nb) {
        return 1.0;
    }

    $pct = 0.0;
    similar_text($na, $nb, $pct);
    $textScore = $pct / 100.0;

    $ba = base64_decode($na, true);
    $bb = base64_decode($nb, true);
    $binScore = 0.0;
    $shingleScore = 0.0;
    $histScore = 0.0;
    if ($ba !== false && $bb !== false && $ba !== '' && $bb !== '') {
        $len = min(strlen($ba), strlen($bb));
        $maxLen = max(strlen($ba), strlen($bb));
        if ($len > 0 && $maxLen > 0) {
            $matches = 0;
            for ($i = 0; $i < $len; $i++) {
                if ($ba[$i] === $bb[$i]) {
                    $matches++;
                }
            }
            $binScore = $matches / $maxLen;
        }
        $shingleScore = huella_similitud_shingles($ba, $bb);
        $histScore = huella_similitud_histograma($ba, $bb);
    }

    return max($textScore, $binScore, $shingleScore, $histScore);
}

function huella_muestras_equivalentes(string $a, string $b): bool
{
    $umbral = huella_modo_prueba() ? 0.35 : 0.72;

    return huella_similitud_muestras($a, $b) >= $umbral;
}

/**
 * @param list<array<string, mixed>> $filas
 * @return array{mejor: ?array, mejorScore: float, segundoScore: float, totalMuestras: int}
 */
function huella_mejor_coincidencia_galeria(string $sample, array $filas, string $tipoEntidad): array
{
    $mejor = null;
    $mejorScore = 0.0;
    $segundoScore = 0.0;
    $totalMuestras = 0;

    foreach ($filas as $row) {
        $tpl = json_decode((string) ($row['template_data'] ?? ''), true);
        if (!is_array($tpl)) {
            continue;
        }
        $muestras = $tpl['muestras'] ?? [];
        if (!is_array($muestras)) {
            continue;
        }
        foreach ($muestras as $m) {
            if (!is_string($m) || trim($m) === '') {
                continue;
            }
            $totalMuestras++;
            $score = huella_similitud_muestras($sample, $m);
            if ($score > $mejorScore) {
                $segundoScore = $mejorScore;
                $mejorScore = $score;
                $mejor = array_merge($row, ['tipo_entidad' => $tipoEntidad]);
            } elseif ($score > $segundoScore) {
                $segundoScore = $score;
            }
        }
    }

    return [
        'mejor' => $mejor,
        'mejorScore' => $mejorScore,
        'segundoScore' => $segundoScore,
        'totalMuestras' => $totalMuestras,
    ];
}

/**
 * Evalúa si una galería produce un candidato válido (umbral por tamaño de esa galería).
 *
 * @param list<array<string, mixed>> $filas
 * @return array{ok: bool, mejor: ?array, mejorScore: float, segundoScore: float, totalMuestras: int, tipo_entidad: string}
 */
function huella_evaluar_candidato_galeria(string $sample, array $filas, string $tipoEntidad): array
{
    $res = huella_mejor_coincidencia_galeria($sample, $filas, $tipoEntidad);
    $base = [
        'ok' => false,
        'mejor' => $res['mejor'],
        'mejorScore' => $res['mejorScore'],
        'segundoScore' => $res['segundoScore'],
        'totalMuestras' => $res['totalMuestras'],
        'tipo_entidad' => $tipoEntidad,
    ];
    if ($res['mejor'] === null || $res['totalMuestras'] === 0) {
        return $base;
    }

    $umbral = huella_umbral_identificacion($res['totalMuestras']);
    $gap = $res['mejorScore'] - $res['segundoScore'];
    if ($res['mejorScore'] >= $umbral['min_score'] && $gap >= $umbral['min_gap']) {
        $base['ok'] = true;
    }

    return $base;
}

/**
 * Identifica alumno o personal por muestra capturada en el navegador.
 *
 * @return array{ok: bool, message?: string, codigo_huella?: string, tipo?: string, id_referencia?: int, nombre?: string}
 */
function huella_identificar_por_muestra(PDO $pdo, string $sample, int $idPlantel): array
{
    $sample = trim($sample);
    if ($sample === '') {
        return ['ok' => false, 'message' => 'No se recibió la muestra de huella'];
    }

    $stAl = $pdo->prepare(
        'SELECT h.id_alumno, h.codigo_huella, h.template_data,
                CONCAT(COALESCE(a.apellido_paterno, a.apellido, \'\'), \' \', COALESCE(a.nombres, a.nombre, \'\')) AS nombre
         FROM alumno_huellas h
         INNER JOIN alumnos a ON a.id_alumno = h.id_alumno AND a.id_plantel = h.id_plantel
         WHERE h.id_plantel = ? AND h.activo = 1 AND a.estado = \'activo\''
    );
    $stAl->execute([$idPlantel]);
    $resAl = huella_evaluar_candidato_galeria($sample, $stAl->fetchAll(PDO::FETCH_ASSOC), 'alumno');

    $stUs = $pdo->prepare(
        'SELECT h.id_usuario, h.codigo_huella, h.template_data,
                CONCAT(COALESCE(u.nombre, \'\'), \' \', COALESCE(u.apellido, \'\')) AS nombre
         FROM usuario_huellas h
         INNER JOIN usuarios u ON u.id_usuario = h.id_usuario
         WHERE h.activo = 1
           AND u.rol NOT IN (\'alumno\')
           AND (
             h.id_plantel = ?
             OR u.id_plantel = ?
             OR (u.id_plantel IS NULL AND h.id_plantel = ?)
           )'
    );
    $stUs->execute([$idPlantel, $idPlantel, $idPlantel]);
    $resUs = huella_evaluar_candidato_galeria($sample, $stUs->fetchAll(PDO::FETCH_ASSOC), 'usuario');

    $candidatos = [];
    if ($resAl['ok']) {
        $candidatos[] = $resAl;
    }
    if ($resUs['ok']) {
        $candidatos[] = $resUs;
    }

    if ($candidatos === []) {
        $totalMuestras = $resAl['totalMuestras'] + $resUs['totalMuestras'];
        $mejorScore = max($resAl['mejorScore'], $resUs['mejorScore']);

        if ($totalMuestras === 0) {
            $cnt = $pdo->prepare(
                'SELECT COUNT(*) FROM alumnos WHERE id_plantel = ? AND huella_registrada = 1 AND estado = \'activo\''
            );
            $cnt->execute([$idPlantel]);
            $conPin = (int) $cnt->fetchColumn();
            if ($conPin > 0) {
                return [
                    'ok' => false,
                    'message' => 'Hay personas con ID en lector pero sin plantilla biométrica. '
                        . 'Vuelva a capturar la huella (3 lecturas) o use Rondín.',
                ];
            }

            return [
                'ok' => false,
                'message' => 'No hay huellas enroladas en este plantel. Registre huellas al inscribir o use Rondín.',
            ];
        }

        $umbralAl = huella_umbral_identificacion(max(1, $resAl['totalMuestras']));
        $msg = 'Huella no reconocida. Esta huella no está registrada en el sistema.';
        if (huella_modo_prueba() && $mejorScore > 0) {
            $msg .= ' (similitud ' . round($mejorScore * 100) . '%, umbral ~'
                . round($umbralAl['min_score'] * 100) . '% — modo prueba)';
        }
        $msg .= ' Si prefiere no usar huella, regístrelo en Rondín con su número de control.';

        return [
            'ok' => false,
            'message' => $msg,
            'score' => $mejorScore > 0 ? round($mejorScore, 3) : null,
        ];
    }

    usort($candidatos, static fn ($a, $b) => $b['mejorScore'] <=> $a['mejorScore']);
    $mejor = $candidatos[0];
    $tipo = ($mejor['tipo_entidad'] ?? '') === 'usuario' ? 'personal' : 'alumno';
    $idRef = $tipo === 'personal'
        ? (int) ($mejor['mejor']['id_usuario'] ?? 0)
        : (int) ($mejor['mejor']['id_alumno'] ?? 0);

    return [
        'ok' => true,
        'codigo_huella' => (string) ($mejor['mejor']['codigo_huella'] ?? ''),
        'tipo' => $tipo,
        'id_referencia' => $idRef,
        'nombre' => trim((string) ($mejor['mejor']['nombre'] ?? '')),
        'score' => round((float) $mejor['mejorScore'], 3),
        'modo' => huella_modo_prueba() ? 'prueba' : 'fingerjet',
    ];
}
