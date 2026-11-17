<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('Dia', 'Dia:*'); !!}
            {!! Form::select('dia', $dias, null, ['class' => 'form-control','required' => true]) !!}
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('hora_inicio', 'Hora inicio:*'); !!}
            {!! Form::number('hora_inicio', null, ['class' => 'form-control','title' => 'Hora Inicio','placeholder' => 'Escribe la hora de inicio','min'=> '1', 'max' => 24,'required' => true]) !!}
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('hora_final', 'Hora final:*'); !!}
            {!! Form::number('hora_final', null, ['class' => 'form-control','title' => 'Hora Final','placeholder' => 'Escribe la hora de final','min'=> '1', 'max' => 24,'required' => true]) !!}
        </div>
    </div>
</div>
