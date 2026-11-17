@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo','Editar horario')

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            Asesorias
        </li>

        <li class="breadcrumb-item">
            <a href="{{ route('asesorias.horarios-profesores.index') }}">Horarios Profesores</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar horario</strong>
        </li>
    </ol>
@endsection

@section('contenido')

    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model($horarioProfesor, ['route' => ['asesorias.horarios-profesores.update', $horarioProfesor], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <h5 class="form-header">
                        Llena el formulario
                    </h5>

                    @include('asesorias.horario_profesor.partials._fields')

                    <div class="form-buttons-w text-right">
                        <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection


@section('scripts')


@endsection
