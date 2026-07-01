<?php
require_once __DIR__ . '/../config.php';
if (!docente_prospecto_puede_gestionar()) {
    echo '<div class="alert">Sin permiso para bolsa de candidatos.</div>';
    return;
}

$filtro = trim((string) ($_GET['f'] ?? ''));
$rows = docente_prospecto_listar_bolsa($pdo, $filtro !== '' ? $filtro : null);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2>Bolsa histórica de candidatos</h2>
    <div style="display:flex; gap:8px;">
      <button type="button" class="secondary" onclick="cargarSeccion('docente_bolsa')">Todos</button>
      <button type="button" class="secondary" onclick="cargarSeccion('docente_bolsa', 'f=apto_no_contratado')">Aptos no contratados</button>
      <button type="button" class="secondary" onclick="cargarSeccion('docente_bolsa', 'f=disponibilidad')">Por disponibilidad</button>
      <button type="button" class="secondary" onclick="cargarSeccion('docente_bolsa', 'f=segunda_oportunidad')">Segunda oportunidad</button>
      <button type="button" class="primary" onclick="cargarSeccion('docente_prospectos')">Volver a reclutamiento</button>
    </div>
  </div>

  <div class="catalog-table-wrap">
    <?php if ($rows === []): ?>
      <p>No hay candidatos en bolsa para este filtro.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Estado / decisión</th>
            <th>Resultado clase</th>
            <th>Motivo no contratación</th>
            <th>Recontacto</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?php echo htmlspecialchars(docente_prospecto_nombre($r)); ?></strong>
              <br><small><?php echo htmlspecialchars($r['telefono'] ?? ''); ?> · <?php echo htmlspecialchars($r['email'] ?? ''); ?></small>
            </td>
            <td><?php echo htmlspecialchars(($r['estado'] ?? '') . ' / ' . ($r['decision_final'] ?? '')); ?></td>
            <td>
              <?php if ($r['puntaje_showclass'] !== null): ?>
                <?php echo htmlspecialchars((string) $r['puntaje_showclass']); ?>%
                <?php echo (int) ($r['showclass_aprobado'] ?? 0) === 1 ? '✓' : '✗'; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['categoria_no_contratacion'])): ?>
                <span class="catalog-badge catalog-badge--muted"><?php echo htmlspecialchars($r['categoria_no_contratacion']); ?></span><br>
              <?php endif; ?>
              <?php echo htmlspecialchars((string) ($r['motivo_no_contratacion'] ?? '—')); ?>
            </td>
            <td>
              <?php echo !empty($r['recontactar_en']) ? date('d/m/Y', strtotime($r['recontactar_en'])) : '—'; ?>
              <?php if ((int) ($r['segunda_oportunidad'] ?? 0) === 1): ?>
                <br><small>Segunda oportunidad</small>
              <?php endif; ?>
            </td>
            <td>
              <button type="button" class="secondary" onclick="cargarSeccion('docente_prospectos')">Ver detalle</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
