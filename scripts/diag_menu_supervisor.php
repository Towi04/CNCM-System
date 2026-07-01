<?php
/**
 * Diagnóstico: menú supervisor (CLI).
 * Uso: php scripts/diag_menu_supervisor.php [id_usuario]
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/menu_config.php';

$idUsuario = (int) ($argv[1] ?? 0);
if ($idUsuario <= 0) {
    $st = $pdo->query("SELECT id_usuario, nombre, apellido, rol, id_rol FROM usuarios WHERE rol IN ('supervisor','supervisora','direccion general') OR id_rol IN (SELECT id_rol FROM roles WHERE clave = 'supervisor') LIMIT 5");
    echo "Supervisores en BD:\n";
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) {
        echo "  id={$u['id_usuario']} {$u['nombre']} rol={$u['rol']} id_rol={$u['id_rol']}\n";
    }
    exit(0);
}

$st = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario = ?');
$st->execute([$idUsuario]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    fwrite(STDERR, "Usuario $idUsuario no encontrado\n");
    exit(1);
}

$_SESSION['user_id'] = $idUsuario;
if (function_exists('rbac_inicializar_sesion_tras_login')) {
    rbac_inicializar_sesion_tras_login($u);
}
if (function_exists('rbac_sincronizar_sesion_usuario')) {
    rbac_sincronizar_sesion_usuario($pdo);
}

echo "rol_efectivo: " . rbac_rol_efectivo() . "\n";
echo "rol_real: " . rbac_rol_real() . "\n";
echo "es_supervisor: " . (rbac_es_supervisor() ? 'yes' : 'no') . "\n";
echo "acceso_total: " . (rbac_tiene_acceso_total() ? 'yes' : 'no') . "\n";
echo "vista_por_rol: " . (menu_cncm_vista_por_rol() ? 'yes' : 'no') . "\n";
echo "caps_count: " . count($_SESSION['rbac_caps'] ?? []) . "\n";

$secciones = menu_cncm_vista_por_rol() ? menu_cncm_secciones_por_rol() : menu_cncm_secciones();
$visibleSections = 0;
$visibleItems = 0;
foreach ($secciones as $sec) {
    if (!menu_cncm_seccion_visible($sec)) {
        continue;
    }
    $visibleSections++;
    foreach ($sec['items'] ?? [] as $it) {
        if (menu_cncm_item_visible($it)) {
            $visibleItems++;
        }
    }
}
echo "secciones_visibles: $visibleSections\n";
echo "items_visibles: $visibleItems\n";

ob_start();
menu_cncm_render_items();
$html = ob_get_clean();
echo "html_bytes: " . strlen($html) . "\n";
echo "html_li_count: " . substr_count($html, '<li') . "\n";
if (strlen($html) < 500) {
    echo "html_preview:\n$html\n";
}
