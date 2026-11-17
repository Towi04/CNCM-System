@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo','Horarios Profesores')


@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <strong>Administracion</strong>
        </li>
        <li class="breadcrumb-item active">
            <strong>Horarios Profesores</strong>
        </li>
    </ol>
@endsection

@section('contenido')
<div class="row justify-content-start px-4">
    <div>
       {!! Form::select('id_profesor', $profesores, null, ['id' => 'select-profesores','class' => 'custom-select custom-select-sm']) !!}
    </div>
</div>

<div class="row widget-list">
    <div class="widget-holder widget-full-height widget-flex col-lg-12">
        <div class="widget-body">
            <div class="mt-3">
                <table id="tb-grupos" class="table table-padded  table-striped table-hover" style="width: 100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Profesor</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>Dia</th>
                            <th>H. Inicio</th>
                            <th>H. Fin</th>
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
            table: $('#tb-grupos'),
            filters: {
                profesores: $("#select-profesores")
            }
        };

        var dt = dom.table.DataTable({
            processing: true,
            serverSide: true,
            dom: "<'row'<'col-6 d-flex align-items-center' l><'col-6'f>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
            ajax: {
                url: "{{ route('admin.horarios-profesores.datatables') }}",
                method:'POST',
                data: function (d) {
                    d.id_sucursal = "{{ optional(session('sucursal'))->id }}";
                    d.id_profesor = dom.filters.profesores.val();
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
                title: 'Grupos'
            }],
            columns: [
                { data: 'id', name: 'id',class: 'text-nowrap'},
                { data: 'full_name',class: 'text-nowrap',searchable: false},
                { data: 'profesor.nombres', name: 'profesor.nombres',class: 'text-nowrap', "visible": false,},
                { data: 'profesor.apellido_materno', name: 'profesor.apellido_materno',class: 'text-nowrap', "visible": false,},
                { data: 'profesor.apellido_paterno', name: 'profesor.apellido_paterno',class: 'text-nowrap', "visible": false,},
                { data: 'dia', name: 'dia',class: 'text-nowrap'},
                { data: 'hora_inicio', name: 'hora_inicio',class: 'text-nowrap'},
                { data: 'hora_final', name: 'hora_final',class: 'text-nowrap'},
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

        dom.filters.profesores.change(function(e){
            dt.ajax.reload( null, false );
        })
    })
</script>
@endsection
