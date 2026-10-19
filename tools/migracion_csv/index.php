<?php
/**
 * Extractor local (XAMPP) — Migración selectiva legado → CSV para HAY.
 * Abrir: http://localhost/…/tools/migracion_csv/
 */
require_once __DIR__ . '/lib.php';

$cfgOk = is_readable(__DIR__ . '/config.php');
$error = '';
$diag = null;
$sucursales = [];
$filtroCount = 0;
$filtroPath = __DIR__ . '/grupos_filtro.txt';

if ($cfgOk) {
    try {
        $pdo = migcsv_pdo();
        $diag = migcsv_diagnostico($pdo);
        if (migcsv_table_exists($pdo, 'sucursales')) {
            $sucursales = $pdo->query('SELECT id, nombre FROM sucursales ORDER BY id')->fetchAll();
        }
        $filtroCount = count(migcsv_resolver_ids_grupos($pdo, migcsv_leer_filtro_grupos($filtroPath)));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$idSuc = (int) ($_GET['id_sucursal'] ?? 0);
$qs = $idSuc > 0 ? ('&id_sucursal=' . $idSuc) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Extractor CSV legado → HAY</title>
  <style>
    :root { --bg:#f4f6f8; --card:#fff; --ink:#1a1a1a; --muted:#666; --acc:#0b6e4f; --bd:#dde3ea; }
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", system-ui, sans-serif; margin:0; background:var(--bg); color:var(--ink); }
    main { max-width: 960px; margin: 0 auto; padding: 28px 18px 60px; }
    h1 { font-size: 1.45rem; margin: 0 0 8px; }
    p.lead { color: var(--muted); margin: 0 0 22px; line-height: 1.45; }
    .card { background: var(--card); border: 1px solid var(--bd); border-radius: 10px; padding: 16px 18px; margin-bottom: 16px; }
    .card h2 { font-size: 1.05rem; margin: 0 0 10px; }
    .warn { background: #fff8e6; border-color: #f0d78c; color: #6a5200; }
    .err { background: #fdecea; border-color: #f5c2c0; color: #8a1f11; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--bd); }
    th { color: var(--muted); font-weight: 600; }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    a.btn, button.btn {
      display: inline-block; background: var(--acc); color: #fff; text-decoration: none;
      border: none; border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; cursor: pointer;
    }
    a.btn.secondary { background: #345; }
    a.btn:hover { filter: brightness(1.08); }
    code { background: #eef2f6; padding: 1px 5px; border-radius: 4px; font-size: 0.88em; }
    label { display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 4px; }
    select { padding: 8px; border-radius: 6px; border: 1px solid var(--bd); min-width: 220px; }
    ol.steps { margin: 8px 0 0; padding-left: 1.2rem; color: var(--muted); line-height: 1.5; }
  </style>
</head>
<body>
<main>
  <h1>Extractor CSV — legado → Portal CNCM (HAY)</h1>
  <p class="lead">
    Corre en XAMPP contra tu BD legado local. Descarga CSVs por plantel, edítalos (especialidad HAY, aula, profesor)
    y súbelos en el Portal: sección <strong>Migración CSV</strong>.
  </p>

  <?php if (!$cfgOk): ?>
    <div class="card warn">
      <h2>Configuración</h2>
      <p>Copie <code>config.example.php</code> a <code>config.php</code> y ponga host/usuario/contraseña de su MySQL local.</p>
    </div>
  <?php elseif ($error !== ''): ?>
    <div class="card err"><h2>Error</h2><p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p></div>
  <?php else: ?>

  <div class="card">
    <h2>1. Filtro de grupos activos</h2>
    <p>Edite <code>grupos_filtro.txt</code> (un <code>id</code> o <code>clave</code> legado por línea). Actual: <strong><?php echo (int) $filtroCount; ?></strong> grupo(s) resuelto(s).</p>
    <p style="color:var(--muted); font-size:0.9rem;">Sin filtro no se pueden exportar grupos/alumnos/pagos (evita bajar toda la escuela).</p>
  </div>

  <div class="card">
    <h2>2. Plantel (opcional)</h2>
    <form method="get">
      <label for="id_sucursal">Filtrar exportaciones por sucursal</label>
      <select name="id_sucursal" id="id_sucursal" onchange="this.form.submit()">
        <option value="0">Todas / sin filtro de sucursal</option>
        <?php foreach ($sucursales as $s): ?>
          <option value="<?php echo (int) $s['id']; ?>"<?php echo $idSuc === (int) $s['id'] ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($s['id'] . ' — ' . $s['nombre'], ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="card">
    <h2>3. Diagnóstico de cobertura</h2>
    <p style="margin:0 0 8px; color:var(--muted); font-size:0.9rem;">
      BD: <code><?php echo htmlspecialchars((string) ($diag['database'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
    </p>
    <table>
      <thead><tr><th>Tabla</th><th>Filas</th></tr></thead>
      <tbody>
        <?php foreach (($diag['tablas'] ?? []) as $t => $n): ?>
          <tr>
            <td><code><?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td><?php echo $n === null ? '—' : (int) $n; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!empty($diag['por_sucursal'])): ?>
      <h2 style="margin-top:16px;">Por sucursal</h2>
      <table>
        <thead>
          <tr>
            <th>Sucursal</th><th>Grupos</th><th>Activos*</th>
            <th>grupos_dias</th><th>grupos_materias</th><th>asistencias</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($diag['por_sucursal'] as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo (int) $r['grupos']; ?></td>
              <td><?php echo (int) $r['grupos_activos_status']; ?></td>
              <td><?php echo (int) $r['grupos_dias']; ?></td>
              <td><?php echo (int) $r['grupos_materias']; ?></td>
              <td><?php echo (int) $r['asistencias']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p style="font-size:0.8rem; color:var(--muted);">* Según <code>grupos.status</code> que contenga “activ”. Use su lista en <code>grupos_filtro.txt</code> como fuente de verdad.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>4. Descargar CSVs</h2>
    <ol class="steps">
      <li>Personal → revisar roles / plantel_slug</li>
      <li>Aulas (plantilla) → poner códigos reales</li>
      <li>Grupos → completar <code>clave_especialidad</code>, <code>aula_codigo</code>, profesor si falta</li>
      <li>Alumnos y pagos de esos grupos</li>
    </ol>
    <div class="actions">
      <a class="btn" href="export.php?tipo=personal<?php echo $qs; ?>">01_personal.csv</a>
      <a class="btn" href="export.php?tipo=aulas<?php echo $qs; ?>">02_aulas.csv</a>
      <a class="btn" href="export.php?tipo=grupos<?php echo $qs; ?>">03_grupos_activos.csv</a>
      <a class="btn" href="export.php?tipo=alumnos<?php echo $qs; ?>">04_alumnos.csv</a>
      <a class="btn" href="export.php?tipo=pagos<?php echo $qs; ?>">05_pagos.csv</a>
      <a class="btn secondary" href="export.php?tipo=diagnostico">Diagnóstico JSON</a>
    </div>
  </div>

  <div class="card">
    <h2>Claves de especialidad HAY</h2>
    <p style="margin:0; font-size:0.9rem; color:var(--muted);">
      En el CSV de grupos use: <code>ING</code>, <code>ING-EXT</code>, <code>ING-K</code>, <code>ING-K-EXT</code>,
      <code>COMP</code>/<code>COMP24</code>/<code>COMP25</code>, <code>COMP-EXT</code>, <code>COMP-K</code>,
      <code>PREP-AB</code>, <code>PREP-ESC</code>. Kids: <code>ING-K</code> o <code>infantil=1</code> → el importador crea pareja IK+CK.
    </p>
    <p style="margin:8px 0 0; font-size:0.9rem; color:var(--muted);">
      Días: <code>0</code>=domingo … <code>6</code>=sábado. Ej. sábado <code>6</code>, mar/jue <code>2,4</code>.
    </p>
  </div>

  <?php endif; ?>
</main>
</body>
</html>
