<?php

namespace HayExam;

/**
 * Coordenadas normalizadas (0–1) en la hoja completa, con márgenes para marcas.
 */
class AnswerSheetLayout
{
    public const MC_COUNT = 41;
    public const MC_OPTIONS = ['A', 'B', 'C', 'D'];
    public const RUBRIC_OPTIONS = ['A', 'B', 'C', 'D', 'E'];
    public const RUBRIC_SCORES = ['A' => 0, 'B' => 25, 'C' => 50, 'D' => 75, 'E' => 100];
    public const WRITING_QUESTIONS = 2;
    public const SPEAKING_QUESTIONS = 2;
    public const CN_DIGITOS = 5;
    public const SHEETS_PER_PAGE = 2;
    public const CANON_W = 1020;
    public const CANON_H = 660;
    /** Versión del layout OMR (reimprimir hoja al cambiar). */
    public const VERSION = 5;

    /** Márgenes (fracción de la hoja) donde van las marcas, sin contenido. */
    public const MARGIN_TOP = 0.062;
    public const MARGIN_BOTTOM = 0.048;
    public const MARGIN_LEFT = 0.062;
    public const MARGIN_RIGHT = 0.048;

  /** Área útil del contenido (alineado con .sheet-body en la plantilla). */
    public const CONTENT_X0 = 0.028;
    public const CONTENT_X1 = 0.972;

    public const MC_COLUMNS = [
        ['id' => 'vocab', 'title' => 'Vocabulary', 'from' => 1, 'to' => 10],
        ['id' => 'grammar_a', 'title' => 'Grammar', 'from' => 11, 'to' => 20],
        ['id' => 'grammar_b', 'title' => 'Grammar', 'from' => 21, 'to' => 30],
        [
            'id' => 'listen_read',
            'title' => 'Listening / Reading',
            'from' => 31,
            'to' => 41,
            'subsections' => [
                ['title' => 'Listening', 'from' => 31, 'to' => 35],
                ['title' => 'Reading', 'from' => 36, 'to' => 41],
            ],
        ],
    ];

    public const WRITING_ASPECTS = [
        'Task Response',
        'Cohesion and Coherence',
        'Grammar Accuracy',
        'Punctuation and Mechanics',
        'Lexis',
    ];

    public const SPEAKING_ASPECTS = [
        'Fluency and Coherence',
        'Grammar Accuracy',
        'Vocabulary range',
        'Pronunciation',
        'Interaction',
    ];

    public static function cornerMarks(): array
    {
        $m = 0.022;
        $s = 0.042;
        return [
            ['id' => 'corner_tl', 'group' => 'corner', 'x' => $m, 'y' => $m, 'r' => $s],
            ['id' => 'corner_tr', 'group' => 'corner', 'x' => 1 - $m, 'y' => $m, 'r' => $s],
            ['id' => 'corner_bl', 'group' => 'corner', 'x' => $m, 'y' => 1 - $m, 'r' => $s],
            ['id' => 'corner_br', 'group' => 'corner', 'x' => 1 - $m, 'y' => 1 - $m, 'r' => $s],
        ];
    }

    /** Marcas adicionales en bordes para alinear el escaneo. */
    public static function alignmentMarks(): array
    {
        $s = 0.014;
        $inset = 0.028;
        return [
            ['id' => 'align_tm', 'group' => 'align', 'x' => 0.5, 'y' => $inset, 'r' => $s],
            ['id' => 'align_bm', 'group' => 'align', 'x' => 0.5, 'y' => 1 - $inset, 'r' => $s],
            ['id' => 'align_lm', 'group' => 'align', 'x' => $inset, 'y' => 0.5, 'r' => $s],
            ['id' => 'align_rm', 'group' => 'align', 'x' => 1 - $inset, 'y' => 0.5, 'r' => $s],
            ['id' => 'align_mc1', 'group' => 'align', 'x' => 0.14, 'y' => 0.152, 'r' => $s * 0.85],
            ['id' => 'align_mc2', 'group' => 'align', 'x' => 0.86, 'y' => 0.152, 'r' => $s * 0.85],
            ['id' => 'align_mc3', 'group' => 'align', 'x' => 0.14, 'y' => 0.522, 'r' => $s * 0.85],
            ['id' => 'align_mc4', 'group' => 'align', 'x' => 0.86, 'y' => 0.522, 'r' => $s * 0.85],
        ];
    }

