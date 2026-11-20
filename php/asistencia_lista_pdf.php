<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$idGrupo = (int) ($_GET['id_grupo'] ?? 0);
$incluirTelefonos = !empty($_GET['telefonos']);
$semanaInicio = max(1, min(53, (int) ($_GET['semana_inicio'] ?? (int) date('W'))));
$totalSemanas = max(1, min(20, (int) ($_GET['semanas'] ?? 12)));
$idPlantel = plantel_scope_id($pdo);

if ($idGrupo <= 0 || !plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
    http_response_code(404);
    echo 'Grupo no encontrado';
    exit;
}

if (function_exists('rbac_cap') && !(rbac_cap('menu_grupos') || rbac_cap('menu_asistencia') || rbac_cap('asistencia_lista_grupo'))) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

asistencia_ensure_schema($pdo);

$stGrupo = $pdo->prepare(
    'SELECT g.*, e.nombre AS especialidad_nombre, e.clave AS especialidad_clave,
            CONCAT(u.nombre, " ", u.apellido) AS profesor_nombre,
            p.nombre AS plantel_nombre, p.razon_social, p.direccion AS plantel_direccion,
            p.prepa_nombre_sep, p.prepa_cct, p.prepa_rvoe, p.prepa_logo_url, p.prepa_direccion
     FROM grupos g
     LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
     LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
     LEFT JOIN planteles p ON p.id_plantel = g.id_plantel
     WHERE g.id_grupo = ? AND g.id_plantel = ?
     LIMIT 1'
);
$stGrupo->execute([$idGrupo, $idPlantel]);
$grupo = $stGrupo->fetch(PDO::FETCH_ASSOC);
if (!$grupo) {
    http_response_code(404);
    echo 'Grupo no encontrado';
    exit;
}
$esPrepaEscolarizada = strtoupper(trim((string) ($grupo['codigo_area'] ?? ''))) === 'PE'
    || str_starts_with(strtoupper(trim((string) ($grupo['clave'] ?? ''))), 'PE');
if ($esPrepaEscolarizada) {
    $grupo['razon_social'] = trim((string) ($grupo['prepa_nombre_sep'] ?? ''))
        ?: (trim((string) ($grupo['plantel_nombre'] ?? '')) ?: 'Preparatoria escolarizada');
    $grupo['plantel_direccion'] = trim((string) ($grupo['prepa_direccion'] ?? ''));
    $grupo['cct_lista'] = trim((string) ($grupo['prepa_cct'] ?? ''));
    $grupo['rvoe_lista'] = trim((string) ($grupo['prepa_rvoe'] ?? ''));
    $grupo['es_prepa_escolarizada'] = 1;
}

$dias = asistencia_lista_pdf_dias_grupo($pdo, $grupo);
$semanas = asistencia_lista_pdf_semanas($semanaInicio, $totalSemanas);
$alumnos = asistencia_lista_pdf_alumnos($pdo, $idGrupo);
$logoDataUri = asistencia_lista_pdf_logo_data_uri($pdo, $idPlantel, $grupo);
$filename = 'lista_asistencia_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($grupo['clave'] ?? $idGrupo)) . '.pdf';

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    if (class_exists('Dompdf\\Dompdf') && class_exists('Dompdf\\Options')) {
        // Sin barra de impresión: Dompdf la dibuja como imagen estática en el PDF
        $htmlPdf = asistencia_lista_pdf_html($grupo, $alumnos, $dias, $semanas, $incluirTelefonos, $logoDataUri, false);
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        if (defined('HAY_ROOT') && is_dir(HAY_ROOT)) {
            $options->setChroot(HAY_ROOT);
        }
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($htmlPdf, 'UTF-8');
        // Siempre horizontal (carta apaisada)
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $dompdf->output();
        exit;
    }
}

// Fallback HTML (sin Dompdf): sí mostrar botón de imprimir
$html = asistencia_lista_pdf_html($grupo, $alumnos, $dias, $semanas, $incluirTelefonos, $logoDataUri, true);
header('Content-Type: text/html; charset=UTF-8');
echo $html;

