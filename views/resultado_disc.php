<?php
// views/resultado_disc.php
require_once __DIR__ . '/../config.php';
global $pdo;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$usuario_id = $_SESSION['user_id'];

function disc_scale_plus(string $letter, int $count): int {
    // Tablas del usuario (D+/I+/S+/C+)
    return match ($letter) {
        'D' => ($count <= 2) ? 1 : (($count <= 4) ? 2 : (($count <= 6) ? 3 : (($count <= 7) ? 4 : (($count <= 9) ? 5 : (($count <= 12) ? 6 : 7))))),
        'I' => ($count <= 2) ? 1 : (($count <= 3) ? 2 : (($count <= 5) ? 3 : (($count <= 6) ? 4 : (($count <= 7) ? 5 : (($count <= 9) ? 6 : 7))))),
        'S' => ($count <= 2) ? 1 : (($count <= 3) ? 2 : (($count <= 4) ? 3 : (($count <= 5) ? 4 : (($count <= 6) ? 5 : (($count <= 8) ? 6 : 7))))),
        'C' => ($count <= 3) ? 1 : (($count <= 4) ? 2 : (($count <= 5) ? 3 : (($count <= 7) ? 4 : (($count <= 8) ? 5 : (($count <= 10) ? 6 : 7))))),
        default => 1,
    };
}

function disc_scale_minus(string $letter, int $count): int {
    // Tablas del usuario (D-/I-/S-/C-) basadas en conteo negativo
    return match ($letter) {
        'D' => ($count >= 11) ? 1 : (($count >= 9) ? 2 : (($count >= 7) ? 3 : (($count >= 6) ? 4 : (($count >= 4) ? 5 : (($count >= 2) ? 6 : 7))))),
        'I' => ($count >= 11) ? 1 : (($count >= 9) ? 2 : (($count >= 8) ? 3 : (($count >= 7) ? 4 : (($count >= 6) ? 5 : (($count >= 4) ? 6 : 7))))),
        'S' => ($count >= 12) ? 1 : (($count >= 10) ? 2 : (($count >= 8) ? 3 : (($count >= 7) ? 4 : (($count >= 5) ? 5 : (($count >= 4) ? 6 : 7))))),
        'C' => ($count >= 9) ? 1 : (($count >= 8) ? 2 : (($count >= 6) ? 3 : (($count >= 5) ? 4 : (($count >= 4) ? 5 : (($count >= 3) ? 6 : 7))))),
        default => 7,
    };
}

function disc_scale_diff(string $letter, int $diff): int {
    // Tablas del usuario (D/I/S/C) para la diferencia (+ - -)
    return match ($letter) {
        'D' => ($diff <= -9) ? 1 : (($diff <= -4) ? 2 : (($diff <= -1) ? 3 : (($diff <= 1) ? 4 : (($diff <= 5) ? 5 : (($diff <= 9) ? 6 : 7))))),
        'I' => ($diff <= -8) ? 1 : (($diff <= -5) ? 2 : (($diff <= -3) ? 3 : (($diff <= -1) ? 4 : (($diff <= 2) ? 5 : (($diff <= 4) ? 6 : 7))))),
        'S' => ($diff <= -10) ? 1 : (($diff <= -7) ? 2 : (($diff <= -4) ? 3 : (($diff <= -2) ? 4 : (($diff <= 1) ? 5 : (($diff <= 4) ? 6 : 7))))),
        'C' => ($diff <= -6) ? 1 : (($diff <= -3) ? 2 : (($diff <= -1) ? 3 : (($diff <= 1) ? 4 : (($diff <= 4) ? 5 : (($diff <= 7) ? 6 : 7))))),
        default => 4,
    };
}

// Obtener resultado por id (si viene) o el último
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$requestedId = $id;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM disc_res WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$id, $usuario_id]);
    $resultado = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM disc_res WHERE user_id = ? ORDER BY creado_en DESC LIMIT 1");
    $stmt->execute([$usuario_id]);
    $resultado = $stmt->fetch();
}

if (!$resultado) {
    echo "<div class='alert alert-warning'>Aún no has realizado la evaluación DISC.</div>";
    return;
}

