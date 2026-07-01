/**
 * Pestaña Permisos por persona — centro de roles y permisos.
 */
window.HayAdminPermisosPersonal = (function () {
  let api = 'php/rbac_permisos_api.php';
  let catalogo = {};
  let restringidos = new Set();
  let esSupervisor = false;
  let usuarios = [];
  let roles = [];
  let detalle = null;
  let docBound = false;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  }

  async function fetchJson(url, opts) {
    const resolved = typeof window.hayResolveAssetUrl === 'function' ? window.hayResolveAssetUrl(url) : url;
    const r = await fetch(resolved, { credentials: 'same-origin', ...opts });
    const ct = (r.headers.get('content-type') || '').toLowerCase();
    if (!ct.includes('application/json')) {
      const t = await r.text();
      throw new Error('Error ' + r.status);
    }
    return r.json();
  }

  function renderTabla() {
    const tbody = document.querySelector('#ap-tabla-personal tbody');
    if (!tbody) return;
    tbody.innerHTML = usuarios.map((u) => {
      const nombre = esc(trimNombre(u));
      const custom = parseInt(u.permisos_personalizados, 10) ? '<span class="catalog-badge catalog-badge--warn" style="margin-left:4px;">Personalizado</span>' : '';
      const susp = parseInt(u.suspendido, 10) ? '<span class="catalog-badge catalog-badge--danger">Suspendido</span>' : '';
      return `<tr>
        <td>${nombre}${custom}</td>
        <td><code>${esc(u.username)}</code></td>
        <td>${esc(u.rol_nombre || u.rol || '')}</td>
        <td>${esc(u.plantel_nombre || '—')}</td>
        <td>${susp || 'Activo'}</td>
        <td><button type="button" class="secondary ap-edit" data-id="${u.id_usuario}">Permisos</button></td>
      </tr>`;
    }).join('');
    if (window.HayDataTable?.init) window.HayDataTable.init('#ap-tabla-personal');
  }

  function trimNombre(u) {
    return [u.nombre, u.apellido].filter(Boolean).join(' ');
  }

  async function cargarLista() {
    const q = document.getElementById('ap-buscar')?.value?.trim() || '';
    const todos = document.getElementById('ap-todos-planteles')?.checked ? '1' : '';
    let url = api + '?action=personal_listar';
    if (q) url += '&q=' + encodeURIComponent(q);
    if (todos) url += '&todos_planteles=1';
    const data = await fetchJson(url);
    if (data.status !== 'ok') throw new Error(data.message || 'Error');
    usuarios = data.usuarios || [];
    roles = data.roles || [];
    renderTabla();
  }

  function overrideMap(overrides) {
    const m = {};
    (overrides || []).forEach((o) => {
      m[o.privilegio] = o;
    });
    return m;
  }

  function renderPrivGrid(rolPrivs, overrides) {
    const grid = document.getElementById('ap-priv-grid');
    if (!grid) return;
    const rolSet = new Set(rolPrivs || []);
    const ov = overrideMap(overrides);
    const grupos = {};
    Object.entries(catalogo).forEach(([k, v]) => {
      const g = v.grupo || 'General';
      if (!grupos[g]) grupos[g] = [];
      grupos[g].push({ k, label: v.label || k });
    });

    grid.innerHTML = Object.keys(grupos).sort().map((g) => {
      const items = grupos[g].map((p) => {
        const enRol = rolSet.has(p.k);
        const o = ov[p.k];
        const tipo = o ? o.tipo : '';
        const hasta = o?.vigente_hasta || '';
        const motivo = o?.motivo || '';
        const disabled = !esSupervisor && restringidos.has(p.k) ? ' disabled title="Solo supervisión"' : '';
        return `<div class="ap-priv-row" style="border-bottom:1px solid #eee;padding:6px 0;font-size:0.86rem;">
          <div style="display:flex;justify-content:space-between;gap:6px;align-items:center;">
            <span>${enRol ? '✓' : '○'} ${esc(p.label)}</span>
            <span>
              <label style="margin-right:6px;"><input type="radio" name="ov_${esc(p.k)}" value="" class="ap-ov-none" data-priv="${esc(p.k)}"${!tipo ? ' checked' : ''}${disabled}> Rol</label>
              <label style="margin-right:6px;"><input type="radio" name="ov_${esc(p.k)}" value="otorgar" class="ap-ov-tipo" data-priv="${esc(p.k)}"${tipo === 'otorgar' ? ' checked' : ''}${disabled}> +</label>
              <label><input type="radio" name="ov_${esc(p.k)}" value="denegar" class="ap-ov-tipo" data-priv="${esc(p.k)}"${tipo === 'denegar' ? ' checked' : ''}${disabled}> −</label>
            </span>
          </div>
          <div class="ap-ov-extra" data-priv="${esc(p.k)}" style="display:${tipo ? 'flex' : 'none'};gap:6px;margin-top:4px;flex-wrap:wrap;">
            <input type="date" class="ap-ov-hasta" data-priv="${esc(p.k)}" value="${esc(hasta)}" placeholder="Vigencia" style="font-size:0.8rem;">
            <input type="text" class="ap-ov-motivo" data-priv="${esc(p.k)}" value="${esc(motivo)}" placeholder="Motivo (opcional)" style="flex:1;min-width:120px;font-size:0.8rem;">
          </div>
        </div>`;
      }).join('');
      return `<div style="border:1px solid #e0e0e0;padding:8px;border-radius:6px;background:#fff;">
        <strong style="font-size:0.85rem;">${esc(g)}</strong>${items}
      </div>`;
    }).join('');

    grid.querySelectorAll('.ap-ov-tipo, .ap-ov-none').forEach((inp) => {
      inp.addEventListener('change', () => {
        const priv = inp.dataset.priv;
        const extra = grid.querySelector('.ap-ov-extra[data-priv="' + priv + '"]');
        if (extra) extra.style.display = inp.classList.contains('ap-ov-none') || !inp.value ? 'none' : 'flex';
      });
    });
  }

  function fillRolesSelect(idRol) {
    const sel = document.getElementById('ap-id-rol');
    if (!sel) return;
    sel.innerHTML = roles.map((r) =>
      `<option value="${r.id_rol}"${parseInt(idRol, 10) === parseInt(r.id_rol, 10) ? ' selected' : ''}>${esc(r.nombre)} (${esc(r.clave)})</option>`
    ).join('');
  }

  async function abrirEditor(idUsuario) {
    const editor = document.getElementById('ap-editor');
    if (!editor) return;
    const data = await fetchJson(api + '?action=personal_detalle&id_usuario=' + idUsuario);
    if (data.status !== 'ok') throw new Error(data.message || 'Error');
    detalle = data.detalle;
    catalogo = data.catalogo || {};
    restringidos = new Set(data.restringidos || []);
    esSupervisor = !!data.es_supervisor;
    if (data.roles) roles = data.roles;

    const u = detalle.usuario;
    document.getElementById('ap-editor-titulo').textContent = 'Permisos: ' + trimNombre(u);
    document.getElementById('ap-id-usuario').value = String(u.id_usuario);
    fillRolesSelect(u.id_rol || 0);

    const hint = document.getElementById('ap-rol-hint');
    if (hint) {
      const nPriv = (detalle.privilegios_rol || []).length;
      hint.textContent = detalle.permisos_personalizados
        ? 'Esta persona tiene permisos personalizados además del rol (' + nPriv + ' del rol + ajustes).'
        : 'Hereda ' + nPriv + ' privilegio(s) del rol sin ajustes individuales.';
    }

    renderPrivGrid(detalle.privilegios_rol, detalle.overrides);
    editor.style.display = 'block';
    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const msg = document.getElementById('ap-msg');
    if (msg) msg.style.display = 'none';
  }

  function collectOverrides() {
    const items = [];
    const grid = document.getElementById('ap-priv-grid');
    if (!grid) return items;
    const privs = new Set();
    grid.querySelectorAll('[data-priv]').forEach((el) => privs.add(el.dataset.priv));
    privs.forEach((priv) => {
      const checked = grid.querySelector('input.ap-ov-tipo[data-priv="' + priv + '"]:checked');
      const none = grid.querySelector('input.ap-ov-none[data-priv="' + priv + '"]:checked');
      if (!checked || none?.checked) return;
      const tipo = checked.value;
      if (tipo !== 'otorgar' && tipo !== 'denegar') return;
      const hasta = grid.querySelector('.ap-ov-hasta[data-priv="' + priv + '"]')?.value || '';
      const motivo = grid.querySelector('.ap-ov-motivo[data-priv="' + priv + '"]')?.value || '';
      items.push({ privilegio: priv, tipo, vigente_hasta: hasta, motivo });
    });
    return items;
  }

  function showMsg(elId, ok, text) {
    const msg = document.getElementById(elId);
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  async function guardarRol() {
    const idU = document.getElementById('ap-id-usuario')?.value;
    const idR = document.getElementById('ap-id-rol')?.value;
    const fd = new FormData();
    fd.append('action', 'personal_rol');
    fd.append('id_usuario', idU);
    fd.append('id_rol', idR);
    const data = await fetchJson(api, { method: 'POST', body: fd });
    showMsg('ap-msg', data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      await cargarLista();
      await abrirEditor(parseInt(idU, 10));
    }
  }

  async function guardarPriv() {
    const idU = document.getElementById('ap-id-usuario')?.value;
    const fd = new FormData();
    fd.append('action', 'personal_guardar');
    fd.append('id_usuario', idU);
    fd.append('items', JSON.stringify(collectOverrides()));
    const data = await fetchJson(api, { method: 'POST', body: fd });
    showMsg('ap-msg', data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      await cargarLista();
      await abrirEditor(parseInt(idU, 10));
    }
  }

  async function limpiarCustom() {
    if (!confirm('¿Quitar todos los permisos personalizados? Solo aplicará el rol base.')) return;
    const idU = document.getElementById('ap-id-usuario')?.value;
    const fd = new FormData();
    fd.append('action', 'personal_limpiar');
    fd.append('id_usuario', idU);
    const data = await fetchJson(api, { method: 'POST', body: fd });
    showMsg('ap-msg', data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      await cargarLista();
      await abrirEditor(parseInt(idU, 10));
    }
  }

  function bindUi() {
    if (docBound) return;
    docBound = true;
    document.addEventListener('click', (e) => {
      if (!e.target.closest('#tab-personal')) return;
      if (e.target.closest('.ap-edit')) {
        abrirEditor(parseInt(e.target.closest('.ap-edit').dataset.id, 10)).catch((err) => alert(err.message));
      }
      if (e.target.closest('#ap-btn-buscar')) cargarLista().catch((err) => alert(err.message));
      if (e.target.closest('#ap-guardar-rol')) guardarRol().catch((err) => alert(err.message));
      if (e.target.closest('#ap-guardar-priv')) guardarPriv().catch((err) => alert(err.message));
      if (e.target.closest('#ap-limpiar-custom')) limpiarCustom().catch((err) => alert(err.message));
      if (e.target.closest('#ap-cancelar')) {
        const ed = document.getElementById('ap-editor');
        if (ed) ed.style.display = 'none';
      }
    });
    document.getElementById('ap-buscar')?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        cargarLista().catch((err) => alert(err.message));
      }
    });
  }

  return {
    init(cfg) {
      api = cfg?.permApi || api;
      bindUi();
      const idPre = parseInt(cfg?.idUsuario, 10) || 0;
      cargarLista()
        .then(() => {
          if (idPre > 0) return abrirEditor(idPre);
        })
        .catch((e) => alert(e.message || 'No se pudo cargar personal'));
    },
  };
})();
