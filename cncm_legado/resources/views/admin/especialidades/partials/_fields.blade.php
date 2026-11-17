<fieldset class="form-group">
    <legend><span>Informacion de la especialidad</span></legend>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('nombre', 'Nombre:*'); !!}
                {!! Form::text('nombre', null, ['class' => 'form-control','required' => true]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('descripcion', 'Descripcion:'); !!}
                {!! Form::text('descripcion', null, ['class' => 'form-control']) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('formas_pago', 'Formas pago disponibles:*'); !!}
                <div class="form-check">
                  <label class="form-check-label">
                    <input type="checkbox" class="form-check-input" name="formas_pago[]" id="" value="mensual" @if(isset($especialidad->id)) @if(in_array('mensual', json_decode($especialidad->formas_pago))) checked @endif @endif>
                    Mensual
                  </label>
                </div>
                <div class="form-check">
                    <label class="form-check-label">
                      <input type="checkbox" class="form-check-input" name="formas_pago[]" id="" value="semanal" @if(isset($especialidad->id)) @if(in_array('semanal', json_decode($especialidad->formas_pago))) checked @endif @endif>
                      Semanal
                    </label>
                  </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('precio_inscripcion', 'Precio Inscripción:'); !!}
                {!! Form::number('precio_inscripcion', null, ['class' => 'form-control', 'readonly'=>true]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('precio_mensualidad', 'Precio Mensualidad:*'); !!}
                {!! Form::number('precio_mensualidad', null, ['class' => 'form-control','readonly'=>true]) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('precio_mensualidad_pronto_pago', 'Precio Mensualidad pronto pago'); !!}
                {!! Form::number('precio_mensualidad_pronto_pago', null, ['class' => 'form-control','readonly'=>true]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('precio_semanal', 'Precio semanal:'); !!}
                {!! Form::number('precio_semanal', null, ['class' => 'form-control','readonly'=>true]) !!}
            </div>
        </div>
    </div>
</fieldset>

<fieldset class="form-group">
    <legend><span>Asignar coordinadores</span></legend>
    <div class="row">
        <div class="col-12">
            <div class="form-group">
                {!! Form::label('id_usuario[]','Coordinadores:', []) !!}
                {!! Form::select('id_usuario[]', [], null, ['id' => 'select2_cordinador','class' => 'form-control w-100','title' => 'Selecciona un coordinador','multiple' => 'multiple']) !!}
            </div>
        </div>
    </div>
</fieldset>
