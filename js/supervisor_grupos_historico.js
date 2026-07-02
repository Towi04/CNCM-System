(function () {
  const cfg = window.HaySupervisorGruposHistorico || {};
  const api = cfg.api || 'php/supervisor_grupos_historico_api.php';
  const state = {
    grupos: Array.isArray(cfg.contexto?.grupos) ? cfg.contexto.grupos : [],
    fases: Array.isArray(cfg.contexto?.fases) ? cfg.contexto.fases : [],
  };

  const msg = document.getElementById('hist-grupos-msg');
  const groupSelects = Array.from(document.querySelectorAll('[data-hist-grupo]'));
  const tableBody = document.querySelector('[data-hist-grupos-body]');

  function showMessage(text, ok) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.innerHTML = text;
    msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function esc(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function groupLabel(g) {
    const anterior = g.clave_anterior ? ` (antes ${g.clave_anterior})` : '';
    const esp = g.especialidad_clave || g.especialidad_nombre || 'Sin especialidad';
    return `${g.clave || 'Grupo'}${anterior} · ${esp}`;
  }

  function renderGroupSelects(selectedValue) {
    const options = ['<option value="">Seleccione grupo</option>']
      .concat(state.grupos.map((g) => `<option value="${esc(g.id_grupo)}">${esc(groupLabel(g))}</option>`))
      .join('');
    groupSelects.forEach((select) => {
      const prev = selectedValue || select.value;
      select.innerHTML = options;
      if (prev) select.value = String(prev);
    });
    renderGradePhaseSelect();
  }

  function renderTable() {
    if (!tableBody) return;
    if (!state.grupos.length) {
      tableBody.innerHTML = '<tr><td colspan="8">Sin grupos registrados en el plantel.</td></tr>';
      return;
    }
    tableBody.innerHTML = state.grupos.map((g) => `
      <tr>
        <td><strong>${esc(g.clave)}</strong></td>
        <td>${g.clave_anterior ? esc(g.clave_anterior) : '<span class="hist-muted">—</span>'}</td>
        <td>${esc(g.especialidad_nombre || '—')}</td>
        <td>${esc(g.fecha_inicio || '—')}</td>
        <td>${esc(g.clave_fase || g.nombre_fase || '—')}</td>
        <td>${Number(g.alumnos || 0)}</td>
        <td>${Number(g.pagos || 0)}</td>
        <td>${Number(g.calificaciones || 0)}</td>
      </tr>
    `).join('');
  }

  function phasesForSpecialty(idEspecialidad) {
    const id = Number(idEspecialidad || 0);
    return state.fases.filter((f) => Number(f.id_especialidad || 0) === id);
  }

  function fillPhaseSelect(select, idEspecialidad, placeholder) {
    if (!select) return;
    const fases = phasesForSpecialty(idEspecialidad);
    if (!idEspecialidad) {
      select.innerHTML = `<option value="">${esc(placeholder || 'Seleccione especialidad')}</option>`;
      return;
    }
    if (!fases.length) {
      select.innerHTML = '<option value="">Sin fases registradas</option>';
      return;
    }
    select.innerHTML = '<option value="">Seleccione</option>' + fases.map((f) => {
      const label = [f.clave_fase, f.nombre_fase].filter(Boolean).join(' — ');
      return `<option value="${esc(f.id_fase)}">${esc(label || ('Fase ' + f.id_fase))}</option>`;
    }).join('');
  }

  document.querySelectorAll('[data-hist-especialidad]').forEach((select) => {
    const form = select.closest('form');
    const phaseSelect = form?.querySelector('[data-hist-fase]');
    select.addEventListener('change', () => fillPhaseSelect(phaseSelect, select.value, 'Primero seleccione especialidad'));
  });

  function selectedGradeGroup() {
    const select = document.querySelector('[data-hist-grupo-calificaciones]');
    const id = Number(select?.value || 0);
    return state.grupos.find((g) => Number(g.id_grupo || 0) === id) || null;
  }

  function renderGradePhaseSelect() {
    const select = document.querySelector('[data-hist-fase-calificaciones]');
    const group = selectedGradeGroup();
    fillPhaseSelect(select, group?.id_especialidad || 0, 'Seleccione un grupo');
  }

  document.querySelector('[data-hist-grupo-calificaciones]')?.addEventListener('change', renderGradePhaseSelect);

  async function refreshGroups(selectedValue) {
    const res = await fetch(`${api}?action=grupos`, { headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    if (data.status === 'ok' && Array.isArray(data.grupos)) {
      state.grupos = data.grupos;
      renderGroupSelects(selectedValue);
      renderTable();
    }
  }

  function formatErrors(errors) {
    if (!Array.isArray(errors) || !errors.length) return '';
    return '<ul class="hist-errors">' + errors.slice(0, 8).map((e) => `<li>${esc(e)}</li>`).join('') + '</ul>';
  }

  async function submitForm(form) {
    const btn = form.querySelector('button[type="submit"]');
    const fd = new FormData(form);
    if (btn) btn.disabled = true;
    try {
      const res = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status !== 'ok') {
        showMessage(esc(data.message || 'No se pudo guardar.'), false);
        return;
      }
      showMessage(esc(data.message || 'Operacion guardada.') + formatErrors(data.errores), true);
      if (fd.get('action') === 'crear_grupo' && data.id_grupo) {
        await refreshGroups(data.id_grupo);
        form.reset();
      } else {
        await refreshGroups();
      }
      if (['cargar_alumnos', 'cargar_pagos', 'cargar_calificaciones'].includes(String(fd.get('action')))) {
        const textarea = form.querySelector('textarea');
        if (textarea) textarea.value = '';
      }
    } catch (err) {
      showMessage('Error de comunicacion al guardar.', false);
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  ['hist-form-grupo', 'hist-form-clave', 'hist-form-alumnos', 'hist-form-pagos', 'hist-form-calificaciones'].forEach((id) => {
    const form = document.getElementById(id);
    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      submitForm(form);
    });
  });

  renderGroupSelects();
  renderTable();
})();
