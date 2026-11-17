@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Permisos
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
                <table class="table table-bordered table-striped" style="word-wrap:normal" id="table_permisos">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th >Permisos/Roles</th>
                                @foreach ($roles as $role)
                                    <th class="text-nowrap">{{$role->display_name}}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permisos as $permiso)
                                <tr>
                                    <td class="bg-primary text-white" nowrap>{{$permiso->display_name}}</td>
                                    @foreach ($roles as $role)
                                        <td style="font-size:14px; font-weight:bold" class="toggle_permiso text-center text-danger" id="celda_{{$role->id}}_{{$permiso->id}}" data-role="{{$role->id}}" data-permission="{{$permiso->id}}"></td>
                                    @endforeach
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
            dom: 'Tfgt',
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
            "order": false,
            fixedColumns:   {
                leftColumns: 2,
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

        $('#table_permisos').DataTable(opciones_datatables);

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
