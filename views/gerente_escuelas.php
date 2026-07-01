<?php

require_once __DIR__ . '/../config.php';

if (!escuelas_puede_gestionar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

escuelas_ensure_schema($pdo);
$idPlantel = plantel_scope_id($pdo);
$asesores = [];
$st = $pdo->prepare(
    "SELECT id_usuario, nombre, apellido FROM usuarios
     WHERE id_plantel = ? AND rol = 'asesor' AND (suspendido IS NULL OR suspendido = 0)
     ORDER BY nombre, apellido"
);
$st->execute([$idPlantel]);
$asesores = $st->fetchAll(PDO::FETCH_ASSOC);

?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/gerente_escuelas.css">

<div class="catalog-wrap ge-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-school"></i> Escuelas externas</h2>
    <p style="color:#666; margin:0;">Catálogo de escuelas para campaña de cartas y registro de visitas.</p>
  </div>

  <div id="ge-msg" class="catalog-alert" style="display:none; margin-bottom:12px;"></div>

  <div class="ge-layout">
    <section class="ge-panel">
      <h3>Escuelas</h3>
      <button type="button" class="primary" id="ge-nueva">Nueva escuela</button>
      <div id="ge-lista-escuelas" class="ge-list"></div>
    </section>

    <section class="ge-panel">
      <h3 id="ge-form-titulo">Datos de escuela</h3>
      <form id="ge-form-escuela" class="ge-form">
        <input type="hidden" name="id_escuela" id="ge-id-escuela" value="0">
        <div class="field"><label>Nombre *</label><input type="text" name="nombre" id="ge-nombre" required maxlength="200"></div>
        <div class="field"><label>Dirección</label><input type="text" name="direccion" maxlength="255"></div>
        <div class="ge-grid-2">
          <div class="field"><label>Colonia</label><input type="text" name="colonia" maxlength="120"></div>
          <div class="field"><label>Municipio</label><input type="text" name="municipio" maxlength="120"></div>
        </div>
        <div class="ge-grid-2">
          <div class="field"><label>Contacto</label><input type="text" name="contacto_nombre" maxlength="160"></div>
          <div class="field"><label>Teléfono contacto</label><input type="text" name="contacto_telefono" maxlength="30"></div>
        </div>
        <label><input type="checkbox" name="activo" value="1" checked> Activa</label>
        <div style="margin-top:10px;">
          <button type="submit" class="primary">Guardar escuela</button>
          <button type="button" id="ge-cancelar" class="secondary" style="margin-left:8px;">Limpiar</button>
        </div>
      </form>

      <hr style="margin:20px 0;">

      <h3>Registrar visita</h3>
      <form id="ge-form-visita" class="ge-form">
        <div class="field">
          <label>Escuela</label>
          <select name="id_escuela" id="ge-visita-escuela" required>
            <option value="">Seleccione</option>
          </select>
        </div>
        <div class="ge-grid-2">
          <div class="field"><label>Fecha visita</label><input type="date" name="fecha_visita" required></div>
          <div class="field"><label>Cartas entregadas</label><input type="number" name="cartas_entregadas" min="0" value="0"></div>
        </div>
        <div class="field">
          <label>Asesor</label>
          <select name="id_usuario_asesor">
            <?php foreach ($asesores as $a): ?>
              <option value="<?php echo (int) $a['id_usuario']; ?>"><?php echo htmlspecialchars(trim($a['nombre'] . ' ' . $a['apellido'])); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Notas</label><textarea name="notas" rows="2" maxlength="500"></textarea></div>
        <button type="submit" class="primary">Guardar visita</button>
      </form>
    </section>
  </div>
</div>

<script>
window.HAY_GERENTE_ESCUELAS = <?php echo json_encode([
    'api' => hay_asset_url('php/escuelas_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/gerente_escuelas.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>if (window.hayGerenteEscuelasInit) window.hayGerenteEscuelasInit();</script>
