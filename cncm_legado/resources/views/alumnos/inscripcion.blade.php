@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Inscribir a otro grupo a  <small>{{ $alumno->fullname }}</small>
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
            <strong> Inscribir a otro grupo a {{ $alumno->nombres }}</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model($alumno, ['route' => ['alumnos.inscribir_a_otro_grupo', $alumno], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')",'id'=>'form-inscribir']) !!}
                    <h5 class="form-header">
                        Para inscribir a {{$alumno->fullname}} a otro grupo llena el siguiente formulario:
                    </h5>

                    @include('alumnos.partials._fields_inscripcion')


                    <div class="form-buttons-w text-right">
                        <button id="inscribir" class="btn btn-success" type="button" ><i class="fa fa-plus"></i> Inscribir</button>
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

            $("#fecha_inicio").datepicker({
                language: 'es',
                format: 'dd-mm-yyyy',
                ignoreReadonly: false,
                todayHighlight: true,
                todayBtn: true,
                autoclose: true,
            });

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

            $('#inscribir').click(function(){
            console.log(dom.grupo.val())
            // console.log($('input[name=forma_pago]:checked').val())

             if(dom.grupo.val() != '' && $('input[name=forma_pago]:checked').val() !== undefined){

                $.post("{{route('grupos.traer_info')}}", { id_grupo: dom.grupo.val(), forma_pago: $('input[name=forma_pago]:checked').val() },
                    function (result) {
                        inscripcion = result.grupo.precio_inscripcion;
                        grupo = result.grupo;
                        swal({
                                title: "Se va a inscribir al alumno al grupo de "+grupo.especialidad.nombre+ " que comienza el día "+moment(grupo.fecha_inicio).format('DD-MM-YYYY')+". Se debe solicitar la primera semana para inscribir.",
                                text: '',
                                type: "success",
                                showCancelButton: true,
                                confirmButtonColor: "#1ee60b",
                                cancelButtonColor: "#999999",
                                confirmButtonText: "Sí, inscribir",
                                cancelButtonText: "Cancelar",
                                showLoaderOnConfirm: true,
                            }).then((result) => {
                                if (result.value) {
                                        dom.form_inscribir.submit();
                                }
                            })


                    },
                    "json"
                );

            }else{

                swal({
                                title: "Tienes que seleccionar Grupo y forma de pago",
                                text: '',
                                type: "error",
                                showCancelButton: true,
                                confirmButtonColor: "#DD6B55",
                                cancelButtonColor: "#999999",
                                cancelButtonText: "Cerrar",
                                showLoaderOnConfirm: true,
                            }).then((result) => {

                            })

            }


        });

        });
    </script>
@endsection
