<?php

/**
 * Plantillas de prompt IA para planeación de clase por especialidad.
 * Placeholders: <<Etiqueta>> se sustituyen con datos del grupo, fase y alumnos.
 */

function planeacion_prompt_ensure_schema(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    catalog_ensure_schema($pdo);
    plantel_ensure_column($pdo, 'especialidades', 'prompt_planeacion', 'MEDIUMTEXT NULL', 'descripcion');
}

function planeacion_prompt_puede_configurar(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['admin', 'director', 'coordinador', 'coordinacion', 'supervisor'], true);
}

/** @return array<string, string> etiqueta => descripción */
function planeacion_prompt_placeholders(): array
{
    return [
        'Tema' => 'Tema de la sesión indicado por el profesor',
        'Nivel' => 'Nivel CEFR de la fase (ej. A1, B2)',
        'Fase' => 'Clave y nombre del parcial / fase',
        'Grupo' => 'Clave del grupo',
        'Especialidad' => 'Nombre de la especialidad',
        'Modalidad' => 'Modalidad del curso (regular, kids, etc.)',
        'Duración' => 'Duración de la clase en minutos',
        'Cantidad de alumnos' => 'Número de alumnos activos en el grupo',
        'Intereses' => 'Gustos e intereses del grupo (perfil de alumnos)',
        'Profesor' => 'Nombre del profesor del grupo',
        'Objetivo parcial' => 'Objetivo del parcial según la fase',
        'Temas fase' => 'Temas programados de la fase',
        'Vocabulario fase' => 'Vocabulario de la fase',
        'Gramática fase' => 'Gramática de la fase',
        'Prácticas sugeridas' => 'Prácticas sugeridas de la fase',
    ];
}

function planeacion_prompt_plantilla_generica(): string
{
    return <<<'TPL'
Eres un asesor pedagógico del Centro de Idiomas CNCM (México).
Genera una planeación de clase aplicable en aula, alineada a la metodología de la escuela.

CONTEXTO DE LA SESIÓN
- Grupo: <<Grupo>>
- Especialidad: <<Especialidad>> (modalidad: <<Modalidad>>)
- Parcial / fase: <<Fase>> (nivel: <<Nivel>>)
- Tema de la sesión: <<Tema>>
- Duración: <<Duración>> minutos
- Alumnos activos: <<Cantidad de alumnos>>
- Profesor: <<Profesor>>

OBJETIVO DEL PARCIAL
<<Objetivo parcial>>

CONTENIDO DE LA FASE
- Temas: <<Temas fase>>
- Vocabulario: <<Vocabulario fase>>
- Gramática: <<Gramática fase>>
- Prácticas sugeridas: <<Prácticas sugeridas>>

<<Intereses>>

Entrega en español de México con este formato:
1) Objetivo de aprendizaje (1-2 líneas)
2) Actividad de inicio (5-10 min)
3) Actividad principal (25-35 min) con variantes según tamaño del grupo
4) Cierre y evaluación formativa (5-10 min)
5) Materiales y recursos
6) Adaptaciones (ritmos distintos, apoyo extra, extensión)

Sé concreto, profesional y listo para usar en clase.
TPL;
}

function planeacion_prompt_plantilla_ingles(): string
{
    return <<<'TPL'
Actúa como un Coordinador Académico Experto en la enseñanza del idioma inglés bajo el enfoque constructivista. Tu objetivo es diseñarme una planeación de clase detallada y minuto a minuto basada en los parámetros que te proporcionaré.

CONTEXTO
- Grupo: <<Grupo>>
- Nivel de inglés del grupo: <<Nivel>>
- Parcial / fase: <<Fase>>
- Cantidad de alumnos: <<Cantidad de alumnos>>
- Gustos de los alumnos: <<Intereses>>
- Tema principal de la clase: <<Tema>>
- Duración: <<Duración>> minutos
- Profesor: <<Profesor>>

OBJETIVO DEL PARCIAL
<<Objetivo parcial>>

CONTENIDO PROGRAMADO DE LA FASE
- Vocabulario: <<Vocabulario fase>>
- Gramática: <<Gramática fase>>
- Temas: <<Temas fase>>

METODOLOGÍA Y SECUENCIA DE EXPLICACIÓN
Debes estructurar la clase siguiendo el constructivismo y aplicar estrictamente el siguiente orden secuencial para las explicaciones de gramática o vocabulario:
1. Método Natural (Inmersión/Inducción): Presenta el tema mediante el contexto, ejemplos vivos, imágenes o mímica. El alumno debe intentar deducir la regla por sí mismo.
2. Método Deductivo: Si tras el método natural el alumno no comprende, pasa a una explicación directa, clara y estructurada de la regla gramatical o estructura.
3. Método Adaptativo: Si persisten dudas, adapta la explicación usando analogías, ejemplos personalizados según los intereses del grupo o simplificando el vocabulario.

FORMATO DE ENTREGA (español de México)
1) Objetivo de aprendizaje comunicativo
2) Secuencia minuto a minuto (inicio, desarrollo, cierre) con tiempos
3) Actividades con instrucciones claras para el docente
4) Materiales y recursos
5) Evaluación formativa
6) Adaptaciones según tamaño del grupo y ritmos distintos