    public static function buildLayout(): array
    {
        return [
            'version' => 5,
            'mc_count' => self::MC_COUNT,
            'mc_columns' => self::MC_COLUMNS,
            'writing_questions' => self::WRITING_QUESTIONS,
            'speaking_questions' => self::SPEAKING_QUESTIONS,
            'sheets_per_page' => self::SHEETS_PER_PAGE,
            'canon_w' => self::CANON_W,
            'canon_h' => self::CANON_H,
            'fill_threshold' => 135,
            'margins' => [
                'top' => self::MARGIN_TOP,
                'bottom' => self::MARGIN_BOTTOM,
                'left' => self::MARGIN_LEFT,
                'right' => self::MARGIN_RIGHT,
            ],
            'page_regions' => [
                ['sheet' => 0, 'y0' => 0.0, 'y1' => 0.5],
                ['sheet' => 1, 'y0' => 0.5, 'y1' => 1.0],
            ],
            'corners' => ['tl', 'tr', 'bl', 'br'],
            'bubbles' => self::buildSheetBubbles(),
            'writing_aspects' => self::WRITING_ASPECTS,
            'speaking_aspects' => self::SPEAKING_ASPECTS,
            'rubric_scores' => self::RUBRIC_SCORES,
        ];
    }

    private static function buildSheetBubbles(): array
    {
        $bubbles = array_merge(self::cornerMarks(), self::alignmentMarks());

        $mcY0 = 0.162;
        $mcY1 = 0.522;
        $colCount = count(self::MC_COLUMNS);
        $contentW = self::CONTENT_X1 - self::CONTENT_X0;
        $colW = $contentW / $colCount;
        $brMc = 0.0105;
        $numStart = 0.050;
        $optW = 0.030;

        foreach (self::MC_COLUMNS as $colIndex => $col) {
            $baseX = self::CONTENT_X0 + $colIndex * $colW;
            $count = $col['to'] - $col['from'] + 1;
            $rows = max(10, $count);
            $rowH = ($mcY1 - $mcY0) / $rows;
            $rowOffset = (int)floor((10 - min(10, $count)) / 2);

            for ($i = 0; $i < $count; $i++) {
                $q = $col['from'] + $i;
                $cy = $mcY0 + ($rowOffset + $i + 0.5) * $rowH;
                $ox = $baseX + $numStart;
                foreach (self::MC_OPTIONS as $oi => $opt) {
                    $bubbles[] = [
                        'id' => 'mc_' . $q . '_' . strtolower($opt),
                        'group' => 'mc',
                        'section' => $col['id'],
                        'column' => $colIndex,
                        'question' => $q,
                        'value' => $opt,
                        'x' => $ox + $oi * $optW,
                        'y' => $cy,
                        'r' => $brMc,
                    ];
                }
            }
        }

        self::addRubricBlock($bubbles, 'writing', self::WRITING_ASPECTS, self::WRITING_QUESTIONS, 0.532, 0.702);
        self::addRubricBlock($bubbles, 'speaking', self::SPEAKING_ASPECTS, self::SPEAKING_QUESTIONS, 0.722, 0.892);

        return $bubbles;
    }

    private static function addRubricBlock(array &$bubbles, string $group, array $aspects, int $numQuestions, float $y0, float $y1): void
    {
        $colW = 0.44;
        $rowH = ($y1 - $y0) / count($aspects);
        $brRub = 0.008;
        $optW = 0.072;

        for ($q = 0; $q < $numQuestions; $q++) {
            $x0 = self::CONTENT_X0 + $q * $colW;
            $ox = $x0 + 0.17;
            foreach ($aspects as $i => $label) {
                $cy = $y0 + ($i + 0.5) * $rowH;
                foreach (self::RUBRIC_OPTIONS as $j => $opt) {
                    $bubbles[] = [
                        'id' => $group[0] . $q . '_' . $i . '_' . strtolower($opt),
                        'group' => $group,
                        'question' => $q,
                        'aspect' => $i,
                        'aspect_label' => $label,
                        'value' => $opt,
                        'x' => $ox + $j * $optW,
                        'y' => $cy,
                        'r' => $brRub,
                    ];
                }
            }
        }
    }

    public static function rubricPercent(string $letter): float
    {
        return (float)(self::RUBRIC_SCORES[strtoupper($letter)] ?? 0);
    }

    public static function normalizarCN(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) > self::CN_DIGITOS) {
            $digits = substr($digits, -self::CN_DIGITOS);
        }
        return str_pad($digits, self::CN_DIGITOS, '0', STR_PAD_LEFT);
    }
}
