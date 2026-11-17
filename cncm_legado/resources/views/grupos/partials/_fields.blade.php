<fieldset class="form-group">
    <legend><span>Informacion del grupo</span></legend>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('clave', 'Clave del grupo:*'); !!}
                {!! Form::text('clave', null, ['class' => 'form-control','required' => true]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('id_especialidad', 'Especialidad:*'); !!}
                {!! Form::select('id_especialidad', $especialidades, null, ['class' => 'form-control','required' => true]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('horario', 'Horario:*'); !!}
                {!! Form::select('horario', config('grupos.horario'), null, ['class' => 'form-control','required' => true]) !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('dias', 'Selecciona los dias:*'); !!}
                @foreach($dias_semana as $dia => $display_dia)
                    @if($grupo->exists)
                        @php
                            $hora_inicio = '';
                            $hora_final = '';
                            $day = $grupo->days->where('dia','=', $dia)->first();
                            if($day){
                                $checked = true;
                                $hora_inicio = $day->hora_inicio;
                                $hora_final = $day->hora_final;
                            }
                            else {
                                $checked = false;
                            }
                        @endphp
                    @else
                        @php
                            $checked = false;
                            $hora_inicio = '';
                            $hora_final = '';
                        @endphp
                    @endif

                    <div class="row border p-2">
                        <div class="col-md-6 align-self-center">
                            <div class="form-check">
                            <label class="form-check-label">
                                {!! Form::checkbox('dia[]', $dia, $checked, ['class'=>'form-control checkbox_dia','data-dia'=>$dia]) !!}
                                {{$display_dia}}
                            </label>
                            </div>


                        </div>
                        <div class="col-md-3 horas horas_{{$dia}}">
                            <label for="">Inicio</label>
                            <input class="form-control" id="inicio_{{$dia}}"  name="inicio_{{$dia}}" type="time" value="{{$hora_inicio}}" >
                        </div>
                        <div class="col-md-3 horas horas_{{$dia}}">
                            <label for="">Fin</label>
                            <input class="form-control" id="fin_{{$dia}}" name="fin_{{$dia}}" type="time" value="{{$hora_final}}">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('fecha_inicio', 'Fecha inicio:*'); !!}
                {!! Form::date('fecha_inicio', null, ['class' => 'form-control', 'placeholder' => 'Escribe la fecha de inicio','required' => true,'min'=>\Carbon\Carbon::today()->subMonths(24)->format('Y-m-d'), 'max'=>\Carbon\Carbon::today()->addMonths(24)->format('Y-m-d') ]); !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('max_alumnos', 'Máximo de alumnos:*'); !!}
                {!! Form::number('max_alumnos', null, ['class' => 'form-control', 'placeholder' => 'Escribe la fecha de inicio','required' => true,'step'=>'1' ]); !!}
                <div class="form-check form-check-inline">
                    <label class="form-check-label">
                        
                        <input class="form-check-input" type="checkbox" name="checkbox_maximo" id="sin_limite_alumnos" value="" @if($grupo) @if(!$grupo->max_alumnos) checked @endif @endif > Sin limite
                    </label>
                </div>
            </div>
        </div>


        @if($grupo->exists)
            <legend class="mt-4"><span>Informacion de precios</span></legend>

            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('precio_semanal', 'Precio semanal:'); !!}
                    {!! Form::number('precio_semanal', null, ['class' => 'form-control','disabled' => true]) !!}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('precio_mensualidad_pronto_pago', 'Pronto Pago:*'); !!}
                    {!! Form::number('precio_mensualidad_pronto_pago', null, ['class' => 'form-control','disabled' => true]) !!}
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('precio_mensualidad', 'Mensualidad:*'); !!}
                    {!! Form::number('precio_mensualidad', null, ['class' => 'form-control','disabled' => true]) !!}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('precio_inscripcion', 'Inscripcion:*'); !!}
                    {!! Form::number('precio_inscripcion', null, ['class' => 'form-control','disabled' => true]) !!}
                </div>
            </div>
        @endif

        <div class="col-md-12">
            <br>
            {!! Form::label('infantil', '¿Grupo Infantil?*'); !!} &nbsp;
            <label>
                {!! Form::checkbox('infantil', 1, null, ['class' => 'i-checks']) !!}
            </label>
        </div>
    </div>
</fieldset>


<script>
document.addEventListener("DOMContentLoaded", function() {
    $('.checkbox_dia').click(function(){
        // alert('algo');
        dia = $(this).data('dia');
        if($(this).is(':checked')){
            $('.horas_'+dia).show();
        }else{
            $('.horas_'+dia).hide();
        }


    })

    @if($grupo->exists)
        checkar_dias()
    @endif
    function checkar_dias(){
        $('.checkbox_dia').each(function(){
                    // alert('algo');
                dia = $(this).data('dia');
                if($(this).is(':checked')){
                    $('.horas_'+dia).show();
                }else{
                    $('.horas_'+dia).hide();
                }
        });
    }

    checkar_maximo_alumnos();

    $('#sin_limite_alumnos').change(function(){
        checkar_maximo_alumnos();
    })

    function checkar_maximo_alumnos(){

        if($('#sin_limite_alumnos').is(':checked')){
            $('#max_alumnos').val(null);
            $('#max_alumnos').attr('readonly',true);
        }else{
            $('#max_alumnos').attr('readonly',false);
        }

    }
});

</script>
