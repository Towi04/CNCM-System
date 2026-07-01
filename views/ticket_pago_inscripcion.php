<?php

require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || !(preregistro_puede_acceder() || alumno_puede_ver())) {

    http_response_code(403);

    echo 'No autorizado';

    exit;

}



$idPago = (int) ($_GET['id_pago'] ?? 0);

$idPlantel = plantel_id_activo();



$ticket = $idPago > 0 ? pago_datos_ticket_inscripcion($pdo, $idPago, $idPlantel) : null;

if (!$ticket) {

    http_response_code(404);

    echo 'Comprobante no encontrado';

    exit;

}



$grupo = trim((string) ($_GET['grupo'] ?? $ticket['grupo'] ?? ''));

if ($grupo !== '') {

    $ticket['grupo'] = $grupo;

}



$autoPrint = !empty($_GET['print']);

$ticketCss = hay_asset_url('css/ticket_termico.css');

?>

<!DOCTYPE html>

<html lang="es">

<head>

  <meta charset="utf-8">

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Comprobante inscripción <?php echo htmlspecialchars($ticket['folio']); ?></title>

  <link rel="stylesheet" href="<?php echo htmlspecialchars($ticketCss, ENT_QUOTES, 'UTF-8'); ?>">

</head>

<body class="ticket-termico-page">

  <div class="ticket-termico-no-print">

    <button type="button" onclick="window.print()">Imprimir</button>

    <button type="button" onclick="window.close()">Cerrar</button>

  </div>

  <?php

  $tituloTicket = 'inscripcion';

  include __DIR__ . '/partials/ticket_termico_body.php';

  ?>

  <?php if ($autoPrint): ?>

  <script>window.onload = function () { window.print(); };</script>

  <?php endif; ?>

</body>

</html>

