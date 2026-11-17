@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo', 'Compras' )

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li  class="breadcrumb-item">
            <a href="{{ route('admin.productos.index') }}">Productos</a>
        </li>
        <li class="breadcrumb-item active">
            <a>Compras del producto {{$producto->nombre}}</a>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row justify-content-start px-4">
        <div>
                <a class="mb-3" href={{ route('admin.compras.create', $producto->id) }}>
                    <button class="btn btn-success btn-sm" type="button">
                        <i class="fa fa-plus-circle fa-xs" aria-hidden="true"></i> Agregar Compra
                    </button>
                </a>
        </div>
    </div>

    <div class="row widget-list">
        <div class="widget-holder widget-full-height widget-flex col-lg-12">
            <div class="widget-body">
                <div class="mt-3">
                    <table class="table table-striped table-padded  table-hover" id="tb-compras" style="width: 100%">
                        <thead>
                            <tr>
                                <th class="text-nowrap">#</th>
                                <th class="text-nowrap">Fecha</th>
                                <th class="text-nowrap">Cantidad</th>
                                <th class="text-nowrap">Usuario</th>
                                <th class="text-nowrap">Apellido Paterno</th>
                                <th class="text-nowrap">Acciones</th>
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
    <script type="text/javascript">
        $(function() {
            const dom = {
                table: $('#tb-compras'),
            };

            var dt = dom.table.DataTable({
                processing: true,
                serverSide: true,
                dom: "<'row'<'col-12 col-sm-6'l><'col-12 col-sm-6 'f>><'row'<'col-12 table-responsive 'tr>><'row'<'col-12 col-sm-5'i><'col-12 col-sm-7 d-flex justify-content-center justify-content-sm-end'p>>",
                ajax: {
                    url: "{{ route('admin.compras.datatables') }}",
                    type: "POST",
                    data: function (d) {
                        d.id_producto = "{{ $producto->id }}"
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
                    {
                        extend: 'excel',
                        title: 'Compras'
                    }
                ],
                columns: [
                    {data: 'id', name: 'id',class:'text-nowrap'},
                    {data: 'fecha', name: 'fecha',class:'text-nowrap'},
                    {data: 'cantidad', name: 'cantidad',class:'text-nowrap'},
                    {data: 'usuario.nombres', name: 'usuario.nombres',class:'text-nowrap'},
                    {data: 'usuario.apellido_paterno', name: 'usuario.apellido_paterno',class:'text-nowrap',orderable: false, visible: false},
                    {data: 'buttons', name: 'buttons', orderable: false, searchable: false}
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
                drawCallback: function (settings) {
                    $("[data-toggle='tooltip']").tooltip();
                },
                order:[[1,'desc']]
            });

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

            dom.table.on('click',"a[data-action='delete']",function(e){
                e.preventDefault();

                var url = $(this).attr('href');

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
                        url: url,
                        type: 'DELETE',
                        success: function (response){
                            dt.ajax.reload( function(){
                                wait.modal('hide');
                                toastr.success('Éxito', 'Se borró con éxito el registro');
                            }, false )
                        },
                        error:function(error){
                            setTimeout(() => {
                                wait.modal('hide');
                                toastr.error('Error', 'Ocurrio un error inesperado');
                            }, 500);
                        }
                    });
                })
            });
        })
    </script>
@endsection
