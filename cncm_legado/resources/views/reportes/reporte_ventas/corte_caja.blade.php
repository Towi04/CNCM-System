<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="ie=edge" http-equiv="x-ua-compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="{{ config('settings.company.autor') }}" name="author">
    <meta content="CORTE DE CAJA" name="description">
    <title>CORTE DE CAJA</title>

    @include('reportes.reporte_ventas.partials._styles_corte_caja')
</head>
<body>

<header class="header">
    <div class="row pt-2 d-flex">
        <div>
            <img class="logo-corporativo" src="{{ imgToBase64(public_path('img/logo.png')) }}" width="100px">
            <h1 class="text-uppercase titulo-principal text-primary">CORTE DE CAJA</h1>
            <h3 class="text-uppercase text-center text-primary">{{ $titulo }}</h3>
            <p class="text-uppercase text-center text-primary"> <span class="font-weight-bold">Impreso por:</span> {{ $autor }}<br><br><small>el {{date('d-m-Y H:i a')}}</small></p>

        </div>
    </div>
</header>

<main class="contenido" >
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Fecha Abono</th>
                <th>No control</th>
                <th>Alumno</th>
                <th>Concepto</th>
                <th>Recibido por</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pagos as $pago)
                <tr class="gradeX" id="abono-{{ $pago->id }}">
                    <td class="text-primary text-center">
                        {{ empty($pago->folio_fiscal) ? $pago->folio: $pago->folio_fiscal }}
                    </td>
                    <td class="text-nowrap">
                        {{ $pago->fecha->format('d-m-Y h:i a') }}
                    </td>
                    <td>
                        {{ $pago->alumno->nuevo_numero_control }}
                    </td>
                    <td class="text-uppercase">
                        {{ $pago->alumno->fullname }}
                    </td>
                    <td>
                        @foreach ($pago->abonos as $abono)
                            {{ $abono->alumno_pago->concepto }}
                        @endforeach
                    </td>
                    <td>
                        {{ $pago->recibio->full_name }}
                    </td>

                    <td class="text-right text-nowrap" style="cursor:pointer">
                        $ {{ number_format($pago->monto, '2', '.', ',') }}
                    </td>
                </tr>
            @endforeach
            <tr class="gradeX" >
                    <td colspan="6" class="text-primary text-right text-white bg-primary">
                        TOTAL:
                    </td>
                    <td class="text-nowrap text-right">
                        ${{ number_format($pagos->sum('monto'),2,'.',',') }}
                    </td>
                </tr>
        </tbody>
    </table>
</main>

<footer class="footer">
</footer>

</body>
</html>
