<?php
global $pdo;
plantel_inicializar_sesion($pdo);
$listaPlanteles = plantel_list_accesibles($pdo, true);
$plantelIdActivo = plantel_scope_id($pdo);
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$puedeElegirPlantel = plantel_puede_elegir_sede($pdo);
?>
<div class="top-nav-stack" id="top-nav-stack">
    <header class="top-nav">
        <div class="nav-left">
            <button type="button" class="btn-menu-mobile" id="btn-menu-mobile" aria-label="Abrir menú">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title" id="page-title">Inicio</h1>
        </div>
        <div class="nav-right">
            <div class="plantel-select<?php echo $puedeElegirPlantel ? '' : ' plantel-select--locked'; ?>" id="plantel-select" title="Sede activa">
                <span class="plantel-label" id="plantel-label"><?php echo htmlspecialchars($plantelNombre); ?></span>
                <?php if ($puedeElegirPlantel && count($listaPlanteles) > 1): ?>
                <i class="fas fa-chevron-down plantel-arrow" aria-hidden="true"></i>
                <div class="plantel-dropdown" id="plantel-dropdown">
                    <?php foreach ($listaPlanteles as $p): ?>
                        <button
                            type="button"
                            class="plantel-option<?php echo (int)$p['id_plantel'] === $plantelIdActivo ? ' is-active' : ''; ?>"
                            data-plantel-id="<?php echo (int)$p['id_plantel']; ?>"
                        >
                            <?php echo htmlspecialchars($p['nombre']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <div class="breadcrumb-bar">
        <span id="breadcrumb">INICIO</span>
    </div>
</div>
<div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>
