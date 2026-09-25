<?php

/**
 * Exportación / importación masiva de fases por especialidad (CSV).
 *
 * Una fila = una semana del temario (pestaña Semanas del formulario).
 * Si la fase no tiene semanas cargadas, se exporta una fila con semana vacía.
 */

/** @return list<string> */
function fase_csv_columnas(): array
{
    return [
        // Identificación / datos de la fase (se repiten en cada semana)
        'id_fase',
        'clave_especialidad',
        'clave_fase',
        'nombre_fase',
        'orden',
        'duracion_semanas',
        'objetivo_parcial',
        'descripcion',
        'temas',
        'practicas_sugeridas',
        'asesoria',
        'nivel_cefr',
        'num_parcial',
        'tipo_contenido',
        'eval_listening',
        'eval_reading',
        'eval_writing',
        'eval_speaking',
        'eval_grammar',
        'eval_vocabulary',
        'vocabulario_resumen',
        'gramatica_resumen',
        'moodle_course_id',
        'moodle_shortname',
        'activo',
        // Semana (pestaña Semanas del modal)
        'semana',
        'titulo_leccion',
        'objetivo_semana',
        'contenido_clase',
        'gramatica',
        'proyecto_tipo',
        'listening',
        'reading',
        'writing',
        'speaking',
        'notas',
        'es_examen',
    ];
}

/**
 * Descripción corta del tipo de dato esperado por columna (para plantilla / ayuda).
 *
 * @return array<string, string>
 */
function fase_csv_tipos_columnas(): array
{
    return [
        'id_fase' => 'número (vacío al crear; id al actualizar)',
        'clave_especialidad' => 'texto (ej. ING, CK)',
        'clave_fase' => 'texto (ej. A1-1)',
        'nombre_fase' => 'texto (ej. A1 - Parcial 1)',
        'orden' => 'número entero (1, 2, 3…)',
        'duracion_semanas' => 'número entero (normalmente 4)',
        'objetivo_parcial' => 'texto libre (objetivo general del parcial)',
        'descripcion' => 'texto libre (opcional)',
        'temas' => 'texto libre (resumen de temas del parcial)',
        'practicas_sugeridas' => 'texto libre',
        'asesoria' => 'texto libre',
        'nivel_cefr' => 'texto (A1, A2, B1, B2…)',
        'num_parcial' => 'número 1 a 4',
        'tipo_contenido' => 'texto: regular | proyecto_nivel | proyecto_final',
        'eval_listening' => 'texto (criterio de evaluación; NO es porcentaje)',
        'eval_reading' => 'texto (criterio de evaluación; NO es porcentaje)',
        'eval_writing' => 'texto (criterio de evaluación; NO es porcentaje)',
        'eval_speaking' => 'texto (criterio de evaluación; NO es porcentaje)',
        'eval_grammar' => 'texto (criterio de evaluación; NO es porcentaje)',
        'eval_vocabulary' => 'texto (criterio de evaluación; NO es porcentaje)',
        'vocabulario_resumen' => 'texto (resumen del parcial)',
        'gramatica_resumen' => 'texto (resumen del parcial)',
        'moodle_course_id' => 'número o vacío',
        'moodle_shortname' => 'texto o vacío (shortname Moodle)',
        'activo' => '0 o 1 (1=activa)',
        'semana' => 'número 1 a 4 (una fila por semana)',
        'titulo_leccion' => 'texto = Tema / lección del formulario',
        'objetivo_semana' => 'texto = Objetivo de la semana',
        'contenido_clase' => 'texto = Contenido de la clase (o Vocabulario en inglés)',
        'gramatica' => 'texto (estructuras; típico en inglés)',
        'proyecto_tipo' => 'texto (practice, examen, proyecto, etc.)',
        'listening' => 'texto opcional',
        'reading' => 'texto opcional',
        'writing' => 'texto opcional',
        'speaking' => 'texto opcional',
        'notas' => 'texto opcional',
        'es_examen' => '0 o 1 (1=semana de examen)',
    ];
}

/**
 * Filas de ejemplo completas (4 semanas) para la plantilla descargable.
 *
 * @return list<array<string, string>>
 */
