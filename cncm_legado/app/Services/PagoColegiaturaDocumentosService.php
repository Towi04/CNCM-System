<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\AlumnoEspecialidad;
use App\Models\ApoyoEspecial;
use Illuminate\Support\Carbon;

use Jenssegers\Date\Date;

use Illuminate\Http\Request;

class PagoColegiaturaDocumentosService
{
    protected $alumno;

    protected $fecha_actual;

    protected $request;

    public function setAlumno(Alumno $alumno)
    {
        $this->alumno = $alumno;

        $this->fecha_actual = today();

        return $this;
    }

    public function setFechaActual($fecha_actual)
    {
        $this->fecha_actual = $fecha_actual;

        return $this;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }


    public function mensual($especialidad, $grupo_inscripcion = null)
    {
        $grupos = $this->alumno->grupos;
        $alumno = $this->alumno;

        

        $today = Carbon::today();

        if($especialidad->pivot){
            
            if(optional($especialidad->pivot)->fecha_inicio){
                
                // SE OBTIENE LA FECHA EN QUE SE INSCRIBIO PARA OBTENER EL MONTO DE LA COLEGIATURA 
                $fecha_inicio_pago = optional($especialidad->pivot)->created_at;
                   
                // SI NO HAY FECHA DE INICIO EN LA ESPECIALIDAD SE BUSCA EN SU DOCUMENTO DE INSCRIPCION
                if(!$fecha_inicio_pago){
                    $primer_pago = $alumno->load('pagos_caja')->pagos_caja->where('id_especialidad',$especialidad->id)->first();
                    # SI NO HAY UN PAGO PREVIO, ENTONCES AGREGO EL SIGUIENTE MES DE ACUERDO A LA FECHA DE INICIO DEL
                    if ($primer_pago) {
                        $fecha_inicio_pago = $primer_pago->fecha;
                        // dd($fecha_inicio);
                    } else {
                        # SE OBTIENE EL ULTIMO REGISTRO Y SE AGREGA LA SIGUIENTE SEMANA CON RESPECTO AL ULTIMO RECIB
                        $fecha_inicio_pago = $especialidad->pivot->fecha_inicio;
                    }
                }

                $montos = $especialidad->getPrecioColegiaturaMensualFecha($fecha_inicio_pago);
                
                

                $fecha_inicio = optional($especialidad->pivot)->fecha_inicio;
                $especialidad->pivot->update([
                    'monto'=> $montos['precio_normal'],
                    'monto_pronto_pago' => $montos['precio_pronto_pago'],
                ]);
            }else{
                // SI NO TIENE FECHA DE INICIO DE ESPECIALIDAD, SE TOMA LA DEL PRIMER GRUPO DE LA MISMA
                $primer_grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                $fecha_inicio = $primer_grupo->pivot->fecha_inicio;
                if($fecha_inicio){
                    // SI LA ESPECIALIDAD NO TIENE FECHA DE INICIO SE GUARDA;
                    $fecha_inicio = new Date($primer_grupo->fecha_inicio);
                    // $alumno_especialidad = AlumnoEspecialidad::find($especialidad->pivot->id);
                    // $alumno_especialidad->fecha_inicio = $fecha_inicio;
                    // $alumno_especialidad->save();
                }else{
                    $fecha_inicio = new Date(date('Y-m-d'));
                }
                
            }
        }else{
            $fecha_inicio = new Date($this->request->fecha_inicio);
            $fecha_inicio_pago = \Carbon\Carbon::today();

        }
        

        $this->fecha_actual = $fecha_inicio;

        $fecha_inicio = new Date($fecha_inicio);
        $today = Carbon::today();

        #VALIDAMOS SI SE VAN A GENERAR PRONTO PAGO O NORMAL
        $dia = $today->day;

        #SE PREGUNTA SI EL MES ACTUAL MAS 1 ES IGUAL A LA FECHA DE INICIO PARA SALIR DEL CICLO
        #SI NO SE SIGUEN GENERANDO PAGOS MENSUALE

        // SI TRAE PIVOT SE ACTUALIZA EL MONTO PACATADO (SOLO ENTRA CUANDO ES CON EL BOTON AZUL)
        $pivot = $especialidad->pivot;
        if($pivot){
            $precios = $especialidad->getPrecioColegiaturaMensualFecha($fecha_inicio);
            $pivot->monto = $precios['precio_normal'];

            
            $pivot->monto_pronto_pago = $precios['precio_pronto_pago'];
            $pivot->save();
            // dd($pivot);
        }
        
        $i=0;

        while(!$today->copy()->addMonth()->isSameMonth($fecha_inicio) && $fecha_inicio->lte($today->copy()->addMonth())){

            
             # SE DEBE VERIFICAR SI EXISTEN DOCUMENTOS QUE FUERON CREADOS POR ADELANTADO
            $existe_documento = $this->alumno->documentos()
            ->where('id_especialidad', $especialidad->id)
            ->where('modalidad', 'mensual')
            ->where('anio', $fecha_inicio->year,)
            ->where('mes', $fecha_inicio->month, )
            ->exists();

            

            if(!$existe_documento){
                            

                $result =  $this->calcular_precio_mensual($grupo_inscripcion, $alumno, $especialidad,$i );

                

                $montos = $especialidad->getPrecioColegiaturaMensualFecha($fecha_inicio_pago);
                
                $documento = $this->crear_documento([
                    'id_especialidad'                  => $especialidad->id,
                    'concepto'                  => config('alumnos.concepto.colegiatura') .' de '.$fecha_inicio->format('F').' del '.$fecha_inicio->year,
                    'monto'                     => $result['monto'],
                    'pronto_pago'               => $montos['precio_pronto_pago'],
                    'normal_pago'               => $montos['precio_normal'],
                    'fecha_limite_pronto_pago'  => $fecha_inicio->year.'-'.$fecha_inicio->month.'-06',
                    'saldo'                     => $result['monto'],
                    'monto_apoyo_inscripcion'   => 0,
                    'mes'                       => $fecha_inicio->month,
                    'anio'                      => $fecha_inicio->year,
                    'fecha_limite'              => $fecha_inicio->copy()->endOfMonth(),
                    'modalidad'                 => 'mensual',
                    'tipo'                      => config('alumnos.concepto.colegiatura'),
                    'status'                    => config('pagos.status.Pendiente'),
                    'especial'                  => $result['especial'],

                ]);

                

            }
            // echo "Fecha {$fecha_inicio->format('d-m-Y')} | {$alumno->fullname}<br>";
            // dd($fecha_inicio->addMonthNoOverflow());
            $fecha_inicio->addMonthNoOverflow()->startOfMonth();
            $this->fecha_actual = $fecha_inicio;

            $i++;
        }   
        
            
        
    }

