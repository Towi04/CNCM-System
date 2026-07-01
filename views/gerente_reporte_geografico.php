<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_gerente_reportes')) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$desde = trim($_GET['desde'] ?? date('Y-m-01'));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$fuente = trim($_GET['fuente'] ?? 'ambos');
if (!in_array($fuente, ['ambos', 'preregistros', 'inscritos'], true)) {
    $fuente = 'ambos';
}

$rep = gerente_reporte_geografico($pdo, $idPlantel, $desde, $hasta, $fuente);
$res = $rep['resumen'] ?? [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-map-marked-alt"></i> Reporte geográfico</h2>
    <p style="color:#666;">Distribución por municipio, colonia y código postal (datos del pre-registro vinculado).</p>
  </div>

  <form class="catalog-toolbar" style="flex-wrap:wrap;" onsubmit="event.preventDefault(); cargarSeccion('gerente_reporte_geografico','desde='+encodeURIComponent(document.getElementById('gg-desde').value)+'&hasta='+encodeURIComponent(document.getElementById('gg-hasta').value)+'&fuente='+encodeURIComponent(document.getElementById('gg-fuente').value));">
    <div>
      <label>Desde</label>
      <input type="date" id="gg-desde" value="<?php echo htmlspecialchars($desde); ?>">
    </div>
    <div>
      <label>Hasta</label>
      <input type="date" id="gg-hasta" value="<?php echo htmlspecialchars($hasta); ?>">
    </div>
    <div>
      <label>Mostrar</label>
      <select id="gg-fuente">
        <option value="ambos"<?php echo $fuente === 'ambos' ? ' selected' : ''; ?>>Pre-registros e inscritos</option>
        <option value="preregistros"<?php echo $fuente === 'preregistros' ? ' selected' : ''; ?>>Solo pre-registros</option>
        <option value="inscritos"<?php echo $fuente === 'inscritos' ? ' selected' : ''; ?>>Solo inscritos</option>
      </select>
    </div>
    <div style="align-self:end;">
      <button type="submit" class="primary">Actualizar</button>
    </div>
  </form>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin-top:16px;">
    <?php if ($fuente !== 'inscritos'): ?>
    <div class="welcome-card" style="padding:14px;">
      <div style="font-size:0.85rem;color:#666;">Pre-registros</div>
      <div style="font-size:1.6rem;font-weight:700;color:#1565c0;"><?php echo (int) ($res['preregistros_total'] ?? 0); ?></div>
      <div style="font-size:0.82rem;color:#555;">
        Con municipio: <?php echo (int) ($res['preregistros_con_municipio'] ?? 0); ?>
        (<?php echo gerente_geo_pct((int) ($res['preregistros_con_municipio'] ?? 0), (int) ($res['preregistros_total'] ?? 0)); ?>)
      </div>
    </div>
    <?php endif; ?>
    <?php if ($fuente !== 'preregistros'): ?>
    <div class="welcome-card" style="padding:14px;">
      <div style="font-size:0.85rem;color:#666;">Inscritos</div>
      <div style="font-size:1.6rem;font-weight:700;color:#2e7d32;"><?php echo (int) ($res['inscritos_total'] ?? 0); ?></div>
      <div style="font-size:0.82rem;color:#555;">
        Con municipio: <?php echo (int) ($res['inscritos_con_municipio'] ?? 0); ?>
        (<?php echo gerente_geo_pct((int) ($res['inscritos_con_municipio'] ?? 0), (int) ($res['inscritos_total'] ?? 0)); ?>)
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($fuente !== 'inscritos'): ?>
  <h3 style="margin:24px 0 12px;"><i class="fas fa-bookmark"></i> Pre-registros</h3>
  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px;">
    <div class="welcome-card" style="padding:16px;">
      <h4 style="margin:0 0 10px;">Por municipio</h4>
      <?php if (empty($rep['municipios_prereg'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <table class="catalog-table">
          <thead><tr><th>Municipio</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($rep['municipios_prereg'] as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['municipio'] ?? '')); ?></td>
              <td><strong><?php echo (int) ($r['total'] ?? 0); ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <div class="welcome-card" style="padding:16px;">
      <h4 style="margin:0 0 10px;">Top colonias</h4>
      <?php if (empty($rep['colonias_prereg'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <table class="catalog-table">
          <thead><tr><th>Colonia</th><th>Municipio</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($rep['colonias_prereg'] as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['colonia'] ?? '', 'Sin colonia')); ?></td>
              <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['municipio'] ?? '')); ?></td>
              <td><?php echo (int) ($r['total'] ?? 0); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($rep['colonias_por_municipio'])): ?>
  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h4 style="margin:0 0 12px;">Colonias principales por municipio</h4>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px;">
      <?php foreach ($rep['colonias_por_municipio'] as $mun => $cols): ?>
        <div style="background:#f8f9fa; border-radius:8px; padding:12px;">
          <strong><?php echo htmlspecialchars($mun); ?></strong>
          <ul style="margin:8px 0 0; padding-left:18px; font-size:0.9rem;">
            <?php foreach ($cols as $c): ?>
              <li><?php echo htmlspecialchars($c['colonia']); ?>: <?php echo (int) $c['total']; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($fuente !== 'preregistros'): ?>
  <h3 style="margin:24px 0 12px;"><i class="fas fa-user-check"></i> Inscritos</h3>
  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px;">
    <div class="welcome-card" style="padding:16px;">
      <h4 style="margin:0 0 10px;">Por municipio</h4>
      <?php if (empty($rep['municipios_inscritos'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <table class="catalog-table">
          <thead><tr><th>Municipio</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($rep['municipios_inscritos'] as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['municipio'] ?? '')); ?></td>
              <td><strong><?php echo (int) ($r['total'] ?? 0); ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <div class="welcome-card" style="padding:16px;">
      <h4 style="margin:0 0 10px;">Top colonias</h4>
      <?php if (empty($rep['colonias_inscritos'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <table class="catalog-table">
          <thead><tr><th>Colonia</th><th>Municipio</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($rep['colonias_inscritos'] as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['colonia'] ?? '', 'Sin colonia')); ?></td>
              <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['municipio'] ?? '')); ?></td>
              <td><?php echo (int) ($r['total'] ?? 0); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($fuente !== 'preregistros' && !empty($rep['cp_inscritos'])): ?>
  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h4 style="margin:0 0 10px;">Códigos postales (inscritos)</h4>
    <table class="catalog-table">
      <thead><tr><th>CP</th><th>Municipio</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($rep['cp_inscritos'] as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['cp'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['municipio'] ?? '')); ?></td>
          <td><?php echo (int) ($r['total'] ?? 0); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($fuente !== 'inscritos' && !empty($rep['cp_prereg'])): ?>
  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h4 style="margin:0 0 10px;">Códigos postales (pre-registros)</h4>
    <table class="catalog-table">
      <thead><tr><th>CP</th><th>Municipio</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($rep['cp_prereg'] as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['cp'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars(gerente_geo_etiqueta($r['municipio'] ?? '')); ?></td>
          <td><?php echo (int) ($r['total'] ?? 0); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <p style="color:#888; font-size:0.85rem; margin-top:20px;">
    Los inscritos heredan domicilio del pre-registro vinculado. Capture colonia y municipio en el pre-registro para mejorar este reporte.
  </p>
</div>
