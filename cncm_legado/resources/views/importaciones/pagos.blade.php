@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Crear Alumno <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('alumnos.index') }}">Importar pagos</a>
        </li>
    </ol>
@endsection

@section('contenido')

{!! Form::open(['route' => 'pagos.importar.store', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
<div class="form-group">
  <label for="">Sube el archivo</label>
  <input type="file" class="form-control-file" name="pagos" id="" placeholder="" aria-describedby="fileHelpId">
  <small id="fileHelpId" class="form-text text-muted">Help text</small>
</div>

<div class="form-group">
  <label for="">Selecciona una sucursal: </label>
  {!! Form::select('id_sucursal', $sucursales, null, ['class'=>'form-control']) !!}
</div>

<button class="btn btn-success">Importar</button>
{!!Form::close() !!}
@endsection 

