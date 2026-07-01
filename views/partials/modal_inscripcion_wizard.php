<?php

// Modal asistente de inscripción (grupo + pago + confirmar en un paso)

?>

<div class="catalog-modal insc-wizard-modal" id="modal-inscripcion-wizard">

  <div class="catalog-modal__panel">

    <h3 id="insc-wizard-modal-titulo" style="margin-top:0;">Inscribir al grupo</h3>

    <div id="insc-wizard-msg" class="catalog-alert" style="display:none;"></div>



    <div id="insc-wizard-resumen" class="insc-wizard-resumen">Cargando…</div>



    <div id="insc-wizard-opciones-wrap" class="insc-wizard-opciones">

      <p class="insc-wizard-opciones__titulo">Opciones <span class="insc-wizard-opciones__hint">(solo si aplica)</span></p>

      <div class="insc-wizard-opciones__checks">

        <label class="insc-wizard-check"><input type="checkbox" id="insc-opt-personalizado"> Personalizado</label>

        <label class="insc-wizard-check"><input type="checkbox" id="insc-opt-avanzado"> Avanzado</label>

        <label class="insc-wizard-check"><input type="checkbox" id="insc-opt-descuento"> Descuento</label>

        <label class="insc-wizard-check"><input type="checkbox" id="insc-opt-recomendado"> Recomendado</label>

        <label class="insc-wizard-check"><input type="checkbox" id="insc-opt-ubicacion"> Ubicación</label>

      </div>

    </div>



    <div id="insc-wizard-personalizado-flow" class="insc-wizard-seccion-extra" style="display:none;">

      <label><strong>Costo acordado con el cliente</strong></label>

      <input type="number" id="insc-wizard-costo-personalizado" min="0.01" step="0.01" placeholder="0.00" style="width:100%; padding:10px; margin:6px 0 12px; border-radius:8px; border:1px solid #ddd;">

      <p id="insc-wizard-personalizado-aviso" style="font-size:0.88rem; color:#555; margin:0 0 8px;">No aplica cobro de inscripción. Se crea el grupo personalizado automáticamente; solo indique el costo acordado y el cobro total.</p>

      <div id="insc-wizard-grupo-per-opcional" style="display:none; margin-top:8px;">

        <label><strong>Unirse a grupo personalizado existente</strong> <span style="font-weight:normal; color:#666;">(opcional)</span></label>

        <select id="insc-wizard-grupo-per" style="width:100%; padding:10px; margin:8px 0; border-radius:8px; border:1px solid #ddd;">

          <option value="">— Crear grupo nuevo —</option>

        </select>

        <p id="insc-wizard-grupo-per-costo" style="font-size:0.88rem; color:#1565c0; margin:0;"></p>

      </div>

    </div>



    <div id="insc-wizard-regular-flow">

      <div id="insc-wizard-descuento-box" class="insc-wizard-seccion-extra" style="display:none;">

        <label><strong>Colegiatura con descuento</strong></label>

        <select id="insc-wizard-regla-desc" style="width:100%; padding:8px; margin:6px 0; border-radius:8px; border:1px solid #ddd;">

          <option value="">— Elija descuento —</option>

        </select>

        <p id="insc-wizard-descuento-aviso" style="font-size:0.85rem; color:#555; margin:6px 0 0;"></p>

        <div id="insc-wizard-combo-grupos" style="display:none; margin-top:10px;"></div>

      </div>



      <label><strong>Grupo</strong></label>

      <p id="insc-wizard-grupo-modo-hint" style="font-size:0.85rem; color:#666; margin:4px 0 6px;">Grupos próximos a iniciar de la especialidad del alumno.</p>

      <div id="insc-wizard-ubicacion-box" class="insc-wizard-seccion-extra" style="display:none;">
        <label>Examen de ubicación (Moodle)</label>
        <select id="insc-wizard-examen-ubicacion" style="width:100%; padding:10px; margin:6px 0 12px; border-radius:8px; border:1px solid #ddd;">
          <option value="">— Seleccione examen —</option>
        </select>
        <p id="insc-wizard-ubicacion-aviso" style="font-size:0.88rem; color:#1565c0; margin:0 0 8px;">
          No se asignará grupo hasta que coordinación evalúe el examen y autorice grupos.
        </p>
      </div>

      <div id="insc-wizard-grupo-wrap">
      <select id="insc-wizard-grupo" style="width:100%; padding:10px; margin:0 0 12px; border-radius:8px; border:1px solid #ddd;">

        <option value="">— Seleccione un grupo —</option>

      </select>

      <p id="insc-wizard-grupo-aviso" style="display:none; color:#e65100; font-size:0.88rem;"></p>
      </div>

      <div id="insc-wizard-asesoria-tardia" class="insc-wizard-seccion-extra" style="display:none; margin-top:12px; padding:12px; background:#f5f8ff; border-radius:8px;">
        <p style="margin:0 0 8px;"><strong>Inscripción tardía (grupo en curso)</strong></p>
        <p id="insc-wizard-asesoria-tardia-msg" style="font-size:0.88rem; color:#555; margin:0 0 10px;"></p>
        <label class="insc-wizard-check"><input type="checkbox" id="insc-asesoria-semana-extra" value="1"> Cobrar semana extra</label>
        <p id="insc-wizard-semana-costo" style="font-size:0.88rem; color:#1565c0; margin:6px 0 10px;"></p>
        <label class="insc-wizard-check"><input type="checkbox" id="insc-asesoria-exonerar-semana" value="1"> Exonerar semana (director)</label>
        <div style="margin-top:10px;">
          <label>Asesorías de regularización (0–3 h, solo individual)</label>
          <select id="insc-asesoria-horas-reg" style="width:100%; padding:8px; margin:6px 0; border-radius:8px; border:1px solid #ddd;">
            <option value="0">0 — ninguna</option>
            <option value="1">1 hora</option>
            <option value="2">2 horas</option>
            <option value="3">3 horas</option>
          </select>
        </div>
      </div>

    </div>



    <div id="insc-wizard-referidor-box" class="insc-wizard-seccion-extra" style="display:none;">

      <label><strong>Alumno que recomendó</strong></label>

      <div class="insc-ref-autocomplete">

        <input type="search" id="insc-wizard-ref-q" placeholder="Nombre o número de control…" autocomplete="off" style="width:100%; padding:8px; margin:6px 0;">

        <ul id="insc-wizard-ref-suggest" class="insc-ref-suggest" hidden></ul>

      </div>

      <p id="insc-wizard-ref-seleccionado" class="insc-ref-seleccionado" style="display:none;"></p>

      <input type="hidden" id="insc-wizard-ref-id" value="0">

      <p id="insc-wizard-ref-preview" style="font-size:0.88rem; color:#1565c0; margin:6px 0;"></p>

    </div>



    <div id="insc-wizard-autorizacion-edad" style="display:none; border-top:1px solid #eee; padding-top:12px; margin-top:8px; background:#fff8e1; border-radius:8px; padding:12px;">

      <p><strong>Autorización por edad</strong></p>

      <p id="insc-wizard-autorizacion-msg" style="font-size:0.88rem; color:#555; margin:4px 0 10px;"></p>

      <div id="insc-wizard-auth-user-wrap">
        <label>Usuario autorizador (gerente, admin o supervisor)</label>
        <input type="text" id="insc-wizard-auth-user" name="hay_insc_auth_user" autocomplete="off" autocapitalize="off" style="width:100%; padding:8px; margin:6px 0; border-radius:8px; border:1px solid #ddd;">
      </div>

      <label>Contraseña</label>

      <input type="password" id="insc-wizard-auth-pass" name="hay_insc_auth_pass" autocomplete="new-password" style="width:100%; padding:8px; margin:6px 0; border-radius:8px; border:1px solid #ddd;">

    </div>



    <div id="insc-wizard-pago-wrap" class="insc-wizard-pago" style="display:none; border-top:1px solid #eee; padding-top:12px; margin-top:8px;">

      <p><strong id="insc-wizard-pago-titulo">Cobro de inscripción</strong></p>

      <p><span id="insc-wizard-pago-saldo-label">Saldo pendiente:</span> <span id="insc-wizard-saldo" class="insc-wizard-saldo">$0.00</span></p>

      <label id="insc-wizard-pago-monto-label">Monto a cobrar</label>

      <input type="number" id="insc-wizard-monto" min="0" step="0.01" style="width:100%; padding:10px; margin:6px 0 12px; border-radius:8px; border:1px solid #ddd;">

      <label>Forma de pago</label>

      <select id="insc-wizard-forma-pago" style="width:100%; padding:10px; margin:6px 0 4px; border-radius:8px; border:1px solid #ddd;">

        <option value="Efectivo">Efectivo</option>

        <option value="Transferencia">Transferencia</option>

        <option value="Tarjeta débito">Tarjeta débito</option>

        <option value="Tarjeta crédito">Tarjeta crédito</option>

      </select>

    </div>



    <input type="hidden" id="insc-wizard-id-alumno" value="0">

    <input type="hidden" id="insc-wizard-id-especialidad" value="0">

    <input type="hidden" id="insc-wizard-id-ae" value="0">

    <input type="hidden" id="insc-wizard-id-prereg" value="0">

    <input type="hidden" id="insc-wizard-id-regla-desc" value="0">

    <input type="hidden" id="insc-wizard-es-personalizado" value="0">



    <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; margin-top:16px;">

      <button type="button" id="insc-wizard-cancel">Cancelar</button>

      <button type="button" class="primary" id="insc-wizard-inscribir">Inscribir</button>

    </div>

  </div>

</div>

