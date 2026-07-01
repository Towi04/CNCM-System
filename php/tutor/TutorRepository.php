<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

final class TutorRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listActivos(?array $allowedIds = null, ?string $especialidad = null): array
    {
        $sql = 'SELECT id_tutor, nombre, descripcion, especialidad, avatar_url, orden
                FROM tutor_tutores WHERE activo = 1';
        $params = [];
        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return [];
            }
            $in = implode(',', array_fill(0, count($allowedIds), '?'));
            $sql .= " AND id_tutor IN ($in)";
            $params = array_merge($params, $allowedIds);
        }
        if ($especialidad !== null && $especialidad !== '') {
            $sql .= ' AND (especialidad = ? OR especialidad = \'general\')';
            $params[] = $especialidad;
        }
        $sql .= ' ORDER BY orden ASC, nombre ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tutor_tutores WHERE id_tutor = ? AND activo = 1 LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countActivos(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM tutor_tutores WHERE activo = 1')->fetchColumn();
    }
}
