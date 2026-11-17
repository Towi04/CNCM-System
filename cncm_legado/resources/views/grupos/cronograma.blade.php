@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    CRONOGRAMA GRUPO: {!!$grupo->nombre_compuesto!!}
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a>Permisos</a>
        </li>
    </ol>
@endsection

@section('contenido')
    <style>
        .contact-box:hover{
            transform: scale(1.05)
        }
        .table-responsive {
            overflow-x: auto !important;
        }

        .content-w {
            overflow: hidden !important;
        }
        td:hover{
            background-color: #abd0ea;
            cursor:pointer;
        }

        .table-responsive>.fixed-column {
            position: absolute;
            display: inline-block;
            width: auto;
            border-right: 1px solid #ddd;
        }
        @media(min-width:768px) {
            .table-responsive>.fixed-column {
                display: none;
            }
        }
        table.dataTable {
            clear: both;
            margin-top: 0px !important;
            margin-bottom: 0px !important;
        }
    </style>

    <div class="row ">
        <div class="col-lg-12 col-md-12">
            <div class="pt-2 ">
                <table class="table table-striped table-bordered table-hover"  id="table_cronograma" >
                    <thead>
                        <tr>
                            <th class="bg-primary text-white ">Orden</th>
                            <th class="bg-primary text-white ">Materia</th>
                            @php 
                                $fecha_inicio = $grupo->fecha_inicio->startOfWeek();
                            @endphp
                            @foreach($grupo->materias as $materia)
                                @for($i =1; $i <=$materia->semanas; $i++ )
                                    <th class="bg-primary text-white"> <a data-toggle="tooltip" title="{{$fecha_inicio->format('d-m-Y')}} - {{$fecha_inicio->endOfWeek()->format('d-m-Y')}}" >{{$fecha_inicio->weekOfYear}}</a></th>
                                    @php 
                                        $fecha_inicio->addDay();
                                    @endphp
                                @endfor
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                            @foreach($grupo->materias as $materia)
                            <tr>
                                <td class="bg-primary text-white">
                                    {{$materia->orden}}
                                </td>
                                <td class="bg-primary text-white">
                                    {{$materia->nombre}}
                                </td>
                                {{-- Materias antes --}}
                                
                                @for($i =1; $i <=$grupo->materias->where('orden','<',$materia->orden)->sum('semanas'); $i++ )
                                    <td> - </td>
                                @endfor
                                 {{-- Semanas de la materia --}}
                                @for($i =1; $i <=$materia->semanas; $i++ )
                                    <td class="bg-primary text-primary" > OK </td>
                                @endfor
                                 {{-- Materias despues --}}
                                 @for($i =1; $i <=$grupo->materias->where('orden','>',$materia->orden)->sum('semanas'); $i++ )
                                 <td> - </td>
                                @endfor
                            </tr>
                            @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.datatables.net/fixedcolumns/3.3.0/js/dataTables.fixedColumns.min.js" crossorigin="anonymous"></script>
<script>
    $(function(){
        var groupColumn = 1;

        var opciones_datatables={
            pageLength: 25,
            responsive: true,
            dom: 'BTfgt',
            buttons: [
                {extend: 'excel', title: 'Permisos'},
            ],

            "drawCallback": function ( settings ) {
                var api = this.api();
                var rows = api.rows( {page:'current'} ).nodes();
                var last=null;
            },
            "language": {
                "lengthMenu": "Mostrar _MENU_ registros por pagina",
                "zeroRecords": "No se encontro ningún registro",
            "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                "infoEmpty": "No hay registros disponibles",
                "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                "search": "Buscar:",
                "paginate": {
                    "first":      "Primera",
                    "last":       "Última",
                    "next":       "Siguiente",
                    "previous":   "Anterior"
                },
            "loadingRecords": "Cargando...",
                "processing":     "Procesando...",
            },
            "order": [[0,'asc']],
            fixedColumns:   {
                leftColumns: 1,
                // rightColumns: 1
            },
            scrollCollapse: true,
            paging:         false,
            scrollX:        true,
            scrollY:        "500px",
        }

        function traer_permisos() {
            $('.toggle_permiso').html('')
            $.post('{{ route("admin.permisos.traer-permisos") }}',{}, function(result){
                roles = result.roles;

                $.each(roles, function(index, rol){
                    const permisos = rol.permissions;

                    $.each(permisos, function(index, permiso){
                        $('#celda_'+rol.id+'_'+permiso.id).html('<i>X</i>');
                    })
                })
            })
        }

        $('#table_cronograma').DataTable(opciones_datatables);

        $('.toggle_permiso').click(function(){
            id_role = $(this).data('role');
            id_permission = $(this).data('permission');

            $.post('{{ route("admin.permisos.guardar-permiso") }}', {id_role: id_role, id_permission: id_permission }, function(result){
                traer_permisos();
            })
        });

        $(document).ready(function () {
            traer_permisos();
        });
    });

</script>

@endsection