Personaliza ejemplos según los intereses del grupo cuando sea pedagógicamente útil. Sé concreto y listo para usar en aula.
TPL;
}

function planeacion_prompt_es_ingles(?array $espRow): bool
{
    if (!$espRow) {
        return false;
    }
    if (function_exists('fase_es_especialidad_ingles') && fase_es_especialidad_ingles($espRow)) {
        return true;
    }
    $nombre = mb_strtolower(trim((string) ($espRow['nombre'] ?? '')));

    return str_contains($nombre, 'inglés') || str_contains($nombre, 'ingles');
}

/** Plantilla predeterminada según especialidad (sin personalización guardada). */
function planeacion_prompt_plantilla_default(?array $espRow = null): string
{
    return planeacion_prompt_es_ingles($espRow)
        ? planeacion_prompt_plantilla_ingles()
        : planeacion_prompt_plantilla_generica();
}

function planeacion_prompt_obtener_raw(PDO $pdo, int $idEspecialidad): ?string
{
    planeacion_prompt_ensure_schema($pdo);
    if ($idEspecialidad <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT prompt_planeacion FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEspecialidad]);
    $val = $st->fetchColumn();
    if ($val === false || $val === null) {
        return null;
    }
    $txt = trim((string) $val);

    return $txt !== '' ? $txt : null;
}

