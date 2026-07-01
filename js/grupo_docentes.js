(function () {
  const cfg = window.HAY_GRUPO_DOCENTES || {};
  const api = cfg.api || 'php/grupo_docente_api.php';
  const idGrupo = cfg.id_grupo || 0;

  let data = { profesores: [], docentes: [], materias_sugeridas: [], multi_materia: false };

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function profOpts(sel) {
    let h = '<option value="">— Sin asignar —</option>';
    (data.profesores || []).forEach((p) => {
      h += '<option value="' + p.id + '"' + (String(sel) === String(p.id) ? ' selected' : '') + '>' + esc(p.label) + '</option>';
    });
    return h;
  }

  function addRow(materiaNombre, idProf, esTitular, idx) {
    const tabla = document.getElementById('gd-tabla');
    if (!tabla) return;
    const row = document.createElement('div');
    row.className = 'gd-row';
    row.style.cssText = 'display:grid; grid-template-columns: 1fr 1.5fr auto; gap:10px; align-items:center;';
    row.innerHTML =
      '<input type="text" name="docente_materia[]" value="' + esc(materiaNombre) + '" placeholder="Materia" style="padding:10px; border:1px solid #ddd; border-radius:8px;">' +
      '<select name="docente_profesor[]" style="padding:10px; border:1px solid #ddd; border-radius:8px;">' + profOpts(idProf) + '</select>' +
      '<label style="font-size:0.85rem; white-space:nowrap;"><input type="radio" name="docente_titular_idx" value="' + idx + '"' + (esTitular ? ' checked' : '') + '> Titular</label>';
    tabla.appendChild(row);
  }

  function renderTabla() {
    const tabla = document.getElementById('gd-tabla');
    if (!tabla) return;
    tabla.innerHTML = '';
    const doc = data.docentes || [];
    if (doc.length) {
      doc.forEach((d, i) => addRow(d.materia_nombre || d.materia_clave || 'General', d.id_profesor, !!Number(d.es_titular), i));
      return;
    }
    const sug = data.materias_sugeridas || [];
    if (data.multi_materia && sug.length > 1) {
      sug.forEach((m, i) => addRow(m.nombre || m.clave, '', i === 0, i));
    } else {
      addRow('General', '', true, 0);
    }
  }

  async function cargar() {
    const { data: res } = await hayFetchJson(api + '?action=listar&id_grupo=' + encodeURIComponent(idGrupo));
    if (res.status !== 'ok') throw new Error(res.message || 'Error al cargar');
    data = res;
    renderTabla();
  }

  window.hayGrupoDocentesInit = function () {
    document.getElementById('gd-btn-add')?.addEventListener('click', () => {
      const tabla = document.getElementById('gd-tabla');
      const idx = tabla ? tabla.querySelectorAll('.gd-row').length : 0;
      addRow('', '', false, idx);
    });

    document.getElementById('form-grupo-docentes')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.target);
      fd.append('action', 'guardar');
      try {
        const { data: res } = await hayFetchJson(api, { method: 'POST', body: fd });
        alert(res.message || (res.status === 'ok' ? 'Guardado' : 'Error'));
        if (res.status === 'ok') cargarSeccion('grupos');
      } catch (e) {
        alert(e.message);
      }
    });

    cargar().catch((e) => alert(e.message));
  };

  if (document.getElementById('grupo-docentes-wrap')) {
    window.hayGrupoDocentesInit();
  }
})();
