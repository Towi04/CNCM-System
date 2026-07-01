<?php
require_once __DIR__ . '/../config.php';
$idUser = (int) ($_SESSION['user_id'] ?? 0);
$prospecto = docente_prospecto_es_candidato_usuario($pdo, $idUser);
if (!$prospecto) {
    echo '<div class="alert">No hay proceso de candidato activo para su cuenta.</div>';
    return;
}
$data = docente_prospecto_resultados_candidato($pdo, (int) $prospecto['id_prospecto']);
$p = $data['prospecto'] ?? $prospecto;
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-user-tie"></i> Mi proceso de selección</h2>
    <p style="color:#666;">Consulte los resultados de su clase muestra y pruebas aplicadas.</p>
  </div>

  <section style="margin-bottom:20px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3>Estado</h3>
    <p><strong><?php echo htmlspecialchars($p['estado'] ?? ''); ?></strong>
      · Decisión: <?php echo htmlspecialchars($p['decision_final'] ?? 'pendiente'); ?></p>
    <?php if (($p['decision_final'] ?? '') === 'no_contratar' || ($p['decision_final'] ?? '') === 'bolsa'): ?>
    <p style="color:#555;"><?php echo nl2br(htmlspecialchars($p['motivo_no_contratacion'] ?? '')); ?></p>
    <?php if (!empty($p['recontactar_en'])): ?>
    <p>Posible recontacto: <?php echo htmlspecialchars($p['recontactar_en']); ?></p>
    <?php endif; ?>
    <?php endif; ?>
  </section>

  <?php if (!empty($data['showclass'])): $sc = $data['showclass']; ?>
  <section style="margin-bottom:20px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3>Clase muestra — <?php echo htmlspecialchars((string) $sc['puntaje_total']); ?>%
      <?php echo (int) ($sc['aprobada'] ?? 0) ? '✓ Aprobada' : '✗ No aprobada'; ?></h3>
    <table class="catalog-table">
      <thead><tr><th>Criterio</th><th>Puntaje</th></tr></thead>
      <tbody>
        <?php foreach ($sc['rubrica'] ?? [] as $it): ?>
        <tr><td><?php echo htmlspecialchars($it['nombre'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars((string) ($it['puntaje'] ?? 0)); ?> / <?php echo (int) ($it['maximo'] ?? 0); ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!empty($sc['comentarios'])): ?>
    <p style="margin-top:8px;"><em><?php echo nl2br(htmlspecialchars($sc['comentarios'])); ?></em></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($data['disc'])): ?>
  <section style="margin-bottom:20px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3>Perfil DISC</h3>
    <p>Resultado registrado. Consulte con coordinación el detalle de su perfil.</p>
  </section>
  <?php elseif ((int) ($p['showclass_aprobado'] ?? 0) === 1 && empty($p['disc_resultado_id'])): ?>
  <section style="margin-bottom:20px;">
    <button type="button" class="primary" onclick="cargarSeccion('examen_disc', 'prospecto_docente=<?php echo (int) $p['id_prospecto']; ?>')">Realizar examen DISC</button>
  </section>
  <?php endif; ?>

  <section style="margin-bottom:20px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3>Documentación y certificación</h3>
    <p style="color:#555;margin-top:0;">Suba su certificación de conocimientos o los documentos solicitados. Si no cuenta con certificación, coordinación podrá inscribirlo al examen Moodle.</p>
    <button type="button" class="primary" onclick="cargarSeccion('mi_expediente_documentos','tipo=prospecto&id=<?php echo (int) $p['id_prospecto']; ?>')">
      <i class="fas fa-folder-open"></i> Subir / actualizar documentos
    </button>
    <?php
    $docs = expediente_documental_listar_con_entregas($pdo, 'prospecto', (int) $p['id_prospecto'], 'candidato_profesor');
    $areasPros = function_exists('docente_prospecto_areas') ? docente_prospecto_areas($pdo, (int) $p['id_prospecto']) : [];
    $pend = array_filter($docs, static fn($d) => empty($d['entrega']) || ($d['entrega']['estado'] ?? '') === 'pendiente');
    $aprob = array_filter($docs, static fn($d) => in_array($d['entrega']['estado'] ?? '', ['aprobado', 'exento'], true));
    ?>
    <p style="margin-top:10px;font-size:14px;">
      <?php if ($areasPros): ?>
      Áreas a evaluar: <strong><?php echo htmlspecialchars(implode(', ', array_column($areasPros, 'nombre'))); ?></strong><br>
      <?php endif; ?>
      Requisitos: <?php echo count($docs); ?>
      · Aprobados: <?php echo count($aprob); ?>
      · Pendientes: <?php echo count($pend); ?>
    </p>
  </section>
</div>
