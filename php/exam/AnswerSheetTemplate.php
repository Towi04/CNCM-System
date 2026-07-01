<?php

namespace HayExam;

class AnswerSheetTemplate
{
    public const HOJA_UNIVERSAL = 'hoja_respuestas_universal.html';

    public static function renderUniversal(): string
    {
        $logo = self::logoDataUri();
        ob_start();
        self::css();
        echo '<body><div class="print-page">';
        echo '<div class="sheet-version">Answer Sheet v' . (int) AnswerSheetLayout::VERSION
            . ' — imprima esta versión para escaneo OMR</div>';
        self::renderAnswerSheet($logo);
        echo '<div class="cut-line"><span>— cut —</span></div>';
        self::renderAnswerSheet($logo);
        echo '</div></body></html>';
        return ob_get_clean();
    }

    private static function logoDataUri(): string
    {
        $path = dirname(__DIR__, 2) . '/src/logo2.png';
        if (!is_file($path)) {
            return '';
        }
        return 'data:image/png;base64,' . base64_encode((string)file_get_contents($path));
    }

    private static function css(): void
    {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><style>';
        echo '@page{size:letter portrait;margin:0.18in;}';
        echo '*{box-sizing:border-box;}';
        echo 'body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:8pt;color:#111;}';
        echo '.print-page{width:8.1in;margin:0 auto;}';
        echo '.sheet-version{background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:6px 10px;font-size:8pt;font-weight:bold;text-align:center;margin-bottom:4px;border-radius:4px;}';
        echo '.answer-sheet{position:relative;height:5.45in;width:100%;border:1px dashed #bbb;}';
        echo '.sheet-margin{position:absolute;inset:0;pointer-events:none;z-index:3;}';
        echo '.fiducial{position:absolute;width:16px;height:16px;background:#111;}';
        echo '.align-mark{position:absolute;width:8px;height:8px;background:#111;border-radius:1px;}';
        echo '.reg-bar-h{position:absolute;height:3px;background:#111;left:0.12in;right:0.12in;}';
        echo '.reg-bar-top{top:0.02in;}.reg-bar-bot{bottom:0.02in;}';
        echo '.fid-tl{top:0.035in;left:0.035in;}.fid-tr{top:0.035in;right:0.035in;}';
        echo '.fid-bl{bottom:0.035in;left:0.035in;}.fid-br{bottom:0.035in;right:0.035in;}';
        echo '.align-tm{top:0.035in;left:50%;transform:translateX(-50%);}';
        echo '.align-bm{bottom:0.035in;left:50%;transform:translateX(-50%);}';
        echo '.align-lm{left:0.035in;top:50%;transform:translateY(-50%);}';
        echo '.align-rm{right:0.035in;top:50%;transform:translateY(-50%);}';
        echo '.align-mc1{left:0.09in;top:0.78in;}.align-mc2{right:0.09in;top:0.78in;}';
        echo '.align-mc3{left:0.09in;top:2.62in;}.align-mc4{right:0.09in;top:2.62in;}';
        echo '.sheet-body{position:relative;z-index:1;padding:0.22in 0.16in 0.16in 0.18in;}';
        echo '.hdr{display:flex;align-items:center;gap:12px;margin:0 0 0.06in;padding:0 0.06in;}';
        echo '.hdr img{height:40px;width:auto;object-fit:contain;}';
        echo '.hdr-txt{flex:1;text-align:center;}';
        echo '.hdr h1{margin:0;font-size:14pt;letter-spacing:0.5px;}';
        echo '.hdr-fields{font-size:7pt;margin-top:5px;display:flex;flex-wrap:wrap;gap:8px 20px;justify-content:center;align-items:flex-end;}';
        echo '.hdr-field{display:flex;align-items:flex-end;gap:5px;white-space:nowrap;}';
        echo '.hdr-field .line{display:inline-block;border-bottom:1.5px solid #222;min-width:2.4in;height:16px;vertical-align:bottom;}';
        echo '.hdr-field.nc .line{min-width:0.9in;}';
        echo '.mc-zone{display:flex;gap:0.1in;margin-bottom:0.05in;position:relative;}';
        echo '.mc-col{flex:1;min-width:0;border:1px solid #999;border-radius:4px;padding:4px 5px;position:relative;}';
        echo '.mc-col-hdr{font-size:7pt;font-weight:700;text-align:center;text-transform:uppercase;';
        echo 'background:#11458B;color:#fff;padding:3px 2px;margin:-4px -5px 5px;border-radius:3px 3px 0 0;line-height:1.2;}';
        echo '.mc-subhdr{font-size:6.5pt;font-weight:700;color:#11458B;margin:4px 0 2px;border-bottom:1px solid #ccc;padding-bottom:1px;}';
        echo '.mc-row{display:flex;align-items:center;margin-bottom:2px;font-size:6.5pt;line-height:1.25;}';
        echo '.mc-row .qnum{width:16px;font-weight:700;text-align:right;flex-shrink:0;margin-right:3px;}';
        echo '.mc-row .opts{display:flex;gap:5px;flex-wrap:nowrap;}';
        echo '.mc-row .opt{display:flex;align-items:center;gap:2px;font-size:6pt;font-weight:600;}';
        echo '.rub-pair{display:flex;gap:0.1in;margin-bottom:0.03in;}';
        echo '.rub-pair .rub-col{flex:1;min-width:0;}';
        echo '.rub-sub{font-size:7pt;font-weight:700;margin-bottom:2px;}';
        echo '.rub-table{width:100%;border-collapse:collapse;font-size:5.5pt;}';
        echo '.rub-table th,.rub-table td{border:1px solid #999;padding:1px 2px;}';
        echo '.rub-table th{background:#eee;}';
        echo '.rub-table td.aspect{font-size:5pt;line-height:1.15;}';
        echo '.rub-table .cell{text-align:center;}';
        echo '.rub-table th.opt-hdr{font-size:4.5pt;line-height:1.1;white-space:nowrap;padding:1px;}';
        echo '.bubble{width:12px;height:12px;border:1.5px solid #222;border-radius:50%;display:inline-block;background:#fff;flex-shrink:0;}';
        echo '.cut-line{border-top:1px dashed #888;margin:0.06in 0;text-align:center;color:#888;font-size:6pt;}';
        echo '.cut-line span{background:#fff;padding:0 6px;position:relative;top:-7px;}';
        echo '@media print{.exam-print-bar{display:none!important;}.answer-sheet{border-color:#ddd;}}';
        echo '</style></head>';
    }

