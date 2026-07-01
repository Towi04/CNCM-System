<?php
declare(strict_types=1);

namespace HayTutor;

/**
 * Servicio de IA — encapsula OpenRouter y registro de logs.
 */
final class AIService
{
    private ?MaterialContextRetriever $materialRetriever = null;
    private ?InstitutionalSystemContextRetriever $institutionalRetriever = null;

    public function __construct(
        private AcademicContextRetriever $contextRetriever,
        private AiLogRepository $logRepo,
        private ?\PDO $pdo = null,
    ) {
        if ($this->pdo !== null) {
            $this->materialRetriever = new MaterialContextRetriever($this->pdo);
            $this->institutionalRetriever = new InstitutionalSystemContextRetriever($this->pdo);
        }
    }

    public function calcularTokens(string $texto): int
    {
        $len = mb_strlen($texto);
        if ($len === 0) {
            return 0;
        }

        return max(1, (int) ceil($len / 4));
    }

    public function estimarCosto(string $modelo, int $tokensPrompt, int $tokensRespuesta): float
    {
        $rates = [
            'openai/gpt-4o' => ['in' => 0.0000025, 'out' => 0.00001],
            'openai/gpt-4o-mini' => ['in' => 0.00000015, 'out' => 0.0000006],
            'google/gemini-2.0-flash' => ['in' => 0.0000001, 'out' => 0.0000004],
        ];
        $rate = $rates[$modelo] ?? ['in' => 0.000001, 'out' => 0.000003];

        return round($tokensPrompt * $rate['in'] + $tokensRespuesta * $rate['out'], 6);
    }

    public function recuperarContexto(string $pregunta, ?string $especialidadTutor = null, ?int $userId = null): string
    {
        $partes = [];

        if ($this->institutionalRetriever !== null && $userId !== null && $userId > 0) {
            try {
                $inst = $this->institutionalRetriever->buscar($pregunta, $userId);
                if ($inst !== '') {
                    $partes[] = $inst;
                }
            } catch (\Throwable $e) {
                error_log('Tutor AIService contexto institucional: ' . $e->getMessage());
            }
        }

        $resultado = ['contexto_usuario' => null, 'semanas' => []];
        try {
            $resultado = $this->contextRetriever->buscar($pregunta, $especialidadTutor, $userId);
            $partes[] = $this->contextRetriever->formatearContexto($resultado);
        } catch (\Throwable $e) {
            error_log('Tutor AIService contexto académico: ' . $e->getMessage());
            $partes[] = 'No se encontró contenido institucional específico para esta consulta en la base de datos CNCM.';
        }

        if ($this->pdo !== null && $userId !== null && $userId > 0 && function_exists('alumno_perfil_texto_para_ia')) {
            try {
                $perfil = alumno_perfil_texto_para_ia($this->pdo, $userId);
                if ($perfil !== '') {
                    $partes[] = $perfil;
                }
            } catch (\Throwable $e) {
                error_log('Tutor AIService perfil alumno: ' . $e->getMessage());
            }
        }

        if ($this->materialRetriever !== null) {
            try {
                $ctx = $resultado['contexto_usuario'] ?? null;
                $idFase = is_array($ctx) ? (int) ($ctx['id_fase'] ?? 0) : 0;
                $numSemana = null;
                foreach ($resultado['semanas'] ?? [] as $s) {
                    if (!empty($s['semana'])) {
                        $numSemana = (int) $s['semana'];
                        break;
                    }
                }
                $material = $this->materialRetriever->buscar(
                    $pregunta,
                    $especialidadTutor,
                    $numSemana,
                    $idFase > 0 ? $idFase : null
                );
                if ($material !== '') {
                    $partes[] = $material;
                }
            } catch (\Throwable $e) {
                error_log('Tutor AIService material RAG: ' . $e->getMessage());
            }
        }

        return implode("\n\n", array_filter($partes, static fn ($p) => trim($p) !== ''));
    }

