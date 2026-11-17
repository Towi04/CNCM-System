@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Sucursales <small>Administra las sucursales del sistema</small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.sucursales.index') }}">Sucursales</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Nueva sucursal</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::open(['route' => 'admin.sucursales.store', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                <h5 class="form-header">
                    Llena el formulario
                </h5>

                <fieldset class="form-group">
                    <legend><span>Informacion de la sucursal</span></legend>
                    @include('admin.sucursales.partials.fields')
                </fieldset>

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
        $(document).ready(function() {
        });
    </script>
@endsection
