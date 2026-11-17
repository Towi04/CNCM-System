<div class="row">
    <div class="col-12 col-sm-4">
        {!! Form::hidden('id_producto', $producto->id, []) !!}
        <div class="form-group">
            {!! Form::label('fecha', 'Fecha:*') !!}
            {!! Form::date('fecha', null, [
                'class'         => 'form-control datepicker',
                'placeholder'   => 'Selecciona la fecha',
                'required'      => true,
                'title'         => 'Fecha'
            ]) !!}
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="form-group">
            {!! Form::label('cantidad', 'Cantidad:') !!}
            {!! Form::number('cantidad', null, [
                'class'         => 'form-control',
                'placeholder'   => 'Escribe la cantidad',
                'title'         => 'Cantidad',
            ]) !!}
        </div>
    </div>
</div>

