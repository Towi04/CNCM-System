<?php
/**
 * Tour guiado por rol y vista (asistente de primer uso).
 */

function tour_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $path = dirname(__DIR__) . '/sql/migrations/016_tour_gerente_marketing.sql';
    if (!is_file($path)) {
        return;
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        return;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // tabla ya existe
        }
    }
}

function tour_rol_actual(): string
{
    return function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : (string) ($_SESSION['rol'] ?? 'staff');
}

/** @return list<array{selector?:string,title:string,body:string,placement?:string}> */
function tour_pasos_para(string $tourKey): array
{
    $rol = tour_rol_actual();
    $pasos = [
        'inicio' => [
            ['selector' => '#sidebar-user-trigger', 'title' => 'Tu perfil', 'body' => 'Desde aquí accedes a tu perfil, cambio de contraseña y reactivar este tour.', 'placement' => 'right'],
            ['selector' => '.sidebar-menu', 'title' => 'Menú principal', 'body' => 'Las opciones del menú dependen de tu rol. Usa las secciones para navegar.', 'placement' => 'right'],
            ['selector' => '#main-content', 'title' => 'Área de trabajo', 'body' => 'Aquí se cargan las pantallas sin recargar la página.', 'placement' => 'left'],
        ],
        'gerente_dashboard' => [
            ['title' => 'Panel gerente', 'body' => 'Resumen de captación: entrevistas, pre-registros, inscritos y podio de asesores.'],
            ['selector' => '#gerente-podio', 'title' => 'Podio semanal', 'body' => 'Comparativa motivacional entre asesores. Los asesores también pueden ver este ranking.'],
        ],
        'alumno_portal' => [
            ['title' => 'Portal alumno', 'body' => 'Consulta calificaciones, pagos, cursos Moodle, mensajes y avisos de tus maestros.'],
            ['selector' => '.ap-portal-grid', 'title' => 'Accesos rápidos', 'body' => 'Calificaciones, pagos, Moodle, cuentas digitales, mensajes con tu grupo o coordinación, promociones y tu perfil.'],
            ['selector' => '.sidebar-menu', 'title' => 'Menú alumno', 'body' => 'Mismas secciones en el menú lateral: Inicio, calificaciones, cursos, pagos, mensajes y soporte.'],
        ],
    ];

    if ($rol === 'gerente' && $tourKey === 'inicio') {
        $pasos['inicio'][] = [
            'title' => 'Rol gerente',
            'body' => 'Tienes acceso a reportes de captación, seguimiento de pendientes de todos los asesores y designación de cartas para nómina.',
        ];
    }

    return $pasos[$tourKey] ?? [];
}

function tour_completado(PDO $pdo, int $idUsuario, string $tourKey): bool
{
    tour_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT completado FROM usuario_tour WHERE id_usuario = ? AND tour_key = ? LIMIT 1'
    );
    $st->execute([$idUsuario, $tourKey]);

    return (int) $st->fetchColumn() === 1;
}

function tour_marcar(PDO $pdo, int $idUsuario, string $tourKey, bool $completado = true): void
{
    tour_ensure_schema($pdo);
    $pdo->prepare(
        'INSERT INTO usuario_tour (id_usuario, tour_key, completado, completado_en)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE completado = VALUES(completado), completado_en = VALUES(completado_en)'
    )->execute([
        $idUsuario,
        $tourKey,
        $completado ? 1 : 0,
        $completado ? date('Y-m-d H:i:s') : null,
    ]);
}

/** Tours pendientes al cargar una sección. */
function tour_pendiente_para_seccion(PDO $pdo, int $idUsuario, string $seccion): ?string
{
    if ($idUsuario <= 0) {
        return null;
    }
    $map = [
        'inicio_panel' => 'inicio',
        'gerente_dashboard' => 'gerente_dashboard',
        'alumno_portal_inicio' => 'alumno_portal',
    ];
    $key = $map[$seccion] ?? null;
    if ($key === null) {
        return null;
    }
    if (tour_completado($pdo, $idUsuario, $key)) {
        return null;
    }

    return $key;
}

function tour_reset_usuario(PDO $pdo, int $idUsuario): void
{
    tour_ensure_schema($pdo);
    $pdo->prepare('DELETE FROM usuario_tour WHERE id_usuario = ?')->execute([$idUsuario]);
}
