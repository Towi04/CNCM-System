@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Asignar alumnos al grupo <small></small>
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
            <strong>Asignar alumnos</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-wrapper pb-2">
                <h6 class="element-header">
                Informacion del grupo
                </h6>
                <table class="table table-bordered">
                    <tr>
                        <td class="bg-primary text-white"><b>Clave Grupo</b></td>
                        <td>
                            {{ $grupo->clave }}
                        </td>
                    </tr>
                    <tr>
                        <td class="bg-primary text-white"><b>Especialidad</b></td>
                        <td>
                            {{ $grupo->especialidad->nombre }}
                        </td>
                    </tr>
                    <tr>
                        <td class="bg-primary text-white"><b>Fecha Inicio</b></td>
                        <td>
                            {{ $grupo->fecha_inicio_format }}
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
                    <tr>
                        <td class="bg-primary text-white"><b>N° Alumnos</b></td>
                        <td>
                            {{ $grupo->alumnos->count() }}
                        </td>
                    </tr>
                    <tr>
                        <td class="bg-primary text-white"><b>Status</b></td>
                        <td>
                            {{ $grupo->status }}
                        </td>
                    </tr>
                    <tr>
                        <td class="bg-primary text-white"><b>Accion</b></td>
                        <td>
                            <button id="btn-asignar-alumno" class="btn btn-success btn-sm" type="button">
                                <i class="fa fa-plus-circle fa-xs" aria-hidden="true"></i> Asignar alumno
                            </button>

                            @can('consultar_grupo')
                                <a  href="{{ route("grupos.cronograma",$grupo->id) }}"
                                    class="btn btn-secondary btn-sm text-white"
                                    data-placement="top"
                                    title="Cronograma">
                                    <i class="fas fa-clock"></i> Cronograma
                                </a>

                                <a  href="{{ route("grupos.lista-asistencia",[$grupo->id,0]) }}"
                                    class="btn btn-dark btn-sm text-white "
                                    target="_blank"
                                    rel="noopener"
                                    data-toggle="tooltip"
                                    data-placement="top"
                                    title="Lista">
                                    <i class="fas fa-file-pdf"></i> Lista Asistencia
                                </a>
                            @endcan
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="modal inmodal fade animated" id="modal-asignar-alumno" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content animated bounceInRight">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Agregar alumno al grupo</h4>
                        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">
                            &times;</span><span class="sr-only">Close</span>
                        </button>
                    </div>

                    {!! Form::open(['id' => 'form-asignar-alumno','route' => ['grupos.guardar-alumnos',$grupo], 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data']) !!}
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        {!! Form::label('id_alumno', 'Alumno:*'); !!}
                                        {!! Form::select('id_alumno',[], null, ['class' => 'form-control','required' => true,'style' => 'width:100%']) !!}
                                    </div>
                                </div>
                            </div>
                            <div class="form-group" id="modal-error">

                            </div>
                        </div>

                        <div class="modal-footer text-right">
                            <button class="btn btn-success btn-sm" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                        </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
        <div class="element-wrapper">
            <h6 class="element-header">
            Alumnos inscritos
            </h6>
            <div class="element-box-tp">
                <table class="table table-striped table-bordered table-hover" id="tb-alumnos">
                    <thead>
                        <tr>
                            <th># Control</th>
                            <th># Control (Ref)</th>
                            <th>Alumno</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
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
        $(function() {
            const dom = {
                btn_asignar_alumno: $("#btn-asignar-alumno"),
                modal_asignar_alumno: $("#modal-asignar-alumno"),
                table: $('#tb-alumnos'),
                select_alumno: $("#id_alumno"),
                form_asignar_alumno: $("#form-asignar-alumno")
            };

            var dt = dom.table.DataTable({
                dom: "<'row'<'col-6' l><'col-6'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
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
                    {data: 'alumno.numero_control', name: 'alumno.numero_control'},
                    {data: 'nombre_alumno', name: 'nombre_alumno'},
                    {data: 'buttons', name: 'buttons', orderable: false, searchable: false}
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

                    $.fn.editable.defaults.mode = 'inline';

                    $('.editable').on('shown', function(e, editable) {
                        $('.editable-submit').html('<i class="fas fa-check fa-1x"></i>');
                        $('.editable-cancel').html('<i class="fas fa-times"></i>');
                    });

                    $('.editable_id_alumno').editable({
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
                                data: function(params) {
                                    return {
                                        term: params.term,
                                        page: params.page || 1,
                                        id_sucursal: "{{ optional(session('sucursal'))->id }}",
                                        _token: '{{ csrf_token() }}'
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

                                if(!option.nombres || !option.apellido_paterno || !option.apellido_materno){
                                    return option.text
                                }

                                return `${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                            },
                            templateSelection: function(option){
                                if(!option.nombres || !option.apellido_paterno || !option.apellido_materno){
                                    return option.text
                                }

                                return `${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                            },
                        },
                        display: function(value, sourceData,response) {
                            if(sourceData){
                                const alumno = sourceData.alumno_grupo.alumno;
                                $(this).text(`${alumno.nombres|| ''} ${alumno.apellido_paterno || ''} ${alumno.apellido_materno || ''} `);
                            }
                        },
                        emptytext: 'Selecciona un alumno',
                        tpl: '<select style="width:100%;z-index: 289;">',
                        type: 'select2',
                    });
                },
            });

            // DETENER EL TIEMPO DE BUSQUEDA PARA EVITAR BUSQUEDAS INNECESARIAS
            var searchWait = 0;
            var searchWaitInterval;

            $('.dataTables_filter input')
                .unbind()
                .bind('input', function(e){
                    var item = $(this);
                    searchWait = 0;
                    if(!searchWaitInterval) searchWaitInterval = setInterval(function(){
                        if(searchWait >= 3){
                            clearInterval(searchWaitInterval);
                            searchWaitInterval = '';
                            searchTerm = $(item).val();
                            dt.search(searchTerm).draw();
                            searchWait = 0;
                        }
                        searchWait++;
                    },200);

                });

            dom.select_alumno.select2({
                language: "es",
                placeholder:'Selecciona un alumnno',
                dropdownParent: dom.modal_asignar_alumno,
                ajax: {
                    method: 'POST',
                    data:function (params) {
                        return {
                            _token: '{{ csrf_token() }}',
                            term: params.term,
                            page: params.page || 1,
                            id_sucursal: "{{ optional(session('sucursal'))->id }}",
                        }
                    },
                    url: '{{ route("alumnos.traer_alumnos_select2") }}',
                    dataType: 'json',
                    cache: false,
                    delay:250,
                    beforeSend:function(xhr,type){
                        xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                    }
                },
                escapeMarkup: function (markup) { return markup; },
                minimumInputLength: 3,
                templateResult: function(option){
                    if (option.loading) {
                        return option.text;
                    }

                    if(!option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                },
                templateSelection:function(option){
                    if(!option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                }
            });

            dom.btn_asignar_alumno.click(function(e){
                dom.form_asignar_alumno.trigger('reset');
                dom.select_alumno.val(null).trigger('change');

                dom.modal_asignar_alumno.find('#modal-error').html(null);
                dom.modal_asignar_alumno.modal('show');
            });

            dom.form_asignar_alumno.submit(function(e){
                e.preventDefault();
                dom.modal_asignar_alumno.modal('hide');

                const formData = new FormData(this);

                $.ajax({
                    url: e.target.action,
                    type: 'POST',
                    cache: false,
                    contentType: false,
                    processData: false,
                    data: formData,
                    success: function (response){
                        dt.ajax.reload( function(){
                            toastr.success('Éxito', response.message);
                        }, false )
                    },
                    error:function(error){
                        dom.modal_asignar_alumno.modal('show');
                        dom.modal_asignar_alumno.find('#modal-error').html('Ocurrio un error inesperado');
                    }
                });

            });

            dom.table.on('click',"a[data-action='delete']",function(e){
                e.preventDefault();

                var url = $(this).attr('href');
                var id = $(this).data('id');
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
                    // wait.modal('show');

                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            id_alumno_grupo: id
                        },
                        success: function (response){
                            // wait.modal('hide');
                            dt.ajax.reload( function(){
                                toastr.success('Éxito', response.message);
                            }, false );
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
