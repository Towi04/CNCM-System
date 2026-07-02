<?php
/**
 * Manejador de inactividad de sesión
 * Verifica si la sesión ha excedido el tiempo máximo de inactividad
 * y destruye la sesión si es necesario
 */

require_once __DIR__ . '/session_helper.php';

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    hay_session_start();
}

// Verificar si hay usuario activo
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No hay sesión activa']);
    exit;
}

// Tiempo máximo de inactividad en segundos (5 minutos = 300 segundos)
$MAX_INACTIVITY_TIME = 5 * 60; // 300 segundos

// Obtener el tiempo de última actividad
$lastActivity = $_SESSION['last_activity'] ?? null;
$currentTime = time();

// Si no existe registro de última actividad, registrarlo
if ($lastActivity === null) {
    $_SESSION['last_activity'] = $currentTime;
    echo json_encode(['status' => 'ok', 'message' => 'Sesión monitorizada']);
    exit;
}

// Calcular tiempo de inactividad
$inactivityTime = $currentTime - $lastActivity;

// Si el tiempo de inactividad excede el máximo permitido
if ($inactivityTime > $MAX_INACTIVITY_TIME) {
    // Destruir la sesión
    hay_session_destroy_completa();
    
    http_response_code(401);
    echo json_encode([
        'status' => 'session_expired',
        'message' => 'Su sesión ha expirado por inactividad',
        'inactivity_time' => $inactivityTime
    ]);
    exit;
}

// Actualizar el tiempo de última actividad
$_SESSION['last_activity'] = $currentTime;

// Calcular tiempo restante
$remainingTime = $MAX_INACTIVITY_TIME - $inactivityTime;

// Responder con el estado de la sesión
echo json_encode([
    'status' => 'ok',
    'remaining_time' => $remainingTime,
    'inactivity_time' => $inactivityTime,
    'max_inactivity_time' => $MAX_INACTIVITY_TIME
]);

exit;
