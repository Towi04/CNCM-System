(function () {

  const cfg = window.HayAcuerdoSupervisor || {};

  const api = cfg.api || 'php/acuerdo_escolar_api.php';

  const form = document.getElementById('form-acuerdo-publicar');

  const msg = document.getElementById('acuerdo-publicar-msg');

  const btn = document.getElementById('btn-acuerdo-publicar');

  const editor = document.getElementById('acuerdo-editor');

  const hiddenContenido = document.getElementById('acuerdo-contenido');

  function syncEditor() {
    if (editor && hiddenContenido) {
      hiddenContenido.value = editor.innerHTML.trim();
    }
  }

  document.querySelectorAll('.acuerdo-editor-toolbar [data-cmd]').forEach((button) => {
    button.addEventListener('click', () => {
      editor?.focus();
      document.execCommand(button.dataset.cmd, false, null);
      syncEditor();
    });
  });

  document.querySelectorAll('.acuerdo-editor-toolbar [data-block]').forEach((button) => {
    button.addEventListener('click', () => {
      editor?.focus();
      document.execCommand('formatBlock', false, button.dataset.block || 'p');
      syncEditor();
    });
  });

  editor?.addEventListener('keydown', (event) => {
    if (event.key === 'Tab') {
      event.preventDefault();
      document.execCommand(event.shiftKey ? 'outdent' : 'indent', false, null);
      syncEditor();
    }
  });

  editor?.addEventListener('input', syncEditor);



  form?.addEventListener('submit', async function (e) {

    e.preventDefault();

    syncEditor();

    const contenido = hiddenContenido?.value?.replace(/<[^>]*>/g, '').trim() || editor?.textContent?.trim() || '';

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


