<div class="row">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xl-12 col-12">
        <div class="form-group">
            {!! Form::label('nombre', 'Nombre:*'); !!}
            {!! Form::text('nombre', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el nombre de la sucursal','required'=> true]); !!}
        </div>

        <div class="form-group">
            {!! Form::label('direccion', 'Dirección*'); !!}
            {!! Form::text('direccion', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui la direccón de la sucursal','required'=>true ]); !!}
        </div>

        <div class="form-group">
            {!! Form::label('municipio', 'Municipio:*'); !!}
            {!! Form::text('municipio', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el municipio de la sucursal']); !!}
        </div>

        <div class="form-group">
            {!! Form::label('telefono', 'Telefono:'); !!}
            {!! Form::text('telefono', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el telefono de la sucursal']); !!}
        </div>

        <div class="form-group">
            {!! Form::label('rfc', 'RFC:*'); !!}
            {!! Form::text('rfc', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el rfc de la sucursal']); !!}
        </div>

        <div class="form-group">
            {!! Form::label('estado', 'Estado'); !!}
            {!! Form::text('estado', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el estado de la sucursal']); !!}
        </div>
    </div>
</div>

<script type="text/javascript">
    window.addEventListener('DOMContentLoaded', (event) => {
    });
</script>
