<div class="text-center">
    <div class="btn-group">

        @can('editar_producto')
            <a  href="{{ route('admin.productos.edit', $id ) }}"
                class="btn btn-primary btn-sm btn-circle"
                data-toggle="tooltip"
                data-placement="left"
                data-action="edit"
                title="Editar"><i class="fas fa-pencil-alt"></i>
            </a>
        @endcan

        @can('eliminar_producto')
        <a href="{{ route('admin.productos.destroy',$id) }}"
            class="btn btn-danger btn-sm text-white"
            data-toggle="tooltip"
            data-id="{{ $id }}"
            data-action="delete"
            data-placement="right"
            title="Eliminar"><i class="fas fa-trash"></i>
        </a>
        @endcan
        @can('gestionar_compras')
        <a href="{{ route('admin.compras.index',$id) }}"
            class="btn btn-info btn-sm text-white"
            data-toggle="tooltip"
            data-id="{{ $id }}"
            data-action=""
            data-placement="right"
            title="Compras"><i class="fas fa-dollar-sign"></i>
        </a>
        @endcan
    </div>
</div>
