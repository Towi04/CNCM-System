<?php
require_once __DIR__ . '/../config.php';
if (!asistencia_puede_tomar()) {
    echo '<div class="alert">No autorizado.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$fecha = trim($_GET['fecha'] ?? date('Y-m-d'));
$grupos = $pdo->prepare('SELECT id_grupo, clave, aula FROM grupos WHERE id_plantel = ? ORDER BY clave');
$grupos->execute([$idPlantel]);
$listaGrupos = $grupos->fetchAll(PDO::FETCH_ASSOC);
$estadosContacto = asistencia_estados_contacto_falta();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/asistencia.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="asist-wrap">
  <div class="asist-checada-header" style="margin-bottom:16px;">
    <div>
      <h2 style="margin:0;"><i class="fas fa-door-open"></i> Rondín de asistencia</h2>
      <p class="asist-checada-sub">
        Alumnos que <strong>no checaron con huella</strong>. Confirme quién sí está presente y registre seguimiento
        de quienes faltaron (llamadas, asesorías, acuerdos).
      </p>
    </div>
    <div class="asist-checada-header__actions">
      <?php if (asistencia_puede_checada()): ?>
      <button type="button" class="secondary" onclick="cargarSeccion('asistencia_checada')">
        <i class="fas fa-fingerprint"></i> Lector de huella
      </button>
      <?php endif; ?>
      <button type="button" class="secondary" onclick="cargarSeccion('asistencia_registros')">Ver checados</button>
      <button type="button" onclick="cargarSeccion('asistencia')"><i class="fas fa-arrow-left"></i> Asistencias</button>
    </div>
  </div>

  <div class="asist-rondin-quick">
    <label for="rondin-buscar"><strong>Registrar presente — buscar alumno</strong></label>
    <p class="asist-checada-hint" style="margin:4px 0 8px;">Número de control, nombre o apellido en un solo campo.</p>
    <div class="asist-rondin-quick__row">
      <div class="asist-rondin-buscar-wrap">
        <input type="search" id="rondin-buscar" class="asist-movil-input" autocomplete="off"
          placeholder="Ej. 10042, García, María…" style="margin:0;">
        <div id="rondin-sugerencias" class="asist-rondin-sugerencias" hidden></div>
      </div>
      <button type="button" class="primary" id="btn-rondin-registrar">Marcar presente</button>
    </div>
    <p id="rondin-msg" class="asist-checada-msg" aria-live="polite"></p>
  </div>

  <div class="asist-toolbar">
    <div>
      <label>Fecha</label>
      <input type="date" id="reg-fecha" value="<?php echo htmlspecialchars($fecha); ?>">
    </div>
    <div>
      <label>Grupo</label>
      <select id="reg-grupo">
        <option value="">Todos</option>
        <?php foreach ($listaGrupos as $g): ?>
        <option value="<?php echo (int)$g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="flex:1; min-width:160px;">
      <label>Filtrar faltantes</label>
      <input type="search" id="reg-buscar" placeholder="Nombre, apellido o control">
    </div>
    <div>
      <label style="display:flex;align-items:center;gap:6px;margin-top:18px;">
        <input type="checkbox" id="reg-todos-grupos"> Todos los grupos (no solo con clase hoy)
      </label>
    </div>
    <div>
      <button type="button" class="primary" id="btn-reg-filtrar">Actualizar</button>
    </div>
  </div>

  <input type="hidden" id="reg-vista" value="faltantes">

  <div id="reg-total" style="margin-bottom:12px; color:#666; font-size:0.9rem;"></div>
  <div id="reg-faltantes-wrap"></div>
</div>

<div id="modal-rondin-baja" class="asist-rondin-modal" hidden>
  <div class="asist-rondin-modal__box">
    <h3>Registrar baja</h3>
    <p><strong id="baja-nombre-alumno"></strong></p>
    <input type="hidden" id="baja-id-alumno">
    <input type="hidden" id="baja-id-grupo">
    <label>Tipo</label>
    <select id="baja-tipo-rondin" style="width:100%; margin-bottom:8px; padding:8px;">
      <option value="temporal">Baja temporal (puede regresar)</option>
      <option value="definitiva">Baja definitiva (no regresa)</option>
    </select>
    <label>Motivo</label>
    <textarea id="baja-motivo-rondin" rows="3" style="width:100%; margin-bottom:12px;" placeholder="Lo que indicó el alumno o tutor…"></textarea>
    <div style="display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" id="btn-baja-rondin-cancel">Cancelar</button>
      <button type="button" class="primary" id="btn-baja-rondin-confirm" style="background:#b71c1c;">Registrar baja</button>
    </div>
  </div>
</div>

<script>
window.HAY_REGISTROS_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/asistencia_registros_api.php'),
    'puede_eliminar' => false,
    'puede_registrar' => true,
    'puede_notas' => true,
    'puede_baja' => true,
    'vista_default' => 'faltantes',
    'tipo_default' => 'alumno',
    'estados_contacto' => $estadosContacto,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/asistencia_registros.js?v=20260607'), ENT_QUOTES, 'UTF-8'); ?>"></script>
