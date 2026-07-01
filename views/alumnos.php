<?php
require_once __DIR__ . '/../config.php';
if (!alumno_puede_ver()) {
    echo '<div class="alert">Debes iniciar sesión.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$filtros = [
    'q' => trim($_GET['q'] ?? ''),
    'estado' => trim($_GET['estado'] ?? ''),
    'id_especialidad' => (int) ($_GET['id_especialidad'] ?? 0),
];
if ($filtros['estado'] === 'todos') {
    $filtros['estado'] = '';
}
$lista = alumno_listar($pdo, $idPlantel, $filtros);
$especialidades = $pdo->query(
    'SELECT id_especialidad, nombre FROM especialidades WHERE activo = 1 ORDER BY orden, nombre'
)->fetchAll(PDO::FETCH_ASSOC);
$gruposPlantel = $pdo->prepare(
    'SELECT id_grupo, clave FROM grupos WHERE id_plantel = ? ORDER BY clave ASC'
);
$gruposPlantel->execute([$idPlantel]);
$gruposOpts = $gruposPlantel->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="css/alumnos.css">
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<div class="alumnos-wrap">
  <div class="alumnos-toolbar">
    <div class="field">
      <label>Buscar alumno (nombre o # control)</label>
      <input type="search" id="filtro-q" name="hay_alumnos_buscar" placeholder="Ej. 10011 o César Carrasco" style="min-width:260px; padding:8px;" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-hay-no-autofill="1">
    </div>
    <div class="field">
      <label>Especialidad</label>
      <select id="filtro-especialidad">
        <option value="">Todas</option>
        <?php foreach ($especialidades as $e): ?>
          <option value="<?php echo (int)$e['id_especialidad']; ?>"<?php echo $filtros['id_especialidad'] === (int)$e['id_especialidad'] ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($e['nombre']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Estado</label>
      <select id="filtro-estado">
        <option value="">Activos (excl. baja/grad.)</option>
        <option value="activo"<?php echo $filtros['estado'] === 'activo' ? ' selected' : ''; ?>>Solo activos</option>
        <option value="baja"<?php echo $filtros['estado'] === 'baja' ? ' selected' : ''; ?>>Baja</option>
        <option value="graduado"<?php echo $filtros['estado'] === 'graduado' ? ' selected' : ''; ?>>Graduados</option>
        <option value="todos"<?php echo isset($_GET['estado']) && $_GET['estado'] === 'todos' ? ' selected' : ''; ?>>Todos</option>
      </select>
    </div>
    <div class="field">
      <button type="button" class="primary" id="btn-aplicar-filtros">Aplicar filtros</button>
    </div>
  </div>

  <div class="alumnos-table-wrap hay-dt-panel">
    <table id="tabla-alumnos" class="display alumnos-dt hay-paged-table" style="width:100%;">
      <thead>
        <tr>
          <th># Control</th>
          <th>Nombre</th>
          <th>Asesor</th>
          <th>Grupos</th>
          <th>Especialidad</th>
          <th>Forma pago</th>
          <th>Pagos</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lista as $a): ?>
          <tr data-id="<?php echo (int)$a['id_alumno']; ?>">
            <td><?php echo htmlspecialchars((string)($a['numero_control'] ?? $a['matricula'] ?? $a['id_alumno'])); ?></td>
            <td>
              <a href="#" class="alumnos-link-nombre" data-id="<?php echo (int)$a['id_alumno']; ?>">
                <?php echo htmlspecialchars($a['nombre_completo']); ?>
              </a>
            </td>
            <td><?php echo htmlspecialchars($a['asesor_nombre'] ?? '—'); ?></td>
            <td><?php echo (int)($a['num_grupos'] ?? 0); ?> · <?php echo htmlspecialchars($a['grupos_txt'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($a['especialidad_nombre'] ?? '—'); ?></td>
            <td><?php echo ($a['forma_pago'] ?? 'mensual') === 'semanal' ? 'Semanal' : 'Mensual'; ?></td>
            <td data-order="<?php echo (int)$a['pagos_hechos']; ?>">
              <?php echo (int)$a['pagos_hechos']; ?> / <?php echo (int)$a['pagos_total']; ?>
            </td>
            <td><?php echo htmlspecialchars(alumno_estado_label($a['estado'] ?? 'activo')); ?></td>
            <td>
              <div class="alumnos-acciones">
                <button type="button" class="btn-icon btn-icon--edit btn-editar-alumno" title="Editar" data-id="<?php echo (int)$a['id_alumno']; ?>">
                  <i class="fas fa-pen"></i>
                </button>
                <button type="button" class="btn-icon btn-icon--grupo btn-grupo-alumno" title="Inscribir a otro grupo" data-id="<?php echo (int)$a['id_alumno']; ?>" data-especialidad="<?php echo (int)($a['id_especialidad'] ?? 0); ?>">
                  <i class="fas fa-users"></i>
                </button>
                <button type="button" class="btn-icon btn-icon--ver btn-ver-alumno" title="Ver información" data-id="<?php echo (int)$a['id_alumno']; ?>">
                  <i class="fas fa-id-card"></i>
                </button>
                <button type="button" class="btn-icon" style="background:#f57c00;" title="Consultar adeudo"
                  onclick="cargarSeccion('consulta_adeudo', 'control=<?php echo urlencode((string)($a['numero_control'] ?? $a['id_alumno'])); ?>')">
                  <i class="fas fa-dollar-sign"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/partials/modal_inscripcion_wizard.php'; ?>

<script src="js/inscripcion_wizard.js?v=20260624"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script>
(function () {
  function buildFiltrosQuery() {
    const p = new URLSearchParams();
    const estado = document.getElementById('filtro-estado')?.value;
    if (estado) p.set('estado', estado);
    const esp = document.getElementById('filtro-especialidad')?.value;
    if (esp) p.set('id_especialidad', esp);
    return p;
  }

  document.getElementById('btn-aplicar-filtros')?.addEventListener('click', () => {
    cargarSeccion('alumnos', buildFiltrosQuery());
  });

  function irDetalle(id) {
    setPageHeader('Detalle alumno', 'INICIO / ALUMNOS / DETALLE ALUMNO');
    cargarSeccion('alumno_detalle', 'id=' + id);
  }

  document.querySelectorAll('.alumnos-link-nombre, .btn-ver-alumno').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      irDetalle(el.dataset.id);
    });
  });
  document.querySelectorAll('.btn-editar-alumno').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      irDetalle(el.dataset.id);
    });
  });

  document.querySelectorAll('.btn-grupo-alumno').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idAlumno = parseInt(btn.dataset.id, 10);
      const idEsp = parseInt(btn.dataset.especialidad || '0', 10) || 0;
      if (!idAlumno || !window.HayInscripcionWizard) return;
      HayInscripcionWizard.openFromAlumno(idAlumno, idEsp, 0, (data) => {
        cargarSeccion('alumnos', buildFiltrosQuery());
      });
    });
  });

  const STORAGE_KEY = 'hay_dt_alumnos_v1';
  let saved = null;
  try { saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null'); } catch (e) {}

  const dt = window.HayDataTable?.init('#tabla-alumnos', {
    order: [[0, 'desc']],
    colReorder: typeof jQuery !== 'undefined' && jQuery.fn.colReorder,
    stateSave: true,
    stateDuration: 0,
    stateLoadCallback: function () { return saved; },
    stateSaveCallback: function (settings, data) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    },
    dom: 'Blfrtip',
    buttons: typeof jQuery !== 'undefined' && jQuery.fn.dataTable?.Buttons ? [
      { extend: 'colvis', text: 'Columnas' },
      { extend: 'excelHtml5', text: 'Excel', title: 'Alumnos_CNCM' },
    ] : [],
    columnDefs: [
      { orderable: false, targets: 8 },
    ],
  });
  if (!dt) return;

  dt.on('column-reorder', function () {
    dt.state.save();
  });

  // Búsqueda en vivo por nombre / control (sin recargar)
  const q = document.getElementById('filtro-q');
  if (q) {
    q.addEventListener('input', () => {
      dt.search(q.value || '').draw();
    });
  }
})();
</script>