function fase_csv_filas_ejemplo(): array
{
    $base = [
        'id_fase' => '',
        'clave_especialidad' => 'ING',
        'clave_fase' => 'A1-1',
        'nombre_fase' => 'A1 - Parcial 1',
        'orden' => '1',
        'duracion_semanas' => '4',
        'objetivo_parcial' => 'Al finalizar el parcial el alumno podrá presentarse, hablar de gustos y decir de dónde es.',
        'descripcion' => 'Primer parcial del nivel A1.',
        'temas' => 'Presentaciones, gustos, países y nacionalidades, descripción básica.',
        'practicas_sugeridas' => 'Diálogos en pareja, role-play de presentación.',
        'asesoria' => 'Repaso de pronombres y verbo to be.',
        'nivel_cefr' => 'A1',
        'num_parcial' => '1',
        'tipo_contenido' => 'regular',
        'eval_listening' => 'Identificar información personal en un diálogo corto.',
        'eval_reading' => 'Comprender un texto breve de presentación.',
        'eval_writing' => 'Escribir 5 oraciones sobre sí mismo.',
        'eval_speaking' => 'Presentarse y preguntar de dónde es alguien.',
        'eval_grammar' => 'To be, wh- questions (who/where).',
        'eval_vocabulary' => 'Countries, nationalities, likes.',
        'vocabulario_resumen' => 'Countries and nationalities; likes and dislikes.',
        'gramatica_resumen' => 'Questions and answers with WHO and WHERE; to be.',
        'moodle_course_id' => '',
        'moodle_shortname' => 'ING-A1-1',
        'activo' => '1',
        'listening' => '',
        'reading' => '',
        'writing' => '',
        'speaking' => '',
        'notas' => '',
        'es_examen' => '0',
    ];

    $semanas = [
        [
            'semana' => '1',
            'titulo_leccion' => 'Introducing',
            'objetivo_semana' => 'At the end of this lesson students will be able to introduce themselves and greet others.',
            'contenido_clase' => "Vocabulary: Greetings and introductions\nSpeaking: Hello, my name is...",
            'gramatica' => 'Subject pronouns; verb to be (am/is/are).',
            'proyecto_tipo' => 'practice',
            'es_examen' => '0',
        ],
        [
            'semana' => '2',
            'titulo_leccion' => 'Likes and dislikes',
            'objetivo_semana' => 'Students will be able to talk about likes and dislikes using simple sentences.',
            'contenido_clase' => "Vocabulary: Food, hobbies, sports\nSpeaking: I like / I don't like",
            'gramatica' => 'Like + noun / like + -ing.',
            'proyecto_tipo' => 'practice',
            'es_examen' => '0',
        ],
        [
            'semana' => '3',
            'titulo_leccion' => 'Countries',
            'objetivo_semana' => 'At the end of this lesson students will be able to say country names and nationality words. They will learn how to ask where someone is from and how to respond to such a question.',
            'contenido_clase' => "Vocabulary: Countries and nationalities (China, Chinese, Mexico, Mexican)\nGrammar: Questions and answers with WHO and WHERE\nListening: Listening for confirmation\nSpeaking: Asking where someone is from",
            'gramatica' => 'Where are you from? I am from...',
            'proyecto_tipo' => 'practice',
            'listening' => 'Listening for confirmation of country names.',
            'speaking' => 'Asking where someone is from.',
            'es_examen' => '0',
        ],
        [
            'semana' => '4',
            'titulo_leccion' => 'Describing people',
            'objetivo_semana' => 'Students will describe basic physical appearance and review the partial.',
            'contenido_clase' => "Vocabulary: Appearance adjectives\nReview of weeks 1-3\nSpeaking: Describe a classmate",
            'gramatica' => 'Have / has; adjective order (basic).',
            'proyecto_tipo' => 'examen',
            'notas' => 'Semana de repaso y evaluación oral.',
            'es_examen' => '1',
        ],
    ];

    $out = [];
    foreach ($semanas as $s) {
        $out[] = array_merge($base, $s);
    }

    return $out;
}

/**
 * @param array<string, mixed> $fase
 * @param array<string, mixed>|null $semana
 * @return array<string, mixed>
 */
