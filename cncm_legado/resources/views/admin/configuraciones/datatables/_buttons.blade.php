<div class="text-center">
    <div class="btn-group">
        <a  href="{{ route("admin.roles.edit",$id) }}"
            class="btn btn-primary btn-sm text-white"
            data-toggle="tooltip"
            data-placement="top"
            data-action="edit"
            title="Editar">
            <i class="fas fa-pencil-alt"></i>            
        </a>

        <a  class="btn btn-danger btn-sm text-white"
            href="{{ route('admin.roles.destroy',$id) }}"
            data-toggle="tooltip"
            data-id="{{ $id }}"
            data-action="delete"
            data-placement="top"
            title="Eliminar">
            <i class="fas fa-trash"></i>            
        </a>
    </div>
</div>
