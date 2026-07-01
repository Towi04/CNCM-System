<?php

require_once __DIR__ . '/../config.php';

global $pdo;

grupo_clave_ensure_schema($pdo);

$idPlantelGrupo = plantel_scope_id($pdo);

grupo_clave_sincronizar_secuencias($pdo, $idPlantelGrupo);

$listaProfs = grupo_docente_listar_profesores_plantel($pdo, $idPlantelGrupo);
$materiasPrep = GRUPO_MATERIAS_PREP;



$preview = grupo_clave_vista_previa($pdo, $idPlantelGrupo, 'I', 'S', false, false);

$apiGrupo = hay_asset_url('php/grupo_nuevo_api.php');

?>

<link rel="stylesheet" href="css/resultados.css">

<link rel="stylesheet" href="css/admin_catalogo.css">



<div class="result-container" id="grupo-nuevo-wrap">

  <div class="result-header">

    <h2>Nuevo grupo</h2>

  </div>



  <div class="patron-desc">

    <p style="color:#666; margin:0 0 12px;">

      La clave se forma con <strong>área + horario + consecutivo</strong> por prefijo, <strong>por plantel</strong>.

      Ejemplos: inglés sábado <strong>IS18</strong>, computación domingo <strong>CD15</strong>, infantil pareja <strong>IK18 + CK18</strong> (misma secuencia).

      Extensivo: prefijo <strong>E</strong> (EI212). Personalizado: <strong>PER-TOEFL</strong>.

    </p>



    <form method="POST" action="php/grupo_save.php" id="form-grupo-nuevo" data-no-global-ajax>

      <input type="hidden" name="generar_clave" value="1">

      <input type="hidden" name="id_especialidad" id="gn-id-especialidad" value="">



      <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">

        <div>

          <label><strong>Tipo de grupo</strong></label><br>

          <select name="tipo_grupo" id="tipo-grupo" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

            <option value="regular">Regular (I, K, C, PA, PE)</option>

            <option value="extensivo">Extensivo (E + área)</option>

            <option value="personalizado">Personalizado (PER-…)</option>

          </select>

        </div>

        <div id="wrap-area">

          <label><strong>Área</strong></label><br>

          <select name="codigo_area" id="codigo-area" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

            <?php foreach (GRUPO_AREAS as $k => $lbl): ?>

              <option value="<?php echo $k; ?>"<?php echo $k === 'I' ? ' selected' : ''; ?>><?php echo $k; ?> — <?php echo htmlspecialchars($lbl); ?></option>

            <?php endforeach; ?>

          </select>

        </div>

        <div id="wrap-horario">

          <label><strong>Horario</strong></label><br>

          <select name="codigo_horario" id="codigo-horario" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

            <?php foreach (GRUPO_HORARIOS as $k => $lbl): ?>

              <option value="<?php echo $k; ?>"<?php echo $k === 'S' ? ' selected' : ''; ?>><?php echo $k; ?> — <?php echo htmlspecialchars($lbl); ?></option>

            <?php endforeach; ?>

          </select>

        </div>

        <div id="wrap-per" style="display:none;">

          <label><strong>Nombre curso PER-</strong></label><br>

          <input type="text" name="nombre_personalizado" id="nombre-per" placeholder="TOEFL, Excel, TKT…" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

        </div>

        <div>

          <label><strong>Clave (siguiente)</strong></label><br>

          <input type="text" id="clave-preview" readonly value="<?php echo htmlspecialchars($preview['clave'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #c5cae9; border-radius:10px; background:#f5f7ff; font-weight:700;">

          <small id="clave-prefijo-hint" style="color:#666;">Prefijo: <?php echo htmlspecialchars($preview['prefijo'] ?? ''); ?></small>

        </div>

        <div>

          <label><strong>Fecha de inicio</strong></label><br>

          <input type="date" name="fecha_inicio" id="gn-fecha-inicio" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

        </div>

        <div id="wrap-profesor-simple">

          <label><strong>Profesor titular</strong></label><br>

          <select name="id_profesor" id="gn-id-profesor" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

            <option value="">— Sin asignar —</option>

            <?php foreach ($listaProfs as $pr): ?>

              <?php
              $lbl = trim($pr['nombre_completo'] ?? ($pr['nombre'] . ' ' . $pr['apellido']));
              if (!empty($pr['hay_areas'])) {
                  $lbl .= ' · ' . $pr['hay_areas'];
              }
              ?>

              <option value="<?php echo (int)$pr['id_usuario']; ?>"><?php echo htmlspecialchars($lbl); ?></option>

            <?php endforeach; ?>

          </select>

          <small style="color:#666;">Puede elegir cualquier docente del plantel, sin importar su área HAY.</small>

        </div>

        <div id="wrap-docentes-multi" style="display:none; grid-column: 1 / -1;">

          <label><strong>Profesores por materia</strong></label>

          <p style="color:#666; margin:4px 0 10px; font-size:0.9rem;">
            En preparatoria, verano y cursos personalizados puede asignar un maestro distinto por materia.
            Deje en blanco las que aún no tengan docente.
          </p>

          <div id="gn-docentes-tabla" style="display:flex; flex-direction:column; gap:8px;"></div>

          <button type="button" class="secondary" id="gn-btn-add-materia" style="margin-top:10px;">+ Agregar materia</button>

        </div>

      </div>



      <div id="wrap-horas" style="margin-top:14px; display:grid; grid-template-columns: 1fr 1fr; gap:12px;">

        <div>

          <label><strong>Hora de inicio</strong></label><br>

          <input type="time" name="hora_inicio" id="gn-hora-inicio" required value="08:00" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

        </div>

        <div>

          <label><strong>Hora de término</strong> <small style="color:#666;">(sugerida, editable)</small></label><br>

          <input type="time" name="hora_fin" id="gn-hora-fin" required value="12:00" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">

        </div>

      </div>



      <div id="wrap-dias-semana" style="margin-top:12px; display:none;">

        <label><strong>Días de clase (lun–vie)</strong></label>

        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:6px;">

          <?php

          $diasLv = [

              1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes',

          ];

          foreach ($diasLv as $num => $lbl):

          ?>

          <label style="display:flex; align-items:center; gap:6px;">

            <input type="checkbox" name="dia_semana[]" value="<?php echo $num; ?>" class="gn-dia-lv" checked>

            <?php echo $lbl; ?>

          </label>

          <?php endforeach; ?>

        </div>

      </div>



      <p id="wrap-dia-finde" style="margin-top:10px; color:#555; display:none;"></p>



      <div style="margin-top:14px; padding:12px; background:#f9f9f9; border-radius:8px;">

        <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">

          <input type="checkbox" name="grupo_avanzado" id="gn-grupo-avanzado" value="1">

          <strong>Grupo avanzado</strong> <span style="color:#666; font-weight:normal;">(si no, inicia en la primera fase)</span>

        </label>

        <div id="wrap-fase-avanzada" style="display:none;">

          <label><strong>Fase inicial del grupo</strong></label><br>

          <select name="id_fase_actual" id="gn-fase-select" style="width:100%; max-width:420px; padding:10px; border:1px solid #ddd; border-radius:10px;">

            <option value="">— Cargando fases —</option>

          </select>

        </div>

      </div>



      <p id="gn-infantil-hint" style="display:none; margin:12px 0 0; padding:10px 12px; background:#e8f5e9; border-radius:8px; color:#2e7d32; font-size:14px;">
        Al guardar se crearán <strong>dos grupos</strong> con la misma secuencia: <strong>IK</strong> (Inglés infantil) y <strong>CK</strong> (Computación infantil), para inscribir al alumno en una o ambas materias.
      </p>

      <div style="margin-top:14px;">
        <label><strong>Mínimo de alumnos para abrir</strong> <span style="color:#666; font-weight:normal;">(opcional; vacío = sin mínimo)</span></label><br>
        <input type="number" name="min_alumnos" min="1" max="99" placeholder="Ej. 5"
          style="width:120px; padding:10px; border:1px solid #ddd; border-radius:10px;">
      </div>

        <button class="primary" type="submit">Guardar grupo</button>

        <button type="button" onclick="cargarSeccion('grupos')">Cancelar</button>

      </div>

    </form>

  </div>

</div>



<script>

window.HAY_GRUPO_NUEVO = <?php echo json_encode([
    'api' => $apiGrupo,
    'profesores' => array_map(static function ($p) {
        $lbl = trim($p['nombre_completo'] ?? '');
        if (!empty($p['hay_areas'])) {
            $lbl .= ' · ' . $p['hay_areas'];
        }

        return [
            'id' => (int) $p['id_usuario'],
            'label' => $lbl,
        ];
    }, $listaProfs),
    'materiasPrep' => $materiasPrep,
], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/grupo_nuevo.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<script>if (window.hayGrupoNuevoInit) window.hayGrupoNuevoInit();</script>

