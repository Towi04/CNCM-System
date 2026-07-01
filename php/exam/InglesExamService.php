<?php

namespace HayExam;

use PDO;
use PDOException;
class InglesExamService
{
    private PDO $pdo;
    private string $uploadDir;
    private string $logoPath;

    private const COUNTS = [
        'vocabulario' => 10,
        'gramatica'   => 20,
        'listening'   => 5,
        'reading'     => 6,
        'writing'     => 2,
        'speaking'    => 2,
    ];

    public function __construct(PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->uploadDir = rtrim($basePath, '/\\') . '/uploads/examenes';
        $this->logoPath = rtrim($basePath, '/\\') . '/src/logo2.png';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function getFasesDisponibles(): array
    {
        $fases = [];
        $tables = ['en_vocabulario', 'en_gramatica', 'en_listening', 'en_reading', 'en_writing', 'en_speaking'];
        foreach ($tables as $t) {
            try {
                $rows = $this->pdo->query("SELECT DISTINCT fase FROM {$t} WHERE id_fusion IS NULL ORDER BY fase")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rows as $f) {
                    $f = FaseHelper::normalizar((string)$f);
                    if ($f !== '') {
                        $fases[$f] = true;
                    }
                }
            } catch (PDOException $e) {
                // tabla aún no existe
            }
        }
        $keys = array_keys($fases);
        sort($keys, SORT_STRING);
        return $keys;
    }

