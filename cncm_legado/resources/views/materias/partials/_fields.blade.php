<fieldset class="form-group">
    <legend><span>Informacion de la Materia</span></legend>
    <div class="row">

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('nombre', 'Nombre:*'); !!}
                {!! Form::text('nombre', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el nombre','required' => true,'autocomplete' => 'off']); !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('id_especialidad', 'Especialidad:*'); !!}
                @if(isset($especialidad))
                {!! Form::select('id_especialidad',$especialidades, @$especialidad->id, ['class' => 'form-control', 'readonly'=>'readonly']) !!}
                @else 
                {!! Form::select('id_especialidad',$especialidades, @$especialidad->id, ['class' => 'form-control']) !!}
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('fase', 'Fase:*'); !!}
                {!! Form::text('fase', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui la fase','required' => true,'autocomplete' => 'off']); !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('orden', 'Orden:*'); !!}
                {!! Form::number('orden', @$max_orden, ['class' => 'form-control', 'placeholder' => 'Escribe aqui la fase','required' => true,'autocomplete' => 'off']); !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('semanas', 'Semanas de duración:*'); !!}
                {!! Form::number('semanas', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el número de semanas de duración','required' => true,'autocomplete' => 'off']); !!}
            </div>
        </div>


    </div>
</fieldset>
