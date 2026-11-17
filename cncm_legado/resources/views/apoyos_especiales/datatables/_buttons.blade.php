<div class="text-center">
    <div class="btn-group">
        {{-- @can('eliminar_alumno') --}}
            <a class="btn btn-danger btn-sm text-white"
                href="{{ route('apoyos-especiales.destroy',$id) }}"
                data-toggle="tooltip"
                data-id="{{ $id }}"
                data-action="delete"
                data-placement="top"
                title="Eliminar">
                <i class=" fas fa-trash"></i>
            </a>
        {{-- @endcan --}}

    </div>
</div>
