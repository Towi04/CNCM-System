@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Descuentos
@endsection


@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Descuentos</strong>
        </li>
    </ol>
@endsection

@section('contenido')
<main class="p-2 bg-white">
    <section class="row justify-content-start px-4">
        <div>
            @can('crear_especialidad')
                <a class="mb-3" href={{ route('descuentos.create') }}>
                    <button class="btn btn-success btn-sm" type="button">
                        <i class="fa fa-plus-circle fa-xs" aria-hidden="true"></i> Agregar
                    </button>
                </a>
            @endcan
        </div>
    </section>

    <section class="row ">
      <div class="col-12">
          <table id="tb-descuentos" class="table table-bordered w-100">
              <thead class="bg-primary text-white">
                  <tr>
                      <th>ID</th>
                      <th>Especialidad 1</th>
                      <th>Especialidad 2</th>
                      <th>Porcentaje descuento</th>
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
<script type="text/javascript">
    $(function() {
        const dom = {
            table: $('#tb-descuentos'),
        };

        var dt = dom.table.DataTable({
            processing: true,
            serverSide: true,
            dom: "<'row'<'col-12 col-sm-6 align-items-center' l><'col-12 col-sm-1 justify-content-center'B> <'col-12 col-sm-5 'f>><'row'<'col-12 table-responsive'tr>><'row'<'col-12 col-sm-7'i><'col-12 col-sm-5 d-flex align-self-end justify-content-center justify-content-sm-end'p>>",
            ajax: {
                url: "{{ route('descuentos.datatables') }}",
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
            buttons: [
                @can('descargar_excel_bd')
                {
                    extend: 'excel',
                    title: 'Descuentos',
                    text:'Excel <i class="fas fa-file-excel"></i>',
                    className: 'btn btn-primary btn-sm',
                }
                @endcan
            ],
            columns: [
                { data: 'id', name: 'id',class: 'text-nowrap'},
                { data: 'especialidad_1.nombre', name: 'especialidad_1.nombre',class: 'text-nowrap text-right'},
                { data: 'especialidad_2.nombre', name: 'especialidad_2.nombre',class: 'text-nowrap text-right'},
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
    })
</script>
@endsection
