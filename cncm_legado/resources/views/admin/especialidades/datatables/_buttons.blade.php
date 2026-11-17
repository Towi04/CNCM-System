<div class="text-center">
    <div class="btn-group">
        @can('editar_especialidad')
            <a  href="{{ route("admin.especialidades.edit",$id) }}"
                class="btn btn-primary btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                data-action="edit"
                title="Editar">
                <i class="fas fa-pencil-alt    "></i>
            </a>
        @endcan

        @can('consultar_especialidad')
            <a  href="{{ route("admin.especialidades.show",$id) }}"
                class="btn btn-success btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                title="Panel">
                <i class=" fas fa-id-card "></i>
            </a>
        @endcan

        @can('eliminar_especialidad')
            <a class="btn btn-danger btn-sm text-white"
                href="{{ route('admin.especialidades.destroy',$id) }}"
                data-toggle="tooltip"
                data-id="{{ $id }}"
                data-action="delete"
                data-placement="top"
                title="Eliminar">
                <i class="fas fa-trash "></i>
            </a>
        @endcan
    </div>
</div>
