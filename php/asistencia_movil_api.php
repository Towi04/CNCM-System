<?php
/** @deprecated Asistencia móvil descontinuada — usar rondín (asistencia_faltantes). */
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
hay_json_response([
    'status' => 'error',
    'code' => 'deprecated',
    'message' => 'Asistencia móvil ya no está disponible. Use Rondín de asistencia.',
    'redirect' => 'asistencia_faltantes',
]);
