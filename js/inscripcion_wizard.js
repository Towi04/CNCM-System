/**
 * Inscripción en un paso: grupo + cobro (si hay saldo) + ticket + confirmación.
 */
(function () {
  const modal = () => document.getElementById('modal-inscripcion-wizard');
  const msgEl = () => $insc('insc-wizard-msg');
  const resumenEl = () => $insc('insc-wizard-resumen');
  const selGrupo = () => $insc('insc-wizard-grupo');
  const saldoNode = () => $insc('insc-wizard-saldo');
  const pagoWrap = () => $insc('insc-wizard-pago-wrap');
  const montoInp = () => $insc('insc-wizard-monto');
  const formaSel = () => document.getElementById('insc-wizard-forma-pago');
  const avisoGrupo = () => document.getElementById('insc-wizard-grupo-aviso');
  const hiddenVal = (id) => ($insc(id)?.value ?? '');

  /** Elemento del modal activo (evita IDs duplicados tras navegación AJAX). */
  function $insc(id) {
    const m = modal();
    if (m) {
      const el = m.querySelector('#' + id);
      if (el) return el;
    }
    return document.getElementById(id);
  }

  let onSuccess = null;
  let descuentoSelectBound = null;
  let busy = false;
  let saldoPendiente = 0;
  let asesoriaTardiaMeta = null;
  let costoSemanaExtra = 0;
  let esCursoPersonalizado = false;
  let esModoUbicacion = false;
  let examenesUbicacion = [];
  let gruposPersonalizados = [];
  let gruposCatalogo = null;
  let requiereAutorizacionEdad = false;
  let mensajeAutorizacionEdad = '';
  let puedeAutorizarEdadSesion = false;
  let usernameSesion = '';
  let reglasDescuento = [];
  let resumenBase = null;
  let comboDetalleActual = null;

  function optPersonalizado() {
    return !!$insc('insc-opt-personalizado')?.checked;
  }

  function optAvanzado() {
    return !!document.getElementById('insc-opt-avanzado')?.checked;
  }

  function optDescuento() {
    return !!document.getElementById('insc-opt-descuento')?.checked;
  }

  function optRecomendado() {
    return !!document.getElementById('insc-opt-recomendado')?.checked;
  }

  function optUbicacion() {
    return !!$insc('insc-opt-ubicacion')?.checked;
  }

  function clearDescuento() {
    setHidden('insc-wizard-id-regla-desc', 0);
    comboDetalleActual = null;
    const aviso = document.getElementById('insc-wizard-descuento-aviso');
    if (aviso) aviso.textContent = '';
    const sel = document.getElementById('insc-wizard-regla-desc');
    if (sel) sel.value = '';
    const comboBox = document.getElementById('insc-wizard-combo-grupos');
    if (comboBox) {
      comboBox.innerHTML = '';
      comboBox.style.display = 'none';
    }
    restaurarResumenRegular();
  }

  function clearReferidor() {
    setHidden('insc-wizard-ref-id', 0);
    const q = document.getElementById('insc-wizard-ref-q');
    if (q) q.value = '';
    const selLabel = document.getElementById('insc-wizard-ref-seleccionado');
    if (selLabel) { selLabel.style.display = 'none'; selLabel.textContent = ''; }
    const preview = document.getElementById('insc-wizard-ref-preview');
    if (preview) preview.textContent = '';
  }

  function updateGrupoModoHint() {
    const hint = document.getElementById('insc-wizard-grupo-modo-hint');
    if (!hint) return;
    hint.textContent = optAvanzado()
      ? 'Grupos en curso (avanzado): el alumno entra a un grupo que ya lleva clases.'
      : 'Grupos próximos a iniciar de la especialidad del alumno.';
  }

  function syncOpcionesChecks() {
    const avz = document.getElementById('insc-opt-avanzado');
    const desc = document.getElementById('insc-opt-descuento');
    const ub = $insc('insc-opt-ubicacion');
    if (esCursoPersonalizado || esModoUbicacion) {
      if (avz) { avz.checked = false; avz.disabled = true; }
      if (desc) { desc.checked = false; desc.disabled = true; }
      const dbox = document.getElementById('insc-wizard-descuento-box');
      if (dbox) dbox.style.display = 'none';
      clearDescuento();
    } else {
      if (avz) avz.disabled = false;
      if (desc) desc.disabled = false;
    }
    if (esCursoPersonalizado && ub) {
      ub.checked = false;
      ub.disabled = true;
    } else if (ub) {
      ub.disabled = false;
    }
    if (esModoUbicacion) {
      const per = document.getElementById('insc-opt-personalizado');
      if (per) { per.checked = false; per.disabled = true; }
    } else {
      const per = document.getElementById('insc-opt-personalizado');
      if (per && !esCursoPersonalizado) per.disabled = false;
    }
    updateGrupoModoHint();
  }

  function fmt(n) {
    return '$' + Number(n || 0).toFixed(2);
  }

  function showMsg(ok, text) {
    const el = msgEl();
    if (!el) return;
    el.style.display = 'block';
    el.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    el.textContent = text || '';
  }

  function hideMsg() {
    const el = msgEl();
    if (el) {
      el.style.display = 'none';
      el.textContent = '';
    }
  }

  function portalModal() {
    const m = modal();
    if (m && m.parentElement !== document.body) {
      document.body.appendChild(m);
    }
  }

  function imprimirTicket(ticketUrl, grupoClave, nombreVentana) {
    if (!ticketUrl) return;
    let url = ticketUrl;
    if (grupoClave) {
      url += (url.includes('?') ? '&' : '?') + 'grupo=' + encodeURIComponent(grupoClave);
    }
    const w = window.open(url, nombreVentana || 'ticket_inscripcion', 'width=420,height=640,scrollbars=yes');
    if (!w) {
      alert('Permita ventanas emergentes para imprimir el comprobante.');
    }
  }

  function modalTieneDatos() {
    const authUser = document.getElementById('insc-wizard-auth-user')?.value?.trim();
    const authPass = document.getElementById('insc-wizard-auth-pass')?.value;
    const idGr = selGrupo()?.value;
    const costoPer = document.getElementById('insc-wizard-costo-personalizado')?.value?.trim();
    const monto = montoInp()?.value?.trim();
    const idGrPer = document.getElementById('insc-wizard-grupo-per')?.value;
    return !!(
      idGr || costoPer || monto || idGrPer || authUser || authPass ||
      optPersonalizado() || optAvanzado() || optDescuento() || optRecomendado() || optUbicacion()
    );
  }

  function intentarCerrarModal() {
    if (modalTieneDatos() && !window.confirm('¿Salir sin completar la inscripción? Se perderán los datos capturados.')) {
      return;
    }
    closeModal();
  }

  function wireModal() {
    portalModal();
  }

  function onWizardOptionChange(e) {
    const t = e.target;
    if (!t || !t.closest('#modal-inscripcion-wizard')) return;

    if (t.id === 'insc-opt-personalizado') {
      aplicarModoPersonalizado(t.checked);
      syncOpcionesChecks();
      return;
    }

    if (t.id === 'insc-opt-avanzado') {
      if (esCursoPersonalizado) {
        t.checked = false;
        return;
      }
      renderGruposSelect();
      refreshAsesoriaTardia();
      return;
    }

    if (t.id === 'insc-wizard-grupo') {
      refreshAsesoriaTardia();
      return;
    }

    if (t.id === 'insc-asesoria-semana-extra' || t.id === 'insc-asesoria-exonerar-semana') {
      syncSemanaExtraMonto();
      return;
    }

    if (t.id === 'insc-opt-descuento') {
      const box = $insc('insc-wizard-descuento-box');
      if (box) box.style.display = t.checked ? '' : 'none';
      if (!t.checked) {
        clearDescuento();
      } else {
        const idRegla = parseInt(hiddenVal('insc-wizard-id-regla-desc'), 10);
        if (idRegla > 0) handleDescuentoSelect();
        else restaurarResumenRegular();
      }
      return;
    }

    if (t.id === 'insc-opt-recomendado') {
      const box = document.getElementById('insc-wizard-referidor-box');
      if (box) box.style.display = t.checked ? '' : 'none';
      if (!t.checked) clearReferidor();
      return;
    }

    if (t.id === 'insc-opt-ubicacion') {
      aplicarModoUbicacion(t.checked);
      syncOpcionesChecks();
    }
  }

  async function loadExamenesUbicacion(idEsp) {
    const sel = $insc('insc-wizard-examen-ubicacion');
    if (!sel || !idEsp) return;
    sel.innerHTML = '<option value="">— Cargando exámenes… —</option>';
    try {
      const { data } = await hayFetchJson(
        'php/inscripcion_wizard_api.php?action=examenes_ubicacion&id_especialidad=' + idEsp
      );
      examenesUbicacion = data.examenes || [];
      sel.innerHTML = '<option value="">— Seleccione examen —</option>';
      if (!examenesUbicacion.length) {
        sel.innerHTML = '<option value="">— Sin exámenes configurados —</option>';
        return;
      }
      examenesUbicacion.forEach((ex) => {
        const o = document.createElement('option');
        o.value = ex.id_examen;
        let txt = ex.nombre || 'Examen';
        if (ex.nombre_fase) txt += ' · ' + ex.nombre_fase;
        if (ex.descripcion) o.title = ex.descripcion;
        o.textContent = txt;
        sel.appendChild(o);
      });
    } catch (e) {
      sel.innerHTML = '<option value="">— Error al cargar exámenes —</option>';
    }
  }

  function aplicarModoUbicacion(si) {
    esModoUbicacion = !!si;
    const box = $insc('insc-wizard-ubicacion-box');
    const grupoWrap = document.getElementById('insc-wizard-grupo-wrap');
    const hint = document.getElementById('insc-wizard-grupo-modo-hint');
    const btn = document.getElementById('insc-wizard-inscribir');

    if (box) box.style.display = si ? '' : 'none';
    if (grupoWrap) grupoWrap.style.display = si ? 'none' : '';
    if (hint) hint.style.display = si ? 'none' : '';

    if (si) {
      const idEsp = parseInt(hiddenVal('insc-wizard-id-especialidad'), 10);
      loadExamenesUbicacion(idEsp);
      const sel = selGrupo();
      if (sel) sel.value = '';
      if (btn) btn.textContent = saldoPendiente > 0.009 ? 'Cobrar e inscribir (ubicación)' : 'Inscribir por ubicación';
    } else {
      const selEx = $insc('insc-wizard-examen-ubicacion');
      if (selEx) selEx.value = '';
      refreshPagoSectionUI();
    }
  }

  function renderResumenHtml(data, modoPersonalizado) {
    if (!data) return '';
    let html =
      '<strong>' + (data.nombre || 'Alumno') + '</strong><br>' +
      (data.numero_control ? 'No. control: ' + data.numero_control + '<br>' : (data.es_prospecto ? '<span style="color:#888;">Sin número de control (se asigna al inscribir)</span><br>' : '')) +
      'Especialidad: ' + (data.especialidad || '—') + '<br>';
    if (!modoPersonalizado) {
      if (data.descuento_aplicado_preview && data.costo_inscripcion_base > 0) {
        html += 'Inscripción: <strong>' + fmt(data.costo_inscripcion) + '</strong>';
        html += ' <span style="color:#2e7d32;">(con descuento, tarifa normal ' + fmt(data.costo_inscripcion_base) + ')</span>';
        if (data.regla_descuento) {
          html += '<br>Colegiatura: ' + data.regla_descuento;
        }
      } else {
        html += 'Inscripción: ' + fmt(data.costo_inscripcion);
      }
      if ((data.monto_apartado || 0) > 0) {
        html += '<br>Apartado registrado: ' + fmt(data.monto_apartado);
        if ((data.apartado_aplicado || 0) > 0) {
          html += ' <span style="color:#2e7d32;">(aplicado a inscripción)</span>';
        }
      }
      if ((data.pagado_inscripcion || 0) > 0) {
        html += '<br>Pagado inscripción: ' + fmt(data.pagado_inscripcion);
      }
      const saldo = parseFloat(data.saldo_inscripcion || 0);
      html += '<br><strong>Saldo pendiente: ' + fmt(saldo) + '</strong>';
      if (saldo <= 0.009) {
        html += '<br><span style="color:#2e7d32;">Inscripción cubierta — solo elija el grupo.</span>';
      } else if ((data.apartado_aplicado || 0) > 0 && (data.monto_apartado || 0) > 0) {
        html += '<br><span style="color:#1565c0;">Apartado aplicado — falta cobrar el resto de la inscripción.</span>';
      }
    } else {
      html += '<span style="color:#1565c0;">Curso personalizado — no aplica cobro de inscripción.</span>';
      const credito = parseFloat(data.credito_apartado_pendiente || 0);
      if (credito > 0) {
        html += '<br>Crédito de apartado disponible: ' + fmt(credito);
      }
    }
    return html;
  }

  function buildResumenDescuentoLocal(tarifa, nombreRegla, totalesRegla) {
    if (!resumenBase || !tarifa) return null;
    const costoDesc = parseFloat(tarifa.costo_inscripcion ?? 0);
    if (!Number.isFinite(costoDesc) || costoDesc < 0) return null;
    let costoBase = parseFloat(resumenBase.costo_inscripcion || 0);
    if (totalesRegla && parseFloat(totalesRegla.referencia || 0) > costoDesc) {
      costoBase = parseFloat(totalesRegla.referencia);
    } else if (parseFloat(tarifa.costo_inscripcion_referencia || 0) > costoDesc) {
      costoBase = parseFloat(tarifa.costo_inscripcion_referencia);
    } else if (parseFloat(tarifa.esp_inscripcion_referencia || 0) > costoDesc) {
      costoBase = parseFloat(tarifa.esp_inscripcion_referencia);
    }
    const pagado = parseFloat(resumenBase.pagado_inscripcion || 0);
    const creditoApartado = parseFloat(resumenBase.credito_apartado_pendiente || 0);
    let saldo = Math.max(0, Math.round((costoDesc - pagado) * 100) / 100);
    if (creditoApartado > 0) {
      saldo = Math.max(0, Math.round((saldo - creditoApartado) * 100) / 100);
    }
    return Object.assign({}, resumenBase, {
      costo_inscripcion: costoDesc,
      costo_inscripcion_base: parseFloat(resumenBase.costo_inscripcion || 0),
      saldo_inscripcion: saldo,
      inscripcion_cubierta: saldo <= 0.009,
      descuento_aplicado_preview: true,
      regla_descuento: nombreRegla || '',
    });
  }

  function aplicarResumenDescuento(data) {
    if (!data) return;
    saldoPendiente = parseFloat(data.saldo_inscripcion || 0);
    if (!Number.isFinite(saldoPendiente)) saldoPendiente = 0;
    const elSaldo = saldoNode();
    if (elSaldo) {
      elSaldo.textContent = fmt(saldoPendiente);
      elSaldo.className = 'insc-wizard-saldo' + (saldoPendiente <= 0.009 ? ' insc-wizard-saldo--ok' : '');
    }
    const monto = montoInp();
    if (monto) monto.value = saldoPendiente > 0 ? saldoPendiente.toFixed(2) : '';
    const resumen = resumenEl();
    if (resumen) resumen.innerHTML = renderResumenHtml(data, false);
    refreshPagoLabels(false);
    togglePagoSection();
  }

  function restaurarResumenRegular() {
    if (!resumenBase) return;
    saldoPendiente = parseFloat(resumenBase.saldo_inscripcion || 0);
    if (!Number.isFinite(saldoPendiente)) saldoPendiente = 0;
    const elSaldo = saldoNode();
    if (elSaldo) {
      elSaldo.textContent = fmt(saldoPendiente);
      elSaldo.className = 'insc-wizard-saldo' + (saldoPendiente <= 0.009 ? ' insc-wizard-saldo--ok' : '');
    }
    const monto = montoInp();
    if (monto) monto.value = saldoPendiente > 0 ? saldoPendiente.toFixed(2) : '';
    const resumen = resumenEl();
    if (resumen) resumen.innerHTML = renderResumenHtml(resumenBase, false);
    refreshPagoLabels(false);
    togglePagoSection();
  }

  function refreshPagoLabels(personalizado) {
    const tit = document.getElementById('insc-wizard-pago-titulo');
    const saldoLbl = document.getElementById('insc-wizard-pago-saldo-label');
    const montoLbl = document.getElementById('insc-wizard-pago-monto-label');
    if (tit) tit.textContent = personalizado ? 'Cobro del curso personalizado' : 'Cobro de inscripción';
    if (saldoLbl) saldoLbl.textContent = personalizado ? 'Total a cobrar:' : 'Saldo pendiente:';
    if (montoLbl) montoLbl.textContent = personalizado ? 'Monto a cobrar (total acordado)' : 'Monto a cobrar';
  }

  function calcularMontoPersonalizadoNeto(costoBruto) {
    const bruto = parseFloat(costoBruto || 0);
    if (!Number.isFinite(bruto) || bruto <= 0) return 0;
    const credito = parseFloat(resumenBase?.credito_apartado_pendiente || 0);
    return Math.max(0, bruto - (Number.isFinite(credito) ? credito : 0));
  }

  async function cargarGruposParaEspecialidad(idAlumno, idEsp) {
    const { data } = await hayFetchJson(
      'php/inscripcion_wizard_api.php?action=grupos_disponibles&id_alumno=' + idAlumno + '&id_especialidad=' + idEsp
    );
    if (data.status !== 'ok') throw new Error(data.message || 'No se pudieron cargar grupos');
    return data.grupos || {};
  }

  function llenarSelectGrupos(sel, gruposData, modoAvanzado) {
    if (!sel) return;
    sel.innerHTML = '<option value="">— Seleccione un grupo —</option>';
    const addGroup = (label, items) => {
      if (!items || !items.length) return;
      const og = document.createElement('optgroup');
      og.label = label;
      items.forEach((g) => {
        const o = document.createElement('option');
        o.value = g.id_grupo;
        const fi = g.fecha_inicio ? ' · inicio ' + g.fecha_inicio : '';
        const fase = g.clave_fase ? ' (' + g.clave_fase + ')' : '';
        o.textContent = g.clave + fase + fi;
        og.appendChild(o);
      });
      if (og.children.length) sel.appendChild(og);
    };
    if (modoAvanzado) {
      addGroup('Grupos en curso', gruposData.en_curso || gruposData.otros || []);
    } else {
      addGroup('Próximos a iniciar', gruposData.proximos || gruposData.por_comenzar || []);
    }
    addGroup('Por ubicación', gruposData.ubicacion || []);
  }

  async function renderComboGruposUI(detalle) {
    const box = document.getElementById('insc-wizard-combo-grupos');
    if (!box) return;
    box.innerHTML = '';
    comboDetalleActual = detalle;
    if (!detalle || !detalle.requiere_varios_grupos || !(detalle.especialidades_adicionales || []).length) {
      box.style.display = 'none';
      return;
    }
    const idAlumno = parseInt(hiddenVal('insc-wizard-id-alumno'), 10);
    const modoAvanzado = optAvanzado();
    box.style.display = '';
    const intro = document.createElement('p');
    intro.style.cssText = 'font-size:0.85rem; color:#555; margin:0 0 8px;';
    intro.textContent = detalle.es_regla_infantil
      ? 'Combo infantil: seleccione el grupo IK (inglés) arriba y el grupo CK (computación) abajo. Si solo cursa una materia, elija únicamente ese grupo.'
      : 'Esta combinación requiere inscribir al alumno en un grupo por cada especialidad del combo.';
    box.appendChild(intro);

    for (const esp of detalle.especialidades_adicionales) {
      const row = document.createElement('div');
      row.className = 'insc-wizard-combo-grupo-row';
      row.dataset.idEspecialidad = String(esp.id_especialidad);
      const lbl = document.createElement('label');
      lbl.textContent = 'Grupo — ' + (esp.nombre || esp.clave || 'Especialidad');
      const sel = document.createElement('select');
      sel.className = 'insc-wizard-combo-grupo-sel';
      sel.style.cssText = 'width:100%; padding:8px; border-radius:8px; border:1px solid #ddd;';
      sel.dataset.idEspecialidad = String(esp.id_especialidad);
      row.appendChild(lbl);
      row.appendChild(sel);
      box.appendChild(row);
      try {
        const gData = await cargarGruposParaEspecialidad(idAlumno, parseInt(esp.id_especialidad, 10));
        llenarSelectGrupos(sel, gData, modoAvanzado);
      } catch (_e) {
        sel.innerHTML = '<option value="">— Sin grupos —</option>';
      }
    }
  }

  function obtenerGruposComboParaEnvio() {
    const out = [];
    document.querySelectorAll('.insc-wizard-combo-grupo-sel').forEach((sel) => {
      const idEsp = parseInt(sel.dataset.idEspecialidad || '0', 10);
      const idGr = parseInt(sel.value || '0', 10);
      if (idEsp > 0 && idGr > 0) out.push({ id_especialidad: idEsp, id_grupo: idGr });
    });
    return out;
  }

  async function handleDescuentoSelect(e) {
    const sel = (e && e.target) ? e.target : $insc('insc-wizard-regla-desc');
    if (!sel || sel.id !== 'insc-wizard-regla-desc') return;
    const id = parseInt(sel.value, 10);
    setHidden('insc-wizard-id-regla-desc', id || 0);
    const aviso = $insc('insc-wizard-descuento-aviso');
    const reg = reglasDescuento.find((x) => String(x.id_regla) === String(id));
    hideMsg();
    if (!id) {
      if (aviso) aviso.textContent = '';
      restaurarResumenRegular();
      await renderComboGruposUI(null);
      return;
    }
    const idAlumno = parseInt(hiddenVal('insc-wizard-id-alumno'), 10);
    const idEsp = parseInt(hiddenVal('insc-wizard-id-especialidad'), 10);
    if (idAlumno <= 0 || idEsp <= 0) {
      showMsg(false, 'Faltan datos del alumno o especialidad. Cierre y vuelva a abrir el asistente.');
      restaurarResumenRegular();
      return;
    }
    if (aviso) {
      aviso.textContent = reg
        ? 'Cargando tarifa de descuento…'
        : '';
    }
    try {
      const urlDet =
        'php/inscripcion_wizard_api.php?action=regla_descuento_detalle&id_regla=' + id +
        '&id_especialidad=' + idEsp + '&id_alumno=' + idAlumno;
      const { data } = await hayFetchJson(urlDet);
      if (data.status !== 'ok' || !data.detalle) {
        throw new Error(data.message || 'Regla de descuento no encontrada');
      }
      const det = data.detalle;
      if (det.id_especialidad_resuelta && parseInt(det.id_especialidad_resuelta, 10) > 0) {
        setHidden('insc-wizard-id-especialidad', det.id_especialidad_resuelta);
        await loadGrupos(idAlumno, parseInt(det.id_especialidad_resuelta, 10));
      }
      let preview = det.resumen_preview || null;
      if (!preview || preview.status === 'error' || !preview.ok) {
        preview = buildResumenDescuentoLocal(
          det.tarifa_especialidad_actual,
          det.regla?.nombre || reg?.nombre || '',
          det.totales_inscripcion || null
        );
      }
      if (!preview) {
        throw new Error(
          data.message || 'Esta regla no tiene inscripción configurada para la especialidad del alumno'
        );
      }
      aplicarResumenDescuento(preview);
      if (aviso) {
        let txt = 'Inscripción con descuento: ' + fmt(preview.costo_inscripcion);
        if (preview.regla_descuento) txt += ' (' + preview.regla_descuento + ')';
        if (det.requiere_varios_grupos) {
          txt += '. Seleccione también el grupo de la otra especialidad del combo.';
        }
        aviso.textContent = txt;
      }
      await renderComboGruposUI(det);
    } catch (err) {
      showMsg(false, err.message || 'Error al cargar descuento');
      if (aviso) aviso.textContent = '';
      restaurarResumenRegular();
      await renderComboGruposUI(null);
    }
  }

  function onDescuentoSelectChange(e) {
    handleDescuentoSelect(e);
  }

  function bindDescuentoSelect() {
    const sel = $insc('insc-wizard-regla-desc');
    if (!sel) return;
    if (descuentoSelectBound === sel) return;
    descuentoSelectBound = sel;
    sel.addEventListener('change', handleDescuentoSelect);
  }

  async function cargarReglasDescuento() {
    try {
      const { data } = await hayFetchJson('php/inscripcion_wizard_api.php?action=reglas_descuento');
      reglasDescuento = data.reglas || [];
      const sel = $insc('insc-wizard-regla-desc');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Elija descuento —</option>';
      reglasDescuento.forEach((r) => {
        const o = document.createElement('option');
        o.value = r.id_regla;
        o.textContent = r.resumen || r.nombre;
        sel.appendChild(o);
      });
      bindDescuentoSelect();
    } catch (e) { /* ignore */ }
  }

  function bindModalDelegation() {
    if (window.__inscWizardDelegated) return;
    window.__inscWizardDelegated = true;

    document.addEventListener('change', (e) => {
      onWizardOptionChange(e);
      onDescuentoSelectChange(e);
      if (e.target?.id === 'insc-wizard-grupo-per') onGrupoPerChange();
    });

    document.addEventListener('input', (e) => {
      if (e.target?.id === 'insc-wizard-costo-personalizado') syncMontoPersonalizado();
    });

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.insc-ref-autocomplete')) {
        const suggest = document.getElementById('insc-wizard-ref-suggest');
        if (suggest) suggest.hidden = true;
      }
    });

    bindReferidoAutocomplete();
  }

  function bindReferidoAutocomplete() {
    if (window.__inscRefBound) return;
    window.__inscRefBound = true;

    let debounce = null;
    let activeIdx = -1;

    document.addEventListener('input', (e) => {
      const q = e.target;
      if (!q || q.id !== 'insc-wizard-ref-q') return;
      const suggest = document.getElementById('insc-wizard-ref-suggest');
      const hiddenId = document.getElementById('insc-wizard-ref-id');
      const selLabel = document.getElementById('insc-wizard-ref-seleccionado');
      const preview = document.getElementById('insc-wizard-ref-preview');

      if (hiddenId) hiddenId.value = '0';
      if (selLabel) { selLabel.style.display = 'none'; selLabel.textContent = ''; }
      if (preview) preview.textContent = '';

      clearTimeout(debounce);
      const term = q.value.trim();
      if (term.length < 1) {
        if (suggest) suggest.hidden = true;
        return;
      }
      debounce = setTimeout(async () => {
        try {
          const { data } = await hayFetchJson(
            'php/referido_api.php?action=buscar&q=' + encodeURIComponent(term)
          );
          if (!suggest) return;
          const list = data.alumnos || [];
          suggest.innerHTML = '';
          if (!list.length) {
            suggest.hidden = true;
            return;
          }
          list.forEach((a) => {
            const li = document.createElement('li');
            li.textContent = (a.numero_control || '') + ' — ' + (a.nombre_completo || '');
            li.addEventListener('mousedown', (ev) => {
              ev.preventDefault();
              elegirReferidor(a);
            });
            suggest.appendChild(li);
          });
          suggest.hidden = false;
          activeIdx = -1;
        } catch (err) { /* ignore */ }
      }, 250);
    });

    document.addEventListener('keydown', (e) => {
      if (e.target?.id !== 'insc-wizard-ref-q') return;
      const suggest = document.getElementById('insc-wizard-ref-suggest');
      if (!suggest || suggest.hidden) return;
      const items = suggest.querySelectorAll('li');
      if (!items.length) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = Math.min(activeIdx + 1, items.length - 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = Math.max(activeIdx - 1, 0);
      } else if (e.key === 'Enter' && activeIdx >= 0) {
        e.preventDefault();
        items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
        return;
      } else if (e.key === 'Escape') {
        suggest.hidden = true;
        return;
      } else {
        return;
      }
      items.forEach((li, i) => li.classList.toggle('is-active', i === activeIdx));
    });
  }

  function elegirReferidor(a) {
    if (!a) return;
    const hiddenId = document.getElementById('insc-wizard-ref-id');
    const q = document.getElementById('insc-wizard-ref-q');
    const selLabel = document.getElementById('insc-wizard-ref-seleccionado');
    const suggest = document.getElementById('insc-wizard-ref-suggest');
    const preview = document.getElementById('insc-wizard-ref-preview');
    if (hiddenId) hiddenId.value = String(a.id_alumno);
    if (q) q.value = a.numero_control || '';
    if (selLabel) {
      selLabel.style.display = '';
      selLabel.textContent = 'Referidor: ' + (a.numero_control || '') + ' — ' + (a.nombre_completo || '');
    }
    if (suggest) suggest.hidden = true;
    const idEsp = parseInt(hiddenVal('insc-wizard-id-especialidad'), 10);
    if (preview && idEsp) {
      hayFetchJson(
        'php/referido_api.php?action=preview_beneficio&id_especialidad=' + idEsp +
          '&id_alumno_referidor=' + a.id_alumno
      ).then(({ data }) => {
        preview.textContent = data.status === 'ok'
          ? 'Crédito al referidor: ' + (data.monto_fmt || '')
          : (data.message || '');
      }).catch(() => { preview.textContent = ''; });
    }
  }

  function actualizarTituloModal() {
    const tit = $insc('insc-wizard-modal-titulo');
    if (tit) {
      tit.textContent = esCursoPersonalizado
        ? 'Inscribir curso personalizado'
        : 'Inscribir al grupo';
    }
  }

  function aplicarModoPersonalizado(si) {
    esCursoPersonalizado = si;
    setHidden('insc-wizard-es-personalizado', si ? '1' : '0');
    actualizarTituloModal();

    const flowPer = $insc('insc-wizard-personalizado-flow');
    const flowReg = $insc('insc-wizard-regular-flow');
    const wrapPerOp = $insc('insc-wizard-grupo-per-opcional');

    if (flowPer) flowPer.style.display = si ? '' : 'none';
    if (flowReg) flowReg.style.display = si ? 'none' : '';

    if (wrapPerOp) {
      wrapPerOp.style.display = si && gruposPersonalizados.length ? '' : 'none';
    }

    if (!si) {
      const costo = document.getElementById('insc-wizard-costo-personalizado');
      if (costo) costo.value = '';
      const selPer = document.getElementById('insc-wizard-grupo-per');
      if (selPer) selPer.value = '';
      const monto = montoInp();
      if (monto) monto.value = '';
      restaurarResumenRegular();
    } else {
      const resumen = resumenEl();
      if (resumen && resumenBase) resumen.innerHTML = renderResumenHtml(resumenBase, true);
      refreshPagoLabels(true);
      updateMontoPersonalizadoCampos();
      togglePagoSection();
    }

    syncOpcionesChecks();
  }

  function onGrupoPerChange() {
    const sel = document.getElementById('insc-wizard-grupo-per');
    const aviso = document.getElementById('insc-wizard-grupo-per-costo');
    const costoInp = document.getElementById('insc-wizard-costo-personalizado');
    const opt = sel?.selectedOptions?.[0];
    if (!opt || !opt.value) {
      if (aviso) aviso.textContent = '';
      if (costoInp) costoInp.readOnly = false;
      return;
    }
    const costo = parseFloat(opt.dataset.costo || '0');
    if (costo > 0 && costoInp) {
      costoInp.value = costo.toFixed(2);
      costoInp.readOnly = true;
      if (aviso) aviso.textContent = 'Colegiatura del grupo: ' + fmt(costo);
    } else if (costoInp) {
      costoInp.readOnly = false;
      if (aviso) aviso.textContent = '';
    }
    syncMontoPersonalizado();
  }

  function updateMontoPersonalizadoCampos() {
    if (!esCursoPersonalizado) return;
    const costo = document.getElementById('insc-wizard-costo-personalizado');
    const monto = montoInp();
    const bruto = parseFloat(costo?.value || '0');
    const neto = calcularMontoPersonalizadoNeto(bruto);
    saldoPendiente = neto;
    const elSaldo = saldoNode();
    if (elSaldo) {
      elSaldo.textContent = fmt(neto);
      elSaldo.className = 'insc-wizard-saldo' + (neto <= 0.009 ? ' insc-wizard-saldo--ok' : '');
    }
    if (monto && neto > 0) monto.value = neto.toFixed(2);
    const avisoPer = document.getElementById('insc-wizard-personalizado-aviso');
    if (avisoPer && bruto > 0) {
      const credito = parseFloat(resumenBase?.credito_apartado_pendiente || 0);
      avisoPer.textContent = credito > 0
        ? 'Costo acordado ' + fmt(bruto) + ' − crédito apartado ' + fmt(credito) + ' = ' + fmt(neto) + ' a cobrar. Comisión asesor: 10%.'
        : 'La comisión del asesor se calcula al 10% de este monto. Debe cobrarse el total acordado (sin cobro de inscripción).';
    }
  }

  function syncSemanaExtraMonto() {
    const chkSem = document.getElementById('insc-asesoria-semana-extra');
    const chkExo = document.getElementById('insc-asesoria-exonerar-semana');
    const base = parseFloat(resumenBase?.saldo_inscripcion || saldoPendiente || 0);
    let extra = 0;
    if (chkSem?.checked && !chkExo?.checked && costoSemanaExtra > 0) {
      extra = costoSemanaExtra;
    }
    saldoPendiente = base + extra;
    const elSaldo = document.getElementById('insc-wizard-saldo');
    if (elSaldo) {
      elSaldo.textContent = fmt(saldoPendiente);
      elSaldo.className = 'insc-wizard-saldo' + (saldoPendiente <= 0.009 ? ' insc-wizard-saldo--ok' : '');
    }
    const monto = montoInp();
    if (monto && saldoPendiente > 0) monto.value = saldoPendiente.toFixed(2);
    refreshPagoSectionUI();
  }

  async function refreshAsesoriaTardia() {
    const box = document.getElementById('insc-wizard-asesoria-tardia');
    const msg = document.getElementById('insc-wizard-asesoria-tardia-msg');
    const costoEl = document.getElementById('insc-wizard-semana-costo');
    if (!box) return;
    asesoriaTardiaMeta = null;
    costoSemanaExtra = 0;
    if (!optAvanzado() || esCursoPersonalizado || esModoUbicacion) {
      box.style.display = 'none';
      syncSemanaExtraMonto();
      return;
    }
    const idGrupo = parseInt(selGrupo()?.value || '0', 10);
    const idAlumno = parseInt(hiddenVal('insc-wizard-id-alumno'), 10);
    if (idGrupo <= 0 || idAlumno <= 0) {
      box.style.display = 'none';
      syncSemanaExtraMonto();
      return;
    }
    try {
      const { data } = await hayFetchJson(
        'php/inscripcion_wizard_api.php?action=grupo_asesoria_meta&id_grupo=' + idGrupo + '&id_alumno=' + idAlumno
      );
      if (data.status !== 'ok' || !data.inscripcion_tardia) {
        box.style.display = 'none';
        syncSemanaExtraMonto();
        return;
      }
      asesoriaTardiaMeta = data;
      costoSemanaExtra = parseFloat(data.costo_semana_extra || 0);
      box.style.display = '';
      if (msg) {
        msg.textContent = 'Grupo ' + (data.grupo_clave || '') + ' inició hace ' + (data.dias_desde_inicio || 0) +
          ' días. Puede cobrar semana extra y otorgar hasta 3 h de asesoría individual de regularización.';
      }
      if (costoEl) {
        costoEl.textContent = costoSemanaExtra > 0
          ? 'Semana extra: ' + (data.costo_semana_extra_fmt || fmt(costoSemanaExtra))
          : 'Sin tarifa semanal configurada para esta especialidad.';
      }
      const chkSem = document.getElementById('insc-asesoria-semana-extra');
      if (chkSem && costoSemanaExtra > 0) chkSem.checked = true;
      syncSemanaExtraMonto();
    } catch (e) {
      box.style.display = 'none';
      syncSemanaExtraMonto();
    }
  }

  function refreshPagoSectionUI() {
    const wrap = pagoWrap();
    const haySaldo = saldoPendiente > 0.009;
    const mostrarPago = esCursoPersonalizado || haySaldo;
    if (wrap) wrap.style.display = mostrarPago ? '' : 'none';
    const btn = document.getElementById('insc-wizard-inscribir');
    if (btn) {
      if (esModoUbicacion) {
        btn.textContent = haySaldo ? 'Cobrar e inscribir (ubicación)' : 'Inscribir por ubicación';
      } else {
        btn.textContent = esCursoPersonalizado || haySaldo ? 'Cobrar e inscribir' : 'Inscribir';
      }
    }
  }

  function syncMontoPersonalizado() {
    updateMontoPersonalizadoCampos();
    refreshPagoSectionUI();
  }

  function fillGruposPersonalizados(items) {
    gruposPersonalizados = items || [];
    const sel = document.getElementById('insc-wizard-grupo-per');
    const wrap = document.getElementById('insc-wizard-grupo-per-opcional');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Crear grupo nuevo —</option>';
    gruposPersonalizados.forEach((g) => {
      const o = document.createElement('option');
      o.value = g.id_grupo;
      const costo = parseFloat(g.personalizado_costo_acordado || 0);
      if (costo > 0) o.dataset.costo = String(costo);
      const fi = g.fecha_inicio ? ' · inicio ' + g.fecha_inicio : '';
      const costoTxt = costo > 0 ? ' · ' + fmt(costo) : '';
      o.textContent = (g.clave || 'Grupo') + fi + costoTxt;
      sel.appendChild(o);
    });
    if (wrap) {
      wrap.style.display = esCursoPersonalizado && gruposPersonalizados.length ? '' : 'none';
    }
  }

  function renderGruposSelect() {
    if (!gruposCatalogo) return;
    const sel = selGrupo();
    if (!sel) return;
    const aviso = avisoGrupo();
    const modoAvanzado = optAvanzado();
    sel.innerHTML = '<option value="">— Seleccione un grupo —</option>';

    const addGroup = (label, items) => {
      if (!items || !items.length) return;
      const og = document.createElement('optgroup');
      og.label = label;
      items.forEach((g) => {
        const o = document.createElement('option');
        o.value = g.id_grupo;
        const fi = g.fecha_inicio ? ' · inicio ' + g.fecha_inicio : '';
        const fase = g.clave_fase ? ' (' + g.clave_fase + ')' : '';
        o.textContent = g.clave + fase + fi;
        og.appendChild(o);
      });
      if (og.children.length) sel.appendChild(og);
    };

    if (modoAvanzado) {
      addGroup('Grupos en curso', gruposCatalogo.en_curso || gruposCatalogo.otros || []);
      addGroup('Por ubicación', gruposCatalogo.ubicacion || []);
    } else {
      addGroup('Próximos a iniciar', gruposCatalogo.proximos || gruposCatalogo.por_comenzar || []);
      addGroup('Por ubicación', gruposCatalogo.ubicacion || []);
    }

    const total = sel.querySelectorAll('option[value]').length;
    if (total === 0 && aviso) {
      aviso.style.display = 'block';
      aviso.textContent = modoAvanzado
        ? 'No hay grupos en curso para esta especialidad. Pruebe «Próximos a iniciar».'
        : (gruposCatalogo.message || 'No hay grupos programados. Use «Grupo en curso (avanzado)» si el alumno entra a un grupo ya iniciado.');
    } else if (aviso) {
      aviso.style.display = 'none';
    }
    updateGrupoModoHint();
  }

  function fillGrupos(data) {
    gruposCatalogo = data || {};
    const aviso = avisoGrupo();
    if (data.ubicacion_pendiente) {
      if (aviso) {
        aviso.style.display = 'block';
        aviso.textContent = data.message || 'Ubicación pendiente.';
      }
      fillGruposPersonalizados([]);
      gruposCatalogo = null;
      return;
    }
    fillGruposPersonalizados(data.personalizados || []);
    renderGruposSelect();
  }

  function setHidden(id, value) {
    const el = $insc(id);
    if (el) el.value = value ?? '';
  }

  function togglePagoSection() {
    refreshPagoSectionUI();
  }

  function toggleAutorizacionEdad() {
    const wrap = document.getElementById('insc-wizard-autorizacion-edad');
    const msg = document.getElementById('insc-wizard-autorizacion-msg');
    const userWrap = document.getElementById('insc-wizard-auth-user-wrap');
    const userInp = document.getElementById('insc-wizard-auth-user');
    if (wrap) wrap.style.display = requiereAutorizacionEdad ? '' : 'none';
    if (msg && requiereAutorizacionEdad) {
      msg.textContent = mensajeAutorizacionEdad || 'La edad no cumple el rango del curso. Confirme con su contraseña.';
    }
    if (requiereAutorizacionEdad && puedeAutorizarEdadSesion) {
      if (userWrap) userWrap.style.display = 'none';
      if (userInp) userInp.value = usernameSesion || '';
    } else if (userWrap) {
      userWrap.style.display = '';
    }
    if (!requiereAutorizacionEdad) {
      const u = document.getElementById('insc-wizard-auth-user');
      const p = document.getElementById('insc-wizard-auth-pass');
      if (u) u.value = '';
      if (p) p.value = '';
    }
  }

  function applyResumen(data) {
    if (!data || typeof data !== 'object') return;

    resumenBase = Object.assign({}, data);
    requiereAutorizacionEdad = !!data.edad_requiere_autorizacion;
    mensajeAutorizacionEdad = data.edad_autorizacion_mensaje || '';
    puedeAutorizarEdadSesion = !!data.puede_autorizar_edad;
    usernameSesion = data.username_sesion || '';
    toggleAutorizacionEdad();

    setHidden('insc-wizard-id-alumno', data.id_alumno || 0);
    setHidden('insc-wizard-id-especialidad', data.id_especialidad || 0);
    setHidden('insc-wizard-id-ae', data.id_alumno_especialidad || 0);
    if (data.id_preregistro) {
      setHidden('insc-wizard-id-prereg', data.id_preregistro);
    }

    if (!esCursoPersonalizado) {
      saldoPendiente = parseFloat(data.saldo_inscripcion || 0);
      if (!Number.isFinite(saldoPendiente)) saldoPendiente = 0;
      const elSaldo = saldoNode();
      if (elSaldo) {
        elSaldo.textContent = fmt(saldoPendiente);
        elSaldo.className = 'insc-wizard-saldo' + (saldoPendiente <= 0.009 ? ' insc-wizard-saldo--ok' : '');
      }
      const monto = montoInp();
      if (monto) monto.value = saldoPendiente > 0 ? saldoPendiente.toFixed(2) : '';
      refreshPagoLabels(false);
    }

    togglePagoSection();

    const resumen = resumenEl();
    if (resumen) resumen.innerHTML = renderResumenHtml(data, esCursoPersonalizado);
  }

  async function loadGrupos(idAlumno, idEsp) {
    const { data } = await hayFetchJson(
      'php/inscripcion_wizard_api.php?action=grupos_disponibles&id_alumno=' + idAlumno + '&id_especialidad=' + idEsp
    );
    if (data.status !== 'ok') throw new Error(data.message || 'No se pudieron cargar grupos');
    fillGrupos(data.grupos || {});
    if (esModoUbicacion) {
      loadExamenesUbicacion(idEsp);
    }
  }

  function resetFormulario() {
    ['insc-opt-personalizado', 'insc-opt-avanzado', 'insc-opt-descuento', 'insc-opt-recomendado', 'insc-opt-ubicacion'].forEach((id) => {
      const el = document.getElementById(id) || $insc(id);
      if (el) { el.checked = false; el.disabled = false; }
    });
    const refBox = document.getElementById('insc-wizard-referidor-box');
    if (refBox) refBox.style.display = 'none';
    const descBox = document.getElementById('insc-wizard-descuento-box');
    if (descBox) descBox.style.display = 'none';
    const perFlow = document.getElementById('insc-wizard-personalizado-flow');
    if (perFlow) perFlow.style.display = 'none';
    clearReferidor();
    clearDescuento();
    aplicarModoPersonalizado(false);
    aplicarModoUbicacion(false);
    updateGrupoModoHint();
    gruposCatalogo = null;
    asesoriaTardiaMeta = null;
    costoSemanaExtra = 0;
    const aseBox = document.getElementById('insc-wizard-asesoria-tardia');
    if (aseBox) aseBox.style.display = 'none';
    const horasReg = document.getElementById('insc-asesoria-horas-reg');
    if (horasReg) horasReg.value = '0';
  }

  function closeModal() {
    const m = modal();
    if (m) {
      m.classList.remove('is-open');
      m.style.display = 'none';
    }
    document.body.style.overflow = '';
    hideMsg();
    busy = false;
    saldoPendiente = 0;
    esCursoPersonalizado = false;
    esModoUbicacion = false;
    requiereAutorizacionEdad = false;
    mensajeAutorizacionEdad = '';
    gruposPersonalizados = [];
    toggleAutorizacionEdad();
    onSuccess = null;
    resetFormulario();
    setHidden('insc-wizard-id-regla-desc', 0);
  }

  function abrirModal() {
    wireModal();
    bindModalDelegation();
    descuentoSelectBound = null;
    cargarReglasDescuento();
    bindDescuentoSelect();
    modal()?.classList.add('is-open');
    if (modal()) modal().style.display = '';
    document.body.style.overflow = 'hidden';
  }

  async function openFromPrereg(idPrereg, callback) {
    onSuccess = callback || null;
    hideMsg();
    resetFormulario();
    abrirModal();
    setHidden('insc-wizard-id-prereg', idPrereg);

    const resumen = resumenEl();
    if (resumen) resumen.textContent = 'Preparando inscripción…';

    try {
      const fd = new FormData();
      fd.append('action', 'iniciar_desde_prereg');
      fd.append('id_preregistro', idPrereg);
      const { data } = await hayFetchJson('php/inscripcion_wizard_api.php', { method: 'POST', body: fd });
      if (data.status !== 'ok') throw new Error(data.message || 'Error');
      applyResumen(data);
      await loadGrupos(data.id_alumno, data.id_especialidad);
    } catch (err) {
      showMsg(false, err.message || 'Error');
    }
  }

  async function openFromAlumno(idAlumno, idEsp, idPrereg, callback) {
    onSuccess = callback || null;
    hideMsg();
    resetFormulario();
    abrirModal();
    setHidden('insc-wizard-id-prereg', idPrereg || 0);

    const resumen = resumenEl();
    if (resumen) resumen.textContent = 'Cargando…';

    try {
      const { data } = await hayFetchJson(
        'php/inscripcion_wizard_api.php?action=resumen&id_alumno=' + idAlumno +
          (idEsp ? '&id_especialidad=' + idEsp : '')
      );
      if (data.status !== 'ok') throw new Error(data.message || 'Error');
      applyResumen(data);
      await loadGrupos(data.id_alumno, data.id_especialidad);
    } catch (err) {
      showMsg(false, err.message || 'Error');
    }
  }

  async function onInscribir() {
    if (busy) return;

    const esPer = optPersonalizado();
    const esUb = optUbicacion();
    if (esPer !== esCursoPersonalizado) {
      aplicarModoPersonalizado(esPer);
    }
    if (esUb !== esModoUbicacion) {
      aplicarModoUbicacion(esUb);
    }

    let idGrupo = '';
    let montoPer = 0;

    if (esUb) {
      const idEx = $insc('insc-wizard-examen-ubicacion')?.value || '';
      if (!idEx) {
        showMsg(false, 'Seleccione el examen de ubicación');
        return;
      }
      if (saldoPendiente > 0.009) {
        const monto = parseFloat(montoInp()?.value || '0');
        if (!Number.isFinite(monto) || monto <= 0) {
          showMsg(false, 'Indique el monto a cobrar (' + fmt(saldoPendiente) + ')');
          return;
        }
        if (monto + 0.01 < saldoPendiente) {
          showMsg(false, 'Debe cobrar el saldo completo: ' + fmt(saldoPendiente));
          return;
        }
      }
    } else if (esPer) {
      const costoBruto = parseFloat(document.getElementById('insc-wizard-costo-personalizado')?.value || '0');
      montoPer = calcularMontoPersonalizadoNeto(costoBruto);
      if (!Number.isFinite(costoBruto) || costoBruto <= 0) {
        showMsg(false, 'Indique el costo acordado del curso personalizado');
        return;
      }
      if (montoPer <= 0) {
        showMsg(false, 'El crédito de apartado cubre el costo acordado');
        return;
      }
      const selPer = document.getElementById('insc-wizard-grupo-per');
      idGrupo = selPer?.value || '';
      saldoPendiente = montoPer;
    } else {
      idGrupo = selGrupo()?.value || '';
      if (!idGrupo) {
        showMsg(false, 'Seleccione un grupo');
        return;
      }
      if (saldoPendiente > 0.009) {
        const monto = parseFloat(montoInp()?.value || '0');
        if (!Number.isFinite(monto) || monto <= 0) {
          showMsg(false, 'Indique el monto a cobrar (' + fmt(saldoPendiente) + ')');
          return;
        }
        if (monto + 0.01 < saldoPendiente) {
          showMsg(false, 'Debe cobrar el saldo completo: ' + fmt(saldoPendiente));
          return;
        }
      }
    }

    if (esPer) {
      const monto = parseFloat(montoInp()?.value || '0');
      if (!Number.isFinite(monto) || monto <= 0) {
        showMsg(false, 'Debe cobrar el total del personalizado: ' + fmt(montoPer));
        return;
      }
      if (monto + 0.01 < montoPer) {
        showMsg(false, 'Debe cobrar el total acordado: ' + fmt(montoPer));
        return;
      }
    }

    if (requiereAutorizacionEdad) {
      const authUser = document.getElementById('insc-wizard-auth-user')?.value?.trim();
      const authPass = document.getElementById('insc-wizard-auth-pass')?.value || '';
      if (!authPass) {
        showMsg(false, puedeAutorizarEdadSesion
          ? 'Indique su contraseña para autorizar la inscripción por edad'
          : 'Indique usuario y contraseña de quien autoriza la inscripción por edad');
        return;
      }
      if (!puedeAutorizarEdadSesion && !authUser) {
        showMsg(false, 'Indique el usuario autorizador');
        return;
      }
    }

    busy = true;
    hideMsg();
    const btn = document.getElementById('insc-wizard-inscribir');
    const btnLabel = btn?.textContent;
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Procesando…';
    }

    try {
      const fd = new FormData();
      fd.append('action', 'completar_inscripcion');
      fd.append('id_alumno', hiddenVal('insc-wizard-id-alumno'));
      if (esUb) {
        fd.append('es_ubicacion', '1');
        fd.append('id_examen_ubicacion', $insc('insc-wizard-examen-ubicacion')?.value || '');
      } else if (idGrupo) {
        fd.append('id_grupo', idGrupo);
      }
      fd.append('id_preregistro', hiddenVal('insc-wizard-id-prereg') || '0');
      fd.append('id_especialidad', hiddenVal('insc-wizard-id-especialidad'));
      fd.append('id_alumno_especialidad', hiddenVal('insc-wizard-id-ae'));
      const montoEnvio = esCursoPersonalizado
        ? montoPer
        : (saldoPendiente > 0.009 ? (montoInp()?.value || '0') : '0');
      fd.append('monto', montoEnvio);
      if (esCursoPersonalizado) {
        const costoBrutoPer = parseFloat(document.getElementById('insc-wizard-costo-personalizado')?.value || '0');
        fd.append('es_curso_personalizado', '1');
        fd.append('monto_personalizado', String(costoBrutoPer));
        fd.append('monto', String(montoPer));
      }
      fd.append('forma_pago', formaSel()?.value || 'Efectivo');
      const idRef = parseInt(hiddenVal('insc-wizard-ref-id'), 10);
      if (optRecomendado()) {
        if (idRef <= 0) {
          showMsg(false, 'Seleccione el alumno que recomendó');
          busy = false;
          if (btn) { btn.disabled = false; btn.textContent = btnLabel || 'Inscribir'; }
          return;
        }
        fd.append('es_referido', '1');
        fd.append('id_alumno_referidor', String(idRef));
      }
      const idRegla = parseInt(hiddenVal('insc-wizard-id-regla-desc'), 10);
      if (optDescuento() && !esPer) {
        if (!idRegla) {
          showMsg(false, 'Seleccione la colegiatura con descuento');
          busy = false;
          if (btn) { btn.disabled = false; btn.textContent = btnLabel || 'Inscribir'; }
          return;
        }
        fd.append('id_regla_colegiatura', String(idRegla));
        const gruposCombo = obtenerGruposComboParaEnvio();
        if (comboDetalleActual?.requiere_varios_grupos) {
          const faltan = (comboDetalleActual.especialidades_adicionales || []).length - gruposCombo.length;
          if (faltan > 0) {
            showMsg(false, 'Seleccione el grupo de cada especialidad del combo');
            busy = false;
            if (btn) { btn.disabled = false; btn.textContent = btnLabel || 'Inscribir'; }
            return;
          }
        }
        if (gruposCombo.length) fd.append('grupos_combo', JSON.stringify(gruposCombo));
      }
      if (requiereAutorizacionEdad) {
        fd.append('usuario_autoriza', document.getElementById('insc-wizard-auth-user')?.value?.trim() || '');
        fd.append('password_autoriza', document.getElementById('insc-wizard-auth-pass')?.value || '');
      }
      if (optAvanzado() && asesoriaTardiaMeta?.inscripcion_tardia) {
        if (document.getElementById('insc-asesoria-semana-extra')?.checked) {
          fd.append('asesoria_semana_extra', '1');
        }
        if (document.getElementById('insc-asesoria-exonerar-semana')?.checked) {
          fd.append('asesoria_exonerar_semana', '1');
        }
        const horasReg = document.getElementById('insc-asesoria-horas-reg')?.value || '0';
        fd.append('asesoria_horas_regularizacion', horasReg);
      }

      const { data } = await hayFetchJson('php/inscripcion_wizard_api.php', { method: 'POST', body: fd });
      if (data.status !== 'ok') throw new Error(data.message || 'No se pudo completar la inscripción');

      let extraMsg = '';
      if (data.asesoria_creditos) {
        extraMsg += '\nCréditos de regularización: ' + data.asesoria_creditos + ' h';
      }
      if (data.asesoria_semana_extra?.message) {
        extraMsg += '\n' + data.asesoria_semana_extra.message;
      }
      if (data.asesoria_semana_error) {
        extraMsg += '\nAtención semana extra: ' + data.asesoria_semana_error;
      }

      closeModal();

      if (data.ticket_url) {
        imprimirTicket(data.ticket_url, data.grupo_clave || data.examen || '');
      }
      if (extraMsg) alert('Inscripción completada.' + extraMsg);

      if (data.es_ubicacion && data.examen) {
        let ubMsg = 'Inscripción por ubicación completada.\nExamen: ' + data.examen;
        if (data.moodle_inscrito) {
          ubMsg += '\nAcceso al curso Moodle del examen: listo.';
        } else if (data.moodle_warning && data.moodle_warning.message) {
          ubMsg += '\n\nAtención Moodle: ' + data.moodle_warning.message;
        } else {
          ubMsg += '\nEl alumno debe presentar el examen en Moodle antes de asignar grupo.';
        }
        alert(ubMsg);
      }

      if (data.ticket_referidor_url) {
        const imprimirCopia = window.confirm(
          'Se aplicó crédito al alumno que recomendó.\n\n¿Desea imprimir ahora la copia para firma del referidor?\n' +
          '(Si no, podrá imprimirla después desde el comprobante REF en caja.)'
        );
        if (imprimirCopia) {
          imprimirTicket(data.ticket_referidor_url + '&copia=1', '', 'ticket_referidor');
        } else {
          imprimirTicket(data.ticket_referidor_url, '', 'ticket_referidor');
        }
      } else if (data.referido_error) {
        alert('Inscripción OK, pero referido: ' + data.referido_error);
      }

      if (data.id_alumno) {
        cargarSeccion('alumno_huella_enroll', 'id=' + data.id_alumno + '&nuevo=1');
        return;
      }

      if (typeof onSuccess === 'function') {
        onSuccess(data);
      }
    } catch (err) {
      showMsg(false, err.message || 'Error');
    } finally {
      busy = false;
      if (btn) {
        btn.disabled = false;
        btn.textContent = btnLabel || 'Inscribir';
      }
    }
  }

  function bindOnce() {
    if (window.__inscWizardBound) return;
    window.__inscWizardBound = true;

    document.addEventListener('click', (e) => {
      const m = modal();
      if (e.target === m) {
        e.preventDefault();
        intentarCerrarModal();
        return;
      }
      if (e.target.closest('#insc-wizard-cancel')) {
        e.preventDefault();
        intentarCerrarModal();
        return;
      }
      if (e.target.closest('#insc-wizard-inscribir')) {
        e.preventDefault();
        onInscribir();
      }
    });
  }

  window.HayInscripcionWizard = {
    openFromPrereg,
    openFromAlumno,
    close: closeModal,
    bindOnce,
    wireModal,
  };

  bindOnce();
  bindModalDelegation();
})();
