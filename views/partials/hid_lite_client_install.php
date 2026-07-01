<?php
/**
 * Banner: descarga del HID Authentication Device Client (driver U.areU).
 * @var string $hid_banner_context Contexto corto (ej. checada, enrolamiento)
 */
$hidLinks = hay_hid_lite_client_links();
$ctx = $hid_banner_context ?? 'lector U.areU 5300';
?>
<div class="hid-lite-client-banner" role="note">
  <div class="hid-lite-client-banner__icon" aria-hidden="true"><i class="fas fa-download"></i></div>
  <div class="hid-lite-client-banner__body">
    <strong>Driver del lector (solo Windows en recepción)</strong>
    <p>
      Para <?php echo htmlspecialchars($ctx); ?> instale el
      <strong>HID Authentication Device Client</strong> en esta PC.
      Debe quedar en ejecución (icono en la bandeja de Windows) y el lector conectado por USB.
    </p>
    <div class="hid-lite-client-banner__actions">
      <a class="primary hid-lite-client-btn" href="<?php echo htmlspecialchars($hidLinks['url'], ENT_QUOTES, 'UTF-8'); ?>"
        target="_blank" rel="noopener noreferrer">
        <i class="fas fa-external-link-alt"></i> Descargar driver HID (oficial)
      </a>
      <?php if (!empty($hidLinks['local_url'])): ?>
      <a class="secondary hid-lite-client-btn" href="<?php echo htmlspecialchars($hidLinks['local_url'], ENT_QUOTES, 'UTF-8'); ?>"
        download>
        <i class="fas fa-file-download"></i> <?php echo htmlspecialchars($hidLinks['local_label']); ?>
      </a>
      <?php endif; ?>
    </div>
    <p class="hid-lite-client-banner__hint">
      Enlace oficial recomendado (siempre la versión más reciente de HID).
      <?php if (empty($hidLinks['local_url'])): ?>
      Copia local opcional: coloque el .exe en <code>downloads/hid/</code> del servidor.
      <?php endif; ?>
    </p>
  </div>
</div>
