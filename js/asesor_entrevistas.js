(function () {

  const cfg = window.HAY_ENTREVISTAS || {};

  const api = cfg.api || 'php/asesor_entrevista_api.php';



  function idAsesor() {
    const sel = document.getElementById('ent-asesor') || document.getElementById('ent-form-asesor');
    if (sel && sel.value) return parseInt(sel.value, 10);
    return cfg.idUsuario || 0;
  }



  function filtrosActuales() {

    return {

      periodo: document.getElementById('ent-periodo')?.value || 'semana',

      estado: document.getElementById('ent-filtro-estado')?.value || 'contacto',

    };

  }



  async function fetchJson(url) {

    const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });

    return r.json();

  }



  async function cargarStats() {

    const { periodo } = filtrosActuales();

    const box = document.getElementById('ent-stats');

    if (!box) return;

    try {

      const d = await fetchJson(

        api + '?action=estadisticas&periodo=' + encodeURIComponent(periodo)

        + '&id_usuario_asesor=' + idAsesor()

      );

      if (d.status !== 'ok') {

        box.style.display = 'none';

        return;

      }

      const s = d.stats || {};

      box.style.display = 'block';

      box.textContent =

        'Entrevistas: ' + (s.total_entrevistas || 0) +

        ' · En contacto: ' + (s.contacto || 0) +

        ' · Pre-registro: ' + (s.preregistro || 0) +

        ' · Inscritos: ' + (s.inscrito || 0);

    } catch (e) {

      box.style.display = 'none';

    }

  }



  async function cargarLista() {

    const { periodo, estado } = filtrosActuales();

    let url = api + '?action=listar&id_usuario_asesor=' + idAsesor()

      + '&periodo=' + encodeURIComponent(periodo);

    if (estado) url += '&estado=' + encodeURIComponent(estado);

    const d = await fetchJson(url);

    const tb = document.querySelector('#ent-tabla tbody');

    if (!tb) return;

    tb.innerHTML = '';

    (d.entrevistas || []).forEach((e) => {

      const tr = document.createElement('tr');

      const nombre = [e.nombres, e.apellido_paterno, e.apellido_materno].filter(Boolean).join(' ');

      const fecha = (e.creado_en || '').slice(0, 16).replace('T', ' ');

      tr.innerHTML =

        '<td>' + fecha + '</td>' +

        '<td>' + escapeHtml(nombre) + '</td>' +

        '<td>' + escapeHtml(e.telefono || '—') + '</td>' +

        '<td>' + escapeHtml(e.estado || '') + '</td>' +

        '<td>' + escapeHtml((e.observaciones || '').slice(0, 80)) + '</td>' +

        '<td><button type="button" class="secondary btn-ent-prereg" data-id="' + e.id_entrevista + '">Pre-registro</button></td>';

      tb.appendChild(tr);

    });

    tb.querySelectorAll('.btn-ent-prereg').forEach((btn) => {

      btn.addEventListener('click', async () => {

        const id = btn.dataset.id;

        const pre = await fetchJson(api + '?action=ir_preregistro&id_entrevista=' + id);

        if (pre.status !== 'ok') {

          alert(pre.message || 'Error');

          return;

        }

        const p = pre.prefill || {};

        const qs = new URLSearchParams();

        if (p.nombres) qs.set('nombres', p.nombres);

        if (p.apellido_paterno) qs.set('apellido_paterno', p.apellido_paterno);

        if (p.apellido_materno) qs.set('apellido_materno', p.apellido_materno);

        if (p.telefono) qs.set('telefono', p.telefono);

        if (p.email) qs.set('email', p.email);

        if (p.id_entrevista) qs.set('id_entrevista', p.id_entrevista);

        if (typeof cargarSeccion === 'function') {

          cargarSeccion('pre_registro_nuevo', qs);

        }

      });

    });

  }



  function escapeHtml(s) {

    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

  }



  async function aplicarFiltros() {

    await Promise.all([cargarStats(), cargarLista()]);

  }



  function bindEntrevistasUi() {

    const form = document.getElementById('ent-form');

    if (form && !form.dataset.hayBound) {

      form.dataset.hayBound = '1';

      form.addEventListener('submit', async (ev) => {

        ev.preventDefault();

        const fd = new FormData(ev.target);

        fd.append('action', 'guardar');

        const r = await fetch(api, {

          method: 'POST',

          body: fd,

          credentials: 'same-origin',

          headers: { 'X-Requested-With': 'fetch' },

        });

        const d = await r.json();

        alert(d.message || (d.status === 'ok' ? 'Guardado' : 'Error'));

        if (d.status === 'ok') {

          ev.target.reset();

          await aplicarFiltros();

        }

      });

    }



    const btnFiltrar = document.getElementById('ent-btn-filtrar');

    if (btnFiltrar && !btnFiltrar.dataset.hayBound) {

      btnFiltrar.dataset.hayBound = '1';

      btnFiltrar.addEventListener('click', () => { aplicarFiltros(); });

    }

    const selAsesor = document.getElementById('ent-asesor');
    if (selAsesor && !selAsesor.dataset.hayBound) {
      selAsesor.dataset.hayBound = '1';
      selAsesor.addEventListener('change', () => {
        const formSel = document.getElementById('ent-form-asesor');
        if (formSel) formSel.value = selAsesor.value;
        aplicarFiltros();
      });
    }

  }



  window.hayEntrevistasInit = function hayEntrevistasInit() {

    bindEntrevistasUi();

    aplicarFiltros();

  };

})();

