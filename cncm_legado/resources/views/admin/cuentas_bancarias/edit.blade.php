@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Editar cuenta bancaria <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.cuentas-bancarias.index') }}">Cuentas Bancarias</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar Cuenta Bancaria</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model($cuentaBancaria, ['route' => ['admin.cuentas-bancarias.update', $cuentaBancaria], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <h5 class="form-header">
                        Llena el formulario
                    </h5>

                    @include('admin.cuentas_bancarias.partials._fields')

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
