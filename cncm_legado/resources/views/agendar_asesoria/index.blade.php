@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo','Agendar asesoria')

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
            <strong>Agendar Asesoria</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <main class="ml-3 mr-3">
        <section class="row">
            <div class="col-auto p-1">
                @can('agendar_asesoria')
                    <button id="btn-agendar-asesoria" class="btn btn-primary btn-sm"> <i class="fa fa-clock"></i> Agendar asesoria</button>
                @endcan
            </div>
        </section>
        <section class="row">
            <div class="col-auto p-1">
                {!! Form::select('id_profesor',[], null, ['class'=>'custom-select custom-select-sm','id'=>'select2_filtro_id_profesor','style' => 'width:250px']) !!}
            </div>

            <div class="col-auto p-1">
                <button type="button" id="btn-limpiar-filtros" class="btn btn-primary btn-sm">Limpiar</button>
            </div>
        </section>
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
                        <h5 class="modal-title">Agendar Asesoria</h5>
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">
                            &times;</span><span class="sr-only">Close</span>
                        </button>
                    </div>

                    {!! Form::open(['id' => 'form-asesoria', 'method' => 'POST','route' => 'agendar-asesoria.guardar-asesoria', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data']) !!}
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
                                        {!! Form::date('fecha', null, ['class' => 'form-control form-control-sm','title' => 'Fecha','min' => now()->format('Y-m-d'),'required' => true,'readonly' => true]) !!}
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

                            <div class="form-group" id="modal-error">
                            </div>
                        </div>

                        <div class="modal-footer text-right">
                            @can('cancelar_asesorias')
                                <button data-action="cancelar" type="button" class="btn btn-danger btn-sm"> <i class="fa fa-stop-circle"></i> Cancelar</button>
                            @endcan
                            <button data-action="guardar" class="btn btn-success btn-sm" type="submit"><i class="fa fa-plus"></i> Guardar</button>
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
    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>

    <script type="text/javascript">
        $(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            const dom = {
                calendar: document.getElementById('calendario-asesorias'),
                asesorias: {
                    crear_asesoria: $('#btn-agendar-asesoria'),
                    modal_asesoria: $('#modal-agendar-asesoria'),
                    form_asesoria: $('#form-asesoria'),
                    filtro:{
                        profesor: $('#select2_filtro_id_profesor'),
                        limpiar: $('#btn-limpiar-filtros'),
                    }
                }
            };

            const dias_semana = {
                0:'Domingo',
                1:'Lunes',
                2:'Martes',
                3:'Miercoles',
                4:'Jueves',
                5:'Viernes',
                6:'Sábado'
            }

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
                        url: " {{ route('agendar-asesoria.traer-asesorias') }} ",
                        type: 'POST',
                        data: {
                            start: moment(fechas.start).format("YYYY-MM-DD"),
                            end: moment(fechas.end).format("YYYY-MM-DD"),
                            id_profesor: dom.asesorias.filtro.profesor.val(),
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
                    const puede_editar_asesoria = "{{ auth()->user()->can('editar_asesoria') }}" == '1';

                    // OCULTAR BOTONNES
                    if(asesoria.status == "{{ config('asesorias.status.keys.espera_confirmacion') }}"){
                        dom.asesorias.modal_asesoria.find('button[data-action="cancelar"]').show();
                    }else{
                        dom.asesorias.modal_asesoria.find('button[data-action="cancelar"]').hide();
                    }

                    // LIMPIEZA DE FORMULARIO
                    dom.asesorias.form_asesoria[0].reset();
                    $(dom.asesorias.form_asesoria[0].id_profesor).empty();
                    $(dom.asesorias.form_asesoria[0].id_alumno).empty();

                    // DESHABILITAR COMPONENTES DEL FORMULARIO
                    if(!puede_editar_asesoria){
                        $(dom.asesorias.form_asesoria[0].id_profesor).attr('readonly',true);
                        $(dom.asesorias.form_asesoria[0].id_alumno).attr('readonly',true);
                        $(dom.asesorias.form_asesoria).find('button[data-action="guardar"]').hide();
                    }

                    $(dom.asesorias.form_asesoria[0].fecha).attr('readonly',true);
                    $(dom.asesorias.form_asesoria[0].horarios).attr('readonly',true);

                    // ACCIONES DEL FORMULARIO
                    const url = "{{ route('agendar-asesoria.actualizar-asesoria','_id') }}";
                    dom.asesorias.form_asesoria.attr('action', url.replace('_id',asesoria.model_id) );
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

                    // MOSTRAR MODAL
                    dom.asesorias.modal_asesoria.find('.modal-title').text('Editar asesoria');
                    dom.asesorias.modal_asesoria.find('button[data-action="guardar"]').html('<i class="fa fa-save"></i> Actualizar');
                    dom.asesorias.modal_asesoria.modal('show');
                }
            });

            var m_asesorias = (function(asesorias){
                asesorias.crear_asesoria.click(function(e){
                    // MOSTRAR BOTONES
                    dom.asesorias.modal_asesoria.find('button[data-action="cancelar"]').hide();
                    dom.asesorias.modal_asesoria.find('button[data-action="guardar"]').show();

                    // LIMPIEZA DE FORMULARIO
                    asesorias.form_asesoria[0].reset();
                    $(asesorias.form_asesoria[0].id_profesor).val(null).trigger('change');
                    $(asesorias.form_asesoria[0].id_alumno).val(null).trigger('change');
                    $(asesorias.form_asesoria[0].id_profesor).empty();
                    $(asesorias.form_asesoria[0].id_alumno).empty();
                    $(asesorias.form_asesoria[0].horarios).empty();

                    // DESHABILITAR ELEMENTOS
                    $(asesorias.form_asesoria[0].fecha).attr('readonly',true);
                    $(asesorias.form_asesoria[0].horarios).attr('readonly',true);


                    asesorias.modal_asesoria.find('.modal-title').text('Agendar Asesoria');
                    dom.asesorias.modal_asesoria.find('button[data-action="guardar"]').html('<i class="fa fa-check"></i> Guardar');
                    asesorias.modal_asesoria.modal('show');
                });

                asesorias.form_asesoria.submit(function(e){
                    e.preventDefault();

                    asesorias.modal_asesoria.modal('hide');
                    wait.modal('show');

                    var formData = new FormData(this);
                    let method = asesorias.form_asesoria.attr('method') || 'POST';

                    if( method == 'PUT') {
                        formData.append('_method','PUT')
                        method = 'POST';
                    }

                    $.ajax({
                        url: $(this).attr('action'),
                        type: method,
                        contentType: false,
                        processData: false,
                        data: formData
                    }).then(function(response){
                        setTimeout(() => {
                            wait.modal('hide');
                        }, 200);

                        calendar.refetchEvents();
                    }).catch(function(e){
                        setTimeout(() => {
                            wait.modal('hide');
                        }, 200);

                        toastr.error('Error', 'Ocurrio un error inesperado');
                    })
                });

                asesorias.modal_asesoria.on('click','button[data-action="cancelar"]',function(){
                    const asesoria = dom.asesorias.modal_asesoria.data('asesoria');
                    asesorias.modal_asesoria.modal('hide');

                    swal({
                        title: "¿Deseas cancelar la asesoria?",
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
                                status: "{{ config('asesorias.status.keys.cancelados') }}",
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

                $(asesorias.form_asesoria[0].id_profesor).select2({
                    dropdownParent: asesorias.modal_asesoria,
                    language: "es",
                    minimumInputLength: 3,
                    placeholder:'Selecciona un profesor',
                    ajax: {
                        method: 'POST',
                        data:
                        function (params) {
                            var query = {
                                term: params.term,
                                page: params.page || 1,
                                _token: '{{ csrf_token() }}'
                            }
                            return query;
                        },
                        url: '{{ route("admin.usuarios.traer_usuarios_select2") }}',
                        dataType:'json',
                        cache: false,
                        delay:250,
                        beforeSend:function(xhr,type){
                            xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                        }
                    },
                    escapeMarkup: function (markup) { return markup; },
                    templateResult: function(option){
                        if(option.loading){
                            return option.text;
                        }

                        if (option.fullname == undefined) {
                            return option.text;
                        }

                        var markup =
                            `<div style='padding:0px 20px' class='row'>
                                <table>
                                    <tr>
                                        <td style="padding-left:10px">
                                            ${option.fullname || ''}
                                        </td>
                                        <td style="padding-left:10px"></td>
                                    </tr>
                                </table>
                            </div>`

                        return markup;
                    },
                    templateSelection: function(option) {
                        if (option.fullname == undefined) {
                            return option.text;
                        }

                        return ` <p>${option.fullname || ''}</p>`;
                    }
                });

                $(asesorias.form_asesoria[0].id_profesor).on('select2:select', function (e) {
                    $(asesorias.form_asesoria[0].fecha).attr('readonly',false);
                    $(asesorias.form_asesoria[0].fecha).attr('required',true);
                });

                $(asesorias.form_asesoria[0].id_alumno).select2({
                    dropdownParent: asesorias.modal_asesoria,
                    language: "es",
                    minimumInputLength: 3,
                    placeholder:'Selecciona un alumno',
                    ajax: {
                        method: 'POST',
                        data:
                        function (params) {
                            var query = {
                                term: params.term,
                                page: params.page || 1,
                                _token: '{{ csrf_token() }}',
                                id_sucursal: "{{ optional(session('sucursal'))->id }}",
                                status:'Alumno'
                            }
                            return query;
                        },
                        url: '{{ route("alumnos.traer_alumnos_select2") }}',
                        dataType:'json',
                        cache: false,
                        delay:250,
                        beforeSend:function(xhr,type){
                            xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                        }
                    },
                    escapeMarkup: function (markup) { return markup; },

                    templateResult: function(option){
                        if(option.loading){
                            return option.text;
                        }

                        if (option.nombres == undefined) {
                            return
                                `<div style='padding:0px 20px' class='row'>
                                    <table>
                                        <tr>
                                            <td style="padding-left:10px">${option.text}</td>
                                            <td style="padding-left:10px"></td>
                                        </tr>
                                    </table>
                                </div>`;
                        }

                        var markup =
                            `<div style='padding:0px 5px' class='row'>
                                <table>
                                    <tr>
                                        <td style="padding-left:10px">
                                            ${option.nombres || ''} ${option.apellido_paterno || ''}  ${option.apellido_materno || ''}
                                        </td>
                                        <td style="padding-left:10px"></td>
                                    </tr>
                                </table>
                            </div>`

                        return markup;
                    },
                    templateSelection: function(option) {
                        if (option.nombres == undefined) {
                            return option.text;
                        }

                        return ` <p>${option.nombres || ''} ${option.apellido_paterno || '' } ${option.apellido_materno || '' } </p>`;
                    }
                });

                $(asesorias.form_asesoria[0].fecha).change(function(e){
                    // OBTENER LA INFORMACION DEL PROVEEDOR
                    const dia = moment(e.target.value).day();
                    const dia_semana = dias_semana[dia];
                    const id_profesor = $(dom.asesorias.form_asesoria[0].id_profesor).val();
                    const $select_horarios = $(asesorias.form_asesoria[0].horarios);
                    const dia_actual = moment().day();
                    const hora_actual = parseInt(moment().format('H')) ;

                    $.ajax({
                        url: "{{ route('agendar-asesoria.horarios-profesor') }}",
                        type: 'POST',
                        data: {
                            id_profesor: id_profesor
                        }
                    }).then(function(response){
                        const horarios = response.horarios || [];

                        // FILTRAR LOS HORARIOS DEL PROFESOR
                        const horarios_disponibles = horarios
                        .filter(function(horario){
                            return horario.dia == dia_semana;
                        })
                        .filter(function(horario){
                            if(dia == dia_actual){
                                return horario.hora_inicio > hora_actual;
                            }
                            return true;
                        });

                        $select_horarios.empty();

                        if(horarios_disponibles.length > 0){
                            $select_horarios.append(new Option('Selecciona un horario', '', false, false));

                            // MOSTRAR LOS HORARIOS DEL PROFESOR
                            horarios_disponibles.forEach(function(horario){
                                const option = new Option(horario.descripcion, horario.id, false, false);
                                option.dataset.inicio = horario.hora_inicio;
                                option.dataset.fin = horario.hora_final;
                                $select_horarios.append(option);
                            });

                            $select_horarios.attr('readonly',false);
                            $select_horarios.attr('required',true);
                        }else{
                            $select_horarios.append(new Option('No hay horarios disponibles', '', false, false))
                        }
                    }).catch(function(e){
                        toastr.error('Error', 'Ocurrio un error inesperado');
                    });
                });

                $(asesorias.form_asesoria[0].horarios).change(function(e){
                    const $option = $(this).find('option:selected');

                    if($option.length){
                        asesorias.form_asesoria[0].hora_inicio.value = `${$option.data('inicio')}:00`
                        asesorias.form_asesoria[0].hora_fin.value = `${$option.data('fin')}:00`
                    }else{
                        asesorias.form_asesoria[0].hora_inicio.value = null
                        asesorias.form_asesoria[0].hora_fin.value = null;
                    }
                })

                // NOTE: FILTROS
                asesorias.filtro.profesor.select2({
                    language: "es",
                    minimumInputLength: 3,
                    allowClear: true,
                    placeholder:'Selecciona un cliente',
                    ajax: {
                        method: 'POST',
                        data:
                        function (params) {
                            var query = {
                                term: params.term,
                                page: params.page || 1,
                                _token: '{{ csrf_token() }}'
                            }
                            return query;
                        },
                        url: '{{ route("admin.usuarios.traer_usuarios_select2") }}',
                        dataType:'json',
                        cache: false,
                        delay:250,
                        beforeSend:function(xhr,type){
                            xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                        }
                    },
                    escapeMarkup: function (markup) { return markup; },

                    templateResult: function(option){
                        if(option.loading){
                            return option.text;
                        }

                        if (option.fullname == undefined) {
                            return option.text;
                        }

                        var markup =
                            `<div style='padding:0px 20px' class='row'>
                                <table>
                                    <tr>
                                        <td style="padding-left:10px">
                                            ${option.fullname || ''}
                                        </td>
                                        <td style="padding-left:10px"></td>
                                    </tr>
                                </table>
                            </div>`

                        return markup;
                    },
                    templateSelection: function(option) {
                        if (option.fullname == undefined) {
                            return option.text;
                        }

                        return ` <p>${option.fullname || ''}</p>`;
                    }
                });

                asesorias.filtro.profesor.on('select2:select', function (e) {
                    calendar.refetchEvents();
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
