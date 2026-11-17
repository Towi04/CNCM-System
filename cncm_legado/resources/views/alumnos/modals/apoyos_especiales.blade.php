<div class="onboarding-modal modal fade" id="modal-apoyo-especial" tabindex="-1" data-backdrop="static" data-keyboard="false" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span
                        aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h5 class="modal-title">Agregar precio especial <small>(*) Campos requeridos</small> </h5>
            </div>

            {!! Form::open(['route' => 'apoyos-especiales.store','id' => 'form-apoyo-especial']) !!}
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('precio', 'Apoyo:*'); !!}
                    {!! Form::number('precio', null, ['class' => 'form-control', 'placeholder' => 'Escribe el monto del apoyo','required' => true,'step' => '0.01','autocomplete' => 'off','style' => "",]); !!}
                </div>
                <div class="form-group">
                    {!! Form::label('fecha_inicio', 'Fecha inicio:*'); !!}
                    {!! Form::text('fecha_inicio', null, ['class' => 'form-control', 'placeholder' => 'Fecha inicio','required' => true,'autocomplete' => 'off','style' => "",]); !!}
                </div>
                <div class="form-group">
                    {!! Form::label('fecha_final', 'Fecha final:*'); !!}
                    {!! Form::text('fecha_final', null, ['class' => 'form-control', 'placeholder' => 'Fecha final','required' => true,'autocomplete' => 'off','style' => ""]); !!}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
