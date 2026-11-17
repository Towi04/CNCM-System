
<fieldset class="form-group">
    <legend><span>Selecciona el grupo al que se va a inscribir</span></legend>
    <div class="row">

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('id_especialidad', 'Especialidad:*'); !!}
                {!! Form::select('id_especialidad',$especialidades, null, ['class' => 'form-control','style' => 'width:100%;']) !!}
            </div>
        </div>

        <div class="col-md-12" id="seccion_grupo" >
            <div class="form-group">
                {!! Form::label('id_grupo', 'Grupo'); !!}
                {!! Form::select('id_grupo',[],null, ['class' => 'form-control','style' => 'width:100%',]) !!}
            </div>
        </div>

        <div class="col-12 pb-3">
            {!! Form::label('fecha_inicio', 'Fecha Inicio:*'); !!}
            {!! Form::text('fecha_inicio', null, ['class' => 'form-control form-control-sm', 'placeholder' => 'Escribe la fecha de inicio','autocomplete' => 'off','required' => true]); !!}
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('forma_pago', 'Forma de pago:*'); !!}
                <br>
                @foreach (config('alumnos.forma_pago') as $key => $value)
                    <label>
                        <input type="radio" name="forma_pago" value="{{ $value }}" class="i-checks" data-grados {{ ($value == old('forma_pago',$alumno->forma_pago))?'checked':'' }}>
                        {{ $value }}
                    </label>
                    &nbsp;
                @endforeach
            </div>
        </div>




    </div>
</fieldset>




<script type="text/javascript">
    window.addEventListener('DOMContentLoaded', (event) => {
        $('.dropify').dropify({
            messages: {
                default: 'Arrastre o pulse para seleccionar imagen',
                replace: 'Arrastre o pulse para reemplazar imagen',
                remove: 'Quitar',
                error: 'Ups, ha ocurrido un error inesperado'
            }
        });

        @can('editar_datos_fiscales')
            $("#solicitud_factura").change(function(e){
                $("#informacion_fiscal").toggle(e.target.checked);
                $("[data-fiscal]").attr('required',e.target.checked)
            })
        @endcan

        $('input[type=radio][data-grados]').on('change', function(e){
            const isOtrosSelected = e.target.value == 'OTROS';

            $("#seccion_otro_grado_estudios").toggle(isOtrosSelected);
            $("#otro_grado_estudios").attr('required',isOtrosSelected)
        });
    });
</script>