/** @return list<array{dia:int,label:string}> */
function asistencia_lista_pdf_dias_grupo(PDO $pdo, array $grupo): array
{
    $st = $pdo->prepare(
        'SELECT DISTINCT dia_semana
         FROM grupo_horarios
         WHERE id_grupo = ? AND activo = 1
         ORDER BY dia_semana'
    );
    $st->execute([(int) ($grupo['id_grupo'] ?? 0)]);
    $dias = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if ($dias === []) {
        $codigo = strtoupper((string) ($grupo['codigo_horario'] ?? ''));
        if ($codigo === 'S') {
            $dias = [6];
        } elseif ($codigo === 'D') {
            $dias = [0];
        } elseif (in_array($codigo, ['M', 'V'], true)) {
            $dias = [1, 2, 3, 4, 5];
        } else {
            $dias = [6];
        }
    }
    $labels = [0 => 'D', 1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S'];
    $out = [];
    foreach ($dias as $dia) {
        if ($dia >= 0 && $dia <= 6) {
            $out[] = ['dia' => $dia, 'label' => $labels[$dia]];
        }
    }

    return $out !== [] ? $out : [['dia' => 6, 'label' => 'S']];
}

/** @return list<int> */
function asistencia_lista_pdf_semanas(int $inicio, int $total): array
{
    $out = [];
    for ($i = 0; $i < $total; $i++) {
        $sem = (($inicio + $i - 1) % 53) + 1;
        $out[] = $sem;
    }

    return $out;
}

/** @return list<array<string,mixed>> */
function asistencia_lista_pdf_alumnos(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        'SELECT a.id_alumno, a.numero_control, a.nombres, a.apellido_paterno, a.apellido_materno,
                a.nombre, a.apellido, a.telefono, a.telefono2
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\'
         ORDER BY COALESCE(a.apellido_paterno, a.apellido, \'\'), COALESCE(a.apellido_materno, \'\'), COALESCE(a.nombres, a.nombre, \'\')'
    );
    $st->execute([$idGrupo]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function asistencia_lista_pdf_horario_label(array $grupo): string
{
    $codigo = strtoupper((string) ($grupo['codigo_horario'] ?? ''));
    return match ($codigo) {
        'M' => 'Matutino',
        'V' => 'Vespertino',
        'S' => 'Sabatino',
        'D' => 'Dominical',
        default => trim((string) ($grupo['horario_texto'] ?? '')) ?: '—',
    };
}

function asistencia_lista_pdf_nombre_alumno(array $alumno): string
{
    $nombre = trim(
        (string) ($alumno['apellido_paterno'] ?? $alumno['apellido'] ?? '') . ' '
        . (string) ($alumno['apellido_materno'] ?? '') . ' '
        . (string) ($alumno['nombres'] ?? $alumno['nombre'] ?? '')
    );
    return mb_strtoupper(trim($nombre), 'UTF-8');
}

function asistencia_lista_pdf_tel(array $alumno): string
{
    $tel = trim((string) ($alumno['telefono'] ?? ''));
    $tel2 = trim((string) ($alumno['telefono2'] ?? ''));
    if ($tel !== '' && $tel2 !== '') {
        return $tel . ' / ' . $tel2;
    }

    return $tel !== '' ? $tel : $tel2;
}

function asistencia_lista_pdf_tel_principal(array $alumno): string
{
    return trim((string) ($alumno['telefono'] ?? ''));
}

function asistencia_lista_pdf_tel_opcional(array $alumno): string
{
    return trim((string) ($alumno['telefono2'] ?? ''));
}

/** Logo del plantel embebido (data URI) para Dompdf / impresión. */
function asistencia_lista_pdf_logo_data_uri(PDO $pdo, int $idPlantel, array $grupo = []): string
{
    $candidatos = [];
    $esPrepa = !empty($grupo['es_prepa_escolarizada']);
    $logoRel = $esPrepa
        ? trim((string) ($grupo['prepa_logo_url'] ?? ''))
        : '';
    try {
        if ($logoRel === '' && !$esPrepa) {
            $st = $pdo->prepare('SELECT logo_url FROM planteles WHERE id_plantel = ? LIMIT 1');
            $st->execute([$idPlantel > 0 ? $idPlantel : (int) ($grupo['id_plantel'] ?? 0)]);
            $logoRel = trim((string) ($st->fetchColumn() ?: ''));
        }
    } catch (Throwable $e) {
        $logoRel = '';
    }
    if ($logoRel !== '') {
        $candidatos[] = $logoRel;
    }
    if (!$esPrepa) {
        $candidatos = array_merge($candidatos, [
            'src/logo.png',
            'src/logo.jpg',
            'uploads/logo.png',
            'uploads/planteles/logo.png',
            'default_avatar.png',
        ]);
    }

    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);
    foreach ($candidatos as $rel) {
        $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
        if ($rel === '' || str_contains($rel, '..')) {
            continue;
        }
        if (preg_match('#^https?://#i', $rel)) {
            $bin = @file_get_contents($rel);
            if ($bin !== false && strlen($bin) > 40) {
                $mime = 'image/png';
                if (preg_match('/\.jpe?g($|\?)/i', $rel)) {
                    $mime = 'image/jpeg';
                } elseif (preg_match('/\.webp($|\?)/i', $rel)) {
                    $mime = 'image/webp';
                } elseif (preg_match('/\.gif($|\?)/i', $rel)) {
                    $mime = 'image/gif';
                }
                return 'data:' . $mime . ';base64,' . base64_encode($bin);
            }
            continue;
        }
        $abs = $root . '/' . $rel;
        if (!is_file($abs)) {
            continue;
        }
        $bin = @file_get_contents($abs);
        if ($bin === false || strlen($bin) < 40) {
            continue;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        // Reducir logos muy grandes para Dompdf (p. ej. src/logo.png original).
        if (function_exists('imagecreatefromstring') && in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            $img = @imagecreatefromstring($bin);
            if ($img !== false) {
                $w = imagesx($img);
                $h = imagesy($img);
                $maxW = 160;
                if ($w > $maxW && $w > 0) {
                    $nw = $maxW;
                    $nh = max(1, (int) round($h * ($maxW / $w)));
                    $scaled = imagecreatetruecolor($nw, $nh);
                    if ($scaled !== false) {
                        imagealphablending($scaled, false);
                        imagesavealpha($scaled, true);
                        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
                        imagefilledrectangle($scaled, 0, 0, $nw, $nh, $transparent);
                        imagecopyresampled($scaled, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        ob_start();
                        imagepng($scaled, null, 6);
                        $resized = ob_get_clean();
                        imagedestroy($scaled);
                        imagedestroy($img);
                        if (is_string($resized) && strlen($resized) > 40) {
                            return 'data:image/png;base64,' . base64_encode($resized);
                        }
                    }
                }
                imagedestroy($img);
            }
        }
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    return '';
}

function asistencia_lista_pdf_html(
    array $grupo,
    array $alumnos,
    array $dias,
    array $semanas,
    bool $incluirTelefonos,
    string $logoDataUri = '',
    bool $mostrarBarraImpresion = false
): string {
    $numCols = count($dias) * count($semanas);
    $minRows = max(14, count($alumnos));
    $especialidad = mb_strtoupper((string) ($grupo['especialidad_nombre'] ?? $grupo['especialidad_clave'] ?? ''), 'UTF-8');
    $profesor = trim((string) ($grupo['profesor_nombre'] ?? ''));
    $plantel = trim((string) ($grupo['razon_social'] ?? 'GRUPO EDUCATIVO CNCM')) ?: 'GRUPO EDUCATIVO CNCM';
    $nombrePlantel = trim((string) ($grupo['plantel_nombre'] ?? ''));
    $direccion = trim((string) ($grupo['plantel_direccion'] ?? ''));
    $cct = trim((string) ($grupo['cct_lista'] ?? ''));
    $rvoe = trim((string) ($grupo['rvoe_lista'] ?? ''));

    // Anchos por contenido (Carta apaisada ~100%): Nombre toma lo que pide el
    // nombre más largo; N°/Ctrl/Tel fijos; el resto se reparte en celdas de asistencia.
    $maxNombreChars = 12;
    $maxCtrlChars = 6;
    $maxTelChars = 0;
    foreach ($alumnos as $alRow) {
        $maxNombreChars = max($maxNombreChars, mb_strlen(asistencia_lista_pdf_nombre_alumno($alRow), 'UTF-8'));
        $maxCtrlChars = max($maxCtrlChars, mb_strlen(trim((string) ($alRow['numero_control'] ?? '')), 'UTF-8'));
        if ($incluirTelefonos) {
            $maxTelChars = max(
                $maxTelChars,
                mb_strlen(asistencia_lista_pdf_tel_principal($alRow), 'UTF-8'),
                mb_strlen(asistencia_lista_pdf_tel_opcional($alRow), 'UTF-8')
            );
        }
    }
    // Em aproximado a % de página Letter landscape (fuente ~8px).
    $pctNum = 2.2;
    $pctCtrl = max(6.5, min(11.0, round($maxCtrlChars * 0.95 + 1.5, 2)));
    $pctTel = $incluirTelefonos ? max(7.0, min(10.0, round(max(10, $maxTelChars) * 0.72 + 1.0, 2))) : 0.0;
    $pctTelTotal = $incluirTelefonos ? ($pctTel * 2) : 0.0;
    // Semanas: celdas mínimas (solo marca √); el resto al nombre.
    $pctAsistMin = $numCols >= 30 ? 1.15 : ($numCols >= 20 ? 1.35 : 1.6);
    $pctAsistBudget = max(18.0, min(48.0, $pctAsistMin * max(1, $numCols)));
    $pctAsist = $numCols > 0 ? round($pctAsistBudget / $numCols, 3) : 0.0;
    $pctNombre = round(100.0 - $pctNum - $pctCtrl - $pctTelTotal - ($pctAsist * $numCols), 2);
    // Garantizar nombre usable aunque haya muchas semanas.
    if ($pctNombre < 22.0) {
        $pctNombre = 22.0;
        $rest = max(12.0, 100.0 - $pctNum - $pctCtrl - $pctTelTotal - $pctNombre);
        $pctAsist = $numCols > 0 ? round($rest / $numCols, 3) : 0.0;
    } elseif ($pctNombre > 48.0 && $numCols > 0) {
        // No dejar el nombre excesivo si cabe; ceder un poco a asistencia.
        $pctNombre = max(28.0, min(48.0, round($maxNombreChars * 0.9 + 8, 2)));
        $rest = max(12.0, 100.0 - $pctNum - $pctCtrl - $pctTelTotal - $pctNombre);
        $pctAsist = round($rest / $numCols, 3);
    }
    $fontNombre = $maxNombreChars >= 36 ? '7.5px' : ($maxNombreChars >= 28 ? '8px' : '8.5px');
    $fontAsist = $numCols >= 28 ? '6.5px' : ($numCols >= 18 ? '7px' : '7.5px');

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Lista de asistencia <?php echo htmlspecialchars((string) ($grupo['clave'] ?? '')); ?></title>
  <style>
    @page { size: Letter landscape; margin: 8mm; }
    * { box-sizing: border-box; }
    html, body { width: 100%; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#111; margin:0; font-size:9px; }
    .no-print { text-align:center; margin: 0 0 10px; padding:8px; background:#f3f3f3; border-bottom:1px solid #ddd; }
    .no-print button { padding:8px 14px; cursor:pointer; }
    .sheet { width:100%; }
    .brand { display:table; width:100%; margin-bottom:6px; }
    .brand-logo, .brand-meta { display:table-cell; vertical-align:middle; }
    .brand-logo { width:78px; }
    .brand-logo img { width:70px; height:auto; max-height:54px; object-fit:contain; }
    .brand-meta { padding-left:10px; }
    .brand-meta .org { font-size:13px; font-weight:700; line-height:1.2; }
    .brand-meta .sub { font-size:10px; color:#333; margin-top:2px; }
    .head { display:table; width:100%; font-size:11px; margin: 2px 0 4px; }
    .head-left, .head-right { display:table-cell; width:50%; vertical-align:top; }
    .head strong { font-weight:700; }
    .materia { margin: 2px 0 5px; font-size:11px; }
    table.lista { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.lista th, table.lista td { border:1px solid #222; padding:1px 3px; vertical-align:middle; }
    table.lista th { font-weight:700; text-align:center; background:#f7f7f7; }
    table.lista td { height:16px; }
    th.num, td.num { text-align:center; white-space:nowrap; overflow:visible; }
    th.ctrl, td.ctrl { text-align:center; white-space:nowrap; font-size:8px; overflow:visible; word-break:break-all; }
    th.tel, td.tel { font-size:7px; white-space:nowrap; overflow:visible; }
    th.nombre, td.nombre {
      text-align:left;
      font-size:<?php echo htmlspecialchars($fontNombre, ENT_QUOTES, 'UTF-8'); ?>;
      line-height:1.15;
      white-space:normal;
      word-wrap:break-word;
      overflow-wrap:anywhere;
      overflow:visible;
    }
    th.asist, td.asist {
      text-align:center;
      padding:0;
      white-space:nowrap;
      font-size:<?php echo htmlspecialchars($fontAsist, ENT_QUOTES, 'UTF-8'); ?>;
      overflow:hidden;
    }
    .obs { margin-top:8px; font-size:11px; }
    .obs-line { border-bottom:1px solid #111; height:16px; }
    .foot { margin-top:8px; text-align:center; font-size:10px; line-height:1.25; }
    .title { text-align:right; font-size:10px; margin-top:2px; }
    @media print {
      .no-print, .no-print * { display:none !important; visibility:hidden !important; height:0 !important; margin:0 !important; padding:0 !important; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      @page { size: Letter landscape; margin: 8mm; }
    }
  </style>
</head>
<body>
  <?php if ($mostrarBarraImpresion): ?>
  <div class="no-print" aria-hidden="false">
    <p style="margin:0 0 8px;color:#555;">Orientación: <strong>horizontal (apaisada)</strong>. Si el diálogo de impresión muestra vertical, cámbielo a horizontal.</p>
    <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>
  <?php endif; ?>
  <div class="sheet">
    <div class="brand">
      <div class="brand-logo">
        <?php if ($logoDataUri !== ''): ?>
          <img src="<?php echo htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
        <?php endif; ?>
      </div>
      <div class="brand-meta">
        <div class="org"><?php echo htmlspecialchars($plantel); ?></div>
        <div class="sub">
          Lista de asistencia
          <?php if ($nombrePlantel !== ''): ?> · <?php echo htmlspecialchars($nombrePlantel); ?><?php endif; ?>
          <?php if ($cct !== ''): ?> · CCT <?php echo htmlspecialchars($cct); ?><?php endif; ?>
          <?php if ($rvoe !== ''): ?> · RVOE <?php echo htmlspecialchars($rvoe); ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="head">
      <div class="head-left">
        <strong>Horario:</strong> <?php echo htmlspecialchars(asistencia_lista_pdf_horario_label($grupo)); ?>
        &nbsp; <strong>Especialidad:</strong> <?php echo htmlspecialchars($especialidad !== '' ? $especialidad : '—'); ?>
      </div>
      <div class="head-right">
        <strong>Grupo:</strong> <?php echo htmlspecialchars((string) ($grupo['clave'] ?? '')); ?>
        &nbsp; <strong>Profesor:</strong> <?php echo htmlspecialchars($profesor !== '' ? $profesor : '________________________'); ?>
      </div>
    </div>
    <div class="materia"><strong>Materia:</strong> ________________________</div>
    <table class="lista" width="100%">
      <colgroup>
        <col width="<?php echo htmlspecialchars((string) $pctNum, ENT_QUOTES, 'UTF-8'); ?>%" style="width:<?php echo htmlspecialchars((string) $pctNum, ENT_QUOTES, 'UTF-8'); ?>%">
        <col width="<?php echo htmlspecialchars((string) $pctCtrl, ENT_QUOTES, 'UTF-8'); ?>%" style="width:<?php echo htmlspecialchars((string) $pctCtrl, ENT_QUOTES, 'UTF-8'); ?>%">
        <col width="<?php echo htmlspecialchars((string) $pctNombre, ENT_QUOTES, 'UTF-8'); ?>%" style="width:<?php echo htmlspecialchars((string) $pctNombre, ENT_QUOTES, 'UTF-8'); ?>%">
        <?php if ($incluirTelefonos): ?>
          <col width="<?php echo htmlspecialchars((string) $pctTel, ENT_QUOTES, 'UTF-8'); ?>%" style="width:<?php echo htmlspecialchars((string) $pctTel, ENT_QUOTES, 'UTF-8'); ?>%">
          <col width="<?php echo htmlspecialchars((string) $pctTel, ENT_QUOTES, 'UTF-8'); ?>%" style="width:<?php echo htmlspecialchars((string) $pctTel, ENT_QUOTES, 'UTF-8'); ?>%">
        <?php endif; ?>
        <?php for ($c = 0; $c < $numCols; $c++): ?>
          <col width="<?php echo htmlspecialchars((string) $pctAsist, ENT_QUOTES, 'UTF-8'); ?>%" style="width:<?php echo htmlspecialchars((string) $pctAsist, ENT_QUOTES, 'UTF-8'); ?>%">
        <?php endfor; ?>
      </colgroup>
      <thead>
        <tr>
          <th class="num" rowspan="2" width="<?php echo htmlspecialchars((string) $pctNum, ENT_QUOTES, 'UTF-8'); ?>%">N°</th>
          <th class="ctrl" rowspan="2" width="<?php echo htmlspecialchars((string) $pctCtrl, ENT_QUOTES, 'UTF-8'); ?>%">N° Ctrl</th>
          <th class="nombre" rowspan="2" width="<?php echo htmlspecialchars((string) $pctNombre, ENT_QUOTES, 'UTF-8'); ?>%">Nombre</th>
          <?php if ($incluirTelefonos): ?>
            <th class="tel" rowspan="2" width="<?php echo htmlspecialchars((string) $pctTel, ENT_QUOTES, 'UTF-8'); ?>%">Tel</th>
            <th class="tel" rowspan="2" width="<?php echo htmlspecialchars((string) $pctTel, ENT_QUOTES, 'UTF-8'); ?>%">Tel 2</th>
          <?php endif; ?>
          <?php foreach ($semanas as $semana): ?>
            <th class="asist" colspan="<?php echo count($dias); ?>"><?php echo (int) $semana; ?></th>
          <?php endforeach; ?>
        </tr>
        <tr>
          <?php foreach ($semanas as $_semana): ?>
            <?php foreach ($dias as $dia): ?>
              <th class="asist" width="<?php echo htmlspecialchars((string) $pctAsist, ENT_QUOTES, 'UTF-8'); ?>%"><?php echo htmlspecialchars($dia['label']); ?></th>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php for ($i = 0; $i < $minRows; $i++): ?>
          <?php $al = $alumnos[$i] ?? null; ?>
          <tr>
            <td class="num"><?php echo $i + 1; ?></td>
            <td class="ctrl"><?php echo $al ? htmlspecialchars((string) ($al['numero_control'] ?? '')) : ''; ?></td>
            <td class="nombre"><?php echo $al ? htmlspecialchars(asistencia_lista_pdf_nombre_alumno($al)) : ''; ?></td>
            <?php if ($incluirTelefonos): ?>
              <td class="tel"><?php echo $al ? htmlspecialchars(asistencia_lista_pdf_tel_principal($al)) : ''; ?></td>
              <td class="tel"><?php echo $al ? htmlspecialchars(asistencia_lista_pdf_tel_opcional($al)) : ''; ?></td>
            <?php endif; ?>
            <?php for ($c = 0; $c < $numCols; $c++): ?><td class="asist">&nbsp;</td><?php endfor; ?>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <div class="obs">
      <strong>Observaciones:</strong>
      <div class="obs-line"></div>
      <div class="obs-line"></div>
    </div>
    <div class="foot">
      <strong><?php echo htmlspecialchars($plantel); ?></strong><br>
      <?php echo htmlspecialchars($direccion); ?>
    </div>
  </div>
</body>
</html>
    <?php
    return (string) ob_get_clean();
}
