@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo','Productos')

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.productos.index') }}">Productos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar Producto {{ $producto->nombre }}</strong>
        </li>
    </ol>
@endsection

@section('contenido')

    <div class="row">
        <div class="col-12">
            <div class="element-box">
                {!! Form::model($producto, ['route' => ['admin.productos.update', $producto], 'method' => 'PUT', 'accept-charset' =>'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => 'wait.modal("show")']) !!}

                    <h6 class="element-header">
                        Información del producto
                    </h6>

                    @include('admin.productos.partials._fields')

                    <div class="form-buttons-w text-right">
                        <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                    </div>

                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection


@section('scripts')

    <script type="text/javascript">
        $(document).ready(function(){
        });
    </script>
@endsection
