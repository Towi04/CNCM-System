<div class="os-tabs-controls m-1">
    <ul class="nav nav-tabs smaller border-0">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-inscripcion">Inscripción</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-semanales">Semanales</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-mensuales">Mensuales</a>
            </li>

    </ul>
</div>
<div class="tab-content">
    <div class="tab-pane active" id="tab-inscripcion">
        <table class="table">
            <thead>
                <tr>
                    <th>Precio</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Autor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($precios->where('tipo','=','Inscripción')->sortByDesc('fecha_inicio') as $precio)
                    <tr>
                        <td>$ {{ number_format($precio->precio_normal,2,'.',',')}}</td>
                        <td>{{optional($precio->fecha_inicio)->format('d-m-Y H:i:s')}}</td>
                        <td>{{optional($precio->fecha_final)->format('d-m-Y H:i:s')}}</td>
                        <td>{{optional($precio->usuario)->fullname}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tab-pane " id="tab-semanales">
        <table class="table">
            <thead>
                <tr>
                    <th>Precio</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Autor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($precios->where('tipo','=','Precio Semanal')->sortByDesc('fecha_inicio') as $precio)
                    <tr>
                        <td>$ {{ number_format($precio->precio_normal,2,'.',',')}}</td>
                        <td>{{optional($precio->fecha_inicio)->format('d-m-Y H:i:s')}}</td>
                        <td>{{optional($precio->fecha_final)->format('d-m-Y H:i:s')}}</td>
                        <td>{{optional($precio->usuario)->fullname}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tab-pane " id="tab-mensuales">
        <table class="table">
            <thead>
                <tr>
                    <th>Precio Pronto Pago</th>
                    <th>Precio</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Autor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($precios->where('tipo','=','Precio Mensual')->sortByDesc('fecha_inicio') as $precio)
                    <tr>
                        <td>$ {{ number_format($precio->precio_pronto_pago,2,'.',',')}}</td>
                        <td>$ {{ number_format($precio->precio_normal,2,'.',',')}}</td>
                        <td>{{optional($precio->fecha_inicio)->format('d-m-Y H:i:s')}}</td>
                        <td>{{optional($precio->fecha_final)->format('d-m-Y H:i:s')}}</td>
                        <td>{{optional($precio->usuario)->fullname}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>