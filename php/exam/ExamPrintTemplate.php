<?php

namespace HayExam;

class ExamPrintTemplate
{
    public static function render(
        string $id,
        string $nombre,
        array $preguntas,
        string $logo,
        string $qrSrc,
        ?string $audioUrl,
        ?string $audioId = null
    ): string {
        $secTitles = [
            'vocabulario' => 'Vocabulary',
            'gramatica' => 'Grammar',
            'listening' => 'Listening',
            'reading' => 'Reading',
            'writing' => 'Writing',
            'speaking' => 'Speaking',
        ];

        $porSeccion = [];
        foreach ($preguntas as $p) {
            $porSeccion[$p['seccion']][] = $p;
        }
        $orden = ['vocabulario', 'gramatica', 'listening', 'reading', 'writing', 'speaking'];

        ob_start();
        self::css();
        echo '<body>';
        self::header($id, $nombre, $logo, $qrSrc, $audioUrl, $audioId);

        foreach ($orden as $sec) {
            if (empty($porSeccion[$sec])) {
                continue;
            }
            $items = $porSeccion[$sec];
            echo '<section class="section">';
            echo '<h3 class="section-title">' . htmlspecialchars($secTitles[$sec] ?? $sec) . '</h3>';

            if ($sec === 'reading') {
                foreach ($items as $it) {
                    if (!empty($it['contexto'])) {
                        echo '<div class="reading-passage">' . nl2br(htmlspecialchars($it['contexto'])) . '</div>';
                        break;
                    }
                }
            }

            if (($items[0]['tipo'] ?? '') === 'opcion_multiple') {
                echo '<div class="mc-grid">';
                foreach ($items as $p) {
                    self::preguntaMc($p, ((int)$p['numero']) % 2 === 0);
                }
                echo '</div>';
            } else {
                echo '<ul class="open-list">';
                foreach ($items as $p) {
                    self::preguntaAbierta($p);
                }
                echo '</ul>';
            }
            echo '</section>';
        }

        self::qrScript($audioUrl);
        echo '</body></html>';
        return ob_get_clean();
    }

    private static function qrLabel(?string $audioId): string
    {
        if ($audioId === null || trim($audioId) === '') {
            return '';
        }
        return 'Audio ' . trim($audioId);
    }

    private static function css(): void
    {
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>';
        echo '@page{size:letter portrait;margin:0.38in 0.42in;}';
        echo '*{box-sizing:border-box;}';
        echo 'body{font-family:Arial,Helvetica,"DejaVu Sans",sans-serif;font-size:12pt;line-height:1.2;color:#111;margin:0;padding:0.32in 0.38in;max-width:8.5in;}';
        echo '.exam-print-bar{background:#e8f0fa;border:1px solid #b6d4f5;padding:8px 12px;margin-bottom:10px;border-radius:6px;display:flex;align-items:center;gap:10px;}';
        echo '.exam-print-bar button{background:#11458B;color:#fff;border:none;padding:7px 14px;border-radius:5px;font-size:12pt;cursor:pointer;font-weight:600;}';
        echo '.exam-print-bar span{font-size:11pt;color:#444;}';
        echo '@media print{.exam-print-bar{display:none!important;}body{padding:0;}}';
        echo '.header{padding-bottom:4px;margin-bottom:6px;}';
        echo '.header table{width:100%;border-collapse:collapse;}';
        echo '.header td{vertical-align:middle;border:none;padding:0;}';
        echo '.logo{width:72px;height:72px;max-width:72px;max-height:72px;object-fit:contain;display:block;}';
        echo '.school{text-align:center;}';
        echo '.school h1{margin:0;font-size:16pt;color:#11458B;line-height:1.1;}';
        echo '.school h2{margin:2px 0 0;font-size:14pt;font-weight:600;color:#333;}';
        echo '.exam-id{font-size:10pt;color:#555;margin-top:3px;font-weight:600;}';
        echo '.qr{text-align:right;}';
        echo '.qr img,.qr canvas{width:72px;height:72px;display:block;margin-left:auto;}';
        echo '.qr-label{font-size:8pt;color:#444;display:block;text-align:right;margin-top:2px;font-weight:600;}';
        echo '.section{margin-top:5px;margin-bottom:3px;}';
        echo '.section-title{font-size:14pt;font-weight:bold;color:#fff;background:#11458B;padding:2px 7px;margin:0 0 3px;}';
        echo '.reading-passage{font-size:11pt;line-height:1.18;background:#f7f9fc;border-left:3px solid #11458B;padding:4px 7px;margin-bottom:4px;}';
        echo '.mc-grid{column-count:2;column-gap:16px;column-fill:balance;column-rule:1px solid #ccc;}';
        echo '.mc-item{break-inside:avoid;page-break-inside:avoid;margin-bottom:7px;font-size:12pt;line-height:1.2;padding:6px 8px;border-radius:3px;}';
        echo '.mc-item.mc-alt{background:#ececec;}';
        echo '.q-num{font-weight:bold;color:#11458B;}';
        echo '.opts{display:grid;grid-template-columns:1fr 1fr;gap:2px 8px;margin-top:3px;padding-left:10px;font-size:11pt;line-height:1.2;}';
        echo '.opt b{font-weight:700;}';
        echo '.open-list{margin:0;padding:0;list-style:none;}';
        echo '.open-item{font-size:12pt;margin-bottom:3px;line-height:1.18;break-inside:avoid;}';
        echo '</style></head>';
    }

