(function () {
  const cfg = window.HAY_PREREG_COMISION || {};
  const api = cfg.api || 'php/preregistro_asesor_api.php';
  const modal = document.getElementById('modal-prereg-comision');
  if (!modal) return;

  const $ = (id) => document.getElementById(id);
  let opcionesAsesor = [];

  async function fetchJson(url, opts) {
    const res = await fetch(url, Object.assign({ headers: { 'X-Requested-With': 'fetch' } }, opts || {}));
    return res.json();
  }

  async function cargarOpciones() {
    const data = await fetchJson(api + '?action=opciones');
    opcionesAsesor = data.asesores || [];
    const sel = $('prc-asesor');
    if (!sel) return;
    sel.innerHTML = opcionesAsesor.map((a) => {
      const inact = a.activo === false ? ' (inactivo)' : '';
      return '<option value="' + a.id_usuario + '">' + escapeHtml(a.nombre + inact) + '</option>';
    }).join('');
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function limpiarEntrevista() {
    $('prc-id-entrevista').value = '0';
    $('prc-ent-seleccionada').style.display = 'none';
    $('prc-ent-resultados').innerHTML = '';
    $('prc-buscar-ent').value = '';
  }

  async function abrirModal(idPrereg) {
    limpiarEntrevista();
    $('prc-msg').style.display = 'none';
    $('prc-motivo').value = '';
    $('prc-id').value = idPrereg;
    const det = await fetchJson(api + '?action=detalle&id_preregistro=' + encodeURIComponent(idPrereg));
    if (det.status !== 'ok') {
      alert(det.message || 'Error');
      return;
    }
    const p = det.preregistro || {};
    $('prc-prospecto').textContent = p.nombre || '';
    $('prc-captura').textContent = 'Capturó: ' + (p.captura_nombre || '—');
    await cargarOpciones();
    const sel = $('prc-asesor');
    if (p.comision_cncm) {
      sel.value = '0';
    } else if (p.id_usuario_asesor) {
      sel.value = String(p.id_usuario_asesor);
    } else if (p.entrevista && p.entrevista.id_entrevista) {
      const idEntAsesor = p.resolver && p.resolver.id ? String(p.resolver.id) : '';
      if (idEntAsesor && [...sel.options].some((o) => o.value === idEntAsesor)) {
        sel.value = idEntAsesor;
      }
    }
    if (p.id_entrevista_origen && p.entrevista) {
      $('prc-id-entrevista').value = String(p.id_entrevista_origen);
      $('prc-ent-seleccionada').style.display = 'block';
      $('prc-ent-seleccionada').textContent =
        'Vinculada: ' + p.entrevista.nombre + ' — ' + p.entrevista.asesor_nombre + ' (' + p.entrevista.fecha + ')';
    }
    modal.classList.add('is-open');
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-comision-prereg');
    if (!btn) return;
    const id = btn.dataset.id;
    if (id) abrirModal(id);
  });

  $('prc-cerrar')?.addEventListener('click', () => modal.classList.remove('is-open'));

  $('prc-btn-buscar-ent')?.addEventListener('click', async () => {
    const q = $('prc-buscar-ent').value.trim();
    if (q.length < 2) {
      alert('Escriba al menos 2 caracteres');
      return;
    }
    const data = await fetchJson(api + '?action=buscar_entrevistas&q=' + encodeURIComponent(q));
    const box = $('prc-ent-resultados');
    const items = data.items || [];
    if (!items.length) {
      box.innerHTML = '<p style="color:#888;font-size:0.85rem;">Sin resultados</p>';
      return;
    }
    box.innerHTML = items.map((it) => {
      const vinc = it.id_preregistro_vinculado > 0 ? ' · ya vinculada' : '';
      return '<button type="button" class="secondary prc-pick-ent" style="display:block;width:100%;text-align:left;margin-bottom:6px;" data-id="' + it.id_entrevista + '" data-asesor="' + it.id_usuario_asesor + '">' +
        escapeHtml(it.nombre) + ' · ' + escapeHtml(it.telefono || '') + '<br><small>' +
        escapeHtml(it.asesor_nombre) + ' — ' + escapeHtml(it.fecha) + vinc + '</small></button>';
    }).join('');
    box.querySelectorAll('.prc-pick-ent').forEach((b) => {
      b.addEventListener('click', () => {
        if (b.textContent.includes('ya vinculada') && !confirm('Esta entrevista ya tiene pre-registro. ¿Vincular de todos modos?')) {
          return;
        }
        $('prc-id-entrevista').value = b.dataset.id;
        $('prc-ent-seleccionada').style.display = 'block';
        $('prc-ent-seleccionada').textContent = 'Seleccionada: ' + b.textContent.replace(/\s+/g, ' ').trim();
        const idAs = b.dataset.asesor;
        if (idAs && $('prc-asesor')) {
          $('prc-asesor').value = idAs;
        }
      });
    });
  });

  $('prc-guardar')?.addEventListener('click', async () => {
    const id = $('prc-id').value;
    const idAsesor = $('prc-asesor').value;
    const cncm = idAsesor === '0';
    const fd = new FormData();
    fd.append('action', 'asignar');
    fd.append('id_preregistro', id);
    if (cncm) {
      fd.append('comision_cncm', '1');
    } else if (idAsesor) {
      fd.append('id_usuario_asesor', idAsesor);
    }
    const idEnt = $('prc-id-entrevista').value;
    if (idEnt && idEnt !== '0') fd.append('id_entrevista', idEnt);
    fd.append('motivo', $('prc-motivo').value.trim());
    const data = await fetchJson(api, { method: 'POST', body: fd });
    const msg = $('prc-msg');
    msg.style.display = 'block';
    msg.style.color = data.status === 'ok' ? '#2e7d32' : '#c62828';
    msg.textContent = data.message || '';
    if (data.status === 'ok') {
      setTimeout(() => {
        modal.classList.remove('is-open');
        if (typeof cargarSeccion === 'function') cargarSeccion('pre_registro_alumnos');
      }, 600);
    }
  });

  cargarOpciones();
})();
