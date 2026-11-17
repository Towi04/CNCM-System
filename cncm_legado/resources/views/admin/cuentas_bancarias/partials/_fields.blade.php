<fieldset class="form-group">
    <legend><span>Información de la cuenta bancaria</span></legend>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('nombre', 'Nombre:*'); !!}
                {!! Form::text('nombre', null, ['class' => 'form-control','placeholder' => 'Escribe aqui el nombre','required' => true]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('banco', 'Banco:'); !!}
                {!! Form::text('banco', null, ['class' => 'form-control','placeholder' => 'Escribe aqui el banco']) !!}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('no_cuenta', 'N° Cuenta:'); !!}
                {!! Form::text('no_cuenta', null, ['class' => 'form-control','placeholder' => 'Escribe aqui el N° de cuenta']) !!}
            </div>
        </div>
    </div>
</fieldset>