$plusCounts  = ['D' => (int)$resultado['D+'], 'I' => (int)$resultado['I+'], 'S' => (int)$resultado['S+'], 'C' => (int)$resultado['C+']];
$minusCounts = ['D' => (int)$resultado['D-'], 'I' => (int)$resultado['I-'], 'S' => (int)$resultado['S-'], 'C' => (int)$resultado['C-']];
$diffCounts  = ['D' => (int)$resultado['D'],  'I' => (int)$resultado['I'],  'S' => (int)$resultado['S'],  'C' => (int)$resultado['C']];

$plusScale = [
    'D' => disc_scale_plus('D', $plusCounts['D']),
    'I' => disc_scale_plus('I', $plusCounts['I']),
    'S' => disc_scale_plus('S', $plusCounts['S']),
    'C' => disc_scale_plus('C', $plusCounts['C']),
];
$minusScale = [
    'D' => disc_scale_minus('D', $minusCounts['D']),
    'I' => disc_scale_minus('I', $minusCounts['I']),
    'S' => disc_scale_minus('S', $minusCounts['S']),
    'C' => disc_scale_minus('C', $minusCounts['C']),
];
$diffScale = [
    'D' => disc_scale_diff('D', $diffCounts['D']),
    'I' => disc_scale_diff('I', $diffCounts['I']),
    'S' => disc_scale_diff('S', $diffCounts['S']),
    'C' => disc_scale_diff('C', $diffCounts['C']),
];

$barMaxPx = 120; // altura máxima visible de barra (evita desbordes en 6/7)

$codigo = !empty($resultado['codigo'])
    ? (string)$resultado['codigo']
    : ((string)$diffScale['D'] . (string)$diffScale['I'] . (string)$diffScale['S'] . (string)$diffScale['C']);
$patron = null;

$patIdFromRow = !empty($resultado['pat_id']) ? (int)$resultado['pat_id'] : null;
if ($patIdFromRow) {
    try {
        $stmt = $pdo->prepare("SELECT nombre, descp FROM disc_pat WHERE id = ? LIMIT 1");
        $stmt->execute([$patIdFromRow]);
        $patron = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $patron = null;
    }
}

if (!$patron) {
    try {
        $stmt = $pdo->prepare("
            SELECT dp.nombre, dp.descp
            FROM disc_cod dc
            JOIN disc_pat dp ON dp.id = dc.pat_id
            WHERE dc.codigo = ?
            LIMIT 1
        ");
        $stmt->execute([$codigo]);
        $patron = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $patron = null;
    }
}

// Fallback: si disc_cod no está poblada, usar tabla `patrones` (codigo -> patron_slug)
if (!$patron) {
    try {
        // Detectar nombres reales de columnas en `patrones`
        $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
        $colStmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'patrones'
        ");
        $colStmt->execute([$dbName]);
        $cols = array_map(fn($x) => (string)$x['COLUMN_NAME'], $colStmt->fetchAll(PDO::FETCH_ASSOC));

        $codigoCol = null;
        foreach (['codigo','code','cod','perfil','pattern_code'] as $c) {
            if (in_array($c, $cols, true)) { $codigoCol = $c; break; }
        }
        $slugCol = null;
        foreach (['patron_slug','slug','patron','perfil_slug','pattern_slug','nombre','name'] as $c) {
            if (in_array($c, $cols, true)) { $slugCol = $c; break; }
        }

        if ($codigoCol && $slugCol) {
            $sql = "
                SELECT dp.nombre, dp.descp
                FROM patrones p
                JOIN disc_pat dp ON dp.slug = p.`$slugCol`
                WHERE p.`$codigoCol` = ?
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo]);
            $patron = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $patron = null;
        }
    } catch (Throwable $e) {
        $patron = null;
    }
}

// Lista de resultados previos (últimos 8)
$stmt = $pdo->prepare("SELECT id, creado_en FROM disc_res WHERE user_id = ? ORDER BY creado_en DESC LIMIT 8");
$stmt->execute([$usuario_id]);
$hist = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="css/resultados.css">

