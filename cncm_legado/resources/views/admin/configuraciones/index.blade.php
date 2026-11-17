@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Configuraciones del sistema
@endsection

@section('buttons')
@endsection

@section('breadcrumb')
@endsection

@section('contenido')


<div class="row widget-list justify-content-center">
    <div class="widget-holder widget-full-height widget-flex col-lg-8">
        <div class="widget-body">
            <div class="table-responsive mt-3">
                <a href="{{url('storage/actividad.log')}}" download><i class="fas fa-download    "></i>  Descargar Bitacora  </a>   
                <table id="tb-roles" class="table table-padded  table-striped table-hover" style="border-collapse:separate !important">
                    <thead>
                        <tr>
                            {{-- <th>Nombre</th> --}}
                            <th>Descripcion</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($configuraciones as $configuracion)
                        <tr>
                            {{-- <td>{{$configuracion->nombre }}</td> --}}
                            <td>{{$configuracion->descripcion }}</td>
                            <td>
                                <a class='editable_{{$configuracion->nombre}} editable'
                                @if($configuracion->nombre == 'mostrar_solo_fiscales')
                                data-type='select'
                                @endif
                                @if($configuracion->nombre == 'porcentaje_fiscal')
                                data-type='text'
                                @endif
                                data-name='valor'
                                data-pk='{{$configuracion->id}}'
                                data-url='{{route('configuraciones.actualizar_informacion_xeditables')}}'
                                data-value='{{$configuracion->valor}}'
                                data-placeholder=''> {{$configuracion->valor }}</a>
                               

                            </td>
                        </tr>
                        @endforeach
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

<script type="text/javascript">
    $(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        const dom = {
            table: $('#tb-roles'),
        };

        var dt = dom.table.DataTable({
            processing: true,
           
            pageLength: 100,
            responsive: true,
            dom: "<'row'<'col-12'f><'col-6'>><'row'<'col-12'tr>><'row'<'col-5'i><'col-7'p>>",
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
            order: false,
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

            // SECCION X EDITABLES

                    $('.editable').on('shown', function(e, editable) {
                        $('.editable-submit').html('<i class="fas fa-check fa-1x"></i>');
                        $('.editable-cancel').html('<i class="fas fa-times"></i>');
                    });

                    // 
                    $('.editable_mostrar_solo_fiscales').editable({
                        emptytext: 'Vacio',
                        onblur: 'ignore',
                        source:[
                            {value: 'No', text: "No"}, 
                            {value: 'Si', text: "Si"}, 
                        ]
                    });

                    // 
                    $('.editable_porcentaje_fiscal').editable({
                        emptytext: 'Vacio',
                        onblur: 'ignore',
                        source:[
                            {value: 'No', text: "No"}, 
                            {value: 'Si', text: "Si"}, 
                        ]
                    });

    })
</script>
@endsection
