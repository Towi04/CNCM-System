<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/maintenance_helper.php';
require_once __DIR__ . '/../php/supervisor_deploy_helper.php';

if (!supervisor_deploy_puede()) {
    echo '<div class="alert">Solo supervisión puede gestionar mantenimiento y promote a producción.</div>';
    return;
}

$apiUrl = hay_asset_url('php/supervisor_deploy_api.php');
$csrf = supervisor_deploy_csrf_token();
$activo = hay_maintenance_activo();
$githubOk = supervisor_deploy_configurado();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-rocket"></i> Mantenimiento y promote a producción</h2>
    <p style="color:#666;max-width:920px;margin:0;">
      Active el modo mantenimiento para que alumnos y personal vean “sistema en actualización”.
      Solo supervisión puede iniciar sesión. Luego puede fusionar <code>develop → main</code> en GitHub
      (dispara el deploy automático a <strong>sistema real</strong>).
    </p>
  </div>

  <div id="supdep-msg" class="catalog-alert" style="display:none;margin-bottom:12px;"></div>

  <div class="catalog-card" style="margin-bottom:16px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;">
    <h3 style="margin-top:0;"><i class="fas fa-hard-hat"></i> Modo mantenimiento</h3>
    <p id="supdep-maint-state" style="margin:0 0 12px;">
      Estado: <strong><?php echo $activo ? 'ACTIVO' : 'inactivo'; ?></strong>
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
      <input type="text" id="supdep-motivo" placeholder="Motivo (opcional)" style="min-width:220px;padding:8px 10px;border:1px solid #ddd;border-radius:8px;">
      <button type="button" class="primary" id="supdep-on"><i class="fas fa-lock"></i> Activar mantenimiento</button>
      <button type="button" class="secondary" id="supdep-off"><i class="fas fa-unlock"></i> Quitar mantenimiento</button>
    </div>
  </div>

  <div class="catalog-card" style="padding:16px;border:1px solid #e5e7eb;border-radius:12px;">
    <h3 style="margin-top:0;"><i class="fas fa-code-branch"></i> Promover develop → main</h3>
    <p id="supdep-git-state" style="color:#666;margin:0 0 12px;">
      <?php echo $githubOk
          ? 'Cargando estado de ramas…'
          : 'Falta <code>HAY_GITHUB_TOKEN</code> en <code>config.local.php</code> del servidor (repo opcional: HAY_GITHUB_REPO).'; ?>
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px;">
      <input type="text" id="supdep-confirm" placeholder="Escriba PROMOVER" autocomplete="off"
        style="min-width:180px;padding:8px 10px;border:1px solid #ddd;border-radius:8px;">
      <button type="button" class="primary" id="supdep-promote" <?php echo $githubOk ? '' : 'disabled'; ?>>
        <i class="fas fa-upload"></i> Fusionar develop → main
      </button>
      <button type="button" class="secondary" id="supdep-refresh"><i class="fas fa-sync"></i> Actualizar estado</button>
    </div>
    <p style="font-size:.88rem;color:#666;margin:0;">
      Tras el merge, revise
      <a href="https://github.com/<?php echo htmlspecialchars(supervisor_deploy_repo(), ENT_QUOTES, 'UTF-8'); ?>/actions" target="_blank" rel="noopener">GitHub Actions</a>
      (job Deploy production). Cuando termine, quite el modo mantenimiento.
    </p>
  </div>
</div>

<script>
(function () {
  const api = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;
  let csrf = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;

  function $(id) { return document.getElementById(id); }
  function showMsg(text, ok) {
    const el = $('supdep-msg');
    if (!el) return;
    el.style.display = 'block';
    el.textContent = text || '';
    el.className = 'catalog-alert ' + (ok ? 'ok' : 'err');
  }
  function post(action, extra) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf', csrf);
    if (extra) {
      Object.keys(extra).forEach((k) => fd.append(k, extra[k]));
    }
    return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then((r) => r.json());
  }
  function refresh() {
    return fetch(api + (api.indexOf('?') >= 0 ? '&' : '?') + 'action=estado', { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => {
        if (data.status !== 'ok') {
          showMsg(data.message || 'No se pudo leer estado', false);
          return;
        }
        csrf = data.csrf || csrf;
        const on = !!data.mantenimiento;
        $('supdep-maint-state').innerHTML = 'Estado: <strong>' + (on ? 'ACTIVO' : 'inactivo') + '</strong>';
        const ramas = data.ramas || {};
        let git = data.github_ok
          ? (ramas.message || 'OK')
          : 'Falta HAY_GITHUB_TOKEN en config.local.php';
        if (ramas.sha_develop || ramas.sha_main) {
          git += ' · develop=' + (ramas.sha_develop || '?') + ' main=' + (ramas.sha_main || '?');
        }
        $('supdep-git-state').textContent = git;
        $('supdep-promote').disabled = !data.github_ok;
      })
      .catch((e) => showMsg(String(e), false));
  }

  $('supdep-on').addEventListener('click', () => {
    post('mantenimiento_on', { motivo: $('supdep-motivo').value || '' }).then((d) => {
      showMsg(d.message || '', d.status === 'ok');
      refresh();
    });
  });
  $('supdep-off').addEventListener('click', () => {
    post('mantenimiento_off').then((d) => {
      showMsg(d.message || '', d.status === 'ok');
      refresh();
    });
  });
  $('supdep-promote').addEventListener('click', () => {
    const confirm = ($('supdep-confirm').value || '').trim();
    if (confirm.toUpperCase() !== 'PROMOVER') {
      showMsg('Escriba PROMOVER para confirmar el merge a producción', false);
      return;
    }
    if (!window.confirm('¿Fusionar develop → main y desplegar producción?')) return;
    post('promover', { confirm: confirm }).then((d) => {
      showMsg(d.message || '', d.status === 'ok');
      refresh();
    });
  });
  $('supdep-refresh').addEventListener('click', refresh);
  refresh();
})();
</script>
