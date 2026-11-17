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

    <div class="row">
        @can('ver_cumpleaños_personal')
            <div class="col-6">
                <div class="element-box">
                    <h4 class="element-header">
                        Cumpleaños de personal
                    </h4>
                    <table  class="table">
                        <thead>
                            <tr>
                                <th>Personal</th>
                                <th>Fecha nacimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($usuarios_cumples as $personal)
                                <tr>
                                    <td>{{$personal->fullname}}</td>
                                    <td>{{$personal->fecha_nacimiento->format('d-m-Y')}}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endcan
        @can('ver_cumpleaños_alumnos')
            <div class="col-6">
                <div class="element-box">
                    <h4 class="element-header">
                        Cumpleaños de alumnos
                    </h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Fecha nacimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($alumnos_cumples as $alumno)
                                <tr>
                                    <td>{{$alumno->fullname}} </td>
                                    <td>{{$alumno->fecha_nacimiento->format('d-m-Y')}}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
              
            </div>
        @endcan

        @can('ver_alertas')
        <div class="col-6">
            <div class="element-box">
                <h4 class="element-header">
                    Alertas de hoy
                    <span class="text-right">
                        <a href="{{route('reportes.alertas')}}">Ver reporte</a>
                    </span>
                </h4>
                <table class="table table-padded">
                    <tbody>
                        @foreach ($alertas as $alerta)
                            <tr>
                                <td>
                                    <h5>{{$alerta->titulo}}</h5>
                                    {!! $alerta->descripcion !!}

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
          
        </div>
    @endcan
    </div>

    @empty(session('sucursal'))
        <div class="element-wrapper">
            <div class="element-box">
                <h5 class="form-header">
                    No tienes asignada una sucursal
                </h5>
                <div class="form-desc">
                    Para poder continuar, solicita que te asignen una sucursal
                </div>
            </div>
        </div>
    @endempty
@endsection

@section('scripts')
@endsection
