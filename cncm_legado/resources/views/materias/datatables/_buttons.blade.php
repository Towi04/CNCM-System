<div class="text-center">
    <div class="btn-group">
        @can('editar_materia')
            <a  href="{{ route("materias.edit",$id) }}"
                class="btn btn-primary btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                data-action="edit"
                title="Editar">
                <i class="fas fa-pencil-alt    "></i>
            </a>
        @endcan

        @can('eliminar_materia')
            <a class="btn btn-danger btn-sm text-white "
                href="{{ route('materias.destroy',$id) }}"
                data-toggle="tooltip"
                data-id="{{ $id }}"
                data-action="delete"
                data-placement="top"
                title="Eliminar">
                <i class="fas fa-trash"></i>
            </a>
        @endcan
    </div>
</div>
