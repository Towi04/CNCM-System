<?php
require_once __DIR__ . '/php/session_helper.php';
hay_session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=sesion');
    exit();
}
/** Migraciones solo al entrar al panel (no en login). */
define('HAY_RUN_SCHEMA_BOOTSTRAP', true);
require_once __DIR__ . '/config.php';
hay_html_utf8_header();

if (function_exists('rbac_sincronizar_sesion_usuario')) {
    rbac_sincronizar_sesion_usuario($pdo);
} elseif (function_exists('rbac_db_cargar_sesion_rol')) {
    rbac_db_cargar_sesion_rol($pdo, rbac_rol_efectivo());
    if (function_exists('rbac_supervisor_aplicar_sesion')) {
        rbac_supervisor_aplicar_sesion();
    }
}
plantel_inicializar_sesion($pdo);
user_avatar_refresh_session($pdo, (int) $_SESSION['user_id']);

// Liberar lock antes del HTML para que las vistas AJAX no queden en cola.
if (function_exists('hay_session_release_lock')) {
    hay_session_release_lock();
}

$plantelSlug = (string) ($_SESSION['plantel_slug'] ?? '');
$plantelFondoUrl = plantel_fondo_imagen($plantelSlug);
$plantelFondoClases = plantel_fondo_clases($plantelSlug);
$debeAceptarAcuerdo = !empty($_SESSION['debe_cambiar_password']) ? false : (
    function_exists('alumno_debe_aceptar_acuerdo')
    && alumno_debe_aceptar_acuerdo($pdo, (int) $_SESSION['user_id'])
);
$debeCompletarPerfil = !$debeAceptarAcuerdo && function_exists('alumno_debe_completar_perfil')
    && alumno_debe_completar_perfil($pdo, (int) $_SESSION['user_id']);
