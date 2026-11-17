<fieldset class="form-group">
    <legend><span>Informacion de la especialidad</span></legend>
    <div class="row">
        <div class="col-12 col-md-4">
            <div class="form-group">
                {!! Form::label('id_especialidad_1', 'Especialidad 1:'); !!}
                {!! Form::select('id_especialidad_1', $especialidades->pluck('nombre','id')->prepend('Selecciona una opción',''), null, ['class' => 'form-control']) !!}
                <p class="form-text text-muted">
                    A esta especialidad se le aplicará el descuento. 
                </p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-group">
                {!! Form::label('forma_pago_1', 'Forma de pago:'); !!}
                {!! Form::select('forma_pago_1', ['semanal'=>'semanal','mensual'=>'mensual'], null,['class' => 'form-control']) !!}
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-group">
                {!! Form::label('monto_1', 'Monto Especialidad 1:'); !!}
                {!! Form::text('monto_1', null, ['class' => 'form-control']) !!}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12 col-md-4">
            <div class="form-group">
                {!! Form::label('id_especialidad_2', 'Especialidad 2:'); !!}
                {!! Form::select('id_especialidad_2', $especialidades->pluck('nombre','id')->prepend('Selecciona una opción',''), null, ['class' => 'form-control']) !!}
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-group">
                {!! Form::label('forma_pago_2', 'Forma de pago:'); !!}
                {!! Form::select('forma_pago_2', ['semanal'=>'semanal','mensual'=>'mensual'],null,  ['class' => 'form-control']) !!}
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-group">
                {!! Form::label('monto_2', 'Monto Especialidad 2:'); !!}
                {!! Form::text('monto_2', null, ['class' => 'form-control']) !!}
            </div>
        </div>
    </div>
</fieldset>
