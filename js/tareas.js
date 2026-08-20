(function () {
  const config = window.HAY_TAREAS || {};
  const api = config.api || 'php/tareas_api.php';
  const lista = document.getElementById('tareas-lista');
  const msg = document.getElementById('tareas-msg');
  let filtro = 'pendientes';

  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function fecha(value) {
    if (!value) return '—';
    const parts = String(value).slice(0, 10).split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : esc(value);
  }

  function aviso(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'alert ' + (ok ? 'alert-success' : 'alert-error');
    msg.textContent = text || '';
  }

  function llenarPersonal(items) {
    const select = document.getElementById('tareas-asignado');
    if (!select) return;
    const actual = select.value;
    select.innerHTML = '<option value="">Todo el equipo / sin asignar</option>' + items.map((persona) =>
      `<option value="${esc(persona.id_usuario)}">${esc(persona.nombre)} · ${esc(persona.rol)}</option>`
    ).join('');
    select.value = actual;
  }

  function render(items) {
    if (!lista) return;
    if (!items.length) {
      lista.innerHTML = '<div class="tareas-card">No hay tareas en este filtro.</div>';
      return;
    }
    lista.innerHTML = items.map((tarea) => {
      const done = tarea.estado === 'hecha';
      const overdue = !done && String(tarea.fecha_efectiva || '') < String(config.hoy || '');
      const classes = ['tareas-card', 'tarea-item', done ? 'is-done' : '', overdue ? 'is-overdue' : ''].filter(Boolean).join(' ');
      const asignada = tarea.asignado_a_nombre ? ` · Asignada a ${esc(tarea.asignado_a_nombre)}` : '';
      const finalizada = done
        ? `<div class="tarea-meta">Hecha ${esc(String(tarea.hecha_en || '').slice(0, 16))}${tarea.hecha_por_nombre ? ' por ' + esc(tarea.hecha_por_nombre) : ''}</div>`
        : '';
      const acciones = done ? '' : `<div class="tarea-actions">
        <button type="button" class="primary" data-accion="hecha" data-id="${esc(tarea.id)}"><i class="fas fa-check"></i> Marcar hecha</button>
        <button type="button" data-accion="posponer" data-id="${esc(tarea.id)}" data-fecha="${esc(tarea.fecha_efectiva)}"><i class="fas fa-calendar-plus"></i> Posponer</button>
      </div>`;
      return `<article class="${classes}">
        <h3 style="margin:0 0 7px;">${esc(tarea.titulo)}</h3>
        ${tarea.descripcion ? `<div class="tarea-description">${esc(tarea.descripcion)}</div>` : ''}
        <div class="tarea-meta"><strong>${overdue ? 'Vencida' : done ? 'Finalizada' : 'Límite'}:</strong> ${fecha(tarea.fecha_efectiva)}${asignada}</div>
        <div class="tarea-meta">Creada por ${esc(tarea.creado_por_nombre || 'Personal')} · ${esc(String(tarea.creado_en || '').slice(0, 16))}</div>
        ${tarea.notas ? `<div class="tarea-meta"><strong>Notas:</strong> ${esc(tarea.notas)}</div>` : ''}
        ${finalizada}${acciones}
      </article>`;
    }).join('');
  }

  async function cargar() {
    if (lista) lista.innerHTML = '<div class="tareas-card">Cargando tareas…</div>';
    try {
      const { data } = await hayFetchJson(api + '?accion=listar&filtro=' + encodeURIComponent(filtro));
      if (data.status !== 'ok') throw new Error(data.message || 'No se pudieron cargar las tareas');
      render(data.items || []);
      llenarPersonal(data.personal || []);
    } catch (error) {
      if (lista) lista.innerHTML = '<div class="tareas-card">No se pudieron cargar las tareas.</div>';
      aviso(false, error.message || 'Error de red');
    }
  }

  async function enviar(fd) {
    try {
      const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
      aviso(data.status === 'ok', data.message || '');
      if (data.status === 'ok') await cargar();
      return data;
    } catch (error) {
      aviso(false, error.message || 'Error de red');
      return { status: 'error' };
    }
  }

  document.getElementById('tareas-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const fd = new FormData(form);
    fd.set('accion', 'crear');
    const data = await enviar(fd);
    if (data.status === 'ok') {
      form.reset();
      const fechaInput = form.querySelector('[name="fecha_limite"]');
      if (fechaInput) fechaInput.value = config.hoy || '';
    }
  });

  document.getElementById('tareas-filtros')?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-filtro]');
    if (!button) return;
    filtro = button.dataset.filtro || 'pendientes';
    document.querySelectorAll('#tareas-filtros [data-filtro]').forEach((item) => {
      item.classList.toggle('is-active', item === button);
    });
    cargar();
  });

  lista?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-accion]');
    if (!button) return;
    const fd = new FormData();
    fd.set('id', button.dataset.id || '0');
    if (button.dataset.accion === 'hecha') {
      if (!window.confirm('¿Marcar esta tarea como hecha?')) return;
      fd.set('accion', 'hecha');
    } else {
      const nuevaFecha = window.prompt('Nueva fecha límite (AAAA-MM-DD)', button.dataset.fecha || config.hoy || '');
      if (nuevaFecha === null) return;
      fd.set('accion', 'posponer');
      fd.set('fecha', nuevaFecha.trim());
    }
    await enviar(fd);
  });

  cargar();
})();
