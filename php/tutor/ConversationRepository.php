<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

final class ConversationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId, int $tutorId, ?string $titulo = null, string $origen = 'hay'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tutor_conversaciones (id_usuario, id_tutor, titulo, origen, creado_en, actualizado_en)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$userId, $tutorId, $titulo, $origen]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Elimina conversaciones sin mensajes del usuario (borradores al seleccionar tutor). */
    public function purgeEmpty(int $userId): void
    {
        if (!$this->columnaArchivadaExiste()) {
            $this->pdo->prepare(
                'DELETE c FROM tutor_conversaciones c
                 WHERE c.id_usuario = ?
                   AND NOT EXISTS (
                     SELECT 1 FROM tutor_mensajes m
                     WHERE m.id_conversacion = c.id_conversacion AND m.`role` = \'user\'
                   )'
            )->execute([$userId]);

            return;
        }
        $this->pdo->prepare(
            'DELETE c FROM tutor_conversaciones c
             WHERE c.id_usuario = ? AND COALESCE(c.archivada, 0) = 0
               AND NOT EXISTS (
                 SELECT 1 FROM tutor_mensajes m
                 WHERE m.id_conversacion = c.id_conversacion AND m.`role` = \'user\'
               )'
        )->execute([$userId]);
    }

    /** @return list<array<string, mixed>> */
    public function listByUser(int $userId, int $limit = 30, bool $soloArchivadas = false): array
    {
        $archivadaClause = $this->columnaArchivadaExiste()
            ? (' AND COALESCE(c.archivada, 0) = ' . ($soloArchivadas ? '1' : '0'))
            : ($soloArchivadas ? ' AND 1=0' : '');

        $stmt = $this->pdo->prepare(
            'SELECT c.id_conversacion, c.id_tutor, c.titulo, c.creado_en, c.actualizado_en,
                    t.nombre AS tutor_nombre, t.especialidad,
                    (SELECT m.mensaje FROM tutor_mensajes m
                     WHERE m.id_conversacion = c.id_conversacion AND m.`role` = \'user\'
                     ORDER BY m.id_mensaje DESC LIMIT 1) AS ultimo_mensaje,
                    (SELECT COUNT(*) FROM tutor_mensajes m
                     WHERE m.id_conversacion = c.id_conversacion AND m.`role` = \'user\') AS num_mensajes
             FROM tutor_conversaciones c
             INNER JOIN tutor_tutores t ON t.id_tutor = c.id_tutor
             WHERE c.id_usuario = ?' . $archivadaClause . '
               AND EXISTS (
                 SELECT 1 FROM tutor_mensajes m
                 WHERE m.id_conversacion = c.id_conversacion AND m.`role` = \'user\'
               )
             ORDER BY c.actualizado_en DESC, c.id_conversacion DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function archivar(int $conversationId, int $userId): bool
    {
        if (!$this->columnaArchivadaExiste()) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE tutor_conversaciones SET archivada = 1, actualizado_en = NOW()
             WHERE id_conversacion = ? AND id_usuario = ?'
        );
        $st->execute([$conversationId, $userId]);

        return $st->rowCount() > 0;
    }

    private function columnaArchivadaExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
        );
        $st->execute(['tutor_conversaciones', 'archivada']);
        $cache = (bool) $st->fetchColumn();

        return $cache;
    }

    public function findForUser(int $conversationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, t.nombre AS tutor_nombre, t.especialidad, t.instrucciones
             FROM tutor_conversaciones c
             INNER JOIN tutor_tutores t ON t.id_tutor = c.id_tutor
             WHERE c.id_conversacion = ? AND c.id_usuario = ?
             LIMIT 1'
        );
        $stmt->execute([$conversationId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function touch(int $conversationId): void
    {
        $this->pdo->prepare('UPDATE tutor_conversaciones SET actualizado_en = NOW() WHERE id_conversacion = ?')
            ->execute([$conversationId]);
    }

    public function updateTitulo(int $conversationId, string $titulo): void
    {
        $this->pdo->prepare('UPDATE tutor_conversaciones SET titulo = ?, actualizado_en = NOW() WHERE id_conversacion = ?')
            ->execute([mb_substr($titulo, 0, 200), $conversationId]);
    }
}
