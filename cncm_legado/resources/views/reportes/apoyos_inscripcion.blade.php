@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Reporte de Apoyos a la inscripción 
@endsection

@section('breadcrumb')
    <ol class="breadcrumb no_print">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            Ventas
        </li>
        <li class="breadcrumb-item">
            Reportes
        </li>
        <li class="breadcrumb-item active">
            <strong>Reporte de apoyos a la inscripción</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <style>
        .contact-box:hover {
            transform: scale(1.05)
        }

        .content-box {
            padding: 10px !important;
        }

        @media screen {
            .print_only {
                display: none
            }
        }

        @media print {

            table.dataTable td,
            table.dataTable th {
                font-size: 12pt;
            }
        }
    </style>

    <div class="row ">
        <div class="col-lg-12 no_print">
            <div class="btn-group ">
                {{-- <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'dia']) }}"
                    class="btn @if ($tipo=='dia' ) btn-primary  text-white @else btn-white @endif">Día</a> --}}
                {{-- <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'semanal']) }}"
                    class="btn  @if ($tipo=='semanal' ) btn-primary  text-white @else btn-white @endif">Semanal</a> --}}
                {{-- <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'mes']) }}"
                    class="btn  @if ($tipo=='mes' ) btn-primary  text-white @else btn-white @endif">Mensual</a>
                <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'anual']) }}"
                    class="btn  @if ($tipo=='anual' ) btn-primary  text-white @else btn-white @endif">Anual</a> --}}
            </div>

        </div>
    </div>
    <div class="row mt-2">
        <div class="col-lg-12 mb-3 no_print">
            <div class="col-sm-12 col-xxxl-12 p-1">
                <a class="element-box el-tablo p-3 float-right" href="#">
                  <div class="label mb-2">
                    Total
                  </div>
                  <div class="value" style="font-size: 0.85rem">
                    $ <span id="span_abonos_monto">{{ number_format($apoyos->sum('apoyo'), 2, '.', ',') }}</span>
                  </div>
                </a>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="element-box p-3">
                <div class="ibox-title mb-2">
                    <div class="row">

                        @if (request('tipo','semanal') == 'dia')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'dia','fecha'=>$fecha_antes->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_antes->format('d \d\e F  \d\e\l  Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-left fa-2x"></i></a>
                            </div>
                            <div class="col-10 col-xxl-10 col-xl-10 col-md-10 col-sm-10">
                                <center>
                                    <small>Semana {{ $fecha->week }}</small>
                                    <h3>{{ $fecha->format('d \d\e F \d\e\l Y') }} <small><a class="no_print"
                                                data-toggle="modal" data-target="#seleccionarFecha"><i
                                                    class="far fa-calendar-alt"></i></a></small></h3>
                                </center>
                            </div>
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo'=>'dia','fecha' => $fecha_despues->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format('d \d\e F \d\e\l Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif

                        @if (request('tipo','semanal') == 'mes')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo'=>'mes','fecha'=> $fecha_antes->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip"
                                    data-title="{{ $fecha_antes->format('d \d\e F  \d\e\l  Y') }}"
                                    data-placement="top"
                                    class="btn btn-primary btn-sm text-white no_print">
                                    <i class="fas fa-arrow-alt-circle-left fa-2x"></i>
                                </a>
                            </div>
                            <div class="col-10 col-xxl-10 col-xl-10 col-md-10 col-sm-10">
                                <center>
                                    <h3>{{ $fecha->format('F \d\e\l Y') }} <small><a class="no_print" data-toggle="modal"
                                                data-target="#seleccionarFecha"><i
                                                    class="far fa-calendar-alt"></i></a></small></h3>
                                </center>
                            </div>
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'mes','fecha' => $fecha_despues->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format('d \d\e F \d\e\l Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif

                        @if (request('tipo','semanal') == 'semanal')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'semanal','fecha' => $fecha_antes->startOfWeek()->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip"
                                    data-title="{{ $fecha_antes->format('d \d\e F  \d\e\l  Y') }}"
                                    data-placement="top"
                                    class="btn btn-primary btn-sm text-white no_print">
                                    <i class="fas fa-arrow-alt-circle-left fa-2x"></i>
                                </a>
                            </div>
                            <div class="col-10 col-xxl-10 col-xl-10 col-md-10 col-sm-10">
                                <center>
                                    <small>Semana {{ $fecha->week }}</small>
                                    <h3>{{ $fecha->startOfWeek()->format('d \d\e F \d\e\l Y') }} al
                                        {{ $fecha->endOfWeek()->format('d \d\e F \d\e\l Y') }} <small><a class="no_print"
                                                data-toggle="modal" data-target="#seleccionarFecha"><i
                                                    class="far fa-calendar-alt"></i></a></small>

                                    </h3>
                                </center>
                            </div>
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo'=> 'semanal','fecha'=>$fecha_despues->startOfWeek()->format('d-m-Y')]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format('d \d\e F \d\e\l Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif

                        @if (request('tipo','dia') == 'anual')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo' => 'anual','fecha'=> $fecha_antes->format('d-m-Y'), ]) }}"
                                    data-toggle="tooltip"
                                    data-title="{{ $fecha_antes->format('Y') }}"
                                    data-placement="top"
                                    class="btn btn-primary btn-sm text-white no_print">
                                    <i class="fas fa-arrow-alt-circle-left fa-2x"></i>
                                </a>
                            </div>
                            <div class="col-10 col-xxl-10 col-xl-10 col-md-10 col-sm-10">
                                <center>
                                    <h3>{{ $fecha->format('Y') }}
                                        <small><a class="no_print" data-toggle="modal"
                                                    data-target="#seleccionarFecha"><i
                                                    class="far fa-calendar-alt"></i></a>
                                        </small>
                                    </h3>
                                </center>
                            </div>
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.apoyos_inscripcion',['tipo'=>'anual','fecha'=> $fecha_despues->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format(' Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="ibox-content mt-2">

                    <div class="element-wrapper">
                        <div class="os-tabs-w">
                            
                            
                                    <div class="table-responsive">
                                        <table class="table table-striped table-sm table-hover tb-pagos" id="tabla_abonos">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Alumno</th>
                                                    <th>Grupo</th>
                                                    <th>Monto</th>
                                                    <th>Solicitó</th>
                                                    <th>Autorizó</th>
                                                    <th>Motivo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($apoyos as $apoyo)
                                                    <tr id="abono-{{ $apoyo->id }}">
                                                        <td class="text-primary text-center text-nowrap">
                                                           {{$apoyo->created_at->format('d-m-Y')}}
                                                        </td>

                                                        <td >
                                                            {{$apoyo->alumno->nuevo_numero_control}}

                                                            {{$apoyo->alumno->fullname}}
                                                        </td>

                                                        <td>
                                                             {{ optional($apoyo->grupo)->nombre }} 
                                                        </td>
                                                        <td class="text-nowrap">
                                                            $ {{ number_format($apoyo->apoyo,2,'.',',') }}
                                                        </td>
                                                        <td >
                                                            {{$apoyo->usuario_solicito->fullname}} 
                                                        </td>

                                                        <td>
                                                            {{$apoyo->usuario_autorizo->fullname}} 
                                                        </td>
                                                        <td>
                                                            {{$apoyo->motivo}} 
                                                        </td>

                                                    </tr>
                                                @endforeach
                                            </tbody>

                                        </table>

                                        <table class="table table-bordered mt-3 float-right print_only" style="width:50%;">
                                            <tr>
                                                <td style="font-size: 1rem;">Total: </td>
                                                <td class="text-right"><b style="font-size: 1rem;"> ${{ number_format($apoyos->sum('apoyo'), 2, '.', ',') }}</b>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" class="bg-primary"></td>
                                            </tr>
                                        </table>
                                    </div>
                                
                        </div>
                    </div>

                </div>
            </div>

        </div>

       
    </div>

    <div class="modal fade" id="seleccionarFecha" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Selecciona una fecha</h4>
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span
                            class="sr-only">Close</span></button>

                </div>

                <div class="modal-body">
                    <center>
                        <div id="datepicker"></div>
                    </center>
                </div>

            </div>
        </div>
    </div>

 
@endsection

@section('scripts')
    <link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
    <script src="{{ asset('plugins/xeditable/js/bootstrap-editable.min.js') }}"></script>
    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>

    <script type="text/javascript">
        $(function(){

            const Helpers = {
                number_format: function(number,decimals){
                    return parseFloat(number).toFixed(decimals).replace(/(\d)(?=(\d{3})+\.)/g, "$1,").toString()
                },
                formTokenDelete:function(){
                    const token = document.head.querySelector('meta[name="csrf-token"]');

                    const form = $('<form>', { 'method': 'POST' });

                    const inputToken = $('<input>', {
                        'type': 'hidden',
                        'name': '_token',
                        'value': token.content
                    });

                    const inputDelete = $('<input>', {
                        'type': 'hidden',
                        'name': '_method',
                        'value': 'DELETE'
                    });

                    form.append(inputToken);
                    form.append(inputDelete);

                    return form;
                },
            };

            const CONFIG_DATEPICKER = {
                days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
                daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
                daysMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
                months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
                monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
                today: "Hoy",
                monthsTitle: "Meses",
                clear: "Borrar",
                weekStart: 1,
            }

            const dom = {
                tikets:{
                    contenido_ticket:$("#contenido-ticket"),
                    modal: $("#modal-ticket"),
                },
            };

            $.fn.datepicker.dates['es'] = CONFIG_DATEPICKER     //👉 DATEPICKER
            $.fn.bdatepicker.dates['es'] = CONFIG_DATEPICKER    //👉 XEDITABLE DATEPICKER

            $('.tab-content').on('click','button[data-action="eliminar"]',function(e){
                const $button = $(this);


                swal({
                    title: "Deseas eliminar este registro",
                    text:'Esta acción no podrá deshacerse',
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#ff3333",
                    cancelButtonColor: "#CDCDCD",
                    confirmButtonText: "Si",
                    cancelButtonText: "Cancelar",
                    showLoaderOnConfirm: false,
                }).then(function(result) {
                    if (!result.value) {
                        return;
                    }

                    wait.modal('show');

                    const form = Helpers.formTokenDelete();

                    form.attr('action',$button.data('url'));

                    form.appendTo('body').submit();

                })
            });

            $('.tab-content').on('click','button[data-action="imprimir"]',function(e){
                const $button = $(this);
                const route = $button.data('url');

                dom.tikets.modal.modal('show');
                dom.tikets.contenido_ticket.html();
                dom.tikets.contenido_ticket.html(`<iframe scrolling='auto' type='text/html' scroll='auto' src='${route}' width='100%' height='450px' align='center'></iframe>`);
            });

            $('.tb-pagos').DataTable({
                responsive: true,
                lengthMenu: [ [-1, 25, 50 ], ["Todos", 25, 50] ],
                buttons: [
                    {extend: 'excel', title: 'Ventas'},
                ],
                language: {
                    "lengthMenu": "Mostrar _MENU_ registros por pagina",
                    "zeroRecords": "No se encontro ningún registro",
                    "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                    "search": "Buscar:",
                    "paginate": {
                        first:      "Primera",
                        last:       "Última",
                        previous: '<i class="fas fa-chevron-left"></i>',
                        next: '<i class="fas fa-chevron-right"></i>'
                    },
                    "loadingRecords": "Cargando...",
                    "processing":     "Procesando...",
                },
                "dom": "<'row'  <'toolbar col-sm-6 col-xs-3 text-left no_print' B> <'col-sm-6 col-xs-9 no_print'f>>" +
                "<'row'<'col-sm-12 table-responsive'tr>>" +
                "<'row'<'col-sm-12 col-lg-12 col-xs-12 no_print'p>>",
                buttons: [
                    @can('descargar_excel_bd')
                    {
                        "extend": 'excelHtml5',
                        "text":'Excel <i class="fas fa-file-excel"></i>',
                        'title': 'Reporte de ventas',
                        "className": 'btn btn-primary',
                    }
                    @endcan
                ],
                order: [[0,'desc']]
            });

            $('#datepicker').datepicker({
                language: 'es',
                format: 'dd-mm-yyyy',
                ignoreReadonly: false,
                todayHighlight: true,
                todayBtn: true
            });

            $('#datepicker').on('changeDate', function() {
                window.location = "{{route('reportes.apoyos_inscripcion')}}?tipo={{$tipo}}&fecha="+$('#datepicker').datepicker('getFormattedDate')+"&forma_pago={{@$_GET['forma_pago']}}"
            });

            $('#convertir_fiscales').click(function(){
                    swal({
                        title: "¿Estas seguro de convertir las ventas no fiscales a fiscales en este periodo?",
                        text:'Esta acción no podrá deshacerse',
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#1bc51c",
                        cancelButtonColor: "#CDCDCD",
                        confirmButtonText: "Si",
                        cancelButtonText: "Cancelar",
                        showLoaderOnConfirm: false,
                    }).then(function(result) {
                        if (!result.value) {
                            return;
                        }
                        // wait.modal('show');

                        $.ajax({
                            url: "{{route('reportes.reporte-ventas.convertir_ventas_fiscales')}}",
                            type: 'POST',
                            data: {
                                tipo: "{{$tipo}}"
                            },
                            success: function (response){
                                //  wait.modal('hide');

                                location.reload();
                            },
                            fail:function(error){
                                toastr.error('Error', 'Ocurrio un error inesperado');
                            }
                        });
                    })
            });

           
        })
    </script>
@endsection
