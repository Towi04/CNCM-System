<?php
require_once __DIR__ . '/../config.php';

if (!supervisor_grupos_historico_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para la carga historica de grupos.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$contexto = supervisor_grupos_historico_contexto($pdo, $idPlantel);
$apiUrl = hay_asset_url('php/supervisor_grupos_historico_api.php');
?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/supervisor_grupos_historico.css?v=20260702'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap hist-grupos-wrap">
  <div class="catalog-header">
    <div>
      <h2><i class="fas fa-history"></i> Carga historica de grupos</h2>
      <p class="hist-grupos-intro">
        Alta retroactiva de grupos ya iniciados, alumnos inscritos, pagos con fecha real y calificaciones por materia/fase.
      </p>
    </div>
  </div>

  <div id="hist-grupos-msg" class="catalog-alert" style="display:none;"></div>

  <div class="hist-grupos-grid">
    <section class="welcome-card hist-card">
      <h3>1. Registrar grupo existente</h3>
      <p>Use la clave nueva del sistema y capture la clave anterior para conservar el historial.</p>
      <form id="hist-form-grupo" class="catalog-form-grid" novalidate>
        <input type="hidden" name="action" value="crear_grupo">
        <label>
          Especialidad
          <select name="id_especialidad" data-hist-especialidad required>
            <option value="">Seleccione</option>
            <?php foreach ($contexto['especialidades'] as $e): ?>
              <option value="<?php echo (int) $e['id_especialidad']; ?>">
                <?php echo htmlspecialchars(trim(($e['clave'] ?? '') . ' — ' . ($e['nombre'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Fecha real de inicio
          <input type="date" name="fecha_inicio" required>
        </label>
        <label>
          Clave anterior
          <input type="text" name="clave_anterior" placeholder="Ej. GPO-ING-01">
        </label>
        <label>
          Clave nueva
          <input type="text" name="clave_nueva" placeholder="Ej. IS18" required>
        </label>
        <label>
          Fase/materia actual
          <select name="id_fase_actual" data-hist-fase>
            <option value="">Primero seleccione especialidad</option>
          </select>
        </label>
        <label>
          Horario descriptivo
          <input type="text" name="horario_texto" placeholder="Sabados 08:00-12:00">
        </label>
        <div class="full hist-actions">
          <button type="submit" class="primary"><i class="fas fa-save"></i> Crear grupo historico</button>
        </div>
      </form>
    </section>

    <section class="welcome-card hist-card">
      <h3>2. Normalizar clave de grupo ya existente</h3>
      <p>Si el grupo ya fue creado, cambie la clave actual y deje registrada la clave anterior.</p>
      <form id="hist-form-clave" class="catalog-form-grid" novalidate>
        <input type="hidden" name="action" value="actualizar_clave">
        <label class="full">
          Grupo
          <select name="id_grupo" data-hist-grupo required></select>
        </label>
        <label>
          Clave anterior a conservar
          <input type="text" name="clave_anterior" placeholder="Se usara la clave actual si se deja vacia">
        </label>
        <label>
          Clave nueva
          <input type="text" name="clave_nueva" placeholder="Ej. CD15" required>
        </label>
        <label class="full">
          Motivo
          <input type="text" name="motivo" value="Normalizacion al patron del sistema">
        </label>
        <div class="full hist-actions">
          <button type="submit" class="primary"><i class="fas fa-exchange-alt"></i> Actualizar clave</button>
        </div>
      </form>
    </section>

    <section class="welcome-card hist-card">
      <h3>3. Cargar alumnos del grupo</h3>
      <p>Una linea por alumno: <code>Nombre completo | telefono | email | numero_control opcional</code>.</p>
      <form id="hist-form-alumnos" class="catalog-form-grid" novalidate>
        <input type="hidden" name="action" value="cargar_alumnos">
        <label>
          Grupo
          <select name="id_grupo" data-hist-grupo required></select>
        </label>
        <label>
          Fecha real de inscripcion
          <input type="date" name="fecha_inscripcion" required>
        </label>
        <label>
          Forma de pago
          <select name="forma_pago">
            <option value="mensual">Mensual</option>
            <option value="semanal">Semanal</option>
          </select>
        </label>
        <label class="full">
          Alumnos
          <textarea name="alumnos_text" rows="8" required placeholder="Juan Perez Lopez | 4641234567 | juan@email.com | 10021"></textarea>
        </label>
        <div class="full hist-actions">
          <button type="submit" class="primary"><i class="fas fa-user-plus"></i> Cargar alumnos</button>
        </div>
      </form>
    </section>

    <section class="welcome-card hist-card">
      <h3>4. Cargar colegiaturas pagadas</h3>
      <p>Una linea por pago: <code>#control o nombre | monto | fecha_pago | tipo | forma | concepto | periodo</code>.</p>
      <form id="hist-form-pagos" class="catalog-form-grid" novalidate>
        <input type="hidden" name="action" value="cargar_pagos">
        <label class="full">
          Grupo
          <select name="id_grupo" data-hist-grupo required></select>
        </label>
        <label class="full">
          Pagos
          <textarea name="pagos_text" rows="8" required placeholder="10021 | 850 | 2026-05-04 | mensualidad | efectivo | Mayo 2026 | 2026-05"></textarea>
        </label>
        <div class="full hist-actions">
          <button type="submit" class="primary"><i class="fas fa-file-invoice-dollar"></i> Guardar pagos</button>
        </div>
      </form>
    </section>

    <section class="welcome-card hist-card hist-card--wide">
      <h3>5. Cargar calificaciones por materia/fase</h3>
      <p>Seleccione la fase correspondiente. Una linea por alumno: <code>#control o nombre | calificacion | observaciones</code>.</p>
      <form id="hist-form-calificaciones" class="catalog-form-grid" novalidate>
        <input type="hidden" name="action" value="cargar_calificaciones">
        <label>
          Grupo
          <select name="id_grupo" data-hist-grupo data-hist-grupo-calificaciones required></select>
        </label>
        <label>
          Materia/fase
          <select name="id_fase" data-hist-fase-calificaciones required>
            <option value="">Seleccione un grupo</option>
          </select>
        </label>
        <label class="full">
          Calificaciones
          <textarea name="calificaciones_text" rows="8" required placeholder="10021 | 9.2 | Parcial cargado historicamente"></textarea>
        </label>
        <div class="full hist-actions">
          <button type="submit" class="primary"><i class="fas fa-star"></i> Guardar calificaciones</button>
        </div>
      </form>
    </section>
  </div>

  <section class="welcome-card hist-table-card">
    <h3>Grupos del plantel</h3>
    <div class="catalog-table-wrap">
      <table class="catalog-table" id="hist-grupos-tabla">
        <thead>
          <tr>
            <th>Clave</th>
            <th>Clave anterior</th>
            <th>Especialidad</th>
            <th>Inicio</th>
            <th>Fase actual</th>
            <th>Alumnos</th>
            <th>Pagos</th>
            <th>Calif.</th>
          </tr>
        </thead>
        <tbody data-hist-grupos-body></tbody>
      </table>
    </div>
  </section>
</div>

<script>
  window.HaySupervisorGruposHistorico = {
    api: <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>,
    contexto: <?php echo json_encode($contexto, JSON_UNESCAPED_UNICODE); ?>
  };
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/supervisor_grupos_historico.js?v=20260702'), ENT_QUOTES, 'UTF-8'); ?>"></script>
