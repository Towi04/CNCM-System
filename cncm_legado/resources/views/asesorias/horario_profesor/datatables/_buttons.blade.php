<div class="text-center">
    <div class="btn-group">
        <a  href="{{ route("asesorias.horarios-profesores.edit",$id) }}"
            class="btn btn-primary btn-sm text-white"
            data-toggle="tooltip"
            data-placement="top"
            data-action="edit"
            title="Editar">
            <i class=" fas fa-pencil-alt"></i>
        </a>

        <a class="btn btn-danger btn-sm text-white"
            href="{{ route('asesorias.horarios-profesores.destroy',$id) }}"
            data-toggle="tooltip"
            data-id="{{ $id }}"
            data-action="delete"
            data-placement="top"
            title="Eliminar">
            <i class="  fas fa-trash"></i>
        </a>
    </div>
</div>
