<?php
require_once __DIR__ . '/../config.php';
if (!fase_puede_editar()) {
    echo '<div class="alert">Solo coordinación, dirección o administradores pueden editar fases.</div>';
    return;
}

if (function_exists('fase_ensure_moodle_columns')) {
    fase_ensure_moodle_columns($pdo);
}

$idEsp = (int) ($_GET['id_especialidad'] ?? 0);
$idPlan = (int) ($_GET['id_plan_version'] ?? 0);
$especialidades = $pdo->query(
    'SELECT id_especialidad, clave, nombre, modalidad, duracion_fase_semanas
     FROM especialidades WHERE activo = 1 ORDER BY orden, nombre'
)->fetchAll(PDO::FETCH_ASSOC);

if ($idEsp <= 0 && !empty($especialidades)) {
    $idEsp = (int) $especialidades[0]['id_especialidad'];
}

$espActual = null;
foreach ($especialidades as $e) {
    if ((int) $e['id_especialidad'] === $idEsp) {
        $espActual = $e;
        break;
    }
}

$versiones = $idEsp ? plan_version_listar($pdo, $idEsp) : [];
$planActivo = $idEsp ? plan_version_activo_nuevos($pdo, $idEsp) : null;
if ($idPlan <= 0 && $planActivo) {
    $idPlan = (int) $planActivo['id_plan_version'];
}
$fases = $idEsp ? fase_listar($pdo, $idEsp, $idPlan > 0 ? $idPlan : null) : [];
$semanasPorFase = $idEsp ? fase_temario_semanas_por_especialidad($pdo, $idEsp) : [];
$durHint = $espActual ? fase_duracion_default_especialidad($espActual) : 4;
$esIngles = $espActual ? fase_es_especialidad_ingles($espActual) : false;
$evalLabels = [
    'eval_listening' => 'Listening',
    'eval_reading' => 'Reading',
    'eval_writing' => 'Writing',
    'eval_speaking' => 'Speaking',
    'eval_grammar' => 'Grammar',
    'eval_vocabulary' => 'Vocabulary',
];
$evalIcons = [
    'eval_listening' => ['fa-headphones', 'listening'],
    'eval_reading' => ['fa-book-open', 'reading'],
    'eval_writing' => ['fa-pen-fancy', 'writing'],
    'eval_speaking' => ['fa-comments', 'speaking'],
    'eval_grammar' => ['fa-spell-check', 'grammar'],
    'eval_vocabulary' => ['fa-language', 'vocabulary'],
];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">
<link rel="stylesheet" href="css/esp_fases.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-layer-group"></i> Fases por especialidad</h2>
    <button type="button" class="primary" id="btn-nueva-fase" title="Nueva fase"><i class="fas fa-plus"></i> Nueva fase</button>
  </div>

  <div class="catalog-toolbar">
    <div class="field" style="min-width:260px;">
      <label>Especialidad</label>
      <select id="sel-esp-fases" onchange="cargarSeccion('esp_fases', 'id_especialidad=' + this.value)">
        <?php foreach ($especialidades as $e): ?>
          <option value="<?php echo (int)$e['id_especialidad']; ?>"<?php echo (int)$e['id_especialidad'] === $idEsp ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($e['clave'] . ' — ' . $e['nombre']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($idEsp > 0 && !empty($versiones)): ?>
    <div class="field" style="min-width:220px;">
      <label>Versión del plan</label>
      <select id="sel-plan-version" onchange="cargarSeccion('esp_fases', 'id_especialidad=<?php echo $idEsp; ?>&id_plan_version=' + this.value)">
        <?php foreach ($versiones as $pv): ?>
          <option value="<?php echo (int)$pv['id_plan_version']; ?>"<?php echo (int)$pv['id_plan_version'] === $idPlan ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($pv['version_label']); ?>
            <?php echo !empty($pv['activo_para_nuevos']) ? ' (nuevos)' : ''; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="align-self:flex-end;">
      <button type="button" class="secondary" id="btn-publicar-plan">Publicar nueva versión</button>
    </div>
    <?php endif; ?>
  </div>
  <input type="hidden" id="fase-id-esp" value="<?php echo (int)$idEsp; ?>">
  <input type="hidden" id="fase-id-plan" value="<?php echo (int)$idPlan; ?>">

  <?php if ($espActual): ?>
    <div class="fase-esp-meta">
      <span><i class="fas fa-graduation-cap"></i> <strong><?php echo htmlspecialchars($espActual['clave']); ?></strong></span>
      <span><?php echo htmlspecialchars($espActual['modalidad']); ?></span>
      <span><i class="far fa-calendar"></i> <?php echo (int)$durHint; ?> semanas por parcial</span>
    </div>
  <?php endif; ?>

  <div id="resp-fases" class="catalog-alert" style="display:none;"></div>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="tabla-fases">
      <thead>
        <tr>
          <th style="width:36px;"></th>
          <th>#</th>
          <th>Código</th>
          <th>Fase (alumno)</th>
          <th>Sem.</th>
          <th>Temario</th>
          <th>Moodle</th>
          <th style="width:90px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fases as $f): ?>
          <?php
            $idF = (int) $f['id_fase'];
            $semanas = $semanasPorFase[$idF] ?? [];
            $resumen = fase_resumen_temario_fila($f, $semanas);
            $clave = $f['clave_fase'] ?? '';
          ?>
          <tr class="fase-row" data-id="<?php echo $idF; ?>">
            <td>
              <button type="button" class="btn-icon-only btn-icon-only--muted btn-toggle-fase" title="Ver temario" aria-expanded="false">
                <i class="fas fa-chevron-right"></i>
              </button>
            </td>
            <td><?php echo (int)$f['orden']; ?></td>
            <td><code><?php echo htmlspecialchars($clave); ?></code></td>
            <td><strong><?php echo htmlspecialchars($f['nombre_fase']); ?></strong></td>
            <td><?php echo (int)($f['duracion_semanas'] ?? $durHint); ?></td>
            <td style="max-width:280px; font-size:0.85rem; color:#555;"><?php echo htmlspecialchars($resumen); ?></td>
            <td style="font-size:0.85rem;">
              <?php if (!empty($f['moodle_course_id'])): ?>
                <span title="<?php echo htmlspecialchars($f['moodle_shortname'] ?? ''); ?>">#<?php echo (int) $f['moodle_course_id']; ?></span>
              <?php else: ?>
                <span style="color:#aaa;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="fase-acciones">
                <button type="button" class="btn-icon-only btn-icon-only--edit btn-edit-fase" title="Editar" data-id="<?php echo $idF; ?>">
                  <i class="fas fa-pen"></i>
                </button>
                <button type="button" class="btn-icon-only btn-icon-only--danger btn-del-fase" title="Desactivar" data-id="<?php echo $idF; ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          <tr class="fase-row-expand" data-for="<?php echo $idF; ?>" hidden>
            <td colspan="8">
              <div class="fase-temario-detail">
                <div class="fase-detail-header">
                  <h3><?php echo htmlspecialchars($f['nombre_fase']); ?></h3>
                  <?php if ($clave): ?>
                    <span class="fase-detail-badge"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($clave); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($f['nivel_cefr'])): ?>
                    <span class="fase-detail-badge"><?php echo htmlspecialchars($f['nivel_cefr']); ?></span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($f['objetivo_parcial'])): ?>
                  <section class="fase-detail-section">
                    <h4 class="fase-detail-section__title"><i class="fas fa-bullseye"></i> Objetivo del parcial</h4>
                    <div class="fase-objetivo-box">
                      <p><?php echo nl2br(htmlspecialchars($f['objetivo_parcial'])); ?></p>
                    </div>
                  </section>
                <?php endif; ?>

                <section class="fase-detail-section">
                  <h4 class="fase-detail-section__title"><i class="fas fa-clipboard-check"></i> Criterios de evaluación</h4>
                  <?php if ($esIngles): ?>
                  <div class="fase-eval-grid">
                    <?php foreach ($evalLabels as $key => $label):
                      $ico = $evalIcons[$key] ?? ['fa-circle', 'listening'];
                      $val = trim($f[$key] ?? '');
                    ?>
                      <div class="fase-eval-card">
                        <div class="fase-eval-card__head">
                          <span class="fase-eval-card__icon fase-eval-card__icon--<?php echo $ico[1]; ?>">
                            <i class="fas <?php echo $ico[0]; ?>"></i>
                          </span>
                          <span class="fase-eval-card__label"><?php echo $label; ?></span>
                        </div>
                        <div class="fase-eval-card__body<?php echo $val === '' ? ' fase-eval-card__body--empty' : ''; ?>">
                          <?php echo $val !== '' ? nl2br(htmlspecialchars($val)) : 'Sin definir'; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php else:
                    $criteriosGen = fase_eval_criterios_genericos($f);
                  ?>
                  <?php if ($criteriosGen === []): ?>
                    <p style="color:#888;">Sin criterios definidos.</p>
                  <?php else: ?>
                    <div class="fase-eval-grid">
                      <?php foreach ($criteriosGen as $cg): ?>
                        <div class="fase-eval-card">
                          <div class="fase-eval-card__head">
                            <span class="fase-eval-card__label"><?php echo htmlspecialchars($cg['nombre'] ?: 'Criterio'); ?></span>
                          </div>
                          <div class="fase-eval-card__body<?php echo ($cg['descripcion'] ?? '') === '' ? ' fase-eval-card__body--empty' : ''; ?>">
                            <?php echo ($cg['descripcion'] ?? '') !== '' ? nl2br(htmlspecialchars($cg['descripcion'])) : 'Sin descripción'; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php endif; ?>
                </section>

                <?php if ($esIngles && (!empty($f['vocabulario_resumen']) || !empty($f['gramatica_resumen']))): ?>
                  <section class="fase-detail-section">
                    <div class="fase-resumen-chips">
                      <?php if (!empty($f['vocabulario_resumen'])): ?>
                        <div class="fase-resumen-chip">
                          <strong>Vocabulario</strong>
                          <?php echo nl2br(htmlspecialchars($f['vocabulario_resumen'])); ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($f['gramatica_resumen'])): ?>
                        <div class="fase-resumen-chip">
                          <strong>Gramática</strong>
                          <?php echo nl2br(htmlspecialchars($f['gramatica_resumen'])); ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </section>
                <?php endif; ?>

                <section class="fase-detail-section">
                  <h4 class="fase-detail-section__title"><i class="fas fa-calendar-week"></i> Semanas del parcial</h4>
                  <?php if ($semanas !== []): ?>
                    <div class="fase-semanas-timeline">
                      <?php foreach ($semanas as $s):
                        $esExamen = !empty($s['es_examen']);
                        $numSem = (int) $s['semana'];
                      ?>
                        <article class="fase-semana-read">
                          <div class="fase-semana-read__top">
                            <div class="fase-semana-read__num<?php echo $esExamen ? ' is-exam' : ''; ?>"><?php echo $numSem; ?></div>
                            <div class="fase-semana-read__title">
                              <h5><?php echo htmlspecialchars($s['titulo_leccion'] ?: 'Semana ' . $numSem); ?></h5>
                              <?php if ($esExamen): ?>
                                <span><i class="fas fa-file-alt"></i> Semana de examen</span>
                              <?php elseif (!empty($s['proyecto_tipo'])): ?>
                                <span><?php echo htmlspecialchars($s['proyecto_tipo']); ?></span>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="fase-semana-read__body">
                            <?php if (!empty($s['objetivo'])): ?>
                              <div class="fase-semana-read__field">
                                <label>Objetivo</label>
                                <p><?php echo nl2br(htmlspecialchars($s['objetivo'])); ?></p>
                              </div>
                            <?php endif; ?>
                            <?php if ($esIngles && !empty($s['vocabulario'])): ?>
                              <div class="fase-semana-read__field">
                                <label>Vocabulario</label>
                                <p><?php echo nl2br(htmlspecialchars($s['vocabulario'])); ?></p>
                              </div>
                            <?php endif; ?>
                            <?php if ($esIngles && !empty($s['gramatica'])): ?>
                              <div class="fase-semana-read__field">
                                <label>Gramática</label>
                                <p><?php echo nl2br(htmlspecialchars($s['gramatica'])); ?></p>
                              </div>
                            <?php endif; ?>
                            <?php if (!$esIngles && !empty($s['vocabulario'])): ?>
                              <div class="fase-semana-read__field">
                                <label>Contenido de la clase</label>
                                <p><?php echo nl2br(htmlspecialchars($s['vocabulario'])); ?></p>
                              </div>
                            <?php endif; ?>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="fase-empty-hint">
                      <i class="fas fa-inbox"></i> No hay semanas registradas. Importe el temario con <code>ingles_temario_seed.sql</code>.
                    </div>
                  <?php endif; ?>
                </section>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (empty($fases)): ?>
      <p>No hay fases para esta especialidad.</p>
    <?php endif; ?>
  </div>
