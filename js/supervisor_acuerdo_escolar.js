(function () {

  const cfg = window.HayAcuerdoSupervisor || {};

  const api = cfg.api || 'php/acuerdo_escolar_api.php';

  const form = document.getElementById('form-acuerdo-publicar');

  const msg = document.getElementById('acuerdo-publicar-msg');

  const btn = document.getElementById('btn-acuerdo-publicar');



  form?.addEventListener('submit', async function (e) {

    e.preventDefault();

    const contenido = document.getElementById('acuerdo-contenido')?.value?.trim() || '';

    if (!contenido) {

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'asist-checada-msg err';

        msg.textContent = 'Escriba el texto del acuerdo.';

      }

      return;

    }

    if (!confirm('¿Publicar nueva versión del acuerdo? Todos los alumnos activos deberán aceptarla.')) {

      return;

    }

    const fd = new FormData(form);

    fd.append('action', 'publicar');

    if (btn) btn.disabled = true;

    try {

      const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });

      const d = await r.json();

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'asist-checada-msg ' + (d.status === 'ok' ? 'ok' : 'err');

        msg.textContent = d.message || '';

      }

      if (d.status === 'ok') {

        setTimeout(() => {

          if (typeof cargarSeccion === 'function') cargarSeccion('supervisor_acuerdo_escolar');

        }, 1200);

      }

    } catch (err) {

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'asist-checada-msg err';

        msg.textContent = 'Error al publicar.';

      }

    }

    if (btn) btn.disabled = false;

  });

})();


