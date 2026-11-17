<table class="table table-bordered">
    <tr>
        <td colspan="2">
            <div class="user-with-avatar">
                <img class="foto" alt="" src="{{ $alumno->url_foto }}"><span>{{ $alumno->nombres }}</span>
            </div>
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white nombre"><b>Nombre:</b></td>
        <td>
            {{ $alumno->fullname }}
        </td>
    </tr>
    <tr>
        <td class="bg-primary text-white email"><b>Email:</b></td>
        <td>
            {{ $alumno->email }}
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white edad"><b>Edad:</b></td>
        <td>
           {{ $alumno->edad }}
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white fecha_nacimiento"><b>Fecha de nacimiento:</b></td>
        <td>
           {{ optional($alumno->fecha_nacimiento)->format('d/m/Y') }}
        </td>
    </tr>
    <tr>
        <td colspan="2">
            DATOS DE CONTACTO
        </td>
    </tr>
    <tr>
        <td class="bg-primary text-white domicilio">Domicilio</td>
        <td>{{ $alumno->domicilio }}</td>
    </tr>
    <tr>
        <td class="bg-primary text-white colonia">Colonia</td>
        <td>{{ $alumno->colonia}}</td>
    </tr>
    <tr>
        <td class="bg-primary text-white municipio">Municipio</td>
        <td>{{ $alumno->municipio }}</td>
    </tr>
    <tr>
        <td class="bg-primary text-white telefono">Teléfono</td>
        <td>{{ $alumno->telefono }}</td>
    </tr>
    <tr>
        <td class="bg-primary text-white celular">Celular</td>
        <td>{{ $alumno->celular }}</td>
    </tr>
    <tr>
        <td class="bg-primary text-white email">Correo Electrónico</td>
        <td>{{ $alumno->email }}</td>
    </tr>
    <tr>
        <td class="bg-primary text-white codio_postal">Codigo Postal</td>
        <td>{{ $alumno->codigo_postal }}</td>
    </tr>

    <tr>
        <td class="bg-primary text-white"><b>Asesor Educativo:</b></td>
        <td>
            {{ $alumno->asesor_educativo->full_name }}
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white"><b>Observaciones:</b></td>
        <td>
            {{ $alumno->observaciones }}
        </td>
    </tr>

    <tr>
        <td colspan="2"> Información de escolaridad</td>
    </tr>
    <tr>
        <td class="bg-primary text-white"><b>Ocupacion:</b></td>
        <td>
            {{ $alumno->ocupacion }}
        </td>
    </tr>
    <tr>
        <td class="bg-primary text-white"><b>Grado Máximo de Estudios:</b></td>
        <td>
            {{ collect($alumno->grado_estudios)->implode(',') }}
        </td>
    </tr>
    @if($alumno->otro_grado_estudios)
    <tr>
        <td class="bg-primary text-white"><b>Otro grado de estudios:</b></td>
        <td>
            {{ $alumno->otro_grado_estudios }}
        </td>
    </tr>
    @endif

    <tr>
        <td class="bg-primary text-white"><b>Padre o Tutor:</b></td>
        <td>
            {{ $alumno->tutor }}
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white"><b>Especialidad:</b></td>
        <td>
            {{ optional($alumno->especialidad)->nombre }}
        </td>
    </tr>

    @if(!empty($alumno->escuela_procedencia))
    <tr>
        <td class="bg-primary text-white"><b>Escuela Procedencia:</b></td>
        <td>
            {{ $alumno->escuela_procedencia }}
        </td>
    </tr>
    @endif

    <tr>
        <td class="bg-primary text-white"><b>Foma de pago:</b></td>
        <td>
            {{ $alumno->forma_pago }}
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white"><b>Objetivo Inscripcion:</b></td>
        <td>
            {{ $alumno->objetivo_inscripcion }}
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white"><b>Como supiste de nosotros:</b></td>
        <td>
            {{ $alumno->como_supiste_nosotros }}
        </td>
    </tr>

    <tr>
        <td class="bg-primary text-white"><b>Enfermedad Crónica:</b></td>
        <td>
            {{ $alumno->enfermedad_cronica }}
        </td>
    </tr>

    @if($alumno->solicitud_factura)
        <tr>
            <td colspan="2">
                INFORMACION FISCAL
            </td>
        </tr>
        <tr>
            <td class="bg-primary text-white"><b>RFC:</b></td>
            <td>
                {{ $alumno->rfc }}
            </td>
        </tr>
        <tr>
            <td class="bg-primary text-white"><b>CFDI:</b></td>
            <td>
                {{ $alumno->cfdi }}
            </td>
        </tr>
        <tr>
            <td class="bg-primary text-white"><b>CURP:</b></td>
            <td>
                {{ $alumno->curp }}
            </td>
        </tr>
        <tr>
            <td class="bg-primary text-white"><b>Telefono:</b></td>
            <td>
                {{ $alumno->telefono_general }}
            </td>
        </tr>

        <tr>
            <td class="bg-primary text-white"><b>Razon Social:</b></td>
            <td>
                {{ $alumno->razon_social }}
            </td>
        </tr>

        <tr>
            <td class="bg-primary text-white"><b>Correo:</b></td>
            <td>
                {{ $alumno->correo_general }}
            </td>
        </tr>

        <tr>
            <td class="bg-primary text-white"><b>Domicilio Fiscal:</b></td>
            <td>
                {{ $alumno->domicilio_fiscal }}
            </td>
        </tr>

    @endif


</table>
