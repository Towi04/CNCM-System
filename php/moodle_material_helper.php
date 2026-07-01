<?php
declare(strict_types=1);

/**
 * Sincroniza actividades Moodle → academico_material.
 */

function moodle_material_ensure_schema(PDO $pdo): void
{
    if (function_exists('academico_material_ensure_schema')) {
        academico_material_ensure_schema($pdo);
    }
    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
    }
}

function moodle_material_base_url(): string
{
    if (defined('MOODLE_URL') && MOODLE_URL !== '') {
        return rtrim((string) MOODLE_URL, '/');
    }

    return '';
}

/** Tipos Moodle que indexamos como actividad. */
function moodle_material_modnames_utiles(): array
{
    return [
        'page', 'book', 'quiz', 'assign', 'lesson', 'url', 'resource',
        'forum', 'h5pactivity', 'scorm', 'workshop', 'feedback',
    ];
}

/**
 * @return array{ok:bool,message:string,cursos?:int,insertados?:int,actualizados?:int,omitidos?:int,errores?:list<string>}
 */
function moodle_sync_academico_material(PDO $pdo, ?int $idEspecialidad = null, bool $soloVisibles = true): array
{
    moodle_material_ensure_schema($pdo);

    if (!function_exists('moodle_enabled') || !moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no está configurado'];
    }
    if (!function_exists('moodle_api_call')) {
        return ['ok' => false, 'message' => 'API Moodle no disponible'];
    }

    $sql = 'SELECT f.id_fase, f.id_especialidad, f.clave_fase, f.nombre_fase, f.orden,
                   f.moodle_course_id, f.moodle_shortname, e.clave AS esp_clave, e.nombre AS esp_nombre
            FROM especialidad_fases f
            INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad
            WHERE f.activo = 1 AND e.activo = 1
              AND f.moodle_course_id IS NOT NULL AND f.moodle_course_id > 0';
    $params = [];
    if ($idEspecialidad !== null && $idEspecialidad > 0) {
        $sql .= ' AND f.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $sql .= ' ORDER BY f.id_especialidad, f.orden ASC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $fases = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($fases === []) {
        return ['ok' => false, 'message' => 'No hay fases con moodle_course_id configurado en especialidad_fases'];
    }

    $base = moodle_material_base_url();
    $insertados = 0;
    $actualizados = 0;
    $omitidos = 0;
    $errores = [];
    $cursosProc = 0;
    $modUtiles = moodle_material_modnames_utiles();

    $find = $pdo->prepare(
        'SELECT id_material FROM academico_material
         WHERE tipo = \'moodle_actividad\' AND moodle_course_id = ? AND moodle_cm_id = ? LIMIT 1'
    );
    $ins = $pdo->prepare(
        'INSERT INTO academico_material
         (tipo, id_especialidad, id_fase, semana, titulo, descripcion, moodle_course_id, moodle_cm_id, moodle_url, contenido_texto, activo)
         VALUES (\'moodle_actividad\',?,?,?,?,?,?,?,?,?,1)'
    );
    $upd = $pdo->prepare(
        'UPDATE academico_material SET titulo = ?, descripcion = ?, moodle_url = ?, contenido_texto = ?, activo = 1
         WHERE id_material = ?'
    );

    $cursosVistos = [];

    foreach ($fases as $fase) {
        $courseId = (int) ($fase['moodle_course_id'] ?? 0);
        if ($courseId <= 0) {
            continue;
        }
        $key = $courseId . ':' . (int) $fase['id_especialidad'];
        if (isset($cursosVistos[$key])) {
            continue;
        }
        $cursosVistos[$key] = true;
        $cursosProc++;

        $res = moodle_api_call('core_course_get_contents', ['courseid' => $courseId]);
        if (empty($res['ok'])) {
            $errores[] = 'Curso ' . $courseId . ': ' . ($res['message'] ?? 'error API');
            continue;
        }

        $sections = (array) ($res['data'] ?? []);
        foreach ($sections as $sec) {
            if (!is_array($sec)) {
                continue;
            }
            if ($soloVisibles && isset($sec['visible']) && (int) $sec['visible'] === 0) {
                continue;
            }
            $secName = trim(strip_tags((string) ($sec['name'] ?? '')));
            $mods = (array) ($sec['modules'] ?? []);
            foreach ($mods as $mod) {
                if (!is_array($mod)) {
                    continue;
                }
                if ($soloVisibles && isset($mod['visible']) && (int) $mod['visible'] === 0) {
                    $omitidos++;
                    continue;
                }
                $modName = (string) ($mod['modname'] ?? '');
                if (!in_array($modName, $modUtiles, true)) {
                    $omitidos++;
                    continue;
                }
                $cmId = (int) ($mod['id'] ?? 0);
                if ($cmId <= 0) {
                    continue;
                }
                $titulo = trim(strip_tags((string) ($mod['name'] ?? 'Actividad Moodle')));
                $desc = trim(strip_tags((string) ($mod['description'] ?? '')));
                if ($desc === '' && $secName !== '') {
                    $desc = 'Sección: ' . $secName;
                }
                $url = (string) ($mod['url'] ?? '');
                if ($url === '' && $base !== '') {
                    $url = $base . '/mod/' . $modName . '/view.php?id=' . $cmId;
                }
                $semana = moodle_material_inferir_semana($titulo . ' ' . $secName . ' ' . $desc);
                $contenido = "Actividad Moodle ({$modName}): {$titulo}\n{$desc}\nURL: {$url}";

                $find->execute([$courseId, $cmId]);
                $idMat = (int) $find->fetchColumn();
                if ($idMat > 0) {
                    $upd->execute([mb_substr($titulo, 0, 220), $desc ?: null, $url ?: null, $contenido, $idMat]);
                    $actualizados++;
                } else {
                    $ins->execute([
                        (int) $fase['id_especialidad'],
                        (int) $fase['id_fase'],
                        $semana,
                        mb_substr($titulo, 0, 220),
                        $desc ?: null,
                        $courseId,
                        $cmId,
                        $url ?: null,
                        $contenido,
                    ]);
                    $insertados++;
                }
            }
        }
    }

    return [
        'ok' => true,
        'message' => "Sync Moodle: {$insertados} nuevos, {$actualizados} actualizados",
        'cursos' => $cursosProc,
        'insertados' => $insertados,
        'actualizados' => $actualizados,
        'omitidos' => $omitidos,
        'errores' => $errores,
    ];
}

function moodle_material_inferir_semana(string $texto): ?int
{
    if (preg_match('/\bsemana\s*(\d{1,2})\b/ui', $texto, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/\bweek\s*(\d{1,2})\b/i', $texto, $m)) {
        return (int) $m[1];
    }

    return null;
}
