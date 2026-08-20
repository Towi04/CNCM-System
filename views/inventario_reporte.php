<?php
require_once __DIR__ . '/../config.php';
rbac_require_any_cap(
    ['admin_catalogo', 'menu_venta_productos'],
    'Sin permiso para consultar el inventario.'
);

$idPlantel = plantel_scope_id($pdo);
$plantel = plantel_find($pdo, $idPlantel);
$stmt = $pdo->prepare(
    'SELECT p.nombre, p.clave, COALESCE(i.existencia, 0) AS existencia,
            COALESCE(i.stock_minimo, p.stock_minimo, 0) AS stock_minimo
     FROM productos p
     LEFT JOIN producto_inventario i
       ON i.id_producto = p.id_producto AND i.id_plantel = ?
     WHERE COALESCE(p.controla_inventario, 1) = 1
     ORDER BY p.nombre, p.clave'
);
$stmt->execute([$idPlantel]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<style>
.inv-reporte { max-width: 960px; margin: 0 auto; padding: 20px; background: #fff; }
.inv-reporte__encabezado { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
.inv-reporte table { width:100%; border-collapse:collapse; margin-top:18px; }
.inv-reporte th,.inv-reporte td { border:1px solid #bbb; padding:8px 10px; text-align:left; }
.inv-reporte th { background:#f1f5f9; }
.inv-reporte .numero { text-align:right; }
@media print {
  body * { visibility:hidden !important; }
  #inventario-reporte, #inventario-reporte * { visibility:visible !important; }
  #inventario-reporte { position:absolute; inset:0; max-width:none; padding:0; }
  .no-print { display:none !important; }
}
</style>

<section class="inv-reporte" id="inventario-reporte">
  <div class="inv-reporte__encabezado">
    <div>
      <h1 style="margin:0 0 6px;">Existencia de productos</h1>
      <div><strong><?php echo htmlspecialchars($plantel['nombre'] ?? ($_SESSION['plantel_nombre'] ?? 'Plantel')); ?></strong></div>
      <div>Corte: <?php echo date('d/m/Y H:i'); ?></div>
    </div>
    <div class="no-print" style="display:flex;gap:8px;">
      <button type="button" onclick="cargarSeccion('admin_productos')">Volver</button>
      <button type="button" class="primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Nombre</th><th>Clave</th><th class="numero">Existencia</th><th class="numero">Stock mínimo</th></tr>
    </thead>
    <tbody>
      <?php if ($productos === []): ?>
        <tr><td colspan="4">No hay productos que controlen inventario.</td></tr>
      <?php else: ?>
        <?php foreach ($productos as $producto): ?>
          <tr>
            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
            <td><?php echo htmlspecialchars($producto['clave']); ?></td>
            <td class="numero"><?php echo (int) $producto['existencia']; ?></td>
            <td class="numero"><?php echo (int) $producto['stock_minimo']; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</section>
