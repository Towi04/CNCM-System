/**
 * Administración de roles — se reinicializa cada vez que se carga la vista (AJAX).
 */
window.HayAdminRoles = (function () {
  let catalogo = {};
  let roles = [];
  let planteles = [];
  let api = 'php/rbac_roles_api.php';
  let docClickBound = false;

  const alcanceLabels = {
    solo_usuario: 'Solo su sede',
    lista: 'Sedes elegidas',
    todos: 'Todas las sedes',
  };

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  }

  async function fetchJson(url, opts) {
    const resolved = typeof window.hayResolveAssetUrl === 'function'
      ? window.hayResolveAssetUrl(url)
      : url;
    const r = await fetch(resolved, { credentials: 'same-origin', ...opts });
    const ct = (r.headers.get('content-type') || '').toLowerCase();
    if (!ct.includes('application/json')) {
      const t = await r.text();
      throw new Error('Error ' + r.status + ': ' + t.replace(/<[^>]+>/g, ' ').slice(0, 120));
    }
    return r.json();
  }

  function renderTabla() {
    const tbody = document.querySelector('#ar-tabla-roles tbody');
    if (!tbody) return;
    tbody.innerHTML = roles.map((r) => {
      let sedes = alcanceLabels[r.alcance_planteles] || r.alcance_planteles || '—';
      if (parseInt(r.acceso_total, 10)) sedes = 'Todas (acceso total)';
      return `<tr>
      <td><code>${esc(r.clave)}</code></td>
      <td>${esc(r.nombre)}</td>
      <td>${esc(sedes)}</td>
      <td>${r.acceso_total ? 'Todos' : (r.num_privilegios || 0)}</td>
      <td>${parseInt(r.es_sistema, 10) ? 'Sí' : 'No'}</td>
      <td>${parseInt(r.activo, 10) ? 'Sí' : 'No'}</td>
      <td><button type="button" class="secondary ar-edit" data-id="${r.id_rol}">Editar</button></td>
    </tr>`;
    }).join('');

    if (window.HayDataTable?.init) {
      window.HayDataTable.init('#ar-tabla-roles');
    }
  }

  function renderPrivGrid(selected) {
    const sel = new Set(selected || []);
    const grid = document.getElementById('ar-priv-grid');
    if (!grid) return;
    const grupos = {};
    Object.entries(catalogo).forEach(([k, v]) => {
      const g = v.grupo || 'General';
      if (!grupos[g]) grupos[g] = [];
      grupos[g].push({ k, label: v.label || k });
    });
    grid.innerHTML = Object.keys(grupos).sort().map((g) => {
      const items = grupos[g].map((p) =>
        `<label style="display:block;margin:4px 0;font-size:0.88rem;">
          <input type="checkbox" class="ar-priv" value="${esc(p.k)}" ${sel.has(p.k) ? 'checked' : ''}> ${esc(p.label)}
        </label>`
      ).join('');
      return `<div style="border:1px solid #e0e0e0; padding:8px; border-radius:6px; background:#fff;">
        <strong style="font-size:0.85rem;">${esc(g)}</strong>${items}
      </div>`;
    }).join('');
  }

  function renderPlantelesGrid(selectedIds) {
    const sel = new Set((selectedIds || []).map((x) => parseInt(x, 10)));
    const grid = document.getElementById('ar-planteles-grid');
    if (!grid) return;
    grid.innerHTML = planteles.map((p) =>
      `<label style="font-size:0.88rem; white-space:nowrap;">
        <input type="checkbox" class="ar-plantel" value="${p.id_plantel}" ${sel.has(p.id_plantel) ? 'checked' : ''}>
        ${esc(p.nombre)}
      </label>`
    ).join('');
  }

  function togglePrivWrap() {
    const total = document.getElementById('ar-acceso-total')?.checked;
    const wrap = document.getElementById('ar-priv-wrap');
    if (wrap) wrap.style.display = total ? 'none' : '';
    if (total) {
      const sel = document.getElementById('ar-alcance-planteles');
      if (sel) sel.value = 'todos';
      togglePlantelesWrap();
    }
  }

  function togglePlantelesWrap() {
    const total = document.getElementById('ar-acceso-total')?.checked;
    const alcance = document.getElementById('ar-alcance-planteles')?.value || 'solo_usuario';
    const wrap = document.getElementById('ar-planteles-wrap');
    const selWrap = document.getElementById('ar-alcance-planteles')?.closest('.field');
    if (selWrap) selWrap.style.display = total ? 'none' : '';
    if (wrap) wrap.style.display = !total && alcance === 'lista' ? '' : 'none';
  }

  function setAlcancePlanteles(val, plantelesIds) {
    const sel = document.getElementById('ar-alcance-planteles');
    if (sel) sel.value = val || 'solo_usuario';
    renderPlantelesGrid(plantelesIds || []);
    togglePlantelesWrap();
  }

  async function cargarLista() {
    const data = await fetchJson(api + '?action=listar');
    if (data.status !== 'ok') throw new Error(data.message || 'Error al listar');
    roles = data.roles || [];
    catalogo = data.catalogo || {};
    planteles = data.planteles || [];
    renderTabla();
  }

  async function abrirEditor(idRol) {
    const editor = document.getElementById('ar-editor');
    if (!editor) return;
    editor.style.display = 'block';
    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const msg = document.getElementById('ar-msg');
    if (msg) msg.style.display = 'none';

    if (idRol <= 0) {
      document.getElementById('ar-editor-titulo').textContent = 'Nuevo rol';
      document.getElementById('ar-id-rol').value = '0';
      document.getElementById('ar-clave').value = '';
      document.getElementById('ar-clave').disabled = false;
      document.getElementById('ar-nombre').value = '';
      document.getElementById('ar-desc').value = '';
      document.getElementById('ar-acceso-total').checked = false;
      document.getElementById('ar-activo').checked = true;
      renderPrivGrid([]);
      setAlcancePlanteles('solo_usuario', []);
      togglePrivWrap();
      return;
    }

    const data = await fetchJson(api + '?action=detalle&id_rol=' + idRol);
    if (data.status !== 'ok') throw new Error(data.message || 'Error');
    if (data.planteles) planteles = data.planteles;
    const r = data.rol;
    document.getElementById('ar-editor-titulo').textContent = 'Editar: ' + (r.nombre || '');
    document.getElementById('ar-id-rol').value = String(r.id_rol);
    document.getElementById('ar-clave').value = r.clave || '';
    document.getElementById('ar-clave').disabled = parseInt(r.es_sistema, 10) === 1;
    document.getElementById('ar-nombre').value = r.nombre || '';
    document.getElementById('ar-desc').value = r.descripcion || '';
    document.getElementById('ar-acceso-total').checked = parseInt(r.acceso_total, 10) === 1;
    document.getElementById('ar-activo').checked = parseInt(r.activo, 10) === 1;
    renderPrivGrid(data.privilegios || []);
    setAlcancePlanteles(r.alcance_planteles || 'solo_usuario', data.planteles_ids || []);
    togglePrivWrap();
  }

  function bindUi() {
    if (docClickBound) return;
    docClickBound = true;

    document.addEventListener('click', (e) => {
      if (!e.target.closest('#admin-roles-wrap') && !e.target.closest('#tab-roles')) return;

      const btnEdit = e.target.closest('.ar-edit');
      if (btnEdit) {
        e.preventDefault();
        abrirEditor(parseInt(btnEdit.dataset.id, 10)).catch((err) => alert(err.message));
        return;
      }
      if (e.target.closest('#ar-nuevo')) {
        abrirEditor(0);
        return;
      }
      if (e.target.closest('#ar-cancelar')) {
        const ed = document.getElementById('ar-editor');
        if (ed) ed.style.display = 'none';
        return;
      }
      if (e.target.closest('#ar-guardar')) {
        guardarRol();
      }
    });

    document.addEventListener('change', (e) => {
      if (!e.target.closest('#admin-roles-wrap') && !e.target.closest('#tab-roles')) return;
      if (e.target.id === 'ar-acceso-total') togglePrivWrap();
      if (e.target.id === 'ar-alcance-planteles') togglePlantelesWrap();
    });
  }

  async function guardarRol() {
      const privs = [];
      if (!document.getElementById('ar-acceso-total')?.checked) {
        document.querySelectorAll('.ar-priv:checked').forEach((c) => privs.push(c.value));
      }
      const plIds = [];
      document.querySelectorAll('.ar-plantel:checked').forEach((c) => plIds.push(c.value));
      const fd = new FormData();
      fd.append('action', 'guardar');
      fd.append('id_rol', document.getElementById('ar-id-rol').value);
      fd.append('clave', document.getElementById('ar-clave').value);
      fd.append('nombre', document.getElementById('ar-nombre').value);
      fd.append('descripcion', document.getElementById('ar-desc').value);
      if (document.getElementById('ar-acceso-total').checked) fd.append('acceso_total', '1');
      if (document.getElementById('ar-activo').checked) fd.append('activo', '1');
      fd.append('alcance_planteles', document.getElementById('ar-alcance-planteles')?.value || 'solo_usuario');
      fd.append('planteles', JSON.stringify(plIds));
      fd.append('privilegios', JSON.stringify(privs));
      try {
        const data = await fetchJson(api, { method: 'POST', body: fd });
        const msg = document.getElementById('ar-msg');
        if (msg) {
          msg.style.display = 'block';
          msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
          msg.textContent = data.message || '';
        }
        if (data.status === 'ok') {
          await cargarLista();
          if (data.id_rol) await abrirEditor(data.id_rol);
        }
      } catch (err) {
        alert(err.message || 'Error');
      }
  }

  return {
    init(cfg) {
      api = (cfg && cfg.api) ? cfg.api : (window.__hayAdminRoles?.api || 'php/rbac_roles_api.php');
      bindUi();
      cargarLista().catch((e) => alert(e.message || 'No se pudo cargar roles'));
    },
  };
})();
