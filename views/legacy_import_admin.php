<?php
require_once __DIR__ . '/../config.php';

if (!legacy_import_admin_puede()) {
    echo '<div class="alert">Solo supervisión puede usar la importación avanzada del legado.</div>';
    return;
}

$conn = legacy_import_legacy_connection();
$leg = $conn['ok'] ? $conn['pdo'] : null;
$connError = $conn['error'];
$ctx = legacy_import_admin_handle($pdo, $leg, $_POST);
$fases = legacy_import_admin_fases();
$legacyDb = defined('LEGACY_DB_NAME') ? LEGACY_DB_NAME : '?';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/legacy_mapeo.css?v=20260703'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap legacy-mapeo-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-file-import"></i> Importar legado (avanzado)</h2>
    <p style="color:#666; margin:0; max-width:820px;">
      Herramienta directa por fase. Para la migración recomendada use
      <a href="#" onclick="cargarSeccion('legacy_migracion'); return false;">Asistente migración legado</a>
      (previsualización por etapas).
    </p>
  </div>

  <?php if (!$conn['ok']): ?>
    <div class="legacy-mig-advertencias">
      <strong>Sin conexión al legado.</strong>
      <?php echo htmlspecialchars($connError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php else: ?>
    <div class="legacy-stats legacy-mig-estado">
      <p class="legacy-mig-ok">Conexión a <code><?php echo htmlspecialchars($legacyDb, ENT_QUOTES, 'UTF-8'); ?></code>: OK</p>
      <p>Registros mapeados: <strong><?php echo (int) $ctx['mapCount']; ?></strong></p>
    </div>
  <?php endif; ?>

  <div class="legacy-mig-panel">
    <form method="post" action="<?php echo htmlspecialchars(hay_asset_url('views/legacy_import_admin.php'), ENT_QUOTES, 'UTF-8'); ?>">
      <label>
        Fase
        <select name="fase" class="catalog-filter-select" style="max-width:420px;">
          <?php foreach ($fases as $f): ?>
            <option value="<?php echo htmlspecialchars($f['key'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="display:block; margin:10px 0;">
        <input type="checkbox" name="dry_run" value="1" checked> Solo simular (no escribe en CNCM)
      </label>
      <label style="display:block; margin:10px 0;">
        <input type="checkbox" name="reset_map" value="1"> Reiniciar mapa antes (solo reimportación total)
      </label>
      <div class="legacy-mig-toolbar">
        <button type="submit" class="primary" <?php echo $leg ? '' : 'disabled'; ?>>Ejecutar</button>
        <button type="button" class="secondary" onclick="cargarSeccion('legacy_migracion')">Ir al asistente</button>
      </div>
    </form>
  </div>

  <?php if (!empty($ctx['error'])): ?>
    <div class="legacy-mig-advertencias"><strong>Error:</strong> <?php echo htmlspecialchars($ctx['error'], ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <?php if (!empty($ctx['resultado'])): ?>
    <div class="catalog-table-wrap hay-dt-panel" style="margin-top:16px;">
      <h3 style="margin:0 0 10px;">Resultado</h3>
      <table class="catalog-table">
        <thead>
          <tr><th>Fase</th><th>Insertados</th><th>Omitidos</th><th>Errores</th></tr>
        </thead>
        <tbody>
          <?php foreach ($ctx['resultado'] as $nombre => $st): ?>
            <tr>
              <td><?php echo htmlspecialchars((string) $nombre, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo (int) ($st['inserted'] ?? 0); ?></td>
              <td><?php echo (int) ($st['skipped'] ?? 0); ?></td>
              <td><?php echo (int) ($st['errors'] ?? 0); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (!empty($ctx['logs'])): ?>
    <div class="catalog-table-wrap hay-dt-panel" style="margin-top:16px;">
      <h3 style="margin:0 0 10px;">Últimos mensajes</h3>
      <table class="catalog-table">
        <thead>
          <tr><th>Hora</th><th>Fase</th><th>Nivel</th><th>Mensaje</th></tr>
        </thead>
        <tbody>
          <?php foreach ($ctx['logs'] as $l): ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($l['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($l['fase'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($l['nivel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($l['mensaje'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const form = document.querySelector('.catalog-wrap.legacy-mapeo-wrap form[method="post"]');
  if (!form || typeof cargarSeccion !== 'function') return;
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const url = typeof hayResolveAssetUrl === 'function'
      ? hayResolveAssetUrl('views/legacy_import_admin.php')
      : 'views/legacy_import_admin.php';
    const contenedor = document.getElementById('main-content');
    if (!contenedor) return;
    contenedor.innerHTML = '<div class="hay-section-loading" style="padding:24px;color:#666;">Ejecutando…</div>';
    try {
      const res = await fetch(url, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
      });
      const html = await res.text();
      if (!res.ok) throw new Error('Error ' + res.status);
      contenedor.innerHTML = html;
      if (typeof ejecutarScripts === 'function') await ejecutarScripts(contenedor);
    } catch (err) {
      contenedor.innerHTML = '<div class="alert">Error: ' + (err.message || 'desconocido') + '</div>';
    }
  });
})();
</script>
