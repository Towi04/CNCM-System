<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$token = trim((string) ($_GET['token'] ?? ''));
$control = trim((string) ($_GET['control'] ?? ''));
$credencial = ($token !== '' || $control !== '')
    ? credencial_verificar($pdo, $token, $control)
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación de credencial — CNCM</title>
  <style>
    body { font-family:system-ui,sans-serif; max-width:560px; margin:40px auto; padding:0 16px; color:#222; background:#f5f7fa; }
    .card { border:1px solid #ddd; border-radius:14px; padding:24px; background:#fff; box-shadow:0 4px 18px rgba(0,0,0,.08); }
    .ok { border-color:#2e7d32; }
    .bad { border-color:#c62828; }
    h1 { color:#11458b; font-size:1.35rem; margin:0 0 12px; }
    .status { font-weight:700; }
    .ok .status { color:#2e7d32; }
    .bad .status { color:#c62828; }
    dl { margin:16px 0 0; display:grid; grid-template-columns:135px 1fr; gap:8px 12px; }
    dt { font-weight:700; color:#555; }
    dd { margin:0; }
    form { display:flex; gap:8px; margin-top:18px; }
    input { flex:1; padding:10px; border:1px solid #bbb; border-radius:8px; }
    button { padding:10px 14px; border:0; border-radius:8px; background:#11458b; color:#fff; cursor:pointer; }
  </style>
</head>
<body>
  <?php if ($token === '' && $control === ''): ?>
    <div class="card">
      <h1>Verificar credencial</h1>
      <p>Escanee el QR o escriba el número de control.</p>
      <form method="get">
        <input type="text" name="control" required placeholder="Número de control">
        <button type="submit">Verificar</button>
      </form>
    </div>
  <?php elseif (!$credencial): ?>
    <div class="card bad">
      <h1>Credencial no encontrada</h1>
      <p class="status">El código no corresponde a una credencial registrada.</p>
    </div>
  <?php else: ?>
    <div class="card <?php echo !empty($credencial['valido']) ? 'ok' : 'bad'; ?>">
      <h1><?php echo !empty($credencial['valido']) ? 'Credencial auténtica y vigente' : 'Credencial no vigente'; ?></h1>
      <p class="status">
        <?php echo !empty($credencial['valido'])
            ? 'Registro verificado.'
            : htmlspecialchars((string) ($credencial['motivo'] ?? 'Credencial no válida'), ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <dl>
        <dt>Alumno</dt>
        <dd><?php echo htmlspecialchars((string) ($credencial['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
        <dt>Número de control</dt>
        <dd><?php echo htmlspecialchars((string) ($credencial['numero_control'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
        <dt>Especialidad</dt>
        <dd><?php echo htmlspecialchars((string) ($credencial['especialidad_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
        <dt>Plantel</dt>
        <dd><?php echo htmlspecialchars((string) ($credencial['plantel_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
        <?php if (!empty($credencial['cct'])): ?>
          <dt>CCT</dt><dd><?php echo htmlspecialchars((string) $credencial['cct'], ENT_QUOTES, 'UTF-8'); ?></dd>
        <?php endif; ?>
        <?php if (!empty($credencial['rvoe'])): ?>
          <dt>RVOE</dt><dd><?php echo htmlspecialchars((string) $credencial['rvoe'], ENT_QUOTES, 'UTF-8'); ?></dd>
        <?php endif; ?>
        <dt>Vigencia</dt>
        <dd>
          <?php echo date('d/m/Y', strtotime((string) $credencial['vigencia_inicio'])); ?>
          al <?php echo date('d/m/Y', strtotime((string) $credencial['vigencia_fin'])); ?>
        </dd>
      </dl>
    </div>
  <?php endif; ?>
</body>
</html>
