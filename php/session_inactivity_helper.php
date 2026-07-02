<?php
/**
 * Helper para incluir el monitor de inactividad de sesión
 * Se debe llamar desde el HEAD y antes de </body> en las vistas principales
 */

/**
 * Incluye el CSS del monitor de inactividad en el HEAD
 */
function session_inactivity_include_css(): void {
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/session_inactivity.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php
}

/**
 * Incluye el script del monitor de inactividad antes de </body>
 */
function session_inactivity_include_js(): void {
    ?>
    <script src="<?php echo htmlspecialchars(hay_asset_url('js/session_inactivity.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
}

/**
 * Incluye tanto CSS como JS en un solo llamado
 * @param string $position 'head' o 'footer'
 */
function session_inactivity_include($position = 'both'): void {
    if ($position === 'head' || $position === 'both') {
        session_inactivity_include_css();
    }
    if ($position === 'footer' || $position === 'both') {
        session_inactivity_include_js();
    }
}
