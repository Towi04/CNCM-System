<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

final class AiLogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function registrar(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tutor_ia_logs (
                id_usuario, id_conversacion, id_tutor, prompt_enviado, respuesta_recibida,
                modelo, tokens_prompt, tokens_respuesta, tokens_total, costo_estimado,
                http_code, provider, creado_en
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            (int) ($data['id_usuario'] ?? 0),
            $data['id_conversacion'] ?? null,
            $data['id_tutor'] ?? null,
            (string) ($data['prompt_enviado'] ?? ''),
            $data['respuesta_recibida'] ?? null,
            (string) ($data['modelo'] ?? ''),
            (int) ($data['tokens_prompt'] ?? 0),
            (int) ($data['tokens_respuesta'] ?? 0),
            (int) ($data['tokens_total'] ?? 0),
            (float) ($data['costo_estimado'] ?? 0),
            $data['http_code'] ?? null,
            (string) ($data['provider'] ?? 'openrouter'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
