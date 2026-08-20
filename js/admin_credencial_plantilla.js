(function () {
  'use strict';

  const cfg = window.HAY_CRED_PLANTILLA || {};
  const api = cfg.api || 'php/credencial_api.php';
  let idActual = 0;
  let lado = 'frente';
  let campos = {
    frente: Array.isArray(cfg.defaultsFrente) ? structuredClone(cfg.defaultsFrente) : [],
    reverso: Array.isArray(cfg.defaultsReverso) ? structuredClone(cfg.defaultsReverso) : [],
  };
  let fondos = { frente: '', reverso: '' };

  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function asset(path) {
    if (!path) return '';
    if (/^(https?:|data:|blob:)/i.test(path)) return path;
    return (cfg.assetRoot || '/').replace(/\/?$/, '/') + String(path).replace(/^\/+/, '');
  }

  function leerCampos() {
    campos[lado] = [...document.querySelectorAll('.cred-pl-campo-row')].map((row) => ({
      campo: row.querySelector('.c-campo')?.value || '',
      x_mm: parseFloat(row.querySelector('.c-x')?.value || '0'),
      y_mm: parseFloat(row.querySelector('.c-y')?.value || '0'),
      font_size: parseFloat(row.querySelector('.c-fs')?.value || '8'),
      align: row.querySelector('.c-align')?.value || 'left',
      width_mm: parseFloat(row.querySelector('.c-w')?.value || '0'),
    }));
  }

  function renderCampos() {
    const box = document.getElementById('cred-pl-campos-list');
    if (!box) return;
    const catalogo = cfg.campos || {};
    box.innerHTML = (campos[lado] || []).map((campo, i) => {
      const options = Object.entries(catalogo).map(([key, meta]) =>
        `<option value="${esc(key)}"${key === campo.campo ? ' selected' : ''}>${esc(meta.label || key)}</option>`
      ).join('');
      return `<div class="doc-pl-campo-row cred-pl-campo-row" data-i="${i}">
        <select class="c-campo">${options}</select>
        <input type="number" class="c-x" value="${Number(campo.x_mm || 0)}" step=".5" title="X mm" placeholder="X">
        <input type="number" class="c-y" value="${Number(campo.y_mm || 0)}" step=".5" title="Y mm" placeholder="Y">
        <input type="number" class="c-fs" value="${Number(campo.font_size || 8)}" step=".5" min="4" title="Tamaño pt">
        <select class="c-align">
          <option value="left"${campo.align === 'left' ? ' selected' : ''}>Izq</option>
          <option value="center"${campo.align === 'center' ? ' selected' : ''}>Centro</option>
          <option value="right"${campo.align === 'right' ? ' selected' : ''}>Der</option>
        </select>
        <input type="number" class="c-w" value="${Number(campo.width_mm || 0)}" step=".5" title="Ancho mm" placeholder="W">
        <button type="button" class="secondary btn-del-c">×</button>
      </div>`;
    }).join('');
    box.querySelectorAll('input,select').forEach((input) => input.addEventListener('input', () => {
      leerCampos();
      renderPreview();
    }));
    box.querySelectorAll('.btn-del-c').forEach((button) => button.addEventListener('click', () => {
      campos[lado].splice(Number(button.closest('.cred-pl-campo-row')?.dataset.i), 1);
      renderCampos();
      renderPreview();
    }));
  }

  function textoMuestra(campo) {
    const muestras = {
      numero_control: 'PE-2026-001',
      nombre_completo: 'NOMBRE DEL ALUMNO',
      especialidad: 'PREPARATORIA / ESPECIALIDAD',
      cct: 'CCT 11ABC0123X',
      rvoe: 'RVOE 2026-001',
      vigencia: '20/08/2026 al 20/08/2027',
      plantel_nombre: 'PLANTEL CNCM',
      foto: 'FOTO',
      qr_verificacion: 'QR',
    };
    return muestras[campo] || campo;
  }

  function renderPreview() {
    const preview = document.getElementById('cred-pl-preview');
    if (!preview) return;
    const ancho = Math.max(40, parseFloat(document.getElementById('cred-pl-ancho')?.value || '85.6'));
    const alto = Math.max(30, parseFloat(document.getElementById('cred-pl-alto')?.value || '54'));
    const scale = Math.min(7, 600 / ancho);
    preview.style.cssText = `position:relative;width:${ancho * scale}px;height:${alto * scale}px;overflow:hidden;background:#fff;border:1px solid #bbb;`;
    const fondo = fondos[lado];
    preview.innerHTML = fondo
      ? `<img src="${esc(asset(fondo))}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:fill;">`
      : '';
    (campos[lado] || []).forEach((campo) => {
      const el = document.createElement('div');
      const width = Number(campo.width_mm || 0);
      el.textContent = textoMuestra(campo.campo);
      el.style.cssText = [
        'position:absolute',
        `left:${Number(campo.x_mm || 0) * scale}px`,
        `top:${Number(campo.y_mm || 0) * scale}px`,
        `font-size:${Math.max(7, Number(campo.font_size || 8) * 1.25)}px`,
        `text-align:${campo.align || 'left'}`,
        width > 0 ? `width:${width * scale}px` : '',
        'overflow:hidden',
        'z-index:2',
        'color:#111',
      ].filter(Boolean).join(';');
      if (campo.campo === 'foto' || campo.campo === 'qr_verificacion') {
        const side = (width || (campo.campo === 'foto' ? 21 : 18)) * scale;
        el.style.width = side + 'px';
        el.style.height = (campo.campo === 'foto' ? side * 1.25 : side) + 'px';
        el.style.display = 'grid';
        el.style.placeItems = 'center';
        el.style.border = '1px dashed #777';
        el.style.background = 'rgba(255,255,255,.75)';
      }
      preview.appendChild(el);
    });
  }

  function llenarSelect() {
    const select = document.getElementById('cred-pl-select');
    if (!select) return;
    select.innerHTML = '<option value="">— Nueva —</option>';
    (cfg.plantillas || []).forEach((plantilla) => {
      const option = document.createElement('option');
      option.value = plantilla.id_plantilla;
      option.textContent = (Number(plantilla.activo) ? '' : '[Inactiva] ') + plantilla.nombre;
      select.appendChild(option);
    });
  }

  function resetNueva() {
    idActual = 0;
    campos.frente = structuredClone(cfg.defaultsFrente || []);
    campos.reverso = structuredClone(cfg.defaultsReverso || []);
    fondos = { frente: '', reverso: '' };
    document.getElementById('cred-pl-nombre').value = '';
    document.getElementById('cred-pl-ancho').value = '85.6';
    document.getElementById('cred-pl-alto').value = '54';
    document.getElementById('cred-pl-activo').checked = true;
    renderCampos();
    renderPreview();
  }

  async function cargar(id) {
    if (!id) {
      resetNueva();
      return;
    }
    const response = await fetch(api + '?accion=obtener&id_plantilla=' + encodeURIComponent(id), { credentials: 'same-origin' });
    const data = await response.json();
    if (data.status !== 'ok') {
      alert(data.message || 'No se pudo cargar');
      return;
    }
    const plantilla = data.plantilla;
    idActual = Number(plantilla.id_plantilla);
    document.getElementById('cred-pl-nombre').value = plantilla.nombre || '';
    document.getElementById('cred-pl-ancho').value = plantilla.ancho_mm || '85.6';
    document.getElementById('cred-pl-alto').value = plantilla.alto_mm || '54';
    document.getElementById('cred-pl-activo').checked = Number(plantilla.activo) === 1;
    campos.frente = Array.isArray(plantilla.campos_frente_json) ? plantilla.campos_frente_json : [];
    campos.reverso = Array.isArray(plantilla.campos_reverso_json) ? plantilla.campos_reverso_json : [];
    fondos.frente = plantilla.fondo_frente_path || '';
    fondos.reverso = plantilla.fondo_reverso_path || '';
    renderCampos();
    renderPreview();
  }

  function cambiarLado(nuevo) {
    leerCampos();
    lado = nuevo;
    document.getElementById('cred-lado-label').textContent = nuevo;
    document.querySelectorAll('.cred-lado-btn').forEach((button) => {
      button.className = button.dataset.lado === nuevo ? 'primary cred-lado-btn' : 'secondary cred-lado-btn';
    });
    renderCampos();
    renderPreview();
  }

  async function guardar() {
    leerCampos();
    const fd = new FormData();
    fd.append('accion', 'guardar');
    if (idActual) fd.append('id_plantilla', String(idActual));
    fd.append('nombre', document.getElementById('cred-pl-nombre')?.value || '');
    fd.append('ancho_mm', document.getElementById('cred-pl-ancho')?.value || '85.6');
    fd.append('alto_mm', document.getElementById('cred-pl-alto')?.value || '54');
    if (document.getElementById('cred-pl-activo')?.checked) fd.append('activo', '1');
    fd.append('campos_frente_json', JSON.stringify(campos.frente));
    fd.append('campos_reverso_json', JSON.stringify(campos.reverso));
    const frente = document.getElementById('cred-pl-fondo-frente')?.files?.[0];
    const reverso = document.getElementById('cred-pl-fondo-reverso')?.files?.[0];
    if (frente) fd.append('fondo_frente', frente);
    if (reverso) fd.append('fondo_reverso', reverso);
    const response = await fetch(api, { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await response.json();
    alert(data.message || '');
    if (data.status === 'ok') {
      location.reload();
    }
  }

  document.getElementById('cred-pl-select')?.addEventListener('change', (event) => cargar(event.target.value));
  document.querySelectorAll('.cred-lado-btn').forEach((button) =>
    button.addEventListener('click', () => cambiarLado(button.dataset.lado))
  );
  document.getElementById('btn-cred-pl-add-campo')?.addEventListener('click', () => {
    leerCampos();
    campos[lado].push({ campo: 'nombre_completo', x_mm: 5, y_mm: 5, font_size: 8, align: 'left', width_mm: 40 });
    renderCampos();
    renderPreview();
  });
  document.getElementById('btn-cred-pl-guardar')?.addEventListener('click', guardar);
  ['cred-pl-ancho', 'cred-pl-alto'].forEach((id) => document.getElementById(id)?.addEventListener('input', renderPreview));
  ['frente', 'reverso'].forEach((side) => {
    document.getElementById('cred-pl-fondo-' + side)?.addEventListener('change', (event) => {
      const file = event.target.files?.[0];
      if (file) {
        fondos[side] = URL.createObjectURL(file);
        if (lado === side) renderPreview();
      }
    });
  });

  llenarSelect();
  renderCampos();
  renderPreview();
})();
