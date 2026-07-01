<?php
require_once __DIR__ . '/../config.php';
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$fases = [];
$fusiones = [];
$historial = [];
$errorCarga = null;

try {
    require_once __DIR__ . '/../php/exam/load.php';
    $svc = new \HayExam\InglesExamService($pdo, dirname(__DIR__));
    $fases = $svc->getFasesDisponibles();
    $fusiones = $svc->getFusionesGuardadas();
    $historial = $svc->listarGenerados(20);
} catch (Throwable $e) {
    $errorCarga = $e->getMessage();
}
?>

<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/examenes.css">

<div class="result-container exam-wizard">
  <div class="result-header">
    <h2><i class="fas fa-file-alt"></i> Generar examen — Inglés</h2>
    <p style="color:#666; margin-top:6px;">45 preguntas: 10 vocabulario · 20 gramática · 5 listening · 6 reading · 2 writing · 2 speaking</p>
    <p style="margin-top:8px;">
      <a href="php/exam/descargar.php?tipo=hoja" target="_blank" class="btn-outline" style="display:inline-block;padding:6px 14px;font-size:0.9rem;">
        <i class="fas fa-print"></i> Imprimir hoja de respuestas (universal)
      </a>
    </p>
  </div>

  <?php if ($errorCarga): ?>
    <div class="exam-msg err" style="display:block;">
      <strong>No se pudo cargar el generador de exámenes.</strong><br>
      <?php echo htmlspecialchars($errorCarga); ?>
      <br><small>Si las tablas no existen, ejecute <code>sql/exam_ingles_schema.sql</code> y <code>sql/exam_fase_texto_migrate.sql</code> en phpMyAdmin.</small>
    </div>
  <?php endif; ?>

  <div id="exam-msg" class="exam-msg"></div>

  <div class="exam-steps">
    <span class="exam-step-pill active" data-step="1">1. Tipo</span>
    <span class="exam-step-pill" data-step="2">2. Configuración</span>
    <span class="exam-step-pill" data-step="3">3. Generar</span>
  </div>

  <form id="form-examen-ingles" action="php/exam/generar_examen_ingles.php" method="POST" enctype="multipart/form-data" data-no-global-ajax>
    <input type="hidden" name="tipo" id="exam-tipo" value="">
    <input type="hidden" name="fases" id="exam-fases-hidden" value="">

    <!-- Paso 1: Tipo de examen -->
    <div class="exam-panel active" id="panel-1">
      <h3>¿Qué tipo de examen desea generar?</h3>
      <div class="exam-tipo-grid">
        <div class="exam-tipo-card" data-tipo="fase">
          <h4><i class="fas fa-layer-group"></i> Fase</h4>
          <p>Un solo parcial. Elija la fase a evaluar.</p>
        </div>
        <div class="exam-tipo-card" data-tipo="nivel">
          <h4><i class="fas fa-chart-line"></i> Nivel</h4>
          <p>Tres fases combinadas en un solo examen.</p>
        </div>
        <div class="exam-tipo-card" data-tipo="fusion">
          <h4><i class="fas fa-random"></i> Fusión</h4>
          <p>Varias fases, fusión guardada o banco CSV propio.</p>
        </div>
      </div>
      <button type="button" class="primary" id="btn-step1-next" disabled>Siguiente</button>
    </div>

    <!-- Paso 2: Configuración -->
    <div class="exam-panel" id="panel-2">
      <h3 id="panel2-title">Configuración</h3>

      <label style="display:block; margin:12px 0 6px; font-weight:600;">Nombre del examen (opcional)</label>
      <input type="text" name="nombre_examen" id="nombre_examen" placeholder="Ej. Fase 3 — Parcial 2" style="width:100%; max-width:400px; padding:10px; border-radius:8px; border:1px solid #ccc;">

      <!-- Fase / Nivel: selección de fases -->
      <div id="block-fases">
        <p id="fases-hint" style="margin:12px 0 6px; color:#555;"></p>
        <div class="exam-fases-grid" id="fases-container">
          <?php if (empty($fases)): ?>
            <p style="color:#c00;">No hay fases en el banco. Importe preguntas en la base de datos.</p>
          <?php else: ?>
            <?php foreach ($fases as $f): ?>
              <span class="exam-fase-chip" data-fase="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Fusión: opciones extra -->
      <div id="block-fusion" style="display:none; margin-top:20px;">
        <hr style="border:none; border-top:1px solid #eee; margin:16px 0;">
        <h4>Fusión guardada</h4>
        <select name="id_fusion" id="id_fusion" style="padding:10px; border-radius:8px; min-width:280px;">
          <option value="">— Nueva fusión / seleccionar fases —</option>
          <?php foreach ($fusiones as $fu): ?>
            <option value="<?php echo (int)$fu['id_fusion']; ?>">
              <?php echo htmlspecialchars($fu['nombre']); ?>
              <?php if ($fu['fases']): ?> (fases: <?php echo htmlspecialchars($fu['fases']); ?>)<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>

        <div style="margin-top:16px;">
          <label style="font-weight:600;">
            <input type="checkbox" name="guardar_fusion" id="guardar_fusion" value="1">
            Guardar esta combinación como fusión reutilizable
          </label>
        </div>
        <input type="text" name="nombre_fusion" id="nombre_fusion" placeholder="Nombre de la fusión (ej. Repaso final A-B-C)" style="width:100%; max-width:400px; padding:10px; margin-top:8px; border-radius:8px; border:1px solid #ccc; display:none;">

        <hr style="border:none; border-top:1px solid #eee; margin:20px 0;">
        <h4>Importar preguntas desde CSV (opcional)</h4>
        <p style="font-size:0.9rem; color:#666;">Suba un CSV con columnas según el tipo. Puede importar sin generar examen aún.</p>
        <select name="tipo_csv" style="padding:8px; border-radius:8px; margin-right:8px;">
          <option value="vocabulario">Vocabulario</option>
          <option value="gramatica">Gramática</option>
          <option value="audios">Audios</option>
          <option value="lecturas">Lecturas</option>
          <option value="listening">Listening</option>
          <option value="reading">Reading</option>
          <option value="writing">Writing</option>
          <option value="speaking">Speaking</option>
        </select>
        <input type="file" name="csv_fusion" accept=".csv" style="margin:8px 0;">
        <button type="button" class="primary" id="btn-import-csv" style="margin-left:8px;">Solo importar CSV</button>
        <input type="hidden" name="solo_importar_csv" id="solo_importar_csv" value="">
      </div>

      <div style="margin-top:20px; display:flex; gap:10px;">
        <button type="button" id="btn-step2-back">Atrás</button>
        <button type="button" class="primary" id="btn-step2-next" disabled>Siguiente</button>
      </div>
    </div>

    <!-- Paso 3: Confirmar y generar -->
    <div class="exam-panel" id="panel-3">
      <h3>Resumen</h3>
      <div id="exam-resumen" style="background:#f9fafb; padding:16px; border-radius:10px; margin-bottom:16px;"></div>
      <button type="button" id="btn-step3-back">Atrás</button>
      <button type="submit" class="primary" id="btn-generar" <?php echo $errorCarga ? 'disabled' : ''; ?>>
        <i class="fas fa-magic"></i> Generar examen (imprimible Carta + CSV)
      </button>
    </div>
  </form>

  <div id="exam-resultado" class="exam-result-box" style="display:none;"></div>

  <?php if (!empty($historial)): ?>
  <hr style="margin:32px 0 20px; border:none; border-top:2px solid #eee;">
  <h3>Exámenes recientes</h3>
  <div class="hist-lines">
    <?php foreach ($historial as $h): ?>
      <div class="hist-line" style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid #eee;">
        <strong><?php echo htmlspecialchars($h['nombre_examen']); ?></strong>
        <span style="color:#666; font-size:0.9rem;"><?php echo htmlspecialchars($h['id_examen']); ?> · <?php echo htmlspecialchars($h['tipo']); ?> · <?php echo htmlspecialchars($h['creado_en']); ?></span>
        <a href="php/exam/descargar.php?tipo=pdf&id=<?php echo urlencode($h['id_examen']); ?>" target="_blank" class="primary" style="padding:6px 12px; font-size:0.85rem;">PDF</a>
        <a href="php/exam/descargar.php?tipo=csv&id=<?php echo urlencode($h['id_examen']); ?>" target="_blank" style="padding:6px 12px; font-size:0.85rem;">Respuestas</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
