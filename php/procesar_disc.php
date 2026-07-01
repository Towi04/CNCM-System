<?php
// php/procesar_disc.php
require_once '../config.php';
global $pdo;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
    exit;
}

$usuario_id = $_SESSION['user_id'];
$idProspectoDocente = (int) ($_POST['id_prospecto_docente'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El formulario ahora envía IDs de disc_words por sección (1..28)
    $mas = $_POST['mas'] ?? [];
    $menos = $_POST['menos'] ?? [];

    // Normalizar/validar: necesitamos secciones 1..28
    for ($s = 1; $s <= 28; $s++) {
        if (!isset($mas[$s], $menos[$s])) {
            die("Faltan respuestas para la sección $s.");
        }
        $mas[$s] = (int)$mas[$s];
        $menos[$s] = (int)$menos[$s];
        if ($mas[$s] <= 0 || $menos[$s] <= 0) {
            die("Respuesta inválida en la sección $s.");
        }
        if ($mas[$s] === $menos[$s]) {
            die("No puedes elegir la misma palabra en MÁS y MENOS (sección $s).");
        }
    }

    // Traer factores desde disc_words para todos los IDs usados
    $allIds = array_values(array_unique(array_merge(array_values($mas), array_values($menos))));
    $placeholders = implode(',', array_fill(0, count($allIds), '?'));

    $stmt = $pdo->prepare("SELECT id, mas, menos FROM disc_words WHERE id IN ($placeholders)");
    $stmt->execute($allIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byId = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = ['mas' => $r['mas'], 'menos' => $r['menos']];
    }
    foreach ($allIds as $id) {
        if (!isset($byId[(int)$id])) {
            die("No existe la palabra seleccionada con id=$id.");
        }
    }

    // Conteos separados
    $plus = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
    $minus = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];

    for ($s = 1; $s <= 28; $s++) {
        $idMas = $mas[$s];
        $idMenos = $menos[$s];

        $fMas = $byId[$idMas]['mas'];
        $fMenos = $byId[$idMenos]['menos'];

        if ($fMas !== 'N' && isset($plus[$fMas])) {
            $plus[$fMas]++;
        }
        if ($fMenos !== 'N' && isset($minus[$fMenos])) {
            $minus[$fMenos]++;
        }
    }

    $diff = [
        'D' => (int)$plus['D'] - (int)$minus['D'],
        'I' => (int)$plus['I'] - (int)$minus['I'],
        'S' => (int)$plus['S'] - (int)$minus['S'],
        'C' => (int)$plus['C'] - (int)$minus['C'],
    ];

    // Calcular código autopercepción (DI SC) y pat_id (si existe)
    $scaleDiff = function (string $letter, int $d): int {
        return match ($letter) {
            'D' => ($d <= -9) ? 1 : (($d <= -4) ? 2 : (($d <= -1) ? 3 : (($d <= 1) ? 4 : (($d <= 5) ? 5 : (($d <= 9) ? 6 : 7))))),
            'I' => ($d <= -8) ? 1 : (($d <= -5) ? 2 : (($d <= -3) ? 3 : (($d <= -1) ? 4 : (($d <= 2) ? 5 : (($d <= 4) ? 6 : 7))))),
            'S' => ($d <= -10) ? 1 : (($d <= -7) ? 2 : (($d <= -4) ? 3 : (($d <= -2) ? 4 : (($d <= 1) ? 5 : (($d <= 4) ? 6 : 7))))),
            'C' => ($d <= -6) ? 1 : (($d <= -3) ? 2 : (($d <= -1) ? 3 : (($d <= 1) ? 4 : (($d <= 4) ? 5 : (($d <= 7) ? 6 : 7))))),
            default => 4,
        };
    };

    $codigo = (string)$scaleDiff('D', $diff['D']) .
              (string)$scaleDiff('I', $diff['I']) .
              (string)$scaleDiff('S', $diff['S']) .
              (string)$scaleDiff('C', $diff['C']);

    $patId = null;
    try {
        $stmt = $pdo->prepare("SELECT pat_id FROM disc_cod WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigo]);
        $patId = $stmt->fetchColumn();
        $patId = $patId !== false ? (int)$patId : null;
    } catch (Throwable $e) {
        $patId = null;
    }

    // Fallback: resolver pat_id via tabla `patrones` (codigo -> patron_slug) + disc_pat(slug->id)
    if (!$patId) {
        try {
            $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
            $colStmt = $pdo->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'patrones'
            ");
            $colStmt->execute([$dbName]);
            $cols = array_map(fn($x) => (string)$x['COLUMN_NAME'], $colStmt->fetchAll(PDO::FETCH_ASSOC));

            $codigoCol = null;
            foreach (['codigo','code','cod','perfil','pattern_code'] as $c) {
                if (in_array($c, $cols, true)) { $codigoCol = $c; break; }
            }
            $slugCol = null;
            foreach (['patron_slug','slug','patron','perfil_slug','pattern_slug','nombre','name'] as $c) {
                if (in_array($c, $cols, true)) { $slugCol = $c; break; }
            }

            if ($codigoCol && $slugCol) {
                $sql = "
                    SELECT dp.id
                    FROM patrones p
                    JOIN disc_pat dp ON dp.slug = p.`$slugCol`
                    WHERE p.`$codigoCol` = ?
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo]);
                $pid = $stmt->fetchColumn();
                $patId = $pid !== false ? (int)$pid : null;
            }
        } catch (Throwable $e) {
            $patId = null;
        }
    }

    try {
        // Guardar en disc_res con el orden de columnas requerido.
        // Columnas: 1+..28+, 1-..28-, D+ I+ S+ C+ D- I- S- C- D I S C codigo pat_id
        $cols = ['user_id'];
        $vals = [$usuario_id];

        for ($s = 1; $s <= 28; $s++) { $cols[] = "`{$s}+`"; $vals[] = $mas[$s]; }
        for ($s = 1; $s <= 28; $s++) { $cols[] = "`{$s}-`"; $vals[] = $menos[$s]; }

        $cols = array_merge($cols, ['`D+`','`I+`','`S+`','`C+`','`D-`','`I-`','`S-`','`C-`','D','I','S','C','codigo','pat_id']);
        $vals = array_merge($vals, [
            $plus['D'], $plus['I'], $plus['S'], $plus['C'],
            $minus['D'], $minus['I'], $minus['S'], $minus['C'],
            $diff['D'], $diff['I'], $diff['S'], $diff['C'],
            $codigo,
            $patId,
        ]);

        $ph = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO disc_res (" . implode(',', $cols) . ") VALUES ($ph)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
        $newId = (int)$pdo->lastInsertId();

        if ($idProspectoDocente > 0 && function_exists('docente_prospecto_vincular_disc')) {
            docente_prospecto_vincular_disc($pdo, $idProspectoDocente, $newId);
        }

        // Redirigir al dashboard para ver el resultado
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
            header('Content-Type: application/json; charset=utf-8');
            $payload = ['status' => 'ok', 'seccion' => 'resultado_disc', 'id' => $newId];
            if ($idProspectoDocente > 0) {
                $payload['seccion'] = 'docente_prospectos';
                $payload['message'] = 'DISC guardado para el prospecto docente';
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }
        header("Location: ../dashboard.php?seccion=resultado_disc&status=success&id=" . $newId);
        exit;

    } catch (PDOException $e) {
        die("Error al guardar: " . $e->getMessage());
    }
}