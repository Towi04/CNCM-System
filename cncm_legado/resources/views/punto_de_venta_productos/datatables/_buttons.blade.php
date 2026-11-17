<div class="text-center">
    <div class="btn-group">
        
        <a href="{{ route('punto_de_venta_productos.eliminar_partida',$id) }}"
            class="btn btn-danger btn-sm text-white"
            data-toggle="tooltip"
            data-id="{{ $id }}"
            data-action="delete"
            data-placement="right"
            title="Eliminar"><i class="fas fa-trash"></i>
        </a>

    </div>
</div>
