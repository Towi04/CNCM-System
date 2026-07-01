<?php
require_once __DIR__ . '/../config.php';
$idPlantel = plantel_scope_id($pdo);
$control = trim($_GET['control'] ?? $_GET['q'] ?? '');
$fechaCorte = trim($_GET['fecha'] ?? date('Y-m-d'));
$ec = null;
$alumno = null;

if ($control !== '') {
    $alumno = pago_buscar_alumno_control($pdo, $control, $idPlantel);
    if ($alumno) {
        $ec = pago_estado_cuenta($pdo, (int) $alumno['id_alumno'], $fechaCorte);
    }
}
?>
<link rel="stylesheet" href="css/estado_cuenta.css">
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="ec-wrap">
  <div class="ec-consulta-bar no-print">
    <div>
      <h2 style="margin:0 0 8px;"><i class="fas fa-calculator"></i> Consulta de adeudo</h2>
      <p style="margin:0; color:#666; font-size:0.9rem;">
        Suma abonos, aplica pronto pago (día <?php echo PAGO_DIA_PRONTO; ?>), varias especialidades. Productos aparte.
      </p>
    </div>
    <div style="flex:1; min-width:200px;">
      <label style="font-weight:600; font-size:0.85rem;"># Control o nombre</label>
      <input type="search" id="adeudo-control" value="<?php echo htmlspecialchars($control); ?>"
        placeholder="Ej. 12557" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
    </div>
    <div>
      <label style="font-weight:600; font-size:0.85rem;">Fecha de corte</label>
      <input type="date" id="adeudo-fecha" value="<?php echo htmlspecialchars($fechaCorte); ?>"
        style="padding:10px; border:1px solid #ddd; border-radius:8px;">
    </div>
    <div>
      <button type="button" class="primary" id="btn-buscar-adeudo">Consultar</button>
    </div>
  </div>

  <?php if ($control !== '' && !$alumno): ?>
    <div class="catalog-alert catalog-alert--error">No se encontró alumno con «<?php echo htmlspecialchars($control); ?>».</div>
  <?php endif; ?>

  <?php if ($ec && $ec['ok']):
    $calMsg = calendario_mensaje_para_alumno($pdo, (int) $alumno['id_alumno'], $fechaCorte, $idPlantel);
  ?>
    <?php if ($calMsg): ?>
    <div class="catalog-alert catalog-alert--<?php echo $calMsg['tipo'] === 'cierre' ? 'error' : 'warn'; ?>" style="margin-bottom:14px;">
      <strong><?php echo htmlspecialchars($calMsg['titulo']); ?></strong><br>
      <?php echo htmlspecialchars($calMsg['mensaje']); ?>
      <?php if (!$calMsg['plantel_abierto']): ?>
        <br><em>El plantel está cerrado; no se recomienda registrar pagos presenciales este día.</em>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="no-print" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
      <button type="button" class="primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir para padre/tutor</button>
      <button type="button" class="info" onclick="cargarSeccion('alumno_detalle', 'id=<?php echo (int)$alumno['id_alumno']; ?>')">Ver perfil del alumno</button>
      <button type="button" class="primary" id="btn-toggle-registro-pago">Registrar abono</button>
    </div>

    <div id="panel-registro-pago" class="catalog-modal__panel no-print" style="display:none; max-width:480px; margin-bottom:16px; padding:16px; background:#f9f9f9; border-radius:10px;">
      <h3 style="margin-top:0;">Registrar pago / abono</h3>
      <form id="form-registro-pago">
        <input type="hidden" name="id_alumno" value="<?php echo (int)$alumno['id_alumno']; ?>">
        <input type="hidden" name="numero_control" value="<?php echo htmlspecialchars($control); ?>">
        <div style="display:grid; gap:10px;">
          <div>
            <label>Tipo</label>
            <select name="tipo" style="width:100%; padding:8px;">
              <option value="abono">Abono (sin periodo específico)</option>
              <option value="inscripcion">Inscripción</option>
              <option value="mensualidad">Mensualidad</option>
              <option value="semanal">Semanal</option>
              <option value="producto">Producto (no colegiatura)</option>
            </select>
          </div>
          <div>
            <label>Especialidad</label>
            <select name="id_especialidad" id="pago-id-especialidad" style="width:100%; padding:8px;">
              <option value="" data-ae="">— General —</option>
              <?php foreach (pago_inscripciones_alumno($pdo, (int)$alumno['id_alumno']) as $row): ?>
                <option value="<?php echo (int)$row['id_especialidad']; ?>"
                  data-ae="<?php echo (int)$row['id_alumno_especialidad']; ?>">
                  <?php echo htmlspecialchars($row['especialidad_nombre']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="id_alumno_especialidad" id="pago-id-ae" value="">
          </div>
          <div>
            <label>Periodo (opcional, ej. 2025-03 o INSCRIPCIÓN)</label>
            <input type="text" name="periodo_ref" placeholder="2025-03" style="width:100%; padding:8px;">
          </div>
          <div>
            <label>Monto ($)</label>
            <input type="number" name="monto" min="0.01" step="0.01" required style="width:100%; padding:8px;">
          </div>
          <div>
            <label>Folio</label>
            <input type="text" name="folio" style="width:100%; padding:8px;">
          </div>
          <div>
            <label>Fecha del pago</label>
            <input type="datetime-local" name="fecha_pago" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width:100%; padding:8px;">
          </div>
          <div>
            <label>Forma de pago</label>
            <select name="forma_pago_efectivo" style="width:100%; padding:8px;">
              <option>Efectivo</option>
              <option>Tarjeta débito</option>
              <option>Tarjeta crédito</option>
              <option>Transferencia</option>
            </select>
          </div>
          <div>
            <label>Notas / qué cubrió</label>
            <textarea name="cubrio" rows="2" style="width:100%; padding:8px;"></textarea>
          </div>
        </div>
        <button type="submit" class="primary" style="margin-top:12px;">Guardar pago</button>
      </form>
    </div>

    <?php include __DIR__ . '/partials/estado_cuenta_body.php'; ?>
  <?php endif; ?>
</div>

<script>
(function () {
  document.getElementById('btn-buscar-adeudo')?.addEventListener('click', () => {
    const p = new URLSearchParams();
    const c = document.getElementById('adeudo-control').value.trim();
    const f = document.getElementById('adeudo-fecha').value;
    if (c) p.set('control', c);
    if (f) p.set('fecha', f);
    cargarSeccion('consulta_adeudo', p);
  });
  document.getElementById('adeudo-control')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') document.getElementById('btn-buscar-adeudo').click();
  });
  document.getElementById('btn-toggle-registro-pago')?.addEventListener('click', () => {
    const p = document.getElementById('panel-registro-pago');
    if (p) p.style.display = p.style.display === 'none' ? 'block' : 'none';
  });
  document.getElementById('pago-id-especialidad')?.addEventListener('change', (e) => {
    const opt = e.target.selectedOptions[0];
    const ae = document.getElementById('pago-id-ae');
    if (ae) ae.value = opt?.dataset?.ae || '';
  });

  document.getElementById('form-registro-pago')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (fd.get('fecha_pago')) {
      fd.set('fecha_pago', fd.get('fecha_pago').replace('T', ' ') + ':00');
    }
    const res = await fetch('php/pago_registrar.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    alert(data.message || '');
    if (data.status === 'ok') cargarSeccion('consulta_adeudo', data.params ? Object.fromEntries(new URLSearchParams(data.params)) : {});
  });
})();
</script>
