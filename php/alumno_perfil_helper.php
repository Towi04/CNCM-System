<?php
declare(strict_types=1);

/**
 * Perfil personal del alumno (gustos / afinidades) para tutor y planeación.
 */

function alumno_perfil_ensure_schema(PDO $pdo): void
{
    alumno_ensure_schema($pdo);
    plantel_ensure_column($pdo, 'alumnos', 'perfil_gustos', 'TEXT NULL', 'moodle_user_id');
    plantel_ensure_column($pdo, 'alumnos', 'perfil_intereses_json', 'JSON NULL', 'perfil_gustos');
    plantel_ensure_column($pdo, 'alumnos', 'perfil_completado', 'TINYINT(1) NOT NULL DEFAULT 0', 'perfil_intereses_json');
    plantel_ensure_column($pdo, 'alumnos', 'perfil_completado_en', 'DATETIME NULL', 'perfil_completado');
}

function academico_material_ensure_schema(PDO $pdo): void
{
    fase_ensure_schema($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS academico_material (
            id_material INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo ENUM(\'libro_alumno\',\'libro_profesor\',\'workbook\',\'studentbook\',\'guia_profesor\',\'moodle_actividad\',\'pdf_fragmento\',\'otro\') NOT NULL DEFAULT \'otro\',
            id_especialidad INT UNSIGNED NULL,
            id_fase INT UNSIGNED NULL,
            semana TINYINT UNSIGNED NULL,
            pagina_inicio SMALLINT UNSIGNED NULL,
            pagina_fin SMALLINT UNSIGNED NULL,
            titulo VARCHAR(220) NOT NULL,
            descripcion TEXT NULL,
            contenido_texto MEDIUMTEXT NULL,
            ruta_archivo VARCHAR(500) NULL,
            moodle_course_id INT UNSIGNED NULL,
            moodle_cm_id INT UNSIGNED NULL,
            moodle_url VARCHAR(500) NULL,
            etiquetas VARCHAR(500) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_material),
            KEY idx_mat_esp (id_especialidad, activo),
            KEY idx_mat_fase_sem (id_fase, semana),
            KEY idx_mat_tipo (tipo, activo),
            KEY idx_mat_pagina (pagina_inicio, pagina_fin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'academico_material', 'id_libro', 'INT UNSIGNED NULL', 'activo');
        plantel_ensure_column($pdo, 'academico_material', 'id_version', 'INT UNSIGNED NULL', 'id_libro');
    }
}

function alumno_perfil_id_desde_usuario(PDO $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    if (function_exists('alumno_portal_id_sesion')) {
        $id = alumno_portal_id_sesion();
        if ($id > 0) {
            return $id;
        }
    }
    $st = $pdo->prepare('SELECT id_alumno FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$userId]);
    return (int) ($st->fetchColumn() ?: 0);
}

/** @return array<string, mixed>|null */
function alumno_perfil_obtener(PDO $pdo, int $idAlumno): ?array
{
    if ($idAlumno <= 0) {
        return null;
    }
    alumno_perfil_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_alumno, perfil_gustos, perfil_intereses_json, perfil_completado, perfil_completado_en
         FROM alumnos WHERE id_alumno = ? LIMIT 1'
    );
    $st->execute([$idAlumno]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function alumno_perfil_completado(PDO $pdo, int $idAlumno): bool
{
    $p = alumno_perfil_obtener($pdo, $idAlumno);

    return $p && (int) ($p['perfil_completado'] ?? 0) === 1;
}

function alumno_debe_completar_perfil(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() !== 'alumno') {
        return false;
    }
    $idAlumno = alumno_perfil_id_desde_usuario($pdo, $userId);
    if ($idAlumno <= 0) {
        return false;
    }

    return !alumno_perfil_completado($pdo, $idAlumno);
}

/** Texto listo para inyectar en prompt del tutor. */
function alumno_perfil_texto_para_ia(PDO $pdo, int $userId): string
{
    $idAlumno = alumno_perfil_id_desde_usuario($pdo, $userId);
    if ($idAlumno <= 0) {
        return '';
    }
    $p = alumno_perfil_obtener($pdo, $idAlumno);
    if (!$p || (int) ($p['perfil_completado'] ?? 0) !== 1) {
        return '';
    }

    $lineas = ['[PERFIL PERSONAL DEL ALUMNO]'];
    $gustos = trim((string) ($p['perfil_gustos'] ?? ''));
    if ($gustos !== '') {
        $lineas[] = 'Gustos y afinidades: ' . $gustos;
    }

    $json = $p['perfil_intereses_json'] ?? null;
    if (is_string($json)) {
        $json = json_decode($json, true);
    }
    if (is_array($json)) {
        $map = [
            'hobbies' => 'Pasatiempos',
            'materias_favoritas' => 'Materias o temas favoritos',
            'como_aprende' => 'Aprende mejor con',
            'meta' => 'Meta personal',
            'otro' => 'Otro',
        ];
        foreach ($map as $k => $label) {
            $v = trim((string) ($json[$k] ?? ''));
            if ($v !== '') {
                $lineas[] = $label . ': ' . $v;
            }
        }
    }

    if (count($lineas) <= 1) {
        return '';
    }

    $lineas[] = 'Usa ejemplos y analogías relacionados con estos gustos cuando ayude a explicar el temario.';

    return implode("\n", $lineas);
}

/**
 * @param array{hobbies?:string,materias_favoritas?:string,como_aprende?:string,meta?:string,otro?:string,gustos_libre?:string} $data
 */
function alumno_perfil_guardar(PDO $pdo, int $idAlumno, array $data): array
{
    if ($idAlumno <= 0) {
        return ['ok' => false, 'message' => 'Alumno no válido'];
    }
    alumno_perfil_ensure_schema($pdo);

    $hobbies = trim((string) ($data['hobbies'] ?? ''));
    $materias = trim((string) ($data['materias_favoritas'] ?? ''));
    $como = trim((string) ($data['como_aprende'] ?? ''));
    $meta = trim((string) ($data['meta'] ?? ''));
    $otro = trim((string) ($data['otro'] ?? ''));
    $libre = trim((string) ($data['gustos_libre'] ?? ''));

    if ($hobbies === '' && $materias === '' && $libre === '') {
        return ['ok' => false, 'message' => 'Cuéntanos al menos tus pasatiempos o temas que te gustan.'];
    }

    $json = array_filter([
        'hobbies' => $hobbies,
        'materias_favoritas' => $materias,
        'como_aprende' => $como,
        'meta' => $meta,
        'otro' => $otro,
    ], static fn ($v) => $v !== '');

    $resumen = $libre !== '' ? $libre : implode('. ', array_filter([
        $hobbies !== '' ? 'Me gusta: ' . $hobbies : '',
        $materias !== '' ? 'Me interesan: ' . $materias : '',
        $como !== '' ? 'Aprendo mejor con: ' . $como : '',
        $meta !== '' ? 'Meta: ' . $meta : '',
    ]));

    $pdo->prepare(
        'UPDATE alumnos SET perfil_gustos = ?, perfil_intereses_json = ?, perfil_completado = 1, perfil_completado_en = NOW()
         WHERE id_alumno = ?'
    )->execute([
        mb_substr($resumen, 0, 4000),
        json_encode($json, JSON_UNESCAPED_UNICODE),
        $idAlumno,
    ]);

    return ['ok' => true, 'message' => 'Perfil guardado'];
}
