@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Reporte de retención
@endsection

@section('breadcrumb')
    <ol class="breadcrumb no_print">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            Reportes
        </li>
        <li class="breadcrumb-item active">
            <strong>Reporte de retención</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <style>
        .contact-box:hover {
            transform: scale(1.05)
        }

        .content-box {
            padding: 10px !important;
        }

        @media screen {
            .print_only {
                display: none
            }
        }

        @media print {

            table.dataTable td,
            table.dataTable th {
                font-size: 12pt;
            }
        }
    </style>

    <div class="row mt-2">
        <div class="col-lg-10">
            <div class="element-box p-3">
                <div class="ibox-title mb-2">
                    <div class="row">
                        @component('components.busqueda_semana_desercion',['route'=>['reportes.desercion'], 'especialidades' => @$especialidades, 'semana' => @$semana, 'year'=> @$year, 'years'=> ['2022'=>'2022'] ])
                        @endcomponent
                    </div>
                </div>

                <div class="ibox-content mt-2">

                    <div class="element-wrapper">
                        <div class="os-tabs-w">
                            
                            <h4>Reporte de retención de semana {{$semana}} del {{$year}} <br><small>Del {{$fecha_inicio->format('d-m-Y')}} al {{$fecha_final->format('d-m-Y')}}</small></h4>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped table-sm table-hover tb-pagos" id="tabla_abonos">
                                            <thead>
                                                <tr>
                                                    <th class="text-center">Grupo</th>
                                                    <th class="text-center">Profesor</th>
                                                    <th class="text-center">Horario</th>
                                                    <th class="text-center">A</th>
                                                    <th class="text-center">I</th>
                                                    <th class="text-center">R</th>
                                                    <th class="text-center">+C</th>
                                                    <th class="text-center">B</th>
                                                    <th class="text-center">-C</th>
                                                    <th class="text-center">FC</th>
                                                    <th class="text-center">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                               @foreach($grupos->filter(function($gru)use($semana,$year){
                                                   $ds = $gru->getDesercionSemana($semana,$year);
                                                   
                                                   if(!$ds){
                                                       return false;
                                                   }else{
                                                    
                                                       return $ds->anterior > 0;
                                                   }
                                               }) as $grupo)
                                               <tr>
                                                    <td class="text-center">{{$grupo->clave}}</td>
                                                    <td class="text-center"></td>
                                                    <td class="text-center">{!!$grupo->horario_corto!!}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->anterior}}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->inicios}}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->reingresos}}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->cambios_horarios_altas}}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->bajas}}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->cambios_horarios_bajas}}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->fin_curso}}</td>
                                                    <td class="text-center">{{optional($grupo->getDesercionSemana($semana,$year))->total_final}}</td>     
                                               </tr>
                                               @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <h6 class="mt-4 element-header">Resumen</h6>
                                    <div class="table-responsive">
                                        <table class=" table table-bordered table-striped table-sm table-hover tb-pagos" id="table_resumen" style="width: 50%">
                                         
                                            <tbody>
                                                <tr>
                                                    <td class="bg-primary text-white">Anterior</td>
                                                    <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                        $ds = $gru->getDesercionSemana($semana,$year);
                                                        if(!$ds){
                                                            return 0;
                                                        }else{
                                                            return $ds->anterior;
                                                        }
                                                    }) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="bg-primary text-white">Inicios</td>
                                                    <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                        $ds = $gru->getDesercionSemana($semana,$year);
                                                        if(!$ds){
                                                            return 0;
                                                        }else{
                                                            return $ds->inicios;
                                                        }
                                                    }) }}</td>
                                                     </tr>
                                                     <tr>
                                                     <td class="bg-primary text-white">Reintegros</td>
                                                     <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                         $ds = $gru->getDesercionSemana($semana,$year);
                                                         if(!$ds){
                                                             return 0;
                                                         }else{
                                                             return $ds->reintegros;
                                                         }
                                                     }) }}</td>
                                                     </tr>
                                                     <tr>
                                                      <td class="bg-primary text-white">Cambios horario (+)</td>
                                                      <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                          $ds = $gru->getDesercionSemana($semana,$year);
                                                          if(!$ds){
                                                              return 0;
                                                          }else{
                                                              return $ds->cambios_horarios_altas;
                                                          }
                                                      }) }}</td>
                                                       </tr>
                                                       <tr>
                                                      <td class="bg-primary text-white">Bajas</td>
                                                      <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                          $ds = $gru->getDesercionSemana($semana,$year);
                                                          if(!$ds){
                                                              return 0;
                                                          }else{
                                                              return $ds->bajas;
                                                          }
                                                      }) }}</td>
                                                       </tr>
                                                       <tr>
                                                      <td class="bg-primary text-white">Cambos horario (-)</td>
                                                      <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                          $ds = $gru->getDesercionSemana($semana,$year);
                                                          if(!$ds){
                                                              return 0;
                                                          }else{
                                                              return $ds->cambios_horarios_bajas;
                                                          }
                                                      }) }}</td>
                                                       </tr>
                                                       <tr>
                                                      <td class="bg-primary text-white">Fin Curso</td>
                                                      <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                        $ds = $gru->getDesercionSemana($semana,$year);
                                                        if(!$ds){
                                                            return 0;
                                                        }else{
                                                            return $ds->fin_curso;
                                                        }
                                                    }) }}</td>
                                                     </tr>
                                                     <tr>
                                                    <td class="bg-primary text-white">Total</td>
                                                    <td>{{$grupos->sum(function($gru) use($semana, $year){
                                                        $ds = $gru->getDesercionSemana($semana,$year);
                                                        if(!$ds){
                                                            return 0;
                                                        }else{
                                                            return $ds->total_final;
                                                        }
                                                    }) }}</td>
                                                </tr>
                                               
                                            </tbody>
                                        </table>
                                    </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

    </div>

  
@endsection

@section('scripts')
    <link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
    <script src="{{ asset('plugins/xeditable/js/bootstrap-editable.min.js') }}"></script>
    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>

    <script type="text/javascript">
        $(function(){
           
        })
    </script>
@endsection
