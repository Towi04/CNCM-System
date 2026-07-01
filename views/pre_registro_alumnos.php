<?php

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}



$puedeVerPreregistro = (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total())
    || preregistro_puede_acceder();
if (!$puedeVerPreregistro) {
    echo '<div class="alert">No tiene permiso para ver pre-registros. Cierre sesión y vuelva a entrar; si persiste, contacte a supervisión para revisar su rol en el sistema.</div>';
    return;
}



$idPlantel = plantel_id_activo();

$labels = preregistro_labels();

$filtros = [

    'q' => trim($_GET['q'] ?? ''),

    'estado' => trim($_GET['estado'] ?? ''),

];

$lista = preregistro_listar($pdo, $idPlantel, $filtros);

$alertas = array_values(array_filter(
    preregistro_alertas_plantel($pdo, $idPlantel),
    static fn(array $a): bool => ($a['tipo'] ?? '') !== 'factura_incompleta'
));

$puedeCobrar = preregistro_puede_cobrar();

$puedeFactura = preregistro_puede_gestionar_factura();

$puedeComision = preregistro_puede_reasignar_comision();

$apiComision = hay_asset_url('php/preregistro_asesor_api.php');

?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<link rel="stylesheet" href="css/pre_registro.css">

<link rel="stylesheet" href="css/hay_buttons.css">



