@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Alumnos <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('pre-registro-alumnos.index') }}">Pre-registro Alumnos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Inscribir alumno {{ $alumno->nombres }}</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model($alumno, ['route' => ['pre-registro-alumnos.inscribir', $alumno], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','id'=>'form-inscribir']) !!}
                    <h5 class="form-header">
                        Llena el formulario
                    </h5>

                    @include('alumnos.pre_registro.partials._fields_inscripcion')

                    <div class="form-buttons-w text-right">
                        <button id="inscribir" class="btn btn-success" type="submit" ><i class="fa fa-plus"></i> Inscribir</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

   @include('alumnos.pre_registro.modals.inscripcion')
   @include('alumnos.pre_registro.modals.ticket')
@endsection


@section('scripts')
<script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>
<script type="text/javascript">
    $(document).ready(function() {

        var dom = {
            especialidad: $("#id_especialidad"),
            grupo: $('#id_grupo'),
            form_inscribir: $('#form-inscribir'),

            select_alumno: $("#id_alumno_recomendo"),

            modal_inscripcion: $("#modal-inscripcion"),
            form_inscripcion: $("#form-inscripcion"),
            btn_inscribir: $("#inscribir"),

            ckb_apoyo_especial: $('#ckb-apoyo-especial'),
            ckb_apoyo_recomendacion: $('#ckb-apoyo-recomendacion'),

            tikets:{
                contenido_ticket:$("#contenido-ticket"),
                modal: $("#modal-ticket"),
            },
        }

        dom.especialidad.select2();

        var CONFIG_DATEPICKER = {
            days: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
            daysShort: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
            daysMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
            months: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
            monthsShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
            today: "Hoy",
            monthsTitle: "Meses",
            clear: "Borrar",
            weekStart: 1,
        }

        $.fn.datepicker.dates['es'] = CONFIG_DATEPICKER  //👉 DATEPICKER

        dom.form_inscribir.find('[name="fecha_nacimiento"]').datepicker({
            language: 'es',
            format: 'dd-mm-yyyy',
            ignoreReadonly: false,
            todayHighlight: true,
            todayBtn: true,
            autoclose: true,
        });

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

                    const formas_pago = response.formas_pago;

                        $('.formas_pago').empty();
                        console.log(formas_pago);
                        $.each(formas_pago, function (index, forma_pago) { 
                            line = '';
                            line += `<label>`
                            line += `    <input type="radio" name="forma_pago" value="${forma_pago}" class="i-checks" >`
                            line += `    ${forma_pago}`
                            line += `</label>`
                            line += `&nbsp;`
                                console.log(line);
                             $('.formas_pago').append(line);
                        });

                    dom.grupo.empty().append('<option value="">Selecciona un grupo</option>');

                    $.each(grupos, function (index, option) {
                        dom.grupo.append(`<option value="${option.id}">${option.clave || ''} | ${option.horario || ''}| ${option.dias || ''} | ${option.fecha_inicio_format || ''} | ${option.tipo_grupo || ''}</option>`);
                    });

                    dom.grupo.val("{{ optional($alumno->grupos->first())->id }}");
                },
                error:function(error){
                    toastr.error('Error', 'Ocurrio un error inesperado');
                }
            });
        });

        dom.especialidad.trigger('change')

        dom.form_inscribir.submit(function(e){
            e.preventDefault();

            if(!$('input[name="grado_estudios[]"]:checked').length){
                swal({
                    title: "Debes seleccionar un grado de estudios",
                    text: '',
                    type: "error",
                    showCancelButton: true,
                    confirmButtonColor: "#DD6B55",
                    cancelButtonColor: "#999999",
                    cancelButtonText: "Cerrar",
                });

                return;
            }


            const forma_pago = $('input[name=forma_pago]:checked').val();

             if(dom.grupo.val() != '' && forma_pago !== undefined){

                $.post("{{route('grupos.traer_info')}}", {id_alumno:{{$alumno->id}}, id_grupo: dom.grupo.val(), forma_pago: $('input[name=forma_pago]:checked').val() },
                    function (result) {
                        inscripcion = result.grupo.precio_inscripcion;
                        grupo = result.grupo;
                        alumno = result.alumno;
                        saldo = alumno.saldo;
                        diferencia = inscripcion - saldo;
                        cupo = result.cupo;

                        if(saldo > 0){
                            txt = "Se va a inscribir al alumno al grupo de "+grupo.especialidad.nombre+ " que comienza el día "+moment(grupo.fecha_inicio).format('DD-MM-YYYY')+". El alumno ya tiene un apartado por "+saldo+" por lo que solo tienes que solicitar la diferencia  de $ "+diferencia+" que quedará registrada como pagada en la ficha del alumno. Precio original de la inscripción: $ "+inscripcion;
                        }else{
                            txt = "Se va a inscribir al alumno al grupo de "+grupo.especialidad.nombre+ " que comienza el día "+moment(grupo.fecha_inicio).format('DD-MM-YYYY')+". Tienes que solicitar la inscripción de $ "+inscripcion+" que quedará registrada como pagada en la ficha del alumno."
                        }

                        if(cupo <= 0){
                            $("#apoyo-autorizacion").show();
                            $("#cupo").val(0);
                            $('.msje_cupo').html(`El grupo ya esta lleno ( ${grupo.max_alumnos - cupo} de ${grupo.max_alumnos}). Para inscribir al alumno se requiere autorización.`);
                            $("[data-usuario]").attr('required',true)
                            $("[data-password]").attr('required',true)
                        }else{
                            $("#apoyo-autorizacion").hide();
                            $("#cupo").val(cupo);
                            $('.msje_cupo').html(``);
                            $("[data-usuario]").attr('required',false)
                            $("[data-password]").attr('required',false)
                        }

                        dom.modal_inscripcion.find('#inscripcion-detalle').text(txt);
                        dom.modal_inscripcion.find('#precio_inscripcion').val(inscripcion);
                        // dom.modal_inscripcion.find('#precio_inscripcion').attr("max",inscripcion);
                        dom.modal_inscripcion.modal('show');
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
                })
            }
        });

        dom.form_inscripcion.submit(function(e){
            e.preventDefault();

            dom.modal_inscripcion.modal('hide');
            wait.modal('show');

            var formData = new FormData(dom.form_inscribir[0]);
            formData.append('tipo_pago',$("#tipo_pago").val());
            formData.append('fecha_inicio',$("#fecha_inicio").val());

            // 👉 EVITAR ENVIAR EL CAMPO EN CASO DE QUE NO TENGA PERMISO PARA EMITIR PRECIO INSCRIPCION
            if ($("#precio_inscripcion").val()) {
                formData.append('apoyo_especial',$("#ckb-apoyo-especial").is(':checked'));
                formData.append('apoyo_por_recomendacion',$("#ckb-apoyo-recomendacion").is(':checked'));
                formData.append('precio_inscripcion',$("#precio_inscripcion").val());
                formData.append('id_alumno_recomendo',$("#id_alumno_recomendo").val());
                formData.append('id_usuario_autoriza',$("#id_usuario_autoriza").val());
                formData.append('password',$("#password").val());
                formData.append('motivo',$("#motivo").val());
            }

            formData.append('cupo',$('#cupo').val());

            if($('#cupo').val() <= 0){
                formData.append('id_usuario_autoriza',$("#id_usuario_autoriza").val());
                formData.append('password',$("#password").val());
                
            }

            $.ajax({
                url: dom.form_inscribir.attr('action'),
                type: 'POST',
                contentType: false,
                processData: false,
                data: formData,
                success: function (response){
                    setTimeout(() => {
                        wait.modal('hide');

                        var pago = response.pago;

                        if(pago) {
                            var route = "{{ route('punto_de_venta.ticket','_pago') }}".replace('_pago',pago.id);

                            dom.tikets.modal.data('redirect',response.redirect)
                            dom.tikets.modal.modal('show');
                            dom.tikets.contenido_ticket.html();
                            dom.tikets.contenido_ticket.html(`<iframe scrolling='auto' type='text/html' scroll='auto' src='${route}' width='100%' height='450px' align='center'></iframe>`);
                        }else{
                            // window.location.href = response.redirect;
                        }

                    }, 250);
                },
                error:function(error){
                    // console.log(error.responseJSON.errors.mensaje);
                    if(error.responseJSON){
                        errors = error.responseJSON.errors.mensaje;
                        msje = '';
                        $.each(errors, function (index, error) { 
                            msje +=''+error;
                        });

                        setTimeout(() => {
                            wait.modal('hide');
                            toastr.error('Error', msje);
                        }, 250);

                    }else{
                        setTimeout(() => {
                        wait.modal('hide');
                        toastr.error('Error', 'Ocurrio un error inesperado aldo');
                    }, 250);
                    }
                    
                }
            });
        });

        dom.tikets.modal.on("hidden.bs.modal", function () {
            var redirect = dom.tikets.modal.data('redirect');
            window.location.href = redirect;
        });

        dom.ckb_apoyo_especial.change(function(e){
            $("#apoyo-especial").toggle(e.target.checked);
            if(e.target.checked && $('#cupo').val() > 0){
                $("#apoyo-autorizacion").toggle(e.target.checked);
                $("[data-password]").attr('required',e.target.checked)
                $("[data-usuario]").attr('required',e.target.checked)
            }
            $("#apoyo-motivo").toggle(e.target.checked);
            $("[data-apoyo]").attr('required',e.target.checked)
           
            $("[data-motivo]").attr('required',e.target.checked)

            if(!e.target.checked){

            }
        })

        dom.ckb_apoyo_recomendacion.change(function(e){
            $("#apoyo-recomendacion").toggle(e.target.checked);
            
            if(e.target.checked && $('#cupo').val() > 0){
                $("#apoyo-autorizacion").toggle(e.target.checked);
                $("[data-usuario]").attr('required',e.target.checked)
                $("[data-password]").attr('required',e.target.checked)
            }

            $("[data-alumno]").attr('required',e.target.checked)
           

            if(!e.target.checked){

            }
        })


        dom.select_alumno.select2({
                dropdownParent: $('#modal-inscripcion'),
                language: "es",
                placeholder:'Selecciona un alumnno',
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

    });

</script>
@endsection