    public function getFusionesGuardadas(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT f.id_fusion, f.nombre,
                       GROUP_CONCAT(ff.fase ORDER BY ff.fase SEPARATOR ',') AS fases
                FROM exam_fusiones f
                LEFT JOIN exam_fusion_fases ff ON ff.id_fusion = f.id_fusion
                WHERE f.area = 'ingles'
                GROUP BY f.id_fusion, f.nombre
                ORDER BY f.nombre
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * @param array{
     *   tipo?: string,
     *   fases?: list<string>,
     *   nombre?: string,
     *   id_fusion?: int,
     *   guardar_fusion?: bool,
     *   nombre_fusion?: string,
     *   id_profesor?: int|null
     * } $opts
     * @return array{
     *   id_examen: string,
     *   nombre: string,
     *   pdf_url: string,
     *   csv_url: string,
     *   hoja_url: string
     * }
     */
    public function generar(array $opts): array
    {
        $tipo = $opts['tipo'] ?? 'fase';
        $fases = array_values(array_unique(array_filter(array_map(
            fn($f) => FaseHelper::normalizar((string)$f),
            $opts['fases'] ?? []
        ), fn($f) => $f !== '')));
        $nombre = trim($opts['nombre'] ?? '');
        $idFusion = isset($opts['id_fusion']) ? (int)$opts['id_fusion'] : null;
        $guardarFusion = !empty($opts['guardar_fusion']);
        $nombreFusion = trim($opts['nombre_fusion'] ?? '');

        if ($tipo === 'fase') {
            if (count($fases) !== 1) {
                throw new \InvalidArgumentException('Seleccione exactamente una fase.');
            }
            if ($nombre === '') {
                $nombre = $fases[0];
            }
        } elseif ($tipo === 'nivel') {
            if (count($fases) !== 3) {
                throw new \InvalidArgumentException('Seleccione exactamente 3 fases para un examen de nivel.');
            }
            sort($fases, SORT_STRING);
            if ($nombre === '') {
                $nombre = 'Nivel - Fases ' . implode(', ', $fases);
            }
        } elseif ($tipo === 'fusion') {
            if ($idFusion) {
                $fases = $this->fasesDeFusion($idFusion);
                if ($nombre === '') {
                    $nombre = $this->nombreFusion($idFusion);
                }
            } elseif (count($fases) < 1) {
                throw new \InvalidArgumentException('Seleccione al menos una fase o una fusión guardada.');
            }
            if ($nombre === '' && $nombreFusion !== '') {
                $nombre = $nombreFusion;
            }
            if ($nombre === '') {
                $nombre = 'Fusión - Fases ' . implode(', ', $fases);
            }
            if ($guardarFusion && $nombreFusion !== '' && !$idFusion) {
                $idFusion = $this->crearFusion($nombreFusion, $fases, $opts['id_profesor'] ?? null);
            }
        } else {
            throw new \InvalidArgumentException('Tipo de examen no válido.');
        }

        $useFusionBank = ($tipo === 'fusion' && $idFusion && empty($opts['fases']));
        $preguntas = $this->armarPreguntas($fases, $tipo, $useFusionBank ? $idFusion : null);

        $idExamen = $this->nuevoIdExamen();
        $audioLink = $preguntas['audio_link'] ?? null;
        $audioId = $preguntas['audio_id'] ?? null;

        $pdfPath = $this->generarPdf($idExamen, $nombre, $preguntas['items'], $audioLink, $audioId);
        $csvPath = $this->generarCsv($idExamen, $preguntas['items']);

        try {
            require_once __DIR__ . '/AnswerSheetService.php';
            $sheetSvc = new AnswerSheetService($this->pdo, dirname($this->uploadDir, 2));
            $sheetSvc->generarHojaUniversal();
        } catch (\Throwable $e) {
            // hoja opcional si faltan tablas de calificación
        }

        $this->guardarExamen($idExamen, [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'fases' => $fases,
            'id_fusion' => $idFusion,
            'id_profesor' => $opts['id_profesor'] ?? null,
            'audio_link' => $audioLink,
            'pdf_path' => $pdfPath,
            'csv_path' => $csvPath,
            'preguntas' => $preguntas['items'],
        ]);

        return [
            'id_examen' => $idExamen,
            'nombre' => $nombre,
            'pdf_url' => 'php/exam/descargar.php?tipo=pdf&id=' . urlencode($idExamen),
            'csv_url' => 'php/exam/descargar.php?tipo=csv&id=' . urlencode($idExamen),
            'hoja_url' => 'php/exam/descargar.php?tipo=hoja',
        ];
    }

    private function armarPreguntas(array $fases, string $tipo, ?int $idFusion): array
    {
        $items = [];
        $n = 1;
        $fusionClause = $idFusion ? ' AND id_fusion = ' . (int)$idFusion : ' AND id_fusion IS NULL';
        $faseClause = $idFusion ? '' : FaseHelper::sqlIn($this->pdo, $fases);

        if ($tipo === 'nivel' && count($fases) === 3) {
            $dist = fn(int $total) => [
                $fases[0] => (int)floor($total / 3),
                $fases[1] => (int)floor($total / 3),
                $fases[2] => $total - 2 * (int)floor($total / 3),
            ];
            foreach (['vocabulario' => self::COUNTS['vocabulario'], 'gramatica' => self::COUNTS['gramatica']] as $sec => $cnt) {
                $porFase = $dist($cnt);
                foreach ($porFase as $fase => $cant) {
                    if ($cant < 1) {
                        continue;
                    }
                    $rows = $this->randomRows("en_{$sec}", $cant, FaseHelper::sqlEquals($this->pdo, $fase) . $fusionClause);
                    foreach ($rows as $r) {
                        $items[] = $this->mapMc($n++, $sec, $r);
                    }
                }
            }
        } else {
            foreach (['vocabulario' => self::COUNTS['vocabulario'], 'gramatica' => self::COUNTS['gramatica']] as $sec => $cnt) {
                $rows = $this->randomRows("en_{$sec}", $cnt, '1=1' . $faseClause . $fusionClause);
                foreach ($rows as $r) {
                    $items[] = $this->mapMc($n++, $sec, $r);
                }
            }
        }

        $audioData = $this->pickListeningBlock($fases, $fusionClause, $faseClause, $idFusion);
        foreach ($audioData['preguntas'] as $r) {
            $items[] = $this->mapMc($n++, 'listening', $r, $audioData['contexto']);
        }

        $readData = $this->pickReadingBlock($fases, $fusionClause, $faseClause, $idFusion);
        foreach ($readData['preguntas'] as $r) {
            $items[] = $this->mapMc($n++, 'reading', $r, $readData['contexto']);
        }

        if ($tipo === 'nivel' && count($fases) === 3) {
            $wRows = $this->randomPorFases('en_writing', self::COUNTS['writing'], $fases, $fusionClause);
            $sRows = $this->randomPorFases('en_speaking', self::COUNTS['speaking'], $fases, $fusionClause);
        } else {
            $wRows = $this->randomRows('en_writing', self::COUNTS['writing'], '1=1' . $faseClause . $fusionClause);
            $sRows = $this->randomRows('en_speaking', self::COUNTS['speaking'], '1=1' . $faseClause . $fusionClause);
        }
        foreach ($wRows as $r) {
            $items[] = $this->mapOpen($n++, 'writing', $r);
        }
        foreach ($sRows as $r) {
            $items[] = $this->mapOpen($n++, 'speaking', $r);
        }

        return [
            'items' => $items,
            'audio_link' => $audioData['link'] ?? null,
            'audio_id' => $audioData['id_audio'] ?? null,
        ];
    }

    private function randomRows(string $table, int $limit, string $where): array
    {
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY RAND() LIMIT " . (int)$limit;
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) < $limit) {
            throw new \RuntimeException("No hay suficientes preguntas en {$table} (se necesitan {$limit}, hay " . count($rows) . ').');
        }
        return $rows;
    }

    private function randomPorFases(string $table, int $total, array $fases, string $fusionClause): array
    {
        $out = [];
        $per = max(1, (int)ceil($total / count($fases)));
        foreach ($fases as $f) {
            if (count($out) >= $total) {
                break;
            }
            $need = min($per, $total - count($out));
            $rows = $this->randomRows($table, $need, FaseHelper::sqlEquals($this->pdo, $f) . $fusionClause);
            $out = array_merge($out, $rows);
        }
        return array_slice($out, 0, $total);
    }

    private function pickListeningBlock(array $fases, string $fusionClause, string $faseClause, ?int $idFusion): array
    {
        $whereAudio = '1=1' . ($idFusion ? $fusionClause : $faseClause . $fusionClause);
        $audios = $this->pdo->query("SELECT * FROM en_audios WHERE {$whereAudio} ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$audios) {
            throw new \RuntimeException('No hay audios disponibles para listening.');
        }
        $idAudio = $audios['id_audio'];
        $link = $audios['link_audio'];
        $ctx = 'Listening: ' . ($audios['nombre_audio'] ?: $idAudio);
        $wq = "id_audio = " . $this->pdo->quote($idAudio) . ($idFusion ? $fusionClause : $faseClause . $fusionClause);
        $preguntas = $this->randomRows('en_listening', self::COUNTS['listening'], $wq);
        return ['preguntas' => $preguntas, 'link' => $link, 'id_audio' => $idAudio, 'contexto' => $ctx];
    }

    private function pickReadingBlock(array $fases, string $fusionClause, string $faseClause, ?int $idFusion): array
    {
        $whereLec = '1=1' . ($idFusion ? $fusionClause : $faseClause . $fusionClause);
        $lec = $this->pdo->query("SELECT * FROM en_lecturas WHERE {$whereLec} ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$lec) {
            throw new \RuntimeException('No hay lecturas disponibles para reading.');
        }
        $idLec = $lec['id_lectura'];
        $ctx = ($lec['nombre_lectura'] ? $lec['nombre_lectura'] . "\n\n" : '') . $lec['lectura'];
        $wq = "id_lectura = " . $this->pdo->quote($idLec) . ($idFusion ? $fusionClause : $faseClause . $fusionClause);
        $preguntas = $this->randomRows('en_reading', self::COUNTS['reading'], $wq);
        return ['preguntas' => $preguntas, 'contexto' => $ctx];
    }

    private function mapMc(int $num, string $sec, array $r, ?string $contexto = null): array
    {
        return [
            'numero' => $num,
            'seccion' => $sec,
            'tipo' => 'opcion_multiple',
            'pregunta' => $r['pregunta'],
            'opcion_a' => $r['opcion_a'] ?? null,
            'opcion_b' => $r['opcion_b'] ?? null,
            'opcion_c' => $r['opcion_c'] ?? null,
            'opcion_d' => $r['opcion_d'] ?? null,
            'respuesta' => strtoupper($r['respuesta'] ?? ''),
            'contexto' => $contexto,
            'id_audio' => $r['id_audio'] ?? null,
            'id_lectura' => $r['id_lectura'] ?? null,
        ];
    }

    private function mapOpen(int $num, string $sec, array $r): array
    {
        return [
            'numero' => $num,
            'seccion' => $sec,
            'tipo' => $sec,
            'pregunta' => $r['pregunta'],
            'opcion_a' => null,
            'opcion_b' => null,
            'opcion_c' => null,
            'opcion_d' => null,
            'respuesta' => $sec === 'writing' ? 'RUBRICA_WRITING' : 'RUBRICA_SPEAKING',
            'contexto' => null,
            'id_audio' => null,
            'id_lectura' => null,
        ];
    }

    private function nuevoIdExamen(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = strlen($chars) - 1;
        for ($intento = 0; $intento < 50; $intento++) {
            $id = '';
            for ($i = 0; $i < 5; $i++) {
                $id .= $chars[random_int(0, $len)];
            }
            $stmt = $this->pdo->prepare('SELECT 1 FROM exam_generados WHERE id_examen = ? LIMIT 1');
            $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) {
                return $id;
            }
        }
        throw new \RuntimeException('No se pudo generar un código de examen único.');
    }

    private function guardarExamen(string $id, array $data): void
    {
        $idPlantel = function_exists('plantel_id_activo') ? plantel_id_activo() : 0;
        $cols = 'id_examen, area, tipo, nombre_examen, fases_usadas, id_fusion, id_profesor, audio_link, pdf_path, csv_path';
        $vals = '?, \'ingles\', ?, ?, ?, ?, ?, ?, ?, ?';
        $params = [
            $id,
            $data['tipo'],
            $data['nombre'],
            implode(',', $data['fases']),
            $data['id_fusion'],
            $data['id_profesor'],
            $data['audio_link'],
            $data['pdf_path'],
            $data['csv_path'],
        ];
        if ($idPlantel > 0 && plantel_table_exists($this->pdo, 'exam_generados')) {
            $chk = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = \'exam_generados\' AND column_name = \'id_plantel\' LIMIT 1'
            );
            $chk->execute();
            if ($chk->fetchColumn()) {
                $cols .= ', id_plantel';
                $vals .= ', ?';
                $params[] = $idPlantel;
            }
        }
        $stmt = $this->pdo->prepare("INSERT INTO exam_generados ({$cols}) VALUES ({$vals})");
        $stmt->execute($params);

        $ins = $this->pdo->prepare("
            INSERT INTO exam_generado_preguntas
            (id_examen, numero, seccion, tipo, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta, contexto, id_audio, id_lectura)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        foreach ($data['preguntas'] as $p) {
            $ins->execute([
                $id, $p['numero'], $p['seccion'], $p['tipo'], $p['pregunta'],
                $p['opcion_a'], $p['opcion_b'], $p['opcion_c'], $p['opcion_d'],
                $p['respuesta'], $p['contexto'], $p['id_audio'], $p['id_lectura'],
            ]);
        }
    }

    public function importarCsvFusion(string $tipoPregunta, int $idFusion, string $csvPath): int
    {
        $map = [
            'vocabulario' => 'en_vocabulario',
            'gramatica' => 'en_gramatica',
            'listening' => 'en_listening',
            'reading' => 'en_reading',
            'writing' => 'en_writing',
            'speaking' => 'en_speaking',
            'audios' => 'en_audios',
            'lecturas' => 'en_lecturas',
        ];
        if (!isset($map[$tipoPregunta])) {
            throw new \InvalidArgumentException('Tipo CSV no válido.');
        }
        $table = $map[$tipoPregunta];
        $fh = fopen($csvPath, 'r');
        if (!$fh) {
            throw new \RuntimeException('No se pudo leer el CSV.');
        }
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return 0;
        }
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), ''));
            $this->insertCsvRow($table, $tipoPregunta, $idFusion, $data);
            $count++;
        }
        fclose($fh);
        return $count;
    }

    private function insertCsvRow(string $table, string $tipo, int $idFusion, array $d): void
    {
        $fase = FaseHelper::normalizar((string)($d['fase'] ?? ''));
        FaseHelper::validar($fase);
        switch ($tipo) {
            case 'vocabulario':
            case 'gramatica':
                $this->pdo->prepare("INSERT INTO {$table} (fase, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta, id_fusion) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$fase, $d['pregunta'], $d['opcion_a'], $d['opcion_b'], $d['opcion_c'], $d['opcion_d'], strtoupper($d['respuesta']), $idFusion]);
                break;
            case 'audios':
                $script = trim($d['script_audio'] ?? '');
                $this->pdo->prepare("INSERT INTO en_audios (fase, id_audio, nombre_audio, link_audio, script_audio, id_fusion) VALUES (?,?,?,?,?,?)")
                    ->execute([
                        $fase,
                        $d['id_audio'],
                        $d['nombre_audio'] ?? null,
                        $d['link_audio'],
                        $script !== '' ? $script : null,
                        $idFusion,
                    ]);
                break;
            case 'lecturas':
                $this->pdo->prepare("INSERT INTO en_lecturas (fase, id_lectura, nombre_lectura, lectura, id_fusion) VALUES (?,?,?,?,?)")
                    ->execute([$fase, $d['id_lectura'], $d['nombre_lectura'] ?? null, $d['lectura'], $idFusion]);
                break;
            case 'listening':
                $this->pdo->prepare("INSERT INTO en_listening (fase, id_audio, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta, id_fusion) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$fase, $d['id_audio'], $d['pregunta'], $d['opcion_a'], $d['opcion_b'], $d['opcion_c'], $d['opcion_d'], strtoupper($d['respuesta']), $idFusion]);
                break;
            case 'reading':
                $this->pdo->prepare("INSERT INTO en_reading (fase, id_lectura, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta, id_fusion) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$fase, $d['id_lectura'], $d['pregunta'], $d['opcion_a'], $d['opcion_b'], $d['opcion_c'], $d['opcion_d'], strtoupper($d['respuesta']), $idFusion]);
                break;
            case 'writing':
                $this->pdo->prepare("INSERT INTO en_writing (fase, pregunta, id_fusion) VALUES (?,?,?)")
                    ->execute([$fase, $d['pregunta'], $idFusion]);
                break;
            case 'speaking':
                $this->pdo->prepare("INSERT INTO en_speaking (fase, pregunta, id_fusion) VALUES (?,?,?)")
                    ->execute([$fase, $d['pregunta'], $idFusion]);
                break;
        }
    }

    public function crearFusion(string $nombre, array $fases, ?int $idProfesor): int
    {
        $this->pdo->prepare("INSERT INTO exam_fusiones (area, nombre, id_profesor) VALUES ('ingles', ?, ?)")
            ->execute([$nombre, $idProfesor]);
        $id = (int)$this->pdo->lastInsertId();
        $ins = $this->pdo->prepare("INSERT INTO exam_fusion_fases (id_fusion, fase) VALUES (?, ?)");
        foreach ($fases as $f) {
            $ins->execute([$id, FaseHelper::normalizar($f)]);
        }
        return $id;
    }

    private function fasesDeFusion(int $idFusion): array
    {
        $stmt = $this->pdo->prepare("SELECT fase FROM exam_fusion_fases WHERE id_fusion = ? ORDER BY fase");
        $stmt->execute([$idFusion]);
        return array_values(array_filter(array_map(
            fn($f) => FaseHelper::normalizar((string)$f),
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        )));
    }

    private function nombreFusion(int $idFusion): string
    {
        $stmt = $this->pdo->prepare("SELECT nombre FROM exam_fusiones WHERE id_fusion = ?");
        $stmt->execute([$idFusion]);
        return (string)($stmt->fetchColumn() ?: 'Fusión');
    }

    private function generarPdf(string $idExamen, string $nombreExamen, array $preguntas, ?string $audioLink, ?string $audioId = null): string
    {
        $logoB64 = '';
        $logoFile = is_file($this->logoPath) ? $this->logoPath : dirname($this->logoPath) . '/logo.png';
        if (is_file($logoFile)) {
            $logoB64 = 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoFile));
        }
        $projectRoot = dirname(__DIR__, 2);
        $qrInfo = ExamPdfHelper::qrParaExamen($audioLink, $idExamen, $projectRoot);
        $qrDisplay = $qrInfo['src'];
        if ($qrDisplay !== '' && strpos($qrDisplay, 'data:') !== 0 && strpos($qrDisplay, 'http') !== 0) {
            $qrFile = $this->uploadDir . '/' . $qrDisplay;
            if (is_file($qrFile) && ExamPdfHelper::tieneDompdf()) {
                $qrDisplay = 'data:image/png;base64,' . base64_encode((string)file_get_contents($qrFile));
            }
        }

        require_once __DIR__ . '/ExamPrintTemplate.php';
        $html = ExamPrintTemplate::render($idExamen, $nombreExamen, $preguntas, $logoB64, $qrDisplay, $audioLink, $audioId);
        $filename = $idExamen . '.pdf';
        $fullPath = $this->uploadDir . '/' . $filename;

        $result = ExamPdfHelper::guardarExamen($html, $fullPath);
        return $this->relPathDesdeAbsoluta($result['path']);
    }

    private function generarCsv(string $idExamen, array $preguntas): string
    {
        $filename = $idExamen . '_respuestas.csv';
        $fullPath = $this->uploadDir . '/' . $filename;
        $fh = fopen($fullPath, 'w');
        fputcsv($fh, ['Id_examen', 'Question number', 'Response/Mapping', 'Point Value']);
        foreach ($preguntas as $p) {
            if (($p['tipo'] ?? '') !== 'opcion_multiple') {
                continue;
            }
            fputcsv($fh, [
                $idExamen,
                $p['numero'],
                $p['respuesta'] ?? '',
                1,
            ]);
        }
        fclose($fh);
        return 'uploads/examenes/' . $filename;
    }

    public function listarGenerados(int $limit = 50): array
    {
        try {
            $idPlantel = function_exists('plantel_id_activo') ? plantel_id_activo() : 0;
            $sql = "
                SELECT id_examen, tipo, nombre_examen, fases_usadas, pdf_path, csv_path, creado_en
                FROM exam_generados WHERE area = 'ingles'";
            if ($idPlantel > 0) {
                $sql .= ' AND (id_plantel = ? OR id_plantel IS NULL)';
            }
            $sql .= ' ORDER BY creado_en DESC LIMIT ?';
            $stmt = $this->pdo->prepare($sql);
            if ($idPlantel > 0) {
                $stmt->bindValue(1, $idPlantel, PDO::PARAM_INT);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function normalizarIdExamen(string $id): string
    {
        return strtoupper(trim($id));
    }

    public function obtenerPorId(string $id): ?array
    {
        $id = $this->normalizarIdExamen($id);
        if ($id === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('
            SELECT id_examen, area, tipo, nombre_examen, fases_usadas, id_fusion, id_profesor,
                   audio_link, pdf_path, csv_path, creado_en
            FROM exam_generados
            WHERE UPPER(TRIM(id_examen)) = ?
            LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cargarPreguntasGeneradas(string $idExamen): array
    {
        $id = $this->normalizarIdExamen($idExamen);
        $stmt = $this->pdo->prepare('
            SELECT numero, seccion, tipo, pregunta, opcion_a, opcion_b, opcion_c, opcion_d,
                   respuesta, contexto, id_audio, id_lectura
            FROM exam_generado_preguntas
            WHERE UPPER(TRIM(id_examen)) = ?
            ORDER BY numero ASC
        ');
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'numero' => (int)$r['numero'],
                'seccion' => (string)$r['seccion'],
                'tipo' => (string)$r['tipo'],
                'pregunta' => (string)$r['pregunta'],
                'opcion_a' => $r['opcion_a'],
                'opcion_b' => $r['opcion_b'],
                'opcion_c' => $r['opcion_c'],
                'opcion_d' => $r['opcion_d'],
                'respuesta' => $r['respuesta'],
                'contexto' => $r['contexto'],
                'id_audio' => $r['id_audio'],
                'id_lectura' => $r['id_lectura'],
            ];
        }
        return $items;
    }

    public function asegurarArchivoPdf(string $idExamen): string
    {
        $exam = $this->obtenerPorId($idExamen);
        if (!$exam) {
            throw new \RuntimeException('Examen no encontrado.');
        }
        $id = $this->normalizarIdExamen($exam['id_examen']);

        $rel = $this->resolverRutaRelativa($exam['pdf_path'] ?? '', $id, ['.pdf', '.html']);
        if ($rel !== null) {
            return $rel;
        }

        $preguntas = $this->cargarPreguntasGeneradas($id);
        if ($preguntas === []) {
            throw new \RuntimeException('No hay preguntas guardadas para regenerar el examen.');
        }

        $audioId = $this->audioIdDesdePreguntas($preguntas);
        $rel = $this->generarPdf(
            $id,
            (string)$exam['nombre_examen'],
            $preguntas,
            $exam['audio_link'] ?? null,
            $audioId
        );
        $this->actualizarRutaArchivo($id, 'pdf_path', $rel);
        return $rel;
    }

    public function asegurarArchivoCsv(string $idExamen): string
    {
        $exam = $this->obtenerPorId($idExamen);
        if (!$exam) {
            throw new \RuntimeException('Examen no encontrado.');
        }
        $id = $this->normalizarIdExamen($exam['id_examen']);

        $rel = $this->resolverRutaRelativa($exam['csv_path'] ?? '', $id, ['_respuestas.csv']);
        if ($rel !== null) {
            return $rel;
        }

        $preguntas = $this->cargarPreguntasGeneradas($id);
        if ($preguntas === []) {
            throw new \RuntimeException('No hay preguntas guardadas para regenerar la clave CSV.');
        }

        $rel = $this->generarCsv($id, $preguntas);
        $this->actualizarRutaArchivo($id, 'csv_path', $rel);
        return $rel;
    }

    private function resolverRutaRelativa(string $relGuardada, string $idExamen, array $sufijos): ?string
    {
        $candidatos = [];
        if ($relGuardada !== '') {
            $candidatos[] = $this->relPathDesdeAbsoluta($relGuardada);
        }
        foreach ($sufijos as $suf) {
            $candidatos[] = 'uploads/examenes/' . $idExamen . $suf;
        }
        $candidatos = array_values(array_unique($candidatos));
        foreach ($candidatos as $rel) {
            if ($this->archivoExiste($rel)) {
                return $rel;
            }
            if (preg_match('/\.pdf$/i', $rel)) {
                $alt = preg_replace('/\.pdf$/i', '.html', $rel);
                if ($alt && $this->archivoExiste($alt)) {
                    return $alt;
                }
            }
        }
        return null;
    }

    private function archivoExiste(string $rel): bool
    {
        $path = $this->rutaAbsoluta($rel);
        return $path !== '' && is_file($path);
    }

    private function rutaAbsoluta(string $rel): string
    {
        $rel = str_replace('\\', '/', trim($rel));
        if ($rel === '') {
            return '';
        }
        $root = dirname(__DIR__, 2);
        if (preg_match('#^[a-zA-Z]:/#', $rel) || (isset($rel[0]) && $rel[0] === '/')) {
            return $rel;
        }
        return $root . '/' . ltrim($rel, '/');
    }

    private function relPathDesdeAbsoluta(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        if (strpos($path, $root . '/') === 0) {
            return ltrim(substr($path, strlen($root)), '/');
        }
        if (preg_match('#uploads/examenes/.+#i', $path, $m)) {
            return $m[0];
        }
        return 'uploads/examenes/' . basename($path);
    }

    private function actualizarRutaArchivo(string $idExamen, string $columna, string $rel): void
    {
        if (!in_array($columna, ['pdf_path', 'csv_path'], true)) {
            return;
        }
        $rel = $this->relPathDesdeAbsoluta($rel);
        $stmt = $this->pdo->prepare("UPDATE exam_generados SET {$columna} = ? WHERE UPPER(TRIM(id_examen)) = ?");
        $stmt->execute([$rel, $this->normalizarIdExamen($idExamen)]);
    }

    /**
     * @param list<array<string, mixed>> $preguntas
     */
    private function audioIdDesdePreguntas(array $preguntas): ?string
    {
        foreach ($preguntas as $p) {
            if (($p['seccion'] ?? '') === 'listening' && !empty($p['id_audio'])) {
                return (string)$p['id_audio'];
            }
        }
        return null;
    }
}