function planeacion_prompt_obtener(PDO $pdo, int $idEspecialidad): string
{
    $custom = planeacion_prompt_obtener_raw($pdo, $idEspecialidad);
    if ($custom !== null) {
        return $custom;
    }
    $esp = null;
    if ($idEspecialidad > 0) {
        $st = $pdo->prepare('SELECT id_especialidad, clave, nombre, modalidad FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $st->execute([$idEspecialidad]);
        $esp = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return planeacion_prompt_plantilla_default($esp);
}

/** @return array{ok:bool,message:string} */
function planeacion_prompt_guardar(PDO $pdo, int $idEspecialidad, string $plantilla): array
{
    if (!planeacion_prompt_puede_configurar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    if ($idEspecialidad <= 0) {
        return ['ok' => false, 'message' => 'Especialidad inválida'];
    }
    planeacion_prompt_ensure_schema($pdo);
    $plantilla = trim($plantilla);
    $pdo->prepare('UPDATE especialidades SET prompt_planeacion = ? WHERE id_especialidad = ?')
        ->execute([$plantilla !== '' ? $plantilla : null, $idEspecialidad]);

    return ['ok' => true, 'message' => 'Plantilla guardada.'];
}

/**
 * Sustituye <<Etiqueta>> en la plantilla. Etiquetas no reconocidas se dejan intactas.
 *
 * @param array<string, string> $vars
 */
function planeacion_prompt_aplicar(string $plantilla, array $vars): string
{
    $map = [];
    foreach ($vars as $k => $v) {
        $map[mb_strtolower(trim((string) $k))] = (string) $v;
    }

    return preg_replace_callback(
        '/<<([^>]+)>>/u',
        static function (array $m) use ($map): string {
            $key = mb_strtolower(trim($m[1]));
            if (array_key_exists($key, $map)) {
                return $map[$key];
            }

            return $m[0];
        },
        $plantilla
    ) ?? $plantilla;
}

/** @return array<string, string> */
function planeacion_prompt_vars_ejemplo(): array
{
    return [
        'Tema' => 'Present Perfect vs Past Simple',
        'Nivel' => 'B1',
        'Fase' => 'P3 — Tercer parcial',
        'Grupo' => 'ING-M-V-01',
        'Especialidad' => 'Inglés Modalidad Virtual',
        'Modalidad' => 'regular',
        'Duración' => '50',
        'Cantidad de alumnos' => '12',
        'Intereses' => "Pasatiempos frecuentes: fútbol, videojuegos, música K-pop.\nIncluya ejemplos alineados a estos intereses.",
        'Profesor' => 'María García',
        'Objetivo parcial' => 'Usar tiempos verbales para narrar experiencias pasadas y hechos recientes.',
        'Temas fase' => 'Narración de experiencias, marcadores temporales',
        'Vocabulario fase' => 'already, yet, just, ever, never',
        'Gramática fase' => 'Present Perfect vs Past Simple',
        'Prácticas sugeridas' => 'Role-play, línea de tiempo personal, juego de preguntas',
    ];
}

/**
 * Construye variables para sustituir en la plantilla.
 *
 * @return array<string, string>
 */
function planeacion_prompt_vars_contexto(
    PDO $pdo,
    array $grupo,
    array $fase,
    string $tema,
    string $duracion,
    int $alumnosActivos
): array {
    $modalidades = function_exists('catalog_modalidades_etiquetas')
        ? catalog_modalidades_etiquetas()
        : [];
    $modKey = trim((string) ($grupo['modalidad'] ?? ''));
    if ($modKey === '' && !empty($grupo['id_especialidad'])) {
        $stMod = $pdo->prepare('SELECT modalidad FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $stMod->execute([(int) $grupo['id_especialidad']]);
        $modKey = trim((string) ($stMod->fetchColumn() ?: ''));
    }
    $modLabel = $modalidades[$modKey] ?? ($modKey !== '' ? $modKey : '—');

    $faseClave = trim((string) ($fase['clave_fase'] ?? ''));
    $faseNombre = trim((string) ($fase['nombre_fase'] ?? ''));
    $faseTxt = ($faseClave !== '' ? $faseClave . ' — ' : '') . $faseNombre;

    $idGrupo = (int) ($grupo['id_grupo'] ?? 0);
    $bloqueGustos = '';
    if ($idGrupo > 0 && function_exists('planeacion_grupo_gustos_texto')) {
        $bloqueGustos = planeacion_grupo_gustos_texto($pdo, $idGrupo);
    }
    if ($bloqueGustos === '') {
        $bloqueGustos = '(Sin perfiles de intereses registrados aún para este grupo.)';
    }

    $nivel = trim((string) ($fase['nivel_cefr'] ?? ''));
    if ($nivel === '') {
        $nivel = 'No especificado';
    }

    return [
        'Tema' => $tema,
        'Nivel' => $nivel,
        'Fase' => $faseTxt !== '' ? $faseTxt : '—',
        'Grupo' => trim((string) ($grupo['clave'] ?? '')),
        'Especialidad' => trim((string) ($grupo['esp_nombre'] ?? '')),
        'Modalidad' => $modLabel,
        'Duración' => $duracion !== '' ? $duracion : '50',
        'Cantidad de alumnos' => (string) max(0, $alumnosActivos),
        'Intereses' => $bloqueGustos,
        'Profesor' => trim((string) ($grupo['profesor_nombre'] ?? '')),
        'Objetivo parcial' => trim((string) ($fase['objetivo_parcial'] ?? '')) ?: '—',
        'Temas fase' => trim((string) ($fase['temas'] ?? '')) ?: '—',
        'Vocabulario fase' => trim((string) ($fase['vocabulario_resumen'] ?? '')) ?: '—',
        'Gramática fase' => trim((string) ($fase['gramatica_resumen'] ?? '')) ?: '—',
        'Prácticas sugeridas' => trim((string) ($fase['practicas_sugeridas'] ?? '')) ?: '—',
    ];
}

/** @return array<string, mixed>|null */
function planeacion_prompt_fase_detalle(PDO $pdo, int $idFase): ?array
{
    if ($idFase <= 0) {
        return null;
    }
    if (function_exists('fase_ensure_schema')) {
        fase_ensure_schema($pdo);
    }
    $st = $pdo->prepare('SELECT * FROM especialidad_fases WHERE id_fase = ? LIMIT 1');
    $st->execute([$idFase]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Resuelve el prompt final para generar una planeación.
 */
function planeacion_prompt_resolver(
    PDO $pdo,
    int $idEspecialidad,
    array $grupo,
    array $fase,
    string $tema,
    string $duracion,
    int $alumnosActivos
): string {
    $plantilla = planeacion_prompt_obtener($pdo, $idEspecialidad);
    $vars = planeacion_prompt_vars_contexto($pdo, $grupo, $fase, $tema, $duracion, $alumnosActivos);

    return planeacion_prompt_aplicar($plantilla, $vars);
}