function fase_csv_fila_desde(array $fase, ?array $semana = null): array
{
    $cols = fase_csv_columnas();
    $line = [];
    foreach ($cols as $col) {
        $line[$col] = '';
    }

    $line['id_fase'] = $fase['id_fase'] ?? '';
    $line['clave_especialidad'] = $fase['clave_especialidad'] ?? '';
    $line['clave_fase'] = $fase['clave_fase'] ?? '';
    $line['nombre_fase'] = $fase['nombre_fase'] ?? '';
    $line['orden'] = $fase['orden'] ?? '';
    $line['duracion_semanas'] = $fase['duracion_semanas'] ?? '';
    $line['objetivo_parcial'] = $fase['objetivo_parcial'] ?? $fase['descripcion'] ?? '';
    $line['descripcion'] = $fase['descripcion'] ?? '';
    $line['temas'] = $fase['temas'] ?? '';
    $line['practicas_sugeridas'] = $fase['practicas_sugeridas'] ?? '';
    $line['asesoria'] = $fase['asesoria'] ?? '';
    $line['nivel_cefr'] = $fase['nivel_cefr'] ?? '';
    $line['num_parcial'] = $fase['num_parcial'] ?? '';
    $line['tipo_contenido'] = $fase['tipo_contenido'] ?? '';
    $line['eval_listening'] = $fase['eval_listening'] ?? '';
    $line['eval_reading'] = $fase['eval_reading'] ?? '';
    $line['eval_writing'] = $fase['eval_writing'] ?? '';
    $line['eval_speaking'] = $fase['eval_speaking'] ?? '';
    $line['eval_grammar'] = $fase['eval_grammar'] ?? '';
    $line['eval_vocabulary'] = $fase['eval_vocabulary'] ?? '';
    $line['vocabulario_resumen'] = $fase['vocabulario_resumen'] ?? '';
    $line['gramatica_resumen'] = $fase['gramatica_resumen'] ?? '';
    $line['moodle_course_id'] = $fase['moodle_course_id'] ?? '';
    $line['moodle_shortname'] = $fase['moodle_shortname'] ?? '';
    $line['activo'] = $fase['activo'] ?? '';

    if ($semana !== null) {
        $line['semana'] = $semana['semana'] ?? '';
        $line['titulo_leccion'] = $semana['titulo_leccion'] ?? '';
        $line['objetivo_semana'] = $semana['objetivo'] ?? '';
        // En el formulario: "Contenido de la clase" (no inglés) o "Vocabulario" (inglés) → columna vocabulario
        $line['contenido_clase'] = $semana['vocabulario'] ?? '';
        $line['gramatica'] = $semana['gramatica'] ?? '';
        $line['proyecto_tipo'] = $semana['proyecto_tipo'] ?? '';
        $line['listening'] = $semana['listening'] ?? '';
        $line['reading'] = $semana['reading'] ?? '';
        $line['writing'] = $semana['writing'] ?? '';
        $line['speaking'] = $semana['speaking'] ?? '';
        $line['notas'] = $semana['notas'] ?? '';
        $line['es_examen'] = isset($semana['es_examen']) ? (string) (int) $semana['es_examen'] : '';
    }

    return $line;
}

/** @return list<array<string, mixed>> */
function fase_csv_filas(PDO $pdo, ?int $idEspecialidad = null): array
{
    fase_ensure_schema($pdo);
    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
    }
    $sql = 'SELECT f.*, e.clave AS clave_especialidad
            FROM especialidad_fases f
            INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad
            WHERE 1=1';
    $params = [];
    if ($idEspecialidad !== null && $idEspecialidad > 0) {
        $sql .= ' AND f.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $sql .= ' ORDER BY e.orden ASC, e.clave ASC, f.orden ASC, f.id_fase ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $idFase = (int) ($r['id_fase'] ?? 0);
        $semanas = $idFase > 0 ? fase_temario_semanas($pdo, $idFase) : [];
        if ($semanas === []) {
            $out[] = fase_csv_fila_desde($r, null);
            continue;
        }
        foreach ($semanas as $s) {
            $out[] = fase_csv_fila_desde($r, $s);
        }
    }

    return $out;
}

