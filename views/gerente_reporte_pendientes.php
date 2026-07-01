<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_gerente_pendientes') && !rbac_cap('menu_gerente_perdidos')) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$tab = trim($_GET['tab'] ?? 'pendientes');
if (!in_array($tab, ['pendientes', 'perdidos', 'asesores'], true)) {
    $tab = 'pendientes';
}

$desde = trim($_GET['desde'] ?? date('Y-m-01'));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$idAsesor = (int) ($_GET['id_asesor'] ?? 0);
$categoria = trim($_GET['categoria'] ?? '');
$soloVencidos = !empty($_GET['vencidos']);

$asesores = gerente_asesores_plantel($pdo, $idPlantel);
$labels = preregistro_labels();

$pendientes = gerente_reporte_pendientes($pdo, $idPlantel, [
    'id_asesor' => $idAsesor > 0 ? $idAsesor : null,
    'categoria_pendiente' => $categoria !== '' ? $categoria : null,
    'solo_vencidos' => $soloVencidos,
]);
$perdidos = gerente_reporte_perdidos($pdo, $idPlantel, $desde, $hasta, $idAsesor > 0 ? $idAsesor : null);
$labelsPend = $labels['categoria_pendiente'] ?? [];
$labelsPerd = $labels['categoria_perdido'] ?? [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-tasks"></i> Seguimiento del plantel</h2>
    <p style="color:#666;">Pendientes de todos los asesores y motivos por los que no se inscribieron.</p>
  </div>

  <div class="catalog-toolbar" style="gap:8px; flex-wrap:wrap; margin-bottom:12px;">
    <button type="button" class="<?php echo $tab === 'pendientes' ? 'primary' : 'secondary'; ?>" onclick="cargarSeccion('gerente_reporte_pendientes','tab=pendientes')">Pendientes</button>
    <button type="button" class="<?php echo $tab === 'perdidos' ? 'primary' : 'secondary'; ?>" onclick="cargarSeccion('gerente_reporte_pendientes','tab=perdidos')">No inscritos</button>
    <button type="button" class="<?php echo $tab === 'asesores' ? 'primary' : 'secondary'; ?>" onclick="cargarSeccion('gerente_reporte_pendientes','tab=asesores')">Por asesor</button>
    <button type="button" class="secondary" onclick="cargarSeccion('pre_registro_alumnos')"><i class="fas fa-list"></i> Lista completa</button>
  </div>

  <?php if ($tab === 'pendientes'): ?>
  <form class="catalog-toolbar" style="flex-wrap:wrap;" onsubmit="event.preventDefault(); var q='tab=pendientes'; var a=document.getElementById('gp-asesor').value; if(a) q+='&id_asesor='+a; var c=document.getElementById('gp-cat').value; if(c) q+='&categoria='+encodeURIComponent(c); if(document.getElementById('gp-vencidos').checked) q+='&vencidos=1'; cargarSeccion('gerente_reporte_pendientes', q);">
    <div>
      <label>Asesor</label>
      <select id="gp-asesor">
        <option value="">Todos</option>
        <?php foreach ($asesores as $a): ?>
          <option value="<?php echo (int) $a['id_usuario']; ?>"<?php echo $idAsesor === (int) $a['id_usuario'] ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars(trim($a['nombre'] . ' ' . $a['apellido'])); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Categoría pendiente</label>
      <select id="gp-cat">
        <option value="">Todas</option>
        <?php foreach ($labelsPend as $k => $v): ?>
          <option value="<?php echo htmlspecialchars($k); ?>"<?php echo $categoria === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="align-self:end;">
      <label><input type="checkbox" id="gp-vencidos"<?php echo $soloVencidos ? ' checked' : ''; ?>> Solo urgentes</label>
    </div>
    <div style="align-self:end;"><button type="submit" class="primary">Filtrar</button></div>
  </form>

  <p style="margin:12px 0;"><strong><?php echo (int) ($pendientes['total'] ?? 0); ?></strong> registro(s) activos o pendientes.</p>

  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead>
        <tr>
          <th>Prospecto</th>
          <th>Asesor</th>
          <th>Estado</th>
          <th>Motivo / categoría</th>
          <th>Curso</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pendientes['items'])): ?>
          <tr><td colspan="6" style="color:#888;">Sin pendientes con estos filtros.</td></tr>
        <?php else: foreach ($pendientes['items'] as $p): ?>
          <tr>
            <td><?php echo htmlspecialchars(preregistro_nombre_completo($p)); ?></td>
            <td><?php echo htmlspecialchars($p['asesor_nombre'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($labels['estado'][$p['estado'] ?? ''] ?? $p['estado'] ?? ''); ?></td>
            <td>
              <?php
              $cat = $labelsPend[$p['categoria_pendiente'] ?? ''] ?? '';
              echo htmlspecialchars($cat !== '' ? $cat : ($p['motivo_pendiente'] ?? '—'));
              if (!empty($p['espera_apertura_curso'])) {
                  echo ' <span style="color:#b45309;">(espera curso)</span>';
              }
              ?>
            </td>
            <td><?php echo htmlspecialchars($p['especialidad_nombre'] ?? '—'); ?></td>
            <td>
              <button type="button" class="secondary" onclick="cargarSeccion('pre_registro_nuevo','id=<?php echo (int) $p['id_preregistro']; ?>')">Abrir</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'perdidos'): ?>
  <form class="catalog-toolbar" style="flex-wrap:wrap;" onsubmit="event.preventDefault(); var q='tab=perdidos&desde='+document.getElementById('gp-desde').value+'&hasta='+document.getElementById('gp-hasta').value; var a=document.getElementById('gp-asesor2').value; if(a) q+='&id_asesor='+a; cargarSeccion('gerente_reporte_pendientes', q);">
    <div><label>Desde</label><input type="date" id="gp-desde" value="<?php echo htmlspecialchars($desde); ?>"></div>
    <div><label>Hasta</label><input type="date" id="gp-hasta" value="<?php echo htmlspecialchars($hasta); ?>"></div>
    <div>
      <label>Asesor</label>
      <select id="gp-asesor2">
        <option value="">Todos</option>
        <?php foreach ($asesores as $a): ?>
          <option value="<?php echo (int) $a['id_usuario']; ?>"<?php echo $idAsesor === (int) $a['id_usuario'] ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars(trim($a['nombre'] . ' ' . $a['apellido'])); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="align-self:end;"><button type="submit" class="primary">Actualizar</button></div>
  </form>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-top:12px;">
    <div class="welcome-card" style="padding:14px;">
      <h4>Por motivo</h4>
      <?php if (empty($perdidos['por_motivo'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <ul><?php foreach ($perdidos['por_motivo'] as $m): ?>
          <li><?php echo htmlspecialchars($labelsPerd[$m['motivo'] ?? ''] ?? $m['motivo'] ?? ''); ?>: <strong><?php echo (int) $m['total']; ?></strong></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <div class="welcome-card" style="padding:14px;">
      <h4>Por asesor</h4>
      <?php if (empty($perdidos['por_asesor'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <ul><?php foreach ($perdidos['por_asesor'] as $m): ?>
          <li><?php echo htmlspecialchars($m['asesor'] ?? ''); ?>: <strong><?php echo (int) $m['total']; ?></strong></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="catalog-table-wrap" style="margin-top:16px;">
    <table class="catalog-table">
      <thead>
        <tr><th>Prospecto</th><th>Asesor</th><th>Motivo</th><th>Detalle</th><th>Curso</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($perdidos['items'])): ?>
          <tr><td colspan="6" style="color:#888;">Sin registros perdidos en el periodo.</td></tr>
        <?php else: foreach ($perdidos['items'] as $p): ?>
          <tr>
            <td><?php echo htmlspecialchars(preregistro_nombre_completo($p)); ?></td>
            <td><?php echo htmlspecialchars($p['asesor_nombre'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($labelsPerd[$p['categoria_perdido'] ?? ''] ?? $p['categoria_perdido'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars(mb_substr((string) ($p['motivo_perdido'] ?? ''), 0, 80)); ?></td>
            <td><?php echo htmlspecialchars($p['especialidad_nombre'] ?? '—'); ?></td>
            <td>
              <button type="button" class="secondary" onclick="cargarSeccion('pre_registro_nuevo','id=<?php echo (int) $p['id_preregistro']; ?>')">Ver</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'asesores'): ?>
  <p style="color:#666; margin-bottom:12px;">Carga de pendientes activos por asesor del plantel.</p>
  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead><tr><th>Asesor</th><th>Pendientes</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($pendientes['resumen_asesor'])): ?>
          <tr><td colspan="3" style="color:#888;">Sin pendientes.</td></tr>
        <?php else: foreach ($pendientes['resumen_asesor'] as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['asesor'] ?? ''); ?></td>
            <td><strong><?php echo (int) ($r['total'] ?? 0); ?></strong></td>
            <td>
              <button type="button" class="secondary" onclick="cargarSeccion('gerente_reporte_pendientes','tab=pendientes&id_asesor=<?php echo (int) $r['id_usuario']; ?>')">Ver lista</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
