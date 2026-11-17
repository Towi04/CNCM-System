@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Detalle del Alumno <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('alumnos.index') }}">Alumnos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Detalle Alumno</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
    <style>
        .contact-box:hover {
            transform: scale(1.05)
        }

        .borderless td,
        .borderless th {
            border: none;
        }

        .contact-box:hover {
            transform: scale(1.05)
        }

        .activity-boxes-w .activity-box:before {
            position: absolute;
            top: 50%;
            left: -30px;
            content: "";
            width: 12px;
            height: 12px;
            border: 0px solid #60769f;
            background-color: #f2f4f8;
            border-radius: 20px;
            -webkit-transform: translateY(-50%);
            transform: translateY(-50%);
            z-index: 2;
        }

        .content-box {
            padding: 0px !important;
        }

        .swal2-overflow {
            overflow-x: visible;
            overflow-y: visible;
        }
    </style>

    <div class="row p-3">
        <div class="col-4 col-lg-4 col-sm-4 col-md-4 col-xs-12">
            <div class="user-profile compact">
                <div class="up-head-w"
                    style="background-image: linear-gradient( var(--primary), 70%, var(--primary));">

                    <div class="up-main-info text-center " style="padding-bottom: 150px; padding-top:10px">
                        <img alt="" src="{{ $alumno->url_foto }}" style="width: 50%">
                        @can('subir_foto_alumnos')
                            {!! Form::open(['route' => ['alumnos.subir_foto'], 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                                <div class="form-buttons-w text-right">
                                    <label for="input-file-now">Foto: </label>
                                    <input type="file" id="input-file-now" class="dropify" name="foto" value="{{ @$alumno->foto }}" @if ($alumno->foto) data-default-file="{{ url('archivo/alumnos_foto/'.$alumno->id.'/'. $alumno->foto) }}" @endif />
                                    <input type="hidden" name="id_alumno" value="{{$alumno->id}}">
                                    <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Subir foto</button>
                                </div>
                            {!! Form::close() !!}
                        @endcan

                        <h2 class="up-header">
                            {{ $alumno->nuevo_numero_control }} - {{ $alumno->full_name }}
                        </h2>
                        <h6 class="up-sub-header">
                        Alumno
                        <br>
                            @role('administrador')
                                <a class="btn btn-info btn-xs btn-sm mt-1" href="{{route('especiales.generar_abonos_alumno', $alumno->id)}}">
                                    Aplicar pagos a documentos
                                </a>
                            @endrole
                        </h6>

                        
                    </div>
                    <svg class="decor" width="842px" height="219px" viewBox="0 0 842 219"
                        preserveAspectRatio="xMaxYMax meet" version="1.1" xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink">
                        <g transform="translate(-381.000000, -362.000000)" fill="#FFFFFF">
                            <path class="decor-path"
                                d="M1223,362 L1223,581 L381,581 C868.912802,575.666667 1149.57947,502.666667 1223,362 Z">
                            </path>
                        </g>
                    </svg>
                </div>

                <div class="up-controls">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="value-pair">
                                {{-- <div class="label">
                                    Status:
                                </div>
                                <div class="value badge badge-pill badge-{{ $cliente->statusClass }}">
                                    {{ $cliente->status }}
                                </div> --}}
                            </div>
                        </div>
                        <div class="col-sm-6 text-right">
                        </div>
                    </div>
                </div>

                <div class="up-contents">
                    <div class="m-b">
                        <div class="row m-b">
                            <div class="col-sm-12 b-b">

                            </div>
                        </div>
                        <div class="p-2">
                            @can(['editar_alumno'])
                                <a href="{{ route('alumnos.edit', $alumno) }}"
                                    class="btn btn-info btn-sm btn-circle float-right text-white mb-2" data-toggle="tooltip"
                                    data-placement="left" title="Editar informacion">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            @endcan
                            <h3>Especialidades</h3>
                            @foreach ($alumno->especialidades as $especialidad)
                            <div class="post-box">
                                {{-- <div class="post-media" style="background-image: url(img/portfolio1.jpg)"></div> --}}
                                <div class="post-content">
                                <h6 class="post-title">
                                    @if($especialidad->pivot->status == 'Pausa') 
                                    <span class="float-right badge badge-warning"> {{$especialidad->pivot->status }}</span>
                                    @else 
                                    <span class="float-right badge badge-success"> {{$especialidad->pivot->status }}</span>
                                    @endif
                                    Especialidad: {{$especialidad->nombre}} 
                                   
                                </h6>
                                <div class="post-text">
                                    <b>Fecha de inicio:</b> 
                                    <a class='
                                    @can('cambiar_fecha_inicio_especialidad') editable_fecha_inicio_grupo @endcan
                                    ' data-pk='{{$especialidad->pivot->id}}' data-name='fecha_inicio' data-url='{{route("alumnos.actualizar_informacion_alumnos_especialidades")}}' data-type='date' data-value="{{ optional($especialidad->pivot->fecha_inicio)->format('d-m-Y') }}">
                                    {!!  optional($especialidad->pivot->fecha_inicio)->format('d-m-Y') !!}
                                    </a>
                                    <br>
                                    <b>Forma pago:</b> 
                                    <a class='editable_forma_pago' data-pk='{{ $especialidad->pivot->id}}' data-name='forma_pago' data-url='{{route("alumnos.actualizar_informacion_alumnos_especialidades")}}' data-type='select' data-value='{{ $especialidad->pivot->forma_pago}}'>
                                    </a>
                                    
                                    <br>
                                    <b>Monto Pronto Pago:</b> 
                                    <a class='editable_forma_pago' data-pk='{{ $especialidad->pivot->id}}' data-name='monto_pronto_pago' data-url='{{route("alumnos.actualizar_informacion_alumnos_especialidades")}}' data-type='text' data-value='{{ $especialidad->pivot->monto_pronto_pago}}'>
                                        $ {{ number_format($especialidad->pivot->monto_pronto_pago,2,'.',',')}}
                                    </a><br>
                                    <b>Monto:</b> 
                                    <a class='editable_forma_pago' data-pk='{{ $especialidad->pivot->id}}' data-name='monto' data-url='{{route("alumnos.actualizar_informacion_alumnos_especialidades")}}' data-type='text' data-value='{{ $especialidad->pivot->monto}}'>
                                        $ {{ number_format($especialidad->pivot->monto,2,'.',',')}}
                                    </a><br>
                                    
                                    <b>Total semanas:</b> {{$especialidad->pivot->semanas_cursar }}<br>
                                    <b>Semanas cursadas:</b>  {{$especialidad->pivot->semanas_cursadas}}<br>
                                    <b>Fecha Inicio: </b>
                                    
                                    <b>Status:</b>  {!! (!empty($especialidad->pivot->status)) ? $especialidad->pivot->status: '' !!}<br>
                                </div>
                                <div class="post-foot">
                                    <div class="row">
                                        <div class="col-12">
                                            
                                        </div>
                                        <div class="col-12">
                                            
                                        </div>
                                    </div>



                                <br>


                                </div>
                                </div>
                            </div>
                            @endforeach

                            <h4>Grupos Actuales</h4>
                            @foreach ($alumno->grupos->whereIn('pivot.status',['Inscrito','Pausa']) as $grupo)
                            <div class="post-box">
                                {{-- <div class="post-media" style="background-image: url(img/portfolio1.jpg)"></div> --}}
                                <div class="post-content">
                                <h6 class="post-title">
                                    @if($grupo->pivot->status == 'Pausa') 
                                    <span class="float-right badge badge-warning"> {{$grupo->pivot->status }}</span>
                                    @else 
                                    <span class="float-right badge badge-success"> {{$grupo->pivot->status }}</span>
                                    @endif
                                    Grupo: {{$grupo->clave}} - {{$grupo->especialidad->nombre}} 
                                   
                                </h6>
                                <div class="post-text">
                                    <b>Fecha inicio:</b> {{ $grupo->fecha_inicio->format('d-m-Y')}}<br>
                                    <b>Horario:</b><br> {!!$grupo->horario_corto!!}
                                    <b>N° Semanas a cursar:</b>  {!!$grupo->materias->sum('semanas')!!}<br>
                                    <b>Fecha Inicio: </b>
                                    <a class='
                                    @can('cambiar_fecha_inicio_grupo') editable_fecha_inicio_grupo @endcan
                                    ' data-pk='{{$grupo->pivot->id}}' data-name='fecha_inicio' data-url='{{route("especiales.actualizar_informacion_grupos_alumnos")}}' data-type='date' data-value="{{ optional($grupo->pivot->fecha_inicio)->format('d-m-Y') }}">
                                    {!!  optional($grupo->pivot->fecha_inicio)->format('d-m-Y') !!}
                                    </a>
                                    <br>
                                    <b>Semanas cursadas:</b>  {!! (!empty($grupo->pivot->fecha_inicio)) ? $grupo->pivot->fecha_inicio->diffInWeeks( now() ): '' !!}<br>
                                    <b>Status:</b>  {!! (!empty($grupo->pivot->status)) ? $grupo->pivot->status: '' !!}<br>
                                </div>
                                <div class="post-foot">
                                    <div class="row">
                                        <div class="col-12">
                                            @can('cambio_horario_grupo')
                                                <a href="{{route('alumnos.cambio_horario', [$alumno->id,$grupo->id])}}" class="btn btn-sm btn-dark">Cambio Horario</a>
                                            @endcan
                                            
                                            @can('baja_grupo')
                                                @if($grupo->pivot->status == 'Inscrito')
                                                    <button data-id="{{$grupo->id}}" class="btn btn-sm ml-0 btn-warning pausar_grupo text-dark"><i class="fas fa-pause    "></i> Pausa</button>
                                                @else 
                                                    <button data-id="{{$grupo->id}}" class="btn btn-sm ml-0 btn-success reaundar_grupo"><i class="fas fa-play    "></i> Reanudar</button>
                                                @endif
                                            @endcan

                                            @can('dar_fin_de_curso')
                                                <button data-id="{{$grupo->id}}" class="btn btn-sm ml-0 mt-1 btn-info dar_fin_de_curso text-white"> Fin de curso</button>
                                            @endcan
                                        </div>
                                        <div class="col-12">
                                            <div class="post-tags mt-2">
                                                <div class="badge badge-primary">
                                                    {{-- Alumnos {{$grupo->alumnos->count()}} --}}
                                                </div>

                                                <a class="post-link float-right" href="{{route('grupos.show', $grupo)}}"><span>Ir a grupo</span><i class="os-icon os-icon-arrow-right7"></i></a>
                                            </div>
                                        </div>
                                    </div>



                                <br>


                                </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-8 col-lg-8 col-sm-8 col-md-8 col-xs-12">

            <div class="row">
                <div class="col-sm-12 col-xxxl-9">
                    <div class="element-wrapper">
                        <div class="element-box">
                            <div class="os-tabs-w">
                                <div class="os-tabs-controls">
                                    <ul class="nav nav-tabs smaller">

                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-documentos">Documentos.</a>
                                        </li>

                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="tab" href="#tab-historial-pagos">Historial de pagos</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-info-alumno">Información del alumno</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-info-asistencias">Asistencias</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-info-productos">Productos</a>
                                        </li>
                                        @can('crear_notas_alumnos')
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-notas">Notas</a>
                                        </li>
                                        @endif
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-historial-grupos">Historial de grupos</a>
                                        </li>


                                    </ul>
                                    <ul class="nav nav-pills smaller d-none d-md-flex">
                                    </ul>
                                </div>

                                <div class="tab-content">
                                    <div class="tab-pane" id="tab-documentos">

                                        {!! Form::open(['route' => ['especiales.generar_documentos_alumno', $alumno->id], 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                                            <div class="form-group">
                                                {!! Form::label('id_especialidad','Selecciona la especialidad:') !!}
                                                {!! Form::select('id_especialidad', $alumno->especialidades->pluck('nombre','id')->prepend('TODAS',''), optional($alumno->especialidades->first())->id, ['id'=>'select_especialidad_documentos','class'=>'form-control w-100']) !!}
                                            </div>
                                            @role('administrador')
                                                <button class="btn btn-info btn-xs btn-sm  mt-1" id="btn_actualizar_docuemntos">
                                                    Actualizar documentos
                                                </button>
                                                <span id="msje_actualizar_docmuentos" style="display: none" class="text-danger">Tienes que seleccionar una especialidad para actualizar documentos</span>
                                            @endrole
                                            <br>
                                        {!! Form::close() !!}

                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered table-hover" id="tb-documentos" width="100%">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Concepto</th>
                                                        <th>Monto</th>
                                                        <th>Saldo</th>
                                                        <th>Fecha Limite</th>
                                                        <th>Pago(s)</th>
                                                        <th>Created at</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                       

                                        @can('asignar_apoyos_especiales_en_inscripcion')

                                            <fieldset class="form-group">
                                                <legend>Apoyos en inscripcion</legend>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <button type="button" id="btn-agregar-apoyo-inscripcion" title="Apoyo inscripcion" class="btn btn-sm btn-primary">Agregar Apoyo a Inscripción</button>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-12">
                                                        <table class="table table-striped table-bordered table-hover" id="tb-apoyos-inscripcion" width="100%">
                                                            <thead>
                                                                <tr>    
                                                                    <th></th>
                                                                    <th>Especialidad</th>
                                                                    <th>Monto</th>
                                                                    <th>Motivo</th>
                                                                    <th>Autorizo</th>
                                                                    <th>Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>

                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </fieldset>

                                        @endcan

                                        @can('asignar_apoyos_especiales')
                                            <fieldset class="form-group">
                                                <legend>Apoyos especiales</legend>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <button type="button" id="btn-agregar-apoyo-especial" title="Apoyo especial" class="btn btn-sm btn-primary">Agregar Apoyo</button>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-12">
                                                        <table class="table table-striped table-bordered table-hover" id="tb-apoyos-especiales" width="100%">
                                                            <thead>
                                                                <tr>
                                                                    <th></th>
                                                                    <th>Fecha Inicio</th>
                                                                    <th>Fecha Final</th>
                                                                    <th>Monto</th>
                                                                    <th>Tipo</th>
                                                                    <th>Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </fieldset>
                                        @endcan

                                    </div>

                                    <div class="tab-pane" id="tab-pagos-pendientes">
                                        <div class="form-group">
                                            {!! Form::label('id_grupo','Selecciona el grupo:') !!}
                                            {!! Form::select('id_grupo', $alumno->grupos->pluck('nombre_compuesto','id'), optional($alumno->grupos->first())->id, ['id'=>'select_grupo','class'=>'form-control w-100']) !!}
                                        </div>

                                        <table class="table table-striped table-bordered table-hover" id="tb-pagos" width="100%">
                                            <thead>
                                                <tr>
                                                    <th># Pago</th>
                                                    <th>Concepto</th>
                                                    <th>Monto</th>
                                                    <th>Saldo</th>
                                                    <th>Fecha Limite</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>

                                        @can('asignar_apoyos_especiales')
                                            <fieldset class="form-group">
                                                <legend>Apoyos especiales</legend>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <button type="button" id="btn-agregar-apoyo-especial" title="Apoyo especial" class="btn btn-sm btn-primary">Agregar Apoyo</button>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-12">
                                                        <table class="table table-striped table-bordered table-hover" id="tb-apoyos-especiales" width="100%">
                                                            <thead>
                                                                <tr>
                                                                    <th></th>
                                                                    <th>Fecha Final</th>
                                                                    <th>Monto</th>
                                                                    <th>Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </fieldset>
                                        @endcan

                                    </div>

                                    <div class="tab-pane active" id="tab-historial-pagos">

                                        <div class="form-group">
                                            {!! Form::label('id_especialidad','Selecciona la especialidad:') !!}
                                            {!! Form::select('id_especialidad', $alumno->especialidades->pluck('nombre','id')->prepend('TODAS',''), optional($alumno->especialidades->first())->id, ['id'=>'select_especialidad_historial_pagos','class'=>'form-control w-100']) !!}
                                        </div>

                                        <table class="table table-striped table-bordered table-hover" id="tb-historial-pagos" width="100%">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Especialidad</th>
                                                    <th>Folio</th>
                                                    <th>Folio Fiscal</th>
                                                    <th>Pago</th>
                                                    <th>Forma Pago</th>
                                                    <th>Cubrio</th>
                                                    <th>Recibio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>


                                    </div>

                                    <div class="tab-pane" id="tab-info-alumno">
                                        @include('alumnos.partials._info_alumno')
                                    </div>

                                    <div class="tab-pane" id="tab-info-asistencias">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>A</th>
                                                    <th>Fecha</th>
                                                    <th>Grupo / Especialidad</th>
                                                    
                                                </tr>
                                            </thead>
                                            @foreach ($alumno->asistencias->sortByDesc('fecha') as $asistencia)
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-check text-success   "></i>
                                                    </td>
                                                    <td>
                                                        {{$asistencia->fecha->format('l d \d\e F \d\e\l Y \a \l\a\s H:i')}}
                                                    </td>
                                                    <td>
                                                        @if($asistencia->grupo)
                                                            {{optional($asistencia->grupo)->nombre_compuesto}}
                                                        @else 
                                                            <span class="text-info">Paso huella fuera de horario de grupo</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>


                                    <div class="tab-pane" id="tab-info-productos">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Conceptos</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            @foreach ($alumno->ventas as $venta)

                                                    <td>
                                                        {{$venta->fecha->format('d-m-Y')}}
                                                    </td>
                                                    <td>
                                                        @foreach ($venta->partidas as $partida)
                                                            {{optional($partida->producto)->nombre}}<br>
                                                        @endforeach
                                                    </td>
                                                    <td class="text-right">
                                                        {{number_format($venta->total,2,'.',',')}}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>

                                    <div class="tab-pane" id="tab-notas">
                                        <a id="btn-notas" class="btn btn-info text-white btn-sm" >Agregar nota</a>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Nota</th>
                                                    <th class="text-right">Autor</th>
                                                </tr>
                                            </thead>
                                            @foreach ($alumno->notas->sortByDesc('fecha') as $nota)
                                                    <td>
                                                        {{optional($nota->fecha)->format('d-m-Y H:m:s')}}
                                                    </td>
                                                    <td>
                                                       {!! $nota->nota !!}
                                                    </td>
                                                    <td class="text-right">
                                                        {{$nota->usuario->fullname}}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>


                                    <div class="tab-pane" id="tab-historial-grupos">
                                        
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Grupo</th>
                                                    <th>Fecha Inicio</th>
                                                    <th>Fecha Final</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            @foreach ($alumno->grupos->sortByDesc('fecha_inicio') as $grupo)
                                                    <td>
                                                        {{$grupo->nombre_compuesto}}
                                                    </td>
                                                    <td class="text-center text-nowrap">
                                                       {{ optional($grupo->pivot->fecha_inicio)->format('d-m-Y') }}
                                                    </td>
                                                    <td class="text-center text-nowrap">
                                                        {{ optional($grupo->pivot->fecha_final)->format('d-m-Y') }}
                                                    </td>
                                                    <td class="text-right">
                                                        {{ $grupo->pivot->status }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('alumnos.modals.apoyos_especiales')
    @include('alumnos.modals.apoyos_inscripciones')
    @include('alumnos.modals.notas')
    @include('alumnos.modals.modalPausarGrupo')
@endsection


@section('scripts')

<link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
<script src="{{ asset('plugins/xeditable/js/bootstrap-editable.min.js') }}"></script>

    <script type="text/javascript">
        $(document).ready(function() {
            const dom = {
                tb_pagos: $("#tb-pagos"),
                tb_documentos: $("#tb-documentos"),
                tb_historial_pagos: $("#tb-historial-pagos"),
                tb_apoyos: $("#tb-apoyos-especiales"),
                btn_apoyo_especial: $('#btn-agregar-apoyo-especial'),
                form_apoyo_especial: $("#form-apoyo-especial"),
                modal_apoyo_especial: $("#modal-apoyo-especial"),

                btn_notas: $('#btn-notas'),
                form_notas: $("#form-notas"),
                modal_notas: $("#modal-notas"),


                tb_apoyos_inscripcion: $("#tb-apoyos-inscripcion"),
                btn_apoyo_inscripcion: $('#btn-agregar-apoyo-inscripcion'),
                form_apoyo_inscripcion: $("#form-apoyo-inscripcion"),
                modal_apoyo_inscripcion: $("#modal-apoyo-inscripcion")
            }

            const CONFIG_DATEPICKER = {
                days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
                daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
                daysMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
                months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
                monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
                today: "Hoy",
                monthsTitle: "Meses",
                clear: "Borrar",
            }

            $.fn.datepicker.dates['es'] = CONFIG_DATEPICKER  //👉 DATEPICKER

            dom.form_apoyo_especial.find('[name="fecha_final"]').datepicker({
                language: 'es',
                format: 'dd-mm-yyyy',
                ignoreReadonly: false,
                todayHighlight: true,
                todayBtn: true,
                autoclose: true,
            });

            dom.form_apoyo_especial.find('[name="fecha_inicio"]').datepicker({
                language: 'es',
                format: 'dd-mm-yyyy',
                ignoreReadonly: false,
                todayHighlight: true,
                todayBtn: true,
                autoclose: true,
            });

            var dt_pagos = dom.tb_pagos.DataTable({
                dom: "<'row'<'col-12'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                processing: true,
                serverSide: true,
                responsive: true,
                pageLength: 10,
                ajax: {
                    url: "{{ route('alumnos.datatables_pagos') }}",
                    type: "POST",
                    data: function (d) {
                        d.id_alumno = "{{ $alumno->id }}";
                        d.id_grupo = $('#select_grupo').val();
                        d._token = $("meta[name='csrf-token']").attr("content");
                    },
                    beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                        }
                    },
                },
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false,orderable: false},
                    {data: 'concepto', name: 'concepto'},
                    {data: 'monto', name: 'monto',className:"text-right"},
                    {data: 'saldo', name: 'saldo', className:"text-right"},
                    {data: 'fecha_limite', name: 'fecha_limite'},
                    {data: 'status', className:"text-center", name: 'status'},
                ],
                order: [[ 4, "asc" ]],
                language: {
                    "lengthMenu": "Mostrar _MENU_ registros por pagina",
                    "zeroRecords": "No se encontro ningún registro",
                    "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primera",
                        "last": "Última",
                        "previous": '<i class="fas fa-chevron-left"></i>',
                        "next": '<i class="fas fa-chevron-right"></i>'
                    },
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                },
                drawCallback: function (settings) {
                    $("[data-toggle='tooltip']").tooltip();
                },
                initComplete: function(settings, json) {
                    // var dt_apoyos = dom.tb_apoyos.DataTable({
                    //     dom: "<'row'<'col-12'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                    //     processing: true,
                    //     serverSide: true,
                    //     responsive: true,
                    //     pageLength: 10,
                    //     ajax: {
                    //         url: "{{ route('apoyos-especiales.datatables') }}",
                    //         type: "POST",
                    //         data: function (d) {
                    //             d.id_alumno = "{{ $alumno->id }}";
                    //             d.id_grupo = $('#select_grupo').val();
                    //             d._token = $("meta[name='csrf-token']").attr("content");
                    //         },
                    //         beforeSend: function(xhr,type) {
                    //         if (!type.crossDomain) {
                    //                 xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                    //             }
                    //         },
                    //     },
                    //     columns: [
                    //         {data: 'id', name: 'id',visible:false},
                    //         {data: 'fecha_final', name: 'fecha_final'},
                    //         {data: 'precio', name: 'precio'},
                    //         { data: 'buttons', name: 'buttons', orderable: false, searchable: false },
                    //     ],
                    //     order: [[ 0, "asc" ]],
                    //     language: {
                    //         "lengthMenu": "Mostrar _MENU_ registros por pagina",
                    //         "zeroRecords": "No se encontro ningún registro",
                    //         "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                    //         "infoEmpty": "No hay registros disponibles",
                    //         "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                    //         "search": "Buscar:",
                    //         "paginate": {
                    //             "first": "Primera",
                    //             "last": "Última",
                    //             "previous": '<i class="fas fa-chevron-left"></i>',
                    //             "next": '<i class="fas fa-chevron-right"></i>'
                    //         },
                    //         "loadingRecords": "Cargando...",
                    //         "processing": "Procesando...",
                    //     },
                    //     drawCallback: function (settings) {
                    //         $("[data-toggle='tooltip']").tooltip();
                    //     },
                    // });

                    // dom.tb_apoyos.on('click',"a[data-action='delete']",function(event){
                    //     event.preventDefault();

                    //     swal({
                    //         title: "¿Estas seguro de eliminar el registro?",
                    //         type: "warning",
                    //         showCancelButton: true,
                    //         confirmButtonColor: "#ff3333",
                    //         cancelButtonColor: "#CDCDCD",
                    //         confirmButtonText: "Borrar",
                    //         cancelButtonText: "Cancelar",
                    //         showLoaderOnConfirm: false,
                    //     }).then(function(result) {
                    //         if (!result.value) {
                    //             return;
                    //         }

                    //         wait.modal('show');

                    //         $.ajax({
                    //             url: event.target.href,
                    //             type: 'POST',
                    //             cache: false,
                    //             data: {
                    //                 _token: $("meta[name='csrf-token']").attr("content"),
                    //                 _method: 'DELETE',
                    //             },
                    //             success: function (response){
                    //                 dt_apoyos.ajax.reload( function(e){
                    //                     wait.modal('hide');
                    //                     toastr.success('Éxito', 'Se borró con éxito el registro');
                    //                 }, false )
                    //             },
                    //             error:function(error){
                    //                 setTimeout(() => {
                    //                     wait.modal('hide');
                    //                     toastr.error('Error', 'Ocurrio un error inesperado');
                    //                 }, 250);
                    //             }
                    //         });
                    //     })
                    // })

                    // $('#select_grupo').change(function(){
                    //     dt_pagos.draw();
                    //     dt_apoyos.draw();
                    // });

                    // dom.btn_apoyo_especial.click(function(e){
                    //     dom.form_apoyo_especial[0].reset();
                    //     dom.modal_apoyo_especial.modal('show');
                    // })

                    // dom.form_apoyo_especial.submit(function(e){
                    //     e.preventDefault();

                    //     dom.modal_apoyo_especial.modal('hide');
                    //     wait.modal('show');

                    //     const $form = $(this);
                    //     const formData = new FormData(this);
                    //     formData.append('id_grupo',$('#select_grupo').val());
                    //     formData.append('id_alumno',"{{ $alumno->id }}");

                    //     $.ajax({
                    //         url: $form.attr('action'),
                    //         type: 'POST',
                    //         cache: false,
                    //         contentType: false,
                    //         processData: false,
                    //         data: formData,
                    //         success: function (response){
                    //             dt_apoyos.ajax.reload( function(e){
                    //                 setTimeout(() => {
                    //                     wait.modal('hide');
                    //                     toastr.success('Éxito', response.message || 'Apoyo agregado correctamente');
                    //                 })
                    //             }, false )
                    //         },
                    //         error:function(error){
                    //             wait.modal('hide');
                    //             const errors = error.responseJSON || {};

                    //             setTimeout(() => {
                    //                 dom.modal_apoyo_especial.modal('show');
                    //                 toastr.error('Error',  errors.message || 'Ocurrio un error inesperado');
                    //             }, 250);
                    //         }
                    //     });


                    // })
                }
            });

            var tb_historial_pagos = dom.tb_historial_pagos.DataTable({
                dom: "<'row'<'col-12'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                processing: true,
                serverSide: true,
                responsive: true,
                pageLength: -1,
                ajax: {
                    url: "{{ route('alumnos.datatables_historial_pagos') }}",
                    type: "POST",
                    data: function (d) {
                        d.id_alumno = "{{ $alumno->id }}";
                        d.id_especialidad = $('#select_especialidad_historial_pagos').val();
                        d._token = $("meta[name='csrf-token']").attr("content");
                    },
                    beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                        }
                    },
                },
                columns: [
                    {data: 'fecha', name: 'fecha'},
                    {data: 'especialidad.nombre', name: 'especialidad.nombre'},
                    {data: 'folio', name: 'folio'},
                    {data: 'folio_fiscal', name: 'folio_fiscal',visible:false},
                    {data: 'monto', name: 'monto'},
                    {data: 'forma_pago', name: 'forma_pago'},
                    {data: 'abonos_documentos.documento.concepto', name: 'abonos_documentos.documento.concepto'},
                    {data: 'recibio.nombres', name: 'recibio.nombres'},
                ],
                order: [[ 0, "desc" ]],
                language: {
                    "lengthMenu": "Mostrar _MENU_ registros por pagina",
                    "zeroRecords": "No se encontro ningún registro",
                    "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primera",
                        "last": "Última",
                        "previous": '<i class="fas fa-chevron-left"></i>',
                        "next": '<i class="fas fa-chevron-right"></i>'
                    },
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                },
                drawCallback: function (settings) {
                    $("[data-toggle='tooltip']").tooltip();

                    $('.editable_especialidad').editable({
                                'emptytext':'Selecciona una esp',
                                'showbuttons':false,
                                'source':[
                                    @foreach($alumno->especialidades->pluck('nombre','id') as $id => $especialidad)
                                    { value: "{{$id}}",text: "{{$especialidad}}"},
                                    @endforeach
                                ]
                            })
                },
                initComplete: function(settings, json) {

                }
            });

            // GUARDAR LOCAL STORAGE
            // activeSelectEspecialidadShowAlumno = window.localStorage.getItem('selectEspecialidadShowAlumno');

            //INIT
            // console.log(activeSelectEspecialidadShowAlumno)

            // if (activeSelectEspecialidadShowAlumno) {
            //     $('#select_especialidad_documentos').val(activeSelectEspecialidadShowAlumno);
            // }else{
                
            //     $('#select_especialidad_documentos').val('{{optional($alumno->especialidades->first())->id}}');
            //     // $('#select_especialidad_documentos').val('');
            // }


            // DATATABLES DE DOCUMENTOS
            var dt_documentos = dom.tb_documentos.DataTable({
                dom: "<'row'<'col-12'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                processing: true,
                serverSide: true,
                responsive: true,
                pageLength: 10,
                xScroll: true,
                ajax: {
                    url: "{{ route('alumnos.datatables_documentos') }}",
                    type: "POST",
                    data: function (d) {
                        d.id_alumno = "{{ $alumno->id }}";
                        d.id_especialidad = $('#select_especialidad_documentos').val();
                        d._token = $("meta[name='csrf-token']").attr("content");
                    },
                    beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                        }
                    },
                },
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false,orderable: false},
                    {data: 'concepto', name: 'concepto'},
                    {data: 'monto', name: 'monto',className:"text-right"},
                    {data: 'saldo', name: 'saldo', className:"text-right"},
                    {data: 'fecha_limite', name: 'fecha_limite'},
                    {data: 'abonos.monto', name: 'abonos.monto', orderable:false, className: 'text-nowrap'},
                    {data: 'created_at', name: 'created_at', visible:false},
                    {data: 'status', className:"text-center", name: 'status'},
                ],
                order: [[ 4, "asc" ]],
                language: {
                    "lengthMenu": "Mostrar _MENU_ registros por pagina",
                    "zeroRecords": "No se encontro ningún registro",
                    "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primera",
                        "last": "Última",
                        "previous": '<i class="fas fa-chevron-left"></i>',
                        "next": '<i class="fas fa-chevron-right"></i>'
                    },
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                },
                drawCallback: function (settings) {
                    $("[data-toggle='tooltip']").tooltip();
                },
                initComplete: function(settings, json) {
                    var dt_apoyos = dom.tb_apoyos.DataTable({
                        dom: "<'row'<'col-12'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                        processing: true,
                        serverSide: true,
                        responsive: true,
                        pageLength: 10,
                        ajax: {
                            url: "{{ route('apoyos-especiales.datatables') }}",
                            type: "POST",
                            data: function (d) {
                                d.id_alumno = "{{ $alumno->id }}";
                                d.id_especialidad = $('#select_especialidad_documentos').val();
                                d._token = $("meta[name='csrf-token']").attr("content");
                            },
                            beforeSend: function(xhr,type) {
                            if (!type.crossDomain) {
                                    xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                                }
                            },
                        },
                        columns: [
                            {data: 'id', name: 'id',visible:false},
                            {data: 'fecha_inicio', name: 'fecha_inicio'},
                            {data: 'fecha_final', name: 'fecha_final'},
                            {data: 'precio', name: 'precio'},
                            {data: 'tipo', name: 'tipo'},
                            { data: 'buttons', name: 'buttons', orderable: false, searchable: false },
                        ],
                        order: [[ 0, "asc" ]],
                        language: {
                            "lengthMenu": "Mostrar _MENU_ registros por pagina",
                            "zeroRecords": "No se encontro ningún registro",
                            "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                            "infoEmpty": "No hay registros disponibles",
                            "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                            "search": "Buscar:",
                            "paginate": {
                                "first": "Primera",
                                "last": "Última",
                                "previous": '<i class="fas fa-chevron-left"></i>',
                                "next": '<i class="fas fa-chevron-right"></i>'
                            },
                            "loadingRecords": "Cargando...",
                            "processing": "Procesando...",
                        },
                        drawCallback: function (settings) {
                            $("[data-toggle='tooltip']").tooltip();
                        },
                    });

                   

                    dom.tb_apoyos.on('click',"a[data-action='delete']",function(event){
                        event.preventDefault();

                        href = $(this).attr('href')

                        swal({
                            title: "¿Estas seguro de eliminar el registro?",
                            type: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#ff3333",
                            cancelButtonColor: "#CDCDCD",
                            confirmButtonText: "Borrar",
                            cancelButtonText: "Cancelar",
                            showLoaderOnConfirm: false,
                        }).then(function(result) {
                            if (!result.value) {
                                return;
                            }

                            wait.modal('show');

                            $.ajax({
                                url: href,
                                type: 'POST',
                                cache: false,
                                data: {
                                    _token: $("meta[name='csrf-token']").attr("content"),
                                    _method: 'DELETE',
                                },
                                success: function (response){
                                    setTimeout(() => {
                                        dt_apoyos.ajax.reload( function(e){
                                            wait.modal('hide');
                                            toastr.success('Éxito', 'Se borró con éxito el registro');
                                        }, false )    
                                    }, 400);
                                    
                                },
                                error:function(error){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                        toastr.error('Error', 'Ocurrio un error inesperado');
                                    }, 250);
                                }
                            });
                        })
                    })

                    $('#select_especialidad_documentos').change(function(){
                        dt_documentos.ajax.reload(null, false);
                        dt_apoyos.ajax.reload(null, false);

                        window.localStorage.setItem('selectEspecialidadShowAlumno',$(this).val());

                        if($(this).val() == ''){
                            $('#btn_actualizar_docuemntos').hide();
                            $('#msje_actualizar_docmuentos').show()
                        }else{
                            $('#btn_actualizar_docuemntos').show();
                            $('#msje_actualizar_docmuentos').hide()
                        }

                    });

                    $('#select_especialidad_historial_pagos').change(function(){
                        tb_historial_pagos.ajax.reload(null, false);
                        // alert('algo');
                    });


                    


                    dom.btn_apoyo_especial.click(function(e){
                        dom.form_apoyo_especial[0].reset();
                        dom.modal_apoyo_especial.modal('show');
                    })
                    

                    dom.form_apoyo_especial.submit(function(e){
                        e.preventDefault();

                        dom.modal_apoyo_especial.modal('hide');
                        wait.modal('show');

                        const $form = $(this);
                        const formData = new FormData(this);
                        formData.append('id_especialidad',$('#select_especialidad_documentos').val());
                        formData.append('id_alumno',"{{ $alumno->id }}");

                        $.ajax({
                            url: $form.attr('action'),
                            type: 'POST',
                            cache: false,
                            contentType: false,
                            processData: false,
                            data: formData,
                            success: function (response){
                                dt_apoyos.ajax.reload( function(e){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                        toastr.success('Éxito', response.message || 'Apoyo agregado correctamente');
                                    },500)
                                }, false )
                            },
                            error:function(error){
                               
                                const errors = error.responseJSON || {};

                                setTimeout(() => {
                                    wait.modal('hide');
                                    dom.modal_apoyo_especial.modal('show');
                                    toastr.error('Error',  errors.message || 'Ocurrio un error inesperado');
                                }, 400);
                            }
                        });


                    })

                    //APOYOS A INSCRIPCION
                    var dt_apoyos_inscripcion = dom.tb_apoyos_inscripcion.DataTable({
                        dom: "<'row'<'col-12'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                        processing: true,
                        serverSide: true,
                        responsive: true,
                        pageLength: 10,
                        ajax: {
                            url: "{{ route('alumnos.datatables_apoyos_inscripcion') }}",
                            type: "POST",
                            data: function (d) {
                                d.id_alumno = "{{ $alumno->id }}";
                                d.id_especialidad = $('#select_especialidad_documentos').val();
                                d._token = $("meta[name='csrf-token']").attr("content");
                            },
                            beforeSend: function(xhr,type) {
                            if (!type.crossDomain) {
                                    xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                                }
                            },
                        },
                        columns: [
                            {data: 'id', name: 'id',visible:false},
                            {data: 'especialidad.nombre', name: 'especialidad.nombre'},
                            {data: 'apoyo', name: 'apoyo'},
                            {data: 'motivo', name: 'motivo'},
                            {data: 'usuario_autorizo.nombres', name: 'usuario_autorizo.nombres'},
                            {data: 'buttons', name: 'buttons', orderable: false, searchable: false },
                        ],
                        order: [[ 0, "asc" ]],
                        language: {
                            "lengthMenu": "Mostrar _MENU_ registros por pagina",
                            "zeroRecords": "No se encontro ningún registro",
                            "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                            "infoEmpty": "No hay registros disponibles",
                            "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                            "search": "Buscar:",
                            "paginate": {
                                "first": "Primera",
                                "last": "Última",
                                "previous": '<i class="fas fa-chevron-left"></i>',
                                "next": '<i class="fas fa-chevron-right"></i>'
                            },
                            "loadingRecords": "Cargando...",
                            "processing": "Procesando...",
                        },
                        drawCallback: function (settings) {
                            $("[data-toggle='tooltip']").tooltip();

                            


                        },
                    });

                    dom.tb_apoyos_inscripcion.on('click',"a[data-action='delete']",function(event){
                        event.preventDefault();

                        href = $(this).attr('href');

                        swal({
                            title: "¿Estas seguro de eliminar el registro de apoyo a la inscripción?",
                            type: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#ff3333",
                            cancelButtonColor: "#CDCDCD",
                            confirmButtonText: "Borrar",
                            cancelButtonText: "Cancelar",
                            showLoaderOnConfirm: false,
                        }).then(function(result) {
                            if (!result.value) {
                                return;
                            }

                            wait.modal('show');

                            $.ajax({
                                url: href,
                                type: 'POST',
                                cache: false,
                                data: {
                                    _token: $("meta[name='csrf-token']").attr("content"),
                                    _method: 'DELETE',
                                },
                                success: function (response){
                                    dt_apoyos_inscripcion.ajax.reload( function(e){

                                        setTimeout(() => {
                                            wait.modal('hide');
                                            toastr.success('Éxito', 'Se borró con éxito el registro');    
                                        }, 200);
                                    }, false )
                                },
                                error:function(error){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                        toastr.error('Error', 'Ocurrio un error inesperado');
                                    }, 250);
                                }
                            });
                        })
                    })

                    dom.btn_apoyo_inscripcion.click(function(e){
                        dom.form_apoyo_inscripcion[0].reset();
                        dom.modal_apoyo_inscripcion.modal('show');
                    })
                    

                    dom.form_apoyo_inscripcion.submit(function(e){
                        e.preventDefault();

                        dom.modal_apoyo_inscripcion.modal('hide');
                        wait.modal('show');

                        const $form = $(this);
                        const formData = new FormData(this);
                        // formData.append('id_grupo',$('#select_grupo_documentos').val());
                        // formData.append('id_alumno',"{{ $alumno->id }}");

                        $.ajax({
                            url: $form.attr('action'),
                            type: 'POST',
                            cache: false,
                            contentType: false,
                            processData: false,
                            data: formData,
                            success: function (response){
                                dt_apoyos_inscripcion.ajax.reload( function(e){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                        toastr.success('Éxito', response.message || 'Apoyo a inscripcion agregado correctamente');
                                    })
                                }, false )
                            },
                            error:function(error){
                                wait.modal('hide');
                                const errors = error.responseJSON || {};

                                setTimeout(() => {
                                    dom.modal_apoyo_inscripcion.modal('show');
                                    toastr.error('Error',  errors.message || 'Ocurrio un error inesperado');
                                }, 250);
                            }
                        });


                    })

                    //NOTAS
                    dom.btn_notas.click(function(e){

                        dom.form_notas[0].reset();
                        dom.modal_notas.modal('show');
                    })
                    

                    dom.form_notas.submit(function(e){
                        e.preventDefault();

                        dom.modal_notas.modal('hide');
                        wait.modal('show');

                        const $form = $(this);
                        const formData = new FormData(this);
                        formData.append('id_alumno',"{{ $alumno->id }}");

                        $.ajax({
                            url: $form.attr('action'),
                            type: 'POST',
                            cache: false,
                            contentType: false,
                            processData: false,
                            data: formData,
                            success: function (response){
                                // dt_notas.ajax.reload( function(e){
                                //     setTimeout(() => {
                                //         wait.modal('hide');
                                //         toastr.success('Éxito', response.message || 'Nota agregada correctamente');
                                //     })
                                // }, false )
                                location.reload();
                                    
                            },
                            error:function(error){
                                wait.modal('hide');
                                const errors = error.responseJSON || {};

                                setTimeout(() => {
                                    dom.modal_notas.modal('show');
                                    toastr.error('Error',  errors.message || 'Ocurrio un error inesperado');
                                }, 250);
                            }
                        });


                    })


                }
            });

            // DATATABLES APOYOS A INSCRIPCION

            $('.pausar_grupo').click(function(){
                id = $(this).data('id');
                $('#input_id_grupo_recontactar').val(id);
                $('#modalPausarGrupo').modal('show');

            });


            $('.reaundar_grupo').click(function(){
                id = $(this).data('id');
                swal({
                            title: "¿Estas seguro de reaundar al alumno en este grupo?",
                            type: "success",
                            showCancelButton: true,
                            confirmButtonColor: "#3bd52c",
                            cancelButtonColor: "#CDCDCD",
                            confirmButtonText: "Si, reaundar",
                            cancelButtonText: "Cancelar",
                            showLoaderOnConfirm: false,
                        }).then(function(result) {
                            if (!result.value) {
                                return;
                            }

                            wait.modal('show');

                            $.ajax({
                                url: "{{route('alumnos.reanudar_grupo')}}",
                                type: 'POST',
                                cache: false,
                                data: {
                                    _token: $("meta[name='csrf-token']").attr("content"),
                                    id_alumno: {{$alumno->id}},
                                    id_grupo: id,
                                },
                                success: function (response){
                                    location.reload();
                                },
                                error:function(error){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                        toastr.error('Error', 'Ocurrio un error inesperado');
                                    }, 250);
                                }
                            });
                        })
            });

            $('.baja_grupo').click(function(){
                id = $(this).data('id');
                swal({
                            title: "¿Estas seguro de dar de baja al alumno de este grupo?",
                            type: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#ff3333",
                            cancelButtonColor: "#CDCDCD",
                            confirmButtonText: "Si, dar de baja",
                            cancelButtonText: "Cancelar",
                            showLoaderOnConfirm: false,
                        }).then(function(result) {
                            if (!result.value) {
                                return;
                            }

                            wait.modal('show');

                            $.ajax({
                                url: "{{route('alumnos.baja_grupo')}}",
                                type: 'POST',
                                cache: false,
                                data: {
                                    _token: $("meta[name='csrf-token']").attr("content"),
                                    id_alumno: {{$alumno->id}},
                                    id_grupo: id,
                                },
                                success: function (response){
                                    location.reload();
                                },
                                error:function(error){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                        toastr.error('Error', 'Ocurrio un error inesperado');
                                    }, 250);
                                }
                            });
                        })
            });

            @role('administrador')
            $('.editable_forma_pago').editable({
                emptytext: 'Vacio',
                source: [
                    {value: 'semanal', text: 'semanal'},
                    {value: 'mensual', text: 'mensual'},
                ]
            });
            @endrole


            @can('dar_fin_de_curso')
            $('.dar_fin_de_curso').click(function(){
                id = $(this).data('id');
                swal({
                            title: "¿Estas seguro de dar fin de curso al alumno de este grupo?",
                            text: "Ya no se generarán mas colegiaturas para este alumno en este grupo.",
                            type: "success",
                            showCancelButton: true,
                            confirmButtonColor: "#3bd52c",
                            cancelButtonColor: "#CDCDCD",
                            confirmButtonText: "Si, fin de curso",
                            cancelButtonText: "Cancelar",
                            showLoaderOnConfirm: false,
                        }).then(function(result) {
                            if (!result.value) {
                                return;
                            }

                            wait.modal('show');

                            $.ajax({
                                url: "{{route('alumnos.fin_de_curso')}}",
                                type: 'POST',
                                cache: false,
                                data: {
                                    _token: $("meta[name='csrf-token']").attr("content"),
                                    id_alumno: {{$alumno->id}},
                                    id_grupo: id,
                                },
                                success: function (response){
                                    location.reload();
                                },
                                error:function(error){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                        toastr.error('Error', 'Ocurrio un error inesperado');
                                    }, 250);
                                }
                            });
                        })
            });
            @endcan

            $('.editable_fecha_inicio_grupo').editable({
                emptytext: 'Vacio',
            });

            // LOCAL STORAGE PARA LAS PESTAÑAS
            // Probar local storage
            var nav_tabs = $(".nav-tabs > li > a");
            nav_tabs.on("shown.bs.tab",handleSeleccionTabShowAlumno);
            activeTabShowAlumno = window.localStorage.getItem('activeTabShowAlumno');

            //INIT
            if (activeTabShowAlumno) {
                $(`.nav-tabs a[href="${activeTabShowAlumno}"]`).tab('show');
            }

            function handleSeleccionTabShowAlumno(event)
            {

                var id = $(event.target).attr("href");
                window.localStorage.setItem('activeTabShowAlumno',id);

            }

            $('#datepicker_recontacto').datepicker({ 
                                        language: 'es',
                                        format: 'yyyy-mm-dd',
                                        ignoreReadonly: false,
                                        todayHighlight: true,
                                        todayBtn: true,
                                        autoclose: true,
                                    });

            

        });
    </script>
@endsection
