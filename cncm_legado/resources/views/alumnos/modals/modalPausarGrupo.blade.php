<div class="onboarding-modal modal fade" id="modalPausarGrupo" tabindex="-1" data-backdrop="static" data-keyboard="false" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span
                        aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h5 class="modal-title">Selecciona una fecha y escribe una nota para recontactar al alumno </h5>
            </div>

            {!! Form::open(['route' => 'alumnos.pausar_grupo','id' => 'formPausarGrupo','onsubmit'=>'wait.modal("show")']) !!}
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('fecha_recontactar', 'Fecha de recontacto:*'); !!}
                    {!! Form::text('fecha_recontactar', null, ['id'=>'datepicker_recontacto', 'class' => 'form-control datepicker', 'placeholder' => 'Selecciona una fecha','required' => true,]); !!}
                </div>

                <div class="form-group">
                    {!! Form::label('nota', 'Nota:*'); !!}
                    {!! Form::textarea('nota', null, ['class' => 'form-control', 'placeholder' => 'Escribe la nota','required' => true,]); !!}
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="id_grupo" id="input_id_grupo_recontactar">
                <input type="hidden" name="id_alumno" value="{{$alumno->id}}" >
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Pausar</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
