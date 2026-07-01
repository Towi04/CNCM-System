<?php
/** @var array $ec resultado de pago_estado_cuenta() */
if (empty($ec['ok'])) {
    echo '<p>' . htmlspecialchars($ec['message'] ?? 'Sin datos') . '</p>';
    return;
}
$a = $ec['alumno'];
$r = $ec['resumen'];
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'CNCM';
?>
<div class="ec-print-area" id="estado-cuenta-print">
  <div class="ec-print-header">
    <h1>Estado de cuenta — Colegiaturas</h1>
    <p style="margin:4px 0 0; color:#666;">
      <?php echo htmlspecialchars($plantelNombre); ?> · Corte al <?php echo date('d/m/Y', strtotime($ec['fecha_corte'])); ?>
    </p>
  </div>

  <p style="margin:0 0 12px;">
    <strong>Alumno:</strong> <?php echo htmlspecialchars($a['nombre_completo']); ?><br>
    <strong># Control:</strong> <?php echo htmlspecialchars((string)($a['numero_control'] ?? $a['id_alumno'])); ?>
    <?php if (!empty($a['telefono'])): ?> · <strong>Tel:</strong> <?php echo htmlspecialchars($a['telefono']); ?><?php endif; ?>
  </p>

  <div class="ec-resumen no-print" style="margin-bottom:16px;">
    <div class="ec-resumen-card">
      <div class="ec-resumen-card__monto"><?php echo catalog_format_mxn($r['colegiatura_esperada']); ?></div>
      <div class="ec-resumen-card__label">Debería haber pagado (coleg.)</div>
    </div>
    <div class="ec-resumen-card">
      <div class="ec-resumen-card__monto"><?php echo catalog_format_mxn($r['colegiatura_pagada']); ?></div>
      <div class="ec-resumen-card__label">Total pagado (coleg.)</div>
    </div>
    <div class="ec-resumen-card ec-resumen-card--adeudo">
      <div class="ec-resumen-card__monto"><?php echo catalog_format_mxn($r['adeudo_colegiatura']); ?></div>
      <div class="ec-resumen-card__label">Adeudo actual</div>
    </div>
  </div>

  <p class="ec-nota">
    Pronto pago aplica al <strong>adelantar</strong> el pago de un mes futuro, o al pagar el mes en curso
    en los primeros <strong><?php echo (int)$ec['dia_pronto_pago']; ?> días</strong>.
    Si el alumno paga un mes ya vencido, aplica tarifa de mensualidad completa.
    La colegiatura inicia cuando arranca el grupo asignado; la inscripción es independiente.
    Si el grupo inicia a mitad de mes, la primera y última mensualidad son proporcionales
    (el total equivale a la duración del programa en meses).
    La baja temporal pospone los meses de colegiatura; al reincorporarse continúa el plan.
    Las tarifas mostradas son las <strong>congeladas al inscribirse</strong> en cada especialidad.
  </p>

  <?php foreach ($ec['inscripciones'] as $ins): ?>
    <h3 class="ec-section-title"><?php echo htmlspecialchars($ins['especialidad']); ?> (<?php echo $ins['forma_pago'] === 'semanal' ? 'Semanal' : 'Mensual'; ?>)</h3>
    <p class="ec-nota">
      Inscripción: <?php echo date('d/m/Y', strtotime($ins['fecha_inscripcion'])); ?>
      <?php if (!empty($ins['fecha_inicio_colegiaturas'])): ?>
        · Colegiaturas desde: <?php echo date('d/m/Y', strtotime($ins['fecha_inicio_colegiaturas'])); ?>
      <?php endif; ?>
      · Tarifas: Insc. <?php echo catalog_format_mxn($ins['tarifas_congeladas']['inscripcion']); ?>
      · Mensual <?php echo catalog_format_mxn($ins['tarifas_congeladas']['mensualidad']); ?>
      · Pronto pago <?php echo catalog_format_mxn($ins['tarifas_congeladas']['pronto_pago']); ?>
      <?php if ($ins['forma_pago'] === 'semanal'): ?>
        · Semanal <?php echo catalog_format_mxn($ins['tarifas_congeladas']['semanal']); ?>
      <?php endif; ?>
      · <strong>Adeudo esp.:</strong> <?php echo catalog_format_mxn($ins['adeudo']); ?>
      <?php if (!empty($ins['regla_combo'])): ?>
        · <em>Regla combo:</em> <?php echo htmlspecialchars($ins['regla_combo']); ?>
      <?php endif; ?>
    </p>

    <?php if (!empty($ins['lineas_pendientes'])): ?>
      <table class="ec-table">
        <thead>
          <tr>
            <th>Periodo</th>
            <th>Concepto</th>
            <th class="monto">Esperado</th>
            <th class="monto">Pagado</th>
            <th class="monto">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ins['lineas_pendientes'] as $ln): ?>
            <tr>
              <td><?php echo htmlspecialchars($ln['periodo']); ?></td>
              <td><?php echo htmlspecialchars($ln['detalle']); ?></td>
              <td class="monto"><?php echo catalog_format_mxn($ln['monto_esperado']); ?></td>
              <td class="monto"><?php echo catalog_format_mxn($ln['monto_pagado']); ?></td>
              <td class="monto"><strong><?php echo catalog_format_mxn($ln['saldo']); ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="ec-nota">Sin saldos pendientes en esta especialidad.</p>
    <?php endif; ?>
  <?php endforeach; ?>

  <h3 class="ec-section-title">Pagos registrados (colegiatura)</h3>
  <?php if (!empty($ec['pagos_inscripcion'])): ?>
    <p class="ec-nota" style="margin-bottom:8px;">
      <strong>Inscripción (independiente de colegiaturas):</strong>
      <?php
      $partesInsc = [];
      foreach ($ec['pagos_inscripcion'] as $pi) {
          $partesInsc[] = date('d/m/Y', strtotime($pi['creado_en']))
              . ' · ' . catalog_format_mxn((float) $pi['monto'])
              . (!empty($pi['folio']) ? ' · ' . htmlspecialchars((string) $pi['folio']) : '');
      }
      echo implode(' · ', $partesInsc);
      ?>
    </p>
  <?php endif; ?>
  <?php if (empty($ec['pagos_colegiatura'])): ?>
    <p class="ec-nota">Sin pagos de colegiatura registrados.</p>
  <?php else: ?>
    <table class="ec-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th>Periodo</th>
          <th>Folio</th>
          <th class="monto">Monto</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ec['pagos_colegiatura'] as $p): ?>
          <tr>
            <td><?php echo date('d/m/Y H:i', strtotime($p['creado_en'])); ?></td>
            <td><?php echo htmlspecialchars(pago_label_tipo($p['tipo'] ?? 'abono')); ?></td>
            <td><?php echo htmlspecialchars($p['periodo_ref'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($p['folio'] ?? '—'); ?></td>
            <td class="monto"><?php echo catalog_format_mxn((float)$p['monto']); ?></td>
            <td><?php echo htmlspecialchars($p['cubrio'] ?? $p['concepto'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" style="text-align:right;"><strong>Total</strong></td>
          <td class="monto"><strong><?php echo catalog_format_mxn($r['colegiatura_pagada']); ?></strong></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>

  <h3 class="ec-section-title">Productos y otros (no afectan colegiatura)</h3>
  <div class="ec-productos">
    <?php if (empty($ec['pagos_productos'])): ?>
      <p class="ec-nota" style="margin:0;">Sin movimientos de productos.</p>
    <?php else: ?>
      <table class="ec-table">
        <thead>
          <tr><th>Fecha</th><th>Concepto</th><th class="monto">Monto</th></tr>
        </thead>
        <tbody>
          <?php foreach ($ec['pagos_productos'] as $p): ?>
            <tr>
              <td><?php echo date('d/m/Y', strtotime($p['creado_en'])); ?></td>
              <td><?php echo htmlspecialchars($p['producto_nombre'] ?? $p['concepto'] ?? 'Producto'); ?></td>
              <td class="monto"><?php echo catalog_format_mxn((float)$p['monto']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="ec-nota">Total productos: <strong><?php echo catalog_format_mxn($r['productos_pagados']); ?></strong> (informativo).</p>
    <?php endif; ?>
  </div>

  <div style="margin-top:24px; padding-top:12px; border-top:2px solid #1a237e;">
    <table class="ec-table" style="border:none;">
      <tr style="font-size:1.1rem;">
        <td style="border:none;"><strong>ADEUDO TOTAL COLEGIATURA</strong></td>
        <td class="monto" style="border:none;"><strong><?php echo catalog_format_mxn($r['adeudo_colegiatura']); ?></strong></td>
      </tr>
    </table>
    <p class="ec-nota" style="margin-top:12px;">
      Documento generado el <?php echo date('d/m/Y H:i'); ?> — Sistema HAY CNCM.
    </p>
  </div>
</div>
