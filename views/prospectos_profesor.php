<?php
require_once __DIR__ . '/../config.php';
if (!catalog_puede_administrar() && ($_SESSION['rol'] ?? '') !== 'profesor') {
    echo '<div class="alert">No autorizado.</div>';
    return;
}
usuario_ensure_schema($pdo);
$idPlantel = plantel_id_activo();
$stmt = $pdo->prepare(
    'SELECT p.*, CONCAT(u.nombre, " ", u.apellido) AS registro_nombre
     FROM prospectos_profesor p
     INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
     WHERE p.id_plantel = ?
     ORDER BY p.creado_en DESC'
);
$stmt->execute([$idPlantel]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$estados = [
    'entrevista' => 'Entrevista',
    'evaluacion' => 'En evaluación',
    'contratado' => 'Contratado',
    'rechazado' => 'No contratado',
    'contactar_despues' => 'Contactar después',
];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-user-tie"></i> Prospectos de profesor</h2>
    <button type="button" class="primary" onclick="cargarSeccion('prospecto_profesor_nuevo')">Nuevo prospecto</button>
  </div>
  <p style="color:#666;">Registro de entrevistas sin correo institucional. Pueden realizar evaluación inicial y subir documentación. Al contratar, se crea el usuario completo.</p>

  <div class="catalog-table-wrap">
    <?php if (empty($rows)): ?>
      <p>Sin prospectos registrados.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr><th>Nombre</th><th>Teléfono</th><th>Especialidad</th><th>Estado</th><th>Registró</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $p): ?>
            <tr>
              <td><?php echo htmlspecialchars(trim($p['nombres'] . ' ' . $p['apellido_paterno'])); ?></td>
              <td><?php echo htmlspecialchars($p['telefono'] ?? '—'); ?></td>
              <td><?php echo htmlspecialchars($p['especialidad'] ?? '—'); ?></td>
              <td><span class="catalog-badge catalog-badge--muted"><?php echo htmlspecialchars($estados[$p['estado']] ?? $p['estado']); ?></span></td>
              <td><?php echo htmlspecialchars($p['registro_nombre'] ?? ''); ?></td>
              <td>
                <button type="button" class="secondary" onclick="cargarSeccion('prospecto_profesor_nuevo', 'id=<?php echo (int)$p['id_prospecto']; ?>')">Ver / editar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
