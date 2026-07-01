<?php

/**
 * Verificación de actividades Moodle para asesorías por falta.
 */

function asesoria_moodle_actividades_completadas(PDO $pdo, int $idAlumno, int $idGrupo, ?string $semanaFalta): bool
{
    if (!function_exists('moodle_configurado') || !moodle_configurado()) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT a.email, g.id_especialidad, g.moodle_course_id FROM alumnos a
         INNER JOIN grupos g ON g.id_grupo = ?
         WHERE a.id_alumno = ? LIMIT 1'
    );
    $st->execute([$idGrupo, $idAlumno]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['email'])) {
        return false;
    }
    $courseId = (int) ($row['moodle_course_id'] ?? 0);
    if ($courseId <= 0 && function_exists('moodle_curso_grupo')) {
        $courseId = (int) moodle_curso_grupo($pdo, $idGrupo);
    }
    if ($courseId <= 0) {
        return false;
    }
    if (!function_exists('moodle_usuario_por_email') || !function_exists('moodle_api_call')) {
        return false;
    }
    $mUser = moodle_usuario_por_email((string) $row['email']);
    if (!$mUser || empty($mUser['id'])) {
        return false;
    }
    try {
        $completion = moodle_api_call('core_completion_get_activities_completion_status', [
            'courseid' => $courseId,
            'userid' => (int) $mUser['id'],
        ]);
        if (!is_array($completion) || empty($completion['statuses'])) {
            return false;
        }
        $completadas = 0;
        foreach ($completion['statuses'] as $st) {
            if (!empty($st['state']) && (int) $st['state'] === 1) {
                $completadas++;
            }
        }

        return $completadas >= 1;
    } catch (Throwable $e) {
        error_log('asesoria_moodle_actividades: ' . $e->getMessage());

        return false;
    }
}