$haySuspensionPortal = '';
if (!empty($_SESSION['user_id']) && function_exists('usuario_por_id')) {
    $uDash = usuario_por_id($pdo, (int) $_SESSION['user_id']);
    if ($uDash && function_exists('usuario_suspension_aplicar_sesion')) {
        usuario_suspension_aplicar_sesion($uDash);
    }
    $haySuspensionPortal = (string) ($_SESSION['suspension_portal'] ?? '');
}
$bodyClass = [];
if (!empty($_SESSION['debe_cambiar_password'])) {
    $bodyClass[] = 'hay-debe-cambiar-password';
}
if ($debeAceptarAcuerdo) {
    $bodyClass[] = 'hay-debe-aceptar-acuerdo';
}
if ($debeCompletarPerfil) {
    $bodyClass[] = 'hay-debe-completar-perfil';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(app_page_title(), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars(hay_asset_url('src/logobco.png'), ENT_QUOTES, 'UTF-8'); ?>" type="image/png">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/main.css?v=20260608'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/plantel_fondo.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/top_nav.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/side_nav.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
    .rbac-sim-banner {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
        padding: 10px 16px; background: #fff8e1; border-bottom: 1px solid #f0c040; color: #5c4a00;
        font-size: 14px; margin-left: 0;
    }
    .rbac-sim-restore {
        padding: 6px 14px; border-radius: 8px; border: 1px solid #c9a227;
        background: #fff; color: #5c4a00; cursor: pointer; font-weight: 600;
    }
    .rbac-sim-restore:hover { background: #fff3cd; }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css?v=20260602'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_icon_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_datatables.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_tour.css?v=20260602'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/session_inactivity.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body data-plantel-slug="<?php echo htmlspecialchars($plantelSlug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $bodyClass !== [] ? ' class="' . htmlspecialchars(implode(' ', $bodyClass), ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>

    <?php include 'php/components/top_nav.php'; ?>

    <?php if (function_exists('rbac_esta_simulando_rol') && rbac_esta_simulando_rol()): ?>
    <div class="rbac-sim-banner" id="rbac-sim-banner" role="status">
        <span>
            <i class="fas fa-eye" aria-hidden="true"></i>
            Vista de capacitación: <strong><?php echo htmlspecialchars(rbac_etiqueta_rol()); ?></strong>
            (su rol real: <?php echo htmlspecialchars(rbac_etiqueta_rol(rbac_rol_real())); ?>)
        </span>
        <button type="button" class="rbac-sim-restore" id="rbac-sim-restore-btn">Volver a mi rol</button>
    </div>
    <?php endif; ?>

    <div class="main-layout">
        <?php include 'php/components/side_nav.php'; ?>

        <main
            class="content-viewport<?php echo $plantelFondoClases !== '' ? ' ' . htmlspecialchars($plantelFondoClases, ENT_QUOTES, 'UTF-8') : ''; ?>"
            id="main-content"
            data-plantel-fondo="<?php echo htmlspecialchars($plantelFondoUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            <?php if ($plantelFondoUrl): ?>style="--plantel-fondo-url: url('<?php echo htmlspecialchars($plantelFondoUrl, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>
        >
            <div class="welcome-card" id="inicio-view">
                <img src="<?php echo htmlspecialchars(hay_asset_url('src/logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="width:150px;">
                <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>
                <p>Panel de control — <?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? 'CNCM'); ?></p>
                <div id="inicio-notificaciones-wrap">
                    <?php include __DIR__ . '/views/inicio_panel.php'; ?>
                </div>
            </div>
        </main>
    </div>

    <script>window.HAY_WEB_ROOT = <?php echo json_encode(hay_web_root()); ?>;</script>
    <script>window.HAY_DEBE_CAMBIAR_PASSWORD = <?php echo !empty($_SESSION['debe_cambiar_password']) ? 'true' : 'false'; ?>;</script>
    <script>window.HAY_DEBE_ACEPTAR_ACUERDO = <?php echo $debeAceptarAcuerdo ? 'true' : 'false'; ?>;</script>
    <script>window.HAY_DEBE_COMPLETAR_PERFIL = <?php echo $debeCompletarPerfil ? 'true' : 'false'; ?>;</script>
    <script>window.HAY_SUSPENSION_PORTAL = <?php echo json_encode($haySuspensionPortal, JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/hay_ui_core.js?v=20260606'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/hay_modal.js?v=20260612'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/inicio_panel.js?v=20260701'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/navigation.js?v=20260612'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/hay_tour.js?v=20260602'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/hay_datatables.js?v=20260602'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/session_inactivity.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
    document.getElementById('rbac-sim-restore-btn')?.addEventListener('click', async function () {
      const fd = new FormData();
      fd.append('accion', 'restaurar');
      try {
        const res = await fetch(<?php echo json_encode(hay_asset_url('php/perfil_cambiar_rol.php'), JSON_UNESCAPED_UNICODE); ?>, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') location.reload();
        else alert(data.message || 'No se pudo restaurar el rol');
      } catch (e) { alert('Error de conexión'); }
    });
    </script>
    <?php if (empty($_SESSION['debe_cambiar_password'])): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        <?php if (rbac_rol_efectivo() === 'alumno'): ?>
        if (typeof cargarSeccion === 'function') {
          <?php if ($debeAceptarAcuerdo): ?>
          cargarSeccion('alumno_acuerdo_aceptar');
          <?php elseif ($debeCompletarPerfil): ?>
          cargarSeccion('alumno_perfil_gustos');
          <?php elseif ($haySuspensionPortal === 'adeudo'): ?>
          cargarSeccion('alumno_cuenta_suspendida');
          <?php else: ?>
          cargarSeccion('alumno_portal_inicio');
          <?php endif; ?>
        }
        <?php else: ?>
        if (typeof hayTourMaybeForSection === 'function') hayTourMaybeForSection('inicio_panel');
        <?php endif; ?>
      });
    </script>
    <?php endif; ?>
    <?php if (!empty($_SESSION['debe_cambiar_password'])): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        if (typeof cargarSeccion === 'function') cargarSeccion('cambiar_password');
      });
    </script>
    <?php endif; ?>
</body>
</html>