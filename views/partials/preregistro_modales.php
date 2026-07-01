<?php
/** Modales de estado pre-registro (UTF-8). */
$labels = $labels ?? preregistro_labels();
?>
<div class="catalog-modal" id="modal-perdido">
  <div class="catalog-modal__panel">
    <h3 style="margin-top:0;">Marcar como perdido</h3>
    <p style="color:#666;">Esta información se usará después en gráficas de por qué perdemos prospectos.</p>
    <input type="hidden" id="perdido-id" value="0">
    <div style="margin-bottom:12px;">
      <label><strong>Categoría</strong></label>
      <select id="perdido-categoria" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        <?php foreach ($labels['categoria_perdido'] as $k => $v): ?>
          <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="margin-bottom:12px;">
      <label><strong>Motivo (detalle)</strong></label>
      <textarea id="perdido-motivo" rows="3" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" placeholder="Ej. No contestó después de 3 llamadas…"></textarea>
    </div>
    <div style="display:flex; gap:10px; justify-content:flex-end;">
      <button type="button" class="secondary" id="btn-cerrar-perdido">Cancelar</button>
      <button type="button" class="danger" id="btn-confirmar-perdido">Confirmar perdido</button>
    </div>
  </div>
</div>

<div class="catalog-modal" id="modal-apartado">
  <div class="catalog-modal__panel">
    <h3 style="margin-top:0;">Registrar apartado</h3>
    <p style="color:#666;">Indique el monto que dejó el prospecto. Al guardar se generará un comprobante para imprimir.</p>
    <input type="hidden" id="apartado-id" value="0">
    <div style="margin-bottom:12px;">
      <label><strong>Monto ($)</strong></label>
      <input type="number" id="apartado-monto" min="0.01" step="0.01" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" placeholder="0.00">
    </div>
    <div style="margin-bottom:12px;">
      <label><strong>Forma de pago</strong></label>
      <select id="apartado-forma-pago" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        <option value="Efectivo">Efectivo</option>
        <option value="Transferencia">Transferencia</option>
        <option value="Tarjeta débito">Tarjeta débito</option>
        <option value="Tarjeta crédito">Tarjeta crédito</option>
      </select>
    </div>
    <div style="display:flex; gap:10px; justify-content:flex-end;">
      <button type="button" class="secondary" id="btn-cerrar-apartado">Cancelar</button>
      <button type="button" class="primary" id="btn-confirmar-apartado">Guardar e imprimir</button>
    </div>
  </div>
</div>

<div class="catalog-modal" id="modal-pendiente">
  <div class="catalog-modal__panel">
    <h3 style="margin-top:0;">Marcar como pendiente</h3>
    <p style="color:#666;">Indique el motivo del seguimiento. Opcionalmente programe cuándo desea que el sistema le recuerde contactar al prospecto.</p>
    <input type="hidden" id="pendiente-id" value="0">
    <div style="margin-bottom:12px;">
      <label><strong>¿Por qué queda pendiente?</strong></label>
      <select id="pendiente-categoria" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        <?php foreach ($labels['categoria_pendiente'] as $k => $v): ?>
          <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="margin-bottom:12px;">
      <label><strong>Observaciones / detalle</strong></label>
      <textarea id="pendiente-motivo" rows="3" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" placeholder="Ej. Volver a llamar después del quincena, pidió información de horarios…"></textarea>
    </div>
    <div style="margin-bottom:12px;">
      <label><strong>Fecha de recordatorio</strong> <span style="color:#888; font-weight:normal;">(opcional)</span></label>
      <input type="date" id="pendiente-recordatorio" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      <p style="font-size:0.82rem; color:#666; margin:6px 0 0;">El sistema mostrará una alerta en esa fecha (o antes si ya pasó) para que contacte al prospecto.</p>
    </div>
    <div style="display:flex; gap:10px; justify-content:flex-end;">
      <button type="button" class="secondary" id="btn-cerrar-pendiente">Cancelar</button>
      <button type="button" class="primary" id="btn-confirmar-pendiente">Guardar pendiente</button>
    </div>
  </div>
</div>
