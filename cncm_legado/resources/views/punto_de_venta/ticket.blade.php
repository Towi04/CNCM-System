<style>
    .text-center {
        text-align: center !important;
    }

    .ticket-footer {
        line-height : .5cm;
        font-size: 7pt;
    }

    @media print{
       .no_print{
           display: none;
       }

       .ticket-header{
            font-size: 5pt;
            text-align: center;
        }

        .ticket-footer {
            line-height : .3cm;
            font-size: 7pt;
        }

       p{
           font-size:7pt !important;
       }
    }
</style>

<section class="ticket">
    <div class="text-center">
        <a class="no_print" onclick="window.print()">
            <i class="fas fa-arrow-left " style="margin-bottom:10px;cursor: pointer;"></i> Imprimir
        </a>
    </div>

    <div class="ticket-header">
        <center>
            <img src="{{ asset('img/logo.png') }}" width="80px"><br>
        </center>
        <p class="text-center" >
            {{ $sucursal->direccion }}
            {{ $sucursal->municipio }}, {{ $sucursal->estado }} <br>
            <b>RFC:</b> {{ $sucursal->rfc }}<br>
            <b>Sucursal:</b> {{ $pago->sucursal->nombre }}<br>
            <b>Tel:</b> {{ $sucursal->telefono }}<br>
            <b>Fecha</b>  {{ $pago->fecha->format('d-m-Y') }}
            <b>Hora</b> {{ $pago->fecha->format('H:i:s') }}
            <b>Folio:</b> {{ empty($pago->folio_fiscal) ? $pago->folio: $pago->folio_fiscal }}<br>
            <b>Recibio:</b> {{ $pago->recibio->nombres }} {{ $pago->recibio->apellido_paterno }}<br>
            <b>Alumno:</b> {{ $pago->alumno->fullname }}<br>
            <b>No Control:</b> {{ $pago->alumno->nuevo_numero_control }}<br>
        </p>
    </div>

    <div class="ticket-body" >
        <p style="line-height : .5cm;">
            <b>Monto Total del pago:</b> {{ number_format($pago->monto,2,'.',',') }} <br>
            <u>Désgloce del pago</u>
        </p>
        <table  style="width:100%;margin-bottom: 1rem;">
            @if($pago->abonos_documentos->count() > 0)
                @foreach ($pago->abonos_documentos as $abono)
                    <tr>
                        <td>{{ $abono->documento->concepto }}</td>
                        <td style="text-align: right">$ {{ number_format($abono->monto,2,'.',',') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="padding-top:35px;border-top:2px solid black; text-align:right"><b>Total: $ {{ number_format($pago->abonos_documentos->sum('monto'),2,'.',',') }}</td>
                </tr>
            @else
                <tr>
                    <td>A CUENTA</td>
                    <td style="text-align: right">$ {{ number_format($pago->monto,2,'.',',') }}</td>
                </tr>
            @endif

        </table>
    </div>

    <div class="ticket-footer">
        <p>
            Efectios fiscales al pago.<br>
            Pago hecho en una sola exhibición.<br>
            Para cualquier duda o sugerencia enviarnos un correo a corporativo@cncm.com.mx
        </p>
    </div>
</section>


<script>
   setTimeout(function () { window.print(); }, 500);
</script>
