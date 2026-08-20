<?php
require_once __DIR__ . '/../config.php';
if (
    !isset($_SESSION['user_id'])
    || !(plantel_es_admin() || (function_exists('rbac_cap') && rbac_cap('admin_planteles')))
) {
    echo '<div class="alert">Solo administradores pueden gestionar planteles.</div>';
    return;
}

$planteles = $pdo->query(
    'SELECT id_plantel, slug, nombre, activo, orden, razon_social, direccion, rfc, telefono, email_contacto, logo_url,
            cct, rvoe, prepa_nombre_sep, prepa_cct, prepa_rvoe, prepa_logo_url, prepa_direccion
     FROM planteles ORDER BY orden ASC, nombre ASC'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">

<div class="result-container">
  <div class="result-header">
    <h2><i class="fas fa-building"></i> Planteles</h2>
    <div class="disc-actions">
      <button type="button" class="primary" id="btn-nuevo-plantel">Nuevo plantel</button>
    </div>
  </div>

  <div class="patron-desc">
    <p style="color:#666; margin-top:0;">
      Cada plantel administra su propio personal, grupos y alumnos. El selector superior cambia la sede activa en sesi�n.
    </p>

    <div id="respuesta-planteles" style="display:none; margin:12px 0; padding:10px; border-radius:8px;"></div>

    <?php if (empty($planteles)): ?>
      <p>No hay planteles registrados.</p>
    <?php else: ?>
      <div class="hist-lines">
        <?php foreach ($planteles as $p): ?>
          <div class="hist-line" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
              <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
              <span style="color:#666;"> � <?php echo htmlspecialchars($p['slug']); ?></span>
              <?php if (!(int)$p['activo']): ?>
                <span style="color:#c62828; font-weight:700;"> � Inactivo</span>
              <?php endif; ?>
              <span style="color:#888;"> � Orden <?php echo (int)$p['orden']; ?></span>
            </div>
            <button type="button" class="btn-icon-only btn-icon-only--edit btn-editar-plantel" title="Editar" data-plantel='<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8"); ?>'>
              <i class="fas fa-pen"></i>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="modal-plantel" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:2000; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:12px; padding:24px; width:min(520px,92vw); max-height:90vh; overflow:auto;">
    <h3 id="modal-plantel-titulo" style="margin-top:0;">Plantel</h3>
    <form id="form-plantel" action="php/plantel_save.php" method="POST">
      <input type="hidden" name="id_plantel" id="plantel-id" value="0">
      <div style="margin-bottom:12px;">
        <label><strong>Nombre</strong></label><br>
        <input type="text" name="nombre" id="plantel-nombre" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>
      <div style="margin-bottom:12px;">
        <label><strong>Slug (URL / c�digo)</strong></label><br>
        <input type="text" name="slug" id="plantel-slug" placeholder="ej. salamanca" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>
      <fieldset style="border:1px solid #e5e5e5; border-radius:8px; padding:12px 14px; margin:0 0 14px;">
        <legend style="font-weight:700; padding:0 6px;">Datos para ticket de pago</legend>
        <p style="margin:0 0 10px; color:#666; font-size:0.85rem;">Aparecen en el comprobante impreso (impresora t�rmica 80 mm).</p>
        <div style="margin-bottom:10px;">
          <label><strong>Raz�n social / encabezado</strong></label><br>
          <input type="text" name="razon_social" id="plantel-razon" placeholder="GRUPO EDUCATIVO CNCM" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        </div>
        <div style="margin-bottom:10px;">
          <label><strong>Direcci�n</strong></label><br>
          <input type="text" name="direccion" id="plantel-direccion" placeholder="Calle, colonia, ciudad, estado" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
          <div>
            <label><strong>RFC</strong></label><br>
            <input type="text" name="rfc" id="plantel-rfc" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
          </div>
          <div>
            <label><strong>Tel�fono</strong></label><br>
            <input type="text" name="telefono" id="plantel-telefono" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
          </div>
        </div>
        <div style="margin-bottom:10px;">
          <label><strong>Correo de contacto (pie del ticket)</strong></label><br>
          <input type="email" name="email_contacto" id="plantel-email" placeholder="corporativo@cncm.com.mx" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        </div>
        <div>
          <label><strong>Logo (ruta opcional)</strong></label><br>
          <input type="text" name="logo_url" id="plantel-logo" placeholder="src/logo.png" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        </div>
      </fieldset>
      <fieldset style="border:1px solid #e5e5e5; border-radius:8px; padding:12px 14px; margin:0 0 14px;">
        <legend style="font-weight:700; padding:0 6px;">CCT / RVOE y Prepa escolarizada SEP</legend>
        <p style="margin:0 0 10px; color:#666; font-size:0.85rem;">
          Los datos generales se usan en credenciales. Los datos SEP sustituyen el encabezado CNCM en listas de grupos PE.
        </p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
          <div>
            <label><strong>CCT general</strong></label><br>
            <input type="text" name="cct" id="plantel-cct" maxlength="40" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
          </div>
          <div>
            <label><strong>RVOE general</strong></label><br>
            <input type="text" name="rvoe" id="plantel-rvoe" maxlength="80" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
          </div>
        </div>
        <div style="margin-bottom:10px;">
          <label><strong>Nombre de prepa registrado ante SEP</strong></label><br>
          <input type="text" name="prepa_nombre_sep" id="plantel-prepa-nombre" maxlength="160" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
          <div>
            <label><strong>CCT prepa</strong></label><br>
            <input type="text" name="prepa_cct" id="plantel-prepa-cct" maxlength="40" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
          </div>
          <div>
            <label><strong>RVOE prepa</strong></label><br>
            <input type="text" name="prepa_rvoe" id="plantel-prepa-rvoe" maxlength="80" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
          </div>
        </div>
        <div style="margin-bottom:10px;">
          <label><strong>Logo prepa (ruta o URL)</strong></label><br>
          <input type="text" name="prepa_logo_url" id="plantel-prepa-logo" maxlength="255" placeholder="uploads/planteles/prepa.png" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        </div>
        <div>
          <label><strong>Dirección prepa (opcional)</strong></label><br>
          <input type="text" name="prepa_direccion" id="plantel-prepa-direccion" maxlength="255" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        </div>
      </fieldset>
      <div style="margin-bottom:12px;">
        <label><strong>Orden en men�</strong></label><br>
        <input type="number" name="orden" id="plantel-orden" min="0" value="0" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>
      <label style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
        <input type="checkbox" name="activo" id="plantel-activo" value="1" checked>
        Activo
      </label>
      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" id="btn-cerrar-plantel">Cancelar</button>
        <button type="submit" class="primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('modal-plantel');
  const form = document.getElementById('form-plantel');
  const msg = document.getElementById('respuesta-planteles');

  function abrirModal(data) {
    document.getElementById('modal-plantel-titulo').textContent = data ? 'Editar plantel' : 'Nuevo plantel';
    document.getElementById('plantel-id').value = data ? data.id_plantel : '0';
    document.getElementById('plantel-nombre').value = data ? data.nombre : '';
    document.getElementById('plantel-slug').value = data ? data.slug : '';
    document.getElementById('plantel-razon').value = data ? (data.razon_social || 'GRUPO EDUCATIVO CNCM') : 'GRUPO EDUCATIVO CNCM';
    document.getElementById('plantel-direccion').value = data ? (data.direccion || '') : '';
    document.getElementById('plantel-rfc').value = data ? (data.rfc || '') : '';
    document.getElementById('plantel-telefono').value = data ? (data.telefono || '') : '';
    document.getElementById('plantel-email').value = data ? (data.email_contacto || 'corporativo@cncm.com.mx') : 'corporativo@cncm.com.mx';
    document.getElementById('plantel-logo').value = data ? (data.logo_url || '') : '';
    document.getElementById('plantel-cct').value = data ? (data.cct || '') : '';
    document.getElementById('plantel-rvoe').value = data ? (data.rvoe || '') : '';
    document.getElementById('plantel-prepa-nombre').value = data ? (data.prepa_nombre_sep || '') : '';
    document.getElementById('plantel-prepa-cct').value = data ? (data.prepa_cct || '') : '';
    document.getElementById('plantel-prepa-rvoe').value = data ? (data.prepa_rvoe || '') : '';
    document.getElementById('plantel-prepa-logo').value = data ? (data.prepa_logo_url || '') : '';
    document.getElementById('plantel-prepa-direccion').value = data ? (data.prepa_direccion || '') : '';
    document.getElementById('plantel-orden').value = data ? data.orden : '0';
    document.getElementById('plantel-activo').checked = data ? Number(data.activo) === 1 : true;
    modal.style.display = 'flex';
  }

  function cerrarModal() {
    modal.style.display = 'none';
  }

  document.getElementById('btn-nuevo-plantel')?.addEventListener('click', () => abrirModal(null));
  document.getElementById('btn-cerrar-plantel')?.addEventListener('click', cerrarModal);

  document.querySelectorAll('.btn-editar-plantel').forEach((btn) => {
    btn.addEventListener('click', () => {
      try {
        abrirModal(JSON.parse(btn.getAttribute('data-plantel')));
      } catch (e) { console.error(e); }
    });
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    if (!document.getElementById('plantel-activo').checked) {
      fd.delete('activo');
    }
    const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    if (msg) {
      msg.style.display = 'block';
      msg.className = data.status === 'ok' ? 'mensaje-exito' : 'mensaje-error';
      msg.textContent = data.message || (data.status === 'ok' ? 'Guardado' : 'Error');
    }
    if (data.status === 'ok' && data.seccion) {
      cerrarModal();
      cargarSeccion(data.seccion);
    }
  });
})();
</script>
