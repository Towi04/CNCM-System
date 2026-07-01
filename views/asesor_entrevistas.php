<?php

require_once __DIR__ . '/../config.php';

if (!asesor_puede_entrevistas()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

$puedeAjena = asesor_puede_registrar_entrevista_ajena();
$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);
$asesoresEquipo = $puedeAjena ? asesor_entrevistas_opciones_asesor($pdo, $idPlantel, $idUsuario) : [];
?>

<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-handshake"></i> Entrevistas / información dada</h2>

    <p style="color:#666; margin:0;">Registre contactos aunque solo tenga nombre y teléfono. Luego puede convertirlos en pre-registro.</p>

  </div>



  <div class="catalog-toolbar">

    <?php if ($puedeAjena && !empty($asesoresEquipo)): ?>
    <div class="field">
      <label>Asesor</label>
      <select id="ent-asesor" name="id_usuario_asesor">
        <?php foreach ($asesoresEquipo as $a): ?>
          <option value="<?php echo (int) $a['id_usuario']; ?>"<?php echo !empty($a['es_yo']) ? ' selected' : ''; ?>>
            <?php
            $lbl = trim(($a['nombre'] ?? '') . ' ' . ($a['apellido'] ?? ''));
            echo htmlspecialchars(!empty($a['es_yo']) ? 'A mi nombre (' . $lbl . ')' : $lbl);
            ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="field">

      <label>Periodo reporte</label>

      <select id="ent-periodo">

        <option value="dia">Hoy</option>

        <option value="semana" selected>Esta semana</option>

        <option value="mes">Este mes</option>

      </select>

    </div>

    <div class="field">

      <label>Filtrar lista</label>

      <select id="ent-filtro-estado">

        <option value="">Todas</option>

        <option value="contacto" selected>Solo contacto</option>

        <option value="preregistro">Con pre-registro</option>

        <option value="inscrito">Inscritos</option>

      </select>

    </div>

    <div class="field" style="align-self:flex-end;">

      <button type="button" class="primary" id="ent-btn-filtrar">Aplicar filtros</button>

    </div>

  </div>



  <div id="ent-stats" class="catalog-alert catalog-alert--ok" style="margin-bottom:14px; display:none;"></div>



  <div class="catalog-header" style="border:none; padding-top:0;">

    <h3 style="margin:0; font-size:1.1rem;">Nueva entrevista</h3>

  </div>

  <form id="ent-form" class="catalog-toolbar" style="align-items:flex-end;" data-no-global-ajax>
    <?php if ($puedeAjena && !empty($asesoresEquipo)): ?>
    <div class="field">
      <label>Registrar a nombre de</label>
      <select name="id_usuario_asesor" id="ent-form-asesor">
        <?php foreach ($asesoresEquipo as $a): ?>
          <option value="<?php echo (int) $a['id_usuario']; ?>"<?php echo !empty($a['es_yo']) ? ' selected' : ''; ?>>
            <?php
            $lbl = trim(($a['nombre'] ?? '') . ' ' . ($a['apellido'] ?? ''));
            echo htmlspecialchars(!empty($a['es_yo']) ? 'A mi nombre (' . $lbl . ')' : $lbl);
            ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="field"><label>Nombre *</label><input name="nombres" required></div>

    <div class="field"><label>Apellido paterno</label><input name="apellido_paterno"></div>

    <div class="field"><label>Teléfono</label><input name="telefono"></div>

    <div class="field" style="flex:1;"><label>Observaciones</label><input name="observaciones"></div>

    <button type="submit" class="primary">Registrar</button>

  </form>



  <div class="catalog-table-wrap hay-dt-panel">

    <table class="catalog-table" id="ent-tabla">

      <thead>

        <tr><th>Fecha</th><th>Nombre</th><th>Teléfono</th><th>Estado</th><th>Notas</th><th></th></tr>

      </thead>

      <tbody></tbody>

    </table>

  </div>

</div>

<script>

window.HAY_ENTREVISTAS = <?php echo json_encode([
    'api' => hay_asset_url('php/asesor_entrevista_api.php'),
    'idUsuario' => (int) ($_SESSION['user_id'] ?? 0),
    'puedeAjena' => $puedeAjena,
], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/asesor_entrevistas.js?v=20260612'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<script>if (window.hayEntrevistasInit) window.hayEntrevistasInit();</script>