</div>

<div id="modal-fase" class="catalog-modal">
  <div class="catalog-modal__panel fase-modal">
    <header class="fase-modal__head">
      <div>
        <h3 id="modal-fase-titulo">Fase</h3>
        <p id="modal-fase-sub">Datos del parcial y temario semanal</p>
      </div>
      <button type="button" class="fase-modal__close" id="fase-cancel-x" title="Cerrar" aria-label="Cerrar">
        <i class="fas fa-times"></i>
      </button>
    </header>

    <nav class="fase-modal__tabs" role="tablist">
      <button type="button" class="fase-modal__tab is-active" data-pane="general">General</button>
      <button type="button" class="fase-modal__tab" data-pane="evaluacion">Evaluación</button>
      <button type="button" class="fase-modal__tab" data-pane="moodle">Moodle</button>
      <button type="button" class="fase-modal__tab" data-pane="semanas">Semanas</button>
    </nav>

    <div class="fase-modal__body">
      <input type="hidden" id="fase-id">

      <div class="fase-modal__pane is-active" data-pane="general">
        <div class="catalog-form-grid">
          <div>
            <label>Código admin</label>
            <input type="text" id="fase-codigo" maxlength="40" placeholder="A1-1">
          </div>
          <div>
            <label>Nombre (alumno)</label>
            <input type="text" id="fase-nombre" required placeholder="A1 - Parcial 1">
          </div>
          <div>
            <label>Orden</label>
            <input type="number" id="fase-orden" min="1">
          </div>
          <div>
            <label>Duración (sem)</label>
            <input type="number" id="fase-semanas" min="1" max="12" value="<?php echo (int)$durHint; ?>">
          </div>
          <div class="full">
            <label>Objetivo del parcial</label>
            <textarea id="fase-objetivo-parcial" rows="4" placeholder="Objetivos generales de las 4 semanas…"></textarea>
          </div>
        </div>
      </div>

      <div class="fase-modal__pane" data-pane="evaluacion">
        <div id="fase-eval-ingles" class="catalog-form-grid"<?php echo $esIngles ? '' : ' style="display:none;"'; ?>>
          <?php foreach ($evalLabels as $key => $label): ?>
            <div class="full">
              <label><?php echo $label; ?></label>
              <textarea id="fase-<?php echo str_replace('eval_', '', $key); ?>" rows="3" placeholder="Criterios de <?php echo strtolower($label); ?>…"></textarea>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="fase-eval-generico"<?php echo $esIngles ? ' style="display:none;"' : ''; ?>>
          <p style="font-size:0.88rem; color:#555; margin:0 0 12px;">
            Defina los <strong>criterios de evaluación</strong> del parcial (examen, proyecto, práctica, etc.).
          </p>
          <div id="fase-criterios-list" class="fase-criterios-list"></div>
          <button type="button" class="secondary" id="fase-add-criterio" style="margin-top:8px;">
            <i class="fas fa-plus"></i> Agregar criterio
          </button>
        </div>
      </div>

      <div class="fase-modal__pane" data-pane="moodle">
        <p style="font-size:0.88rem; color:#555; margin:0 0 12px;">
          Defina el curso Moodle en la <strong>primera fase de cada bloque</strong> (ej. fases 1, 5, 9…).
          Las fases siguientes del bloque heredan el mismo curso hasta el siguiente bloque.
        </p>
        <div class="catalog-form-grid">
          <div>
            <label>ID curso Moodle</label>
            <input type="number" id="fase-moodle-course-id" min="1" placeholder="Ej. 42">
          </div>
          <div>
            <label>Shortname Moodle</label>
            <input type="text" id="fase-moodle-shortname" maxlength="80" placeholder="Ej. ING-A1-BLOQUE1">
          </div>
        </div>
      </div>

      <div class="fase-modal__pane" data-pane="semanas">
        <div class="fase-week-tabs" id="fase-week-tabs" role="tablist"></div>
        <div id="fase-semanas-editor"></div>
      </div>
    </div>

    <footer class="fase-modal__foot">
      <button type="button" class="secondary" id="fase-cancel">Cancelar</button>
      <button type="button" class="primary" id="fase-save"><i class="fas fa-save"></i> Guardar</button>
    </footer>
  </div>
