<?php
require_once __DIR__ . '/../config.php';
/** @var PDO $pdo */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$gid = isset($_GET['grupo']) ? (int)$_GET['grupo'] : 0;
if ($gid <= 0) { echo "<p>Grupo inválido.</p>"; return; }

$stmt = $pdo->prepare("SELECT clave FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1");
$stmt->execute([$gid, plantel_scope_id($pdo)]);
$clave = $stmt->fetchColumn();
if (!$clave) { echo "<p>Grupo no existe en este plantel.</p>"; return; }

$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id_grupo = ? ORDER BY activo DESC, apellido ASC, nombre ASC");
$stmt->execute([$gid]);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
  <h3 style="margin:0;">Grupo: <?php echo htmlspecialchars((string)$clave); ?></h3>
</div>

<form method="POST" action="php/alumno_save.php" style="margin-top:10px;">
  <input type="hidden" name="id_grupo" value="<?php echo (int)$gid; ?>">
  <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 120px; gap:10px;">
    <input name="nombre" placeholder="Nombre" required style="padding:10px; border:1px solid #ddd; border-radius:10px;">
    <input name="apellido" placeholder="Apellido" required style="padding:10px; border:1px solid #ddd; border-radius:10px;">
    <input name="matricula" placeholder="Matrícula (opcional)" style="padding:10px; border:1px solid #ddd; border-radius:10px;">
    <button class="primary" type="submit">Agregar</button>
  </div>
</form>

<div class="hist-lines" style="margin-top:12px;">
  <?php if (empty($alumnos)): ?>
    <div class="hist-line">Sin alumnos en este grupo.</div>
  <?php else: ?>
    <?php foreach ($alumnos as $a): ?>
      <div class="hist-line" style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
        <div>
          <strong><?php echo htmlspecialchars($a['apellido'] . ' ' . $a['nombre']); ?></strong>
          <?php if (!empty($a['matricula'])): ?>
            · <?php echo htmlspecialchars((string)$a['matricula']); ?>
          <?php endif; ?>
          <?php if (!(int)$a['activo']): ?>
            <span style="margin-left:8px; color:#b00; font-weight:800;">(inactivo)</span>
          <?php endif; ?>
        </div>
        <form method="POST" action="php/alumno_toggle.php" style="margin:0;">
          <input type="hidden" name="id_alumno" value="<?php echo (int)$a['id_alumno']; ?>">
          <input type="hidden" name="id_grupo" value="<?php echo (int)$gid; ?>">
          <button type="submit"><?php echo ((int)$a['activo'] ? 'Desactivar' : 'Activar'); ?></button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

