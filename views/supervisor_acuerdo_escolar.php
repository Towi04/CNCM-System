<?php

require_once __DIR__ . '/../config.php';

if (!acuerdo_escolar_puede_publicar()) {

    echo '<div class="alert">Sin permiso para gestionar el acuerdo escolar.</div>';

    return;

}



acuerdo_escolar_ensure_schema($pdo);

$activo = acuerdo_version_activo_nuevos($pdo);

$versiones = acuerdo_escolar_listar($pdo);

$apiUrl = hay_asset_url('php/acuerdo_escolar_api.php');

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/supervisor_acuerdo_escolar.css'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="catalog-wrap acuerdo-supervisor-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-file-signature"></i> Acuerdo escolar</h2>

    <p class="acuerdo-supervisor-intro">

      Publique una nueva versión cuando cambien las políticas. Los alumnos activos deberán aceptarla al entrar al portal.

    </p>

  </div>



  <?php if ($activo): ?>

  <div class="welcome-card acuerdo-activo-card">

    <h3>Versión vigente para nuevos: <?php echo htmlspecialchars($activo['version_label']); ?></h3>

    <p class="acuerdo-meta">

      Desde <?php echo htmlspecialchars($activo['vigente_desde'] ?? ''); ?>

      · Publicada <?php echo htmlspecialchars(substr((string) ($activo['creado_en'] ?? ''), 0, 16)); ?>

    </p>

    <div class="acuerdo-preview"><?php echo acuerdo_escolar_render_contenido((string) ($activo['contenido'] ?? '')); ?></div>

  </div>

  <?php endif; ?>



  <div class="welcome-card" style="margin-top:16px; padding:20px;">

    <h3 style="margin-top:0;">Publicar nueva versión</h3>

    <form id="form-acuerdo-publicar" novalidate>

      <label for="acuerdo-version-label">Etiqueta de versión</label>

      <input type="text" id="acuerdo-version-label" name="version_label" maxlength="40"

        placeholder="Ej. v2026.06" value="<?php echo htmlspecialchars('v' . date('Y.m'), ENT_QUOTES, 'UTF-8'); ?>"

        style="width:100%; max-width:280px; padding:8px; margin-bottom:12px;">



      <label for="acuerdo-contenido">Texto del acuerdo</label>

      <textarea id="acuerdo-contenido" name="contenido" rows="16" class="acuerdo-editor-textarea"><?php
        echo htmlspecialchars((string) ($activo['contenido'] ?? ''), ENT_QUOTES, 'UTF-8');
      ?></textarea>



      <p style="color:#b45309; font-size:0.9rem; margin:12px 0;">

        <i class="fas fa-exclamation-triangle"></i>

        Al publicar, todos los alumnos activos deberán volver a aceptar este documento.

      </p>



      <button type="submit" class="primary" id="btn-acuerdo-publicar">

        <i class="fas fa-upload"></i> Publicar acuerdo

      </button>

      <div id="acuerdo-publicar-msg" class="asist-checada-msg" style="display:none; margin-top:12px;"></div>

    </form>

  </div>



  <div class="welcome-card" style="margin-top:16px; padding:16px;">

    <h3 style="margin-top:0;">Historial de versiones</h3>

    <?php if (empty($versiones)): ?>

      <p style="color:#888;">Sin versiones registradas.</p>

    <?php else: ?>

      <table class="catalog-table">

        <thead>

          <tr>

            <th>Versión</th>

            <th>Vigente desde</th>

            <th>Activa nuevos</th>

            <th>Aceptaciones</th>

            <th>Publicada</th>

          </tr>

        </thead>

        <tbody>

          <?php foreach ($versiones as $v): ?>

          <tr>

            <td><strong><?php echo htmlspecialchars($v['version_label']); ?></strong></td>

            <td><?php echo htmlspecialchars($v['vigente_desde'] ?? '—'); ?></td>

            <td><?php echo (int) ($v['activo_para_nuevos'] ?? 0) === 1 ? 'Sí' : '—'; ?></td>

            <td><?php echo (int) ($v['num_aceptaciones'] ?? 0); ?></td>

            <td><?php echo htmlspecialchars(substr((string) ($v['creado_en'] ?? ''), 0, 16)); ?></td>

          </tr>

          <?php endforeach; ?>

        </tbody>

      </table>

    <?php endif; ?>

  </div>

</div>



<script>

  window.HayAcuerdoSupervisor = { api: <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?> };

</script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/supervisor_acuerdo_escolar.js?v=20260719'), ENT_QUOTES, 'UTF-8'); ?>"></script>