<div class="prereg-wrap">

  <div id="respuesta-prereg-lista" class="catalog-alert" style="display:none;"></div>



  <?php if (!empty($alertas)): ?>

    <div class="prereg-alertas">

      <strong><i class="fas fa-bell"></i> Alertas del sistema</strong>

      <ul>

        <?php foreach ($alertas as $a): ?>

          <li>

            <?php echo htmlspecialchars($a['mensaje']); ?>

            <button type="button" class="btn-resolver-alerta secondary" data-id="<?php echo (int)$a['id_alerta']; ?>" style="margin-left:8px; font-size:0.8rem;">Marcar atendida</button>

          </li>

        <?php endforeach; ?>

      </ul>

    </div>

  <?php endif; ?>



  <div class="prereg-list-bar">

    <button type="button" class="btn-prereg-agregar" onclick="cargarSeccion('pre_registro_nuevo')">

      <i class="fas fa-plus"></i> Agregar Pre-registro

    </button>

    <div class="prereg-filtro-estado-wrap" style="margin-left:auto;">

      <label for="prereg-filtro-estado">Estado</label>

      <select id="prereg-filtro-estado">

        <option value="">Todos</option>

        <?php foreach ($labels['estado'] as $k => $v): ?>

          <option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>

        <?php endforeach; ?>

      </select>

      <button type="button" class="secondary" id="btn-filtrar-estado-prereg">Aplicar</button>

    </div>

  </div>



  <div class="prereg-table-panel">

    <div class="prereg-dt-toolbar" id="prereg-dt-toolbar"></div>

    <table id="tabla-preregistros" class="prereg-dt display" style="width:100%;">

      <thead>

        <tr>

          <th>Acciones</th>

          <th>Capturó</th>

          <th>Comisión</th>

          <th>F. registro</th>

          <th>Nombre</th>

          <th>Apartado</th>

          <th>Teléfono</th>

          <th>Email</th>

          <th>Observaciones</th>

        </tr>

      </thead>

      <tbody>

        <?php foreach ($lista as $p): ?>

          <?php

            $nombre = mb_strtoupper(preregistro_nombre_completo($p), 'UTF-8');

            $estado = $p['estado'];

            $apartado = (int)$p['tiene_apartado']

                ? catalog_format_mxn((float)($p['monto_apartado'] ?? 0))

                : '$ 0.00';

            $fecha = date('d/m/Y', strtotime($p['creado_en']));

            $tel = $p['telefono'] ?: ($p['telefono2'] ?? '');

            $obs = $p['observaciones'] ?? '';

            if ($estado === 'perdido' && !empty($p['motivo_perdido'])) {

                $obs = trim($obs . ' · Perdido: ' . $p['motivo_perdido']);

            }

            $rowAlert = preregistro_fila_destacada($p);

            $puedeAcciones = !in_array($estado, ['perdido', 'inscrito'], true);
            $navPrereg = preregistro_nav_destino($p);
            $comisionLbl = preregistro_comision_label_from_row($p);
            $capturoNombre = mb_strtoupper($p['asesor_nombre'] ?? '', 'UTF-8');

          ?>

          <tr data-id="<?php echo (int)$p['id_preregistro']; ?>"<?php echo $rowAlert ? ' class="row-alert"' : ''; ?>>

            <td>

              <div class="prereg-acciones">

                <?php if ($puedeAcciones): ?>

                  <?php if ($puedeCobrar): ?>
                  <button type="button" class="btn-icon btn-icon--ok btn-inscribir-prereg" title="Inscribir" data-id="<?php echo (int)$p['id_preregistro']; ?>">

                    <i class="fas fa-check"></i>

                  </button>
                  <?php endif; ?>

                  <button type="button" class="btn-icon btn-icon--wait btn-pendiente-prereg" title="Pendiente" data-id="<?php echo (int)$p['id_preregistro']; ?>">

                    <i class="fas fa-clock"></i>

                  </button>

                  <button type="button" class="btn-icon btn-icon--del btn-perdido-prereg" title="Marcar perdido" data-id="<?php echo (int)$p['id_preregistro']; ?>">

                    <i class="fas fa-trash-alt"></i>

                  </button>

                  <?php if ($puedeFactura && (int)$p['factura_datos_pendientes']): ?>
                  <button type="button" class="btn-icon btn-icon--factura" title="Completar datos de factura" onclick="cargarSeccion('pre_registro_nuevo', 'id=<?php echo (int)$p['id_preregistro']; ?>')">
                    <i class="fas fa-file-invoice"></i>
                  </button>
                  <?php endif; ?>

                <?php elseif ($puedeFactura && (int)$p['factura_datos_pendientes']): ?>

                  <button type="button" class="btn-icon btn-icon--factura" title="Completar datos de factura" onclick="cargarSeccion('pre_registro_nuevo', 'id=<?php echo (int)$p['id_preregistro']; ?>')">

                    <i class="fas fa-file-invoice"></i>

                  </button>

                <?php else: ?>

                  <span style="color:#999; font-size:0.75rem;">—</span>

                <?php endif; ?>

                <?php if ($navPrereg['seccion'] === 'alumno_detalle'): ?>
                <button type="button" class="btn-icon btn-icon--ver-alumno" title="Ver alumno inscrito" onclick="cargarSeccion('alumno_detalle', '<?php echo htmlspecialchars($navPrereg['params'], ENT_QUOTES, 'UTF-8'); ?>')">
                  <i class="fas fa-user"></i>
                </button>
                <?php else: ?>
                <button type="button" class="btn-icon btn-icon--edit" title="Editar" onclick="cargarSeccion('pre_registro_nuevo', 'id=<?php echo (int)$p['id_preregistro']; ?>')">
                  <i class="fas fa-pen"></i>
                </button>
                <?php endif; ?>

                <?php if ($puedeComision): ?>
                <button type="button" class="btn-icon btn-icon--comision btn-comision-prereg" title="Asignar comisión" data-id="<?php echo (int)$p['id_preregistro']; ?>">
                  <i class="fas fa-user-tag"></i>
                </button>
                <?php endif; ?>

              </div>

            </td>

            <td><?php echo htmlspecialchars($capturoNombre); ?></td>

            <td>
              <?php echo htmlspecialchars(mb_strtoupper($comisionLbl, 'UTF-8')); ?>
            </td>

            <td data-order="<?php echo strtotime($p['creado_en']); ?>"><?php echo $fecha; ?></td>

            <td>

              <?php if ($navPrereg['seccion'] === 'alumno_detalle'): ?>
              <a class="prereg-nombre-link" onclick="cargarSeccion('alumno_detalle', '<?php echo htmlspecialchars($navPrereg['params'], ENT_QUOTES, 'UTF-8'); ?>')"><?php echo htmlspecialchars($nombre); ?></a>
              <?php else: ?>
              <a class="prereg-nombre-link" onclick="cargarSeccion('pre_registro_nuevo', 'id=<?php echo (int)$p['id_preregistro']; ?>')"><?php echo htmlspecialchars($nombre); ?></a>
              <?php endif; ?>

              <?php if ($estado !== 'activo'): ?>

                <br><span class="prereg-badge prereg-badge--estado-<?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars($labels['estado'][$estado] ?? $estado); ?></span>

              <?php endif; ?>
              <?php echo preregistro_icono_factura_pendiente_html($p); ?>

            </td>

            <td class="prereg-apartado" data-order="<?php echo (float)($p['monto_apartado'] ?? 0); ?>"><?php echo htmlspecialchars($apartado); ?></td>

            <td><?php echo htmlspecialchars($tel); ?></td>

            <td><?php echo htmlspecialchars($p['email'] ?? ''); ?></td>

            <td><?php echo htmlspecialchars(mb_strimwidth($obs, 0, 120, '…')); ?></td>

          </tr>

        <?php endforeach; ?>

      </tbody>

    </table>

  </div>

