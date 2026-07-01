<?php
require_once __DIR__ . '/../config.php';
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$profesorId = (int)($_SESSION['user_id'] ?? 0);
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
$semana = isset($_GET['semana']) ? (int)$_GET['semana'] : 0;
if ($profesorId <= 0 || $anio <= 0) { echo "<p>Parámetros inválidos.</p>"; return; }

// Horarios por día (última hora = cierre; última clase empieza 1h antes)
$days = [
  ['dow' => 0, 'label' => 'Dom', 'open' => 8, 'close' => 14],
  ['dow' => 1, 'label' => 'Lun', 'open' => 8, 'close' => 19],
  ['dow' => 2, 'label' => 'Mar', 'open' => 8, 'close' => 19],
  ['dow' => 3, 'label' => 'Mié', 'open' => 8, 'close' => 19],
  ['dow' => 4, 'label' => 'Jue', 'open' => 8, 'close' => 19],
  ['dow' => 5, 'label' => 'Vie', 'open' => 8, 'close' => 19],
  ['dow' => 6, 'label' => 'Sáb', 'open' => 8, 'close' => 20],
];

// Cargar disponibilidad guardada
$stmt = $pdo->prepare(
    'SELECT dow, hora, disponible FROM asesoria_disp
     WHERE id_plantel = ? AND id_profesor = ? AND anio = ? AND semana = ?'
);
$stmt->execute([plantel_id_activo(), $profesorId, $anio, $semana]);
$map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $map[(int)$r['dow']][(int)$r['hora']] = (int)$r['disponible'];
}

// Determinar rango máximo de horas para dibujar filas
$minHour = 8;
$maxClose = 20; // sábados
?>

<style>
  .ase-table { width:100%; border-collapse: collapse; overflow:hidden; border-radius: 12px; }
  .ase-table th, .ase-table td { border:1px solid #eee; padding:8px; text-align:center; }
  .ase-table th { background:#f6f6f6; font-weight:800; }
  .ase-hour { background:#fafafa; font-weight:800; width:90px; }
  .ase-cell { cursor:pointer; user-select:none; }
  .ase-on { background:#dff7e8; }
  .ase-off { background:#fde2e2; opacity:0.7; }
  .ase-na { background:#f3f3f3; color:#aaa; }
  .ase-actions { margin: 10px 0 0 0; display:flex; gap:10px; flex-wrap:wrap; }
  .ase-actions button { padding:10px 14px; border-radius:10px; border:1px solid #ddd; cursor:pointer; font-weight:800; }
  .ase-actions .primary { background: var(--azul); color:#fff; border-color: var(--azul); }
</style>

<form method="POST" action="php/asesoria_save.php" id="form-asesoria">
  <input type="hidden" name="anio" value="<?php echo (int)$anio; ?>">
  <input type="hidden" name="semana" value="<?php echo (int)$semana; ?>">

  <table class="ase-table">
    <thead>
      <tr>
        <th class="ase-hour">Hora</th>
        <?php foreach ($days as $d): ?>
          <th><?php echo htmlspecialchars($d['label']); ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php for ($h = $minHour; $h < $maxClose; $h++): ?>
        <tr>
          <td class="ase-hour"><?php echo sprintf('%02d:00', $h); ?></td>
          <?php foreach ($days as $d):
            $dow = (int)$d['dow'];
            $open = (int)$d['open'];
            $close = (int)$d['close'];
            $isNA = ($h < $open) || ($h >= ($close)); // cierre: no hay clase desde close
            $checked = $map[$dow][$h] ?? 0;
            $name = "disp[$dow][$h]";
          ?>
            <?php if ($isNA): ?>
              <td class="ase-na">—</td>
            <?php else: ?>
              <td class="ase-cell <?php echo $checked ? 'ase-on' : 'ase-off'; ?>" data-dow="<?php echo $dow; ?>" data-h="<?php echo $h; ?>">
                <input type="hidden" name="<?php echo $name; ?>" value="<?php echo $checked ? '1' : '0'; ?>">
                <?php echo $checked ? 'Disponible' : 'No'; ?>
              </td>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <div class="ase-actions">
    <button class="primary" type="submit">Guardar disponibilidad</button>
    <button type="button" onclick="marcarTodo(1)">Marcar todo</button>
    <button type="button" onclick="marcarTodo(0)">Limpiar</button>
  </div>
</form>

<script>
  function toggleCell(td) {
    const input = td.querySelector('input[type="hidden"]');
    if (!input) return;
    const newVal = input.value === '1' ? '0' : '1';
    input.value = newVal;
    td.classList.toggle('ase-on', newVal === '1');
    td.classList.toggle('ase-off', newVal !== '1');
    td.textContent = (newVal === '1' ? 'Disponible' : 'No');
    td.appendChild(input);
  }

  document.querySelectorAll('.ase-cell').forEach(td => {
    td.addEventListener('click', () => toggleCell(td));
  });

  function marcarTodo(v) {
    document.querySelectorAll('.ase-cell').forEach(td => {
      const input = td.querySelector('input[type="hidden"]');
      if (!input) return;
      input.value = String(v);
      td.classList.toggle('ase-on', v === 1);
      td.classList.toggle('ase-off', v !== 1);
      td.textContent = (v === 1 ? 'Disponible' : 'No');
      td.appendChild(input);
    });
  }
</script>

