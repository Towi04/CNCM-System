(function () {
  const cfg = window.HAY_VP_CONFIG || {};
  const api = cfg.api || 'php/venta_productos_api.php';

  const selAlumno = document.getElementById('vp-sel-alumno');
  const inpCliente = document.getElementById('vp-cliente-nombre');
  const selProducto = document.getElementById('vp-sel-producto');
  const prodInfo = document.getElementById('vp-prod-info');
  const tbody = document.querySelector('#vp-tabla-carrito tbody');
  const elTotal = document.getElementById('vp-total');
  const btnTerminar = document.getElementById('vp-btn-terminar');
  const msg = document.getElementById('vp-msg');

  let productos = [];
  let carrito = [];

  function fmt(n) {
    return '$ ' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  async function cargarAlumnos() {
    const r = await fetch(api + '?action=alumnos', { credentials: 'same-origin' });
    const data = await r.json();
    selAlumno.innerHTML = '<option value="">Selecciona un alumno</option>';
    (data.alumnos || []).forEach((a) => {
      const o = document.createElement('option');
      o.value = a.id_alumno;
      o.textContent = (a.nombre_completo || '').trim() + (a.numero_control ? ' · ' + a.numero_control : '');
      selAlumno.appendChild(o);
    });
  }

  async function cargarProductos() {
    const r = await fetch(api + '?action=productos', { credentials: 'same-origin' });
    const data = await r.json();
    productos = data.productos || [];
    selProducto.innerHTML = '<option value="">Selecciona un producto</option>';
    productos.forEach((p) => {
      const o = document.createElement('option');
      o.value = p.id_producto;
      const extra = p.sin_limite ? ' · sin límite' : ' · stock ' + p.existencia;
      o.textContent = p.nombre + ' — ' + fmt(p.precio) + extra;
      o.dataset.producto = JSON.stringify(p);
      selProducto.appendChild(o);
    });
  }

  function actualizarInfoProducto() {
    const opt = selProducto.selectedOptions[0];
    if (!opt || !opt.dataset.producto) {
      prodInfo.hidden = true;
      return;
    }
    const p = JSON.parse(opt.dataset.producto);
    prodInfo.hidden = false;
    if (p.sin_limite) {
      prodInfo.textContent = 'Servicio sin control de inventario — siempre disponible.';
    } else {
      prodInfo.textContent = 'Existencia en plantel: ' + p.existencia + ' pieza(s).';
    }
  }

  function renderCarrito() {
    if (!carrito.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="pv-empty">No se encontró ningún registro</td></tr>';
      elTotal.textContent = fmt(0);
      btnTerminar.disabled = true;
      return;
    }

    tbody.innerHTML = '';
    let total = 0;
    carrito.forEach((item, idx) => {
      total += item.subtotal;
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td><button type="button" class="vp-btn-remove" data-idx="' + idx + '" title="Quitar"><i class="fas fa-trash"></i></button></td>' +
        '<td>' + item.nombre + (item.sin_limite ? '' : ' <small style="color:#888;">(máx. ' + item.max + ')</small>') + '</td>' +
        '<td><div class="vp-qty-cell">' +
        '<button type="button" class="vp-btn-qty" data-idx="' + idx + '" data-delta="-1">−</button>' +
        '<input type="number" min="1" value="' + item.cantidad + '" data-idx="' + idx + '" class="vp-inp-cant">' +
        '<button type="button" class="vp-btn-qty" data-idx="' + idx + '" data-delta="1">+</button>' +
        '</div></td>' +
        '<td>' + fmt(item.precio) + '</td>' +
        '<td>' + fmt(item.subtotal) + '</td>';
      tbody.appendChild(tr);
    });

    elTotal.textContent = fmt(total);
    btnTerminar.disabled = !puedeVender();
  }

  function puedeVender() {
    if (!carrito.length) return false;
    const idAl = parseInt(selAlumno.value, 10);
    const nombre = (inpCliente.value || '').trim();
    return idAl > 0 || nombre !== '';
  }

  function agregarProducto() {
    const opt = selProducto.selectedOptions[0];
    if (!opt || !opt.dataset.producto) return;
    const p = JSON.parse(opt.dataset.producto);
    const existente = carrito.find((c) => c.id_producto === p.id_producto);
    if (existente) {
      const max = p.sin_limite ? 9999 : p.existencia;
      if (existente.cantidad >= max) {
        alert('Cantidad máxima disponible: ' + max);
        return;
      }
      existente.cantidad += 1;
      existente.subtotal = existente.cantidad * existente.precio;
    } else {
      carrito.push({
        id_producto: p.id_producto,
        nombre: p.nombre,
        precio: parseFloat(p.precio) || 0,
        cantidad: 1,
        subtotal: parseFloat(p.precio) || 0,
        sin_limite: !!p.sin_limite,
        max: p.sin_limite ? 9999 : parseInt(p.existencia, 10) || 0,
      });
    }
    selProducto.value = '';
    prodInfo.hidden = true;
    renderCarrito();
  }

  function cambiarCantidad(idx, cant) {
    const item = carrito[idx];
    if (!item) return;
    const max = item.sin_limite ? 9999 : item.max;
    cant = Math.max(1, Math.min(max, cant));
    item.cantidad = cant;
    item.subtotal = round2(item.precio * cant);
    renderCarrito();
  }

  function round2(n) {
    return Math.round(n * 100) / 100;
  }

  selProducto.addEventListener('change', () => {
    actualizarInfoProducto();
    if (selProducto.value) agregarProducto();
  });

  selAlumno.addEventListener('change', () => {
    if (selAlumno.value) inpCliente.value = '';
    renderCarrito();
  });

  inpCliente.addEventListener('input', () => {
    if (inpCliente.value.trim()) selAlumno.value = '';
    renderCarrito();
  });

  tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const idx = parseInt(btn.getAttribute('data-idx'), 10);
    if (btn.classList.contains('vp-btn-remove')) {
      carrito.splice(idx, 1);
      renderCarrito();
      return;
    }
    if (btn.classList.contains('vp-btn-qty')) {
      const delta = parseInt(btn.getAttribute('data-delta'), 10);
      const item = carrito[idx];
      if (item) cambiarCantidad(idx, item.cantidad + delta);
    }
  });

  tbody.addEventListener('change', (e) => {
    if (e.target.classList.contains('vp-inp-cant')) {
      cambiarCantidad(parseInt(e.target.getAttribute('data-idx'), 10), parseInt(e.target.value, 10) || 1);
    }
  });

  document.getElementById('vp-form-venta').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!puedeVender()) {
      alert('Seleccione un alumno o escriba el nombre del comprador');
      return;
    }
    if (!carrito.length) {
      alert('Agregue productos al carrito');
      return;
    }

    const fd = new FormData();
    fd.append('action', 'vender');
    fd.append('id_alumno', selAlumno.value || '0');
    fd.append('cliente_nombre', inpCliente.value.trim());
    fd.append('forma_pago', document.getElementById('vp-forma').value || 'Efectivo');
    fd.append('items', JSON.stringify(carrito.map((c) => ({
      id_producto: c.id_producto,
      cantidad: c.cantidad,
    }))));

    btnTerminar.disabled = true;
    try {
      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await r.json();
      if (data.status !== 'ok') throw new Error(data.message || 'Error');
      showMsg(true, (data.message || 'Venta registrada') + ' — ' + (data.total_fmt || ''));
      carrito = [];
      renderCarrito();
      selAlumno.value = '';
      inpCliente.value = '';
      if (data.ticket_url) window.open(data.ticket_url, '_blank');
      await cargarProductos();
    } catch (err) {
      showMsg(false, err.message || 'Error al vender');
      btnTerminar.disabled = false;
    }
  });

  cargarAlumnos();
  cargarProductos();
})();
