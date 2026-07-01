<?php

namespace HayExam;

class ExamPdfHelper
{
    public static function tieneDompdf(): bool
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return false;
        }
        require_once $autoload;
        return class_exists('Dompdf\Dompdf');
    }

    /**
     * Descarga imagen QR y la guarda en disco. Devuelve ruta relativa para el HTML (ej. qr/EXM-xxx.png).
     */
    public static function guardarQrArchivo(?string $url, string $examId, string $projectRoot): string
    {
        if (!$url || trim($url) === '') {
            return '';
        }
        $dir = rtrim($projectRoot, '/\\') . '/uploads/examenes/qr';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', $examId);
        $file = $dir . '/' . $safeId . '.png';
        $img = self::descargarQrImagen($url);
        if ($img) {
            file_put_contents($file, $img);
            return 'qr/' . $safeId . '.png';
        }
        return '';
    }

    /** Ruta img para el examen: archivo local, data URI, o vacío (JS en plantilla). */
    public static function qrParaExamen(?string $url, string $examId, string $projectRoot): array
    {
        $rel = self::guardarQrArchivo($url, $examId, $projectRoot);
        if ($rel !== '') {
            return ['src' => $rel, 'url' => $url];
        }
        $data = self::qrDataUri($url);
        if ($data !== '') {
            return ['src' => $data, 'url' => $url];
        }
        $apiQr = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=2&format=png&data=' . rawurlencode(trim($url));
        return ['src' => $apiQr, 'url' => $url];
    }

    public static function qrDataUri(?string $url): string
    {
        $img = self::descargarQrImagen($url);
        if (!$img) {
            return '';
        }
        return 'data:image/png;base64,' . base64_encode($img);
    }

    private static function descargarQrImagen(?string $url): ?string
    {
        if (!$url || trim($url) === '') {
            return null;
        }
        $encoded = rawurlencode(trim($url));
        $apis = [
            'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=2&format=png&data=' . $encoded,
            'https://quickchart.io/qr?size=200&margin=1&text=' . $encoded,
        ];
        foreach ($apis as $api) {
            $body = self::httpGet($api);
            if ($body !== null && strlen($body) > 80) {
                return $body;
            }
        }
        return null;
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 CNCM-HAY-Exam',
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) {
                return $body;
            }
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 25,
                'user_agent' => 'Mozilla/5.0 CNCM-HAY-Exam',
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return ($body !== false && strlen($body) > 80) ? $body : null;
    }

    /**
     * @return array{path: string, es_pdf: bool}
     */
    public static function guardarExamen(string $html, string $rutaPdfDeseada): array
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Options')) {
                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', true);
                $options->set('defaultFont', 'DejaVu Sans');
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();
                file_put_contents($rutaPdfDeseada, $dompdf->output());
                return ['path' => $rutaPdfDeseada, 'es_pdf' => true];
            }
        }

        $rutaHtml = preg_replace('/\.pdf$/i', '.html', $rutaPdfDeseada);
        $bar = '<div class="exam-print-bar"><button type="button" onclick="window.print()">'
            . 'Imprimir / Guardar PDF (Carta)</button>'
            . '<span>45 preguntas: 10 vocabulario · 20 gramática · 5 listening · 6 reading · 2 writing · 2 speaking</span></div>';
        $htmlOut = preg_replace('/<body>/i', '<body>' . $bar, $html, 1);
        file_put_contents($rutaHtml, $htmlOut);
        return ['path' => $rutaHtml, 'es_pdf' => false];
    }
}
