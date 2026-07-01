<?php

require_once __DIR__ . '/../config.php';
if (!preregistro_puede_acceder()) {

    echo '<div class="alert">No autorizado.</div>';

    return;

}



$labels = preregistro_labels();

$idPlantel = plantel_scope_id($pdo);

$escuelasPrereg = function_exists('escuelas_listar') ? escuelas_listar($pdo, $idPlantel) : [];

$idEdit = (int) ($_GET['id'] ?? 0);

$row = null;

if ($idEdit > 0) {

    $stmt = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1');

    $stmt->execute([$idEdit, $idPlantel]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {

        echo '<div class="alert">Pre-registro no encontrado.</div>';

        return;

    }

    $idAlumnoInscrito = preregistro_redirect_alumno_id($row);
    if ($idAlumnoInscrito > 0 && !preregistro_puede_editar($row)) {
        echo '<div class="catalog-alert catalog-alert--ok">Este prospecto ya está inscrito. Abriendo el perfil del alumno…</div>';
        echo '<script>if (typeof cargarSeccion === "function") cargarSeccion("alumno_detalle", "id=' . $idAlumnoInscrito . '");</script>';
        return;
    }

}



$especialidades = $pdo->query(

    'SELECT id_especialidad, nombre, clave, inscripcion_abierta, modalidad,
            edad_min, edad_max, costo_inscripcion, inscripcion_por_cuatrimestre,
            costo_mensualidad, costo_cuatrimestre, costo_anual, parciales_por_cuatrimestre
     FROM especialidades
     WHERE activo = 1 AND visible = 1 ORDER BY orden ASC, nombre ASC'

)->fetchAll(PDO::FETCH_ASSOC);



$r = $row ?: [];
if (!$row) {
    foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'telefono', 'email', 'observaciones'] as $k) {
        if (!empty($_GET[$k])) {
            $r[$k] = trim((string) $_GET[$k]);
        }
    }
}
$idEntrevistaVinculo = (int) ($_GET['id_entrevista'] ?? 0);

$puedeComision = preregistro_puede_reasignar_comision();

$asesoresComision = $puedeComision ? preregistro_asesores_comision_opciones($pdo, $idPlantel) : [];

$gradoActual = $r['grado_estudios'] ?? '';

?>

<link rel="stylesheet" href="css/admin_catalogo.css">

<link rel="stylesheet" href="css/pre_registro.css">

<link rel="stylesheet" href="css/hay_buttons.css">



