(function () {
  const api = (window.HAY_GRUPOS_FASES || {}).api || 'php/asesor_grupos_fases_api.php';
  const estLabels = {
    cursando_ahora: 'Cursando ahora',
    por_entrar: 'Por entrar a la fase',
    programado: 'Programado',
    ya_paso: 'Ya pasó',
  };

  async function json(url) {
    const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
    return r.json();
  }

  function faseLabel(f) {
    const nombre = (f.nombre_fase || '').trim();
    if (nombre) return nombre;
    return (f.clave_fase || '').trim() || '—';
  }

  document.getElementById('gf-esp')?.addEventListener('change', async (ev) => {
    const id = ev.target.value;
    const sel = document.getElementById('gf-fase');
    if (!sel) return;
    sel.innerHTML = '<option value="">Cargando…</option>';
    sel.disabled = true;
    if (!id) {
      sel.innerHTML = '<option value="">Primero especialidad</option>';
      return;
    }
    const d = await json(api + '?action=fases&id_especialidad=' + id);
    sel.innerHTML = '<option value="">— Fase —</option>';
    (d.fases || []).forEach((f) => {
      const o = document.createElement('option');
      o.value = f.id_fase;
      o.textContent = faseLabel(f);
      sel.appendChild(o);
    });
    sel.disabled = false;
  });

  document.getElementById('gf-buscar')?.addEventListener('click', async () => {
    const idFase = document.getElementById('gf-fase')?.value;
    const idEsp = document.getElementById('gf-esp')?.value;
    if (!idFase) {
      alert('Seleccione una fase');
      return;
    }
    let url = api + '?action=buscar&id_fase=' + idFase;
    if (idEsp) url += '&id_especialidad=' + idEsp;
    const d = await json(url);
    const table = document.getElementById('gf-tabla');
    const tb = table?.querySelector('tbody');
    const res = document.getElementById('gf-resumen');
    const wrap = table?.closest('.catalog-table-wrap');
    if (window.HayDataTable?.destroyIn && wrap) {
      window.HayDataTable.destroyIn(wrap);
    }
    if (res) res.textContent = (d.total || 0) + ' grupo(s) encontrado(s)';
    if (!tb) return;
    tb.innerHTML = '';
    (d.grupos || []).forEach((g) => {
      const tr = document.createElement('tr');
      const faseTxt = (g.fase_nombre || g.fase_clave || '').trim();
      tr.innerHTML =
        '<td><strong>' + esc(g.clave) + '</strong></td>' +
        '<td>' + esc(g.esp_nombre) + '</td>' +
        '<td>' + esc(faseTxt) + '</td>' +
        '<td>' + esc(estLabels[g.estado_grupo] || g.estado_grupo) + '</td>' +
        '<td>' + esc(g.fecha_entrada_fase) + '</td>' +
        '<td>' + esc(g.fecha_inicio_grupo) + '</td>' +
        '<td>' + esc(g.horario) + '</td>' +
        '<td>' + esc(g.profesor_nombre) + '</td>' +
        '<td>' + esc(g.aula || '—') + '</td>';
      tb.appendChild(tr);
    });
    if ((d.grupos || []).length > 0 && window.HayDataTable) {
      window.HayDataTable.init('#gf-tabla', {
        order: [[4, 'asc']],
        searching: false,
        scrollX: false,
        scrollY: false,
        paging: (d.grupos || []).length > 25,
      });
    }
  });

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }
})();
