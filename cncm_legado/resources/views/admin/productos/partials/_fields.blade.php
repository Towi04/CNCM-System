<div class="row">
    <div class="col-12 col-sm-4">
        <div class="form-group">
            {!! Form::label('nombre', 'Nombre:*') !!}
            {!! Form::text('nombre', null, [
                'class'         => 'form-control',
                'placeholder'   => 'Escribe aqui el nombre',
                'required'      => true,
                'title'         => 'Producto'
            ]) !!}
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="form-group">
            {!! Form::label('descripcion', 'Descripción:') !!}
            {!! Form::text('descripcion', null, [
                'class'         => 'form-control',
                'placeholder'   => 'Escribe la descripcion',
                'title'         => 'Producto',
            ]) !!}
        </div>
    </div>
    <div class="col-12 col-sm-4">
        <div class="form-group">
            {!! Form::label('pre', 'Precio:*') !!}
            {!! Form::number('precio', null, [
                'class'         => 'form-control',
                'placeholder'   => 'Escribe el precio del producto',
                'title'         => 'Precio producto',
                'step'          => '0.01',
                'required'      => true
            ]) !!}
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-sm-6">
        <div class="form-group">
            {!! Form::label('clave_sat', 'Clave SAT:'); !!}
            {!! Form::text('clave_sat',null, ['class' => 'form-control','placeholder'=>'Escribe la clave SAT']); !!}
        </div>
    </div>

    <div class="col-12 col-sm-6">
        <div class="form-group">
            {!! Form::label('clave_unidad_sat', 'Clave Unidad SAT:'); !!}
            {!! Form::select('clave_unidad_sat',$claves_unidad,null, ['class' => 'form-control']); !!}
        </div>
    </div>
</div>