<div class="prereg-wrap">

  <div id="respuesta-prereg-form" class="catalog-alert" style="display:none;"></div>

  <div id="prereg-saving-overlay" class="prereg-saving-overlay" style="display:none;" aria-live="polite">
    <div class="prereg-saving-overlay__box">
      <i class="fas fa-spinner fa-spin"></i> Guardando pre-registro…
    </div>
  </div>



  <form id="form-preregistro" class="prereg-form-page" action="php/preregistro_save.php" method="POST" enctype="multipart/form-data" data-no-global-ajax="1">

    <input type="hidden" name="id_preregistro" value="<?php echo (int)($r['id_preregistro'] ?? 0); ?>">
    <?php if ($idEntrevistaVinculo > 0): ?>
    <input type="hidden" name="id_entrevista" value="<?php echo $idEntrevistaVinculo; ?>">
    <div class="catalog-alert catalog-alert--ok" style="margin-bottom:12px;">
      <i class="fas fa-link"></i> Vinculado a entrevista previa: la comisión se asignará al asesor que registró la entrevista.
    </div>
    <?php endif; ?>

    <?php if ($puedeComision): ?>
    <section class="prereg-section" style="margin-bottom:16px; padding:14px; background:#f8fafc; border-radius:10px; border:1px solid #e3e8ef;">
      <h2 class="prereg-section-title" style="margin-top:0;"><i class="fas fa-user-tag"></i> Comisión de venta</h2>
      <p style="color:#666; font-size:0.88rem; margin:0 0 12px;">Por defecto la comisión va al asesor que captura. Puede asignarla a otro asesor, vincular una entrevista previa o registrar como CNCM.</p>
      <div class="prereg-grid prereg-grid--2">
        <div class="field">
          <label>Asesor que recibe comisión</label>
          <select name="id_usuario_asesor_comision" id="prereg-comision-asesor">
            <option value="">— Igual que quien captura —</option>
            <?php foreach ($asesoresComision as $a): ?>
              <option value="<?php echo (int) $a['id_usuario']; ?>"
                <?php
                $selId = (int) ($r['id_usuario_asesor'] ?? 0);
                if (!empty($r['comision_cncm']) && (int) $a['id_usuario'] === 0) {
                    echo ' selected';
                } elseif ($selId > 0 && $selId === (int) $a['id_usuario']) {
                    echo ' selected';
                }
                ?>>
                <?php echo htmlspecialchars($a['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Motivo de asignación (opcional)</label>
          <input type="text" name="motivo_comision" maxlength="200" placeholder="Ej. Comisión al asesor que dio información">
        </div>
      </div>
    </section>
    <?php endif; ?>

    <input type="hidden" name="id_plantel" value="<?php echo $idPlantel; ?>">

    <h1>Llena el formulario</h1>



    <section class="prereg-section">

      <h2 class="prereg-section-title">Información de alumno</h2>

      <div class="prereg-grid prereg-grid--3">

        <div class="span-full prereg-field">

          <label>Foto:</label>

          <div class="prereg-foto-zone" id="prereg-foto-zone">

            <input type="file" name="foto" id="prereg-foto-file" accept="image/jpeg,image/png,image/webp">

            <div class="prereg-foto-zone__icon"><i class="fas fa-cloud-upload-alt"></i></div>

            <div class="prereg-foto-zone__text">Arrastre o pulse para seleccionar imagen</div>

          </div>

          <div class="prereg-foto-tools">

            <button type="button" class="secondary" id="btn-prereg-camara">Activar cámara</button>

            <button type="button" class="primary" id="btn-prereg-capturar" style="display:none;">Capturar foto</button>

            <button type="button" class="secondary" id="btn-prereg-cam-off" style="display:none;">Apagar cámara</button>

          </div>

          <video id="prereg-video" autoplay playsinline style="display:none;"></video>

          <canvas id="prereg-canvas" style="display:none;"></canvas>

          <div class="prereg-foto-preview" id="prereg-foto-preview">

            <?php if (!empty($r['foto'])): ?>

              <img src="<?php echo htmlspecialchars($r['foto']); ?>" alt="Foto actual" id="prereg-foto-img">

            <?php endif; ?>

          </div>

        </div>

        <div class="prereg-field">

          <label>Nombres <span class="req">*</span></label>

          <input type="text" name="nombres" required maxlength="120" placeholder="ESCRIBE AQUI LOS NOMBRES" value="<?php echo htmlspecialchars($r['nombres'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Apellido Paterno <span class="req">*</span></label>

          <input type="text" name="apellido_paterno" required maxlength="80" placeholder="ESCRIBE AQUI EL APELLIDO PATERNO" value="<?php echo htmlspecialchars($r['apellido_paterno'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Apellido Materno <span class="req">*</span></label>

          <input type="text" name="apellido_materno" maxlength="80" placeholder="ESCRIBE AQUI EL APELLIDO MATERNO" value="<?php echo htmlspecialchars($r['apellido_materno'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Fecha nacimiento</label>

          <input type="date" name="fecha_nacimiento" id="prereg-fecha-nac" value="<?php echo htmlspecialchars($r['fecha_nacimiento'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Edad</label>

          <input type="number" name="edad" id="prereg-edad" min="0" max="120" readonly tabindex="-1" placeholder="Se calcula con la fecha" value="<?php echo htmlspecialchars((string)($r['edad'] ?? '')); ?>">

        </div>

      </div>

    </section>



    <section class="prereg-section">

      <h2 class="prereg-section-title">¿Cómo supiste de nosotros?</h2>

      <div class="prereg-field">

        <select name="medio_entero" id="prereg-medio">

          <option value="">SELECCIONA UNA OPCIÓN</option>

          <?php foreach ($labels['medio_entero'] as $k => $v): ?>

            <option value="<?php echo $k; ?>"<?php echo ($r['medio_entero'] ?? '') === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars(mb_strtoupper($v, 'UTF-8')); ?></option>

          <?php endforeach; ?>

        </select>

      </div>

      <div class="prereg-field" id="wrap-medio-otro" style="<?php echo ($r['medio_entero'] ?? '') === 'otro' ? '' : 'display:none;'; ?> margin-top:10px;">

        <label>Especifique (otro)</label>

        <input type="text" name="medio_entero_otro" maxlength="120" placeholder="ESPECIFIQUE" value="<?php echo htmlspecialchars($r['medio_entero_otro'] ?? ''); ?>">

      </div>

      <div class="prereg-field" id="wrap-escuela-origen" style="<?php echo ($r['medio_entero'] ?? '') === 'cartas' ? '' : 'display:none;'; ?> margin-top:10px;">

        <label>Escuela de origen (cartas) *</label>

        <select name="id_escuela_origen" id="prereg-escuela">

          <option value="">SELECCIONA LA ESCUELA</option>

          <?php foreach ($escuelasPrereg as $esc): ?>

            <option value="<?php echo (int) $esc['id_escuela']; ?>"<?php echo (int) ($r['id_escuela_origen'] ?? 0) === (int) $esc['id_escuela'] ? ' selected' : ''; ?>><?php echo htmlspecialchars(mb_strtoupper($esc['nombre'], 'UTF-8')); ?></option>

          <?php endforeach; ?>

        </select>

      </div>

    </section>



    <section class="prereg-section">

      <h2 class="prereg-section-title">Datos de contacto</h2>

      <div class="prereg-grid">

        <div class="span-full prereg-field">

          <label>Domicilio</label>

          <input type="text" name="domicilio" maxlength="200" placeholder="ESCRIBE AQUI EL DOMICILIO" value="<?php echo htmlspecialchars($r['domicilio'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Colonia</label>

          <input type="text" name="colonia" maxlength="120" placeholder="ESCRIBE AQUI LA COLONIA" value="<?php echo htmlspecialchars($r['colonia'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Municipio</label>

          <input type="text" name="municipio" maxlength="120" placeholder="ESCRIBE AQUI EL MUNICIPIO" value="<?php echo htmlspecialchars($r['municipio'] ?? ''); ?>">

        </div>

        <div class="prereg-grid prereg-grid--4 span-full" style="margin:0;">

          <div class="prereg-field">

            <label>Teléfono</label>

            <input type="tel" name="telefono" id="prereg-telefono" maxlength="30" placeholder="ESCRIBE EL TELEFONO" value="<?php echo htmlspecialchars($r['telefono'] ?? ''); ?>">

          </div>

          <div class="prereg-field">

            <label>Celular</label>

            <input type="tel" name="telefono2" maxlength="30" placeholder="ESCRIBE EL CELULAR" value="<?php echo htmlspecialchars($r['telefono2'] ?? ''); ?>">

          </div>

          <div class="prereg-field">

            <label>Correo electrónico</label>

            <input type="email" name="email" id="prereg-email" maxlength="160" placeholder="ESCRIBE EL CORREO" value="<?php echo htmlspecialchars($r['email'] ?? ''); ?>">

          </div>

          <div class="prereg-field">

            <label>C.P</label>

            <input type="text" name="codigo_postal" maxlength="10" placeholder="C.P." value="<?php echo htmlspecialchars($r['codigo_postal'] ?? ''); ?>">

          </div>

        </div>

      </div>

    </section>



    <section class="prereg-section">

      <h2 class="prereg-section-title">Información de escolaridad</h2>

      <div class="prereg-grid">

        <div class="prereg-field">

          <label>Ocupación</label>

          <input type="text" name="ocupacion" maxlength="120" placeholder="ESCRIBE LA OCUPACIÓN" value="<?php echo htmlspecialchars($r['ocupacion'] ?? ''); ?>">

        </div>

        <div class="span-full prereg-field">

          <label>Grado Máximo de estudios</label>

          <div class="prereg-radios">

            <?php foreach ($labels['grado_estudios'] as $k => $v): ?>

              <label>

                <input type="radio" name="grado_estudios" value="<?php echo $k; ?>"<?php echo $gradoActual === $k ? ' checked' : ''; ?>>

                <?php echo htmlspecialchars(mb_strtoupper($v, 'UTF-8')); ?>

              </label>

            <?php endforeach; ?>

          </div>

        </div>

        <div class="span-full prereg-field">

          <label>Padre o Tutor</label>

          <input type="text" name="padre_tutor" maxlength="160" placeholder="ESCRIBE EL TUTOR" value="<?php echo htmlspecialchars($r['padre_tutor'] ?? ''); ?>">

        </div>

        <div class="span-full prereg-field">

          <label>Especialidad</label>

          <select name="id_especialidad" id="prereg-especialidad">

            <option value="">SELECCIONA UNA ESPECIALIDAD</option>

            <?php foreach ($especialidades as $e):
              $edadTxt = catalog_edad_rango_texto(
                  isset($e['edad_min']) && $e['edad_min'] !== '' ? (int) $e['edad_min'] : null,
                  isset($e['edad_max']) && $e['edad_max'] !== '' ? (int) $e['edad_max'] : null
              );
              $colegTxt = catalog_colegiatura_resumen($e);
            ?>
              <option value="<?php echo (int)$e['id_especialidad']; ?>"
                data-abierta="<?php echo (int)$e['inscripcion_abierta']; ?>"
                data-edad-min="<?php echo htmlspecialchars((string)($e['edad_min'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-edad-max="<?php echo htmlspecialchars((string)($e['edad_max'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-edad-texto="<?php echo htmlspecialchars($edadTxt, ENT_QUOTES, 'UTF-8'); ?>"
                data-colegiatura="<?php echo htmlspecialchars($colegTxt, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo (int)($r['id_especialidad'] ?? 0) === (int)$e['id_especialidad'] ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($e['nombre']); ?>
                <?php if (!(int)$e['inscripcion_abierta']): ?> (inscripción próximamente)<?php endif; ?>
              </option>
            <?php endforeach; ?>

          </select>

          <p id="aviso-curso-cerrado" class="prereg-aviso-inline" style="display:none;">

            Este curso aún no abre inscripción. El sistema generará una alerta cuando abra.

          </p>

          <p id="aviso-especialidad-info" class="prereg-aviso-inline" style="display:none;"></p>

          <p id="aviso-edad-especialidad" class="prereg-aviso-inline prereg-aviso-inline--warn" style="display:none;"></p>
          <p class="prereg-aviso-inline" style="font-size:0.82rem; color:#666; margin-top:4px;">Si la edad no cumple el rango, puede guardar el pre-registro; al cobrar la inscripción recepción solicitará autorización de dirección o supervisor.</p>

        </div>

        <div class="span-full prereg-field">

          <label>Objetivo inscripción</label>

          <textarea name="objetivo_inscripcion" rows="3" placeholder="ESCRIBE LAS OBSERVACIONES"><?php echo htmlspecialchars($r['objetivo_inscripcion'] ?? ''); ?></textarea>

        </div>

        <div class="span-full prereg-field">

          <label>Enfermedad Crónica</label>

          <input type="text" name="enfermedad_detalle" id="prereg-enfermedad-detalle" maxlength="200" placeholder="ESCRIBE LA ENFERMEDAD CRONICA"

            value="<?php echo htmlspecialchars($r['enfermedad_detalle'] ?? ''); ?>">

          <input type="hidden" name="enfermedad_cronica" id="prereg-enfermedad-hidden" value="<?php echo !empty($r['enfermedad_cronica']) ? '1' : '0'; ?>">

        </div>

        <div class="span-full prereg-check-row">

          <input type="checkbox" name="requiere_factura" id="prereg-factura" value="1"<?php echo !empty($r['requiere_factura']) ? ' checked' : ''; ?>>

          <label for="prereg-factura" style="margin:0;">Solicitud Factura</label>

        </div>

      </div>

    </section>



    <p id="aviso-factura-prereg" class="prereg-aviso-inline" style="font-size:0.82rem; color:#666; margin:0 0 10px;<?php echo empty($r['requiere_factura']) ? ' display:none;' : ''; ?>">Puede guardar sin datos fiscales completos. Recepción recibirá aviso en su panel de inicio para dar seguimiento. También puede activar o quitar la solicitud de factura después de inscribir al alumno.</p>
    <section class="prereg-section prereg-factura-panel<?php echo !empty($r['requiere_factura']) ? ' is-visible' : ''; ?>" id="panel-factura">

      <h2 class="prereg-section-title">Información fiscal</h2>

      <div class="prereg-grid prereg-grid--2">

        <div class="prereg-field">

          <label>RFC</label>

          <input type="text" name="factura_rfc" maxlength="20" placeholder="ESCRIBE EL RFC" value="<?php echo htmlspecialchars($r['factura_rfc'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Curp</label>

          <input type="text" name="factura_curp" maxlength="22" placeholder="ESCRIBE EL CURP" value="<?php echo htmlspecialchars($r['factura_curp'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Teléfono</label>

          <input type="tel" name="factura_telefono" id="prereg-factura-tel" maxlength="30" placeholder="ESCRIBE EL TELEFONO GENERAL" value="<?php echo htmlspecialchars($r['factura_telefono'] ?? $r['telefono'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Razón Social</label>

          <input type="text" name="factura_razon_social" maxlength="200" placeholder="ESCRIBE LA RAZÓN SOCIAL" value="<?php echo htmlspecialchars($r['factura_razon_social'] ?? ''); ?>">

        </div>

        <div class="prereg-field">

          <label>Correo</label>

          <input type="email" name="factura_correo" id="prereg-factura-correo" maxlength="160" placeholder="ESCRIBE EL CORREO" value="<?php echo htmlspecialchars($r['factura_correo'] ?? $r['email'] ?? ''); ?>">

        </div>

        <div class="span-full prereg-field">

          <label>Domicilio Fiscal</label>

          <input type="text" name="factura_domicilio_fiscal" maxlength="255" placeholder="ESCRIBE EL DOMICILIO FISCAL" value="<?php echo htmlspecialchars($r['factura_domicilio_fiscal'] ?? ''); ?>">

        </div>

        <div class="span-full prereg-field">

          <label>Constancia de situación fiscal (PDF o imagen)</label>

          <input type="file" name="factura_constancia" accept="image/jpeg,image/png,image/webp,application/pdf">

          <?php if (!empty($r['factura_constancia_path'])): ?>

            <p style="margin:6px 0 0;"><a href="<?php echo htmlspecialchars($r['factura_constancia_path']); ?>" target="_blank">Ver archivo actual</a></p>

          <?php endif; ?>

        </div>

      </div>

    </section>



    <section class="prereg-section">

      <h2 class="prereg-section-title">Observaciones</h2>

      <div class="prereg-field">

        <textarea name="observaciones" rows="4" placeholder="ESCRIBE LAS OBSERVACIONES"><?php echo htmlspecialchars($r['observaciones'] ?? ''); ?></textarea>

      </div>

    </section>



    <div class="prereg-form-footer">

      <button type="button" class="prereg-volver" onclick="cargarSeccion('pre_registro_alumnos')">← Volver a la lista</button>

      <button type="submit" class="btn-prereg-guardar" id="btn-prereg-guardar"><i class="fas fa-plus"></i> Guardar</button>

    </div>

  </form>

</div>



<script>

(function () {

  const fecha = document.getElementById('prereg-fecha-nac');

  const edad = document.getElementById('prereg-edad');

  function calcEdad() {

    if (!fecha?.value) { if (edad) edad.value = ''; return; }

    const n = new Date(fecha.value);

    const h = new Date();

    let e = h.getFullYear() - n.getFullYear();

    const m = h.getMonth() - n.getMonth();

    if (m < 0 || (m === 0 && h.getDate() < n.getDate())) e--;

    if (edad) edad.value = e >= 0 ? e : '';

  }

  calcEdad();



  const medio = document.getElementById('prereg-medio');

  const wrapOtro = document.getElementById('wrap-medio-otro');

  const wrapEscuela = document.getElementById('wrap-escuela-origen');

  function syncMedioFields() {

    if (wrapOtro) wrapOtro.style.display = medio?.value === 'otro' ? '' : 'none';

    if (wrapEscuela) wrapEscuela.style.display = medio?.value === 'cartas' ? '' : 'none';

  }

  medio?.addEventListener('change', syncMedioFields);

  syncMedioFields();



  const esp = document.getElementById('prereg-especialidad');

  const avisoCerrado = document.getElementById('aviso-curso-cerrado');

  const avisoEspInfo = document.getElementById('aviso-especialidad-info');

  const avisoEdadEsp = document.getElementById('aviso-edad-especialidad');

  function edadActual() {

    const el = document.getElementById('prereg-edad');

    if (!el || !el.value) return null;

    const n = parseInt(el.value, 10);

    return Number.isFinite(n) ? n : null;

  }

  function checkCurso() {

    const opt = esp?.selectedOptions[0];

    const abierta = opt?.dataset.abierta === '1';

    if (avisoCerrado) avisoCerrado.style.display = opt && opt.value && !abierta ? 'block' : 'none';

    if (avisoEspInfo) {

      if (opt && opt.value) {

        const parts = [];

        if (opt.dataset.edadTexto) parts.push('Edad: ' + opt.dataset.edadTexto);

        if (opt.dataset.colegiatura && opt.dataset.colegiatura !== '—') parts.push('Colegiatura: ' + opt.dataset.colegiatura);

        avisoEspInfo.textContent = parts.join(' · ');

        avisoEspInfo.style.display = parts.length ? 'block' : 'none';

      } else {

        avisoEspInfo.style.display = 'none';

      }

    }

    const edad = edadActual();

    if (avisoEdadEsp && opt && opt.value) {

      const min = opt.dataset.edadMin !== '' ? parseInt(opt.dataset.edadMin, 10) : null;

      const max = opt.dataset.edadMax !== '' ? parseInt(opt.dataset.edadMax, 10) : null;

      let warn = '';

      if (edad == null && (min != null || max != null)) {

        warn = 'Indique fecha de nacimiento para validar edad (' + (opt.dataset.edadTexto || '') + ')';

      } else if (edad != null) {

        if (min != null && edad < min) warn = 'Edad ' + edad + ' años: mínimo ' + min + ' para este curso';

        else if (max != null && edad > max) warn = 'Edad ' + edad + ' años: máximo ' + max + ' para este curso';

      }

      avisoEdadEsp.textContent = warn;

      avisoEdadEsp.style.display = warn ? 'block' : 'none';

    } else if (avisoEdadEsp) {

      avisoEdadEsp.style.display = 'none';

    }

  }

  esp?.addEventListener('change', checkCurso);

  fecha?.addEventListener('change', () => { calcEdad(); checkCurso(); });

  checkCurso();



  const detEnf = document.getElementById('prereg-enfermedad-detalle');

  const hidEnf = document.getElementById('prereg-enfermedad-hidden');

  detEnf?.addEventListener('input', () => {

    if (hidEnf) hidEnf.value = detEnf.value.trim() ? '1' : '0';

  });






  const chkFactura = document.getElementById('prereg-factura');

  const panelFactura = document.getElementById('panel-factura');

  const avisoFactura = document.getElementById('aviso-factura-prereg');

  chkFactura?.addEventListener('change', () => {

    panelFactura?.classList.toggle('is-visible', chkFactura.checked);
    if (avisoFactura) avisoFactura.style.display = chkFactura.checked ? '' : 'none';

  });



  const tel = document.getElementById('prereg-telefono');

  const email = document.getElementById('prereg-email');

  const ftel = document.getElementById('prereg-factura-tel');

  const fcorreo = document.getElementById('prereg-factura-correo');

  tel?.addEventListener('blur', () => { if (ftel && !ftel.value) ftel.value = tel.value; });

  email?.addEventListener('blur', () => { if (fcorreo && !fcorreo.value) fcorreo.value = email.value; });



  const zone = document.getElementById('prereg-foto-zone');

  const fileInput = document.getElementById('prereg-foto-file');

  const preview = document.getElementById('prereg-foto-preview');

  function showPreviewFromFile(file) {

    if (!file || !file.type.startsWith('image/')) return;

    const reader = new FileReader();

    reader.onload = (ev) => {

      preview.innerHTML = '<img src="' + ev.target.result + '" alt="">';

    };

    reader.readAsDataURL(file);

  }

  function preregSetFileInput(file) {

    if (!fileInput || !file) return;

    const dt = new DataTransfer();

    dt.items.add(file);

    fileInput.files = dt.files;

  }

  function preregCompressImageFile(file, maxW, quality) {

    return new Promise((resolve) => {

      if (!file || !file.type.startsWith('image/')) {

        resolve(file);

        return;

      }

      const img = new Image();

      const url = URL.createObjectURL(file);

      img.onload = () => {

        URL.revokeObjectURL(url);

        let w = img.width;

        let h = img.height;

        if (w > maxW) {

          h = Math.round(h * (maxW / w));

          w = maxW;

        }

        const c = document.createElement('canvas');

        c.width = w;

        c.height = h;

        c.getContext('2d').drawImage(img, 0, 0, w, h);

        c.toBlob((blob) => {

          if (!blob) {

            resolve(file);

            return;

          }

          const name = (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';

          resolve(new File([blob], name, { type: 'image/jpeg' }));

        }, 'image/jpeg', quality);

      };

      img.onerror = () => {

        URL.revokeObjectURL(url);

        resolve(file);

      };

      img.src = url;

    });

  }

  async function preregPhotoForUpload(file) {

    if (!file) return null;

    let out = file;

    if (file.size > 400000 || !/^image\/jpe?g$/i.test(file.type)) {

      out = await preregCompressImageFile(file, 720, 0.8);

    }

    return out;

  }



  fileInput?.addEventListener('change', () => {

    if (fileInput.files[0]) showPreviewFromFile(fileInput.files[0]);

  });



  zone?.addEventListener('dragover', (e) => {

    e.preventDefault();

    zone.classList.add('is-dragover');

  });

  zone?.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));

  zone?.addEventListener('drop', (e) => {

    e.preventDefault();

    zone.classList.remove('is-dragover');

    const f = e.dataTransfer?.files?.[0];

    if (f && fileInput) {

      const dt = new DataTransfer();

      dt.items.add(f);

      fileInput.files = dt.files;

      showPreviewFromFile(f);

    }

  });



  let camStream = null;

  const video = document.getElementById('prereg-video');

  const canvas = document.getElementById('prereg-canvas');

  const btnCam = document.getElementById('btn-prereg-camara');

  const btnCap = document.getElementById('btn-prereg-capturar');

  const btnOff = document.getElementById('btn-prereg-cam-off');



  async function startCamera() {

    try {

      camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });

      video.srcObject = camStream;

      video.style.display = 'block';

      btnCap.style.display = '';

      btnOff.style.display = '';

      btnCam.style.display = 'none';

    } catch (err) {

      alert('No se pudo acceder a la cámara. Usa arrastrar imagen o permite el permiso en el navegador.');

    }

  }

  function stopCamera() {

    if (camStream) camStream.getTracks().forEach(t => t.stop());

    camStream = null;

    video.style.display = 'none';

    btnCap.style.display = 'none';

    btnOff.style.display = 'none';

    btnCam.style.display = '';

  }

  btnCam?.addEventListener('click', (e) => { e.preventDefault(); startCamera(); });

  btnOff?.addEventListener('click', (e) => { e.preventDefault(); stopCamera(); });

  btnCap?.addEventListener('click', (e) => {

    e.preventDefault();

    if (!video.videoWidth) return;

    const maxW = 800;

    let w = video.videoWidth;

    let h = video.videoHeight;

    if (w > maxW) {

      h = Math.round(h * (maxW / w));

      w = maxW;

    }

    canvas.width = w;

    canvas.height = h;

    canvas.getContext('2d').drawImage(video, 0, 0, w, h);

    canvas.toBlob((blob) => {

      if (!blob) return;

      const file = new File([blob], 'foto_cam.jpg', { type: 'image/jpeg' });

      preregSetFileInput(file);

      preview.innerHTML = '<img src="' + URL.createObjectURL(blob) + '" alt="">';

      stopCamera();

    }, 'image/jpeg', 0.82);

  });



  const form = document.getElementById('form-preregistro');

  const msg = document.getElementById('respuesta-prereg-form');

  const btnGuardar = document.getElementById('btn-prereg-guardar');
  const overlay = document.getElementById('prereg-saving-overlay');
  let guardando = false;

  form?.addEventListener('submit', async (e) => {

    e.preventDefault();

    if (guardando) return;

    guardando = true;

    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.dataset.labelOriginal = btnGuardar.innerHTML;
      btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
    }

    if (overlay) overlay.style.display = 'flex';

    try {

    const fd = new FormData(form);

    if (hidEnf && hidEnf.value !== '1') fd.delete('enfermedad_cronica');

    else fd.set('enfermedad_cronica', '1');

    if (!chkFactura?.checked) fd.delete('requiere_factura');

    fd.delete('foto');

    fd.delete('foto_base64');

    let fotoSesion = false;

    const fotoFile = fileInput?.files?.[0];

    if (fotoFile) {

      const photoFd = new FormData();

      photoFd.append('foto', await preregPhotoForUpload(fotoFile));

      const { data: upFoto } = await hayFetchJson('php/preregistro_foto_upload.php', { method: 'POST', body: photoFd });

      if (upFoto.status !== 'ok') {

        throw new Error(upFoto.message || 'No se pudo subir la foto');

      }

      fotoSesion = true;

    }

    if (fotoSesion) fd.set('foto_sesion', '1');

      const { data } = await hayFetchJson(form.action, { method: 'POST', body: fd });

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');

        msg.textContent = data.message || '';

      }

      if (data.status === 'ok') {
        const hidId = form.querySelector('input[name="id_preregistro"]');
        if (hidId && data.id_preregistro) hidId.value = String(data.id_preregistro);
        if (data.seccion) {
          if (data.params) {
            cargarSeccion(data.seccion, data.params);
          } else {
            const p = new URLSearchParams();
            p.set('ok', '1');
            cargarSeccion(data.seccion, p);
          }
        }
      }

    } catch (err) {

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'catalog-alert catalog-alert--error';

        msg.textContent = err.message || 'Error al guardar';

      }

    } finally {

      guardando = false;

      if (overlay) overlay.style.display = 'none';

      if (btnGuardar) {
        btnGuardar.disabled = false;
        if (btnGuardar.dataset.labelOriginal) btnGuardar.innerHTML = btnGuardar.dataset.labelOriginal;
      }

    }

  });

})();

</script>

