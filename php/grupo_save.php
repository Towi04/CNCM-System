<?php

require_once __DIR__ . '/../config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id'])) {

    header('Location: ../index.php');

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ../dashboard.php?seccion=grupos');

    exit;

}



$fecha = trim($_POST['fecha_inicio'] ?? '');

$idProfesor = (int) ($_POST['id_profesor'] ?? 0) ?: null;
$asignacionesDocentes = grupo_docente_parse_post($_POST);
if ($asignacionesDocentes !== []) {
    foreach ($asignacionesDocentes as $a) {
        if (!empty($a['es_titular']) || $idProfesor === null) {
            $idProfesor = (int) $a['id_profesor'];
            if (!empty($a['es_titular'])) {
                break;
            }
        }
    }
}

$idEsp = (int) ($_POST['id_especialidad'] ?? 0) ?: null;

$grupoAvanzado = !empty($_POST['grupo_avanzado']);

$idFasePost = (int) ($_POST['id_fase_actual'] ?? 0) ?: null;

$horaInicio = trim($_POST['hora_inicio'] ?? '') ?: '08:00:00';

$horaFin = trim($_POST['hora_fin'] ?? '') ?: '10:00:00';

if (strlen($horaInicio) === 5) {

    $horaInicio .= ':00';

}

if (strlen($horaFin) === 5) {

    $horaFin .= ':00';

}

$diasPost = $_POST['dia_semana'] ?? [];



if ($fecha === '') {

    header('Location: ../dashboard.php?seccion=grupo_nuevo&error=1');

    exit;

}



$idPlantel = plantel_scope_id($pdo);

grupo_clave_ensure_schema($pdo);

asistencia_ensure_schema($pdo);



if ($idProfesor > 0 && !plantel_usuario_pertenece($pdo, $idProfesor, $idPlantel)) {

    header('Location: ../dashboard.php?seccion=grupo_nuevo&error=profesor');

    exit;

}



$clave = trim($_POST['clave'] ?? '');

$codigoArea = strtoupper(trim($_POST['codigo_area'] ?? 'I'));

$codigoHorario = strtoupper(trim($_POST['codigo_horario'] ?? 'S'));

$tipoGrupo = $_POST['tipo_grupo'] ?? 'regular';

$extensivo = $tipoGrupo === 'extensivo' ? 1 : 0;

$personalizado = $tipoGrupo === 'personalizado' ? 1 : 0;

$nombrePer = trim($_POST['nombre_personalizado'] ?? '');

$numSec = null;

$diasSemana = [];
if (!$personalizado) {
    if ($codigoArea === 'K') {
        if ($codigoHorario === 'S') {
            $diasSemana = [6];
        } elseif ($codigoHorario === 'D') {
            $diasSemana = [0];
        } else {
            if (!is_array($diasPost)) {
                $diasPost = [$diasPost];
            }
            foreach ($diasPost as $d) {
                $d = (int) $d;
                if ($d >= 1 && $d <= 5) {
                    $diasSemana[] = $d;
                }
            }
            if ($diasSemana === [] && ($codigoHorario === 'M' || $codigoHorario === 'V')) {
                header('Location: ../dashboard.php?seccion=grupo_nuevo&error=dias');
                exit;
            }
            if ($diasSemana === [] && $codigoHorario !== 'M' && $codigoHorario !== 'V') {
                $diasSemana = $codigoHorario === 'D' ? [0] : [6];
            }
        }
    } elseif ($codigoHorario === 'S') {
        $diasSemana = [6];
    } elseif ($codigoHorario === 'D') {
        $diasSemana = [0];
    } else {
        if (!is_array($diasPost)) {
            $diasPost = [$diasPost];
        }
        foreach ($diasPost as $d) {
            $d = (int) $d;
            if ($d >= 1 && $d <= 5) {
                $diasSemana[] = $d;
            }
        }
        if ($diasSemana === [] && ($codigoHorario === 'M' || $codigoHorario === 'V')) {
            header('Location: ../dashboard.php?seccion=grupo_nuevo&error=dias');
            exit;
        }
    }
}

$horarioTexto = $diasSemana !== []
    ? grupo_horario_texto_desde_dias($diasSemana, $horaInicio, $horaFin)
    : null;

if ($idEsp <= 0 && !$personalizado) {

    $idEsp = (int) (grupo_area_id_especialidad($pdo, $codigoArea) ?? 0);

}



