<?php
require_once __DIR__ . '/../config.php';
if (!catalog_puede_administrar() && !catalog_puede_confirmar_inventario()) {
    echo '<div class="alert">No tienes permiso para ver productos e inventario.</div>';
    return;
}

$idPlantel = (int) ($_GET['id_plantel'] ?? plantel_id_activo());
$planteles = plantel_list($pdo, false);
$filtros = [
    'q' => trim($_GET['q'] ?? ''),
    'visible' => $_GET['visible'] ?? '',
    'descontinuado' => $_GET['descontinuado'] ?? '',
    'activo' => $_GET['activo'] ?? '',
    'solo_bajo_stock' => !empty($_GET['solo_bajo_stock']),
];
$rows = catalog_listar_productos($pdo, $filtros, $idPlantel);
$pendientes = catalog_movimientos_pendientes($pdo, $idPlantel);
$puedeAdmin = catalog_puede_administrar();
$puedeConfirmar = catalog_puede_confirmar_inventario();
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-box"></i> Productos e inventario</h2>
    <?php if ($puedeAdmin): ?>
      <button type="button" class="primary" id="btn-nuevo-producto">Nuevo producto</button>
    <?php endif; ?>
  </div>

  <p style="color:#666; margin-top:0;">
    Libros, USB, certificados, constancias, etc. Control de existencia por plantel, recepciones por confirmar,
    mermas por defectos y alertas de stock bajo. Los productos descontinuados sin existencia pueden ocultarse.
    Algunos artículos son <strong>gratis para profesores</strong> (material de apoyo).
  </p>

  <div id="respuesta-productos" class="catalog-alert" style="display:none;"></div>

  <?php if ($puedeConfirmar && !empty($pendientes)): ?>
    <div class="catalog-pendientes">
      <h3><i class="fas fa-truck-loading"></i> Recepciones pendientes de confirmar</h3>
      <ul style="margin:0; padding-left:18px;">
        <?php foreach ($pendientes as $m): ?>
          <li style="margin-bottom:8px;">
            <strong><?php echo htmlspecialchars($m['producto_nombre']); ?></strong>
            (<?php echo htmlspecialchars($m['producto_clave']); ?>) —
            <?php echo (int)$m['cantidad']; ?> pza(s)
            <?php if (!empty($m['notas'])): ?> · <em><?php echo htmlspecialchars($m['notas']); ?></em><?php endif; ?>
            <button type="button" class="btn-confirmar-entrada primary" style="margin-left:8px;"
              data-movimiento="<?php echo (int)$m['id_movimiento']; ?>"
              data-cantidad="<?php echo (int)$m['cantidad']; ?>">
              Confirmar recepción
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form class="catalog-toolbar" id="form-filtros-prod" onsubmit="return false;">
    <div class="field">
      <label>Plantel</label>
      <select id="filtro-plantel-prod">
        <?php foreach ($planteles as $pl): ?>
          <option value="<?php echo (int)$pl['id_plantel']; ?>"<?php echo (int)$pl['id_plantel'] === $idPlantel ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($pl['nombre']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Buscar</label>
      <input type="search" id="filtro-q-prod" value="<?php echo htmlspecialchars($filtros['q']); ?>" placeholder="Clave, nombre, SAT…">
    </div>
    <div class="field">
      <label>Visible</label>
      <select id="filtro-visible-prod">
        <option value="">Todos</option>
        <option value="1"<?php echo $filtros['visible'] === '1' ? ' selected' : ''; ?>>Sí</option>
        <option value="0"<?php echo $filtros['visible'] === '0' ? ' selected' : ''; ?>>No</option>
      </select>
    </div>
    <div class="field">
      <label>Descontinuado</label>
      <select id="filtro-desc-prod">
        <option value="">Todos</option>
        <option value="0"<?php echo $filtros['descontinuado'] === '0' ? ' selected' : ''; ?>>Vigente</option>
        <option value="1"<?php echo $filtros['descontinuado'] === '1' ? ' selected' : ''; ?>>Sí</option>
      </select>
    </div>
    <div class="field">
      <label>Estado</label>
      <select id="filtro-activo-prod">
        <option value="">Todos</option>
        <option value="1"<?php echo $filtros['activo'] === '1' ? ' selected' : ''; ?>>Activos</option>
        <option value="0"<?php echo $filtros['activo'] === '0' ? ' selected' : ''; ?>>Inactivos</option>
      </select>
    </div>
    <div class="field" style="align-self:flex-end;">
      <label style="display:flex; align-items:center; gap:6px; margin-bottom:0;">
        <input type="checkbox" id="filtro-bajo-prod"<?php echo $filtros['solo_bajo_stock'] ? ' checked' : ''; ?>>
        Solo bajo stock
      </label>
    </div>
    <div class="field" style="align-self:flex-end;">
      <button type="button" class="primary" id="btn-buscar-prod">Buscar</button>
    </div>
  </form>

  <div class="catalog-table-wrap hay-dt-panel">
    <table class="catalog-table display hay-paged-table" id="tabla-productos" style="width:100%;">
      <thead>
        <tr>
          <th>Clave</th>
          <th>Nombre</th>
          <th>Precio</th>
          <th>SAT</th>
          <th>Existencia</th>
          <th>Inventario</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr class="prod-empty-row"><td colspan="8">No hay productos con esos filtros.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $p): ?>
            <?php
              $bajo = !empty($p['bajo_stock']);
              $sinStock = (int)($p['existencia'] ?? 0) <= 0;
              $ocultarAuto = (int)$p['descontinuado'] && $sinStock && !(int)$p['visible'];
            ?>
            <tr data-row='<?php echo htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>'
                class="<?php echo $bajo ? 'row-bajo-stock' : ''; ?>">
              <td><strong><?php echo htmlspecialchars($p['clave']); ?></strong></td>
              <td>
                <?php echo htmlspecialchars($p['nombre']); ?>
                <?php if ((int)$p['gratis_profesor']): ?>
                  <span class="catalog-badge catalog-badge--ok">Gratis prof.</span>
                <?php endif; ?>
              </td>
              <td><?php echo catalog_format_mxn((float)$p['precio']); ?></td>
              <td><small><?php echo htmlspecialchars($p['clave_sat']); ?> / <?php echo htmlspecialchars($p['unidad_sat']); ?></small></td>
              <td>
                <strong><?php echo (int)($p['existencia'] ?? 0); ?></strong>
                <?php if ($bajo): ?>
                  <span class="catalog-badge catalog-badge--warn">Bajo stock</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)($p['controla_inventario'] ?? 1)): ?>
                  <span class="catalog-badge catalog-badge--ok">Con stock</span>
                <?php else: ?>
                  <span class="catalog-badge catalog-badge--muted">Sin límite</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$p['visible']): ?><span class="catalog-badge catalog-badge--ok">Visible</span><?php else: ?><span class="catalog-badge catalog-badge--muted">Oculto</span><?php endif; ?>
                <?php if ((int)$p['descontinuado']): ?><span class="catalog-badge catalog-badge--danger">Descontinuado</span><?php endif; ?>
                <?php if ($ocultarAuto): ?><span class="catalog-badge catalog-badge--muted">Sin existencia</span><?php endif; ?>
              </td>
              <td>
                <div class="catalog-actions">
                  <?php if ($puedeAdmin): ?>
                    <button type="button" class="btn-editar-prod" title="Editar"><i class="fas fa-pen"></i></button>
                    <button type="button" class="btn-entrada-prod" title="Registrar entrada"><i class="fas fa-truck"></i></button>
                    <button type="button" class="btn-merma-prod danger" title="Merma"><i class="fas fa-minus-circle"></i></button>
                    <button type="button" class="btn-eliminar-prod danger" title="Descontinuar"><i class="fas fa-trash"></i></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($puedeAdmin): ?>
