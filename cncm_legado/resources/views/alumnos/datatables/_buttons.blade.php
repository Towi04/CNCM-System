<div class="text-center">
    <div class="btn-group">
        @canany(['editar_datos_fiscales','editar_alumno'])
            <a  href="{{ route("alumnos.edit",$id) }}"
                class="btn btn-primary btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                data-action="edit"
                title="Editar">
                <i class=" fas fa-pencil-alt"></i>
            </a>
        @endcanany

        @can('consultar_alumno')
            <a  href="{{ route("alumnos.show",$id) }}"
                class="btn btn-success btn-sm text-white"
                data-toggle="tooltip"
                data-placement="top"
                data-action="show"
                title="Ver">
                <i class=" fas fa-id-card"></i>
            </a>
        @endcan

        @can('eliminar_alumno')
            <a class="btn btn-danger btn-sm text-white"
                href="{{ route('alumnos.destroy',$id) }}"
                data-toggle="tooltip"
                data-id="{{ $id }}"
                data-action="delete"
                data-placement="top"
                title="Eliminar">
                <i class=" fas fa-trash"></i>
            </a>
        @endcan

        @can('inscribir_a_otros_grupos')
       
            <a class="btn btn-info btn-sm text-white "
                href="{{ route('alumnos.formulario_inscribir_otro_grupo',$id) }}"
                data-toggle="tooltip"
                data-id="{{ $id }}"
                data-placement="top"
                title="Inscribir a otro grupo">
                <i class="fas fa-file-contract"></i>
            </a>
        @endcan
    </div>
</div>
