
<fieldset class="form-group">
    
    <div class="row">

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('id_especialidad', 'Especialidad:*'); !!}
                {!! Form::select('id_especialidad', $especialidades, $grupo_origen->id_especialidad, ['class' => 'form-control','style' => 'width:100%;','disabled'=>true]) !!}
            </div>
        </div>

        <div class="col-md-12" id="seccion_grupo" >
            <div class="form-group">
                {!! Form::label('id_grupo', 'Grupo anterior: '); !!}<br>
                {{$grupo_origen->nombre_compuesto}}
            </div>
        </div>
        <legend><span>Selecciona el grupo al que se va a inscribir</span></legend>
        <div class="col-md-12" id="seccion_grupo" >
            <div class="form-group">
                {!! Form::label('id_grupo', 'Grupo'); !!}
                {!! Form::select('id_grupo',[],null, ['class' => 'form-control','style' => 'width:100%','required'=>'required']) !!}
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

