<style>
    @media print{
       .no_print{
           display: none;
       }
   }

</style>

<p style="text-align: center">
   <a class="no_print" onclick="window.print()"><i class="fas fa-arrow-left " style="margin-bottom:10px;cursor: pointer;"></i> Imprimir</a><br>

   <img src="{{ asset('img/logo.png') }}" width="120px"><br><br>
   Centro Nacional de Computación de México S.C.<br>
    Domicilio fiscal: Andador Gongora No. 104 Colonia Centro. CP 38000<br>
    RFC {{$venta->sucursal->rfc}} - {{$venta->sucursal->telefono}}<BR> Celaya, Guanajuato. <br><br>

    <b>Sucursal:</b> {{ $venta->sucursal->nombre }} <br>
    Domicilio sucursal: {{$venta->sucursal->direccion}}, {{$venta->sucursal->municipio}}, {{$venta->sucursal->estado}}
</p>
<div class="content" >

   <p style="line-height : .5cm; text-align:center">
       Fecha {{ $venta->fecha->format('d-m-Y') }}<br>
       Hora {{ $venta->fecha->format('H:i:s') }}
       <hr>

       Folio: {{ $venta->folio }}<br>
       Recibio: {{ optional($venta->recibio)->fullname }}<br><br>
       @if($venta->id_alumno)
       Alumno: {{ optional($venta->alumno)->fullname }}<br>
       No Control: {{ $venta->alumno->nuevo_numero_control }}<br>
       @else 
       Nombre: {{$venta->nombre}} (Externo)
       @endif

   </p>
   <hr>
    <p style="line-height : .5cm;">
        <b>Monto Total del venta:</b> {{ number_format($venta->total,2,'.',',') }}
   <br><br>
    <u>Désgloce del venta</u>
    <br>
        <table  style="width:100%;">
            <thead>
                <tr>
                    <th style="text-align: left">Conc.</th>
                    <th style="text-align: left">Ctd.</th>
                    <th style="text-align: right">P.U.</th>
                    <th style="text-align: right">Total</th>
                </tr>

            </thead>
            @foreach ($venta->partidas as $partida)
                <tr>
                    <td>{{ $partida->producto->nombre }}</td>
                    <td>{{ $partida->cantidad }}</td>
                    <td style="text-align: right">{{ $partida->precio }}</td>
                    <td style="text-align: right">$ {{ number_format($partida->total,2,'.',',') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" style="padding-top:35px;border-top:2px solid black; text-align:right"><b>Total: $ {{ number_format($venta->total,2,'.',',') }}</td>
            </tr>
        </table>

<br>
       Efectios fiscales al venta.<br>
       Pago hecho en una sola exhibición.<br><br>

       Para cualquier duda o sugerencia enviarnos un correo a corporativo@cncm.com.mx
</div>
<script>
   setTimeout(function () { window.print(); }, 500);
</script>