<div class="catalog-modal" id="modal-producto">
  <div class="catalog-modal__panel" style="width:min(720px,100%);">
    <h3 id="modal-prod-titulo" style="margin-top:0;">Producto</h3>
    <form id="form-producto" action="php/producto_save.php" method="POST">
      <input type="hidden" name="id_producto" id="prod-id" value="0">
      <div class="catalog-form-grid">
        <div>
          <label>Clave</label>
          <input type="text" name="clave" id="prod-clave" required maxlength="40">
        </div>
        <div>
          <label>Nombre</label>
          <input type="text" name="nombre" id="prod-nombre" required maxlength="160">
        </div>
        <div class="full">
          <label>Descripción</label>
          <textarea name="descripcion" id="prod-descripcion" rows="2"></textarea>
        </div>
        <div>
          <label>Precio ($)</label>
          <input type="number" name="precio" id="prod-precio" min="0" step="0.01" value="0">
        </div>
        <div>
          <label>Stock mínimo (alerta)</label>
          <input type="number" name="stock_minimo" id="prod-stock-min" min="0" value="5">
        </div>
        <div>
          <label>Clave SAT</label>
          <input type="text" name="clave_sat" id="prod-clave-sat" maxlength="20" value="01010101">
        </div>
        <div>
          <label>Unidad SAT</label>
          <input type="text" name="unidad_sat" id="prod-unidad-sat" maxlength="10" value="H87">
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" id="prod-orden" min="0" value="0">
        </div>
        <div class="full" style="display:flex; flex-wrap:wrap; gap:16px;">
          <label><input type="checkbox" name="controla_inventario" id="prod-controla-inv" value="1" checked> Controla inventario (libros, USB, etc.)</label>
          <label><input type="checkbox" name="gratis_profesor" id="prod-gratis" value="1"> Gratis para profesores</label>
          <label><input type="checkbox" name="visible" id="prod-visible" value="1" checked> Visible</label>
          <label><input type="checkbox" name="descontinuado" id="prod-descontinuado" value="1"> Descontinuado</label>
          <label><input type="checkbox" name="activo" id="prod-activo" value="1" checked> Activo</label>
        </div>
      </div>
      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
        <button type="button" id="btn-cerrar-prod">Cancelar</button>
        <button type="submit" class="primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="catalog-modal" id="modal-movimiento">
  <div class="catalog-modal__panel" style="width:min(400px,100%);">
    <h3 id="modal-mov-titulo" style="margin-top:0;">Movimiento de inventario</h3>
    <form id="form-movimiento">
      <input type="hidden" id="mov-id-producto" value="0">
      <input type="hidden" id="mov-tipo" value="entrada">
      <input type="hidden" id="mov-id-plantel" value="<?php echo $idPlantel; ?>">
      <p id="mov-producto-label" style="color:#666;"></p>
      <div style="margin-bottom:12px;">
        <label><strong>Cantidad</strong></label>
        <input type="number" id="mov-cantidad" min="1" value="1" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
      </div>
      <div style="margin-bottom:12px;">
        <label><strong>Notas</strong></label>
        <textarea id="mov-notas" rows="2" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></textarea>
      </div>
      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" id="btn-cerrar-mov">Cancelar</button>
        <button type="submit" class="primary" id="btn-guardar-mov">Registrar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  const idPlantel = <?php echo $idPlantel; ?>;
  const msg = document.getElementById('respuesta-productos');
  const modalProd = document.getElementById('modal-producto');
  const modalMov = document.getElementById('modal-movimiento');
  [modalProd, modalMov].forEach((m) => {
    if (m && m.parentElement !== document.body) document.body.appendChild(m);
  });

  const tabla = document.getElementById('tabla-productos');
  if (window.HayDataTable && tabla && !tabla.querySelector('.prod-empty-row')) {
    HayDataTable.init('#tabla-productos', {
      order: [[0, 'asc']],
      columnDefs: [{ orderable: false, targets: 7 }],
      pageLength: 25,
    });
  } else if (window.HayDataTable && tabla) {
    HayDataTable.init('#tabla-productos', {
      paging: false,
      searching: false,
      info: false,
    });
  }

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  function buildQuery() {
    const params = new URLSearchParams();
    const q = document.getElementById('filtro-q-prod')?.value || '';
    const plantel = document.getElementById('filtro-plantel-prod')?.value || idPlantel;
    if (q) params.set('q', q);
    params.set('id_plantel', plantel);
    const visible = document.getElementById('filtro-visible-prod')?.value;
    const desc = document.getElementById('filtro-desc-prod')?.value;
    const activo = document.getElementById('filtro-activo-prod')?.value;
    if (visible !== '') params.set('visible', visible);
    if (desc !== '') params.set('descontinuado', desc);
    if (activo !== '') params.set('activo', activo);
    if (document.getElementById('filtro-bajo-prod')?.checked) params.set('solo_bajo_stock', '1');
    return params;
  }

  document.getElementById('btn-buscar-prod')?.addEventListener('click', () => {
    cargarSeccion('admin_productos', buildQuery());
  });

  document.querySelectorAll('.btn-confirmar-entrada').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const cant = prompt('¿Cuántas piezas recibió el plantel?', btn.getAttribute('data-cantidad') || '1');
      if (cant === null) return;
      const fd = new FormData();
      fd.append('action', 'confirmar_entrada');
      fd.append('id_movimiento', btn.getAttribute('data-movimiento'));
      fd.append('cantidad_confirmada', cant);
      fd.append('id_plantel', idPlantel);
      const res = await fetch('php/inventario_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion, buildQuery());
    });
  });

  function cerrarModal(modal) {
    if (!modal) return;
    modal.classList.remove('is-open');
    if (!document.querySelector('.catalog-modal.is-open')) {
      document.body.style.overflow = '';
    }
  }

  function abrirProducto(data) {
    if (!modalProd) return;
    document.getElementById('modal-prod-titulo').textContent = data ? 'Editar producto' : 'Nuevo producto';
    document.getElementById('prod-id').value = data ? data.id_producto : '0';
    document.getElementById('prod-clave').value = data ? data.clave : '';
    document.getElementById('prod-nombre').value = data ? data.nombre : '';
    document.getElementById('prod-descripcion').value = data ? (data.descripcion || '') : '';
    document.getElementById('prod-precio').value = data ? data.precio : '0';
    document.getElementById('prod-stock-min').value = data ? (data.stock_minimo || data.stock_alerta || 5) : '5';
    document.getElementById('prod-clave-sat').value = data ? data.clave_sat : '01010101';
    document.getElementById('prod-unidad-sat').value = data ? data.unidad_sat : 'H87';
    document.getElementById('prod-orden').value = data ? data.orden : '0';
    document.getElementById('prod-controla-inv').checked = data ? Number(data.controla_inventario ?? 1) === 1 : true;
    document.getElementById('prod-gratis').checked = data ? Number(data.gratis_profesor) === 1 : false;
    document.getElementById('prod-visible').checked = data ? Number(data.visible) === 1 : true;
    document.getElementById('prod-descontinuado').checked = data ? Number(data.descontinuado) === 1 : false;
    document.getElementById('prod-activo').checked = data ? Number(data.activo) === 1 : true;
    modalProd.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function abrirMovimiento(row, tipo) {
    if (!modalMov) return;
    document.getElementById('mov-id-producto').value = row.id_producto;
    document.getElementById('mov-tipo').value = tipo;
    document.getElementById('mov-id-plantel').value = document.getElementById('filtro-plantel-prod')?.value || idPlantel;
    document.getElementById('mov-producto-label').textContent = row.nombre + ' (' + row.clave + ')';
    document.getElementById('modal-mov-titulo').textContent = tipo === 'entrada' ? 'Registrar entrada al plantel' : 'Registrar merma';
    document.getElementById('btn-guardar-mov').textContent = tipo === 'entrada' ? 'Solicitar confirmación' : 'Aplicar merma';
    document.getElementById('mov-cantidad').value = '1';
    document.getElementById('mov-notas').value = '';
    modalMov.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function parseRow(btn) {
    try {
      return JSON.parse(btn.closest('tr')?.getAttribute('data-row') || 'null');
    } catch (e) {
      return null;
    }
  }

  document.getElementById('btn-nuevo-producto')?.addEventListener('click', () => abrirProducto(null));
  document.getElementById('btn-cerrar-prod')?.addEventListener('click', () => cerrarModal(modalProd));
  document.getElementById('btn-cerrar-mov')?.addEventListener('click', () => cerrarModal(modalMov));

  tabla?.addEventListener('click', async (e) => {
    const edit = e.target.closest('.btn-editar-prod');
    if (edit) {
      const row = parseRow(edit);
      if (row) abrirProducto(row);
      return;
    }
    const entrada = e.target.closest('.btn-entrada-prod');
    if (entrada) {
      const row = parseRow(entrada);
      if (row) abrirMovimiento(row, 'entrada');
      return;
    }
    const merma = e.target.closest('.btn-merma-prod');
    if (merma) {
      const row = parseRow(merma);
      if (row) abrirMovimiento(row, 'merma');
      return;
    }
    const del = e.target.closest('.btn-eliminar-prod');
    if (del) {
      const row = parseRow(del);
      if (!row) return;
      if (!confirm('¿Descontinuar «' + row.nombre + '» y ocultarlo?')) return;
      const fd = new FormData();
      fd.append('id_producto', row.id_producto);
      const res = await fetch('php/producto_delete.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion, buildQuery());
    }
  });

  document.getElementById('form-producto')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    ['gratis_profesor', 'visible', 'descontinuado', 'activo'].forEach((n) => {
      const el = document.getElementById('prod-' + (n === 'gratis_profesor' ? 'gratis' : n === 'descontinuado' ? 'descontinuado' : n));
      if (el && !el.checked) fd.delete(n);
    });
    const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok' && data.seccion) {
      cerrarModal(modalProd);
      cargarSeccion(data.seccion, buildQuery());
    }
  });

  document.getElementById('form-movimiento')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData();
    const tipo = document.getElementById('mov-tipo').value;
    fd.append('action', tipo === 'entrada' ? 'registrar_entrada' : 'merma');
    fd.append('id_producto', document.getElementById('mov-id-producto').value);
    fd.append('id_plantel', document.getElementById('mov-id-plantel').value);
    fd.append('cantidad', document.getElementById('mov-cantidad').value);
    fd.append('notas', document.getElementById('mov-notas').value);
    const res = await fetch('php/inventario_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok' && data.seccion) {
      cerrarModal(modalMov);
      cargarSeccion(data.seccion, buildQuery());
    }
  });
})();
</script>
