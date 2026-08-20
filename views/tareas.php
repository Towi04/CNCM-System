<?php
require_once __DIR__ . '/../config.php';
rbac_require_cap('menu_tareas', 'Sin permiso para consultar tareas.');
if (!tareas_puede_usar()) {
    echo '<div class="alert alert-error">Esta sección es exclusiva para el personal.</div>';
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">
<style>
.tareas-wrap{max-width:1100px;margin:0 auto;padding:18px}.tareas-grid{display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:20px;align-items:start}
.tareas-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;box-shadow:0 2px 8px rgba(15,23,42,.05)}
.tareas-form{display:grid;gap:11px}.tareas-form label{font-weight:600;font-size:.88rem}.tareas-form input,.tareas-form textarea,.tareas-form select{width:100%;box-sizing:border-box;margin-top:4px;padding:9px;border:1px solid #cbd5e1;border-radius:8px}
.tareas-filtros{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.tareas-filtros button.is-active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
.tarea-item{border-left:5px solid #3b82f6;margin-bottom:10px}.tarea-item.is-overdue{border-left-color:#dc2626}.tarea-item.is-done{border-left-color:#16a34a;opacity:.86}
.tarea-meta{color:#64748b;font-size:.84rem;margin-top:7px}.tarea-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.tarea-description{white-space:pre-wrap;color:#334155}
@media(max-width:760px){.tareas-grid{grid-template-columns:1fr}}
</style>

<div class="tareas-wrap">
  <div style="margin-bottom:16px;">
    <h2 style="margin:0 0 5px;"><i class="fas fa-clipboard-check"></i> Tareas</h2>
    <p style="margin:0;color:#64748b;">Pendientes del personal del plantel y seguimiento de fechas límite.</p>
  </div>
  <div id="tareas-msg" class="alert" style="display:none;"></div>

  <div class="tareas-grid">
    <section class="tareas-card">
      <h3 style="margin-top:0;">Nueva tarea</h3>
      <form id="tareas-form" class="tareas-form">
        <label>Título
          <input type="text" name="titulo" maxlength="180" required>
        </label>
        <label>Descripción
          <textarea name="descripcion" rows="3" maxlength="4000"></textarea>
        </label>
        <label>Fecha límite
          <input type="date" name="fecha_limite" value="<?php echo date('Y-m-d'); ?>" required>
        </label>
        <label>Asignar a
          <select name="asignado_a" id="tareas-asignado"><option value="">Todo el equipo / sin asignar</option></select>
        </label>
        <label>Notas
          <textarea name="notas" rows="2" maxlength="4000"></textarea>
        </label>
        <button type="submit" class="primary"><i class="fas fa-plus"></i> Crear tarea</button>
      </form>
    </section>

    <section>
      <div class="tareas-filtros" id="tareas-filtros">
        <button type="button" data-filtro="pendientes" class="is-active">Pendientes</button>
        <button type="button" data-filtro="vencidas">Vencidas</button>
        <button type="button" data-filtro="hechas">Hechas</button>
      </div>
      <div id="tareas-lista"><div class="tareas-card">Cargando tareas…</div></div>
    </section>
  </div>
</div>

<script>
window.HAY_TAREAS = <?php echo json_encode([
    'api' => hay_asset_url('php/tareas_api.php'),
    'hoy' => date('Y-m-d'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/tareas.js?v=20260820'), ENT_QUOTES, 'UTF-8'); ?>"></script>