    private function calcular_precio_mensual($grupo, $alumno, $especialidad, $i)
    {
        $fecha_inicio = $this->fecha_actual;

        $apoyo_especial = $alumno ->apoyos_especiales->where('id_especialidad', $especialidad->id)->filter(function($apoyo)use($fecha_inicio){
            if($apoyo->fecha_inicio){
                return $apoyo->fecha_inicio->lte($fecha_inicio) && $apoyo->fecha_final->gte($fecha_inicio);
            }else{
                return false;
            }
            
        })->first();


        if (empty($apoyo_especial)) {

            $dia = $this->fecha_actual->day;

            # SI EL DIA ACTUAL ES ENTRE 1-6, ENTONCES SE ASIGNA EL PRECIO DE PRONTO PAGO
            if (in_array($dia, range(1, 6))) {


                $montos = [
                    'monto'=> (!$especialidad->pivot)?$grupo->precio_mensualidad_pronto_pago:$especialidad->pivot->monto_pronto_pago,
                    'especial'=>0,
                ];

                

                return $montos;
            }else{
                #SE CALCULA DE ACUERDO A LAS CLASES RESTANTES QUE TENGA EN EL MES
                #SE DEBEN CALCULAR DE ACUERDO A LAS SEMANAS RESTANTES DONDE CUMPLA CON TODAS SUS CLASES
                #PAGA EL NUMERO DE SEMANAS COMPLETAS POR PAGO SEMANAL. 
                    
                    if(!$grupo){
                        // SE OBTIENE EL PRIMER GRUPO DE ESE ALUMNO EN ESA ESPECIALIDAD
                        $grupo = $alumno->grupos->where('id_especialidad','=',$especialidad->id)->first();
                        // dd($grupo);
                    }
                    if($grupo){
                        $precio_mensualidad = $grupo->precio_mensualidad;
                        
                    
                    // $precio_semana = $grupo->precio_semana;
                    $dia_actual = $fecha_inicio;
                    $mes_actual = $dia_actual->month;
                    

                    $lista_numero_dias = [
                        'lunes'     => 1,
                        'martes'    => 2,
                        'miercoles' => 3,
                        'jueves'    => 4,
                        'viernes'   => 5,
                        'sabado'    => 6,
                        'domingo'   => 7,
                    ];
            
                    $total_dias = 0;
                    $dias_pendientes = 0;
            
                    $days = $grupo->days;
                    $total_days = $days->count() * 4;

                    foreach ($days as $grupodia) {
                        $numero_dia =  $lista_numero_dias[$grupodia->dia] ?? 0;

                        
                        $total_dias += countDaysInMonth($mes_actual,$numero_dia);
                        $dias_pendientes += countDaysInMonth($dia_actual,$numero_dia);

                    }
                    
                    $semanas = round($dias_pendientes / $days->count());

                   
                    // $precio = ($total_dias == 0) ? 0 : $dias_pendientes * $grupo->precio_mensualidad / $total_dias;

                    if($semanas <4){
                        $precio = $semanas * $especialidad->getPrecioColegiaturaSemanalFecha($fecha_inicio);
                        $especial = 1;
                    }else{
                        $precio = $especialidad->getPrecioColegiaturaMensualFecha($fecha_inicio)['precio_pronto_pago'];
                        $especial = 0;
                    }
                    
                    
                    
                    return [
                        'monto'=>$precio ?? 0,
                        'especial'=>$especial,
                    ];
                }else{
                    return [
                        'monto'=>0,
                        'especial'=>1,
                    ];
                }


            }
            # SE AGREGA EL PRECIO NORMAL DE LA MENSUALIDAD

            return [
                'monto'=> $grupo->precio_mensualidad ?? 0,
                'especial'=>0,
            ];
        }

        return [
            'monto'=>$apoyo_especial->precio ?? 0,
            'especial'=>1,
        ];
    }

