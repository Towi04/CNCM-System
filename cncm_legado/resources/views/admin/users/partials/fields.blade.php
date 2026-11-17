<div class="row">
    <div class="col-12 col-lg-7">
        <div class="row">
            <div class="col-12 col-sm-4">
                <div class="form-group">
                    {!! Form::label('nombres', 'Nombres*') !!}
                    {!! Form::text('nombres', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui los nombre(s)']) !!}
                    </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="form-group">
                    {!! Form::label('apellido_paterno', 'Apellido paterno*') !!}
                    {!! Form::text('apellido_paterno', null, ['class' => 'form-control', 'placeholder' =>'Escribe aqui el apellido paterno']) !!}
                    </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="form-group">
                    {!! Form::label('apellido_materno', 'Apellido materno') !!}
                    {!! Form::text('apellido_materno', null, ['class' => 'form-control', 'placeholder' => 'Escribe aquí el apellido materno']) !!}
                    </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="form-group">
                    {!! Form::label('fecha_nacimiento', 'Fecha Nacimiento') !!}
                    {!! Form::text('fecha_nacimiento', null, ['class' => 'form-control datepicker', 'placeholder' => 'Ingresa fecha de nacimiento']) !!}
                </div>
            </div>
            
            <div class="col-12 col-sm-6">

                <div class="form-group">
                    {!! Form::label('celular', 'Celular') !!}
                    {!! Form::text('celular', null, ['class' => 'form-control', 'placeholder' => 'Ingresa el Celular']) !!}
                </div>
            </div>
            <div class="col-12 col-sm-12">
                <div class="form-group">
                    {!! Form::label('email', 'Email*') !!}
                    {!! Form::text('email', null, ['class' => 'form-control', 'placeholder' => 'Ingresa el Correo Electrónico', 'type' => 'email']) !!}
                    <p class="help-block">Ejemplos: minombre@gmail.com</p>
                    </div>
            </div>
        </div>
        

        

       

       



        <div class="form-group">
        {!! Form::label('password', 'Contraseña*') !!}
        {!! Form::password('password', ['class' => 'form-control', 'placeholder' => 'Contraseña', 'id' => 'input_password']) !!}
        </div>

        <!--Password-->
        <div class="form-group" id="div_enviar_datos" @if (@$user) style="display:none" @endif>
            <label> <input type="checkbox" class="i-checks" name="enviar_datos"> Enviar datos por correo </label>
        </div>

        <div class="form-group">
            {!! Form::label('dias', 'Selecciona el horario :*'); !!}
            @foreach($dias_semana as $dia => $display_dia)
                @if($user->exists)
                    @php
                        $hora_inicio = '';
                        $hora_final = '';
                        $day = $user->days->where('dia','=', $dia)->first();
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

    <div class="col-12 col-sm-5 ">
        
            

        <label for="input-file-now">Foto: </label>
        <input type="file" id="input-file-now" class="dropify" name="foto"
            value="{{ @$user->foto }}" @if (isset($user)) data-default-file="{{ url('archivo/usuarios_foto/' . $user->id . '/' . $user->foto) }}" @endif) />

        <div class="element-wrapper pt-4">
            <h3 class="element-header">
                Roles
            </h3>
            <div class="element-box">
                {!! Form::label('role', 'Selecciona los roles del usuario:') !!}
                @foreach ($roles as $role)

                    <br>
                    <label>
                    <?php
                        $algo = false;
                        $user_r = [];

                        if (isset($user)) {
                            $user_r = $user->roles->toArray();
                        }
                    ?>

                        @foreach ($user_r as $user_roles)
                            @if ($user_roles['id'] == $role->id)
                            <?php $algo=true; ?>
                            @endif
                        @endforeach

                        {!! Form::checkbox('role[]', $role->id, $algo, ['class' => 'i-checks']) !!}
                        {{ $role->display_name }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</div>

@can('asignar_varias_sucursales')
    <fieldset class="form-group">
        <legend><span>Sucursales</span></legend>

        @foreach ($sucursales->chunk(2) as $chunk)
            <div class="row">
                @foreach ($chunk as $key => $sucursal)
                    <div class="col-md-6">
                        <label>
                            {!! Form::checkbox('sucursales[]', $sucursal->id,
                                in_array($sucursal->id, $user->sucursales->pluck('id')->toArray() ) ||
                                in_array($sucursal->id, old('sucursales',[]) )
                            , ['class' => 'i-checks']) !!}
                            {{ $sucursal->nombre }}
                        </label>
                    </div>
                @endforeach
            </div>
        @endforeach
    </fieldset>
@endcan


<script type="text/javascript">
    window.addEventListener('DOMContentLoaded', function(event) {
        $('.dropify').dropify({
            messages: {
                default: 'Arrastre o pulse para seleccionar imagen',
                replace: 'Arrastre o pulse para reemplazar imagen',
                remove: 'Quitar',
                error: 'Ups, ha ocurrido un error inesperado'
            }
        });

        

        $('#input_password').keyup(function(){
            if($(this).val()!='')
            $('#div_enviar_datos').show();
            else
            $('#div_enviar_datos').hide();
        });

        $('.checkbox_dia').click(function(){
        // alert('algo');
        dia = $(this).data('dia');
        if($(this).is(':checked')){
            $('.horas_'+dia).show();
        }else{
            $('.horas_'+dia).hide();
        }


    })

    @if($user->exists)
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


    });
</script>