(function() {
  let tipo = '';
  let fasesSel = [];
  const pills = document.querySelectorAll('.exam-step-pill');
  const panels = document.querySelectorAll('.exam-panel');
  const form = document.getElementById('form-examen-ingles');
  const msgBox = document.getElementById('exam-msg');
  const resultBox = document.getElementById('exam-resultado');

  function goStep(n) {
    panels.forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + n).classList.add('active');
    pills.forEach(p => {
      const s = parseInt(p.dataset.step, 10);
      p.classList.toggle('active', s === n);
      p.classList.toggle('done', s < n);
    });
    if (n === 3) buildResumen();
  }

  function showMsg(text, ok) {
    msgBox.textContent = text;
    msgBox.className = 'exam-msg ' + (ok ? 'ok' : 'err');
    msgBox.style.display = 'block';
  }

  document.querySelectorAll('.exam-tipo-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.exam-tipo-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      tipo = card.dataset.tipo;
      document.getElementById('exam-tipo').value = tipo;
      document.getElementById('btn-step1-next').disabled = false;
    });
  });

  document.getElementById('btn-step1-next').addEventListener('click', () => {
    const blockFusion = document.getElementById('block-fusion');
    const hint = document.getElementById('fases-hint');
    blockFusion.style.display = tipo === 'fusion' ? 'block' : 'none';
    if (tipo === 'fase') {
      hint.textContent = 'Seleccione 1 fase:';
    } else if (tipo === 'nivel') {
      hint.textContent = 'Seleccione exactamente 3 fases:';
    } else {
      hint.textContent = 'Seleccione una o más fases (o elija una fusión guardada):';
    }
    fasesSel = [];
    document.querySelectorAll('.exam-fase-chip').forEach(c => c.classList.remove('selected'));
    document.getElementById('btn-step2-next').disabled = true;
    goStep(2);
  });

  document.querySelectorAll('.exam-fase-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const f = chip.dataset.fase;
      const idx = fasesSel.indexOf(f);
      if (tipo === 'fase') {
        fasesSel = [f];
        document.querySelectorAll('.exam-fase-chip').forEach(c => c.classList.remove('selected'));
        chip.classList.add('selected');
      } else if (tipo === 'nivel') {
        if (idx >= 0) {
          fasesSel.splice(idx, 1);
          chip.classList.remove('selected');
        } else if (fasesSel.length < 3) {
          fasesSel.push(f);
          chip.classList.add('selected');
        }
      } else {
        if (idx >= 0) {
          fasesSel.splice(idx, 1);
          chip.classList.remove('selected');
        } else {
          fasesSel.push(f);
          chip.classList.add('selected');
        }
      }
      fasesSel.sort((a, b) => String(a).localeCompare(String(b)));
      document.getElementById('exam-fases-hidden').value = fasesSel.join(',');
      validateStep2();
    });
  });

  document.getElementById('id_fusion').addEventListener('change', validateStep2);
  document.getElementById('guardar_fusion').addEventListener('change', function() {
    document.getElementById('nombre_fusion').style.display = this.checked ? 'block' : 'none';
  });

  function validateStep2() {
    const fusionId = document.getElementById('id_fusion').value;
    let ok = false;
    if (tipo === 'fase') ok = fasesSel.length === 1;
    else if (tipo === 'nivel') ok = fasesSel.length === 3;
    else ok = fasesSel.length >= 1 || fusionId !== '';
    document.getElementById('btn-step2-next').disabled = !ok;
  }

  document.getElementById('btn-step2-back').addEventListener('click', () => goStep(1));
  document.getElementById('btn-step2-next').addEventListener('click', () => goStep(3));
  document.getElementById('btn-step3-back').addEventListener('click', () => goStep(2));

  function buildResumen() {
    const nombre = document.getElementById('nombre_examen').value || '(automático)';
    const fusion = document.getElementById('id_fusion').selectedOptions[0]?.text || '';
    let html = '<p><strong>Tipo:</strong> ' + tipo + '</p>';
    html += '<p><strong>Nombre:</strong> ' + nombre + '</p>';
    if (fasesSel.length) html += '<p><strong>Fases:</strong> ' + fasesSel.join(', ') + '</p>';
    if (tipo === 'fusion' && document.getElementById('id_fusion').value) {
      html += '<p><strong>Fusión:</strong> ' + fusion + '</p>';
    }
    document.getElementById('exam-resumen').innerHTML = html;
  }

  document.getElementById('btn-import-csv').addEventListener('click', async () => {
    document.getElementById('solo_importar_csv').value = '1';
    const fd = new FormData(form);
    try {
      const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      showMsg(data.message || (data.status === 'ok' ? 'Importado' : 'Error'), data.status === 'ok');
    } catch (e) {
      showMsg('Error al importar CSV.', false);
    }
    document.getElementById('solo_importar_csv').value = '';
  });

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    document.getElementById('solo_importar_csv').value = '';
    document.getElementById('exam-fases-hidden').value = fasesSel.join(',');
    fasesSel.forEach((f, i) => {
      let inp = form.querySelector('input[name="fases[]"]');
      if (!inp) {
        inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'fases[]';
        form.appendChild(inp);
      }
    });
    const existing = form.querySelectorAll('input[name="fases[]"]');
    existing.forEach(el => el.remove());
    fasesSel.forEach(f => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'fases[]';
      inp.value = f;
      form.appendChild(inp);
    });

    const btn = document.getElementById('btn-generar');
    btn.disabled = true;
    btn.textContent = 'Generando…';
    try {
      const fd = new FormData(form);
      const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status === 'ok') {
        showMsg(data.message, true);
        resultBox.style.display = 'block';
        let links = '<a href="' + data.pdf_url + '" target="_blank" class="primary"><i class="fas fa-file-alt"></i> Examen</a>' +
          '<a href="' + data.csv_url + '" target="_blank"><i class="fas fa-key"></i> Respuestas</a>';
        resultBox.innerHTML = '<h4>Examen ' + data.id_examen + '</h4><p>' + (data.nombre || '') + '</p>' + links;
        const hist = document.querySelector('.hist-lines');
        if (hist && data.id_examen) {
          const idEnc = encodeURIComponent(data.id_examen);
          const row = document.createElement('div');
          row.className = 'hist-line';
          row.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #eee;';
          row.innerHTML = '<strong>' + (data.nombre || data.id_examen) + '</strong>' +
            '<span style="color:#666;font-size:0.9rem;">' + data.id_examen + ' · recién generado</span>' +
            '<a href="php/exam/descargar.php?tipo=pdf&id=' + idEnc + '" target="_blank" class="primary" style="padding:6px 12px;font-size:0.85rem;">PDF</a>' +
            '<a href="php/exam/descargar.php?tipo=csv&id=' + idEnc + '" target="_blank" style="padding:6px 12px;font-size:0.85rem;">Respuestas</a>';
          hist.insertBefore(row, hist.firstChild);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        showMsg(data.message || 'No se pudo generar el examen.', false);
      }
    } catch (err) {
      showMsg('Error de conexión al generar el examen.', false);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-magic"></i> Generar examen (PDF + CSV)';
  });
})();
</script>
