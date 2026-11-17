<fieldset class="form-group">
    <legend><span>Ingresa la siguiente información</span></legend>
    <input type="hidden" name="id_grupo" value="{{@$grupo->id}}">
    <input type="hidden" name="id_especialidad" value="{{@$especialidad->id}}">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('tipo', 'Tipo:*'); !!}
                {!! Form::select('tipo', [
                    '' => 'Selecciona una opción',
                    'Inscripción' => 'Inscripcion',
                    'Precio Semanal' => 'Precio Semanal',
                    'Precio Mensual' => 'Precio Mensual',
                ],null, ['class' => 'form-control tipo_precio','required' => true]) !!}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('periodo', 'Periodo:*'); !!}
                {!! Form::select('periodo', [
                    '' => 'Selecciona una opción',
                    'Precio actual' => 'Precio actual',
                    'Precio historico' => 'Precio historico',
                ],null, ['class' => 'form-control periodo_precio','required' => true]) !!}
            </div>
        </div>
        <div class="w-100"></div>
        <div class="col-md-12 precio_semanal">
            <div class="form-group">
                {!! Form::label('precio_semanal', 'Precio semanal:*'); !!}
                {!! Form::number('precio_semanal', null, ['class' => 'form-control input_precio_semanal', 'step'=>'0.01']) !!}
            </div>
        </div>
        <div class="col-md-12 precio_mensual">
            <div class="form-group">
                {!! Form::label('precio_mensual_pronto_pago', 'Precio mensual pronto pago:*'); !!}
                {!! Form::number('precio_mensual_pronto_pago', null, ['class' => 'form-control input_precio_pronto_pago', 'step'=>'0.01']) !!}
            </div>
        </div>
        <div class="col-md-12 precio_mensual">
            <div class="form-group">
                {!! Form::label('precio_mensual', 'Precio mensual:*'); !!}
                {!! Form::number('precio_mensual', null, ['class' => 'form-control input_precio_normal', 'step'=>'0.01']) !!}
            </div>
        </div>
        <div class="col-md-12 precio_inscripcion">
            <div class="form-group">
                {!! Form::label('precio_inscripcion', 'Precio inscripción:*'); !!}
                {!! Form::number('precio_inscripcion', null, ['class' => 'form-control input_precio_inscripcion','step'=>'0.01','required'=>true]) !!}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group fecha_inicio">
                {!! Form::label('fecha_inicio', 'Fecha de inicio:*'); !!}
                {!! Form::text('fecha_inicio', null, ['class' => 'form-control datepicker','required'=>true]) !!}
            </div>
            <div class="form-group fecha_final">
                {!! Form::label('fecha_final', 'Fecha final:*'); !!}
                {!! Form::text('fecha_final', null, ['class' => 'form-control datepicker input_fecha_final','data-date-end-date'=>"-2d"]) !!}
            </div>
        </div>


    </div>
</fieldset>


<script>
document.addEventListener("DOMContentLoaded", function() {
    $('.precio_semanal').hide();
    $('.precio_mensual').hide();
    $('.precio_inscripcion').hide();
    
    $('.fecha_final').hide();

    $('.tipo_precio').change(function(){
        if($(this).val() == ''){
            $('.precio_semanal').hide();
            $('.precio_mensual').hide();
            $('.precio_inscripcion').hide();

            $('.input_precio_normal').val('');
            $('.input_precio_pronto_pago').val('');
            $('.input_precio_semanal').val('');
        }

        if($(this).val() == 'Inscripción'){
            $('.precio_semanal').hide();
            $('.precio_mensual').hide();
            $('.precio_inscripcion').show();

            $('.input_precio_normal').val('');
            $('.input_precio_normal').attr('required',false);
            $('.input_precio_pronto_pago').val('');
            $('.input_precio_pronto_pago').attr('required',false);
            $('.input_precio_semanal').val('');
            $('.input_precio_semanal').attr('required',false);
            $('.input_precio_inscripcion').val('');
            $('.input_precio_inscripcion').attr('required',true);
        }

        if($(this).val() == 'Precio Semanal'){
            $('.precio_semanal').show();
            $('.precio_mensual').hide();
            $('.precio_inscripcion').hide();

            $('.input_precio_normal').val('');
            $('.input_precio_normal').attr('required',false);
            $('.input_precio_pronto_pago').val('');
            $('.input_precio_pronto_pago').attr('required',false);
            $('.input_precio_semanal').val('');
            $('.input_precio_semanal').attr('required',true);
            $('.input_precio_inscripcion').val('');
            $('.input_precio_inscripcion').attr('required',false);
        }

        if($(this).val() == 'Precio Mensual'){
            $('.precio_semanal').hide();
            $('.precio_mensual').show();
            $('.precio_inscripcion').hide();

            $('.input_precio_normal').val('');
            $('.input_precio_normal').attr('required',true);
            $('.input_precio_pronto_pago').val('');
            $('.input_precio_pronto_pago').attr('required',true)
            $('.input_precio_semanal').val('');
            $('.input_precio_semanal').attr('required',false)
            $('.input_precio_inscripcion').val('');
            $('.input_precio_inscripcion').attr('required',false)
        }


    })

    $('.periodo_precio').change(function(){
        
        if($(this).val() == ''){
            $('.fecha_final').hide();
            $('.fecha_final').attr('required',false);
            $('.input_fecha_final').val('')
        }

        if($(this).val() == 'Precio actual'){
            $('.fecha_final').hide();
            $('.fecha_final').attr('required',false);
            $('.input_fecha_final').val('')
        }

        if($(this).val() == 'Precio historico'){
            $('.fecha_final').show();
            $('.input_fecha_final').attr('required',true);
        }

    });

});

</script>
