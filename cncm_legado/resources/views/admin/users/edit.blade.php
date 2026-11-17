@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Usuarios
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{url('/')}}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{route('admin.usuarios.index')}}">Usuarios</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar usuario {{$user->nombre}}</strong>
        </li>
    </ol>
@endsection

@section('contenido')

    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model( $user, ['route' => ['admin.usuarios.update', $user],'method' => 'PUT',  'accept-charset'=>'UTF-8','enctype'=>'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <h5 class="form-header">
                        Llena el formulario
                    </h5>

                    <fieldset class="form-group">
                        <legend><span>Información del usuario</span></legend>
                        @include('admin.users.partials.fields')
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



document.addEventListener("DOMContentLoaded", function() {

    $('.datepicker').datepicker({
        language: 'es',
        format: 'yyyy-mm-dd',
        ignoreReadonly: false,
        todayHighlight: true,
        todayBtn: true
    });

})

    </script>
@endsection
