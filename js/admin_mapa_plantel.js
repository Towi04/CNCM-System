(function () {
  const cfg = window.HAY_MAPA_AULAS_CONFIG || {};
  const api = cfg.api || 'php/aula_api.php';
  const especialidades = cfg.especialidades || [];
  const tipos = cfg.tipos || {};
  const fotoMax = cfg.fotoMax || 3;
  let aulas = [];

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function renderEspCheckboxes(selected) {
    const sel = new Set((selected || []).map((e) => String(e.id_especialidad || e)));
    return especialidades.map((e) => {
      const id = String(e.id_especialidad);
      const chk = sel.has(id) ? ' checked' : '';
      return `<label style="display:block;margin:4px 0;"><input type="checkbox" name="esp" value="${id}"${chk}> ${esc(e.nombre)}</label>`;
    }).join('');
  }

  function equipLabel(a) {
    const parts = [];
    if (a.tiene_pizarron) parts.push('Pizarrón');
    if (a.tiene_proyector) parts.push('Proyector');
    if (a.tiene_tv) parts.push('TV');
    if (a.tiene_pc) parts.push('PC');
    return parts.join(', ') || '—';
  }

  function renderTabla(list) {
    const tbody = document.querySelector('#mapa-aulas-tabla tbody');
    if (!tbody) return;
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="color:#888;">No hay aulas registradas. Use el formulario para dar de alta.</td></tr>';
      return;
    }
    tbody.innerHTML = list.map((a) => {
      const esp = !a.todas_especialidades
        ? (a.especialidades || []).map((e) => e.nombre).join(', ') || 'Ninguna'
        : 'Todas';
      const cap = a.capacidad_flexible ? `${a.capacidad} (flex)` : String(a.capacidad);
      return `<tr>
        <td><strong>${esc(a.codigo)}</strong><br><span style="color:#666;font-size:0.85rem;">${esc(a.nombre || '')}</span></td>
        <td>${esc(a.tipo_label || tipos[a.tipo_aula] || a.tipo_aula || '—')}</td>
        <td>${esc(a.piso || '—')}</td>
        <td>${esc(cap)}</td>
        <td>${esc(equipLabel(a))}</td>
        <td>${esc(esp)}</td>
        <td>${a.activo ? 'Activa' : 'Inactiva'}</td>
        <td>
          <button type="button" class="secondary btn-mapa-edit" data-id="${a.id_aula}">Editar</button>
          <button type="button" class="secondary btn-mapa-del" data-id="${a.id_aula}" style="color:#b71c1c;">Eliminar</button>
        </td>
      </tr>`;
    }).join('');

    tbody.querySelectorAll('.btn-mapa-edit').forEach((btn) => {
      btn.addEventListener('click', () => {
        const a = list.find((x) => String(x.id_aula) === btn.dataset.id);
        if (a) llenarForm(a);
      });
    });
    tbody.querySelectorAll('.btn-mapa-del').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('¿Eliminar esta aula?')) return;
        const fd = new FormData();
        fd.append('accion', 'eliminar');
        fd.append('id_aula', btn.dataset.id);
        const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await r.json();
        setMsg(data.message || '', data.status !== 'ok');
        if (data.status === 'ok') cargar();
      });
    });
  }

  function renderFotos(fotos) {
    const wrap = document.getElementById('mapa-fotos');
    if (!wrap) return;
    wrap.innerHTML = (fotos || []).map((f) => `
      <div style="position:relative;">
        <img src="${esc(f.url || f.ruta)}" alt="Foto aula" style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid #ddd;">
        <button type="button" data-foto="${f.id_foto}" class="btn-del-foto" style="position:absolute;top:-6px;right:-6px;background:#b71c1c;color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:11px;cursor:pointer;">×</button>
      </div>
    `).join('');
    wrap.querySelectorAll('.btn-del-foto').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('¿Eliminar esta foto?')) return;
        const fd = new FormData();
        fd.append('accion', 'eliminar_foto');
        fd.append('id_foto', btn.dataset.foto);
        const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await r.json();
        setMsg(data.message || '', data.status !== 'ok');
        if (data.status === 'ok') cargar();
      });
    });
    const input = document.getElementById('mapa-foto-input');
    if (input) input.style.display = (fotos || []).length >= fotoMax ? 'none' : 'block';
  }

  function llenarForm(a) {
    document.getElementById('mapa-form-titulo').textContent = 'Editar aula';
    document.getElementById('mapa-id-aula').value = a.id_aula || '';
    document.getElementById('mapa-codigo').value = a.codigo || '';
    document.getElementById('mapa-nombre').value = a.nombre || '';
    document.getElementById('mapa-piso').value = a.piso || '';
    document.getElementById('mapa-capacidad').value = a.capacidad || 20;
    document.getElementById('mapa-tipo').value = a.tipo_aula || 'aula';
    document.getElementById('mapa-pizarron').checked = !!Number(a.tiene_pizarron);
    document.getElementById('mapa-proyector').checked = !!Number(a.tiene_proyector);
    document.getElementById('mapa-tv').checked = !!Number(a.tiene_tv);
    document.getElementById('mapa-pc').checked = !!Number(a.tiene_pc);
    document.getElementById('mapa-cap-flex').checked = !!Number(a.capacidad_flexible);
    document.getElementById('mapa-todas-esp').checked = !!Number(a.todas_especialidades);
    document.getElementById('mapa-notas').value = a.notas || '';
    document.getElementById('mapa-activo').checked = !!Number(a.activo);
    toggleEsp();
    document.getElementById('mapa-esp-box').innerHTML = renderEspCheckboxes(a.especialidades || []);
    document.getElementById('mapa-fotos-wrap').hidden = false;
    renderFotos(a.fotos || []);
  }

  function limpiarForm() {
    document.getElementById('mapa-form-titulo').textContent = 'Nueva aula';
    document.getElementById('mapa-form').reset();
    document.getElementById('mapa-id-aula').value = '';
    document.getElementById('mapa-pizarron').checked = true;
    document.getElementById('mapa-todas-esp').checked = true;
    document.getElementById('mapa-activo').checked = true;
    document.getElementById('mapa-fotos-wrap').hidden = true;
    document.getElementById('mapa-fotos').innerHTML = '';
    toggleEsp();
    document.getElementById('mapa-esp-box').innerHTML = renderEspCheckboxes([]);
    setMsg('');
  }

  function toggleEsp() {
    const todas = document.getElementById('mapa-todas-esp').checked;
    document.getElementById('mapa-esp-wrap').hidden = todas;
  }

  function setMsg(txt, err) {
    const el = document.getElementById('mapa-msg');
    if (!el) return;
    el.textContent = txt || '';
    el.style.color = err ? '#b71c1c' : '#2e7d32';
  }

  async function cargar() {
    const r = await fetch(api + '?accion=listar', { credentials: 'same-origin' });
    const data = await r.json();
    aulas = data.aulas || [];
    renderTabla(aulas);
    const id = document.getElementById('mapa-id-aula').value;
    if (id) {
      const a = aulas.find((x) => String(x.id_aula) === String(id));
      if (a) renderFotos(a.fotos || []);
    }
  }

  document.getElementById('mapa-todas-esp')?.addEventListener('change', toggleEsp);
  document.getElementById('btn-mapa-limpiar')?.addEventListener('click', limpiarForm);

  document.getElementById('mapa-foto-input')?.addEventListener('change', async (ev) => {
    const file = ev.target.files && ev.target.files[0];
    const idAula = document.getElementById('mapa-id-aula').value;
    if (!file || !idAula) return;
    const fd = new FormData();
    fd.append('accion', 'subir_foto');
    fd.append('id_aula', idAula);
    fd.append('foto', file);
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await r.json();
    setMsg(data.message || '', data.status !== 'ok');
    ev.target.value = '';
    if (data.status === 'ok') cargar();
  });

  document.getElementById('mapa-form')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const espIds = [];
    if (!document.getElementById('mapa-todas-esp').checked) {
      document.querySelectorAll('#mapa-esp-box input[name=esp]:checked').forEach((c) => espIds.push(c.value));
    }
    const fd = new FormData();
    fd.append('accion', 'guardar');
    fd.append('id_aula', document.getElementById('mapa-id-aula').value);
    fd.append('codigo', document.getElementById('mapa-codigo').value);
    fd.append('nombre', document.getElementById('mapa-nombre').value);
    fd.append('piso', document.getElementById('mapa-piso').value);
    fd.append('capacidad', document.getElementById('mapa-capacidad').value);
    fd.append('tipo_aula', document.getElementById('mapa-tipo').value);
    fd.append('tiene_pizarron', document.getElementById('mapa-pizarron').checked ? 1 : 0);
    fd.append('tiene_proyector', document.getElementById('mapa-proyector').checked ? 1 : 0);
    fd.append('tiene_tv', document.getElementById('mapa-tv').checked ? 1 : 0);
    fd.append('tiene_pc', document.getElementById('mapa-pc').checked ? 1 : 0);
    fd.append('capacidad_flexible', document.getElementById('mapa-cap-flex').checked ? 1 : 0);
    fd.append('todas_especialidades', document.getElementById('mapa-todas-esp').checked ? 1 : 0);
    fd.append('notas', document.getElementById('mapa-notas').value);
    fd.append('activo', document.getElementById('mapa-activo').checked ? 1 : 0);
    fd.append('especialidades', JSON.stringify(espIds));
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await r.json();
    setMsg(data.message || '', data.status !== 'ok');
    if (data.status === 'ok') {
      if (data.id_aula) document.getElementById('mapa-id-aula').value = data.id_aula;
      document.getElementById('mapa-fotos-wrap').hidden = false;
      cargar();
    }
  });

  document.getElementById('mapa-esp-box').innerHTML = renderEspCheckboxes([]);
  cargar();
})();