</div>



<?php require __DIR__ . '/partials/modal_inscripcion_wizard.php'; ?>



<?php if ($puedeComision): ?>
<div class="catalog-modal" id="modal-prereg-comision">
  <div class="catalog-modal__panel" style="max-width:560px;">
    <h3 style="margin-top:0;"><i class="fas fa-user-tag"></i> Asignar comisión</h3>
    <p style="color:#666; font-size:0.9rem;">La comisión de inscripción se acredita al asesor indicado aquí, aunque otro haya capturado el pre-registro. Use CNCM cuando la inscripción es de la escuela o el asesor ya no labora.</p>
    <input type="hidden" id="prc-id" value="0">
    <p id="prc-prospecto" style="font-weight:600;"></p>
    <p id="prc-captura" style="color:#666; font-size:0.88rem;"></p>

    <div class="field" style="margin-bottom:12px;">
      <label><strong>Asesor comisión</strong></label>
      <select id="prc-asesor" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
        <option value="">— Cargando —</option>
      </select>
    </div>

    <div class="field" style="margin-bottom:12px;">
      <label><strong>Vincular entrevista previa</strong> <span style="font-weight:normal;color:#888;">(opcional)</span></label>
      <div style="display:flex; gap:8px;">
        <input type="text" id="prc-buscar-ent" placeholder="Nombre o teléfono…" style="flex:1; padding:10px; border:1px solid #ddd; border-radius:8px;">
        <button type="button" class="secondary" id="prc-btn-buscar-ent">Buscar</button>
      </div>
      <div id="prc-ent-resultados" style="margin-top:8px; max-height:160px; overflow:auto;"></div>
      <p id="prc-ent-seleccionada" style="font-size:0.85rem; color:#1565c0; margin:8px 0 0; display:none;"></p>
      <input type="hidden" id="prc-id-entrevista" value="0">
    </div>

    <div class="field" style="margin-bottom:12px;">
      <label><strong>Motivo</strong> <span style="font-weight:normal;color:#888;">(opcional)</span></label>
      <input type="text" id="prc-motivo" maxlength="200" placeholder="Ej. Cliente regresó; comisión al asesor que dio información" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">
    </div>

    <div style="display:flex; gap:10px; justify-content:flex-end;">
      <button type="button" class="secondary" id="prc-cerrar">Cancelar</button>
      <button type="button" class="primary" id="prc-guardar">Guardar</button>
    </div>
    <p id="prc-msg" style="margin:10px 0 0; font-size:0.88rem; display:none;"></p>
  </div>
</div>
<?php endif; ?>



<div class="catalog-modal" id="modal-perdido">

  <div class="catalog-modal__panel">

    <h3 style="margin-top:0;">Marcar como perdido</h3>

    <p style="color:#666;">Esta información se usará después en gráficas de por qué perdemos prospectos.</p>

    <input type="hidden" id="perdido-id" value="0">

    <div style="margin-bottom:12px;">

      <label><strong>Categoría</strong></label>

      <select id="perdido-categoria" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;">

        <?php foreach ($labels['categoria_perdido'] as $k => $v): ?>

          <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>

        <?php endforeach; ?>

      </select>

    </div>

    <div style="margin-bottom:12px;">

      <label><strong>Motivo (detalle)</strong></label>

      <textarea id="perdido-motivo" rows="3" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" placeholder="Ej. No contestó después de 3 llamadas…"></textarea>

    </div>

    <div style="display:flex; gap:10px; justify-content:flex-end;">

      <button type="button" class="secondary" id="btn-cerrar-perdido">Cancelar</button>

      <button type="button" class="danger" id="btn-confirmar-perdido">Confirmar perdido</button>

    </div>

  </div>

