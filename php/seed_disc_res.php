<?php
/**
 * Seeder rápido: genera N resultados de prueba en disc_res para un user_id.
 * Uso (en navegador):
 *   php/seed_disc_res.php?user_id=3&n=10
 *
 * Recomendación: úsalo una vez y luego bórralo.
 */

require_once __DIR__ . '/../config.php';
global $pdo;

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 3;
$n = isset($_GET['n']) ? (int)$_GET['n'] : 8;
if ($userId <= 0) { http_response_code(400); die("user_id inválido"); }
if ($n <= 0 || $n > 50) { http_response_code(400); die("n inválido (1..50)"); }

function pickTwoDifferent(array $ids): array {
    if (count($ids) < 2) {
        throw new RuntimeException('Se requieren al menos 2 opciones');
    }
    $a = $ids[array_rand($ids)];
    do { $b = $ids[array_rand($ids)]; } while ($b === $a);
    return [$a, $b];
}

// Cargar palabras por sección
$stmt = $pdo->prepare("SELECT id, sec, mas, menos FROM disc_words ORDER BY sec ASC, ord ASC, id ASC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bySec = [];
$factById = [];
foreach ($rows as $r) {
    $sec = (int)$r['sec'];
    $id = (int)$r['id'];
    $bySec[$sec][] = $id;
    $factById[$id] = ['mas' => $r['mas'], 'menos' => $r['menos']];
}
for ($s = 1; $s <= 28; $s++) {
    if (empty($bySec[$s]) || count($bySec[$s]) !== 4) {
        http_response_code(500);
        die("Sección $s no tiene 4 palabras en disc_words.");
    }
}

$inserted = [];

for ($k = 0; $k < $n; $k++) {
    $mas = [];
    $menos = [];
    $plus = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
    $minus = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];

    for ($s = 1; $s <= 28; $s++) {
        [$idMas, $idMenos] = pickTwoDifferent($bySec[$s]);
        $mas[$s] = $idMas;
        $menos[$s] = $idMenos;

        $fMas = $factById[$idMas]['mas'];
        $fMenos = $factById[$idMenos]['menos'];

        if ($fMas !== 'N' && isset($plus[$fMas])) $plus[$fMas]++;
        if ($fMenos !== 'N' && isset($minus[$fMenos])) $minus[$fMenos]++;
    }

    $diff = [
        'D' => $plus['D'] - $minus['D'],
        'I' => $plus['I'] - $minus['I'],
        'S' => $plus['S'] - $minus['S'],
        'C' => $plus['C'] - $minus['C'],
    ];

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

    $cols = ['user_id'];
    $vals = [$userId];
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
    $inserted[] = (int)$pdo->lastInsertId();
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode([
    'status' => 'ok',
    'user_id' => $userId,
    'inserted_count' => count($inserted),
    'ids' => $inserted,
], JSON_UNESCAPED_UNICODE);

