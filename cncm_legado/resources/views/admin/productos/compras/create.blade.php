
@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo','Crear Compra')

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.compras.index', $producto->id) }}">Compras de producto {{$producto->nombre}}</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Nuevo Compra</strong>
        </li>
    </ol>
@endsection

@section('contenido')

    <div class="row">
        <div class="col-12">
            <div class="element-box">
                {!! Form::open(['route' => 'admin.compras.store', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' =>'multipart/form-data','onsubmit' => 'wait.modal("show")']) !!}
                    <h6 class="element-header">
                        Información del de la compra
                    </h6>

                    @include('admin.productos.compras.partials._fields')

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
