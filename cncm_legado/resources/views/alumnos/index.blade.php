@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Alumnos
@endsection

@section('breadcrumb')

    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Alumnos</strong>
        </li>
    </ol>
    
@endsection

@section('contenido')

    <main class="p-2 bg-white">
        <section class="row">
            <div class="col-12">
                @can('crear_alumno')
                    <a class="mb-3" href={{ route('alumnos.create') }}>
                        <button class="btn btn-success btn-sm" type="button">
                            <i class="fa fa-plus-circle fa-xs" aria-hidden="true"></i> Agregar Alumno
                        </button>
                    </a>
                @endcan

                <label>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" name="" id="alumnos_no_grupos" value="" >
                                Mostrar alumnos sin grupos
                        </label>
                    </div>
                </label>
            </div>
        </section>

        <section class="row">
            <div class="col-12 form-inline">
                <div class="form-group">
                  <label for="">Filtra por forma de pago:</label>
                  {!! Form::select('forma_pago', [''=>'Todos','semanal'=>'Semanal','mensual'=>'Mensual'], null, ['class'=>'form-control','id'=>'filtro_forma_pago']) !!}
                </div>
            </div>
        </section>
        <section class="row">
            <div class="col-12">
                <table id="tb-alumnos" class="table table-bordered w-100">
                    <thead>
                        <tr class="bg-primary text-white">
                            <th># Control</th>
                            <th># Control (Ref)</th>
                            <th>Nombre</th>
                            <th></th>
                            <th></th>
                            <th></th>

                            <th>Asesor</th>
                            <th></th>
                            <th></th>
                            <th></th>

                            <th>Grupos</th>
                            <th>Forma de pago</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

@endsection

@section('scripts')
<link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
<script src="{{ asset('plugins/xeditable/js/bootstrap-editable.min.js') }}"></script>


    <script type="text/javascript">
        $(function() {
            const dom = {
                table: $('#tb-alumnos'),
            };

            var dt = dom.table.DataTable({
                processing: true,
                serverSide: true,
                dom: "<'row'<'col-12 col-sm-6 align-items-center' l><'col-12 col-sm-1 justify-content-center'B> <'col-12 col-sm-5 'f>><'row'<'col-12 table-responsive'tr>><'row'<'col-12 col-sm-7'i><'col-12 col-sm-5 d-flex align-self-end justify-content-center justify-content-sm-end'p>>",
                ajax: {
                    url: "{{ route('alumnos.datatables') }}",
                    method:'POST',
                    data: function (d) {
                        d.id_sucursal = "{{ optional(session('sucursal'))->id }}"
                        d.alumnos_no_grupos = $('#alumnos_no_grupos').is(':checked')
                        d.forma_pago = $('#filtro_forma_pago').val()
                    },
                    beforeSend: function(xhr,type) {
                        if (!type.crossDomain) {
                            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                        }
                    },
                },
                pageLength: 10,
                responsive: true,
                buttons: [
                    @can('descargar_excel_bd')
                    {
                        extend: 'excel',
                        text:'Excel <i class="fas fa-file-excel"></i>',
                        className: 'btn btn-primary btn-sm',
                        extend: 'excel',
                        title: 'Alumnos'
                    }
                    @endcan
                ],
                columns: [
                    { data: 'nuevo_numero_control', name: 'nuevo_numero_control',class: 'text-nowrap'},
                    { data: 'numero_control', name: 'numero_control',class: 'text-nowrap'},

                    { data: 'nombre_alumno', name: 'nombre_alumno',class: 'text-nowrap'},
                    { data: 'nombres', name: 'nombres',class: 'text-nowrap',visible:false},
                    { data: 'apellido_paterno', name: 'apellido_paterno',class: 'text-nowrap',visible:false},
                    { data: 'apellido_materno', name: 'apellido_materno',class: 'text-nowrap',visible:false},

                    { data: 'nombre_asesor',name: 'nombre_asesor',class: 'text-nowrap'},
                    { data: 'asesor_educativo.nombres', name: 'asesor_educativo.nombres',class: 'text-nowrap',visible:false},
                    { data: 'asesor_educativo.apellido_paterno', name: 'asesor_educativo.apellido_paterno',class: 'text-nowrap',visible:false},
                    { data: 'asesor_educativo.apellido_materno', name: 'asesor_educativo.apellido_materno',class: 'text-nowrap',visible:false},

                    { data: 'no_grupos', name: 'no_grupos',class: 'text-nowrap', orderable: false, searchable: false},
                    { data: 'forma_pago', name: 'forma_pago',class: 'text-nowrap'},
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

                    $('.editable_asesor').editable({
                                'emptytext':'Selecciona una asesor',
                                'showbuttons':false,
                                'source':[
                                    @foreach($asesores as $id => $asesor)
                                    { value: "{{$id}}",text: "{{$asesor}}"},
                                    @endforeach
                                ]
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
                        error:function(error){
                            setTimeout(() => {
                                wait.modal('hide');
                                toastr.error('Error', 'Ocurrio un error inesperado');
                            }, 250);
                        }
                    });
                })
            })


            $('#alumnos_no_grupos').click(function(){
                dt.ajax.reload(null, false);
            });

            $('#filtro_forma_pago').change(function(){
                dt.ajax.reload(null, false);
            })
        })
    </script>
@endsection
