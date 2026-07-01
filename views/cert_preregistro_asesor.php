<?php

require_once __DIR__ . '/../config.php';

if (!certificacion_puede_preregistro_asesor()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';

    return;

}

?>

<link rel="stylesheet" href="css/admin_catalogo.css">

<link rel="stylesheet" href="css/certificaciones.css">

<div class="catalog-wrap cert-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-certificate"></i> Pre-registro certificaciones</h2>

    <p style="color:#666; margin:0;">Registro rápido para el asesor. Al confirmar pago se crea el alumno y su usuario para subir documentos.</p>

  </div>



  <div id="cpr-msg" class="catalog-alert" hidden></div>



  <div class="catalog-toolbar">

    <div class="field" style="flex:1;">

      <label>Certificación</label>

      <select id="cpr-producto"><option value="">Cargando…</option></select>

    </div>

    <button type="button" class="primary" id="cpr-btn-nuevo" disabled>Nuevo registro</button>

  </div>



  <div id="cpr-form-wrap" hidden>

    <h3 style="margin:16px 0 8px;">Datos del solicitante</h3>

    <form id="cpr-form" class="catalog-toolbar" style="flex-wrap:wrap;" data-no-global-ajax>

      <input type="hidden" name="id_producto" id="cpr-id-producto">

      <div class="field"><label>Nombre(s) *</label><input name="nombres" required></div>

      <div class="field"><label>Apellido paterno *</label><input name="apellido_paterno" required></div>

      <div class="field"><label>Apellido materno</label><input name="apellido_materno"></div>

      <div class="field"><label>Teléfono</label><input name="telefono" type="tel"></div>

      <div class="field"><label>Email</label><input type="email" name="email"></div>

      <div id="cpr-campos-extra" style="width:100%; display:flex; flex-wrap:wrap; gap:12px;"></div>

      <p id="cpr-precio-ref" style="width:100%; margin:0; color:#666; font-size:0.9rem;" hidden></p>

      <div class="field" style="width:100%;">

        <label>Notas</label>

        <textarea name="notas" rows="2" style="width:100%;"></textarea>

      </div>

      <button type="submit" class="primary">Registrar solicitud</button>

      <p style="color:#666; font-size:0.85rem; width:100%;">

        El cobro e inscripción definitiva se enlazarán al punto de venta en la siguiente fase.

        Ya se crea usuario alumno para documentación cuando aplique.

      </p>

    </form>

  </div>

</div>

<script>

window.HAY_CERT_PREREG = <?php echo json_encode(['api' => hay_asset_url('php/certificacion_api.php')], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/cert_preregistro_asesor.js?v=20260611'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<script>if (window.hayCertPreregistroInit) window.hayCertPreregistroInit();</script>