function fase_csv_enviar_descarga(PDO $pdo, ?int $idEspecialidad = null, bool $soloPlantilla = false): void
{
    $cols = fase_csv_columnas();
    $tag = $idEspecialidad ? ('esp' . $idEspecialidad . '_') : '';
    $filename = $soloPlantilla
        ? 'plantilla_fases_especialidad.csv'
        : 'fases_' . $tag . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $cols);

    if ($soloPlantilla) {
        // Fila de tipos (se ignora al importar si clave_fase = __TIPO__)
        $tipos = fase_csv_tipos_columnas();
        $tipoRow = [];
        foreach ($cols as $c) {
            if ($c === 'clave_fase') {
                $tipoRow[] = '__TIPO__';
            } elseif ($c === 'nombre_fase') {
                $tipoRow[] = 'FILA DE AYUDA — borrar antes de subir (indica si cada columna es texto o número)';
            } else {
                $tipoRow[] = $tipos[$c] ?? '';
            }
        }
        fputcsv($out, $tipoRow);

        foreach (fase_csv_filas_ejemplo() as $row) {
            $line = [];
            foreach ($cols as $c) {
                $line[] = $row[$c] ?? '';
            }
            fputcsv($out, $line);
        }
    } else {
        foreach (fase_csv_filas($pdo, $idEspecialidad) as $row) {
            $line = [];
            foreach ($cols as $c) {
                $line[] = $row[$c] ?? '';
            }
            fputcsv($out, $line);
        }
    }
    fclose($out);
    exit;
}

/**
 * Upsert de una semana del temario.
 *
 * @param array<string, mixed> $s
 */
function fase_csv_upsert_semana(PDO $pdo, int $idFase, array $s): void
{
    $sem = (int) ($s['semana'] ?? 0);
    if ($idFase <= 0 || $sem < 1 || $sem > 4) {
        return;
    }

    $upsert = $pdo->prepare(
        'INSERT INTO fase_temario_semana (
            id_fase, semana, titulo_leccion, objetivo, vocabulario, gramatica,
            listening, reading, writing, speaking, notas, es_examen, proyecto_tipo
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            titulo_leccion=VALUES(titulo_leccion), objetivo=VALUES(objetivo),
            vocabulario=VALUES(vocabulario), gramatica=VALUES(gramatica),
            listening=VALUES(listening), reading=VALUES(reading),
            writing=VALUES(writing), speaking=VALUES(speaking),
            notas=VALUES(notas), es_examen=VALUES(es_examen),
            proyecto_tipo=VALUES(proyecto_tipo)'
    );
    $upsert->execute([
        $idFase,
        $sem,
        trim((string) ($s['titulo_leccion'] ?? '')) ?: null,
        trim((string) ($s['objetivo'] ?? '')) ?: null,
        trim((string) ($s['vocabulario'] ?? '')) ?: null,
        trim((string) ($s['gramatica'] ?? '')) ?: null,
        trim((string) ($s['listening'] ?? '')) ?: null,
        trim((string) ($s['reading'] ?? '')) ?: null,
        trim((string) ($s['writing'] ?? '')) ?: null,
        trim((string) ($s['speaking'] ?? '')) ?: null,
        trim((string) ($s['notas'] ?? '')) ?: null,
        !empty($s['es_examen']) ? 1 : 0,
        trim((string) ($s['proyecto_tipo'] ?? '')) ?: null,
    ]);
}

/**
 * @return array{ok:bool, message:string, actualizadas?:int, creadas?:int, semanas?:int, errores?:list<string>}
 */