<div class="result-container">
  <div class="result-header">
    <h2>Resultado DISC</h2>
    <?php if ($patron && !empty($patron['nombre'])): ?>
      <p style="margin:0; font-weight:800;"><?php echo htmlspecialchars($patron['nombre']); ?></p>
    <?php endif; ?>

    <div class="disc-actions">
      <button class="primary" type="button" onclick="cargarSeccion('examen_disc')">Volver al test</button>
      <button type="button" onclick="cargarSeccion('resultado_disc')">Ver último resultado</button>
    </div>
  </div>

  <div class="result-content">
    <div class="disc-panels">
      <div class="disc-panel">
        <h3>Inconsciente</h3>
        <div class="disc-bars">
          <?php foreach (['D','I','S','C'] as $l): $cls = strtolower($l); $hpx = (int)round(($plusScale[$l] / 7) * $barMaxPx); ?>
            <div class="disc-bar-wrap">
              <div class="disc-bar-space">
                <div class="disc-bar <?php echo $cls; ?>" style="height: <?php echo $hpx; ?>px;">
                  <div class="num"><?php echo $plusScale[$l]; ?></div>
                </div>
              </div>
              <div class="disc-label"><?php echo $l; ?>+</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="disc-panel">
        <h3>Autopercepción</h3>
        <div class="disc-bars">
          <?php foreach (['D','I','S','C'] as $l): $cls = strtolower($l); $hpx = (int)round(($diffScale[$l] / 7) * $barMaxPx); ?>
            <div class="disc-bar-wrap">
              <div class="disc-bar-space">
                <div class="disc-bar <?php echo $cls; ?>" style="height: <?php echo $hpx; ?>px;">
                  <div class="num"><?php echo $diffScale[$l]; ?></div>
                </div>
              </div>
              <div class="disc-label"><?php echo $l; ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="disc-panel">
        <h3>Consciente</h3>
        <div class="disc-bars">
          <?php foreach (['D','I','S','C'] as $l): $cls = strtolower($l); $hpx = (int)round(($minusScale[$l] / 7) * $barMaxPx); ?>
            <div class="disc-bar-wrap">
              <div class="disc-bar-space">
                <div class="disc-bar <?php echo $cls; ?>" style="height: <?php echo $hpx; ?>px;">
                  <div class="num"><?php echo $minusScale[$l]; ?></div>
                </div>
              </div>
              <div class="disc-label"><?php echo $l; ?>-</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php if ($patron && !empty($patron['nombre'])): ?>
      <div class="patron-desc">
        <h4><?php echo htmlspecialchars($patron['nombre']); ?></h4>
        <p><?php echo htmlspecialchars($patron['descp'] ?? ''); ?></p>
      </div>
    <?php else: ?>
      <div class="patron-desc">
        <h4>Patrón no encontrado</h4>
        <p>
          Aún no está cargado el <strong>mapeo de códigos</strong> para convertir el código
          <strong><?php echo htmlspecialchars($codigo); ?></strong> en un patrón.
          Carga la tabla <code>patrones</code> (codigo → patron_slug) o <code>disc_cod</code> (codigo → pat_id).
        </p>
      </div>
    <?php endif; ?>

    <?php if (!empty($hist)): ?>
      <div class="patron-desc">
        <h4>Resultados anteriores</h4>
        <div class="hist-lines">
          <?php foreach ($hist as $hrow): ?>
            <a class="hist-line" href="#" onclick="cargarResultadoDisc(<?php echo (int)$hrow['id']; ?>); return false;">
              #<?php echo (int)$hrow['id']; ?> · <?php echo htmlspecialchars(substr((string)$hrow['creado_en'], 0, 16)); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <script>
        function cargarResultadoDisc(id) {
          const contenedor = document.getElementById('main-content');
          if (!contenedor) return;
          console.log('[DISC] cargarResultadoDisc ->', id);
          contenedor.innerHTML = '<div class="result-container"><div class="result-header"><h2>Resultado DISC</h2><p style="color:#666; font-weight:600;">Cargando intento #' + id + '...</p></div></div>';
          const url = 'views/resultado_disc.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
          fetch(url, { cache: 'no-store', headers: { 'X-Requested-With': 'fetch' } })
            .then(r => r.text())
            .then(html => {
              contenedor.innerHTML = html;
              // Ejecutar scripts embebidos (igual que navigation.js)
              const scripts = contenedor.querySelectorAll('script');
              scripts.forEach(script => {
                const nuevoScript = document.createElement('script');
                if (script.src) nuevoScript.src = script.src;
                else nuevoScript.textContent = script.textContent;
                document.body.appendChild(nuevoScript);
              });
              window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(() => { contenedor.innerHTML = '<div class="alert">Error al cargar resultado.</div>'; });
        }
      </script>
    <?php endif; ?>
  </div>
</div>