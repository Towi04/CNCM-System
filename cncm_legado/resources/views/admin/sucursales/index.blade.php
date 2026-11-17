@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Sucursales <small>Administra las sucursales del sistema</small>
@endsection

@section('buttons')
    <a href={{ route('admin.sucursales.create') }}>
        <button class="btn btn-success btn-sm" type="button">
            <i class="fa fa-plus"></i> Agregar sucursal
        </button>
    </a>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Sucursales</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row justify-content-start px-4">
        <div>
            <a class="mb-3" href={{ route('admin.sucursales.create') }}>
                <button class="btn btn-success btn-sm" type="button">
                    <i class="fa fa-plus-circle fa-xs" aria-hidden="true"></i> Agregar sucursal
                </button>
            </a>
        </div>
    </div>

    <div class="row widget-list">
        <div class="widget-holder widget-full-height widget-flex col-lg-12">
            <div class="widget-body">
                <div class="table-responsive mt-3">
                    <table id="tb-sucursales" class="table table-padded  table-striped table-hover" style="border-collapse:separate !important">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Dirección</th>
                                <th>Municipio</th>
                                <th>Estado</th>
                                <th>Telefono</th>
                                <th>RFC</th>
                                <th class="text-center">Acciones</th>
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
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        const dom = {
            table: $('#tb-sucursales'),
        };

        var dt = dom.table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.sucursales.datatables') }}",
                method:'POST',
                data: function (d) {
                }
            },
            pageLength: 10,
            responsive: true,
            buttons: [{
                extend: 'excel',
                title: 'Orden Compra'
            }],
            columns: [
                { data: 'nombre', name: 'nombre',class: 'text-nowrap'},
                { data: 'direccion', name: 'direccion', class: 'text-nowrap' },
                { data: 'municipio', name: 'municipio', class: 'text-nowrap' },
                { data: 'estado', name: 'estado', class: 'text-nowrap' },
                { data: 'telefono', name: 'telefono', class: 'text-nowrap' },
                { data: 'rfc', name: 'rfc', class: 'text-nowrap' },
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
            order: [[ 2, 'desc' ] ],
            drawCallback: function(settings) {
                $("[data-toggle='tooltip']").tooltip();
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
                        setTimeout(function(){
                            wait.modal('hide');
                        },250);

                        toastr.success('Éxito', 'Se borró con éxito el registro');

                        dt.ajax.reload( null, false )
                    },
                    fail:function(error){
                        toastr.error('Error', 'Ocurrio un error inesperado');
                    }
                });
            })
        })
    })
</script>
@endsection
