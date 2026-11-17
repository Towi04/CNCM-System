@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Panel del grupo <small></small>
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
            <strong>Panel del grupo</strong>
        </li>
    </ol>
@endsection

@section('contenido')
<style>
    .contact-box:hover{
        transform: scale(1.05)
    }
    .table-responsive {
        overflow-x: auto !important;
    }

    .content-w {
        overflow: hidden !important;
    }
    td:hover{
        background-color: #abd0ea;
        cursor:pointer;
    }

    .table-responsive>.fixed-column {
        position: absolute;
        display: inline-block;
        width: auto;
        border-right: 1px solid #ddd;
    }
    @media(min-width:768px) {
        .table-responsive>.fixed-column {
            display: none;
        }
    }
    table.dataTable {
        clear: both;
        margin-top: 0px !important;
        margin-bottom: 0px !important;
    }
</style>

<div class="row p-2">
    <div class="col-5 col-lg-5 col-sm-5 col-md-5 col-xs-12">
        <div class="user-profile compact">
            <div class="up-head-w"
                style="background-image: linear-gradient( rgb(24,41,72,0.9), 70%, rgb(24,41,72,0.9));">

                <div class="up-main-info " style="padding-bottom: 150px; padding-top:100px">
                    <h2 class="up-header">
                        Grupo
                    </h2>
                    <h6 class="up-sub-header">
                        Folio: {{ $grupo->clave }}
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
                    <div class="">
                        @can(['editar_grupo'])
                            <a href="{{ route('grupos.edit', $grupo) }}"
                                class="btn btn-info btn-sm btn-circle float-right text-white mb-2" data-toggle="tooltip"
                                data-placement="left" title="Editar informacion">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        @endcan

                        <table class="table table-bordered">
                            <tr>
                                <td class="bg-primary text-white"><b>Fecha Inicio</b></td>
                                <td>
                                    {{ $grupo->fecha_inicio->format('d/m/Y') }}
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-primary text-white"><b>Especialidad</b></td>
                                <td>
                                    {{ $grupo->especialidad->nombre }}
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-primary text-white"><b>Horario</b></td>
                                <td>
                                    {{ $grupo->horario }}
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-primary text-white"><b>Dias</b></td>
                                <td>
                                    {!! $grupo->days->pluck('display_name')->implode('<br>') !!}
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-primary text-white"><b>Tipo de grupo</b></td>
                                <td>
                                    {{ ($grupo->infantil)?'Infantil':'Adulto' }}
                                </td>
                            </tr>
                            @can(['editar_grupo'])
                            <tr>
                                <td class="bg-primary text-white"><b>Inscripcion</b></td>
                                <td>
                                    {{ number_format($grupo->precio_inscripcion,2,'.',',') }}
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-primary text-white"><b>Precio Semanal</b></td>
                                <td>
                                    {{ number_format($grupo->precio_semanal,2,'.',',') }}
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-primary text-white"><b>Precio Mensual</b></td>
                                <td>
                                    {{ number_format($grupo->precio_mensualidad,2,'.',',') }}
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-primary text-white"><b>Precio Mensual Pronto Pago</b></td>
                                <td>
                                    {{ number_format($grupo->precio_mensualidad_pronto_pago,2,'.',',') }}
                                </td>
                            </tr>
                            @endcan
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-7 col-lg-7 col-sm-7 col-md-7 col-xs-12">
        <div class="row">
            <div class="col-sm-12 col-xxxl-9">
              <div class="element-wrapper">
                {{-- <h6 class="element-header">
                  Informacion del grupo
                </h6> --}}
                <div class="element-box">
                  <div class="os-tabs-w">
                    <div class="os-tabs-controls">
                      <ul class="nav nav-tabs smaller">
                        <li class="nav-item">
                          <a class="nav-link active" data-toggle="tab" href="#tab-materias">Materias</a>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link " data-toggle="tab" href="#tab-alumnos">Alumnos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link " data-toggle="tab" href="#tab-cronograma">Cronograma</a>
                          </li>
                      </ul>
                    </div>
                    <div class="tab-content">
                      <div class="tab-pane active" id="tab-materias">
                        <table class="table table-striped table-bordered table-hover" id="tb-materias" width="100%">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Materia</th>
                                    <th>Profesor</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th>Horas</th>
                                    <th>Lista</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                      </div>
                      <div class="tab-pane " id="tab-alumnos">
                        <table class="table table-striped table-bordered table-hover" id="tb-alumnos" width="100%" >
                            <thead>
                                <tr>
                                    <th>No Control</th>
                                    <th>Alumno</th>
                                    <th>Fecha inicio</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                      </div>
                      <div class="tab-pane " id="tab-cronograma">
                          <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover"  id="table_cronograma" >
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        @php
                                            $fecha_inicio = $grupo->fecha_inicio->startOfWeek();
                                        @endphp
                                        @foreach($grupo->materias as $materia)
                                            @for($i =1; $i <=$materia->semanas; $i++ )
                                                <th> <a data-toggle="tooltip" title="{{$fecha_inicio->format('d-m-Y')}} - {{$fecha_inicio->endOfWeek()->format('d-m-Y')}}" >{{$fecha_inicio->weekOfYear}}</a></th>
                                                @php
                                                    $fecha_inicio->addDay();
                                                @endphp
                                            @endfor
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                        @foreach($grupo->materias as $materia)
                                        <tr>
                                            <td>
                                                {{$materia->nombre}}
                                            </td>
                                            {{-- Materias antes --}}

                                            @for($i =1; $i <=$grupo->materias->where('orden','<',$materia->orden)->sum('semanas'); $i++ )
                                                <td> - </td>
                                            @endfor
                                             {{-- Semanas de la materia --}}
                                            @for($i =1; $i <=$materia->semanas; $i++ )
                                                <td class="bg-primary text-primary" > OK </td>
                                            @endfor
                                             {{-- Materias despues --}}
                                             @for($i =1; $i <=$grupo->materias->where('orden','>',$materia->orden)->sum('semanas'); $i++ )
                                             <td> - </td>
                                            @endfor
                                        </tr>
                                        @endforeach
                                </tbody>
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