    public function semanal($especialidad, $grupo_inscripcion = null)
    {
        $alumno = $this->alumno;

        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);

        $today = Carbon::today();

       
            Carbon::setWeekStartsAt(Carbon::SUNDAY);
            Carbon::setWeekEndsAt(Carbon::SATURDAY);
        
            if($especialidad->pivot){
                if(optional($especialidad->pivot)->fecha_inicio){

                    // SE OBTIENE LA FECHA EN QUE SE INSCRIBIO PARA OBTENER EL MONTO DE LACOLEGIATURA 
                    $fecha_inicio_pago = optional($especialidad->pivot)->created_at;
                   
                    // SI NO HAY FECHA DE INICIO EN LA ESPECIALIDAD SE BUSCA EN SU DOCUMENTO DE INSCRIPCION
                    if(!$fecha_inicio_pago){
                        $primer_pago = $alumno->load('pagos_caja')->pagos_caja->where('id_especialidad',$especialidad->id)->first();
                        # SI NO HAY UN PAGO PREVIO, ENTONCES AGREGO EL SIGUIENTE MES DE ACUERDO A LA FECHA DE INICIO DEL
                        if ($primer_pago) {
                            $fecha_inicio_pago = $primer_pago->fecha;
                            // dd($fecha_inicio);
                        } else {
                            # SE OBTIENE EL ULTIMO REGISTRO Y SE AGREGA LA SIGUIENTE SEMANA CON RESPECTO AL ULTIMO RECIB
                            $fecha_inicio_pago = $especialidad->pivot->fecha_inicio;
                        }
                    }
                    $monto = $especialidad->getPrecioColegiaturaSemanalFecha($fecha_inicio_pago);
                    // dd($monto);

                    $fecha_inicio = optional($especialidad->pivot)->fecha_inicio;
                    $especialidad->pivot->update([
                        'monto'=> $monto,
                        'monto_pronto_pago' => 0,
                    ]);

                }else{
                    // SI NO TIENE FECHA DE INICIO DE ESPECIALIDAD, SE TOMA LA DEL PRIMER GRUPO DE LA MISMA
                    $primer_grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                    $fecha_inicio = $primer_grupo->pivot->fecha_inicio;
                    if($fecha_inicio){
                        // SI LA ESPECIALIDAD NO TIENE FECHA DE INICIO SE GUARDA;
                        $fecha_inicio = new Date($primer_grupo->fecha_inicio);
                        // $alumno_especialidad = AlumnoEspecialidad::find($especialidad->pivot->id);
                        // $alumno_especialidad->fecha_inicio = $fecha_inicio;
                        // $alumno_especialidad->save();
                    }else{
                        $fecha_inicio = new Date(date('Y-m-d'));
                    }
                    
                }
            }else{
                $fecha_inicio = new Date($this->request->fecha_inicio);
            }
            
    
            
