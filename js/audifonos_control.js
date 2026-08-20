(function () {
  const api = (window.HAY_AUDIFONOS && window.HAY_AUDIFONOS.api) || 'php/audifonos_api.php';
  const msg = document.getElementById('audifonos-msg');
  const prestamosBox = document.getElementById('aud-prestamos');
  const historialBody = document.getElementById('aud-historial');

  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function fecha(value) {
    return value ? String(value).replace('T', ' ').slice(0, 16) : '—';
  }

  function estado(value) {
    return ({
      prestado: 'Prestado',
      devuelto: 'Devuelto',
      devuelto_parcial: 'Devuelto parcial',
      con_falla: 'Con falla'
    })[value] || value || '—';
  }

  function aviso(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text || '';
  }

  async function enviar(formData) {
    const { data } = await hayFetchJson(api, { method: 'POST', body: formData });
    aviso(data.status === 'ok', data.message || '');
    if (data.status === 'ok') await cargar();
    return data;
  }

  function renderPrestamos(items) {
    if (!prestamosBox) return;
    if (!items.length) {
      prestamosBox.innerHTML = '<div class="catalog-alert catalog-alert--ok">No hay audífonos pendientes de devolución.</div>';
      return;
    }
    prestamosBox.innerHTML = items.map((p) => {
      const pendiente = Math.max(0, Number(p.cantidad || 0) - Number(p.cantidad_devuelta || 0));
      return `<form class="aud-devolver catalog-card" style="padding:14px;margin-bottom:10px;display:grid;grid-template-columns:minmax(200px,1fr) repeat(2,minmax(120px,auto));gap:12px;align-items:end;">
        <input type="hidden" name="id_prestamo" value="${esc(p.id_prestamo)}">
        <div>
          <strong>${esc(p.profesor_nombre)}</strong>
          <div style="font-size:.86rem;color:#666;">Prestados ${esc(p.cantidad)} · devueltos ${esc(p.cantidad_devuelta)} · pendientes <strong>${pendiente}</strong></div>
          <div style="font-size:.8rem;color:#888;">${esc(fecha(p.prestado_en))}${p.notas ? ' · ' + esc(p.notas) : ''}</div>
        </div>
        <label>Devolver ahora
          <input type="number" name="cantidad" min="1" max="${pendiente}" value="${pendiente}" required style="width:100%;">
        </label>
        <button type="submit" class="primary"><i class="fas fa-undo"></i> Registrar</button>
        <label style="grid-column:1/-1;"><input type="checkbox" name="con_falla" value="1" class="aud-falla-check"> Reportar falla</label>
        <label class="aud-falla-wrap" style="display:none;grid-column:1/-1;">Descripción de la falla
          <input type="text" name="falla_reportada" maxlength="1000" style="width:100%;">
        </label>
        <label style="grid-column:1/-1;">Nota de devolución
          <input type="text" name="notas" maxlength="500" style="width:100%;">
        </label>
      </form>`;
    }).join('');
  }

  function renderHistorial(items) {
    if (!historialBody) return;
    if (!items.length) {
      historialBody.innerHTML = '<tr><td colspan="6" style="color:#888;">Sin movimientos.</td></tr>';
      return;
    }
    historialBody.innerHTML = items.map((p) => `<tr>
      <td>${esc(p.profesor_nombre)}</td>
      <td>${esc(p.cantidad)} / ${esc(p.cantidad_devuelta)} devueltos</td>
      <td>${esc(fecha(p.prestado_en))}</td>
      <td>${esc(fecha(p.devuelto_en))}</td>
      <td>${esc(estado(p.estado))}</td>
      <td>${esc([p.falla_reportada, p.notas].filter(Boolean).join(' · ') || '—')}</td>
    </tr>`).join('');
  }

  async function cargar() {
    try {
      const { data } = await hayFetchJson(api + '?accion=resumen');
      if (data.status !== 'ok') {
        aviso(false, data.message || 'No se pudo cargar');
        return;
      }
      const inv = data.inventario || {};
      document.getElementById('aud-total').textContent = inv.total || 0;
      document.getElementById('aud-prestados').textContent = inv.prestados || 0;
      document.getElementById('aud-disponibles').textContent = inv.disponibles || 0;
      const stockInput = document.getElementById('aud-stock-total');
      if (stockInput) stockInput.value = inv.total || 0;
      const profesor = document.getElementById('aud-profesor');
      if (profesor) {
        const actual = profesor.value;
        profesor.innerHTML = '<option value="">Seleccione profesor</option>' + (data.profesores || []).map((p) =>
          `<option value="${esc(p.id_usuario)}">${esc(p.nombre)}</option>`
        ).join('');
        profesor.value = actual;
      }
      renderPrestamos(data.prestamos || []);
      renderHistorial(data.historial || []);
    } catch (error) {
      aviso(false, error.message || 'Error de red');
    }
  }

  document.getElementById('aud-stock-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.currentTarget);
    fd.set('accion', 'stock');
    await enviar(fd);
  });

  document.getElementById('aud-prestar-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const fd = new FormData(form);
    fd.set('accion', 'prestar');
    const data = await enviar(fd);
    if (data.status === 'ok') form.reset();
  });

  prestamosBox?.addEventListener('change', (event) => {
    const check = event.target.closest('.aud-falla-check');
    if (!check) return;
    const wrap = check.closest('form')?.querySelector('.aud-falla-wrap');
    if (wrap) wrap.style.display = check.checked ? 'block' : 'none';
  });

  prestamosBox?.addEventListener('submit', async (event) => {
    const form = event.target.closest('.aud-devolver');
    if (!form) return;
    event.preventDefault();
    const fd = new FormData(form);
    fd.set('accion', 'devolver');
    await enviar(fd);
  });

  cargar();
})();
