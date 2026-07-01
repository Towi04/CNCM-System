<?php

namespace HayExam;

use PDO;
use PDOException;

class AnswerSheetService
{
    private PDO $pdo;
    private string $uploadDir;

    public function __construct(PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->uploadDir = rtrim($basePath, '/\\') . '/uploads/examenes';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function getPlantelConfig(): array
    {
        try {
            $row = $this->pdo->query('SELECT digitos_control, peso_mc, peso_writing, peso_speaking FROM exam_plantel_config WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'digitos_control' => (int)$row['digitos_control'],
                    'peso_mc' => (float)$row['peso_mc'],
                    'peso_writing' => (float)$row['peso_writing'],
                    'peso_speaking' => (float)$row['peso_speaking'],
                ];
            }
        } catch (PDOException $e) {
            // tabla aún no existe
        }
        return ['digitos_control' => 5, 'peso_mc' => 70, 'peso_writing' => 15, 'peso_speaking' => 15];
    }

    public function guardarPlantelConfig(int $digitos, float $pesoMc, float $pesoW, float $pesoS): void
    {
        $digitos = AnswerSheetLayout::CN_DIGITOS;
        $stmt = $this->pdo->prepare('
            INSERT INTO exam_plantel_config (id, digitos_control, peso_mc, peso_writing, peso_speaking)
            VALUES (1, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE digitos_control = VALUES(digitos_control),
                peso_mc = VALUES(peso_mc), peso_writing = VALUES(peso_writing), peso_speaking = VALUES(peso_speaking)
        ');
        $stmt->execute([$digitos, $pesoMc, $pesoW, $pesoS]);
    }

    public function generarHoja(string $idExamen, ?int $digitosControl = null): string
    {
        require_once __DIR__ . '/AnswerSheetTemplate.php';
        $html = AnswerSheetTemplate::renderUniversal();
        $bar = '<div class="exam-print-bar" style="background:#e8f0fa;border:1px solid #b6d4f5;padding:8px 12px;margin-bottom:8px;">'
            . '<button type="button" onclick="window.print()" style="background:#11458B;color:#fff;border:none;padding:7px 14px;border-radius:5px;cursor:pointer;font-weight:600;">'
            . 'Print answer sheet</button></div>';
        $html = preg_replace('/<body>/i', '<body>' . $bar, $html, 1);

        $path = $this->uploadDir . '/' . AnswerSheetTemplate::HOJA_UNIVERSAL;
        file_put_contents($path, $html);
        return 'uploads/examenes/' . AnswerSheetTemplate::HOJA_UNIVERSAL;
    }

    public function generarHojaUniversal(): string
    {
        return $this->generarHoja('');
    }

    public function claveExamen(string $idExamen): array
    {
        $exam = $this->obtenerExamen($idExamen);
        if (!$exam) {
            throw new \RuntimeException('Examen no encontrado.');
        }
        $stmt = $this->pdo->prepare("
            SELECT numero, respuesta FROM exam_generado_preguntas
            WHERE id_examen = ? AND tipo = 'opcion_multiple'
            ORDER BY numero ASC
        ");
        $stmt->execute([$idExamen]);
        $clave = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $clave[(int)$r['numero']] = strtoupper((string)$r['respuesta']);
        }
        return [
            'id_examen' => $idExamen,
            'fase' => $this->fasePrincipal($exam['fases_usadas']),
            'nombre_examen' => $exam['nombre_examen'],
            'clave' => $clave,
            'max_mc' => AnswerSheetLayout::MC_COUNT,
        ];
    }

    public function buscarExamen(string $idExamen): ?array
    {
        $id = strtoupper(trim($idExamen));
        if ($id === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('
            SELECT id_examen, nombre_examen, tipo, fases_usadas, creado_en
            FROM exam_generados
            WHERE UPPER(TRIM(id_examen)) = ?
            LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $fasesRaw = array_values(array_filter(array_map('trim', explode(',', (string)$row['fases_usadas']))));
        $tipo = (string)$row['tipo'];
        return [
            'id_examen' => $row['id_examen'],
            'nombre_examen' => $row['nombre_examen'],
            'tipo' => $tipo,
            'es_nivel' => $tipo === 'nivel',
            'fases' => FaseHelper::fasesParaRegistro($tipo, $fasesRaw),
            'fases_detalle' => $fasesRaw,
            'creado_en' => $row['creado_en'],
        ];
    }

    public function listarExamenes(int $limit = 30): array
    {
        try {
            $idPlantel = function_exists('plantel_id_activo') ? plantel_id_activo() : 0;
            $sql = "
                SELECT id_examen, nombre_examen, tipo, fases_usadas, creado_en
                FROM exam_generados WHERE area = 'ingles'";
            if ($idPlantel > 0) {
                $sql .= ' AND (id_plantel = ? OR id_plantel IS NULL)';
            }
            $sql .= ' ORDER BY creado_en DESC LIMIT ?';
            $stmt = $this->pdo->prepare($sql);
            if ($idPlantel > 0) {
                $stmt->execute([$idPlantel, $limit]);
            } else {
                $stmt->execute([$limit]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * @param array{numero_control:string,mc:array<int,string>,writing:array<int,string>,speaking:array<int,string>,fase?:string} $scan
     */
    public function registrarEscaneo(string $idExamen, array $scan, ?int $idProfesor): array
    {
        $exam = $this->obtenerExamen($idExamen);
        if (!$exam) {
            throw new \RuntimeException('Examen no encontrado.');
        }

        $control = AnswerSheetLayout::normalizarCN((string)($scan['numero_control'] ?? ''));
        if (strlen(preg_replace('/\D/', '', (string)($scan['numero_control'] ?? ''))) < 1) {
            throw new \InvalidArgumentException('CN incompleto (se requieren hasta 5 dígitos).');
        }

        $alumno = $this->buscarAlumnoPorControl($control);
        if (!$alumno) {
            throw new \RuntimeException('No se encontró alumno con CN/matrícula: ' . $control);
        }

        $fase = trim((string)($scan['fase'] ?? ''));
        $fasesRaw = array_values(array_filter(array_map('trim', explode(',', (string)$exam['fases_usadas']))));
        if ($fase === '') {
            $opts = FaseHelper::fasesParaRegistro($exam['tipo'], $fasesRaw);
            $fase = $opts[0] ?? $this->fasePrincipal($exam['fases_usadas']);
        }
        if (($exam['tipo'] ?? '') === 'nivel') {
            $nivel = FaseHelper::extraerNivel($fase);
            if ($nivel === '') {
                throw new \InvalidArgumentException('No se pudo determinar el nivel (A1, A1+, B1, etc.) para registrar.');
            }
            $fase = $nivel;
        }

        $clave = $this->claveExamen($idExamen)['clave'];
        $mcIn = $scan['mc'] ?? [];
        $correctas = 0;
        $respuestasMc = [];
        for ($q = 1; $q <= AnswerSheetLayout::MC_COUNT; $q++) {
            $resp = strtoupper(trim((string)($mcIn[$q] ?? $mcIn[(string)$q] ?? '')));
            $ok = isset($clave[$q]) && $resp !== '' && $resp === $clave[$q];
            if ($ok) {
                $correctas++;
            }
            $respuestasMc[$q] = $resp;
        }

        $maxMc = count($clave) > 0 ? count($clave) : AnswerSheetLayout::MC_COUNT;
        $calMc = $maxMc > 0 ? round(($correctas / $maxMc) * 100, 2) : 0;

        $writing = $this->normalizarRubricasBloques(
            $scan['writing'] ?? [],
            AnswerSheetLayout::WRITING_ASPECTS,
            AnswerSheetLayout::WRITING_QUESTIONS
        );
        $speaking = $this->normalizarRubricasBloques(
            $scan['speaking'] ?? [],
            AnswerSheetLayout::SPEAKING_ASPECTS,
            AnswerSheetLayout::SPEAKING_QUESTIONS
        );
        $calWriting = $this->promedioRubricasBloques($writing);
        $calSpeaking = $this->promedioRubricasBloques($speaking);

        $cfg = $this->getPlantelConfig();
        $pesoMc = $cfg['peso_mc'];
        $pesoW = $cfg['peso_writing'];
        $pesoS = $cfg['peso_speaking'];
        $sumaPesos = $pesoMc + $pesoW + $pesoS;
        if ($sumaPesos <= 0) {
            $sumaPesos = 100;
            $pesoMc = 70;
            $pesoW = 15;
            $pesoS = 15;
        }
        $calFinal = round(
            ($calMc * $pesoMc + $calWriting * $pesoW + $calSpeaking * $pesoS) / $sumaPesos,
            2
        );

        $stmt = $this->pdo->prepare('
            INSERT INTO exam_calificaciones
            (id_examen, fase, id_alumno, id_grupo, numero_control, correctas_mc, max_mc,
             calificacion_mc, calificacion_writing, calificacion_speaking, calificacion_final,
             respuestas_mc, rubrica_writing, rubrica_speaking, id_profesor)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                fase = VALUES(fase),
                numero_control = VALUES(numero_control),
                correctas_mc = VALUES(correctas_mc),
                max_mc = VALUES(max_mc),
                calificacion_mc = VALUES(calificacion_mc),
                calificacion_writing = VALUES(calificacion_writing),
                calificacion_speaking = VALUES(calificacion_speaking),
                calificacion_final = VALUES(calificacion_final),
                respuestas_mc = VALUES(respuestas_mc),
                rubrica_writing = VALUES(rubrica_writing),
                rubrica_speaking = VALUES(rubrica_speaking),
                id_profesor = VALUES(id_profesor),
                escaneado_en = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            $idExamen,
            $fase,
            (int)$alumno['id_alumno'],
            (int)$alumno['id_grupo'],
            $control,
            $correctas,
            $maxMc,
            $calMc,
            $calWriting,
            $calSpeaking,
            $calFinal,
            json_encode($respuestasMc, JSON_UNESCAPED_UNICODE),
            json_encode($writing, JSON_UNESCAPED_UNICODE),
            json_encode($speaking, JSON_UNESCAPED_UNICODE),
            $idProfesor,
        ]);

        return [
            'id_alumno' => (int)$alumno['id_alumno'],
            'alumno' => trim($alumno['nombre'] . ' ' . $alumno['apellido']),
            'numero_control' => $control,
            'fase' => $fase,
            'correctas_mc' => $correctas,
            'max_mc' => $maxMc,
            'calificacion_mc' => $calMc,
            'calificacion_writing' => $calWriting,
            'calificacion_speaking' => $calSpeaking,
            'calificacion_final' => $calFinal,
        ];
    }

    public function listarCalificaciones(string $idExamen): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, a.nombre, a.apellido
                FROM exam_calificaciones c
                JOIN alumnos a ON a.id_alumno = c.id_alumno
                WHERE c.id_examen = ?
                ORDER BY c.calificacion_final DESC, a.apellido ASC
            ");
            $stmt->execute([$idExamen]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listarGruposCalificar(): array
    {
        try {
            $idPlantel = function_exists('plantel_id_activo') ? plantel_id_activo() : 0;
            $sql = 'SELECT g.id_grupo, g.clave, e.nombre AS esp_nombre,
                    (SELECT COUNT(*) FROM alumno_grupos ag
                     INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
                     WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1 AND a.activo = 1) AS num_alumnos
                    FROM grupos g
                    LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
                    WHERE 1=1';
            $params = [];
            if ($idPlantel > 0) {
                $sql .= ' AND g.id_plantel = ?';
                $params[] = $idPlantel;
            }
            $sql .= ' ORDER BY g.clave ASC';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listarAlumnosGrupo(int $idGrupo, ?string $idExamen = null): array
    {
        if ($idGrupo <= 0) {
            return [];
        }
        $calificados = [];
        if ($idExamen !== null && $idExamen !== '') {
            try {
                $st = $this->pdo->prepare('SELECT id_alumno FROM exam_calificaciones WHERE id_examen = ?');
                $st->execute([$idExamen]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
                    $calificados[(int) $id] = true;
                }
            } catch (PDOException $e) {
                $calificados = [];
            }
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT a.id_alumno,
                        COALESCE(NULLIF(TRIM(a.numero_control), ''), NULLIF(TRIM(a.matricula), '')) AS cn,
                        TRIM(CONCAT(COALESCE(a.nombres, a.nombre, ''), ' ', COALESCE(a.apellido_paterno, a.apellido, ''))) AS nombre_completo
                 FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
                 WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.activo = 1
                 ORDER BY nombre_completo ASC"
            );
            $stmt->execute([$idGrupo]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$r) {
                $r['cn'] = AnswerSheetLayout::normalizarCN((string) ($r['cn'] ?? ''));
                $r['calificado'] = !empty($calificados[(int) ($r['id_alumno'] ?? 0)]);
            }
            unset($r);

            return $rows;
        } catch (PDOException $e) {
            return [];
        }
    }

    private function buscarAlumnoPorControl(string $control): ?array
    {
        $control = AnswerSheetLayout::normalizarCN($control);
        $variantes = [$control];
        $sinCero = ltrim($control, '0');
        if ($sinCero !== '' && $sinCero !== $control) {
            $variantes[] = $sinCero;
        }
        $idPlantel = function_exists('plantel_id_activo') ? plantel_id_activo() : 0;
        $sql = '
            SELECT id_alumno, id_grupo, nombre, apellido, matricula, numero_control
            FROM alumnos
            WHERE activo = 1 AND (matricula = ? OR numero_control = ?)';
        if ($idPlantel > 0) {
            $sql .= ' AND id_plantel = ?';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        foreach (array_unique($variantes) as $v) {
            $stmt->execute($idPlantel > 0 ? [$v, $v, $idPlantel] : [$v, $v]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        return null;
    }

    private function obtenerExamen(string $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id_examen, nombre_examen, fases_usadas, tipo
            FROM exam_generados WHERE UPPER(id_examen) = UPPER(?) LIMIT 1
        ');
        $stmt->execute([trim($id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fasePrincipal(?string $fases): string
    {
        $parts = array_filter(array_map('trim', explode(',', (string)$fases)));
        return $parts[0] ?? 'General';
    }

    private function normalizarRubrica(array $in, array $aspectos): array
    {
        $out = [];
        foreach ($aspectos as $i => $label) {
            $val = strtoupper(trim((string)($in[$i] ?? $in[(string)$i] ?? '')));
            if (!in_array($val, AnswerSheetLayout::RUBRIC_OPTIONS, true)) {
                $val = '';
            }
            $out[] = ['aspect' => $label, 'letter' => $val, 'percent' => AnswerSheetLayout::rubricPercent($val)];
        }
        return $out;
    }

    private function normalizarRubricasBloques(array $in, array $aspectos, int $numQuestions): array
    {
        $nested = isset($in[0]) && is_array($in[0]);
        if (!$nested && $in !== []) {
            $bloques = [$this->normalizarRubrica($in, $aspectos)];
            for ($q = 1; $q < $numQuestions; $q++) {
                $bloques[] = $this->normalizarRubrica([], $aspectos);
            }
            return $bloques;
        }
        $bloques = [];
        for ($q = 0; $q < $numQuestions; $q++) {
            $bloques[] = $this->normalizarRubrica($in[$q] ?? $in[(string)$q] ?? [], $aspectos);
        }
        return $bloques;
    }

    private function promedioRubricasBloques(array $bloques): float
    {
        $proms = [];
        foreach ($bloques as $bloque) {
            $letras = array_filter(array_column($bloque, 'letter'));
            if ($letras !== []) {
                $proms[] = $this->promedioRubrica($bloque);
            }
        }
        if ($proms === []) {
            return 0;
        }
        return round(array_sum($proms) / count($proms), 2);
    }

    private function promedioRubrica(array $rubrica): float
    {
        $vals = array_filter(array_column($rubrica, 'percent'), fn($p) => $p !== null);
        if (empty($vals)) {
            return 0;
        }
        return round(array_sum($vals) / count($vals), 2);
    }
}