</div>

<div class="onboarding-modal modal fade" id="modal-opciones-lista" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span><span class="sr-only">Close</span>
                </button>

                <h5 class="modal-title">¿Como quieres imprimir la lista?</h5>
            </div>

            {!! Form::open(['id' => 'form-opciones-lista']) !!}
                <div class="modal-body">
                    <div class="form-group">
                        {!! Form::label('opciones', 'Selecciona la opcion', []) !!}
                        {!! Form::select('opciones', ['si'=> 'Con Telefono','no' => 'Sin Telefono'], null, ['class' => 'form-control form-control-sm w-100' , 'title' => 'Opciones','required' => true ]) !!}
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Imprimir</button>
                </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

@endsection


@section('scripts')
{{-- <script src="https://cdn.datatables.net/fixedcolumns/3.3.0/js/dataTables.fixedColumns.min.js" crossorigin="anonymous"></script> --}}
    <script type="text/javascript">
        $(document).ready(function() {

            const dom = {
                tb_alumnos: $("#tb-alumnos"),
                tb_materias: $("#tb-materias"),
                opciones_lista:{
                    modal: $("#modal-opciones-lista"),
                    form: $("#form-opciones-lista"),
                }
            }

            var dt_alumnos = dom.tb_alumnos.DataTable({
                dom: "<'row'<'col-6'f><'col-6'>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                processing: true,
                serverSide: true,
                responsive: true,
                pageLength: 10,
                ajax: {
                    url: "{{ route('grupos.datatables_alumnos') }}",
                    type: "POST",
                    data: function (d) {
                        d.id_grupo = "{{ $grupo->id }}";
                    },
                    beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                        }
                    },
                },
                columns: [
                    {data: 'alumno.nuevo_numero_control', name: 'alumno.nuevo_numero_control'},
                    {data: 'nombre_alumno', name: 'nombre_alumno', className:'text-nowrap'},
                    {data: 'fecha_inicio', name: 'fecha_inicio',className:'text-nowrap'},
                    {data: 'alumno.nombres', name: 'alumno.nombres',visible:false},
                    {data: 'alumno.apellido_paterno', name: 'alumno.apellido_paterno',visible:false},
                    {data: 'alumno.apellido_materno', name: 'alumno.apellido_materno',visible:false},
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
                },
            });

            var dt_materias = dom.tb_materias.DataTable({
                dom: "<'row'<'col-6' f><'col-6'>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
                processing: true,
                serverSide: true,
                responsive: true,
                pageLength: 10,
                ajax: {
                    url: "{{ route('grupos.datatables_materias') }}",
                    type: "POST",
                    data: function (d) {
                        d.id_grupo = "{{ $grupo->id }}";
                    },
                    beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                        }
                    },
                },
                columns: [
                    {data: 'orden', name: 'orden'},
                    {data: 'nombre_materia', name: 'materia.nombre'},
                    {data: 'nombre_profesor',name:'nombre_profesor'},
                    {data: 'profesor.nombres', name: 'profesor.nombres',visible:false},
                    {data: 'profesor.apellido_paterno', name: 'profesor.apellido_paterno',visible:false},
                    {data: 'profesor.apellido_materno', name: 'profesor.apellido_materno',visible:false},
                    {data: 'horas_semana',name:'horas_semana'},
                    {data: 'buttons_lista',name:'buttons_lista'},

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

                    dom.tb_materias.on('click',"a[data-action='opciones-lista']",function(event){
                        event.preventDefault();
                        dom.opciones_lista.modal.data('url',$(this).attr('href'));
                        dom.opciones_lista.modal.modal('show');
                    })

                    dom.opciones_lista.form.submit(function(event){
                        event.preventDefault();

                        const data = {
                            url: dom.opciones_lista.modal.data('url'),
                            mostrar_telefono: dom.opciones_lista.form[0].opciones.value,
                            generar_url: function(){
                                return this.url+"?mostrar-telefono=" + this.mostrar_telefono
                            }
                        }

                        dom.opciones_lista.modal.modal('hide');
                        dom.opciones_lista.form[0].reset();

                        window.open(data.generar_url(), "_blank");
                    })
                    
                },
            });




        });
    </script>
@endsection