    /**
     * @param list<array{role:string,content:string}> $historial
     * @return array{ok:bool,text?:string,model?:string,message?:string,hint?:string,tokens_prompt?:int,tokens_respuesta?:int,tokens_total?:int,costo?:float,http_code?:int}
     */
    public function enviarMensaje(
        string $systemPrompt,
        string $contextoAcademico,
        string $preguntaUsuario,
        array $historial = [],
        ?int $userId = null,
        ?int $conversationId = null,
        ?int $tutorId = null,
    ): array {
        $messages = $this->construirPrompt($systemPrompt, $contextoAcademico, $historial, $preguntaUsuario);
        $model = \defined('OPENROUTER_TUTOR_MODEL') && \constant('OPENROUTER_TUTOR_MODEL') !== ''
            ? (string) \constant('OPENROUTER_TUTOR_MODEL')
            : (function_exists('hay_openrouter_model') ? hay_openrouter_model() : 'openai/gpt-4o');

        $options = [
            'model' => $model,
            'temperature' => str_contains($contextoAcademico, 'No se encontró contenido institucional') ? 0.65 : 0.45,
            'max_tokens' => (int) (\defined('OPENROUTER_TUTOR_MAX_TOKENS') ? \constant('OPENROUTER_TUTOR_MAX_TOKENS') : 1800),
        ];

        if (!function_exists('hay_openrouter_chat')) {
            return ['ok' => false, 'message' => 'OpenRouter no disponible'];
        }

        $res = hay_openrouter_chat($messages, $options);
        $promptSerializado = json_encode($messages, JSON_UNESCAPED_UNICODE) ?: '';
        $tokensPrompt = (int) ($res['usage']['prompt_tokens'] ?? 0);
        $tokensResp = (int) ($res['usage']['completion_tokens'] ?? 0);
        if ($tokensPrompt === 0) {
            $tokensPrompt = $this->calcularTokens($promptSerializado);
        }
        if ($tokensResp === 0 && !empty($res['text'])) {
            $tokensResp = $this->calcularTokens((string) $res['text']);
        }
        $tokensTotal = $tokensPrompt + $tokensResp;
        $costo = $this->estimarCosto($model, $tokensPrompt, $tokensResp);

        if ($userId !== null && $userId > 0) {
            $this->registrarLog([
                'id_usuario' => $userId,
                'id_conversacion' => $conversationId,
                'id_tutor' => $tutorId,
                'prompt_enviado' => mb_substr($promptSerializado, 0, 65000),
                'respuesta_recibida' => $res['ok'] ? (string) ($res['text'] ?? '') : null,
                'modelo' => (string) ($res['model'] ?? $model),
                'tokens_prompt' => $tokensPrompt,
                'tokens_respuesta' => $tokensResp,
                'tokens_total' => $tokensTotal,
                'costo_estimado' => $costo,
                'http_code' => $res['http_code'] ?? null,
                'provider' => 'openrouter',
            ]);
        }

        if (!$res['ok']) {
            return [
                'ok' => false,
                'message' => $res['message'] ?? 'Error de IA',
                'hint' => $res['hint'] ?? null,
                'http_code' => $res['http_code'] ?? 0,
            ];
        }

        return [
            'ok' => true,
            'text' => (string) ($res['text'] ?? ''),
            'model' => (string) ($res['model'] ?? $model),
            'tokens_prompt' => $tokensPrompt,
            'tokens_respuesta' => $tokensResp,
            'tokens_total' => $tokensTotal,
            'costo' => $costo,
            'http_code' => $res['http_code'] ?? 200,
        ];
    }

