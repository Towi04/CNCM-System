<?php

declare(strict_types=1);



require __DIR__ . '/config.php';



$token = trim($_GET['token'] ?? '');

?>

<!DOCTYPE html>

<html lang="es">

<head>

  <meta charset="UTF-8">

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Verificación de documento — CNCM</title>

  <style>

    body { font-family: system-ui, sans-serif; max-width: 520px; margin: 40px auto; padding: 0 16px; color: #222; }

    .card { border: 1px solid #ddd; border-radius: 10px; padding: 24px; }

    .ok { border-color: #2e7d32; background: #e8f5e9; }

    .bad { border-color: #c62828; background: #ffebee; }

    h1 { font-size: 1.25rem; margin: 0 0 12px; color: #11458B; }

    dl { margin: 0; }

    dt { font-weight: 600; margin-top: 10px; font-size: 0.85rem; color: #555; }

    dd { margin: 2px 0 0; }

  </style>

</head>

<body>

<?php if ($token === ''): ?>

  <div class="card bad"><h1>Token no proporcionado</h1><p>Escanee el código QR del documento.</p></div>

<?php else:

    $doc = documento_verificar_token($pdo, $token);

    if (!$doc): ?>

  <div class="card bad"><h1>Documento no encontrado</h1><p>El código no corresponde a un documento registrado.</p></div>

<?php elseif (empty($doc['valido'])): ?>

  <div class="card bad">

    <h1>Documento no válido</h1>

    <p><?php echo htmlspecialchars((string) ($doc['motivo'] ?? 'No vigente')); ?></p>

  </div>

<?php else: ?>

  <div class="card ok">

    <h1>Documento auténtico</h1>

    <p>Este documento fue emitido por <?php echo htmlspecialchars(app_display_name(), ENT_QUOTES, 'UTF-8'); ?> de <?php echo htmlspecialchars(APP_INSTITUTION, ENT_QUOTES, 'UTF-8'); ?>.</p>

    <dl>

      <dt>Tipo</dt><dd><?php echo htmlspecialchars(ucfirst((string) ($doc['tipo'] ?? ''))); ?></dd>

      <dt>Folio</dt><dd><?php echo htmlspecialchars((string) ($doc['folio'] ?? '')); ?></dd>

      <dt>Alumno</dt><dd><?php echo htmlspecialchars((string) ($doc['alumno_nombre'] ?? '')); ?></dd>

      <dt>Control</dt><dd><?php echo htmlspecialchars((string) ($doc['numero_control'] ?? '')); ?></dd>

      <dt>Válido hasta</dt><dd><?php echo !empty($doc['vigente_hasta']) ? htmlspecialchars(date('d/m/Y', strtotime($doc['vigente_hasta']))) : '—'; ?></dd>

      <dt>Emitido</dt><dd><?php echo !empty($doc['generado_en']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($doc['generado_en']))) : '—'; ?></dd>

    </dl>

  </div>

<?php endif; endif; ?>

</body>

</html>

