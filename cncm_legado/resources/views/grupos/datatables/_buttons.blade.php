<div class="text-center">
    <div class="btn-group">
        @can('editar_grupo')
            <a  href="{{ route("grupos.edit",$id) }}"
                class="btn btn-primary btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                data-action="edit"
                title="Editar">
                <i class=" fas fa-pencil-alt"></i>
            </a>
        @endcan

        @can('consultar_grupo')
            <a  href="{{ route("grupos.show",$id) }}"
                class="btn btn-warning btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                title="Panel">
                <i class=" fas fa-id-card"></i>
            </a>
        @endcan

        @can('asignar_materias')
            <a  href="{{ route("grupos.asignar-materias",$id) }}"
                class="btn btn-success btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                data-action="show"
                title="Asignar Materia">
                <i class=" fas fa-book"></i>
            </a>
        @endcan

        @can('asignar_alumnos')
            <a  href="{{ route("grupos.asignar-alumnos",$id) }}"
                class="btn btn-info btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                title="Asignar alumnos">
                <i class=" fas fa-users"></i>
            </a>
        @endcan

        @can('eliminar_grupo')
            <a class="btn btn-danger btn-sm text-white"
                href="{{ route('grupos.destroy',$id) }}"
                data-toggle="tooltip"
                data-id="{{ $id }}"
                data-action="delete"
                data-placement="top"
                title="Eliminar">
                <i class=" fas fa-trash"></i>
            </a>
        @endcan

        @can('consultar_grupo')
        <a  href="{{ route("grupos.cronograma",$id) }}"
            class="btn btn-secondary btn-sm text-white"
            data-toggle="tooltip"
            data-placement="top"
            title="Cronograma">
            <i class=" fas fa-clock"></i>
        </a>

        <a  href="{{ route("grupos.lista-asistencia",[$id, 'no']) }}"
            class="btn btn-dark btn-sm text-white"
            target="_blank"
            rel="noopener"
            data-toggle="tooltip"
            data-placement="top"
            data-action="opciones-lista"
            title="Lista">
            <i class="  fas fa-file-pdf"></i>
        </a>


    @endcan

    @can('finalizar_grupo')
        @if($status == 'Activo')
            <a  data-id="{{$id}}"
                class="btn btn-secondary btn-sm text-white finalizar_grupo"
                data-toggle="tooltip"
                data-placement="top"
                title="Finalizar grupo">
                <i class=" fas fa-ban "></i>
            </a>
        @else 
            <a  data-id="{{$id}}"
                class="btn btn-success btn-sm text-whiteactivar_grupo"
                data-toggle="tooltip"
                data-placement="top"
                title="Activar grupo">
                <i class=" fas fa-check "></i>
            </a>
        @endif
    @endcan
    </div>
</div>