    /**
     * @param list<array{role:string,content:string}> $historial
     * @return list<array{role:string,content:string}>
     */
    public function construirPrompt(
        string $systemPrompt,
        string $contextoAcademico,
        array $historial,
        string $preguntaUsuario,
    ): array {
        $system = $systemPrompt . "\n\n"
            . "=== CONTENIDO INSTITUCIONAL RECUPERADO (prioridad máxima) ===\n"
            . $contextoAcademico . "\n\n"
            . $this->reglasRespuestaTemario($contextoAcademico);

        $messages = [['role' => 'system', 'content' => $system]];

        foreach (array_slice($historial, -16) as $msg) {
            $role = $msg['role'] ?? '';
            $content = trim((string) ($msg['content'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $preguntaUsuario];

        return $messages;
    }

    private function reglasRespuestaTemario(string $contextoAcademico): string
    {
        $sinTemario = str_contains($contextoAcademico, 'No se encontró contenido institucional');
        $tieneInstitucional = str_contains($contextoAcademico, '[DATOS INSTITUCIONALES HAY');
        $tienePerfil = str_contains($contextoAcademico, '[PERFIL PERSONAL DEL ALUMNO]');
        $tieneMaterial = str_contains($contextoAcademico, 'MATERIALES CNCM');

        $reglas = '';
        if ($tieneInstitucional) {
            $reglas = 'Reglas OBLIGATORIAS (datos operativos HAY):'
                . "\n- Si aparece [TARIFAS OFICIALES CNCM], responda con esos montos exactos (referencia y apoyo educativo)."
                . "\n- Si aparece [EXPEDIENTE ALUMNO] o [PRE-REGISTROS], use teléfono, email y datos listados; no diga que no tiene acceso."
                . "\n- Si aparece [PODIO ASESORES], cite el ranking del periodo indicado."
                . "\n- No remita a coordinación si la respuesta ya está en el bloque institucional."
                . "\n- No invente tarifas, teléfonos ni datos que no figuren en el contexto.";
        }

        if (!$sinTemario) {
            $reglasAcad = 'Reglas OBLIGATORIAS (temario):'
                . "\n- Responde PRIMERO y principalmente con el temario CNCM del bloque anterior."
                . "\n- Si aparece [LECCIÓN SEMANAL OFICIAL], usa vocabulario, gramática, listening, reading, writing y speaking de ese bloque."
                . "\n- Si aparece [PARCIAL/FASE], menciona el objetivo del parcial y los temas generales registrados."
                . "\n- NO sustituyas el temario institucional por un programa genérico de inglés u otra materia."
                . "\n- Cita explícitamente la semana, fase o programa CNCM cuando corresponda."
                . "\n- Solo si el temario anterior no cubre un detalle menor, indícalo y complementa brevemente.";
            if ($tieneMaterial) {
                $reglasAcad .= "\n- Si hay [MATERIAL INSTITUCIONAL], sugiere páginas del libro o actividades Moodle concretas cuando ayuden."
                    . "\n- Para ejercicios de libro indexados, guía paso a paso usando el contenido recuperado; no inventes enunciados.";
            }
            if ($tienePerfil) {
                $reglasAcad .= "\n- Personaliza ejemplos según [PERFIL PERSONAL DEL ALUMNO] sin dejar de cumplir el temario oficial.";
            }
            $reglasAcad .= "\n- Nunca inventes datos institucionales (fechas, calificaciones, políticas)."
                . "\n- No resuelvas exámenes completos; guía paso a paso.";
            $reglas = $reglas !== '' ? $reglas . "\n\n" . $reglasAcad : $reglasAcad;

            return $reglas;
        }

        if ($reglas !== '') {
            return $reglas . "\n- Nunca inventes datos institucionales."
                . "\n- No resuelvas exámenes completos; guía paso a paso.";
        }

        return 'Reglas:'
            . "\n- No se recuperó temario específico en la base CNCM para esta consulta; indícalo con claridad."
            . "\n- Puedes complementar con pedagogía general, sin inventar contenido institucional."
            . "\n- Nunca inventes datos institucionales (fechas, calificaciones, políticas)."
            . "\n- No resuelvas exámenes completos; guía paso a paso.";
    }

    public function registrarLog(array $data): int
    {
        try {
            return $this->logRepo->registrar($data);
        } catch (\Throwable $e) {
            error_log('Tutor AIService log: ' . $e->getMessage());

            return 0;
        }
    }
}