if (!empty($_POST['generar_clave'])) {

    if ($codigoArea === 'K' && !$personalizado && !$extensivo) {

        try {

            $idFaseIng = null;

            $idFaseComp = null;

            if ($grupoAvanzado && $idFasePost > 0) {

                $idFaseIng = $idFasePost;

            }

            $resPareja = grupo_infantil_crear_pareja(

                $pdo,

                $idPlantel,

                $fecha,

                $idProfesor,

                $idFaseIng,

                $idFaseComp,

                $diasSemana,

                $horaInicio,

                $horaFin,

                $horarioTexto

            );

            if (!$resPareja['ok']) {

                throw new RuntimeException($resPareja['message'] ?? 'No se pudo crear la pareja infantil');

            }

            if (function_exists('grupo_apertura_inicializar')) {
                $minAlumnos = (int) ($_POST['min_alumnos'] ?? 0) ?: null;
                grupo_apertura_inicializar($pdo, (int) ($resPareja['id_grupo_ingles'] ?? 0), $minAlumnos);
                grupo_apertura_inicializar($pdo, (int) ($resPareja['id_grupo_computacion'] ?? 0), $minAlumnos);
            }

            if ($asignacionesDocentes !== []) {
                foreach (['id_grupo_ingles', 'id_grupo_computacion'] as $k) {
                    $idG = (int) ($resPareja[$k] ?? 0);
                    if ($idG > 0) {
                        grupo_docente_guardar($pdo, $idG, $idPlantel, $asignacionesDocentes);
                    }
                }
            } elseif ($idProfesor > 0) {
                foreach (['id_grupo_ingles', 'id_grupo_computacion'] as $k) {
                    $idG = (int) ($resPareja[$k] ?? 0);
                    if ($idG > 0) {
                        grupo_docente_guardar($pdo, $idG, $idPlantel, [[
                            'id_profesor' => $idProfesor,
                            'materia_clave' => '',
                            'materia_nombre' => 'General',
                            'es_titular' => true,
                        ]]);
                    }
                }
            }

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {

                header('Content-Type: application/json; charset=utf-8');

                echo json_encode([

                    'status' => 'ok',

                    'seccion' => 'grupos',

                    'clave' => ($resPareja['clave_ingles'] ?? '') . ' + ' . ($resPareja['clave_computacion'] ?? ''),

                    'pareja_infantil' => $resPareja,

                ], JSON_UNESCAPED_UNICODE);

                exit;

            }

            header('Location: ../dashboard.php?seccion=grupos&ok=1&msg=' . urlencode(

                'Pareja infantil creada: ' . ($resPareja['clave_ingles'] ?? '') . ' y ' . ($resPareja['clave_computacion'] ?? '')

            ));

            exit;

        } catch (Throwable $e) {

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {

                header('Content-Type: application/json; charset=utf-8');

                echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

                exit;

            }

            header('Location: ../dashboard.php?seccion=grupo_nuevo&error=clave');

            exit;

        }

    }

    try {

        $gen = grupo_clave_generar(

            $pdo,

            $idPlantel,

            $codigoArea,

            $codigoHorario,

            (bool) $extensivo,

            (bool) $personalizado,

            $nombrePer

        );

        $clave = $gen['clave'];

        $codigoArea = $gen['codigo_area'];

        $codigoHorario = $gen['codigo_horario'] ?: $codigoHorario;

        $numSec = $gen['numero_secuencial'] ?: null;

    } catch (Throwable $e) {

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {

            header('Content-Type: application/json; charset=utf-8');

            echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

            exit;

        }

        header('Location: ../dashboard.php?seccion=grupo_nuevo&error=clave');

        exit;

    }

}



if ($clave === '') {

    header('Location: ../dashboard.php?seccion=grupo_nuevo&error=1');

    exit;

}



$idFase = null;

if ($idEsp > 0) {

    if ($grupoAvanzado) {

        if ($idFasePost <= 0) {

            header('Location: ../dashboard.php?seccion=grupo_nuevo&error=fase');

            exit;

        }

        $idFase = $idFasePost;

    } else {

        $idFase = grupo_primera_fase($pdo, $idEsp);

    }

}



try {

    $stmt = $pdo->prepare(

        'INSERT INTO grupos (

            id_plantel, clave, fecha_inicio, id_profesor, aula, id_especialidad, id_fase_actual,

            moodle_nivel, horario_texto, codigo_area, codigo_horario, es_extensivo, es_personalizado, numero_secuencial

        ) VALUES (?, ?, ?, ?, NULL, ?, ?, NULL, ?, ?, ?, ?, ?, ?)'

    );

    $stmt->execute([

        $idPlantel,

        $clave,

        $fecha,

        $idProfesor,

        $idEsp ?: null,

        $idFase,

        $horarioTexto,

        $personalizado ? 'PER' : $codigoArea,

        $personalizado ? null : $codigoHorario,

        $extensivo,

        $personalizado,

        $numSec,

    ]);

    $idGrupo = (int) $pdo->lastInsertId();

    if ($idGrupo > 0 && function_exists('tutor_asignar_grupo')) {
        tutor_asignar_grupo($pdo, $idGrupo);
    }

    if ($idGrupo > 0 && function_exists('grupo_apertura_inicializar')) {
        $minAlumnos = (int) ($_POST['min_alumnos'] ?? 0) ?: null;
        grupo_apertura_inicializar($pdo, $idGrupo, $minAlumnos);
    }

    if ($idGrupo > 0 && $diasSemana !== []) {

        grupo_guardar_horarios($pdo, $idGrupo, $diasSemana, $horaInicio, $horaFin);

    }

    if ($idGrupo > 0 && $asignacionesDocentes !== []) {
        grupo_docente_guardar($pdo, $idGrupo, $idPlantel, $asignacionesDocentes);
    } elseif ($idGrupo > 0 && $idProfesor > 0) {
        grupo_docente_guardar($pdo, $idGrupo, $idPlantel, [[
            'id_profesor' => $idProfesor,
            'materia_clave' => '',
            'materia_nombre' => 'General',
            'es_titular' => true,
        ]]);
    }



    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(['status' => 'ok', 'seccion' => 'grupos', 'clave' => $clave], JSON_UNESCAPED_UNICODE);

        exit;

    }

    header('Location: ../dashboard.php?seccion=grupos&ok=1');

    exit;

} catch (PDOException $e) {

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

        exit;

    }

    header('Location: ../dashboard.php?seccion=grupo_nuevo&error=bd');

    exit;

}

