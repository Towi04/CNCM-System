<?php

if (!function_exists('imgToBase64')) {

    /**
     * Convierte las imagenees a formato Base 64
     *
     * @param $path Url de la imagen
     * @return string image Parse Base 64
     */
    function imgToBase64($path = '')
    {
        if (!file_exists($path)) {
            return '';
        }

        $file = file_get_contents($path);
        $fileEncoded = base64_encode($file);
        $fileMimeType = mime_content_type ($path);
        $base64 = "data:{$fileMimeType};base64,{$fileEncoded}";

        return $base64;
    }
}