function fase_csv_importar(PDO $pdo, string $tmpPath, ?int $forzarIdEsp = null): array
{
    if (!fase_puede_editar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    if (!is_readable($tmpPath)) {
        return ['ok' => false, 'message' => 'No se pudo leer el CSV'];
    }
    fase_ensure_schema($pdo);
    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
    }

    $fh = fopen($tmpPath, 'r');
    if ($fh === false) {
        return ['ok' => false, 'message' => 'No se pudo abrir el CSV'];
    }
    $header = fgetcsv($fh);
    if ($header === false || $header === []) {
        fclose($fh);
        return ['ok' => false, 'message' => 'CSV vacío'];
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
    $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
    $map = array_flip($header);
    if (!isset($map['nombre_fase']) && !isset($map['clave_fase'])) {
        fclose($fh);
        return ['ok' => false, 'message' => 'El CSV debe incluir nombre_fase o clave_fase'];
    }

    $actualizadas = 0;
    $creadas = 0;
    $semanasOk = 0;
    $errores = [];
    $lineNum = 1;
    /** @var array<int, true> fases ya contadas como actualizadas/creadas en esta corrida */
    $fasesVistas = [];

    while (($data = fgetcsv($fh)) !== false) {
        $lineNum++;
        if ($data === [null] || $data === false) {
            continue;
        }
        $get = static function (string $col) use ($map, $data): string {
            if (!isset($map[$col])) {
                return '';
            }
            $i = $map[$col];

            return isset($data[$i]) ? trim((string) $data[$i]) : '';
        };

        $claveFase = $get('clave_fase');
        if ($claveFase === '__TIPO__' || strtoupper($claveFase) === '__TIPO__') {
            continue; // fila de ayuda de la plantilla
        }
        if ($get('id_fase') === '' && $claveFase === '' && $get('nombre_fase') === '') {
            continue;
        }

        $idFase = (int) $get('id_fase');
        $claveEsp = strtoupper(trim($get('clave_especialidad')));
        $idEsp = $forzarIdEsp;
        if (($idEsp === null || $idEsp <= 0) && $claveEsp !== '') {
            $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE UPPER(clave) = ? LIMIT 1');
            $st->execute([$claveEsp]);
            $idEsp = (int) $st->fetchColumn();
        }
        if ($idFase > 0) {
            $exist = fase_obtener($pdo, $idFase);
            if ($exist) {
                $idEsp = (int) $exist['id_especialidad'];
            }
        }
        if ($idEsp === null || $idEsp <= 0) {
            $errores[] = "Línea {$lineNum}: no se resolvió especialidad (clave_especialidad / id_fase)";
            continue;
        }

        $nombre = $get('nombre_fase');
        if ($nombre === '' && $claveFase === '' && $idFase <= 0) {
            continue;
        }

        if ($idFase <= 0 && $claveFase !== '') {
            $stF = $pdo->prepare(
                'SELECT id_fase FROM especialidad_fases
                 WHERE id_especialidad = ? AND UPPER(clave_fase) = ? AND activo = 1
                 ORDER BY id_fase ASC LIMIT 1'
            );
            $stF->execute([$idEsp, strtoupper($claveFase)]);
            $idFase = (int) $stF->fetchColumn();
        }

        $esNueva = $idFase <= 0;
        $payload = [
            'id_fase' => $idFase,
            'id_especialidad' => $idEsp,
            'nombre_fase' => $nombre !== '' ? $nombre : ($claveFase !== '' ? $claveFase : 'Fase'),
            'clave_fase' => $claveFase,
            'orden' => $get('orden') !== '' ? (int) $get('orden') : 0,
            'duracion_semanas' => $get('duracion_semanas') !== '' ? (int) $get('duracion_semanas') : null,
            'descripcion' => $get('descripcion') !== '' ? $get('descripcion') : $get('objetivo_parcial'),
            'temas' => $get('temas'),
            'practicas_sugeridas' => $get('practicas_sugeridas'),
            'asesoria' => $get('asesoria'),
            'activo' => $get('activo') === '' ? 1 : ((int) $get('activo') ? 1 : 0),
            'moodle_course_id' => $get('moodle_course_id'),
            'moodle_shortname' => $get('moodle_shortname'),
            // No pasar semanas_json aquí: se hace upsert por fila más abajo
        ];

        $res = fase_guardar($pdo, $payload);
        if (!$res['ok']) {
            $errores[] = "Línea {$lineNum}: " . ($res['message'] ?? 'error');
            continue;
        }
        $idSaved = (int) ($res['id_fase'] ?? $idFase);
        if ($idSaved <= 0) {
            $stLast = $pdo->prepare(
                'SELECT id_fase FROM especialidad_fases WHERE id_especialidad = ? ORDER BY id_fase DESC LIMIT 1'
            );
            $stLast->execute([$idEsp]);
            $idSaved = (int) $stLast->fetchColumn();
        }

        if ($idSaved > 0 && !isset($fasesVistas[$idSaved])) {
            $fasesVistas[$idSaved] = true;
            if ($esNueva) {
                $creadas++;
            } else {
                $actualizadas++;
            }
        }

        if ($idSaved > 0) {
            $sets = [];
            $params = [];
            $extraMap = [
                'objetivo_parcial' => 'objetivo_parcial',
                'nivel_cefr' => 'nivel_cefr',
                'num_parcial' => 'num_parcial',
                'tipo_contenido' => 'tipo_contenido',
                'eval_listening' => 'eval_listening',
                'eval_reading' => 'eval_reading',
                'eval_writing' => 'eval_writing',
                'eval_speaking' => 'eval_speaking',
                'eval_grammar' => 'eval_grammar',
                'eval_vocabulary' => 'eval_vocabulary',
                'vocabulario_resumen' => 'vocabulario_resumen',
                'gramatica_resumen' => 'gramatica_resumen',
            ];
            foreach ($extraMap as $csvCol => $dbCol) {
                if (!isset($map[$csvCol])) {
                    continue;
                }
                $val = $get($csvCol);
                $sets[] = "{$dbCol} = ?";
                if ($csvCol === 'num_parcial') {
                    $params[] = $val === '' ? null : (int) $val;
                } elseif ($csvCol === 'tipo_contenido') {
                    $params[] = in_array($val, ['regular', 'proyecto_nivel', 'proyecto_final'], true)
                        ? $val
                        : 'regular';
                } else {
                    $params[] = $val === '' ? null : $val;
                }
            }
            if ($sets !== []) {
                try {
                    $params[] = $idSaved;
                    $pdo->prepare(
                        'UPDATE especialidad_fases SET ' . implode(', ', $sets) . ' WHERE id_fase = ?'
                    )->execute($params);
                } catch (Throwable $e) {
                    $errores[] = "Línea {$lineNum}: extras — " . $e->getMessage();
                }
            }

            // Semana (pestaña Semanas)
            $numSemana = $get('semana');
            $tieneDatosSemana = $numSemana !== ''
                || $get('titulo_leccion') !== ''
                || $get('objetivo_semana') !== ''
                || $get('contenido_clase') !== ''
                || $get('vocabulario') !== ''
                || $get('proyecto_tipo') !== '';

            if ($tieneDatosSemana) {
                $sem = (int) $numSemana;
                if ($sem < 1 || $sem > 4) {
                    $errores[] = "Línea {$lineNum}: semana debe ser un número del 1 al 4";
                } else {
                    $contenido = $get('contenido_clase');
                    if ($contenido === '' && isset($map['vocabulario'])) {
                        $contenido = $get('vocabulario');
                    }
                    $objetivoSem = $get('objetivo_semana');
                    if ($objetivoSem === '' && isset($map['objetivo'])) {
                        $objetivoSem = $get('objetivo');
                    }
                    try {
                        fase_csv_upsert_semana($pdo, $idSaved, [
                            'semana' => $sem,
                            'titulo_leccion' => $get('titulo_leccion') !== '' ? $get('titulo_leccion') : $get('tema'),
                            'objetivo' => $objetivoSem,
                            'vocabulario' => $contenido,
                            'gramatica' => $get('gramatica'),
                            'listening' => $get('listening'),
                            'reading' => $get('reading'),
                            'writing' => $get('writing'),
                            'speaking' => $get('speaking'),
                            'notas' => $get('notas'),
                            'es_examen' => $get('es_examen') === '' ? 0 : ((int) $get('es_examen') ? 1 : 0),
                            'proyecto_tipo' => $get('proyecto_tipo'),
                        ]);
                        $semanasOk++;
                    } catch (Throwable $e) {
                        $errores[] = "Línea {$lineNum}: semana — " . $e->getMessage();
                    }
                }
            }
        }
    }
    fclose($fh);

    $msg = "{$actualizadas} fase(s) actualizada(s), {$creadas} creada(s), {$semanasOk} semana(s) guardada(s)";
    if ($errores !== []) {
        $msg .= '. Avisos: ' . count($errores);
    }

    return [
        'ok' => ($actualizadas + $creadas + $semanasOk) > 0 || $errores === [],
        'message' => $msg,
        'actualizadas' => $actualizadas,
        'creadas' => $creadas,
        'semanas' => $semanasOk,
        'errores' => array_slice($errores, 0, 20),
    ];
}
