<div class="text-center">
    <div class="btn-group">
        @can('convertir_pre_registro_alumno')
            <a  href="{{ route("pre-registro-alumnos.formulario-inscripcion",$id) }}"
                class="btn btn-success btn-sm text-white "
                title="Inscribir">
                <i class="fas fa-check"></i>
            </a>
        @endcan

       @if(Auth::id() == $id_asesor_educativo)
            <a  href="{{ route("pre-registro-alumnos.edit",$id) }}"
                class="btn btn-primary btn-sm text-white "
                title="Editar">
                <i class="fas fa-edit"></i>
            </a>
        @endif

        @canany(['convertir_pre_registro_alumno','realizar_pre_registro'])
            <a class="btn btn-danger btn-sm text-white "
                href="{{ route('pre-registro-alumnos.destroy',$id) }}"
                data-toggle="tooltip"
                data-id="{{ $id }}"
                data-action="delete"
                data-placement="top"
                title="Eliminar">
                <i class="fas fa-trash"></i>
            </a>
        @endcanany
    </div>
</div>
