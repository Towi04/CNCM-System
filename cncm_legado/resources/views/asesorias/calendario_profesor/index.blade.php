@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo','Mi calendario')

@section('css' )
    <link href="{{asset('plugins/fullcalendar/packages/core/main.min.css')}}" rel="stylesheet">
    <link href="{{asset('plugins/fullcalendar/packages/daygrid/main.min.css')}}" rel="stylesheet">
    <link href="{{asset('plugins/fullcalendar/packages/list/main.min.css')}}" rel="stylesheet">
@endsection


@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Mi Calendario</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <main class="ml-3 mr-3">
        {{-- <section class="row">
            <div class="col-auto p-1">
                @can('agendar_asesoria')
                    <button id="btn-agendar-asesoria" class="btn btn-primary btn-sm"> <i class="fa fa-clock"></i> Agendar asesoria</button>
                @endcan
            </div>
        </section> --}}
        {{-- <section class="row">
            <div class="col-auto p-1">
                {!! Form::select('id_profesor',[], null, ['class'=>'custom-select custom-select-sm','id'=>'select2_filtro_id_profesor','style' => 'width:250px']) !!}
            </div>

            <div class="col-auto p-1">
                <button type="button" id="btn-limpiar-filtros" class="btn btn-primary btn-sm">Limpiar</button>
            </div>
        </section> --}}
        <section class="row">
            <div class="col-12 p-1">
                <div style="height: 80vh">
                    <div id="calendario-asesorias"></div>
                </div>
            </div>
        </section>
    </main>

    <div class="modal inmodal fade animated" id="modal-agendar-asesoria" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content animated bounceInRight">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Agendar Actividad</h5>
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">
                            &times;</span><span class="sr-only">Close</span>
                        </button>
                    </div>

                    {!! Form::open(['id' => 'form-asesoria', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data']) !!}
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('id_profesor', 'Profesor:*'); !!}
                                        {!! Form::select('id_profesor',[], null, ['class' => 'form-control','required' => true,'style' => 'width:100%']) !!}
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('id_alumno', 'Alumno:* ') !!}
                                        {!! Form::select('id_alumno',[], null, ['class' => 'form-control','title' => 'Alumno' ,'required' => true, 'style' => 'width:100%;']) !!}
                                    </div>
                                </div>

                            </div>

                            <div class="row">
                                <div class="col-6 col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('fecha', 'Fecha:*') !!}
                                        {!! Form::date('fecha', null, ['class' => 'form-control form-control-sm','title' => 'Fecha','min' => now()->format('Y-m-d'),'required' => true,'disabled' => true]) !!}
                                    </div>
                                </div>

                                <div class="col-6 col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('horarios', 'Horarios:*') !!}
                                        {!! Form::select('horarios',[], null, ['class' => 'form-control form-control-sm','title' => 'Horarios']) !!}
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('hora_inicio', 'Inicio:*') !!}
                                        {!! Form::time('hora_inicio',null, ['class' => 'form-control','title' => 'Fecha','required' => true,'readonly' => true]) !!}
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <div class="form-group">
                                        {!! Form::label('hora_fin', 'fin:*') !!}
                                        {!! Form::time('hora_fin',null, ['class' => 'form-control','title' => 'Fecha','required' => true,'readonly' => true]) !!}
                                    </div>
                                </div>

                            </div>

                            <div class="form-group" id="comentarios-rechazo" >
                                {!! Form::label('notas', 'Motivos del rechazo:') !!}
                                {!! Form::textarea('notas', null, ['class' => 'form-control','rows' => 3,'readonly' => true]) !!}

                            </div>

                            <div class="form-group" id="modal-error">

                            </div>
                        </div>

                        <div class="modal-footer text-right">
                            <button data-action="rechazar" type="button" id="btn-rechazar" class="btn btn-danger btn-sm"> <i class="fa fa-stop-circle"></i> Rechazar</button>
                            <button data-action="aceptar" type="button" id="btn-aceptar" class="btn btn-success btn-sm"><i class="fa fa-check"></i> Aceptar</button>
                        </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script src="{{asset('js/plugins/fullcalendar/moment.min.js')}}"></script>
    <script src="{{asset('plugins/fullcalendar/packages/core/main.min.js')}}"></script>
    <script src="{{asset('plugins/fullcalendar/packages/daygrid/main.min.js')}}"></script>
    <script src="{{asset('plugins/fullcalendar/packages/list/main.min.js')}}"></script>
    <script src="{{asset('plugins/fullcalendar/packages/timegrid/main.min.js')}}"></script>
    <script src="{{asset('plugins/fullcalendar/packages/core/locales-all.min.js')}}"></script>
    <script type="text/javascript">
        $(function() {

            const dom = {
                calendar: document.getElementById('calendario-asesorias'),
                asesorias: {
                    crear_asesoria: $('#btn-agendar-asesoria'),
                    modal_asesoria: $('#modal-agendar-asesoria'),
                    form_asesoria: $('#form-asesoria'),
                    filtro:{
                        profesor: $('#select2_filtro_id_cliente'),
                        limpiar: $('#btn-limpiar-filtros'),
                    }
                }
            };

            var calendar = new FullCalendar.Calendar(dom.calendar, {
                plugins: [ 'dayGrid','list'],
                minTime: "07:00:00",
                defaultView: 'dayGridMonth',
                locale: 'es',
                themeSystem: 'bootstrap',
                allDaySlot: true,
                editable: true,
                eventLimit: false,
                eventTextColor: '#fff',
                height: 'parent',
                header: {
                    left: 'prev,next,today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek',
                },
                events: function(fechas, successCallback, failureCallback){
                    $.ajax({
                        url: " {{ route('asesorias.calendario-profesor.traer-asesorias') }}",
                        type: 'POST',
                        data: {
                            start: moment(fechas.start).format("YYYY-MM-DD"),
                            end: moment(fechas.end).format("YYYY-MM-DD"),
                            id_sucursal: "{{ optional(session('sucursal'))->id }}",
                        }
                    }).then(function(response){
                        successCallback(response.asesorias);
                    }).catch(function(error){
                        failureCallback(error)
                    });
                },
                eventRender: function(info) {
                    $(info.el).find('.fc-title').html(info.event.title);
                    $(info.el).find('.fc-list-item-title').html(info.event.title);
                },
                eventClick: function(calEvent, jsEvent, view) {
                    const asesoria = calEvent.event.extendedProps;

                     // LIMPIEZA DE FORMULARIO
                     dom.asesorias.form_asesoria[0].reset();
                    $(dom.asesorias.form_asesoria[0].id_profesor).empty();
                    $(dom.asesorias.form_asesoria[0].id_alumno).empty();

                    // OCULTAR BOTONNES
                    if(asesoria.status == "{{ config('asesorias.status.keys.espera_confirmacion') }}"){
                        dom.asesorias.modal_asesoria.find('button[data-action]').show();
                    }else{
                        dom.asesorias.modal_asesoria.find('button[data-action]').hide();
                    }
                    // MOSTRAR COMENTARIOS DE RECHAZO
                    if(asesoria.status == "{{ config('asesorias.status.keys.rechazados') }}"){
                        dom.asesorias.modal_asesoria.find('#comentarios-rechazo').show();
                        $(dom.asesorias.form_asesoria[0].notas).val(asesoria.notas);
                    }else{
                        dom.asesorias.modal_asesoria.find('#comentarios-rechazo').hide();
                        $(dom.asesorias.form_asesoria[0].notas).val(null);
                    }

                    // DESHABILITAR COMPONENTES DEL FORMULARIO
                    $(dom.asesorias.form_asesoria[0].id_profesor).attr('disabled',true);
                    $(dom.asesorias.form_asesoria[0].id_alumno).attr('disabled',true);
                    $(dom.asesorias.form_asesoria[0].fecha).attr('disabled',true);
                    $(dom.asesorias.form_asesoria[0].horarios).attr('disabled',true);

                    // ACCIONES DEL FORMULARIO
                    dom.asesorias.form_asesoria.attr('method','PUT');
                    dom.asesorias.modal_asesoria.data('asesoria',asesoria);

                    // INFORMACION DE ASESORIA
                    $(dom.asesorias.form_asesoria[0].id_profesor).append(
                        new Option(asesoria.profesor.fullname, asesoria.id_profesor, false, false)
                    ).trigger('change');

                    $(dom.asesorias.form_asesoria[0].id_alumno).append(
                        new Option(asesoria.alumno.fullname, asesoria.id_alumno, false, false)
                    ).trigger('change');

                    dom.asesorias.form_asesoria[0].fecha.value = asesoria.format_fecha_inicio;
                    dom.asesorias.form_asesoria[0].hora_inicio.value = `${asesoria.hora_inicio}:00`
                    dom.asesorias.form_asesoria[0].hora_fin.value = `${asesoria.hora_final}:00`;

                    // MOSTRAR EL HORARIO SELECCIONADO
                    const horarios = asesoria.profesor.horarios;

                    const horario = horarios.find(function(horario){
                        return horario.hora_inicio == asesoria.hora_inicio;
                    });


                    const $select_horarios = $(dom.asesorias.form_asesoria[0].horarios);
                    $select_horarios.empty();

                    const option = new Option(`${horario.hora_inicio} - ${horario.hora_final}`, horario.model_id, false, false);
                    option.dataset.inicio = horario.hora_inicio;
                    option.dataset.fin = horario.hora_final;
                    $select_horarios.append(option);

                    dom.asesorias.modal_asesoria.find('.modal-title').text('Revisar Asesoria');
                    dom.asesorias.modal_asesoria.modal('show');
                }
            });

            var m_asesorias = (function(asesorias){
                asesorias.modal_asesoria.on('click','button[data-action="aceptar"]',function(){
                    const asesoria = dom.asesorias.modal_asesoria.data('asesoria');
                    asesorias.modal_asesoria.modal('hide');

                    swal({
                        title: "¿Deseas aceptar la asesoria?",
                        type: "warning",
                        showCancelButton: true,
                        cancelButtonColor: "#CDCDCD",
                        cancelButtonText: "Cancelar",
                        confirmButtonColor: "#2ecc71",
                        confirmButtonText: "Aceptar",
                        showLoaderOnConfirm: false,
                    }).then(function(result) {
                        if (!result.value) {
                            asesorias.modal_asesoria.modal('show');
                            return;
                        }

                        wait.modal('show');

                        $.ajax({
                            url: "{{ route('asesorias.calendario-profesor.status-asesoria') }}",
                            type: 'POST',
                            data: {
                                status: "{{ config('asesorias.status.keys.agendados') }}",
                                id_asesoria: asesoria.model_id,
                            },
                            success: function (response){
                                setTimeout(() => {
                                    wait.modal('hide');
                                }, 200);

                                calendar.refetchEvents();
                            },
                            error:function(error){
                                wait.modal('hide');
                                toastr.error('Error', 'Ocurrio un error inesperado');
                            }
                        });

                    })
                });

                asesorias.modal_asesoria.on('click','button[data-action="rechazar"]',function(){
                    const asesoria = dom.asesorias.modal_asesoria.data('asesoria');

                    asesorias.modal_asesoria.modal('hide');

                    swal({
                        title: "¿Deseas rechazar la asesoria?",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#ff3333",
                        cancelButtonColor: "#CDCDCD",
                        confirmButtonText: "Rechazar",
                        cancelButtonText: "Cancelar",
                        showLoaderOnConfirm: false,
                    }).then(function(result) {
                        if (!result.value) {
                            asesorias.modal_asesoria.modal('show');
                            return;
                        }

                        Swal.fire({
                            title:'Rechazar asesoria',
                            input: 'textarea',
                            inputLabel: 'Message',
                            inputPlaceholder: 'Escribe el motivo del rechazo...',
                            inputAttributes: {
                                'aria-label': 'Escribe tu mensaje aqui',
                                'required': true,
                            },
                            confirmButtonColor: "#ff3333",
                            cancelButtonColor: "#CDCDCD",
                            confirmButtonText: "Rechazar",
                            cancelButtonText: "Cancelar",
                            showCancelButton: true
                        }).then(function(result){
                            if(result.dismiss){
                                asesorias.modal_asesoria.modal('show');
                                return;
                            }

                            wait.modal('show');

                            $.ajax({
                                url: "{{ route('asesorias.calendario-profesor.status-asesoria') }}",
                                type: 'POST',
                                data: {
                                    notas: result.value || '',
                                    status: "{{ config('asesorias.status.keys.rechazados') }}",
                                    id_asesoria: asesoria.model_id
                                },
                                success: function (response){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                    }, 200);

                                    calendar.refetchEvents();
                                },
                                error:function(error){
                                    setTimeout(() => {
                                        wait.modal('hide');
                                    }, 200);

                                    toastr.error('Error', 'Ocurrio un error inesperado');
                                }
                            });
                        });
                    })
                });

                asesorias.filtro.limpiar.click(function(e){
                    asesorias.filtro.profesor.val(null).trigger('change');
                    calendar.refetchEvents();
                });
            })(dom.asesorias);


            calendar.render();
        });
    </script>
@endsection
