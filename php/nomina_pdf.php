<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !nomina_puede_gestionar()) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idLiq = (int) ($_GET['id_liquidacion'] ?? 0);
$idUsuario = (int) ($_GET['id_usuario'] ?? 0) ?: null;

nomina_enviar_pdf_sobres($pdo, $idLiq, $idPlantel, $idUsuario);
