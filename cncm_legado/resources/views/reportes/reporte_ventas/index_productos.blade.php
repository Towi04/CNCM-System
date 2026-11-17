@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Reporte de ventas de productos {{ date('Y') }}
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
            <strong>Reporte de ventas (Productos)</strong>
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
                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'dia']) }}"
                    class="btn @if ($tipo=='dia' ) btn-primary  text-white @else btn-white @endif">Día</a>
                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'semanal']) }}"
                    class="btn  @if ($tipo=='semanal' ) btn-primary  text-white @else btn-white @endif">Semanal</a>
                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'mes']) }}"
                    class="btn  @if ($tipo=='mes' ) btn-primary  text-white @else btn-white @endif">Mensual</a>
                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'anual']) }}"
                    class="btn  @if ($tipo=='anual' ) btn-primary  text-white @else btn-white @endif">Anual</a>
            </div>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-lg-9">
            <div class="element-box p-3">
                <div class="ibox-title mb-2">
                    <div class="row">

                        @if (request('tipo','dia') == 'dia')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'dia','fecha'=>$fecha_antes->format('d-m-Y') ]) }}"
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
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo'=>'dia','fecha' => $fecha_despues->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format('d \d\e F \d\e\l Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif

                        @if (request('tipo','dia') == 'mes')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo'=>'mes','fecha'=> $fecha_antes->format('d-m-Y') ]) }}"
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
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'mes','fecha' => $fecha_despues->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format('d \d\e F \d\e\l Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif

                        @if (request('tipo','dia') == 'semanal')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'semanal','fecha' => $fecha_antes->startOfWeek()->format('d-m-Y') ]) }}"
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
                                                    class="far fa-calendar-alt"></i></a></small></h3>
                                </center>
                            </div>
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo'=> 'semanal','fecha'=>$fecha_despues->startOfWeek()->format('d-m-Y')]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format('d \d\e F \d\e\l Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif

                        @if (request('tipo','dia') == 'anual')
                            <div class="col-1 col-xxl-1 col-xl-1 col-md-1 col-sm-1">
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo' => 'anual','fecha'=> $fecha_antes->format('d-m-Y'), ]) }}"
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
                                <a href="{{ route('reportes.reporte-ventas.index_productos',['tipo'=>'anual','fecha'=> $fecha_despues->format('d-m-Y') ]) }}"
                                    data-toggle="tooltip" data-title="{{ $fecha_despues->format(' Y') }}"
                                    data-placement="top" class="btn btn-primary btn-sm text-white no_print"><i
                                        class="fas fa-arrow-alt-circle-right fa-2x"></i></a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="ibox-content mt-2">

                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabla_ventas">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha Venta</th>
                                    <th>Alumno</th>
                                    <th>Nombre</th>
                                    <th>Concepto</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($partidas as $partida)
                                    <tr class="gradeX" id="venta-{{ $partida->id }}">

                                            <td class="text-danger text-center">
                                                <div>
                                                    <a  @if($puede_editar)
                                                            class='editable_ventas_folio editable'
                                                            data-type='number'
                                                            data-name='folio'
                                                            data-min="1"
                                                            data-pk='{{ $partida->id_venta }}'
                                                            data-url='{{ route('reportes.reporte-ventas-producto.actualizar_ventas_xeditable') }}'
                                                            data-value='{{$partida->venta->folio }}'
                                                        @endif
                                                    >
                                                        {{ $partida->venta->folio }}
                                                    </a>
                                                </div>

                                                <div class="mt-2 btn-group">

                                                    @if($puede_eliminar)
                                                        <button class="btn btn-sm btn-danger"
                                                            type="button"
                                                            data-url="{{ route('reportes.reporte-ventas-producto.eliminar_partida',$partida) }}"
                                                            data-action="eliminar"><i class="fas fa-trash"></i>
                                                        </button>
                                                    @endif

                                                    @if($puede_reimprimir)
                                                        <button type="button"
                                                            data-action="imprimir"
                                                            data-url="{{ route('punto_de_venta_productos.ticket',$partida->id_venta) }}"
                                                            class="btn btn-sm btn-primary"
                                                            title="Imprimir"  >
                                                            <i class="fas fa-print"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>


                                        <td nowrap>
                                            <a @if($puede_editar)
                                                    class="editable_ventas_fecha editable"
                                                    data-name="fecha"
                                                    data-type="date"
                                                    data-value="{{ $partida->venta->fecha->format('Y-m-d') }}"
                                                    data-pk="{{ $partida->id_venta }}"
                                                    data-url="{{ route('reportes.reporte-ventas-producto.actualizar_ventas_xeditable') }}"
                                                @endif>

                                                {{ optional($partida->venta->fecha)->format('d-m-Y H:i') }}
                                            </a>

                                        </td>
                                        <td>
                                            <a @if($puede_editar)
                                                    class='editable_ventas_id_alumno editable'
                                                    data-type='select2'
                                                    data-pk='{{ $partida->id_venta }}'
                                                    data-url='{{ route('reportes.reporte-ventas-producto.actualizar_ventas_xeditable') }}'
                                                    data-value='{{ $partida->venta->id_alumno }}'
                                                    data-name='id_alumno'
                                                @endif
                                                >
                                                {{ optional($partida->venta->alumno)->nuevo_numero_control }}
                                            </a>
                                        </td>
                                        <td>
                                            <a>
                                                @if($partida->venta->id_alumno)
                                                {{ optional($partida->venta->alumno)->fullname }}
                                                @else 
                                                {{ $partida->venta->nombre }}
                                                @endif
                                                
                                            </a>
                                        </td>
                                        <td>
                                            <a @if($puede_editar)
                                                    class='editable_partidas_ventas_id_producto editable'
                                                    data-type='select2'
                                                    data-pk='{{ $partida->id }}'
                                                    data-url='{{ route('reportes.reporte-ventas-producto.actualizar_partidas_ventas_xeditable') }}'
                                                    data-value='{{ $partida->id_producto }}'
                                                    data-name='id_producto'
                                                @endif
                                                >
                                                {{ optional($partida->producto)->nombre }}
                                            </a>

                                        </td>

                                        <td class="text-right text-nowrap" style="cursor:pointer">
                                            <a @if($puede_editar)
                                                    class='editable_partidas_ventas_cantidad editable'
                                                    data-type='number'
                                                    data-min="1"
                                                    data-pk='{{ $partida->id }}'
                                                    data-url='{{ route('reportes.reporte-ventas-producto.actualizar_partidas_ventas_xeditable') }}'
                                                    data-value='{{ $partida->cantidad }}'
                                                    data-name='cantidad'
                                                @endif
                                                >
                                                {{ $partida->cantidad }}
                                            </a>
                                        </td>

                                        <td class="text-right text-nowrap" data-precio style="cursor:pointer">
                                            $ {{ number_format($partida->precio, '2', '.', ',') }}
                                        </td>

                                        <td class="text-right text-nowrap" data-total style="cursor:pointer">
                                            $ {{ number_format($partida->total, '2', '.', ',') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>

                        </table>

                        <table class="table table-bordered mt-3 float-right print_only" style="width:50%;">
                            <tr>
                                <td style="font-size: 1rem;">Total: </td>
                                <td class="text-right">
                                    <b style="font-size: 1rem;"> $ <span id="span-print-total">{{ number_format($partidas->sum('total'), 2, '.', ',') }}</span></b>
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

        <div class="col-lg-3 mb-3 no_print">
            <div class="col-sm-12 col-xxxl-12 p-1">
                <a class="element-box el-tablo" href="#">
                  <div class="label mb-2">
                    Total
                  </div>
                  <div class="value">
                    $ <span id="span-total">{{ number_format($total, 2, '.', ',') }}</span>
                  </div>
                </a>
            </div>

            @can('convertir_no_fiscales_a_fiscales')
                @if($tipo == 'semanal' || $tipo == 'mes' )
                    <div class="col-sm-12 col-xxxl-12 p-1">
                        <a class="element-box el-tablo" href="#">
                        <div class="label mb-2">
                            No fiscales
                        </div>
                        <div class="value">
                            $ <span id="span-total-no-fiscal">{{ number_format($total_no_fiscal, 2, '.', ',') }}</span>
                        </div>
                        </a>
                    </div>
                    <div class="col-sm-12 col-xxxl-12 p-1">
                        <a class="element-box el-tablo" href="#">
                        <div class="label mb-2">
                            Fiscales
                        </div>
                        <div class="value">
                            $  <span id="span-total-fiscal">{{ number_format($total_fiscal, 2, '.', ',') }}</span>
                        </div>
                        </a>
                    </div>
                    <div class="col-sm-12 col-xxxl-12 p-1">
                        <a class="element-box el-tablo" href="#">
                        <div class="label mb-2">
                            % de fiscales
                        </div>
                        <div class="value">
                           <span id="span-porcentaje-fiscal">{{ $porcentaje_fiscal }}</span>
                        </div>
                        </a>
                    </div>
                    <button id="convertir_fiscales" class="btn btn-primary btn-block ">Convertir ventas no fiscales a fiscales</button>
                @endif

            @endcan
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

    <div class="modal inmodal fade animated" id="modal-ticket" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content animated bounceInRight">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Ticket</h4>
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">
                            &times;</span><span class="sr-only">Close</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div id="contenido-ticket"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-dismiss="modal">Cerrar</button>
                    </div>
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

        const dom = {
            tikets:{
                contenido_ticket:$("#contenido-ticket"),
                modal: $("#modal-ticket"),
            },
        };

        $.fn.datepicker.dates['es'] = CONFIG_DATEPICKER     //👉 DATEPICKER
        $.fn.bdatepicker.dates['es'] = CONFIG_DATEPICKER    //👉 XEDITABLE DATEPICKER


        $('#tabla_ventas').DataTable({
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
            order: [[0,'desc']],
        });

        $('#tabla_ventas').on('click','button[data-action="eliminar"]',function(e){
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

        $('#tabla_ventas').on('click','button[data-action="imprimir"]',function(e){
            const $button = $(this);
            const route = $button.data('url');

            dom.tikets.modal.modal('show');
            dom.tikets.contenido_ticket.html();
            dom.tikets.contenido_ticket.html(`<iframe scrolling='auto' type='text/html' scroll='auto' src='${route}' width='100%' height='450px' align='center'></iframe>`);
        });

        $('#datepicker').datepicker({
            language: 'es',
            format: 'dd-mm-yyyy',
            ignoreReadonly: false,
            todayHighlight: true,
            todayBtn: true
        });

        $('#datepicker').on('changeDate', function() {
            window.location = "{{route('reportes.reporte-ventas.index_productos')}}?tipo={{$tipo}}&fecha="+$('#datepicker').datepicker('getFormattedDate')+"&forma_pago={{@$_GET['forma_pago']}}"
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

        @if($puede_editar)
            const actualizarTotales = function(){
                return $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    cache: false,
                    data: {
                        tipo:"{{ request('tipo','dia') }}",
                        fecha:"{{ request('fecha') }}"
                    },
                    success: function (response){
                        $("#span-total").html(Helpers.number_format(response.total,2));
                        $("#span-print-total").html(Helpers.number_format(response.total,2));
                        $("#span-total-fiscal").html(Helpers.number_format(response.total_fiscal,2))
                        $("#span-total-no-fiscal").html(Helpers.number_format(response.total_no_fiscal,2))
                        $("#span-porcentaje-fiscal").html(response.porcentaje_fiscal)
                    },
                    fail:function(error){
                        toastr.error('Error', 'Ocurrio un error inesperado');
                    }
                });
            }

            // 👉 VENTAS
            $('.editable_ventas_folio').editable({
                emptytext: 'Vacio',
                onblur: 'ignore',
            });

            $('.editable_ventas_fecha').editable({
                format: 'yyyy-mm-dd',
                viewformat: 'dd-mm-yyyy',
                emptytext: 'Vacio',
                datepicker: {
                    weekStart: 1,
                    orientation: 'bottom left',
                    language: 'es',
                },
                display: function(value, sourceData,response) {
                    if(sourceData && sourceData.hasOwnProperty('venta')){
                        $(this).text(sourceData.venta.format_fecha);

                    }
                },
            });

            $('.editable_ventas_id_alumno').editable({
                select2: {
                    placeholder: 'Selecciona un alumno',
                    allowClear: true,
                    minimumInputLength: 3,
                    ajax: {
                        method: 'POST',
                        url: '{{ route("alumnos.traer_alumnos_select2") }}',
                        dataType: 'json',
                        cache: false,
                        delay:250,
                        data:function (params) {
                            return {
                                _token: '{{ csrf_token() }}',
                                term: params.term,
                                page: params.page || 1,
                                id_sucursal: "{{ optional(session('sucursal'))->id }}",
                                status:'Alumno'
                            }
                        },
                        beforeSend:function(xhr,type){
                            xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                        },
                        processResults: function (data, page) {
                            return data;
                        }
                    },
                    templateResult: function(option){
                        if (option.loading) {
                            return option.text;
                        }

                        if(!option.nuevo_numero_control || !option.nombres || !option.apellido_paterno || !option.apellido_materno){
                            return option.text
                        }

                        return `No. Control: ${option.nuevo_numero_control} | Nombre: ${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                    },
                    templateSelection: function(option){
                        if(!option.nuevo_numero_control ||  !option.nombres || !option.apellido_paterno || !option.apellido_materno){
                            return option.text
                        }

                        return `No. Control: ${option.nuevo_numero_control} | Nombre: ${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                    },
                },
                display: function(value, sourceData,response) {
                    if(sourceData){
                        $(this).text(sourceData.alumno);
                    }
                },
                mode:'inline',
                emptytext: 'Vacio',
                tpl: '<select style="width:100%;z-index: 289;">',
            })

            // 👉 PARTIDAS VENTAS
            $('.editable_partidas_ventas_id_producto').editable({
                select2: {
                    placeholder: 'Selecciona un producto',
                    allowClear: true,
                    minimumInputLength: 3,
                    ajax: {
                        method: 'POST',
                        url: '{{ route("admin.productos.traer_productos_select2") }}',
                        dataType: 'json',
                        cache: false,
                        delay:250,
                        data:function (params) {
                            return {
                                _token: '{{ csrf_token() }}',
                                term: params.term,
                                page: params.page || 1,
                                id_sucursal: "{{ optional(session('sucursal'))->id }}",
                            }
                        },
                        beforeSend:function(xhr,type){
                            xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                        },
                        processResults: function (data, page) {
                            return data;
                        }
                    },
                    templateResult: function(option){
                        if (option.loading) {
                            return option.text;
                        }

                        if(!option.nombre || !option.descripcion ){
                            return option.text
                        }

                        return `${option.nombre} ${option.descripcion} `;
                    },
                    templateSelection: function(option){
                        if(!option.nombre || !option.descripcion){
                            return option.text
                        }

                        return `${option.nombre} ${option.descripcion} `;
                    },
                },
                display: function(value, sourceData,response) {
                    if (sourceData) {
                        const partida = sourceData.partida;
                        const producto = partida.producto;

                        $(this).text(producto.nombre);
                        $(this).closest('tr').find('[data-precio]').html('$ ' + Helpers.number_format(partida.precio,2) );
                        $(this).closest('tr').find('[data-total]').html('$ '+ Helpers.number_format(partida.total,2) );

                        actualizarTotales();
                    }
                },
                mode:'inline',
                emptytext: 'Vacio',
                tpl: '<select style="width:100%;z-index: 289;">',
            })

            $('.editable_partidas_ventas_cantidad').editable({
                emptytext: 'Vacio',
                onblur: 'ignore',
                display: function(value, sourceData,response) {
                    if (sourceData) {
                        const partida = sourceData.partida;
                        const producto = partida.producto;

                        $(this).text(partida.cantidad);
                        $(this).closest('tr').find('[data-precio]').html('$ ' + Helpers.number_format(partida.precio,2) );
                        $(this).closest('tr').find('[data-total]').html('$ '+ Helpers.number_format(partida.total,2) );

                        actualizarTotales();
                    }
                },
            });

        @endif
    </script>
@endsection
