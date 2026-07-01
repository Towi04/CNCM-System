<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

/** Búsqueda en catálogo academico_material (libros, Moodle, PDF indexados). */
final class MaterialContextRetriever
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscar(string $pregunta, ?string $especialidadTutor = null, ?int $numSemana = null, ?int $idFase = null): string
    {
        try {
            if (!$this->tablaExiste()) {
                return '';
            }

            $pagina = $this->extraerPagina($pregunta);
            $espIds = $this->especialidadIds($especialidadTutor);
            $idEsp = $espIds[0] ?? null;

            $rows = [];
            if ($pagina === null && function_exists('academico_material_buscar_semantico')) {
                try {
                    $rows = academico_material_buscar_semantico($this->pdo, $pregunta, $idEsp, 6);
                } catch (\Throwable $e) {
                    error_log('MaterialContextRetriever semántico: ' . $e->getMessage());
                    $rows = [];
                }
            }
            if ($rows === []) {
                $rows = $this->consultar($pregunta, $espIds, $numSemana, $idFase, $pagina);
            } elseif ($pagina !== null) {
                $extra = $this->consultar($pregunta, $espIds, $numSemana, $idFase, $pagina);
                $seen = array_flip(array_map(static fn ($r) => (int) ($r['id_material'] ?? 0), $rows));
                foreach ($extra as $e) {
                    $id = (int) ($e['id_material'] ?? 0);
                    if ($id > 0 && !isset($seen[$id])) {
                        $rows[] = $e;
                    }
                }
            }

            if ($rows === []) {
                return '';
            }

            $bloques = [];
            foreach ($rows as $r) {
                $tipo = (string) ($r['tipo'] ?? 'material');
                $titulo = (string) ($r['titulo'] ?? '');
                $lineas = ['[MATERIAL INSTITUCIONAL — ' . strtoupper($tipo) . '] ' . $titulo];
                if (!empty($r['descripcion'])) {
                    $lineas[] = 'Descripción: ' . $this->truncar((string) $r['descripcion'], 400);
                }
                if (!empty($r['pagina_inicio'])) {
                    $pag = 'Página(s) ' . $r['pagina_inicio'];
                    if (!empty($r['pagina_fin']) && (int) $r['pagina_fin'] !== (int) $r['pagina_inicio']) {
                        $pag .= '-' . $r['pagina_fin'];
                    }
                    $lineas[] = $pag;
                }
                if (!empty($r['semana'])) {
                    $lineas[] = 'Semana temario: ' . $r['semana'];
                }
                if (!empty($r['moodle_url'])) {
                    $lineas[] = 'Actividad Moodle: ' . $r['moodle_url'];
                } elseif (!empty($r['moodle_course_id'])) {
                    $lineas[] = 'Moodle course id: ' . $r['moodle_course_id'] . ', cm id: ' . ($r['moodle_cm_id'] ?? '');
                }
                if (!empty($r['ruta_archivo'])) {
                    $lineas[] = 'Archivo: ' . $r['ruta_archivo'];
                }
                if (!empty($r['contenido_texto'])) {
                    $lineas[] = 'Contenido:' . "\n" . $this->truncar((string) $r['contenido_texto'], 1200);
                }
                $bloques[] = implode("\n", $lineas);
            }

            return "MATERIALES CNCM (libros, workbook, Moodle):\n\n" . implode("\n\n---\n\n", $bloques);
        } catch (\Throwable $e) {
            error_log('MaterialContextRetriever: ' . $e->getMessage());

            return '';
        }
    }

    private function tablaExiste(): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $st->execute(['academico_material']);

        return (bool) $st->fetchColumn();
    }

    /** @param list<int> $espIds @return list<array<string, mixed>> */
    private function consultar(string $pregunta, array $espIds, ?int $numSemana, ?int $idFase, ?int $pagina): array
    {
        $where = ['m.activo = 1'];
        $params = [];
        $from = 'academico_material m';
        if ($this->columnaExiste('academico_material', 'id_version') && $this->tablaLibroVersionExiste()) {
            $from .= ' LEFT JOIN academico_libro_version v ON v.id_version = m.id_version';
            $where[] = '(m.id_version IS NULL OR v.activo_rag = 1)';
        }

        if ($espIds !== []) {
            $in = implode(',', array_fill(0, count($espIds), '?'));
            $where[] = "(m.id_especialidad IN ($in) OR m.id_especialidad IS NULL)";
            $params = array_merge($params, $espIds);
        }

        if ($idFase !== null && $idFase > 0) {
            $where[] = '(m.id_fase = ? OR m.id_fase IS NULL)';
            $params[] = $idFase;
        }

        if ($numSemana !== null && $numSemana > 0) {
            $where[] = '(m.semana = ? OR m.semana IS NULL)';
            $params[] = $numSemana;
        }

        if ($pagina !== null && $pagina > 0) {
            $where[] = '(m.pagina_inicio IS NULL OR (m.pagina_inicio <= ? AND (m.pagina_fin IS NULL OR m.pagina_fin >= ?)))';
            $params[] = $pagina;
            $params[] = $pagina;
        }

        $tokens = preg_split('/\s+/u', mb_strtolower(trim($pregunta)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $likeParts = [];
        foreach (array_slice($tokens, 0, 5) as $t) {
            if (mb_strlen($t) < 4) {
                continue;
            }
            $likeParts[] = '(m.titulo LIKE ? OR m.descripcion LIKE ? OR m.contenido_texto LIKE ? OR m.etiquetas LIKE ?)';
            $p = '%' . $t . '%';
            array_push($params, $p, $p, $p, $p);
        }

        $sql = 'SELECT m.* FROM ' . $from . ' WHERE ' . implode(' AND ', $where);
        if ($likeParts !== [] && $pagina === null && $numSemana === null) {
            $sql .= ' AND (' . implode(' OR ', $likeParts) . ')';
        }
        $sql .= ' ORDER BY
            (m.id_especialidad IS NOT NULL) DESC,
            (m.semana IS NOT NULL) DESC,
            (m.pagina_inicio IS NOT NULL) DESC
            LIMIT 8';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    private function tablaLibroVersionExiste(): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $st->execute(['academico_libro_version']);

        return (bool) $st->fetchColumn();
    }

    private function extraerPagina(string $pregunta): ?int
    {
        if (preg_match('/\bp[aá]g(?:ina|\.)?\s*(\d{1,4})\b/ui', $pregunta, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bpage\s+(\d{1,4})\b/i', $pregunta, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** @return list<int> */
    private function especialidadIds(?string $especialidad): array
    {
        $map = [
            'ingles' => ["modalidad IN ('regular','extensivo')", "clave LIKE 'ING%'"],
            'computacion' => ["clave LIKE 'COMP%'"],
            'preparatoria' => ["modalidad IN ('prep_abierta','prep_escolarizada')"],
            'kids' => ["modalidad = 'kids'"],
        ];
        $sql = 'SELECT id_especialidad FROM especialidades WHERE activo = 1';
        if ($especialidad !== null && $especialidad !== '' && $especialidad !== 'general' && isset($map[$especialidad])) {
            $sql .= ' AND (' . implode(' OR ', $map[$especialidad]) . ')';
        }
        $sql .= ' LIMIT 40';

        return array_map('intval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max - 1) . '…';
    }
}