    private static function header(string $id, string $nombre, string $logo, string $qrSrc, ?string $audioUrl, ?string $audioId): void
    {
        $qrText = self::qrLabel($audioId);
        echo '<div class="header"><table><tr>';
        echo '<td style="width:20%">';
        if ($logo) {
            echo '<img class="logo" src="' . $logo . '" alt="Logo" width="72" height="72">';
        }
        echo '</td><td class="school" style="width:58%">';
        echo '<h1>Grupo Educativo CNCM</h1>';
        echo '<h2>' . htmlspecialchars($nombre) . '</h2>';
        echo '<div class="exam-id">Código: ' . htmlspecialchars($id) . '</div>';
        echo '</td><td class="qr" style="width:22%">';
        if ($qrSrc || $audioUrl) {
            echo '<div class="qr-wrap">';
            if ($qrSrc) {
                echo '<img id="exam-qr-img" src="' . htmlspecialchars($qrSrc, ENT_QUOTES, 'UTF-8') . '" alt="QR" width="72" height="72">';
            }
            if ($audioUrl) {
                $style = $qrSrc ? 'display:none' : '';
                echo '<canvas id="exam-qr-canvas" width="72" height="72" style="' . $style . '"></canvas>';
            }
            if ($qrText !== '') {
                echo '<span class="qr-label">' . htmlspecialchars($qrText) . '</span>';
            }
            echo '</div>';
        }
        echo '</td></tr></table></div>';
    }

    private static function qrScript(?string $audioUrl): void
    {
        if (!$audioUrl) {
            return;
        }
        $urlJson = json_encode($audioUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        echo '<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>';
        echo '<script>(function(){';
        echo 'var url=' . $urlJson . ';';
        echo 'var img=document.getElementById("exam-qr-img");';
        echo 'var canvas=document.getElementById("exam-qr-canvas");';
        echo 'function drawQr(){if(!canvas||typeof QRCode==="undefined")return;';
        echo 'QRCode.toCanvas(canvas,url,{width:72,margin:1},function(err){';
        echo 'if(!err){if(img)img.style.display="none";canvas.style.display="block";}});}';
        echo 'if(img){img.onerror=drawQr;img.onload=function(){if(img.naturalWidth<8)drawQr();};';
        echo 'setTimeout(function(){if(img.naturalWidth<8)drawQr();},800);}else{drawQr();}';
        echo '})();</script>';
    }

    private static function preguntaMc(array $p, bool $alt): void
    {
        $cls = 'mc-item' . ($alt ? ' mc-alt' : '');
        echo '<div class="' . $cls . '"><span class="q-num">' . (int)$p['numero'] . '.</span> ';
        echo htmlspecialchars($p['pregunta']);
        echo '<div class="opts">';
        foreach (['a', 'b', 'c', 'd'] as $L) {
            $k = 'opcion_' . $L;
            if (!empty($p[$k])) {
                echo '<span class="opt"><b>' . strtoupper($L) . ')</b> ' . htmlspecialchars($p[$k]) . '</span>';
            }
        }
        echo '</div></div>';
    }

    private static function preguntaAbierta(array $p): void
    {
        echo '<li class="open-item"><span class="q-num">' . (int)$p['numero'] . '.</span> ';
        echo htmlspecialchars($p['pregunta']) . '</li>';
    }
}
