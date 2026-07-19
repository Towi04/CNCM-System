(function () {

  const cfg = window.HayAcuerdoSupervisor || {};

  const api = cfg.api || 'php/acuerdo_escolar_api.php';

  const form = document.getElementById('form-acuerdo-publicar');

  const msg = document.getElementById('acuerdo-publicar-msg');

  const btn = document.getElementById('btn-acuerdo-publicar');

  const contenidoField = document.getElementById('acuerdo-contenido');
  const editorInline = document.getElementById('acuerdo-editor-inline');
  let editorTiny = null;

  function syncEditor() {
    if (editorTiny && contenidoField) {
      contenidoField.value = editorTiny.getContent().trim();
    } else if (editorInline && contenidoField && editorInline.style.display !== 'none') {
      contenidoField.value = editorInline.innerHTML.trim();
    }
  }

  let tinyInicializado = false;

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function initialEditorContent() {
    const raw = contenidoField?.value || '';
    if (/<(p|div|br|ul|ol|li|strong|b|em|i|u|blockquote|h[1-6])\b/i.test(raw)) {
      return raw;
    }
    return escapeHtml(raw).replace(/\r?\n/g, '<br>');
  }

  function initTinyMce() {
    if (tinyInicializado || !window.tinymce || !contenidoField || !editorInline) {
      return;
    }
    const initialContent = initialEditorContent();
    tinyInicializado = true;
    editorInline.innerHTML = initialContent || '<p><br></p>';
    editorInline.style.display = 'block';
    contenidoField.style.display = 'none';
    window.tinymce.init({
      selector: '#acuerdo-editor-inline',
      inline: true,
      menubar: false,
      branding: false,
      promotion: false,
      license_key: 'gpl',
      plugins: 'lists code',
      toolbar: 'undo redo | blocks | bold italic underline | bullist numlist outdent indent | removeformat | code',
      toolbar_persist: true,
      block_formats: 'Párrafo=p; Título=h3; Subtítulo=h4; Cita=blockquote',
      content_style: 'body{font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;} ul,ol{padding-left:24px;}',
      invalid_elements: 'script,style,iframe,object,embed,form,input,button,textarea,select',
      extended_valid_elements: 'p,div,br,ul,ol,li,strong/b,em/i,u,blockquote,h1,h2,h3,h4,h5,h6',
      setup(editor) {
        editorTiny = editor;
        editor.on('init', () => {
          editor.setContent(initialContent || '<p><br></p>');
          syncEditor();
        });
        editor.on('change keyup undo redo input', syncEditor);
      },
    });
  }

  function cargarTinyFallback() {
    if (window.tinymce || document.getElementById('tinymce-fallback-script')) {
      initTinyMce();
      return;
    }
    const script = document.createElement('script');
    script.id = 'tinymce-fallback-script';
    script.src = 'https://unpkg.com/tinymce@7/tinymce.min.js';
    script.referrerPolicy = 'origin';
    script.onload = initTinyMce;
    document.head.appendChild(script);
  }

  if (window.tinymce) {
    initTinyMce();
  } else {
    setTimeout(cargarTinyFallback, 1200);
  }



  form?.addEventListener('submit', async function (e) {

    e.preventDefault();

    syncEditor();

    const contenidoHtml = contenidoField?.value?.trim() || '';
    const contenido = contenidoHtml.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();

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


