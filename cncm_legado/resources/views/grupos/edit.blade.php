@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Editar Grupo <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('grupos.index') }}">Grupos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar Grupo</strong>
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
                {!! Form::model($grupo, ['route' => ['grupos.update', $grupo], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <h5 class="form-header">
                        Llena el formulario
                    </h5>

                    @include('grupos.partials._fields')

                    <div class="form-buttons-w text-right">
                        <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                    </div>
                {!! Form::close() !!}
            </div>

            <div class="row">
                <div class="col-4">
                    <div class="element-box">
                        {!! Form::open(['route' => 'grupos.guardar_precio', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                        <h5 class="form-header">
                            Registro de precios
                        </h5>
        
                        @include('grupos.partials._fields_precios')
        
                        <div class="form-buttons-w text-right">
                            <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar cambios</button>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
                <div class="col-8">
                    <div class="element-box">
                
                        <h5 class="form-header">
                            Historico de precios
                        </h5>
                        
                        @php
                            $precios = $grupo->precios;
                        @endphp
                        @include('grupos.partials._historico_precios')
        
                    </div>
                </div>
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
