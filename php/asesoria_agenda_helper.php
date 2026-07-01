<?php

/**
 * Agenda, slots, reservas y estados de asesorías.
 */

function asesoria_fecha_a_semana(PDO $pdo, string $fecha): array
{
    $st = $pdo->prepare('SELECT YEAR(?) AS anio, WEEK(?, 0) AS semana, DAYOFWEEK(?) - 1 AS dow');
    $st->execute([$fecha, $fecha, $fecha]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'anio' => (int) ($r['anio'] ?? date('Y', strtotime($fecha))),
        'semana' => (int) ($r['semana'] ?? 0),
        'dow' => (int) ($r['dow'] ?? 0),
    ];
}

function asesoria_slot_reservado(PDO $pdo, int $idPlantel, int $idProfesor, string $fecha, int $hora): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM asesoria_cita
         WHERE id_plantel = ? AND id_profesor = ? AND fecha = ? AND hora_inicio = ?
           AND estado IN (\'agendada\',\'confirmada\',\'impartida\',\'np\')
         LIMIT 1'
    );
    $st->execute([$idPlantel, $idProfesor, $fecha, $hora]);

    return (bool) $st->fetchColumn();
}

function asesoria_profesor_disponible_slot(PDO $pdo, int $idPlantel, int $idProfesor, string $fecha, int $hora): bool
{
    if (asesoria_slot_reservado($pdo, $idPlantel, $idProfesor, $fecha, $hora)) {
        return false;
    }
    $w = asesoria_fecha_a_semana($pdo, $fecha);
    $st = $pdo->prepare(
        'SELECT disponible FROM asesoria_disp
         WHERE id_plantel = ? AND id_profesor = ? AND anio = ? AND semana = ? AND dow = ? AND hora = ?
         LIMIT 1'
    );
    $st->execute([$idPlantel, $idProfesor, $w['anio'], $w['semana'], $w['dow'], $hora]);
    $disp = $st->fetchColumn();

    return $disp !== false && (int) $disp === 1;
}

