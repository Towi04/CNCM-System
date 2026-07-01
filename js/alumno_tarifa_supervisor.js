(function () {
  const cfg = window.HayAlumnoTarifaSupervisor || {};
  const api = cfg.api || 'php/alumno_tarifa_supervisor_api.php';
  const idAlumno = cfg.idAlumno || 0;
  const panel = document.getElementById('alumno-tarifa-supervisor-panel');
  if (!panel || idAlumno <= 0) return;

  const msgEl = document.getElementById('alumno-tarifa-supervisor-msg');
  const listEl = document.getElementById('alumno-tarifa-supervisor-list');
  const histEl = document.getElementById('alumno-tarifa-supervisor-hist');

  function msg(text, ok) {
    if (!msgEl) return;
    msgEl.style.display = text ? 'block' : 'none';
    msgEl.className = 'asist-checada-msg ' + (ok ? 'ok' : 'err');
    msgEl.textContent = text || '';
  }

  function fmt(n) {
    return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtFecha(iso) {
    if (!iso) return '—';
    const p = String(iso).split('-');
    if (p.length !== 3) return iso;
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  function badge(item) {
    if (!item.override_activo) return '<span style="color:#666;">Tarifa estándar</span>';
    if (item.override_vigencia === 'resto_curso') {
      return '<span style="color:#00695c; font-weight:600;">Beneficio resto del curso hasta ' + fmtFecha(item.override_vigente_hasta) + '</span>';
    }
    if (item.override_vigencia === 'temporal') {
      return '<span style="color:#1565c0; font-weight:600;">Beneficio temporal hasta ' + fmtFecha(item.override_vigente_hasta) + '</span>';
    }
    if (item.override_vigencia === 'permanente') {
      return '<span style="color:#6a1b9a; font-weight:600;">Colegiatura personalizada</span>';
    }
    return '<span style="color:#c62828;">Vencido (pendiente restaurar)</span>';
  }

  function renderItem(item) {
    const t = item.tarifa_actual;
    const b = item.tarifa_base;
    const idAe = item.id_alumno_especialidad;
    const checked = item.override_vigente_hasta && !item.override_resto_curso ? 'checked' : '';
    const checkedResto = item.override_resto_curso ? 'checked' : '';
    const hasta = item.override_vigente_hasta || '';

    return (
      '<div class="alumno-tarifa-card" data-ae="' + idAe + '" style="border:1px solid #e0e0e0; border-radius:10px; padding:16px; margin-bottom:16px; background:#fafafa;">' +
        '<div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px; margin-bottom:12px;">' +
          '<div><strong style="font-size:1.05rem;">' + (item.especialidad || '') + '</strong>' +
          (item.clave ? ' <span style="color:#888;">(' + item.clave + ')</span>' : '') +
          '<div style="margin-top:4px;">' + badge(item) + '</div></div>' +
          '<div style="text-align:right; font-size:0.85rem; color:#666;">Forma de pago: ' + (item.forma_pago === 'semanal' ? 'Semanal' : 'Mensual') + '</div>' +
        '</div>' +
        (item.override_activo && item.override_motivo
          ? '<p style="margin:0 0 10px; font-size:0.9rem; color:#555;"><em>Motivo:</em> ' + item.override_motivo +
            (item.override_autor ? ' · <em>Por:</em> ' + item.override_autor : '') + '</p>'
          : '') +
        '<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:12px;">' +
          '<label>Inscripción<br><input type="number" step="0.01" min="0" class="tf-insc" value="' + t.inscripcion + '" style="width:100%; padding:8px;"></label>' +
          '<label>Mensualidad<br><input type="number" step="0.01" min="0" class="tf-men" value="' + t.mensualidad + '" style="width:100%; padding:8px;"></label>' +
          '<label>Pronto pago<br><input type="number" step="0.01" min="0" class="tf-pro" value="' + t.pronto_pago + '" style="width:100%; padding:8px;"></label>' +
          '<label>Semanal<br><input type="number" step="0.01" min="0" class="tf-sem" value="' + t.semanal + '" style="width:100%; padding:8px;"></label>' +
        '</div>' +
        '<p style="font-size:0.82rem; color:#777; margin:0 0 12px;">Tarifa normal de referencia: inscripción ' + fmt(b.inscripcion) +
          ', mensualidad ' + fmt(b.mensualidad) + ', pronto pago ' + fmt(b.pronto_pago) + ', semanal ' + fmt(b.semanal) + '</p>' +
        '<label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:0.9rem;">' +
          '<input type="radio" name="tf-tipo-' + idAe + '" class="tf-tipo" value="permanente"' + (!checked && !checkedResto ? ' checked' : '') + '> Permanente (sin fecha fin)' +
        '</label>' +
        '<label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:0.9rem;">' +
          '<input type="radio" name="tf-tipo-' + idAe + '" class="tf-tipo" value="temporal"' + (checked ? ' checked' : '') + '> Temporal hasta fecha' +
        '</label>' +
        '<div class="tf-hasta-wrap" style="margin:0 0 10px 24px; display:' + (checked ? 'block' : 'none') + ';">' +
          '<label>Válido hasta<br><input type="date" class="tf-hasta" value="' + hasta + '" style="padding:8px;"></label>' +
        '</div>' +
        '<label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:0.9rem;">' +
          '<input type="radio" name="tf-tipo-' + idAe + '" class="tf-tipo" value="meses"> Temporal por meses' +
        '</label>' +
        '<div class="tf-meses-wrap" style="margin:0 0 10px 24px; display:none;">' +
          '<label>Meses de beneficio<br><input type="number" class="tf-meses" min="1" max="36" value="3" style="width:100px; padding:8px;"></label>' +
        '</div>' +
        '<label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:0.9rem;">' +
          '<input type="radio" name="tf-tipo-' + idAe + '" class="tf-tipo" value="resto_curso"' + (checkedResto ? ' checked' : '') + '> Resto del curso (hasta fin de especialidad)' +
        '</label>' +
        '<label style="display:block; margin:12px 0 8px;">Motivo del ajuste<br>' +
          '<input type="text" class="tf-motivo" maxlength="255" placeholder="Ej. beneficio autorizado por dirección" style="width:100%; padding:8px;" value="' + (item.override_motivo || '') + '"></label>' +
        '<div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px;">' +
          '<button type="button" class="primary btn-tf-guardar" data-ae="' + idAe + '"><i class="fas fa-save"></i> Guardar colegiatura</button>' +
          (item.override_activo
            ? '<button type="button" class="secondary btn-tf-restaurar" data-ae="' + idAe + '"><i class="fas fa-undo"></i> Restaurar tarifa normal</button>'
            : '') +
          '<button type="button" class="secondary btn-tf-condonar" data-ae="' + idAe + '" style="border-color:#c62828; color:#c62828;"><i class="fas fa-hand-holding-usd"></i> Condonar adeudo</button>' +
        '</div>' +
      '</div>'
    );
  }

  function renderCondonaciones(rows) {
    const el = document.getElementById('alumno-tarifa-condonaciones');
    if (!el) return;
    if (!rows || !rows.length) {
      el.innerHTML = '<p style="color:#888;">Sin condonaciones registradas.</p>';
      return;
    }
    let html = '<table class="catalog-table"><thead><tr><th>Fecha</th><th>Especialidad</th><th>Monto</th><th>Motivo</th><th>Usuario</th></tr></thead><tbody>';
    rows.forEach((c) => {
      html += '<tr><td>' + fmtFecha(String(c.creado_en || '').slice(0, 10)) + '</td><td>' +
        (c.especialidad_nombre || 'Todas') + '</td><td>' + fmt(c.monto_condonado) + '</td><td>' +
        (c.motivo || '—') + '</td><td>' + (c.usuario_nombre || '—') + '</td></tr>';
    });
    html += '</tbody></table>';
    el.innerHTML = html;
  }

  function renderHistorial(rows) {
    if (!histEl) return;
    if (!rows || !rows.length) {
      histEl.innerHTML = '<p style="color:#888;">Sin movimientos registrados.</p>';
      return;
    }
    const accionLbl = { aplicar: 'Aplicó ajuste', restaurar: 'Restauró tarifa', vencer: 'Venció beneficio', condonar: 'Condonó adeudo' };
    let html = '<table class="catalog-table"><thead><tr><th>Fecha</th><th>Especialidad</th><th>Acción</th><th>Montos</th><th>Motivo</th><th>Usuario</th></tr></thead><tbody>';
    rows.forEach((h) => {
      const montos = 'Ins ' + fmt(h.costo_inscripcion) + ' · Men ' + fmt(h.costo_mensualidad);
      html += '<tr><td>' + fmtFecha(String(h.creado_en || '').slice(0, 10)) + ' ' + String(h.creado_en || '').slice(11, 16) +
        '</td><td>' + (h.especialidad_nombre || '') + '</td><td>' + (accionLbl[h.accion] || h.accion) +
        '</td><td>' + montos + '</td><td>' + (h.motivo || '—') + '</td><td>' + (h.usuario_nombre || '—') + '</td></tr>';
    });
    html += '</tbody></table>';
    histEl.innerHTML = html;
  }

  function syncTipoVigencia(card) {
    const tipo = card.querySelector('.tf-tipo:checked')?.value || 'permanente';
    const hastaWrap = card.querySelector('.tf-hasta-wrap');
    const mesesWrap = card.querySelector('.tf-meses-wrap');
    if (hastaWrap) hastaWrap.style.display = tipo === 'temporal' ? 'block' : 'none';
    if (mesesWrap) mesesWrap.style.display = tipo === 'meses' ? 'block' : 'none';
  }

  function bindCards() {
    panel.querySelectorAll('.alumno-tarifa-card').forEach((card) => syncTipoVigencia(card));
    panel.querySelectorAll('.tf-tipo').forEach((radio) => {
      radio.addEventListener('change', () => {
        const card = radio.closest('.alumno-tarifa-card');
        if (card) syncTipoVigencia(card);
      });
    });

    panel.querySelectorAll('.btn-tf-guardar').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const card = btn.closest('.alumno-tarifa-card');
        if (!card) return;
        const tipo = card.querySelector('.tf-tipo:checked')?.value || 'permanente';
        const fd = new FormData();
        fd.append('action', 'guardar');
        fd.append('id_alumno', idAlumno);
        fd.append('id_alumno_especialidad', btn.dataset.ae);
        fd.append('costo_inscripcion', card.querySelector('.tf-insc')?.value || '0');
        fd.append('costo_mensualidad', card.querySelector('.tf-men')?.value || '0');
        fd.append('costo_pronto_pago', card.querySelector('.tf-pro')?.value || '0');
        fd.append('costo_semanal', card.querySelector('.tf-sem')?.value || '0');
        fd.append('motivo', card.querySelector('.tf-motivo')?.value || '');
        if (tipo === 'temporal') {
          fd.append('beneficio_temporal', '1');
          fd.append('vigente_hasta', card.querySelector('.tf-hasta')?.value || '');
        } else if (tipo === 'meses') {
          fd.append('beneficio_temporal', '1');
          fd.append('meses_temporal', card.querySelector('.tf-meses')?.value || '1');
        } else if (tipo === 'resto_curso') {
          fd.append('beneficio_resto_curso', '1');
        }
        btn.disabled = true;
        try {
          const r = await fetch(api, { method: 'POST', body: fd });
          const data = await r.json();
          msg(data.message || '', data.status === 'ok');
          if (data.status === 'ok') await cargar();
        } catch (e) {
          msg('Error al guardar', false);
        }
        btn.disabled = false;
      });
    });

    panel.querySelectorAll('.btn-tf-restaurar').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('¿Restaurar la tarifa normal de esta especialidad?')) return;
        const fd = new FormData();
        fd.append('action', 'restaurar');
        fd.append('id_alumno', idAlumno);
        fd.append('id_alumno_especialidad', btn.dataset.ae);
        fd.append('motivo', 'Restauración manual por supervisor');
        btn.disabled = true;
        try {
          const r = await fetch(api, { method: 'POST', body: fd });
          const data = await r.json();
          msg(data.message || '', data.status === 'ok');
          if (data.status === 'ok') await cargar();
        } catch (e) {
          msg('Error al restaurar', false);
        }
        btn.disabled = false;
      });
    });

    panel.querySelectorAll('.btn-tf-condonar').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const motivo = prompt('Motivo de la condonación de adeudo (obligatorio):');
        if (motivo === null) return;
        if (!motivo.trim()) {
          msg('Indica el motivo de la condonación', false);
          return;
        }
        if (!confirm('¿Condonar el adeudo pendiente de esta especialidad? Esta acción queda registrada.')) return;
        const fd = new FormData();
        fd.append('action', 'condonar');
        fd.append('id_alumno', idAlumno);
        fd.append('id_alumno_especialidad', btn.dataset.ae);
        fd.append('motivo', motivo.trim());
        btn.disabled = true;
        try {
          const r = await fetch(api, { method: 'POST', body: fd });
          const data = await r.json();
          msg(data.message || '', data.status === 'ok');
          if (data.status === 'ok') await cargar();
        } catch (e) {
          msg('Error al condonar', false);
        }
        btn.disabled = false;
      });
    });
  }

  async function cargar() {
    if (listEl) listEl.innerHTML = '<p style="color:#666;">Cargando…</p>';
    try {
      const r = await fetch(api + '?action=listar&id_alumno=' + idAlumno);
      const data = await r.json();
      if (data.status !== 'ok') {
        msg(data.message || 'Error', false);
        return;
      }
      msg('', true);
      if (!data.items || !data.items.length) {
        listEl.innerHTML = '<p>Este alumno no tiene especialidades activas con colegiatura.</p>';
      } else {
        listEl.innerHTML = data.items.map(renderItem).join('');
        bindCards();
      }
      renderHistorial(data.historial || []);
      renderCondonaciones(data.condonaciones || []);
    } catch (e) {
      msg('No se pudo cargar la información', false);
    }
  }

  window.HayAlumnoTarifaSupervisorCargar = cargar;

  const nav = document.getElementById('alumno-tabs-nav');
  nav?.querySelectorAll('button[data-tab="colegiatura-supervisor"]').forEach((btn) => {
    btn.addEventListener('click', () => cargar());
  });
  if (nav?.querySelector('button[data-tab="colegiatura-supervisor"]')?.classList.contains('is-active')) {
    cargar();
  }
})();