            if($fecha_inicio->isSunday()){
                $fecha_inicio->addDay();
            }
            
            $today = Carbon::today();
            if($today->isSunday()){
                $today->addDay();
            }
            
            # SE AGREGA EL PRECIO NORMAL DE LA MENSUALIDAD
            
            
            #SE PREGUNTA SI EL MES ACTUAL MAS 1 ES IGUAL A LA FECHA DE INICIO PARA SALIR DEL CICLO
            #SI NO SE SIGUEN GENERANDO PAGOS SEMANALES
            while(!$today->copy()->addWeek()->isSameWeek($fecha_inicio) && $fecha_inicio->lte($today->copy()->addWeek())){
    
                #BUSCA UN APOYO SI EXISTE EN ESA SEMANA 
                $apoyos = $alumno->load('apoyos_especiales')->apoyos_especiales->where('id_especialidad', $especialidad->id)->filter(function($apoyo)use($fecha_inicio){
                    if($apoyo->fecha_inicio && $apoyo->fecha_final){
                        return $apoyo->fecha_inicio->lte($fecha_inicio) && $apoyo->fecha_final->gte($fecha_inicio);
                    }else{
                        return false;
                    }
                });
    
                if($apoyos->first()){
                    $monto =  $apoyos->first()->precio ?? 0;
                }else{
                    if($especialidad->pivot){
                        $monto = $especialidad->pivot->monto;
                        // $monto =  $especialidad->pivot->monto ?? 0;
                    }else{
                        $monto =  $grupo_inscripcion->precio_semanal ?? 0;
                    }
                    
                }
              
                
                $this->crear_documento([
                    'id_grupo'                  => null,
                    'id_especialidad'           => $especialidad->id,
                    'concepto'                  => config('alumnos.concepto.colegiatura') .' de semana #'.$fecha_inicio->weekOfYear.' del '.$fecha_inicio->year,
                    'monto'                     => $monto,
                    'pronto_pago'               => $monto,
                    'normal_pago'               => $monto,
                    'fecha_limite_pronto_pago'  => $fecha_inicio->copy()->endOfWeek(),
                    'saldo'                     => $monto,
                    'monto_apoyo_inscripcion'   => 0,
                    'semana'                    => $fecha_inicio->weekOfYear,
                    'anio'                      => $fecha_inicio->year,
                    'fecha_limite'              => $fecha_inicio->copy()->endOfWeek(),
                    'modalidad'                 => 'semanal',
                    'tipo'                      => config('alumnos.concepto.colegiatura'),
                    'status'                    => config('pagos.status.Pendiente'),
                ]);
    
                // echo "Semana {$fecha_inicio->weekOfYear} fin_semana {$fecha_inicio->copy()->endOfWeek()}| Fecha {$fecha_inicio->format('d-m-Y')} | {$alumno->fullname}<br>";
                $fecha_inicio->addWeek();
                
    
            }   
        

    }

    private function calcular_precio_semanal($grupo, $alumno)
    {
        $apoyo_especial = $this->apoyo_especial($grupo, $alumno);

        if (empty($apoyo_especial)) {
            return  $grupo->precio_semanal ?? 0;
        }

        return $apoyo_especial->precio ?? 0;
    }

    

    private function apoyo_especial($grupo, $alumno)
    {
        $apoyo_especial = ApoyoEspecial::toBase()
            ->where('id_alumno', $alumno->id)
            ->where('id_grupo', $grupo->id)
            ->whereRaw('CAST(fecha_final AS date) > cast( NOW() AS date) AND ')
            ->orderBy('fecha_final')
            ->first();



        return $apoyo_especial;
    }

    private function crear_documento(array $atributos)
    {
        $fields = [
            'semanal' => 'semana',
            'mensual' => 'mes'
        ];

        $field = $fields[$atributos['modalidad']];

        

        # SE DEBE VERIFICAR SI EXISTEN DOCUMENTOS QUE FUERON CREADOS POR ADELANTADO
        $existe_documento = $this->alumno->documentos()
            ->where('id_especialidad', $atributos['id_especialidad'])
            ->where('modalidad', $atributos['modalidad'])
            ->where('anio', $atributos['anio'])
            ->where($field, $atributos[$field] )
            ->whereYear('created_at', $this->fecha_actual->year)
            ->exists();

           
        

        # SI NO EXISTE DOCUMENTO , SE DEBE GENERAR
        if (!$existe_documento) {
            $this->alumno->documentos()->create($atributos);
        }
        
    }
}
