@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Crear Grupo <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('grupos.index') }}">Grupo</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Nuevo Grupo</strong>
        </li>
    </ol>
@endsection

@section('contenido')
<style>
    .horas{
        display:none;
    }
</style>
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::open(['route' => 'grupos.store', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                <h5 class="form-header">
                    Llena el formulario
                </h5>

                @include('grupos.partials._fields')

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
