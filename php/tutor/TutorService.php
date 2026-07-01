<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

final class TutorService
{
    private TutorRepository $tutors;
    private ConversationRepository $conversations;
    private MessageRepository $messages;
    private AIService $ai;
    private TutorAccess $access;

    public function __construct(private PDO $pdo)
    {
        $context = new AcademicContextRetriever($pdo);
        $this->tutors = new TutorRepository($pdo);
        $this->conversations = new ConversationRepository($pdo);
        $this->messages = new MessageRepository($pdo);
        $this->ai = new AIService($context, new AiLogRepository($pdo), $pdo);
        $this->access = new TutorAccess($pdo);
    }

    /** @return list<array<string, mixed>> */
    public function listTutores(int $userId, ?string $especialidad = null): array
    {
        return $this->tutors->listActivos($this->access->idsPermitidos($userId), $especialidad);
    }

    /** @return list<array<string, mixed>> */
    public function listConversaciones(int $userId, bool $archivadas = false): array
    {
        $this->conversations->purgeEmpty($userId);
        $all = $this->conversations->listByUser($userId, 30, $archivadas);
        $allowed = $this->access->idsPermitidos($userId);
        if ($allowed === null) {
            return $all;
        }

        return array_values(array_filter($all, static function ($c) use ($allowed) {
            return in_array((int) ($c['id_tutor'] ?? 0), $allowed, true);
        }));
    }

    public function archivarConversacion(int $userId, int $conversationId): array
    {
        $conv = $this->conversations->findForUser($conversationId, $userId);
        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversación no encontrada'];
        }
        if (!$this->access->puedeTutor($userId, (int) ($conv['id_tutor'] ?? 0))) {
            return ['ok' => false, 'message' => 'Sin permiso'];
        }
        if (!$this->conversations->archivar($conversationId, $userId)) {
            return ['ok' => false, 'message' => 'No se pudo archivar'];
        }

        return ['ok' => true, 'message' => 'Conversación archivada'];
    }

    public function crearConversacion(int $userId, int $tutorId): array
    {
        if (!$this->access->puedeTutor($userId, $tutorId)) {
            return ['ok' => false, 'message' => 'No tiene permiso para usar este tutor'];
        }

        $tutor = $this->tutors->findById($tutorId);
        if (!$tutor) {
            return ['ok' => false, 'message' => 'Tutor no encontrado'];
        }

        $id = $this->conversations->create($userId, $tutorId, null);

        return [
            'ok' => true,
            'id_conversacion' => $id,
            'tutor' => [
                'id_tutor' => (int) $tutor['id_tutor'],
                'nombre' => $tutor['nombre'],
                'especialidad' => $tutor['especialidad'],
            ],
        ];
    }

    public function obtenerConversacion(int $userId, int $conversationId): array
    {
        $conv = $this->conversations->findForUser($conversationId, $userId);
        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversación no encontrada'];
        }

        return [
            'ok' => true,
            'conversacion' => $conv,
            'mensajes' => $this->messages->listByConversation($conversationId),
        ];
    }

    public function enviarPregunta(int $userId, int $conversationId, string $pregunta, ?int $tutorIdNuevo = null): array
    {
        $pregunta = trim($pregunta);
        if ($pregunta === '') {
            return ['ok' => false, 'message' => 'Escriba una pregunta'];
        }
        if (mb_strlen($pregunta) > 4000) {
            return ['ok' => false, 'message' => 'La pregunta es demasiado larga (máx. 4000 caracteres)'];
        }

        if ($conversationId <= 0 && $tutorIdNuevo !== null && $tutorIdNuevo > 0) {
            $crear = $this->crearConversacion($userId, $tutorIdNuevo);
            if (!$crear['ok']) {
                return $crear;
            }
            $conversationId = (int) ($crear['id_conversacion'] ?? 0);
        }

        if ($conversationId <= 0) {
            return ['ok' => false, 'message' => 'Seleccione un tutor para iniciar'];
        }

        $conv = $this->conversations->findForUser($conversationId, $userId);
        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversación no encontrada'];
        }

        if (!$this->access->puedeTutor($userId, (int) ($conv['id_tutor'] ?? 0))) {
            return ['ok' => false, 'message' => 'Sin permiso para esta conversación'];
        }

        if (!function_exists('hay_ai_configured') || !hay_ai_configured()) {
            return ['ok' => false, 'message' => 'IA no configurada. Revise OPENROUTER_API_KEY en config.local.php'];
        }

        $tutorId = (int) $conv['id_tutor'];
        $especialidad = (string) ($conv['especialidad'] ?? 'general');
        $systemPrompt = (string) ($conv['instrucciones'] ?? '');

        $historial = $this->messages->chatHistory($conversationId, 20);
        $contexto = $this->ai->recuperarContexto($pregunta, $especialidad, $userId);

        $tokensUser = $this->ai->calcularTokens($pregunta);
        $this->messages->add($conversationId, 'user', $pregunta, $tokensUser);

        $res = $this->ai->enviarMensaje(
            $systemPrompt,
            $contexto,
            $pregunta,
            $historial,
            $userId,
            $conversationId,
            $tutorId,
        );

        if (!$res['ok']) {
            return [
                'ok' => false,
                'message' => $res['message'] ?? 'Error al generar respuesta',
                'hint' => $res['hint'] ?? null,
            ];
        }

        $respuesta = (string) ($res['text'] ?? '');
        $tokensAsst = (int) ($res['tokens_respuesta'] ?? $this->ai->calcularTokens($respuesta));
        $msgId = $this->messages->add($conversationId, 'assistant', $respuesta, $tokensAsst, [
            'model' => $res['model'] ?? null,
            'contexto_chars' => mb_strlen($contexto),
        ]);

        if (empty($conv['titulo']) || ($conv['titulo'] ?? '') === 'Nueva conversación') {
            $titulo = mb_substr($pregunta, 0, 80);
            $this->conversations->updateTitulo($conversationId, $titulo);
        } else {
            $this->conversations->touch($conversationId);
        }

        return [
            'ok' => true,
            'id_conversacion' => $conversationId,
            'id_mensaje' => $msgId,
            'respuesta' => $respuesta,
            'model' => $res['model'] ?? null,
            'tokens' => $res['tokens_total'] ?? 0,
            'contexto_encontrado' => mb_strlen($contexto) > 80 && !str_contains($contexto, 'No se encontró contenido institucional'),
            'contexto_preview' => mb_substr($contexto, 0, 400),
        ];
    }
}
