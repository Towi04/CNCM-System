@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Grupos
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Grupos</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <main class="p-2 bg-white">
        <section class="row align-items-center justify-content-sm-center justify-content-md-start">
            <div class="col-auto">
                @can('crear_grupo')
                    <a class="" href={{ route('grupos.create') }}>
                        <button class="btn btn-success btn-sm" type="button">
                            <i class="fa fa-plus-circle fa-xs" aria-hidden="true"></i> Agregar
                        </button>
                    </a>
                @endcan
            </div>

            <div class="col-auto">
                <div class="form-inline">
                    <label for="status_grupo">
                        <span class="mr-2">Status:</span>
                        {!! Form::select('grupo', ['' => 'Todos'] + config('grupos.status.values',[]) , config('grupos.status.values.Activo'), ['id' => 'status_grupo','class' => 'custom-select custom-select-sm']) !!}
                    </label>
                </div>
            </div>

        </section>

        <section class="row">
           <div class="col-12">
                <table id="tb-grupos" class="table table-bordered w-100" >
                    <thead class="bg-primary text-white">
                        <tr>
                            <th>#</th>
                            <th>Clave</th>
                            <th>Especialidad</th>
                            <th>Horario</th>
                            <th>Dias</th>
                            <th>Tipo</th>
                            <th>No Alumnos</th>
                            <th>Semanas</th>
                            <th>Fecha Inicio</th>
                            <th>Status</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
           </div>
        </section>
    </main>

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
<script type="text/javascript">
    $(function() {
        const dom = {
            table: $('#tb-grupos'),
            opciones_lista:{
                modal: $("#modal-opciones-lista"),
                form: $("#form-opciones-lista"),
            }
        };

        var dt = dom.table.DataTable({
            processing: true,
            serverSide: true,
            dom: "<'row'<'col-12 col-sm-6 align-items-center' l><'col-12 col-sm-1 justify-content-center'B> <'col-12 col-sm-5 'f>><'row'<'col-12 table-responsive'tr>><'row'<'col-12 col-sm-7'i><'col-12 col-sm-5 d-flex align-self-end justify-content-center justify-content-sm-end'p>>",
            ajax: {
                url: "{{ route('grupos.datatables') }}",
                method:'POST',
                data: function (d) {
                    d.id_sucursal= "{{ optional(session('sucursal'))->id }}";
                    d.status= $('#status_grupo').val()
                },
                beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                        xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                    }
                },
            },
            pageLength: 10,
            lengthMenu: [[10,50,100,-1],['10','50','100','Todos']],
            responsive: true,
            buttons: [
                @can('descargar_excel_bd')
                {
                    title: 'Grupos',
                    extend: 'excel',
                    text:'Excel <i class="fas fa-file-excel"></i>',
                    className: 'btn btn-primary btn-sm',
                }
                @endcan
            ],
            columns: [
                { data: 'id', name: 'id',class: 'text-nowrap'},
                { data: 'clave', name: 'clave',class: 'text-nowrap'},
                { data: 'especialidad.nombre', name: 'especialidad.nombre',class: 'text-nowrap'},
                { data: 'horario', name: 'horario',class: 'text-nowrap'},
                { data: 'days', name: 'days',class: 'text-nowrap'},
                { data: 'infantil', name: 'infantil',class: 'text-nowrap'},
                { data: 'no_alumnos', name: 'infantil',class: 'text-nowrap text-center',orderable: false, searchable: false},
                { data: 'no_semanas', name: 'no_semanas',class: 'text-nowrap',orderable: false, searchable: false},
                { data: 'fecha_inicio', name: 'fecha_inicio',class: 'text-nowrap'},
                { data: 'status', name: 'status',class: 'text-nowrap'},
                { data: 'buttons', name: 'buttons', orderable: false, searchable: false }
            ],
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
            order: [[ 0, 'desc' ] ],
            drawCallback: function(settings) {
                $("[data-toggle='tooltip']").tooltip();

                $('.finalizar_grupo').on('click',function(){
                    id= $(this).data('id')
                    swal({
                        title: "¿Estas seguro de FINALIZAR el grupo?",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#ff3333",
                        cancelButtonColor: "#CDCDCD",
                        confirmButtonText: "Si, finalizar",
                        cancelButtonText: "Cancelar",
                        showLoaderOnConfirm: false,
                    }).then(function(result) {
                        if (!result.value) {
                            return;
                        }

                        wait.modal('show');

                        $.ajax({
                            url: "{{route('grupos.finalizar_grupo')}}",
                            type: 'POST',
                            cache: false,
                            data: {
                                _token: $("meta[name='csrf-token']").attr("content"),
                                id:id
                            },
                            success: function (response){
                                dt.ajax.reload( function(e){
                                    wait.modal('hide');
                                    toastr.success('Éxito', 'Se finalizó con éxito el grupo');
                                }, false )
                            },
                            fail:function(error){
                                wait.modal('hide');
                                toastr.error('Error', 'Ocurrio un error inesperado');
                            }
                        });
                    })
                })

                $('.activar_grupo').on('click',function(){
                    id= $(this).data('id')
                    swal({
                        title: "¿Estas seguro de ACTIVAR el grupo?",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#2ad521",
                        cancelButtonColor: "#CDCDCD",
                        confirmButtonText: "Si, activar",
                        cancelButtonText: "Cancelar",
                        showLoaderOnConfirm: false,
                    }).then(function(result) {
                        if (!result.value) {
                            return;
                        }

                        wait.modal('show');

                        $.ajax({
                            url: "{{route('grupos.activar_grupo')}}",
                            type: 'POST',
                            cache: false,
                            data: {
                                _token: $("meta[name='csrf-token']").attr("content"),
                                id:id
                            },
                            success: function (response){
                                dt.ajax.reload( function(e){
                                    wait.modal('hide');
                                    toastr.success('Éxito', 'Se activo con éxito el grupo');
                                }, false )
                            },
                            fail:function(error){
                                wait.modal('hide');
                                toastr.error('Error', 'Ocurrio un error inesperado');
                            }
                        });
                    })
                })
            },
        });

        var searchWait = 0;
        var searchWaitInterval;

        $('.dataTables_filter input')
            .unbind()
            .bind('input', function(e) {
                var item = $(this);
                searchWait = 0;
                if (!searchWaitInterval) searchWaitInterval = setInterval(function() {
                    if (searchWait >= 3) {
                        clearInterval(searchWaitInterval);
                        searchWaitInterval = '';
                        searchTerm = $(item).val();
                        dt.search(searchTerm).draw();
                        searchWait = 0;
                    }
                    searchWait++;
                }, 200);

            });

        dom.table.on('click',"a[data-action='delete']",function(event){
            event.preventDefault();

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
                    url: event.target.href,
                    type: 'DELETE',
                    cache: false,
                    data: {
                        _token: $("meta[name='csrf-token']").attr("content"),
                    },
                    success: function (response){
                        dt.ajax.reload( function(e){
                            wait.modal('hide');
                            toastr.success('Éxito', 'Se borró con éxito el registro');
                        }, false )
                    },
                    fail:function(error){
                        toastr.error('Error', 'Ocurrio un error inesperado');
                    }
                });
            })
        })

        dom.table.on('click',"a[data-action='opciones-lista']",function(event){
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

        $('#status_grupo').change(function(){
            dt.draw();
        });

        $('#select_id_especialidad').change(function(){
            window.localStorage.setItem('activeTabIdEspecelidad',$(this).val());
            dt.ajax.reload(null, false);
        });

        // SE ACTIVA LOCAL STORAGE PARA GUARDAR STATUS TIPO DE ESTACION
        activeTabIdEspecelidad = window.localStorage.getItem('activeTabIdEspecelidad');
        //INIT
        if (activeTabIdEspecelidad) {

            $('#select_id_especialidad').val(activeTabIdEspecelidad);
            dt.ajax.reload(null,false);
        }
    })
</script>
@endsection
