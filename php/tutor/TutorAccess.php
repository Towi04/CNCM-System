<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

/** Reglas de acceso a tutores por rol y grupo. */
final class TutorAccess
{
    /** Roles que solo ven al Assistant (general). */
    private const ROLES_SOLO_ASISTENTE = ['asesor', 'gerente', 'recepcion', 'caja'];

    /** Roles con acceso a todos los tutores. */
    private const ROLES_TODOS_TUTORES = ['supervisor', 'admin', 'director', 'coordinador', 'coordinacion'];

    public function __construct(private PDO $pdo)
    {
    }

    /** null = todos los tutores; array = IDs permitidos. */
    public function idsPermitidos(int $userId): ?array
    {
        if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
            return null;
        }

        $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';

        if (in_array($rol, self::ROLES_TODOS_TUTORES, true)) {
            return null;
        }

        if ($rol === 'alumno') {
            return $this->idsParaAlumno($userId);
        }

        if ($rol === 'profesor') {
            return $this->idsParaProfesor($userId);
        }

        if (in_array($rol, self::ROLES_SOLO_ASISTENTE, true)) {
            return $this->idsSoloAsistente();
        }

        return $this->idsSoloAsistente();
    }

    public function puedeUsar(int $userId): bool
    {
        if (empty($userId)) {
            return false;
        }
        $ids = $this->idsPermitidos($userId);

        return $ids === null || $ids !== [];
    }

    public function puedeTutor(int $userId, int $tutorId): bool
    {
        $ids = $this->idsPermitidos($userId);
        if ($ids === null) {
            return true;
        }

        return in_array($tutorId, $ids, true);
    }

    /** @return list<int> */
    private function idsParaAlumno(int $userId): array
    {
        try {
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
                return [];
            }

            if (function_exists('usuario_alumno_puede_tutor') && !usuario_alumno_puede_tutor($this->pdo, $idAlumno)) {
                return [];
            }

            $this->sincronizarTutoresGruposAlumno($idAlumno);

            $st = $this->pdo->prepare(
                'SELECT DISTINCT g.id_tutor
                 FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.estado = \'activo\'
                 INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
                 WHERE ag.id_alumno = ? AND ag.activo = 1 AND g.id_tutor IS NOT NULL'
            );
            $st->execute([$idAlumno]);

            return array_values(array_filter(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        } catch (\Throwable $e) {
            error_log('TutorAccess idsParaAlumno: ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<int> */
    private function idsParaProfesor(int $userId): array
    {
        $idPlantel = function_exists('plantel_scope_id') ? plantel_scope_id($this->pdo) : 0;
        $sql = 'SELECT DISTINCT g.id_especialidad FROM grupos g WHERE g.id_profesor = ? AND g.id_especialidad IS NOT NULL';
        $params = [$userId];
        if ($idPlantel > 0) {
            $sql .= ' AND g.id_plantel = ?';
            $params[] = $idPlantel;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $espIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $out = [];
        foreach ($espIds as $idEsp) {
            $idTutor = self::resolverTutorParaEspecialidad($this->pdo, $idEsp);
            if ($idTutor > 0) {
                $out[$idTutor] = $idTutor;
            }
        }

        return array_values($out);
    }

    /** @return list<int> */
    private function idsSoloAsistente(): array
    {
        $st = $this->pdo->query(
            "SELECT id_tutor FROM tutor_tutores WHERE activo = 1 AND especialidad = 'general' ORDER BY orden LIMIT 1"
        );
        $id = (int) ($st->fetchColumn() ?: 0);

        return $id > 0 ? [$id] : [];
    }

    private function sincronizarTutoresGruposAlumno(int $idAlumno): void
    {
        $st = $this->pdo->prepare(
            'SELECT g.id_grupo FROM alumno_grupos ag
             INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
             WHERE ag.id_alumno = ? AND ag.activo = 1'
        );
        $st->execute([$idAlumno]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $idGrupo) {
            self::asignarTutorGrupo($this->pdo, (int) $idGrupo);
        }
    }

    public static function slugDesdeEspecialidad(array $esp): string
    {
        $clave = strtoupper(trim((string) ($esp['clave'] ?? '')));
        $modalidad = (string) ($esp['modalidad'] ?? '');

        if (str_starts_with($clave, 'COMP')) {
            return 'computacion';
        }
        if (in_array($modalidad, ['prep_abierta', 'prep_escolarizada'], true)) {
            return 'preparatoria';
        }
        if ($modalidad === 'kids') {
            return str_starts_with($clave, 'COMP') ? 'computacion' : 'ingles';
        }
        if (str_starts_with($clave, 'ING')) {
            return 'ingles';
        }

        return 'general';
    }

    public static function resolverTutorParaEspecialidad(PDO $pdo, int $idEsp): int
    {
        if ($idEsp <= 0) {
            return 0;
        }
        $st = $pdo->prepare('SELECT clave, modalidad FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $st->execute([$idEsp]);
        $esp = $st->fetch(PDO::FETCH_ASSOC);
        if (!$esp) {
            return 0;
        }
        $slug = self::slugDesdeEspecialidad($esp);
        $t = $pdo->prepare(
            'SELECT id_tutor FROM tutor_tutores WHERE activo = 1 AND especialidad = ? ORDER BY orden ASC LIMIT 1'
        );
        $t->execute([$slug]);
        $id = (int) ($t->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        $t = $pdo->query(
            "SELECT id_tutor FROM tutor_tutores WHERE activo = 1 AND especialidad = 'general' ORDER BY orden LIMIT 1"
        );

        return (int) ($t->fetchColumn() ?: 0);
    }

    public static function asignarTutorGrupo(PDO $pdo, int $idGrupo): void
    {
        if ($idGrupo <= 0) {
            return;
        }
        $st = $pdo->prepare('SELECT id_especialidad, id_tutor FROM grupos WHERE id_grupo = ? LIMIT 1');
        $st->execute([$idGrupo]);
        $g = $st->fetch(PDO::FETCH_ASSOC);
        if (!$g || empty($g['id_especialidad'])) {
            return;
        }
        $idTutor = self::resolverTutorParaEspecialidad($pdo, (int) $g['id_especialidad']);
        if ($idTutor <= 0) {
            return;
        }
        if ((int) ($g['id_tutor'] ?? 0) === $idTutor) {
            return;
        }
        $pdo->prepare('UPDATE grupos SET id_tutor = ? WHERE id_grupo = ?')->execute([$idTutor, $idGrupo]);
    }

    /** Asigna tutor a todos los grupos que aún no lo tienen. */
    public static function backfillGrupos(PDO $pdo): void
    {
        $rows = $pdo->query(
            'SELECT id_grupo FROM grupos WHERE id_especialidad IS NOT NULL AND (id_tutor IS NULL OR id_tutor = 0) LIMIT 500'
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($rows as $idGrupo) {
            self::asignarTutorGrupo($pdo, (int) $idGrupo);
        }
    }
}
