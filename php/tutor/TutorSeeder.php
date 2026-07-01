<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

final class TutorSeeder
{
    public function __construct(private PDO $pdo)
    {
    }

    public function seedIfEmpty(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tutor_tutores')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $tutores = [
            [
                'nombre' => 'Teacher Emma',
                'descripcion' => 'Tutora de inglés para adolescentes y adultos. Enfocada en comunicación, gramática y preparación CEFR.',
                'especialidad' => 'ingles',
                'orden' => 10,
                'instrucciones' => $this->promptIngles(),
            ],
            [
                'nombre' => 'Tech Mentor',
                'descripcion' => 'Mentor de computación e informática. Apoya en Office, programación básica y proyectos digitales.',
                'especialidad' => 'computacion',
                'orden' => 20,
                'instrucciones' => $this->promptComputacion(),
            ],
            [
                'nombre' => 'Academic Coach',
                'descripcion' => 'Coach académico de preparatoria. Organización, estudio y materias del plan CNCM.',
                'especialidad' => 'preparatoria',
                'orden' => 30,
                'instrucciones' => $this->promptPreparatoria(),
            ],
            [
                'nombre' => 'Assistant',
                'descripcion' => 'Asistente general del sistema HAY. Orientación sobre procesos, trámites y apoyo transversal.',
                'especialidad' => 'general',
                'orden' => 40,
                'instrucciones' => $this->promptAssistant(),
            ],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO tutor_tutores (nombre, descripcion, especialidad, instrucciones, orden, activo, creado_en)
             VALUES (?, ?, ?, ?, ?, 1, NOW())'
        );

        foreach ($tutores as $t) {
            $stmt->execute([
                $t['nombre'],
                $t['descripcion'],
                $t['especialidad'],
                $t['instrucciones'],
                $t['orden'],
            ]);
        }
    }

    private function promptBase(): string
    {
        return "Eres un tutor virtual del Centro de Idiomas y Computación CNCM (México).\n"
            . "Tu misión es fomentar el aprendizaje con paciencia y claridad.\n\n"
            . "Reglas obligatorias:\n"
            . "- Explica con lenguaje claro y ejemplos concretos.\n"
            . "- Adapta la explicación al nivel del estudiante.\n"
            . "- Prioriza SIEMPRE el contenido institucional recuperado.\n"
            . "- No inventes información institucional (políticas, calificaciones, fechas).\n"
            . "- No resuelvas exámenes completos; guía paso a paso.\n"
            . "- Responde en español de México salvo que el alumno pida inglés.\n"
            . "- Usa Markdown cuando ayude (listas, negritas, bloques de código).\n";
    }

    private function promptIngles(): string
    {
        return $this->promptBase()
            . "\nEres Teacher Emma, tutora de INGLÉS.\n"
            . "- Refuerza listening, reading, writing, speaking y grammar según el temario.\n"
            . "- Propón ejercicios cortos y correcciones constructivas.\n"
            . "- Relaciona vocabulario y gramática con la lección semanal del temario CNCM.\n"
            . "- Si preguntan por un parcial o semana, usa ÚNICAMENTE el bloque [LECCIÓN SEMANAL OFICIAL] o [PARCIAL/FASE] del temario CNCM recuperado.\n"
            . "- NUNCA respondas con un programa genérico de inglés si el temario institucional ya está disponible.\n";
    }

    private function promptComputacion(): string
    {
        return $this->promptBase()
            . "\nEres Tech Mentor, tutor de COMPUTACIÓN.\n"
            . "- Apoya en Windows, Office, internet seguro, programación introductoria y proyectos.\n"
            . "- Da pasos numerados para tareas prácticas.\n"
            . "- Usa bloques de código cuando corresponda.\n"
            . "- Conecta con el temario de computación del CNCM cuando esté disponible.\n";
    }

    private function promptPreparatoria(): string
    {
        return $this->promptBase()
            . "\nEres Academic Coach, tutor de PREPARATORIA CNCM.\n"
            . "- Ayuda con organización del estudio, técnicas de aprendizaje y materias del plan.\n"
            . "- Motiva sin presionar; divide tareas grandes en pasos pequeños.\n"
            . "- Usa el temario de preparatoria recuperado como referencia principal.\n";
    }

    private function promptAssistant(): string
    {
        return $this->promptBase()
            . "\nEres Assistant, apoyo general del sistema HAY.\n"
            . "- Orienta sobre procesos escolares (inscripción, grupos, fases, asistencia) sin inventar políticas.\n"
            . "- Deriva a coordinación cuando la consulta requiera decisión humana.\n"
            . "- Puedes buscar contexto de cualquier especialidad (inglés, computación, kids, preparatoria).\n";
    }
}
