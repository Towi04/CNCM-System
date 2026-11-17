@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
   Cambio de horario de grupo  <small>{{ $alumno->fullname }}</small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('alumnos.index') }}"> Alumnos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>  Cambio de horario de grupo {{ $alumno->nombres }}</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model($alumno, ['route' => ['alumnos.guardar_cambio_horario'], 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <h5 class="form-header">
                        Para realizar cambio de horario de grupo a {{$alumno->fullname}} a otro grupo llena el siguiente formulario:
                    </h5>

                    @include('alumnos.partials._fields_cambio_horario')

                    <input type="hidden" name="id_alumno" value="{{$alumno->id}}">
                    <input type="hidden" name="id_grupo_origen" value="{{$grupo_origen->id}}">
                    <div class="form-buttons-w text-right">
                        <button class="btn btn-success" ><i class="fa fa-plus"></i> Cambiar horario</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

@endsection


@section('scripts')
    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            const dom = {
                especialidad: $("#id_especialidad"),
                grupo: $('#id_grupo'),
                form_inscribir: $('#form-inscribir'),
            }

            dom.especialidad.change(function(e){

                
                if(!e.target.value){
                    dom.grupo.empty().append('<option value="">Selecciona antes una especialidad</option>');
                    return;
                }

                $.ajax({
                    url:'{{ route("grupos.traer_grupos_select2") }}',
                    type: 'POST',
                    cache: false,
                    data: {
                        _token: $("meta[name='csrf-token']").attr("content"),
                        id_especialidad: e.target.value,
                        id_sucursal: "{{ optional(session('sucursal'))->id }}"
                    },
                    success: function (response){
                        const grupos = response.results || [];

                        dom.grupo.empty().append('<option value="">Selecciona un grupo</option>');

                        $.each(grupos, function (index, option) {
                            dom.grupo.append(`<option value="${option.id}">${option.clave} | ${option.fecha_inicio_format} | ${option.dias_corto} | ${option.tipo_grupo}</option>`);
                        });

                    },
                    error:function(error){
                        toastr.error('Error', 'Ocurrio un error inesperado');
                    }
                });
            });

            dom.especialidad.trigger('change');

            
        });
    </script>
@endsection
