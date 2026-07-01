<?php
$titulo = $titulo ?? 'Módulo en desarrollo';
$descripcion = $descripcion ?? 'Esta sección se integrará al sistema completo próximamente.';
?>
<link rel="stylesheet" href="css/resultados.css">
<div class="result-container">
  <div class="result-header">
    <h2><i class="fas fa-tools"></i> <?php echo htmlspecialchars($titulo); ?></h2>
  </div>
  <div class="welcome-card" style="text-align:left; max-width:640px;">
    <p style="color:#555; line-height:1.6; margin:0 0 12px;"><?php echo htmlspecialchars($descripcion); ?></p>
    <p style="color:#888; font-size:14px; margin:0;">
      Plantel activo: <strong><?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? '—'); ?></strong>
    </p>
  </div>
</div>
