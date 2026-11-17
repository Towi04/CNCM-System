<div class="onboarding-modal modal fade" id="modal-notas" tabindex="-1" data-backdrop="static" data-keyboard="false" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span
                        aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h5 class="modal-title">Agrega una nota al alumno <small>(*) Campos requeridos</small> </h5>
            </div>

            {!! Form::open(['route' => 'alumnos.guardar_nota','id' => 'form-notas']) !!}
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('nota', 'Nota:*'); !!}
                    {!! Form::textarea('nota', null, ['class' => 'form-control ckeditor', 'placeholder' => 'Escribe la nota','required' => true,]); !!}
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
