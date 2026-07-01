<?php

declare(strict_types=1);



namespace HayTutor;



use PDO;



/**

 * RAG institucional: temarios CNCM (especialidades, fases, semanas).

 * Combina búsqueda por intención (semana/fase), contexto del alumno y keywords.

 */

final class AcademicContextRetriever

{

    /** @var list<string> */

    private const STOP_WORDS = [

        'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'y', 'o', 'en', 'con', 'por', 'para',

        'que', 'como', 'cuál', 'cual', 'qué', 'es', 'son', 'me', 'mi', 'tu', 'su', 'se',

        'a', 'al', 'lo', 'le', 'les', 'nos', 'hay', 'si', 'sí', 'no', 'the', 'is', 'are', 'what',

        'how', 'can', 'could', 'would', 'should', 'do', 'does', 'did', 'have', 'has', 'been',

        'puedes', 'puede', 'podrías', 'explicar', 'explicarme', 'brevemente', 'favor', 'hola',

    ];



    public function __construct(private PDO $pdo)

    {

    }



    /** @return list<string> */

    public function tokenizar(string $texto): array

    {

        $texto = mb_strtolower($texto);

        $texto = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $texto) ?? $texto;

        $parts = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];

        foreach ($parts as $p) {

            if (mb_strlen($p) < 3 || in_array($p, self::STOP_WORDS, true)) {

                continue;

            }

            $tokens[] = $p;

        }



        return array_values(array_unique($tokens));

    }



    /**

     * @return array{especialidades:list<array>,fases:list<array>,semanas:list<array>,bloques:list<string>}

     */

    public function buscar(string $pregunta, ?string $especialidadTutor = null, ?int $userId = null, int $limite = 12): array

    {

        $espIds = $this->especialidadIdsPorTutor($especialidadTutor);

        $ctxUsuario = ($userId !== null && $userId > 0) ? $this->contextoAcademicoUsuario($userId, $espIds) : null;



        $numSemana = $this->extraerNumeroSemana($pregunta);

        $idFaseRef = $this->extraerIdFase($pregunta, $espIds, $ctxUsuario);

        $intencionTemario = $this->intencionTemario($pregunta);



        $especialidades = [];

        $fases = [];

        $semanas = [];



        if ($numSemana !== null) {

            $semanas = $this->obtenerSemanasPorNumero($espIds, $numSemana, $idFaseRef, $ctxUsuario, $limite);

        }



        if ($idFaseRef !== null) {

            $fase = $this->obtenerFasePorId($idFaseRef);

            if ($fase) {

                $fases[] = $fase;

            }

        }



        if ($ctxUsuario !== null && $intencionTemario && empty($fases)) {

            $idFaseAlumno = (int) ($ctxUsuario['id_fase'] ?? 0);

            if ($idFaseAlumno > 0) {

                $faseAlumno = $this->obtenerFasePorId($idFaseAlumno);

                if ($faseAlumno) {

                    $fases[] = $faseAlumno;

                }

                if ($numSemana !== null && $semanas === []) {

                    $semanas = $this->obtenerSemanasPorNumero($espIds, $numSemana, $idFaseAlumno, $ctxUsuario, $limite);

                }

            }

        }



        $tokens = $this->tokenizar($pregunta);

        if ($tokens === [] && $intencionTemario) {

            $tokens = ['temario'];

        }



        if ($tokens !== []) {

            $especialidades = $this->buscarEspecialidades($tokens, $espIds, 4);

            $fases = $this->fusionarFilas($fases, $this->buscarFases($tokens, $espIds, min(5, $limite)));

            $semanas = $this->fusionarFilas($semanas, $this->buscarSemanas($tokens, $espIds, $limite));

        }



        if ($intencionTemario && $semanas === [] && $numSemana !== null) {
            $primeraFase = $this->listarFasesResumen($espIds, $ctxUsuario, 1);
            if ($primeraFase !== []) {
                $fases = $this->fusionarFilas($fases, $primeraFase);
                $semanas = $this->obtenerSemanasPorNumero(
                    $espIds,
                    $numSemana,
                    (int) ($primeraFase[0]['id_fase'] ?? 0),
                    $ctxUsuario,
                    $limite
                );
            }
        }

        if ($intencionTemario && $semanas === [] && $numSemana === null) {

            $numSemana = 1;

            $semanas = $this->obtenerSemanasPorNumero($espIds, 1, $idFaseRef, $ctxUsuario, $limite);

        }



        if ($intencionTemario && $fases === []) {

            $fases = $this->listarFasesResumen($espIds, $ctxUsuario, 3);

        }



        if ($intencionTemario && $especialidades === [] && $espIds !== []) {

            $especialidades = $this->listarEspecialidadesResumen($espIds, 3);

        }



        $bloques = $this->construirBloques($especialidades, $fases, $semanas, $ctxUsuario);



        return [

            'especialidades' => $especialidades,

            'fases' => $fases,

            'semanas' => $semanas,

            'bloques' => $bloques,

            'contexto_usuario' => $ctxUsuario,

        ];

    }



    public function formatearContexto(array $resultado): string

    {

        $bloques = $resultado['bloques'] ?? [];

        if ($bloques === []) {

            return 'No se encontró contenido institucional específico para esta consulta en la base de datos CNCM.';

        }



        return "CONTENIDO OFICIAL DEL TEMARIO CNCM (usar como fuente principal):\n\n"
            . $this->bloqueContextoUsuario($resultado['contexto_usuario'] ?? null)
            . implode("\n\n---\n\n", $bloques);

    }



    /** @param array<string, mixed>|null $ctx */

    private function bloqueContextoUsuario(?array $ctx): string

    {

        if ($ctx === null || empty($ctx['grupo_clave'])) {

            return '';

        }



        return '[ALUMNO EN GRUPO] '

            . ($ctx['grupo_clave'] ?? '')

            . ' · ' . ($ctx['esp_nombre'] ?? '')

            . ' · Fase actual: ' . trim(($ctx['clave_fase'] ?? '') . ' ' . ($ctx['nombre_fase'] ?? ''))

            . "\n\n";

    }



    private function intencionTemario(string $pregunta): bool

    {

        $t = mb_strtolower($pregunta);



        return (bool) preg_match(

            '/\b(temario|temas?|semana|parcial|fase|lecci[oó]n|unidad|contenido|clases?|ingl[eé]s|ingles|english|computaci[oó]n|preparatoria|kids)\b/u',

            $t

        );

    }



    private function extraerNumeroSemana(string $pregunta): ?int

    {

        $t = mb_strtolower($pregunta);



        if (preg_match('/\b(?:primera|primer|1ra|1ª)\s+semana\b/u', $t)) {

            return 1;

        }

        if (preg_match('/\bsemana\s+(?:1|uno|primera|1ra|1ª)\b/u', $t)) {

            return 1;

        }

        if (preg_match('/\bweek\s+(\d{1,2})\b/i', $pregunta, $m)) {

            return max(1, min(52, (int) $m[1]));

        }

        if (preg_match('/\bsemana\s+(\d{1,2})\b/u', $t, $m)) {

            return max(1, min(52, (int) $m[1]));

        }



        $ordinales = [

            'segunda' => 2, 'segundo' => 2, '2da' => 2, '2ª' => 2,

            'tercera' => 3, 'tercer' => 3, '3ra' => 3, '3ª' => 3,

            'cuarta' => 4, 'cuarto' => 4, '4ta' => 4, '4ª' => 4,

            'quinta' => 5, 'quinto' => 5,

            'sexta' => 6, 'sexto' => 6,

        ];

        foreach ($ordinales as $palabra => $num) {

            if (preg_match('/\b' . preg_quote($palabra, '/') . '\s+semana\b/u', $t)) {

                return $num;

            }

        }



        return null;

    }



    /** @param list<int> $espIds @return array<string, mixed>|null */

    private function contextoAcademicoUsuario(int $userId, array $espIds): ?array

    {

        $idAlumno = 0;

        if (function_exists('alumno_portal_id_sesion')) {

            $idAlumno = alumno_portal_id_sesion();

        }

        if ($idAlumno <= 0) {

            $st = $this->pdo->prepare('SELECT id_alumno FROM usuarios WHERE id_usuario = ? LIMIT 1');

            $st->execute([$userId]);

            $idAlumno = (int) $st->fetchColumn();

        }

        if ($idAlumno <= 0) {

            return null;

        }



        $sql = 'SELECT g.id_grupo, g.clave AS grupo_clave, g.id_especialidad, g.id_fase_actual,

                       e.clave AS esp_clave, e.nombre AS esp_nombre,

                       f.clave_fase, f.nombre_fase

                FROM alumno_grupos ag

                INNER JOIN grupos g ON g.id_grupo = ag.id_grupo

                INNER JOIN especialidades e ON e.id_especialidad = g.id_especialidad

                LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual

                WHERE ag.id_alumno = ? AND ag.activo = 1 AND e.activo = 1';

        $params = [$idAlumno];

        if ($espIds !== []) {

            $in = implode(',', array_fill(0, count($espIds), '?'));

            $sql .= " AND g.id_especialidad IN ($in)";

            $params = array_merge($params, $espIds);

        }

        if ($this->columnaExiste('alumno_grupos', 'fecha_inicio') && $this->columnaExiste('alumno_grupos', 'id_alumno_grupo')) {
            $sql .= ' ORDER BY ag.fecha_inicio DESC, ag.id_alumno_grupo DESC LIMIT 1';
        } elseif ($this->columnaExiste('alumno_grupos', 'id_alumno_grupo')) {
            $sql .= ' ORDER BY ag.id_alumno_grupo DESC LIMIT 1';
        } elseif ($this->columnaExiste('alumno_grupos', 'creado_en')) {
            $sql .= ' ORDER BY ag.creado_en DESC LIMIT 1';
        } else {
            $sql .= ' LIMIT 1';
        }



        $st = $this->pdo->prepare($sql);

        $st->execute($params);

        $row = $st->fetch(PDO::FETCH_ASSOC);



        if (!$row) {

            return null;

        }



        return [

            'id_grupo' => (int) ($row['id_grupo'] ?? 0),

            'grupo_clave' => (string) ($row['grupo_clave'] ?? ''),

            'id_especialidad' => (int) ($row['id_especialidad'] ?? 0),

            'id_fase' => (int) ($row['id_fase_actual'] ?? 0),

            'esp_clave' => (string) ($row['esp_clave'] ?? ''),

            'esp_nombre' => (string) ($row['esp_nombre'] ?? ''),

            'clave_fase' => (string) ($row['clave_fase'] ?? ''),

            'nombre_fase' => (string) ($row['nombre_fase'] ?? ''),

        ];

    }



    /**

     * @param list<int> $espIds

     * @param array<string, mixed>|null $ctxUsuario

     */

    private function extraerIdFase(string $pregunta, array $espIds, ?array $ctxUsuario): ?int

    {

        $t = mb_strtoupper($pregunta);

        if (preg_match('/\bF\s*(\d{1,2})\b/', $t, $m)) {

            $id = $this->idFasePorClave($espIds, 'F' . $m[1]);

            if ($id > 0) {

                return $id;

            }

        }

        if (preg_match('/\b(?:parcial|fase)\s+(\d{1,2})\b/ui', $pregunta, $m)) {

            $id = $this->idFasePorOrden($espIds, (int) $m[1]);

            if ($id > 0) {

                return $id;

            }

        }

        if ($ctxUsuario !== null && !empty($ctxUsuario['id_fase'])) {

            return (int) $ctxUsuario['id_fase'];

        }



        return null;

    }



    /** @param list<int> $espIds */

    private function idFasePorClave(array $espIds, string $claveFase): int

    {

        if ($espIds === []) {

            return 0;

        }

        $in = implode(',', array_fill(0, count($espIds), '?'));

        $params = array_merge($espIds, [strtoupper($claveFase)]);

        $st = $this->pdo->prepare(

            "SELECT id_fase FROM especialidad_fases

             WHERE id_especialidad IN ($in) AND activo = 1 AND UPPER(clave_fase) = ?

             ORDER BY orden ASC LIMIT 1"

        );

        $st->execute($params);



        return (int) ($st->fetchColumn() ?: 0);

    }



    /** @param list<int> $espIds */

    private function idFasePorOrden(array $espIds, int $orden): int

    {

        if ($espIds === [] || $orden <= 0) {

            return 0;

        }

        $in = implode(',', array_fill(0, count($espIds), '?'));

        $st = $this->pdo->prepare(

            "SELECT id_fase FROM especialidad_fases

             WHERE id_especialidad IN ($in) AND activo = 1

             ORDER BY orden ASC, id_fase ASC

             LIMIT 1 OFFSET " . max(0, $orden - 1)

        );

        $st->execute($espIds);



        return (int) ($st->fetchColumn() ?: 0);

    }



    /** @param list<int> $espIds @param array<string, mixed>|null $ctxUsuario @return list<array> */

    private function obtenerSemanasPorNumero(array $espIds, int $numSemana, ?int $idFase, ?array $ctxUsuario, int $limite): array

    {

        if ($espIds === []) {

            return [];

        }



        $in = implode(',', array_fill(0, count($espIds), '?'));

        $params = $espIds;

        $params[] = $numSemana;



        $sql = "SELECT s.id_semana, s.id_fase, s.semana, s.titulo_leccion, s.objetivo,

                       s.vocabulario, s.gramatica, s.listening, s.reading, s.writing, s.speaking, s.notas,

                       f.clave_fase, f.nombre_fase, f.objetivo_parcial, f.temas AS fase_temas,

                       e.clave AS esp_clave, e.nombre AS esp_nombre

                FROM fase_temario_semana s

                INNER JOIN especialidad_fases f ON f.id_fase = s.id_fase AND f.activo = 1

                INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad AND e.activo = 1

                WHERE f.id_especialidad IN ($in) AND s.semana = ?";



        if ($idFase !== null && $idFase > 0) {

            $sql .= ' AND f.id_fase = ?';

            $params[] = $idFase;

        }



        $idFasePreferida = (int) ($ctxUsuario['id_fase'] ?? 0);

        if ($idFasePreferida > 0) {

            $sql .= ' ORDER BY (e.clave = \'ING\') DESC, (f.id_fase = ' . (int) $idFasePreferida . ') DESC, f.orden ASC, s.semana ASC';

        } else {

            $sql .= ' ORDER BY (e.clave = \'ING\') DESC, f.orden ASC, s.semana ASC';

        }

        $sql .= ' LIMIT ' . max(1, min(10, $limite));



        $st = $this->pdo->prepare($sql);

        $st->execute($params);



        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }



  /** @return array<string, mixed>|null */

    private function obtenerFasePorId(int $idFase): ?array

    {

        if ($idFase <= 0) {

            return null;

        }

        $st = $this->pdo->prepare(

            'SELECT f.id_fase, f.id_especialidad, f.clave_fase, f.nombre_fase, f.objetivo_parcial,

                    f.temas, f.nivel_cefr, f.descripcion, e.clave AS esp_clave, e.nombre AS esp_nombre

             FROM especialidad_fases f

             INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad

             WHERE f.id_fase = ? AND f.activo = 1 LIMIT 1'

        );

        $st->execute([$idFase]);

        $row = $st->fetch(PDO::FETCH_ASSOC);



        return $row ?: null;

    }



    /** @param list<int> $espIds @param array<string, mixed>|null $ctxUsuario @return list<array> */

    private function listarFasesResumen(array $espIds, ?array $ctxUsuario, int $limite): array

    {

        if ($espIds === []) {

            return [];

        }

        $in = implode(',', array_fill(0, count($espIds), '?'));

        $idFasePref = (int) ($ctxUsuario['id_fase'] ?? 0);

        $order = $idFasePref > 0

            ? 'ORDER BY (f.id_fase = ' . $idFasePref . ') DESC, f.orden ASC'

            : 'ORDER BY f.orden ASC';



        $sql = "SELECT f.id_fase, f.id_especialidad, f.clave_fase, f.nombre_fase, f.objetivo_parcial,

                       f.temas, f.nivel_cefr, e.clave AS esp_clave, e.nombre AS esp_nombre

                FROM especialidad_fases f

                INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad

                WHERE f.id_especialidad IN ($in) AND f.activo = 1

                $order

                LIMIT " . max(1, $limite);

        $st = $this->pdo->prepare($sql);

        $st->execute($espIds);



        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }



    /** @param list<int> $espIds @return list<array> */

    private function listarEspecialidadesResumen(array $espIds, int $limite): array

    {

        $in = implode(',', array_fill(0, count($espIds), '?'));

        $st = $this->pdo->prepare(

            "SELECT id_especialidad, clave, nombre, modalidad, descripcion

             FROM especialidades WHERE id_especialidad IN ($in) AND activo = 1

             ORDER BY orden ASC LIMIT " . max(1, $limite)

        );

        $st->execute($espIds);



        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }



    /** @param list<array> $base @param list<array> $extra @return list<array> */

    private function fusionarFilas(array $base, array $extra): array

    {

        $seen = [];

        $out = [];

        foreach (array_merge($base, $extra) as $row) {

            $key = (int) ($row['id_semana'] ?? $row['id_fase'] ?? $row['id_especialidad'] ?? 0);

            if ($key > 0 && isset($seen[$key])) {

                continue;

            }

            if ($key > 0) {

                $seen[$key] = true;

            }

            $out[] = $row;

        }



        return $out;

    }



    /** @return list<int> */

    private function especialidadIdsPorTutor(?string $especialidad): array

    {

        $map = [

            'ingles' => ["modalidad IN ('regular','extensivo')", "clave LIKE 'ING%'"],

            'computacion' => ["clave LIKE 'COMP%'"],

            'preparatoria' => ["modalidad IN ('prep_abierta','prep_escolarizada')"],

            'kids' => ["modalidad = 'kids'"],

        ];



        $sql = 'SELECT id_especialidad FROM especialidades WHERE activo = 1';



        if ($especialidad !== null && $especialidad !== '' && $especialidad !== 'general' && isset($map[$especialidad])) {

            $conds = $map[$especialidad];

            $sql .= ' AND (' . implode(' OR ', $conds) . ')';

        }



        $sql .= ' ORDER BY orden ASC, nombre ASC LIMIT 40';

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];



        return array_map(static fn ($r) => (int) $r['id_especialidad'], $rows);

    }



    /** @param list<string> $tokens @param list<int> $espIds @return list<array> */

    private function buscarEspecialidades(array $tokens, array $espIds, int $limite): array

    {

        $where = ['e.activo = 1'];

        $params = [];

        if ($espIds !== []) {

            $in = implode(',', array_fill(0, count($espIds), '?'));

            $where[] = "e.id_especialidad IN ($in)";

            $params = array_merge($params, $espIds);

        }

        $likeParts = [];

        foreach (array_slice($tokens, 0, 6) as $t) {

            $likeParts[] = '(e.nombre LIKE ? OR e.clave LIKE ? OR e.descripcion LIKE ?)';

            $p = '%' . $t . '%';

            $params[] = $p;

            $params[] = $p;

            $params[] = $p;

        }

        if ($likeParts !== []) {

            $where[] = '(' . implode(' OR ', $likeParts) . ')';

        }



        $sql = 'SELECT e.id_especialidad, e.clave, e.nombre, e.modalidad, e.descripcion

                FROM especialidades e

                WHERE ' . implode(' AND ', $where) . '

                ORDER BY e.orden ASC LIMIT ' . max(1, $limite);

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);



        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }



    /** @param list<string> $tokens @param list<int> $espIds @return list<array> */

    private function buscarFases(array $tokens, array $espIds, int $limite): array

    {

        $params = [];

        $where = ['f.activo = 1'];

        if ($espIds !== []) {

            $in = implode(',', array_fill(0, count($espIds), '?'));

            $where[] = "f.id_especialidad IN ($in)";

            $params = array_merge($params, $espIds);

        }

        $likeParts = [];

        foreach (array_slice($tokens, 0, 6) as $t) {

            $likeParts[] = '(f.nombre_fase LIKE ? OR f.clave_fase LIKE ? OR f.objetivo_parcial LIKE ? OR f.temas LIKE ? OR f.descripcion LIKE ?)';

            $p = '%' . $t . '%';

            for ($i = 0; $i < 5; $i++) {

                $params[] = $p;

            }

        }

        if ($likeParts !== []) {

            $where[] = '(' . implode(' OR ', $likeParts) . ')';

        }



        $sql = 'SELECT f.id_fase, f.id_especialidad, f.clave_fase, f.nombre_fase, f.objetivo_parcial,

                       f.temas, f.nivel_cefr, e.clave AS esp_clave, e.nombre AS esp_nombre

                FROM especialidad_fases f

                INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad

                WHERE ' . implode(' AND ', $where) . '

                ORDER BY f.orden ASC

                LIMIT ' . max(1, $limite);

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);



        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }



    /** @param list<string> $tokens @param list<int> $espIds @return list<array> */

    private function buscarSemanas(array $tokens, array $espIds, int $limite): array

    {

        $params = [];

        $where = ['f.activo = 1'];

        if ($espIds !== []) {

            $in = implode(',', array_fill(0, count($espIds), '?'));

            $where[] = "f.id_especialidad IN ($in)";

            $params = array_merge($params, $espIds);

        }

        $likeParts = [];

        foreach (array_slice($tokens, 0, 6) as $t) {

            $likeParts[] = '(s.titulo_leccion LIKE ? OR s.objetivo LIKE ? OR s.vocabulario LIKE ? OR s.gramatica LIKE ? OR s.listening LIKE ? OR s.reading LIKE ? OR s.writing LIKE ? OR s.speaking LIKE ? OR s.notas LIKE ?)';

            $p = '%' . $t . '%';

            for ($i = 0; $i < 9; $i++) {

                $params[] = $p;

            }

        }

        if ($likeParts !== []) {

            $where[] = '(' . implode(' OR ', $likeParts) . ')';

        }



        $sql = 'SELECT s.id_semana, s.id_fase, s.semana, s.titulo_leccion, s.objetivo,

                       s.vocabulario, s.gramatica, s.listening, s.reading, s.writing, s.speaking, s.notas,

                       f.clave_fase, f.nombre_fase, f.objetivo_parcial, e.clave AS esp_clave, e.nombre AS esp_nombre

                FROM fase_temario_semana s

                INNER JOIN especialidad_fases f ON f.id_fase = s.id_fase

                INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad

                WHERE ' . implode(' AND ', $where) . '

                ORDER BY e.clave ASC, f.orden ASC, s.semana ASC

                LIMIT ' . max(1, $limite);

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);



        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }



    /** @param list<array> $especialidades @param list<array> $fases @param list<array> $semanas @return list<string> */

    private function construirBloques(array $especialidades, array $fases, array $semanas, ?array $ctxUsuario = null): array

    {

        $bloques = [];



        foreach ($especialidades as $e) {

            $bloques[] = '[PROGRAMA] ' . ($e['clave'] ?? '') . ' — ' . ($e['nombre'] ?? '')

                . "\nModalidad: " . ($e['modalidad'] ?? '')

                . "\n" . $this->truncar((string) ($e['descripcion'] ?? ''), 400);

        }



        foreach ($fases as $f) {

            $bloques[] = '[PARCIAL/FASE] ' . ($f['esp_clave'] ?? '') . ' / ' . ($f['clave_fase'] ?? '') . ' — ' . ($f['nombre_fase'] ?? '')

                . "\nObjetivo del parcial: " . $this->truncar((string) ($f['objetivo_parcial'] ?? ''), 600)

                . "\nTemas generales: " . $this->truncar((string) ($f['temas'] ?? ''), 500)

                . (!empty($f['nivel_cefr']) ? "\nNivel CEFR: " . $f['nivel_cefr'] : '');

        }



        foreach ($semanas as $s) {

            $lineas = ['[LECCIÓN SEMANAL OFICIAL] ' . ($s['esp_clave'] ?? '') . ' / ' . ($s['clave_fase'] ?? '') . ' — Semana ' . ($s['semana'] ?? '')];

            if (!empty($s['titulo_leccion'])) {

                $lineas[] = 'Título: ' . $s['titulo_leccion'];

            }

            if (!empty($s['objetivo_parcial'])) {

                $lineas[] = 'Objetivo del parcial: ' . $this->truncar((string) $s['objetivo_parcial'], 300);

            }

            foreach (['objetivo', 'vocabulario', 'gramatica', 'listening', 'reading', 'writing', 'speaking', 'notas'] as $campo) {

                if (!empty($s[$campo])) {

                    $lineas[] = ucfirst($campo) . ': ' . $this->truncar((string) $s[$campo], 500);

                }

            }

            $bloques[] = implode("\n", $lineas);

        }



        return array_values(array_unique($bloques));

    }



    private function truncar(string $texto, int $max): string

    {

        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);

        if (mb_strlen($texto) <= $max) {

            return $texto;

        }



        return mb_substr($texto, 0, $max - 1) . '…';

    }



    private function columnaExiste(string $tabla, string $columna): bool

    {

        $st = $this->pdo->prepare(

            'SELECT 1 FROM information_schema.columns

             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'

        );

        $st->execute([$tabla, $columna]);



        return (bool) $st->fetchColumn();

    }

}