</div>



<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script src="js/inscripcion_wizard.js?v=20260624"></script>

<?php if ($puedeComision): ?>
<script>
window.HAY_PREREG_COMISION = <?php echo json_encode(['api' => $apiComision], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/preregistro_comision.js?v=20260605'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>

<script>

(function () {

  const msg = document.getElementById('respuesta-prereg-lista');

  function showMsg(ok, text) {

    if (!msg) return;

    msg.style.display = 'block';

    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');

    msg.textContent = text;

  }



  document.getElementById('btn-filtrar-estado-prereg')?.addEventListener('click', () => {

    const p = new URLSearchParams();

    const est = document.getElementById('prereg-filtro-estado')?.value || '';

    if (est) p.set('estado', est);

    cargarSeccion('pre_registro_alumnos', p);

  });



  if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {

    const dt = jQuery('#tabla-preregistros').DataTable({

      pageLength: 10,

      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],

      order: [[3, 'desc']],

      language: {

        url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json',

        search: 'Buscar:',

        lengthMenu: 'Mostrar _MENU_',

        zeroRecords: 'No hay pre-registros',

        info: 'Mostrando _START_ a _END_ de _TOTAL_',

      },

      dom: 'Blfrtip',

      buttons: [

        { extend: 'excelHtml5', text: 'Excel', title: 'Pre_registros_CNCM', exportOptions: { columns: ':not(:first-child)' } },

      ],

      columnDefs: [{ orderable: false, targets: 0 }],

    });

    const toolbar = document.getElementById('prereg-dt-toolbar');

    const wrapper = jQuery('.dataTables_wrapper');

    if (toolbar && wrapper.length) {

      wrapper.find('.dataTables_length, .dt-buttons, .dataTables_filter').appendTo(toolbar);

    }

  }



  async function cambiarEstado(id, estado, extra) {

    const fd = new FormData();

    fd.append('id_preregistro', id);

    fd.append('estado', estado);

    if (extra) Object.keys(extra).forEach((k) => fd.append(k, extra[k]));

    const res = await fetch('php/preregistro_estado.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });

    const data = await res.json();

    showMsg(data.status === 'ok', data.message || '');

    if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);

  }



  document.querySelectorAll('.btn-inscribir-prereg').forEach((btn) => {

    btn.addEventListener('click', () => {

      const id = btn.dataset.id;

      if (!id) return;

      if (window.HayInscripcionWizard?.openFromPrereg) {

        window.HayInscripcionWizard.openFromPrereg(id, () => cargarSeccion('pre_registro_alumnos'));

        return;

      }

      alert('No se pudo abrir el asistente de inscripción. Recargue el panel.');

    });

  });

  document.querySelectorAll('.btn-pendiente-prereg').forEach((btn) => {

    btn.addEventListener('click', () => cambiarEstado(btn.dataset.id, 'pendiente'));

  });



  const modalPerdido = document.getElementById('modal-perdido');

  document.querySelectorAll('.btn-perdido-prereg').forEach((btn) => {

    btn.addEventListener('click', () => {

      document.getElementById('perdido-id').value = btn.dataset.id;

      document.getElementById('perdido-motivo').value = '';

      modalPerdido?.classList.add('is-open');

    });

  });

  document.getElementById('btn-cerrar-perdido')?.addEventListener('click', () => modalPerdido?.classList.remove('is-open'));

  document.getElementById('btn-confirmar-perdido')?.addEventListener('click', async () => {

    const id = document.getElementById('perdido-id').value;

    const cat = document.getElementById('perdido-categoria').value;

    const mot = document.getElementById('perdido-motivo').value.trim();

    if (!mot) { alert('Escribe el motivo'); return; }

    modalPerdido.classList.remove('is-open');

    await cambiarEstado(id, 'perdido', { categoria_perdido: cat, motivo_perdido: mot });

  });



  document.querySelectorAll('.btn-resolver-alerta').forEach((btn) => {

    btn.addEventListener('click', async () => {

      const fd = new FormData();

      fd.append('action', 'resolver');

      fd.append('id_alerta', btn.dataset.id);

      const res = await fetch('php/preregistro_alerta.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });

      const data = await res.json();

      if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);

    });

  });

})();

</script>