    private static function renderAnswerSheet(string $logoDataUri): void
    {
        echo '<div class="answer-sheet">';
        echo '<div class="sheet-margin">';
        echo '<div class="reg-bar-h reg-bar-top"></div><div class="reg-bar-h reg-bar-bot"></div>';
        foreach (['tl', 'tr', 'bl', 'br'] as $c) {
            echo '<div class="fiducial fid-' . $c . '"></div>';
        }
        foreach (['tm', 'bm', 'lm', 'rm', 'mc1', 'mc2', 'mc3', 'mc4'] as $c) {
            echo '<div class="align-mark align-' . $c . '"></div>';
        }
        echo '</div>';
        echo '<div class="sheet-body">';
        self::headerHtml($logoDataUri);
        self::mcHtml();
        self::rubricPairHtml('Writing', AnswerSheetLayout::WRITING_QUESTIONS, AnswerSheetLayout::WRITING_ASPECTS, 'w');
        self::rubricPairHtml('Speaking', AnswerSheetLayout::SPEAKING_QUESTIONS, AnswerSheetLayout::SPEAKING_ASPECTS, 's');
        echo '</div></div>';
    }

    private static function headerHtml(string $logoDataUri): void
    {
        echo '<div class="hdr">';
        if ($logoDataUri !== '') {
            echo '<img src="' . $logoDataUri . '" alt="School logo">';
        }
        echo '<div class="hdr-txt"><h1>Answer Sheet</h1>';
        echo '<div class="hdr-fields">';
        echo '<div class="hdr-field"><span>Name:</span><span class="line"></span></div>';
        echo '<div class="hdr-field nc"><span>NC:</span><span class="line"></span></div>';
        echo '</div></div>';
        if ($logoDataUri !== '') {
            echo '<div style="width:40px;"></div>';
        }
        echo '</div>';
    }

    private static function mcHtml(): void
    {
        echo '<div class="mc-zone">';
        foreach (AnswerSheetLayout::MC_COLUMNS as $col) {
            echo '<div class="mc-col">';
            echo '<div class="mc-col-hdr">' . htmlspecialchars($col['title']) . '</div>';
            if (!empty($col['subsections'])) {
                foreach ($col['subsections'] as $sub) {
                    echo '<div class="mc-subhdr">' . htmlspecialchars($sub['title']) . '</div>';
                    self::mcRows($sub['from'], $sub['to']);
                }
            } else {
                self::mcRows($col['from'], $col['to']);
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private static function mcRows(int $from, int $to): void
    {
        for ($q = $from; $q <= $to; $q++) {
            echo '<div class="mc-row"><span class="qnum">' . $q . '.</span><span class="opts">';
            foreach (AnswerSheetLayout::MC_OPTIONS as $opt) {
                echo '<span class="opt"><span class="bubble" data-id="mc_' . $q . '_' . strtolower($opt) . '"></span>' . $opt . '</span>';
            }
            echo '</span></div>';
        }
    }

    private static function rubricPairHtml(string $section, int $numQ, array $aspects, string $prefix): void
    {
        echo '<div class="rub-pair">';
        for ($q = 0; $q < $numQ; $q++) {
            echo '<div class="rub-col">';
            echo '<div class="rub-sub">' . htmlspecialchars($section) . ' ' . ($q + 1) . '</div>';
            echo '<table class="rub-table"><thead><tr><th>Aspect</th>';
            foreach (AnswerSheetLayout::RUBRIC_OPTIONS as $o) {
                $pct = AnswerSheetLayout::RUBRIC_SCORES[$o] ?? 0;
                echo '<th class="cell opt-hdr">' . htmlspecialchars($o . '=' . $pct . '%') . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($aspects as $i => $label) {
                echo '<tr><td class="aspect">' . htmlspecialchars($label) . '</td>';
                foreach (AnswerSheetLayout::RUBRIC_OPTIONS as $opt) {
                    echo '<td class="cell"><span class="bubble" data-id="' . $prefix . $q . '_' . $i . '_' . strtolower($opt) . '"></span></td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';
    }
}
