@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Crear un Pre-Registro de Alumno
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('pre-registro-alumnos.index') }}">Pre-Registro Alumnos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Nuevo Alumno</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::open(['route' => 'pre-registro-alumnos.store', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                <h5 class="form-header">
                    Llena el formulario
                </h5>

                @include('alumnos.pre_registro.partials._fields')

                <div class="form-buttons-w text-right">
                    <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection


@section('scripts')
    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>
    <script type="text/javascript">
    </script>
@endsection
