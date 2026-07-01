<?php
require_once __DIR__ . '/../config.php';
if (!marketing_puede_administrar()) {
    echo '<div class="alert">Sin permiso para administrar banners.</div>';
    return;
}
$banners = marketing_banners_admin_listar($pdo);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-image"></i> Banners — portal alumno</h2>
    <p style="color:#666;">Promociones y avisos visuales en Inicio y Promociones del alumno.</p>
  </div>

  <div id="mkt-alert" class="catalog-alert" style="display:none;"></div>

  <div class="welcome-card" style="padding:16px; margin-bottom:16px;">
    <h3 style="margin:0 0 12px;">Nuevo / editar banner</h3>
    <form id="form-banner" style="display:grid; gap:10px; max-width:720px;">
      <input type="hidden" name="id_banner" id="banner-id" value="0">
      <label>Título <input type="text" name="titulo" id="banner-titulo" required maxlength="160" style="width:100%;"></label>
      <label>URL imagen <input type="url" name="imagen_url" id="banner-imagen" placeholder="https://…" style="width:100%;"></label>
      <label>Enlace (opcional) <input type="url" name="enlace_url" id="banner-enlace" style="width:100%;"></label>
      <label>Texto alternativo <input type="text" name="texto_alt" id="banner-alt" maxlength="200" style="width:100%;"></label>
      <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <label>Audiencia
          <select name="audiencia" id="banner-audiencia">
            <option value="alumno">Alumnos</option>
            <option value="todos">Todos</option>
            <option value="staff">Staff</option>
          </select>
        </label>
        <label>Orden <input type="number" name="orden" id="banner-orden" value="0" style="width:80px;"></label>
        <label>Vigente desde <input type="date" name="vigente_desde" id="banner-desde"></label>
        <label>Vigente hasta <input type="date" name="vigente_hasta" id="banner-hasta"></label>
      </div>
      <label><input type="checkbox" name="activo" id="banner-activo" value="1" checked> Activo</label>
      <div>
        <button type="submit" class="primary">Guardar banner</button>
        <button type="button" class="secondary" id="banner-limpiar">Limpiar</button>
      </div>
    </form>
  </div>

  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead>
        <tr>
          <th>Orden</th>
          <th>Título</th>
          <th>Audiencia</th>
          <th>Vigencia</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="banner-tbody">
        <?php if (empty($banners)): ?>
          <tr><td colspan="6" style="color:#888;">Sin banners.</td></tr>
        <?php else: foreach ($banners as $b): ?>
          <tr data-id="<?php echo (int) $b['id_banner']; ?>">
            <td><?php echo (int) ($b['orden'] ?? 0); ?></td>
            <td>
              <strong><?php echo htmlspecialchars($b['titulo'] ?? ''); ?></strong>
              <?php if (!empty($b['imagen_url'])): ?>
                <br><small><a href="<?php echo htmlspecialchars($b['imagen_url']); ?>" target="_blank" rel="noopener">Ver imagen</a></small>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($b['audiencia'] ?? 'alumno'); ?></td>
            <td>
              <?php
              $v = [];
              if (!empty($b['vigente_desde'])) {
                  $v[] = 'desde ' . date('d/m/Y', strtotime($b['vigente_desde']));
              }
              if (!empty($b['vigente_hasta'])) {
                  $v[] = 'hasta ' . date('d/m/Y', strtotime($b['vigente_hasta']));
              }
              echo $v ? htmlspecialchars(implode(' · ', $v)) : '—';
              ?>
            </td>
            <td><?php echo (int) ($b['activo'] ?? 0) ? 'Activo' : 'Inactivo'; ?></td>
            <td>
              <button type="button" class="secondary btn-edit-banner"
                data-json="<?php echo htmlspecialchars(json_encode($b, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>">Editar</button>
              <button type="button" class="secondary btn-del-banner" data-id="<?php echo (int) $b['id_banner']; ?>">Eliminar</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const api = 'php/marketing_banners_api.php';
  const alertEl = document.getElementById('mkt-alert');
  const form = document.getElementById('form-banner');

  function showAlert(msg, ok) {
    if (!alertEl) return;
    alertEl.textContent = msg;
    alertEl.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    alertEl.style.display = 'block';
    setTimeout(() => { alertEl.style.display = 'none'; }, 4000);
  }

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action', 'guardar');
    if (!document.getElementById('banner-activo')?.checked) fd.set('activo', '0');
    try {
      const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (data.status === 'ok') {
        showAlert('Banner guardado', true);
        cargarSeccion('admin_marketing_banners');
      } else {
        showAlert(data.message || 'Error', false);
      }
    } catch (err) {
      showAlert('Error de conexión', false);
    }
  });

  document.getElementById('banner-limpiar')?.addEventListener('click', () => {
    form?.reset();
    document.getElementById('banner-id').value = '0';
    document.getElementById('banner-activo').checked = true;
  });

  document.querySelectorAll('.btn-edit-banner').forEach((btn) => {
    btn.addEventListener('click', () => {
      let b = {};
      try { b = JSON.parse(btn.dataset.json || '{}'); } catch (e) {}
      document.getElementById('banner-id').value = b.id_banner || '0';
      document.getElementById('banner-titulo').value = b.titulo || '';
      document.getElementById('banner-imagen').value = b.imagen_url || '';
      document.getElementById('banner-enlace').value = b.enlace_url || '';
      document.getElementById('banner-alt').value = b.texto_alt || '';
      document.getElementById('banner-audiencia').value = b.audiencia || 'alumno';
      document.getElementById('banner-orden').value = b.orden || '0';
      document.getElementById('banner-desde').value = b.vigente_desde || '';
      document.getElementById('banner-hasta').value = b.vigente_hasta || '';
      document.getElementById('banner-activo').checked = String(b.activo) === '1';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  document.querySelectorAll('.btn-del-banner').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('¿Eliminar este banner?')) return;
      const fd = new FormData();
      fd.append('action', 'eliminar');
      fd.append('id_banner', btn.dataset.id || '0');
      try {
        const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (data.status === 'ok') cargarSeccion('admin_marketing_banners');
        else alert(data.message || 'No se pudo eliminar');
      } catch (e) {
        alert('Error de conexión');
      }
    });
  });
})();
</script>