</div>

<script>
(function() {
  const modal = document.getElementById('modal-fase');
  const esIngles = <?php echo $esIngles ? 'true' : 'false'; ?>;
  const evalFields = ['listening','reading','writing','speaking','grammar','vocabulary'];
  const semanasEditor = document.getElementById('fase-semanas-editor');
  const weekTabsEl = document.getElementById('fase-week-tabs');
  const criteriosList = document.getElementById('fase-criterios-list');
  let activeWeek = 1;

  function showMsg(t, ok) {
    const el = document.getElementById('resp-fases');
    el.style.display = 'block';
    el.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    el.textContent = t;
  }

  function esc(v) {
    if (v == null) return '';
    return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
  }

  function setModalPane(name) {
    modal.querySelectorAll('.fase-modal__tab').forEach(t => {
      t.classList.toggle('is-active', t.dataset.pane === name);
    });
    modal.querySelectorAll('.fase-modal__pane').forEach(p => {
      p.classList.toggle('is-active', p.dataset.pane === name);
    });
  }

  function setWeekPane(n) {
    activeWeek = n;
    weekTabsEl.querySelectorAll('.fase-week-tab').forEach(t => {
      t.classList.toggle('is-active', parseInt(t.dataset.week, 10) === n);
    });
    semanasEditor.querySelectorAll('.fase-week-panel').forEach(p => {
      p.classList.toggle('is-active', parseInt(p.dataset.semana, 10) === n);
    });
  }

  function buildCriterioRow(item) {
    const nombre = esc(item?.nombre || '');
    const desc = esc(item?.descripcion || '');
    return `<div class="fase-criterio-row" style="border:1px solid #e0e0e0;border-radius:8px;padding:10px;margin-bottom:8px;background:#fafafa;">
      <div class="fase-field" style="margin-bottom:8px;">
        <label>Criterio de evaluación</label>
        <input type="text" class="crit-nombre" value="${nombre}" placeholder="Ej. Examen práctico, Proyecto, Participación…">
      </div>
      <div class="fase-field">
        <label>Descripción del criterio</label>
        <textarea class="crit-desc" rows="3" placeholder="Qué se evalúa y cómo…">${desc}</textarea>
      </div>
      <button type="button" class="secondary crit-remove" style="margin-top:6px;font-size:0.82rem;">Quitar</button>
    </div>`;
  }

  function renderCriterios(items) {
    if (!criteriosList) return;
    const list = (items && items.length) ? items : [{ nombre: '', descripcion: '' }];
    criteriosList.innerHTML = list.map(buildCriterioRow).join('');
    criteriosList.querySelectorAll('.crit-remove').forEach(btn => {
      btn.addEventListener('click', () => {
        const row = btn.closest('.fase-criterio-row');
        if (criteriosList.querySelectorAll('.fase-criterio-row').length <= 1) {
          row.querySelector('.crit-nombre').value = '';
          row.querySelector('.crit-desc').value = '';
          return;
        }
        row.remove();
      });
    });
  }

  function collectCriterios() {
    if (!criteriosList) return [];
    return Array.from(criteriosList.querySelectorAll('.fase-criterio-row')).map(row => ({
      nombre: row.querySelector('.crit-nombre')?.value?.trim() || '',
      descripcion: row.querySelector('.crit-desc')?.value?.trim() || '',
    })).filter(c => c.nombre || c.descripcion);
  }

  function buildSemanasEditor(semanas) {
    const bySem = {};
    (semanas || []).forEach(s => { bySem[s.semana] = s; });

    let tabsHtml = '';
    let panelsHtml = '';
    for (let w = 1; w <= 4; w++) {
      const s = bySem[w] || {};
      const label = s.titulo_leccion ? esc(s.titulo_leccion).slice(0, 12) : 'Semana';
      tabsHtml += `<button type="button" class="fase-week-tab${w === activeWeek ? ' is-active' : ''}" data-week="${w}" role="tab">
        <span class="fase-week-tab__n">${w}</span>
        <span class="fase-week-tab__l">${label}</span>
      </button>`;
      panelsHtml += `<div class="fase-week-panel${w === activeWeek ? ' is-active' : ''}" data-semana="${w}">
        <div class="fase-field">
          <label>${esIngles ? 'Lección' : 'Tema / lección'}</label>
          <input type="text" class="sem-titulo" value="${esc(s.titulo_leccion)}" placeholder="${esIngles ? 'Lesson ' + w : 'Tema semana ' + w}">
        </div>
        <div class="fase-field">
          <label>Objetivo de la semana</label>
          <textarea class="sem-objetivo" rows="4" placeholder="${esIngles ? 'At the end of the lesson students will be able to…' : 'Al finalizar la semana el alumno podrá…'}">${esc(s.objetivo)}</textarea>
        </div>
        ${esIngles ? `
        <div class="fase-field">
          <label>Vocabulario</label>
          <textarea class="sem-vocab" rows="2" placeholder="Temas de vocabulario…">${esc(s.vocabulario)}</textarea>
        </div>
        <div class="fase-field">
          <label>Gramática</label>
          <textarea class="sem-gram" rows="2" placeholder="Estructuras gramaticales…">${esc(s.gramatica)}</textarea>
        </div>` : `
        <div class="fase-field">
          <label>Contenido de la clase</label>
          <textarea class="sem-vocab" rows="3" placeholder="Temas, herramientas o actividades que se verán en clase…">${esc(s.vocabulario)}</textarea>
        </div>`}
        <div class="fase-field">
          <label>Proyecto / actividad (tipo)</label>
          <input type="text" class="sem-proyecto" value="${esc(s.proyecto_tipo)}" placeholder="Proyecto, examen, práctica, etc.">
        </div>
      </div>`;
    }
    weekTabsEl.innerHTML = tabsHtml;
    semanasEditor.innerHTML = panelsHtml;

    weekTabsEl.querySelectorAll('.fase-week-tab').forEach(btn => {
      btn.addEventListener('click', () => setWeekPane(parseInt(btn.dataset.week, 10)));
    });
  }

  function collectSemanas() {
    const out = [];
    semanasEditor.querySelectorAll('.fase-week-panel').forEach(card => {
      out.push({
        semana: parseInt(card.dataset.semana, 10),
        titulo_leccion: card.querySelector('.sem-titulo')?.value || '',
        objetivo: card.querySelector('.sem-objetivo')?.value || '',
        vocabulario: card.querySelector('.sem-vocab')?.value || '',
        gramatica: esIngles ? (card.querySelector('.sem-gram')?.value || '') : '',
        proyecto_tipo: card.querySelector('.sem-proyecto')?.value || '',
      });
    });
    return out;
  }

  function closeModal() {
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  async function openModal(idFase) {
    activeWeek = 1;
    setModalPane('general');
    buildSemanasEditor([]);
    if (!esIngles) renderCriterios([]);

    if (!idFase) {
      document.getElementById('modal-fase-titulo').textContent = 'Nueva fase';
      document.getElementById('modal-fase-sub').textContent = 'Complete los datos del parcial';
      document.getElementById('fase-id').value = '';
      document.getElementById('fase-codigo').value = '';
      document.getElementById('fase-nombre').value = '';
      document.getElementById('fase-orden').value = document.querySelectorAll('.fase-row').length + 1;
      document.getElementById('fase-objetivo-parcial').value = '';
      document.getElementById('fase-moodle-course-id').value = '';
      document.getElementById('fase-moodle-shortname').value = '';
      if (esIngles) {
        evalFields.forEach(k => { const el = document.getElementById('fase-' + k); if (el) el.value = ''; });
      } else {
        renderCriterios([]);
      }
      modal.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      return;
    }

    const r = await fetch('php/fase_api.php?action=get&id_fase=' + encodeURIComponent(idFase));
    const data = await r.json();
    if (data.status !== 'ok' || !data.fase) {
      showMsg(data.message || 'No se pudo cargar la fase', false);
      return;
    }
    const f = data.fase;
    document.getElementById('modal-fase-titulo').textContent = f.clave_fase || f.nombre_fase;
    document.getElementById('modal-fase-sub').textContent = f.nombre_fase || 'Editar temario del parcial';
    document.getElementById('fase-id').value = f.id_fase;
    document.getElementById('fase-codigo').value = f.clave_fase || '';
    document.getElementById('fase-nombre').value = f.nombre_fase || '';
    document.getElementById('fase-orden').value = f.orden || 1;
    document.getElementById('fase-semanas').value = f.duracion_semanas || <?php echo (int)$durHint; ?>;
    document.getElementById('fase-objetivo-parcial').value = f.objetivo_parcial || '';
    if (esIngles) {
      document.getElementById('fase-listening').value = f.eval_listening || '';
      document.getElementById('fase-reading').value = f.eval_reading || '';
      document.getElementById('fase-writing').value = f.eval_writing || '';
      document.getElementById('fase-speaking').value = f.eval_speaking || '';
      document.getElementById('fase-grammar').value = f.eval_grammar || '';
      document.getElementById('fase-vocabulary').value = f.eval_vocabulary || '';
    } else {
      let criterios = [];
      try {
        criterios = f.eval_criterios_json ? JSON.parse(f.eval_criterios_json) : [];
      } catch (e) { criterios = []; }
      renderCriterios(Array.isArray(criterios) ? criterios : []);
    }
    document.getElementById('fase-moodle-course-id').value = f.moodle_course_id || '';
    document.getElementById('fase-moodle-shortname').value = f.moodle_shortname || '';
    buildSemanasEditor(f.semanas || []);
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  modal.querySelectorAll('.fase-modal__tab').forEach(tab => {
    tab.addEventListener('click', () => setModalPane(tab.dataset.pane));
  });

  document.getElementById('fase-add-criterio')?.addEventListener('click', () => {
    if (!criteriosList) return;
    const div = document.createElement('div');
    div.innerHTML = buildCriterioRow({ nombre: '', descripcion: '' });
    const row = div.firstElementChild;
    criteriosList.appendChild(row);
    row.querySelector('.crit-remove')?.addEventListener('click', () => {
      if (criteriosList.querySelectorAll('.fase-criterio-row').length <= 1) {
        row.querySelector('.crit-nombre').value = '';
        row.querySelector('.crit-desc').value = '';
        return;
      }
      row.remove();
    });
  });

  document.getElementById('btn-nueva-fase').onclick = () => openModal(0);
  document.getElementById('fase-cancel').onclick = closeModal;
  document.getElementById('fase-cancel-x').onclick = closeModal;

  document.querySelectorAll('.btn-toggle-fase').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      const id = tr.dataset.id;
      const detail = document.querySelector('.fase-row-expand[data-for="' + id + '"]');
      const open = detail.hidden;
      detail.hidden = !open;
      tr.classList.toggle('is-open', open);
      btn.querySelector('i').className = open ? 'fas fa-chevron-down' : 'fas fa-chevron-right';
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  document.querySelectorAll('.btn-edit-fase').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.id));
  });

  document.querySelectorAll('.btn-del-fase').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('¿Desactivar esta fase?')) return;
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('id_fase', btn.dataset.id);
      const r = await fetch('php/fase_api.php', { method: 'POST', body: fd });
      const data = await r.json();
      showMsg(data.message, data.status === 'ok');
      if (data.status === 'ok') {
        cargarSeccion('esp_fases', 'id_especialidad=' + document.getElementById('fase-id-esp').value);
      }
    });
  });

  document.getElementById('btn-publicar-plan')?.addEventListener('click', async () => {
    const label = prompt('Etiqueta de la nueva versión (ej. v2026):', 'v' + new Date().getFullYear());
    if (!label) return;
    const fd = new FormData();
    fd.append('action', 'publicar');
    fd.append('id_especialidad', document.getElementById('fase-id-esp').value);
    fd.append('version_label', label);
    const r = await fetch('php/plan_version_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    showMsg(data.message || (data.status === 'ok' ? 'Versión publicada' : 'Error'), data.status === 'ok');
    if (data.status === 'ok') {
      const idPlan = data.id_plan_version || '';
      cargarSeccion('esp_fases', 'id_especialidad=' + document.getElementById('fase-id-esp').value + (idPlan ? '&id_plan_version=' + idPlan : ''));
    }
  });

  document.getElementById('fase-save').onclick = async () => {
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id_fase', document.getElementById('fase-id').value);
    fd.append('id_especialidad', document.getElementById('fase-id-esp').value);
    fd.append('clave_fase', document.getElementById('fase-codigo').value);
    fd.append('nombre_fase', document.getElementById('fase-nombre').value);
    fd.append('orden', document.getElementById('fase-orden').value);
    fd.append('duracion_semanas', document.getElementById('fase-semanas').value);
    fd.append('objetivo_parcial', document.getElementById('fase-objetivo-parcial').value);
    if (esIngles) {
      evalFields.forEach(k => {
        fd.append('eval_' + k, document.getElementById('fase-' + k)?.value || '');
      });
    } else {
      fd.append('eval_criterios_json', JSON.stringify(collectCriterios()));
    }
    fd.append('moodle_course_id', document.getElementById('fase-moodle-course-id')?.value || '');
    fd.append('moodle_shortname', document.getElementById('fase-moodle-shortname')?.value || '');
    fd.append('semanas_json', JSON.stringify(collectSemanas()));
    const r = await fetch('php/fase_api.php', { method: 'POST', body: fd });
    const data = await r.json();
    showMsg(data.message, data.status === 'ok');
    if (data.status === 'ok') {
      closeModal();
      cargarSeccion('esp_fases', 'id_especialidad=' + document.getElementById('fase-id-esp').value);
    }
  };
})();
</script>
