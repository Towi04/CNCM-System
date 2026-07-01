<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

final class MessageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function add(int $conversationId, string $role, string $mensaje, int $tokens = 0, ?array $metadata = null): int
    {
        $metaJson = $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        if ($this->columnaMetadataExiste()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tutor_mensajes (id_conversacion, `role`, mensaje, tokens, metadata_json, creado_en)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$conversationId, $role, $mensaje, max(0, $tokens), $metaJson]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tutor_mensajes (id_conversacion, `role`, mensaje, tokens, creado_en)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$conversationId, $role, $mensaje, max(0, $tokens)]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function columnaMetadataExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
        );
        $st->execute(['tutor_mensajes', 'metadata_json']);
        $cache = (bool) $st->fetchColumn();

        return $cache;
    }

    /** @return list<array<string, mixed>> */
    public function listByConversation(int $conversationId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_mensaje, `role`, mensaje, tokens, creado_en
             FROM tutor_mensajes
             WHERE id_conversacion = ? AND `role` IN (\'user\', \'assistant\')
             ORDER BY id_mensaje ASC
             LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute([$conversationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{role:string,content:string}> */
    public function chatHistory(int $conversationId, int $limit = 20): array
    {
        $rows = $this->listByConversation($conversationId, $limit);
        $out = [];
        foreach ($rows as $row) {
            $role = (string) ($row['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $out[] = [
                'role' => $role,
                'content' => (string) ($row['mensaje'] ?? ''),
            ];
        }

        return $out;
    }
}
