@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Asistencias
@endsection


@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Asistencias</strong>
        </li>
    </ol>
@endsection

@section('contenido')

    <div class="row">
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
            <div class="element-box">

                <div class="row">
                    <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
                        <div class="form-group">
                            {!! Form::label('id_alumno', 'Busca al alumno:*'); !!}
                            {!! Form::select('id_alumno',[], null, ['class' => 'form-control','required' => true,'style' => 'width:100%']) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
            <div class="element-box">
                <h5 class="element-header">
                    Datos del alumno
                </h5>
                <div class="row">
                    <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12 text-center">
                        <input type="hidden" id="input_asistencia" value="">

                      <h4>FOTO:</h4>
                      <img class="foto" src="{{url('archivo/alumnos_foto/no/no')}}" style="width: 50%" alt="">
                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12">
                        <button class="btn btn-danger " id="eliminar_asistencia" style="display:none">Eliminar asistencia</button>
                        @include('alumnos.partials._info_alumno_resumen')
                    </div>

                </div>

            </div>

        </div>

    </div>


@endsection

@section('scripts')
    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>

    <script type="text/javascript">
        $(function() {
            var Helpers = function() {};

            Helpers.prototype.number_format = function(number,decimals) {
                return parseFloat(number).toFixed(decimals).replace(/(\d)(?=(\d{3})+\.)/g, "$1,").toString()
            }

            Helpers = new Helpers();

            const dom = {
                select_alumno: $("#id_alumno"),

            };


            dom.select_alumno.select2({
                language: "es",
                placeholder:'Selecciona un alumnno',
                dropdownParent: dom.modal_asignar_alumno,
                ajax: {
                    method: 'POST',
                    data:function (params) {
                        return {
                            _token: '{{ csrf_token() }}',
                            term: params.term,
                            page: params.page || 1,
                            id_sucursal: "{{ optional(session('sucursal'))->id }}",
                            status:'Alumno'
                        }
                    },
                    url: '{{ route("alumnos.traer_alumnos_select2") }}',
                    dataType: 'json',
                    cache: false,
                    delay:250,
                    beforeSend:function(xhr,type){
                        xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                    }
                },
                escapeMarkup: function (markup) { return markup; },
                minimumInputLength: 2,
                templateResult: function(option){
                    if (option.loading) {
                        return option.text;
                    }

                    if(!option.nuevo_numero_control || !option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `No. Control: ${option.nuevo_numero_control} | Nombre: ${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                },
                templateSelection:function(option){
                    if(!option.nuevo_numero_control ||  !option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `No. Control: ${option.nuevo_numero_control} | Nombre: ${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                }
            });


            dom.select_alumno.on('select2:select', function (e) {

                alumno = e.params.data;

                grupos = alumno.grupos;

                $('.foto').prop('src',alumno.url_foto);
                $('#numero_control').html(alumno.nuevo_numero_control);
                $('#numero_control_ref').html(alumno.numero_control);
                $('#nombre').html(alumno.fullname);
                $('.email').html(alumno.email);
                $('.celular').html(alumno.edad);
                $('.email').html(alumno.domicilio);

                $.each(grupos, function (index, grupo) {
                     txt = '';
                     txt +=`${grupo.id} ${grupo.especialidad.nombre}<br>`;
                     txt +=`${grupo.horario} <br><hr>`;
                });
                $('.grupos').html(txt);

                $.post("{{route('asistencias.registrar_asistencia')}}", {id_alumno: alumno.id},
                    function (result) {
                        toastr.success('Éxito', 'Se registro la asistencia con éxito');
                        $('.asistencia').html('Se registro la asistencia el '+moment().format('DD-MM-YYYY HH:mm:ss'));
                        $('#input_asistencia').val(result.asistencia.id);
                        $('#eliminar_asistencia').show();
                    },
                    "json"
                );




            });


            $('#eliminar_asistencia').click(function(){
                numero_control = $('#numero_control').html();
                nombre = $('#nombre').html();

                swal({
                title: `¿Estas seguro de eliminar esta asistencia de ${numero_control} ${nombre}?`,
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#ff3333",
                cancelButtonColor: "#CDCDCD",
                confirmButtonText: "Borrar",
                cancelButtonText: "Cancelar",
                showLoaderOnConfirm: false,
            }).then(function(result) {
                if (!result.value) {
                    return;
                }

                wait.modal('show');

                $.ajax({
                    url: "{{route('asistencias.eliminar_asistencia')}}",
                    type: 'POST',
                    cache: false,
                    data: {
                        _token: $("meta[name='csrf-token']").attr("content"),
                        id: $('#input_asistencia').val(),
                    },
                    success: function (response){

                            setTimeout(() => {wait.modal('hide'); }, 250);
                            toastr.success('Éxito', 'Se borró con éxito la asistencia');
                            $('.foto').prop('src',"{{url('archivo/alumnos_foto/no/no')}}");
                            $('#numero_control').html('');
                            $('#nombre').html('');
                            $('.email').html('');
                            $('.celular').html('');
                            $('.email').html('');
                            $('.grupos').html('');
                            $('.asistencia').html(' ');
                            $('#input_asistencia').val('');
                            $('#eliminar_asistencia').hide();
                    },
                    error:function(error){
                        setTimeout(() => {
                            wait.modal('hide');
                            toastr.error('Error', 'Ocurrio un error inesperado');
                        }, 250);
                    }
                });
            })
            });



        });
    </script>
@endsection
