<?php

namespace HayExam;

use PDO;
use PDOException;

class BancoInglesService
{
    public const TIPOS = [
        'vocabulario' => [
            'tabla' => 'en_vocabulario',
            'label' => 'Vocabulario',
            'csv_headers' => ['fase', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
            'csv_ejemplo' => ['A1 1-4', 'What is the opposite of "hot"?', 'cold', 'warm', 'cool', 'heat', 'A'],
            'campos' => ['fase', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
        ],
        'gramatica' => [
            'tabla' => 'en_gramatica',
            'label' => 'Gramática',
            'csv_headers' => ['fase', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
            'csv_ejemplo' => ['A1 1-4', 'She ___ coffee every morning.', 'drinks', 'drink', 'drinking', 'drank', 'A'],
            'campos' => ['fase', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
        ],
        'audios' => [
            'tabla' => 'en_audios',
            'label' => 'Audios',
            'csv_headers' => ['fase', 'id_audio', 'nombre_audio', 'link_audio', 'script_audio'],
            'csv_ejemplo' => ['A1 1-4', 'AUD-F1-01', 'Daily routine', 'https://ejemplo.com/audio.mp3', 'She wakes up at seven o\'clock every morning...'],
            'campos' => ['fase', 'id_audio', 'nombre_audio', 'link_audio', 'script_audio'],
        ],
        'lecturas' => [
            'tabla' => 'en_lecturas',
            'label' => 'Lecturas',
            'csv_headers' => ['fase', 'id_lectura', 'nombre_lectura', 'lectura'],
            'csv_ejemplo' => ['B1+ 5-8', 'LEC-F1-01', 'The Park', 'Last Sunday, Maria went to the park with her family...'],
            'campos' => ['fase', 'id_lectura', 'nombre_lectura', 'lectura'],
        ],
        'listening' => [
            'tabla' => 'en_listening',
            'label' => 'Listening',
            'csv_headers' => ['fase', 'id_audio', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
            'csv_ejemplo' => ['A1 1-4', 'AUD-F1-01', 'What time does she wake up?', '6 am', '7 am', '8 am', '9 am', 'B'],
            'campos' => ['fase', 'id_audio', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
        ],
        'reading' => [
            'tabla' => 'en_reading',
            'label' => 'Reading',
            'csv_headers' => ['fase', 'id_lectura', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
            'csv_ejemplo' => ['B1+ 5-8', 'LEC-F1-01', 'Who went to the park?', 'Maria and her family', 'Maria alone', 'Her teacher', 'Her classmates', 'A'],
            'campos' => ['fase', 'id_lectura', 'pregunta', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta'],
        ],
        'writing' => [
            'tabla' => 'en_writing',
            'label' => 'Writing',
            'csv_headers' => ['fase', 'pregunta'],
            'csv_ejemplo' => ['A1 1-4', 'Write a short paragraph (60-80 words) about your favorite hobby.'],
            'campos' => ['fase', 'pregunta'],
        ],
        'speaking' => [
            'tabla' => 'en_speaking',
            'label' => 'Speaking',
            'csv_headers' => ['fase', 'pregunta'],
            'csv_ejemplo' => ['A1 1-4', 'Introduce yourself: name, age, where you live, and one hobby.'],
            'campos' => ['fase', 'pregunta'],
        ],
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function tipoValido(string $tipo): bool
    {
        return isset(self::TIPOS[$tipo]);
    }

    public function listar(string $tipo, ?string $fase = null, int $page = 1, int $perPage = 30): array
    {
        $meta = self::TIPOS[$tipo];
        $table = $meta['tabla'];
        $where = 'id_fusion IS NULL';
        $params = [];
        if ($fase !== null && FaseHelper::normalizar($fase) !== '') {
            $where .= ' AND fase = ?';
            $params[] = FaseHelper::normalizar($fase);
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY fase ASC, id DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function obtener(string $tipo, int $id): ?array
    {
        $table = self::TIPOS[$tipo]['tabla'];
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ? AND id_fusion IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(string $tipo, array $data): int
    {
        if (!$this->tipoValido($tipo)) {
            throw new \InvalidArgumentException('Tipo no válido.');
        }
        $this->insertRow($tipo, $data, null);
        return (int)$this->pdo->lastInsertId();
    }

    public function actualizar(string $tipo, int $id, array $data): void
    {
        if (!$this->obtener($tipo, $id)) {
            throw new \RuntimeException('Registro no encontrado.');
        }
        $sets = [];
        $vals = [];
        foreach (self::TIPOS[$tipo]['campos'] as $campo) {
            if (!array_key_exists($campo, $data)) {
                continue;
            }
            $val = $data[$campo];
            if ($campo === 'fase') {
                $val = FaseHelper::normalizar((string)$val);
                FaseHelper::validar($val);
            }
            if ($campo === 'respuesta' && $val !== null && $val !== '') {
                $val = strtoupper((string)$val);
            }
            $sets[] = "{$campo} = ?";
            $vals[] = $val;
        }
        if (empty($sets)) {
            throw new \InvalidArgumentException('No hay datos para actualizar.');
        }
        $vals[] = $id;
        $table = self::TIPOS[$tipo]['tabla'];
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = ? AND id_fusion IS NULL";
        $this->pdo->prepare($sql)->execute($vals);
    }

    public function eliminar(string $tipo, int $id): void
    {
        $table = self::TIPOS[$tipo]['tabla'];
        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = ? AND id_fusion IS NULL");
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('No se pudo eliminar el registro.');
        }
    }

    public function importarCsv(string $tipo, string $csvPath): int
    {
        $meta = self::TIPOS[$tipo];
        $fh = fopen($csvPath, 'r');
        if (!$fh) {
            throw new \RuntimeException('No se pudo leer el archivo CSV.');
        }
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            throw new \RuntimeException('El CSV está vacío.');
        }
        $header = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), $header);
        $expected = $meta['csv_headers'];
        $missing = array_diff($expected, $header);
        if (!empty($missing)) {
            fclose($fh);
            throw new \InvalidArgumentException(
                'Columnas faltantes en el CSV: ' . implode(', ', $missing) .
                '. Use el archivo de ejemplo para este tipo.'
            );
        }

        $count = 0;
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), ''));
            try {
                $this->insertRow($tipo, $data, null);
                $count++;
            } catch (\Throwable $e) {
                $errors[] = "Línea {$line}: " . $e->getMessage();
            }
        }
        fclose($fh);

        if ($count === 0 && !empty($errors)) {
            throw new \RuntimeException(implode("\n", array_slice($errors, 0, 5)));
        }

        return $count;
    }

    public function insertRow(string $tipo, array $d, ?int $idFusion): void
    {
        $fase = FaseHelper::normalizar((string)($d['fase'] ?? ''));
        FaseHelper::validar($fase);
        switch ($tipo) {
            case 'vocabulario':
            case 'gramatica':
                $table = self::TIPOS[$tipo]['tabla'];
                $this->pdo->prepare("INSERT INTO {$table} (fase, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta, id_fusion) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([
                        $fase, trim($d['pregunta']), trim($d['opcion_a']), trim($d['opcion_b']),
                        trim($d['opcion_c']), trim($d['opcion_d']), strtoupper(trim($d['respuesta'])), $idFusion,
                    ]);
                break;
            case 'audios':
                if (trim($d['id_audio'] ?? '') === '' || trim($d['link_audio'] ?? '') === '') {
                    throw new \InvalidArgumentException('id_audio y link_audio son obligatorios.');
                }
                $script = trim($d['script_audio'] ?? '');
                $this->pdo->prepare("INSERT INTO en_audios (fase, id_audio, nombre_audio, link_audio, script_audio, id_fusion) VALUES (?,?,?,?,?,?)")
                    ->execute([
                        $fase,
                        trim($d['id_audio']),
                        trim($d['nombre_audio'] ?? '') ?: null,
                        trim($d['link_audio']),
                        $script !== '' ? $script : null,
                        $idFusion,
                    ]);
                break;
            case 'lecturas':
                if (trim($d['id_lectura'] ?? '') === '' || trim($d['lectura'] ?? '') === '') {
                    throw new \InvalidArgumentException('id_lectura y lectura son obligatorios.');
                }
                $this->pdo->prepare("INSERT INTO en_lecturas (fase, id_lectura, nombre_lectura, lectura, id_fusion) VALUES (?,?,?,?,?)")
                    ->execute([$fase, trim($d['id_lectura']), trim($d['nombre_lectura'] ?? '') ?: null, trim($d['lectura']), $idFusion]);
                break;
            case 'listening':
                $this->pdo->prepare("INSERT INTO en_listening (fase, id_audio, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta, id_fusion) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        $fase, trim($d['id_audio']), trim($d['pregunta']),
                        trim($d['opcion_a']), trim($d['opcion_b']), trim($d['opcion_c']), trim($d['opcion_d']),
                        strtoupper(trim($d['respuesta'])), $idFusion,
                    ]);
                break;
            case 'reading':
                $this->pdo->prepare("INSERT INTO en_reading (fase, id_lectura, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta, id_fusion) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        $fase, trim($d['id_lectura']), trim($d['pregunta']),
                        trim($d['opcion_a']), trim($d['opcion_b']), trim($d['opcion_c']), trim($d['opcion_d']),
                        strtoupper(trim($d['respuesta'])), $idFusion,
                    ]);
                break;
            case 'writing':
            case 'speaking':
                $table = self::TIPOS[$tipo]['tabla'];
                if (trim($d['pregunta'] ?? '') === '') {
                    throw new \InvalidArgumentException('La pregunta es obligatoria.');
                }
                $this->pdo->prepare("INSERT INTO {$table} (fase, pregunta, id_fusion) VALUES (?,?,?)")
                    ->execute([$fase, trim($d['pregunta']), $idFusion]);
                break;
            default:
                throw new \InvalidArgumentException('Tipo no válido.');
        }
    }

    public function generarCsvEjemplo(string $tipo): string
    {
        $meta = self::TIPOS[$tipo];
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $meta['csv_headers']);
        fputcsv($fh, $meta['csv_ejemplo']);
        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);
        return $content;
    }

    public function getFasesDisponibles(string $tipo): array
    {
        try {
            $table = self::TIPOS[$tipo]['tabla'];
            $stmt = $this->pdo->query("SELECT DISTINCT fase FROM {$table} WHERE id_fusion IS NULL ORDER BY fase");
            return array_values(array_filter(array_map(
                fn($f) => FaseHelper::normalizar((string)$f),
                $stmt->fetchAll(PDO::FETCH_COLUMN)
            )));
        } catch (PDOException $e) {
            return [];
        }
    }
}
