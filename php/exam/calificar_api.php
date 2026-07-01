<?php
require_once __DIR__ . '/bootstrap.php';

use HayExam\AnswerSheetLayout;
use HayExam\AnswerSheetService;

exam_require_session();

global $pdo;
$svc = new AnswerSheetService($pdo, dirname(__DIR__, 2));
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'layout':
            exam_json_response(['status' => 'ok', 'layout' => AnswerSheetLayout::buildLayout()]);

        case 'buscar_examen':
            $id = trim($_GET['id_examen'] ?? $_POST['id_examen'] ?? '');
            if ($id === '') {
                throw new InvalidArgumentException('Escriba el código del examen.');
            }
            $exam = $svc->buscarExamen($id);
            if (!$exam) {
                exam_json_response(['status' => 'error', 'message' => 'No se encontró examen con código «' . $id . '».'], 404);
            }
            exam_json_response(['status' => 'ok', 'examen' => $exam]);

        case 'config':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $cfg = $svc->getPlantelConfig();
                $digitos = (int)($_POST['digitos_control'] ?? $cfg['digitos_control']);
                $svc->guardarPlantelConfig(
                    $digitos,
                    (float)($_POST['peso_mc'] ?? $cfg['peso_mc']),
                    (float)($_POST['peso_writing'] ?? $cfg['peso_writing']),
                    (float)($_POST['peso_speaking'] ?? $cfg['peso_speaking'])
                );
                exam_json_response(['status' => 'ok', 'message' => 'Configuración guardada.', 'config' => $svc->getPlantelConfig()]);
            }
            exam_json_response(['status' => 'ok', 'config' => $svc->getPlantelConfig()]);

        case 'examenes':
            exam_json_response(['status' => 'ok', 'examenes' => $svc->listarExamenes(40)]);

        case 'clave':
            $id = trim($_GET['id_examen'] ?? '');
            if ($id === '') {
                throw new InvalidArgumentException('id_examen requerido.');
            }
            exam_json_response(['status' => 'ok', ...$svc->claveExamen($id)]);

        case 'calificaciones':
            $id = trim($_GET['id_examen'] ?? '');
            if ($id === '') {
                throw new InvalidArgumentException('id_examen requerido.');
            }
            exam_json_response(['status' => 'ok', 'items' => $svc->listarCalificaciones($id)]);

        case 'grupos':
            exam_json_response(['status' => 'ok', 'grupos' => $svc->listarGruposCalificar()]);

        case 'alumnos_grupo':
            $idGrupo = (int) ($_GET['id_grupo'] ?? 0);
            $idExamen = trim($_GET['id_examen'] ?? '');
            if ($idGrupo <= 0) {
                throw new InvalidArgumentException('id_grupo requerido.');
            }
            exam_json_response([
                'status' => 'ok',
                'alumnos' => $svc->listarAlumnosGrupo($idGrupo, $idExamen !== '' ? $idExamen : null),
            ]);

        case 'generar_hoja':
            $path = $svc->generarHojaUniversal();
            exam_json_response([
                'status' => 'ok',
                'url' => $path,
                'download' => 'php/exam/descargar.php?tipo=hoja',
            ]);

        case 'procesar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Método no permitido.');
            }
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                $body = $_POST;
            }
            $idExamen = trim((string)($body['id_examen'] ?? ''));
            if ($idExamen === '') {
                throw new InvalidArgumentException('id_examen requerido.');
            }
            $result = $svc->registrarEscaneo($idExamen, $body, (int)($_SESSION['user_id'] ?? 0) ?: null);
            exam_json_response(['status' => 'ok', 'message' => 'Calificación registrada.', 'resultado' => $result]);

        default:
            exam_json_response(['status' => 'error', 'message' => 'Acción no válida.'], 400);
    }
} catch (Throwable $e) {
    exam_json_response(['status' => 'error', 'message' => $e->getMessage()], 400);
}
