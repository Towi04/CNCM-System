<fieldset class="form-group">
    <legend><span>Informacion de alumno</span></legend>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label for="input-file-now">Foto: </label>
                <input type="file" id="input-file-now" class="dropify" name="foto" value="{{ @$alumno->foto }}" @if ($alumno->foto) data-default-file="{{ url('archivo/alumnos_foto/'.$alumno->id.'/'. $alumno->foto) }}" @endif  @if( ($alumno->exists)? ((empty($alumno->apellido_paterno))?false:true) : false) readonly @endif />
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('nombres', 'Nombres:*'); !!}
                {!! Form::text('nombres', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui los nombres','required'=> true,'autocomplete' => 'off', 'style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' => ($alumno->exists)? ((empty($alumno->nombres))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('apellido_paterno', 'Apellido Paterno:*'); !!}
                {!! Form::text('apellido_paterno', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el apellido paterno','autocomplete' => 'off','required' => true,'style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->apellido_paterno))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('apellido_materno', 'Apellido Materno:*'); !!}
                {!! Form::text('apellido_materno', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el apellido materno','placeholder' => 'Escribe aqui el apellido materno','autocomplete' => 'off','required' => true,'style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->apellido_materno))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('edad', 'Edad:'); !!}
                {!! Form::number('edad', null, ['class' => 'form-control', 'placeholder' => 'Escribe la edad', 'min' => '1', 'max'=>"100",'readonly' =>  ($alumno->exists)? ((empty($alumno->edad))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('fecha_nacimiento', 'Fecha nacimiento:'); !!}
                {!! Form::date('fecha_nacimiento', null, ['class' => 'form-control', 'placeholder' => 'Escribe la fecha de nacimiento','readonly' =>  ($alumno->exists)? ((empty($alumno->fecha_nacimiento))?false:true) : false]); !!}
            </div>
        </div>
    </div>
</fieldset>


<fieldset class="form-group">
    <legend><span>¿Como Supiste de nosotros?</span></legend>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::select('como_supiste_nosotros', config('alumnos.como_conocio'),null, ['class' => 'form-control','placeholder' => 'Selecciona una opción','style' => "text-transform:uppercase",]) !!}
            </div>
        </div>
    </div>
</fieldset>

<fieldset class="form-group">
    <legend><span>Datos de contacto</span></legend>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('domicilio', 'Domicilio:'); !!}
                {!! Form::text('domicilio', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el domicilio','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->domicilio))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('Colonia', 'Colonia:'); !!}
                {!! Form::text('colonia', null, ['class' => 'form-control', 'placeholder' => 'Escribe la colonia','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->colonia))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('Municipio', 'Municipio:'); !!}
                {!! Form::text('municipio', null, ['class' => 'form-control', 'placeholder' => 'Escribe el municipio','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->municipio))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('telefono', 'Telefono:'); !!}
                {!! Form::text('telefono', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el telefono','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->telefono))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('celular', 'Celular:'); !!}
                {!! Form::text('celular', null, ['class' => 'form-control', 'placeholder' => 'Escribe el celular','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->celular))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('email', 'Correo electrónico:'); !!}
                {!! Form::email('email', null, ['class' => 'form-control', 'placeholder' => 'Escribe el correo electronico','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->email))?false:true) : false]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('codigo_postal', 'C.P:'); !!}
                {!! Form::text('codigo_postal', null, ['class' => 'form-control', 'placeholder' => 'Escribe el C.P','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->codigo_postal))?false:true) : false]); !!}
            </div>
        </div>
    </div>
</fieldset>

<fieldset class="form-group">
    <legend><span>Informacion de escolaridad</span></legend>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('ocupacion', 'Ocupación:'); !!}
                {!! Form::text('ocupacion', null, ['class' => 'form-control', 'placeholder' => 'Escribe la ocupación','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->ocupacion))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('grado_estudios', 'Grado Máximo de estudios:*'); !!}
                <br>
                @foreach (config('alumnos.grado_estudios') as $key => $value)
                    <label>
                        <input type="radio" name="grado_estudios[]" value="{{ $value }}" class="i-checks" data-grados {{ in_array($value, old('grado_estudios',$alumno->grado_estudios) ?? [])?'checked':'' }}   {{ ($alumno->exists)? ((empty($alumno->grado_estudios))? '': 'disabled') : '' }}>
                        {{ $value }}
                        @if(($alumno->exists)? ((empty($alumno->grado_estudios))?false:true) : false)
                            @if(in_array($value, old('grado_estudios',$alumno->grado_estudios) ?? []))
                                {!! Form::hidden('grado_estudios[]', $value) !!}
                            @endif
                        @endif
                    </label>
                    &nbsp;
                @endforeach
            </div>
        </div>

        <div class="col-md-12" id="seccion_otro_grado_estudios" style="{{ empty($alumno->otro_grado_estudios)?'display:none':'' }}" >
            <div class="form-group">
                {!! Form::label('otro_grado_estudios', 'Otro Grado de estudios:'); !!}
                {!! Form::text('otro_grado_estudios', null, ['class' => 'form-control', 'placeholder' => 'Escribe otro grado de estudios','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->otro_grado_estudios))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('tutor', 'Padre o Tutor:'); !!}
                {!! Form::text('tutor', null, ['class' => 'form-control', 'placeholder' => 'Escribe el tutor','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->tutor))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('id_especialidad', 'Especialidad:'); !!}
                {!! Form::select('id_especialidad',$especialidades, null, ['class' => 'form-control','style' => 'width:100%;','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->id_especialidad))?false:true) : false]) !!}
            </div>
        </div>

        <div class="col-md-12" id="seccion_otra_especialidad" style="{{ (optional($alumno->especialidad)->nombre == 'OTROS') ?'':'display: none'}}">
            <div class="form-group">
                {!! Form::label('otra_especialidad', 'Otra Especialidad:*'); !!}
                {!! Form::text('otra_especialidad', null, ['class' => 'form-control', 'placeholder' => 'Escribe otra especialidad','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->otra_especialidad))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-12" id="seccion_escuela_procedencia" style="{{ in_array(optional($alumno->especialidad)->nombre,['PREPA ABIERTA','PREPA ESCOLARIZADA'])?'':'display: none' }}">
            <div class="form-group">
                {!! Form::label('escuela_procedencia', 'Escuela de procedencia:*'); !!}
                {!! Form::text('escuela_procedencia', null, ['class' => 'form-control', 'placeholder' => 'Escribe la escuela de procedencia','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->escuela_procedencia))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('objetivo_inscripcion', 'Objetivo inscripcion:'); !!}
                {!! Form::textarea('objetivo_inscripcion', null, ['class' => 'form-control','rows'=> 3,'placeholder' => 'Escribe las observaciones','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->objetivo_inscripcion))?false:true) : false]) !!}
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('enfermedad_cronica', 'Enfermidad Cronica:'); !!}
                {!! Form::text('enfermedad_cronica', null, ['class' => 'form-control','placeholder' => 'Escribe la enfermedad cronica','autocomplete' => 'off','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->enfermedad_cronica))?false:true) : false]) !!}
            </div>
        </div>

        @can('editar_datos_fiscales')
            <div class="col-md-12">
                {!! Form::label('solicitud_factura', 'Solicitud Factura*'); !!} &nbsp;
                <label>
                    {!! Form::checkbox('solicitud_factura', 1, null, ['class' => 'i-checks','disabled' =>  ($alumno->exists)? ((empty($alumno->solicitud_factura))?false:true) : false]) !!}
                    @if(($alumno->exists)? ((empty($alumno->solicitud_factura))?false:true) : false)
                        {!! Form::hidden('solicitud_factura', $alumno->solicitud_factura) !!}
                    @endif
                </label>
            </div>
        @endcan
    </div>
</fieldset>

@can('editar_datos_fiscales')
<fieldset class="form-group" id="informacion_fiscal" style="{{ (old('solicitud_factura',$alumno->solicitud_factura))?'':'display: none;' }}">
    <legend><span>Información fiscal</span></legend>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('rfc', 'RFC:*'); !!}
                {!! Form::text('rfc', null, ['class' => 'form-control','placeholder' => 'Escribe el RFC','autocomplete' => 'off','data-fiscal','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->rfc))?false:true) : false]) !!}
            </div>
        </div>
        {{-- <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('cfdi', 'CFDI:*'); !!}
                {!! Form::select('cfdi', $cfdis,null, ['class' => 'form-control','autocomplete' => 'off','data-fiscal','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->cfdi))?false:true) : false]) !!}
            </div>
        </div> --}}

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('curp', 'Curp:*'); !!}
                {!! Form::text('curp', null, ['class' => 'form-control','placeholder' => 'Escribe el CURP','autocomplete' => 'off','data-fiscal','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->curp))?false:true) : false]) !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('telefono_general', 'Telefono:*'); !!}
                {!! Form::text('telefono_general', null, ['class' => 'form-control','placeholder' => 'Escribe el Telefono General','autocomplete' => 'off','data-fiscal','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->curp))?false:true) : false ]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('razon_social', 'Razon Social:*'); !!}
                {!! Form::text('razon_social', null, ['class' => 'form-control','placeholder' => 'Escribe la Razón Social','autocomplete' => 'off','data-fiscal','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->razon_social))?false:true) : false]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('correo_general', 'Correo:*'); !!}
                {!! Form::email('correo_general', null, ['class' => 'form-control', 'placeholder' => 'Escribe el correo','autocomplete' => 'off','data-fiscal','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->correo_general))?false:true) : false]); !!}
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('domicilio_fiscal', 'Domicilio Fiscal:*'); !!}
                {!! Form::text('domicilio_fiscal', null, ['class' => 'form-control','placeholder' => 'Escribe el domicilio fiscal','data-fiscal','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->domicilio_fiscal))?false:true) : false]) !!}
            </div>
        </div>
    </div>
</fieldset>
@endcan


<fieldset class="form-group">
    <legend><span>Observaciones</span></legend>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::textarea('observaciones', null, ['class' => 'form-control','rows'=> 3,'placeholder' => 'Escribe las observaciones','style' => "text-transform:uppercase",'onkeyup' => 'javascript:this.value=this.value.toUpperCase();','readonly' =>  ($alumno->exists)? ((empty($alumno->observaciones))?false:true) : false]) !!}
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

