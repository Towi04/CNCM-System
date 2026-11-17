@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Inicio
@endsection

@section('css')
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="element-wrapper">
        <div class="element-box">
            <h5 class="form-header">
                Seleccionar Sucursal
            </h5>
            <div class="form-desc">
                Debes asignar una sucursal para poder continuar
            </div>
            @can('asignar_varias_sucursales')
                {!! Form::open(['route' => 'admin.sucursales.asociar-sucursal', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <fieldset class="form-group">
                        <legend><span>Sucursales</span></legend>

                        @foreach ($sucursales->chunk(2) as $chunk)
                            <div class="row">
                                @foreach ($chunk as $key => $sucursal)
                                    <div class="col-md-6">
                                        <label>
                                            {!! Form::checkbox('sucursales[]', $sucursal->id,
                                                in_array($sucursal->id, old('sucursales',[]) )
                                            , ['class' => 'i-checks']) !!}
                                            {{ $sucursal->nombre }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </fieldset>
                     <div class="form-buttons-w text-right">
                        <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                    </div>
                {!! Form::close() !!}
            @endcan
        </div>
    </div>
@endsection

@section('scripts')
@endsection
