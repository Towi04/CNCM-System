@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Mi Perfil
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('profile.index') }}">Mi Perfil</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar mi perfil</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="element-wrapper">
        <h6 class="element-header">
            Mi perfil
        </h6>
        <div class="element-box">
            {!! Form::model($user, ['route' => ['profile.update', $user], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data']) !!}
            <h5 class="form-header">
                Datos Personales
            </h5>
            <div class="form-desc">
                En esta sección podras actualizar tu información personal
            </div>
            @include('profile.partials._fields')
            <div class="form-buttons-w">
                <button class="btn btn-primary" type="submit"> Guardar</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>

@endsection


@section('scripts')
    <script type="text/javascript">
        $(document).ready(function() {
        });
    </script>
@endsection