function asesoria_profesor_puede_materia(PDO $pdo, int $idProfesor, int $idPlantel, string $materiaClave, ?int $idEspecialidad = null, bool $kidsDual = false): bool
{
    asesoria_ensure_schema($pdo);
    $sql = 'SELECT 1 FROM profesor_asesoria_materia
            WHERE id_usuario = ? AND id_plantel = ? AND activo = 1
              AND (materia_clave = ? OR materia_clave = \'\')';
    $params = [$idProfesor, $idPlantel, $materiaClave];
    if ($idEspecialidad > 0) {
        $sql .= ' AND (id_especialidad IS NULL OR id_especialidad = ?)';
        $params[] = $idEspecialidad;
    }
    if ($kidsDual) {
        $sql .= ' AND puede_kids_dual = 1';
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if ($st->fetchColumn()) {
        return true;
    }
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM profesor_asesoria_materia WHERE id_usuario = ? AND id_plantel = ? AND activo = 1');
    $cnt->execute([$idProfesor, $idPlantel]);

    return (int) $cnt->fetchColumn() === 0;
}

function asesoria_profesores_para_materia(PDO $pdo, int $idPlantel, string $materiaClave, ?int $idEspecialidad = null, bool $kidsDual = false): array
{
    asesoria_ensure_schema($pdo);
    $sql = 'SELECT DISTINCT u.id_usuario, CONCAT(u.nombre, \' \', u.apellido) AS nombre
            FROM usuarios u
            INNER JOIN profesor_asesoria_materia pam ON pam.id_usuario = u.id_usuario AND pam.id_plantel = ?
            WHERE u.suspendido = 0 AND pam.activo = 1
              AND (pam.materia_clave = ? OR pam.materia_clave = \'\')';
    $params = [$idPlantel, $materiaClave];
    if ($idEspecialidad > 0) {
        $sql .= ' AND (pam.id_especialidad IS NULL OR pam.id_especialidad = ?)';
        $params[] = $idEspecialidad;
    }
    if ($kidsDual) {
        $sql .= ' AND pam.puede_kids_dual = 1';
    }
    $sql .= ' ORDER BY u.nombre, u.apellido';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows !== []) {
        return $rows;
    }
    $st2 = $pdo->prepare(
        "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) AS nombre
         FROM usuarios u
         WHERE u.id_plantel = ? AND u.rol = 'profesor' AND u.suspendido = 0
         ORDER BY u.nombre, u.apellido"
    );
    $st2->execute([$idPlantel]);

    return $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function asesoria_slots_disponibles(PDO $pdo, int $idPlantel, int $idProfesor, string $desde, string $hasta): array
{
    $out = [];
    $d = strtotime($desde);
    $fin = strtotime($hasta);
    while ($d <= $fin) {
        $fecha = date('Y-m-d', $d);
        for ($h = 8; $h <= 20; $h++) {
            if (asesoria_profesor_disponible_slot($pdo, $idPlantel, $idProfesor, $fecha, $h)) {
                $out[] = ['fecha' => $fecha, 'hora' => $h];
            }
        }
        $d = strtotime('+1 day', $d);
    }

    return $out;
}

function asesoria_agendar(PDO $pdo, array $data, int $idUsuario): array
{
    if (!asesoria_puede_agendar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    asesoria_ensure_schema($pdo);
    $idPlantel = (int) ($data['id_plantel'] ?? plantel_id_activo());
    $idAlumno = (int) ($data['id_alumno'] ?? 0);
    $idProfesor = (int) ($data['id_profesor'] ?? 0);
    $fecha = trim((string) ($data['fecha'] ?? ''));
    $hora = (int) ($data['hora_inicio'] ?? 0);
    $tipo = trim((string) ($data['tipo'] ?? 'pagada_materia'));
    $tema = trim((string) ($data['tema'] ?? ''));
    $materiaClave = trim((string) ($data['materia_clave'] ?? ''));
    $idEsp = (int) ($data['id_especialidad'] ?? 0);
    $idGrupo = (int) ($data['id_grupo'] ?? 0);
    $mismoTema = !empty($data['mismo_tema']);
    $moodleOk = !empty($data['moodle_verificado']);
    $autorizadoHoy = !empty($data['autorizar_mismo_dia']) && asesoria_puede_autorizar_mismo_dia();
    $idCitaExistente = (int) ($data['id_cita_agrupar'] ?? 0);
    $formaPago = trim((string) ($data['forma_pago'] ?? 'Efectivo'));

    if ($idAlumno <= 0 || $idProfesor <= 0 || $fecha === '' || $hora < 8 || $hora > 20) {
        return ['ok' => false, 'message' => 'Datos incompletos'];
    }
    if ($tema === '') {
        return ['ok' => false, 'message' => 'Indique el tema'];
    }
    if (!asesoria_fecha_permitida($fecha, $autorizadoHoy)) {
        return ['ok' => false, 'message' => 'No se puede agendar el mismo día sin autorización de coordinación'];
    }

    $elig = asesoria_alumno_elegible($pdo, $idAlumno, $idPlantel);
    if (!$elig['ok']) {
        return $elig;
    }

    if ($tipo === 'falta_gratis' && asesoria_alumno_en_personalizado($pdo, $idAlumno)) {
        return ['ok' => false, 'message' => 'Personalizado no tiene derecho a asesoría por falta'];
    }
    if ($tipo === 'falta_gratis' && $idEsp > 0 && asesoria_especialidad_requiere_moodle($pdo, $idEsp) && !$moodleOk) {
        if (!asesoria_moodle_verificar_alumno($pdo, $idAlumno, $idGrupo, $data['semana_falta'] ?? null)) {
            return ['ok' => false, 'message' => 'Debe completar actividades Moodle de la semana antes de agendar (o marque verificado manualmente)'];
        }
        $moodleOk = true;
    }

    $maxAl = asesoria_max_alumnos($mismoTema);
    if ($tipo === 'regularizacion' && asesoria_credito_tiene_individual($pdo, $idAlumno, $idPlantel)) {
        $maxAl = 1;
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $idCita = $idCitaExistente;
        if ($idCita > 0) {
            $cita = asesoria_cita_obtener($pdo, $idCita, $idPlantel);
            if (!$cita || (int) asesoria_cita_num_alumnos($pdo, $idCita) >= (int) $cita['max_alumnos']) {
                throw new RuntimeException('La cita grupal ya está llena');
            }
            if (!$mismoTema && (int) $cita['max_alumnos'] > 2) {
                throw new RuntimeException('Temas distintos: máximo 2 alumnos');
            }
        } else {
            if (!asesoria_profesor_disponible_slot($pdo, $idPlantel, $idProfesor, $fecha, $hora)) {
                throw new RuntimeException('El profesor no tiene disponibilidad en ese horario');
            }
            if (!asesoria_profesor_puede_materia($pdo, $idProfesor, $idPlantel, $materiaClave, $idEsp, $tipo === 'kids_dual')) {
                throw new RuntimeException('El profesor no está autorizado para esta materia');
            }
            $pdo->prepare(
                'INSERT INTO asesoria_cita (
                    id_plantel, fecha, hora_inicio, id_profesor, materia_clave, id_especialidad, tema, tipo,
                    max_alumnos, mismo_tema, moodle_verificado, id_usuario_agenda, id_autorizacion_mismo_dia, estado
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $idPlantel, $fecha, $hora, $idProfesor, $materiaClave, $idEsp ?: null, $tema, $tipo,
                $maxAl, $mismoTema ? 1 : 0, $moodleOk ? 1 : 0, $idUsuario,
                $autorizadoHoy ? $idUsuario : null, 'agendada',
            ]);
            $idCita = (int) $pdo->lastInsertId();
        }

        $costo = asesoria_calcular_cobro_alumno($pdo, $tipo, false, $idEsp ?: null, $idPlantel);
        $idCredito = null;
        if ($tipo === 'regularizacion') {
            $idCredito = asesoria_credito_consumir($pdo, $idAlumno, 1.0, $idPlantel);
            if ($idCredito === null) {
                throw new RuntimeException('Sin créditos de regularización disponibles');
            }
            $costo = 0;
        }

        $idPago = null;
        if ($costo > 0.01) {
            $resPago = pago_registrar($pdo, [
                'id_alumno' => $idAlumno,
                'monto' => $costo,
                'tipo' => 'asesoria',
                'concepto' => 'Asesoría — ' . $tema,
                'forma_pago_efectivo' => $formaPago,
                'id_especialidad' => $idEsp ?: null,
                'id_usuario' => $idUsuario,
            ]);
            if (!$resPago['ok']) {
                throw new RuntimeException($resPago['message'] ?? 'Error al registrar pago');
            }
            $idPago = (int) ($resPago['id_pago'] ?? 0);
        }

        $pdo->prepare(
            'INSERT INTO asesoria_cita_alumno (id_cita, id_alumno, id_grupo, costo, id_credito, id_pago, estado_cobro)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $idCita, $idAlumno, $idGrupo ?: null, $costo, $idCredito, $idPago,
            $costo > 0.01 ? 'pagado' : 'gratis',
        ]);

        $pdo->prepare(
            'UPDATE asesoria_cita SET costo_total_alumnos = costo_total_alumnos + ? WHERE id_cita = ?'
        )->execute([$costo, $idCita]);

        if ($ownTx) {
            $pdo->commit();
        }

        return ['ok' => true, 'message' => 'Asesoría agendada', 'id_cita' => $idCita];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function asesoria_cambiar_estado(PDO $pdo, int $idCita, string $nuevoEstado, array $opts = []): array
{
    if (!asesoria_puede_agendar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $idPlantel = (int) ($opts['id_plantel'] ?? plantel_id_activo());
    $cita = asesoria_cita_obtener($pdo, $idCita, $idPlantel);
    if (!$cita) {
        return ['ok' => false, 'message' => 'Cita no encontrada'];
    }
    $estadosValidos = array_keys(ASESORIA_ESTADOS);
    if (!in_array($nuevoEstado, $estadosValidos, true)) {
        return ['ok' => false, 'message' => 'Estado inválido'];
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $sql = 'UPDATE asesoria_cita SET estado = ?';
        if ($nuevoEstado === 'cancelada_a_tiempo') {
            $sql .= ', cancelada_en = NOW(), motivo_cancelacion = ?';
            $params = [$nuevoEstado, $opts['motivo'] ?? 'Cancelación a tiempo', $idCita];
        } else {
            $params = [$nuevoEstado, $idCita];
        }
        if ($nuevoEstado === 'confirmada') {
            $sql .= ', confirmada_recepcion_en = NOW()';
        }
        $sql .= ' WHERE id_cita = ?';
        $pdo->prepare($sql)->execute($params);

        if ($nuevoEstado === 'impartida' || $nuevoEstado === 'np') {
            $presentes = $nuevoEstado === 'impartida'
                ? (int) ($opts['num_presentes'] ?? asesoria_cita_num_alumnos($pdo, $idCita))
                : 0;
            if ($nuevoEstado === 'impartida') {
                $pdo->prepare('UPDATE asesoria_cita_alumno SET asistio = 1 WHERE id_cita = ?')->execute([$idCita]);
            }
            if ($nuevoEstado === 'np') {
                $pdo->prepare('UPDATE asesoria_cita_alumno SET asistio = 0 WHERE id_cita = ?')->execute([$idCita]);
                foreach (asesoria_cita_alumnos($pdo, $idCita) as $ca) {
                    $cargo = asesoria_calcular_cobro_alumno($pdo, (string) $cita['tipo'], true, (int) ($cita['id_especialidad'] ?? 0), $idPlantel);
                    if ($cargo > 0.01) {
                        pago_registrar($pdo, [
                            'id_alumno' => (int) $ca['id_alumno'],
                            'monto' => $cargo,
                            'tipo' => 'asesoria',
                            'concepto' => 'Reagendar asesoría (NP) — ' . ($cita['tema'] ?? ''),
                            'forma_pago_efectivo' => $opts['forma_pago'] ?? 'Efectivo',
                            'id_usuario' => $_SESSION['user_id'] ?? null,
                        ]);
                    }
                }
            }
            asesoria_registrar_pago_profesor($pdo, $cita, $nuevoEstado, $presentes);
        }

        if ($ownTx) {
            $pdo->commit();
        }

        return ['ok' => true, 'message' => 'Estado actualizado'];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function asesoria_registrar_pago_profesor(PDO $pdo, array $cita, string $estado, int $numPresentes): void
{
    $idCita = (int) $cita['id_cita'];
    $st = $pdo->prepare('SELECT 1 FROM asesoria_pago_profesor WHERE id_cita = ? LIMIT 1');
    $st->execute([$idCita]);
    if ($st->fetchColumn()) {
        return;
    }
    $importe = asesoria_calcular_pago_profesor(
        $pdo,
        $estado,
        $numPresentes,
        (int) ($cita['mismo_tema'] ?? 1) === 1,
        (int) $cita['id_plantel']
    );
    if ($importe < 0.01) {
        return;
    }
    $concepto = 'Asesoría ' . ($cita['fecha'] ?? '') . ' ' . ($cita['hora_inicio'] ?? '') . 'h — ' . ASESORIA_ESTADOS[$estado];
    $pdo->prepare(
        'INSERT INTO asesoria_pago_profesor (id_cita, id_profesor, concepto, importe) VALUES (?,?,?,?)'
    )->execute([$idCita, (int) $cita['id_profesor'], $concepto, $importe]);
}

function asesoria_cancelar_a_tiempo(PDO $pdo, int $idCita, string $motivo = ''): array
{
    $idPlantel = plantel_id_activo();
    $cita = asesoria_cita_obtener($pdo, $idCita, $idPlantel);
    if (!$cita) {
        return ['ok' => false, 'message' => 'No encontrada'];
    }
    $inicio = strtotime($cita['fecha'] . ' ' . str_pad((string) $cita['hora_inicio'], 2, '0', STR_PAD_LEFT) . ':00:00');
    $horas = ($inicio - time()) / 3600;
    if ($horas < asesoria_horas_cancelacion_gratis()) {
        return ['ok' => false, 'message' => 'Cancelación debe ser al menos ' . asesoria_horas_cancelacion_gratis() . ' horas antes'];
    }

    return asesoria_cambiar_estado($pdo, $idCita, 'cancelada_a_tiempo', ['motivo' => $motivo, 'id_plantel' => $idPlantel]);
}

function profesor_asesoria_materia_listar(PDO $pdo, int $idUsuario, ?int $idPlantel = null): array
{
    asesoria_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT pam.*, e.nombre AS esp_nombre FROM profesor_asesoria_materia pam
         LEFT JOIN especialidades e ON e.id_especialidad = pam.id_especialidad
         WHERE pam.id_usuario = ? AND pam.id_plantel = ?
         ORDER BY pam.materia_nombre, pam.nivel'
    );
    $st->execute([$idUsuario, $idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function profesor_asesoria_materia_guardar(PDO $pdo, int $idUsuario, array $items, ?int $idPlantel = null): array
{
    if (!asesoria_puede_administrar() && !asesoria_puede_agendar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    asesoria_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $pdo->prepare('DELETE FROM profesor_asesoria_materia WHERE id_usuario = ? AND id_plantel = ?')
        ->execute([$idUsuario, $idPlantel]);
    $ins = $pdo->prepare(
        'INSERT INTO profesor_asesoria_materia (
            id_usuario, id_plantel, id_especialidad, materia_clave, materia_nombre, nivel, puede_kids_dual, activo
         ) VALUES (?,?,?,?,?,?,?,1)'
    );
    foreach ($items as $it) {
        $clave = trim((string) ($it['materia_clave'] ?? ''));
        $nombre = trim((string) ($it['materia_nombre'] ?? $clave));
        if ($nombre === '' && $clave === '') {
            continue;
        }
        $ins->execute([
            $idUsuario,
            $idPlantel,
            !empty($it['id_especialidad']) ? (int) $it['id_especialidad'] : null,
            $clave,
            $nombre ?: 'General',
            trim((string) ($it['nivel'] ?? 'general')),
            !empty($it['puede_kids_dual']) ? 1 : 0,
        ]);
    }

    return ['ok' => true, 'message' => 'Materias guardadas'];
}

/** Verificación Moodle — manual v1 + API v2. */
function asesoria_moodle_verificar_alumno(PDO $pdo, int $idAlumno, int $idGrupo, ?string $semanaFalta): bool
{
    if ($idGrupo <= 0) {
        return false;
    }
    if (!function_exists('asesoria_moodle_actividades_completadas')) {
        return false;
    }

    return asesoria_moodle_actividades_completadas($pdo, $idAlumno, $idGrupo, $semanaFalta);
}

function asesoria_citas_sugeridas_agrupar(PDO $pdo, int $idPlantel, int $idGrupo, string $tema, string $desde, string $hasta): array
{
    return asesoria_listar($pdo, $idPlantel, [
        'desde' => $desde,
        'hasta' => $hasta,
        'activas' => true,
    ], 30);
}
