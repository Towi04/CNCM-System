<!DOCTYPE html>
<html>
  <head>
    <title>Lista de asistencia</title>
    <meta charset="utf-8">
    <meta content="ie=edge" http-equiv="x-ua-compatible">
    <meta content="{{ config('settings.company.autor') }}" name="author">
    <meta content="Lista de asistencia" name="description">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="{{asset('img/logo.png')}}" rel="shortcut icon">
    <link href="apple-touch-icon.png" rel="apple-touch-icon">
    <style>
        @page {
            margin: 0cm 0cm;
            size: letter landscape;
        }

        body {
            margin: 2.75cm 1cm 4cm 1cm;
            line-height: .5cm;
            font-family: "Gill Sans, sans-serif" !important;
            font-size: 10pt;
            background-color: white
        }

        table {
            border-collapse: collapse;
        }

        /*👉 ESTILOS BOOSTRAP  */
        .row {
            display: -ms-flexbox;
            display: flex;
            -ms-flex-wrap: wrap;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }

        .page-break {
            page-break-after: always;
        }

        .header {
            position: fixed;
            top: 0.2cm;
            left: 0cm;
            right: 0cm;
            height: 2.2cm;
            text-align: center;
            line-height: 30px;
        }

        .footer {
            position: fixed;
            bottom: 0cm;
            left: 0cm;
            right: 0cm;
            height: 1.5cm;
            color: white;
            text-align: center;
            line-height: 30px;
            padding: 0px !important;
        }

        .table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #636e72;
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #636e72;
        }

        .table tbody + tbody {
            border-top: 2px solid #636e72;
        }

        .table .table {
            background-color: #fff;
        }
       .table thead > tr > th{
           margin: 0;
           padding: 0.10rem;
           font-size: 0.5rem;
        }

       .table tbody > tr > td{
            margin: 0;
            padding: 0.10rem;
            color: black;
            font-size: 0.5rem;
        }

        .table-bordered {
            border: 1px solid #636e72;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #636e72;
        }

        .titulo-principal{
            font-size: 1.2rem;
        }

        .font-weight-bold {
            font-weight: 700 !important;
        }

        .text-dark {
            color: #343a40 !important;
        }

        .text-uppercase {
            text-transform: uppercase !important;
        }

        .text-primary {
            color: #182948 !important;
        }

        .text-white {
            color: #fff !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-nowrap {
            white-space: nowrap !important;
        }

        .bg-primary {
            background-color: #182948 !important;
        }

    </style>
    </head>
    <body>
        <div class="header">
            <div class="row pt-2 d-flex">
                <div class="col-12 text-center">
                    <span class="text-uppercase titulo-principal text-primary">GRUPO EDUCATIVO CNCM</span> <br>
                    <span class="text-dark">{{ $sucursal->direccion }} , {{ $sucursal->municipio }} {{ $sucursal->estado }} </span> <br>
                    <span class="text-dark">Lista de asistencia</span>
                </div>
            </div>
        </div>
        <div class="footer">
        </div>

        <main style="overflow:hidden">
            <table style="width: 100%; padding-bottom:0.5rem;">
                <tr class="text-dark">
                    <td><span class="font-weight-bold">Horario:</span>  {{ $grupo->horario }}</td>
                    <td class="text-right"> <span class="font-weight-bold">Especialidad:</span> {{ $grupo->especialidad->nombre }}</td>
                </tr>
                <tr class="text-dark">
                    <td> <span class="font-weight-bold">Grupo:</span> {{ $grupo->clave }} </td>
                    <td class="text-right"> <span class="font-weight-bold">Profesor:</span>
                        @if(isset($profesor->id))
                            <span>{{$profesor->fullname}}</span>
                        @else 
                            <span>________________________</span>
                        @endif
                    </td>
                </tr>
                <tr class="text-dark">
                    <td> <span class="font-weight-bold">Materia:</span>
                        @if(isset($materia->nombre))
                         {{ $materia->nombre }} 
                        @else 
                        <span>________________________</span>
                        @endif
                        </td>
                    <td class="text-right"> <span class="font-weight-bold"></td>
                </tr>
            </table>

            <table class="table table-bordered table-condensed">
                <thead>
                    <tr>
                        <th colspan="4" class="text-right text-dark">Fase</th>
                        @foreach ($semanas as $semana)
                            <th colspan="{{ count($dias) }}"></th>
                        @endforeach
                    </tr>
                    <tr>
                        <th colspan="4" class="text-right text-dark">Semana</th>
                        @foreach ($semanas as $semana)
                            <th class="text-center text-dark" colspan="{{ count($dias) }}" >{{ $semana }}</th>
                        @endforeach
                    </tr>
                    <tr class="bg-primary text-white">
                        <th>N°</th>
                        <th>Nombre </th>
                        <th class="text-nowrap">N° Ctrl</th>
                        @if($mostrar_telefono)
                            <th>Tel</th>
                        @endif
                        @foreach ($semanas as $semana)
                            @foreach ($dias as $dia)
                                <th>{{ $array_dias[$dia] }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupo->alumnos->where('pivot.status','Inscrito') as $alumno)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="text-nowrap">{{ $alumno->fullname }} </td>
                            <td class="text-nowrap">{{ $alumno->nuevo_numero_control }}</td>
                            @if($mostrar_telefono)
                                <td class="text-nowrap">{{ $alumno->telefono }}</td>
                            @endif
                            @foreach ($semanas as $semana)
                                @foreach ($dias as $item)
                                    <td></td>
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                    @for ($i = $grupo->alumnos->count()+1; $i < $grupo->alumnos->count()+6; $i++)
                    <tr>
                        <td>{{ $i }}</td>
                        <td class="text-nowrap"></td>
                        <td class="text-nowrap"></td>
                        @if($mostrar_telefono)
                            <td class="text-nowrap"></td>
                        @endif
                        @foreach ($semanas as $semana)
                            @foreach ($dias as $item)
                                <td></td>
                            @endforeach
                        @endforeach
                    </tr>
                    @endfor
                    
                    
                </tbody>
            </table>

            <div>
                <span class="text-dark">Observaciones:</span> <span style="display: inline-block; border-bottom: 1px solid ;width:90%;"></span>
            </div>
            <div style="margin-top: 0.4rem;">
                ________________________________________________________________________________________________________________________________________________________________________________________________________________
            </div>
            <div style="margin-top: 0.4rem;">
                ________________________________________________________________________________________________________________________________________________________________________________________________________________
            </div>


        </main>
</body>
</html>
