@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
        Proyección cobranza
@endsection


@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Proyeccion cobranza</strong>
        </li>
    </ol>
@endsection

@section('contenido')


<div class="row widget-list justify-content-end">
    <div class="col-lg-3 mb-3 no_print">
        {{-- <button onclick="window.print();" class="btn btn-block btn-secondary mb-3">
            <i class="fas fa-print"></i> Imprimir
        </button> --}}
        {{-- <div class="col-sm-12 col-xxxl-12 p-1">
            <a class="element-box el-tablo" href="#">
              <div class="label mb-2">
                Total
              </div>
              <div class="total_proyeccion">
              </div>
            </a>
        </div> --}}
    </div>
</div>
<div class="row">
    <div class="col-lg-12 mb-3 no_print">
    <div class="widget-holder widget-full-height widget-flex col-lg-12">
        <div class="widget-body">
            <div class="table-responsive mt-3">
                <table id="tb-alumnos" class="table table-padded  table-striped table-hover">
                    <thead>
                        <tr>
                            <th># Control</th>
                            <th># Control(Ref)</th>
                            <th>Nombre</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>Pagos por cobrar</th>
                            <th>Monto por cobrar</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>

</div>


@endsection

@section('scripts')
<script type="text/javascript">
    $(function() {
        const dom = {
            table: $('#tb-alumnos'),
        };

        var dt = dom.table.DataTable({
            processing: true,
            serverSide: true,
            dom: "<'row'<'col-6 d-flex align-items-center' l><'col-6'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
            ajax: {
                url: "{{ route('reportes.reporte-ventas.datatables_proyeccion') }}",
                method:'POST',
                data: function (d) {
                    d.id_sucursal = "{{ optional(session('sucursal'))->id }}"
                },
                beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                        xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                    }
                },
            },
            pageLength: 10,
            responsive: true,
            buttons: [{
                extend: 'excel',
                title: 'Alumnos'
            }],
            columns: [
                { data: 'nuevo_numero_control', name: 'nuevo_numero_control',class: 'text-nowrap text-center'},
                { data: 'numero_control', name: 'numero_control',class: 'text-nowrap text-center'},
                { data: 'nombre_alumno', name: 'nombre_alumno',class: 'text-nowrap'},
                { data: 'nombres', name: 'nombres',class: 'text-nowrap',visible:false},
                { data: 'apellido_paterno', name: 'apellido_paterno',class: 'text-nowrap',visible:false},
                { data: 'apellido_materno', name: 'apellido_materno',class: 'text-nowrap',visible:false},
                { data: 'pagos_por_cobrar', class: 'text-nowrap text-center',orderable:false,searchable:false},
                { data: 'monto_por_cobrar', class: 'text-nowrap text-right',orderable:false,searchable:false},
                // { data: 'buttons', name: 'buttons', orderable: false, searchable: false }
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
    })
</script>
@endsection
