<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Pago;
use App\Models\Alumno;
use App\Models\AlumnoPago;
use App\Models\Documento;
use App\Models\Grupo;
use App\Models\Especialidad;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Jenssegers\Date\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PuntoDeVentaController extends Controller
{
    public function index(Request $request)
    {
        $alumno_huella = null;

        if (isset($request->id)) {
            $alumno_huella = Alumno::with(['especialidades'])->find($request->id);
        }

        return view('punto_de_venta.index', compact('alumno_huella'));
    }

    public function recibir_abonos(Request $request)
    {
        $this->validate($request, [
            'monto'         => 'required|numeric|min:0.01|not_in:0',
            'forma_pago'    => 'required',
        ]);

        $sucursal = session('sucursal');
        $id_sucursal = optional(session('sucursal'))->id;
        $id_recibio = auth()->id();
        $fecha_abono = now();

        if (isset($request->id_alumno)) {
            $alumno = Alumno::with('especialidades')->find($request->input('id_alumno'));
            $pagos_alumno = $alumno->documentos()->pendientes()->where('id_especialidad', '=', $request->id_especialidad)->orderBy('fecha_limite', 'asc')->get();
            $monto = $request->input('monto');

            $folio = Pago::query()->select('folio')->where('id_sucursal', $id_sucursal)->max('folio') ?? 0;
            $folio_fiscal = Pago::query()->select('folio_fiscal')->where('id_sucursal', $id_sucursal)->max('folio_fiscal') ?? 0;

            $venta_fiscal = ($request->input('forma_pago', '') != 'Efectivo') ? true : $alumno->solicitud_factura;

            Log::alert('Usuario '.Auth::user()->fullname.' recibio abono de '.$alumno->numero_control_fullname.' por '.$monto.' con folio '.(($venta_fiscal)?$folio_fiscal+1:$folio+1).' Sucursal: '.$sucursal->nombre);
            $id_especialidad = $request->id_especialidad;
        }

        if (isset($request->id_preregistro)) {
            $alumno = Alumno::with('especialidades')->find($request->input('id_preregistro'));
            $monto = $request->input('monto');

            $folio = Pago::query()->select('folio')->where('id_sucursal', $id_sucursal)->max('folio') ?? 0;
            $folio_fiscal = Pago::query()->select('folio_fiscal')->where('id_sucursal', $id_sucursal)->max('folio_fiscal') ?? 0;

            $venta_fiscal = ($request->input('forma_pago', '') != 'Efectivo') ? true : $alumno->solicitud_factura;

            Log::alert('Usuario '.Auth::user()->fullname.' recibio anticipo de '.$alumno->numero_control_fullname.' por '.$monto.' con folio '.(($venta_fiscal)?$folio_fiscal+1:$folio+1).' Sucursal: '.$sucursal->nombre);
            $id_especialidad = null;
        }

        if($fecha_abono->isSunday() && $venta_fiscal){
            $fecha_abono = $fecha_abono->addDay();
        }

        try {
            DB::beginTransaction();

            $pago = Pago::create([
                'folio'         => ($venta_fiscal) ? null : ($folio + 1),  # 👉 SI NO ES UNA VENTA FISCAL, PONER EL FOLIO EN NULL
                'folio_fiscal'  => ($venta_fiscal) ? $folio_fiscal + 1 : null,
                'forma_pago'    => $request->input('forma_pago'),
                'id_sucursal'   => $id_sucursal,
                'id_alumno'     => $alumno->id,
                'id_especialidad'     => $id_especialidad,
                'monto'         => $monto,
                'fecha'         => $fecha_abono,
                'id_recibio'    => $id_recibio,
            ]);

            # 👉 SI ES ALUMNO SE COBRAN SUS PAGOS
            if (isset($request->id_alumno)) {
                
                # SI NO TIENE NINGUN PAGO PENDIENTE, SE ADELANTA SU PROXIMO PAGO
                if ($pagos_alumno->isEmpty()) {
                    // dd('true'. $pagos_alumno);
                    $especialidad = $alumno->especialidades->where('id', $request->id_especialidad)->first();

                    
                    while($monto > 0){
                        
                        $monto = $this->crear_documentos_adelantados($especialidad, $alumno, $venta_fiscal, $monto,$pago);
                    }
                    


                } else {
                   
                    # SI TIENE PAGOS ACTUALIZAR PAGOS
                    
                    foreach ($pagos_alumno as $pa) {
                        
                        if ($monto > 0) {
                            $saldo_alumno = abs(($pa->saldo == 0) ? $pa->monto : $pa->saldo);

                            if ($monto > $saldo_alumno) {
                                $monto = $monto - $saldo_alumno;
                                
                                $pa->update([
                                    'saldo'     => 0,
                                    'status'    => config('pagos.status.Pagado'),
                                ]);
                                
                                $pago->abonos_documentos()->create([
                                    'id_sucursal'       => $id_sucursal,
                                    'id_documento'    => $pa->id,
                                    'id_especialidad'    => $request->id_especialidad,
                                    'monto'             => $saldo_alumno,
                                    'venta_fiscal'      => $venta_fiscal,
                                ]);
                                

                            } else {
                                $nuevo_saldo =  $saldo_alumno - $monto;

                                $pa->update([
                                    'saldo'     => $nuevo_saldo,
                                    'status'    => ($nuevo_saldo == 0) ? config('pagos.status.Pagado') : config('pagos.status.Pendiente'),
                                ]);

                                $pago->abonos_documentos()->create([
                                    'id_sucursal'       => $id_sucursal,
                                    'id_documento'    => $pa->id,
                                    'id_especialidad'    => $request->id_especialidad,
                                    'monto'             => $monto,
                                    'venta_fiscal'      => $venta_fiscal,
                                ]);

                                $monto = 0;
                            }
                        }
                        
                    }

                    //ADELANTAR PAGOS
                    
                    if($monto>0){
                        $especialidad = $alumno->especialidades->where('id', $request->id_especialidad)->first();

                        
                        if ($especialidad->pivot->forma_pago == 'semanal') {
                            $mont_pactado = $especialidad->pivot->monto;
                        }

                        if ($especialidad->pivot->forma_pago == 'mensual') {
                            $mont_pactado = $especialidad->pivot->monto_pronto_pago;
                        }

                       
                        while($monto > 0 && $mont_pactado > 0 ){
                            $monto = $this->crear_documentos_adelantados($especialidad, $alumno, $venta_fiscal, $monto,$pago);
                        }
                    }
                }
            } else {
                # 👉 SI ES PREREGISTRO SE ABONA EL MONTO A SU SALDO
                $alumno->saldo = $monto;
                $alumno->save();

                # 👉 SE GENERA EL CONCEPTO
                $alumno_pago = Documento::create([
                    'id_alumno'     => $alumno->id,
                    'tipo'          => 'Apartado',
                    'concepto'      => 'Apartado',
                    'monto'         => $monto,
                    'fecha_limite'  => now(),
                    'status'        => config('pagos.status.Pagado'),
                ]);

                # 👉 SE GENERA EL PAGO
                $pago->abonos_documentos()->create([
                    'id_sucursal'       => $id_sucursal,
                    'id_alumno_pago'    => $alumno_pago->id,
                    'monto'             => $monto,
                    'venta_fiscal'      => $venta_fiscal,
                ]);
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Abono Registrado correctamente',
                    'data'    => [
                        'pago' => $pago
                    ]
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();

            throw ValidationException::withMessages([
                "error" => 'Error al guardar en base de dato s' . $th->getMessage().' L:'. $th->getLine(),
            ]);
        }

        return redirect()->back();
    }

    public function pago_manual(Request $request)
    {
        $request->validate([
            'id_alumno'     => 'required',
            'id_especialidad'      => 'required',
            'monto'         => 'required',
            // 'concepto'      => 'required',
            'forma_pago'    => 'required',
            'fecha'         => 'required',
            // 'no_pago'       => 'required',
            // 'forma'         => 'required',
        ]);

        $id_sucursal = optional(session('sucursal'))->id;
        $id_recibio = auth()->id();
        $fecha_pago = $request->fecha;

        try {
            DB::beginTransaction();

            $alumno = Alumno::findOrFail($request->input('id_alumno'));

            # INDENTIFICAR EL FOLIO MENSUAL 😁
            $venta_fiscal = ($request->input('forma_pago', '') != 'Efectivo') ? true : $alumno->solicitud_factura;

            # MODALIDAD DE PAGO
            $modalidades = [
                'Mes'       => 'mensual',
                'Semana'    => 'semanal'
            ];

            $modalidad = $modalidades[$request->input('forma')] ?? '';

            if ($modalidad == 'semanal') {
                $data = [
                    'modalidad' => $modalidad,
                    'semana'    => $request->input('no_pago'),
                ];
            } else {
                $data = [
                    'modalidad' => $modalidad,
                    'mes'       => $request->input('no_pago'),
                ];
            }

            $fields = array_merge([
                'id_especialidad'      => $request->input('id_especialidad'),
                'concepto'      => '',
                'monto'         => $request->input('monto'),
                'tipo'          => $request->input('concepto'),
                'status'        => config('pagos.status.Pagado'),
                'fecha_limite'  => $fecha_pago,
                'anio'          =>  Carbon::parse($fecha_pago)->year
            ], $data);


            # CREO EL ABONO DEL ALUMNO 😊
            // $pago_alumno = $alumno->pagos()->create($fields);

            # CREO EL PAGO DEL ALUMNO 😏
            $pago = Pago::create([
                'folio'         => ($venta_fiscal) ? null : $request->input('folio'),  # 👉 SI NO ES UNA VENTA FISCAL, PONER EL FOLIO EN NULL,
                'folio_fiscal'  => ($venta_fiscal) ? $request->input('folio') : null,
                'id_sucursal'   => $id_sucursal,
                'id_alumno'     => $alumno->id,
                'id_especialidad'     => $request->input('id_especialidad'),
                'monto'         => $request->input('monto'),
                'forma_pago'    => $request->input('forma_pago'),
                'fecha'         => $fecha_pago,
                'id_recibio'    => $id_recibio,
            ]);

            # GENERO EL ABONO 🙄
            // $pago->abonos()->create([
            //     'id_sucursal'       => $id_sucursal,
            //     'id_alumno_pago'    => $pago_alumno->id,
            //     'monto'             => $request->input('monto'),
            //     'venta_fiscal'      => $venta_fiscal,
            // ]);

            # TERMINO TRANSACCION 😥
            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Abono Registrado correctamente',
                    'data'    => [
                        'pago' => $pago
                    ]
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();

            throw ValidationException::withMessages([
                "error" => 'Error al guardar en base de datos: ' . $th->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message'   => 'Pago Registrado correctamente',
        ]);
    }

    public function ticket($id)
    {
        $pago = Pago::findOrFail($id);
        $sucursal = optional(session('sucursal'));

        $pago->load(['abonos.alumno_pago', 'abonos.pago', 'alumno', 'sucursal']);

        return view('punto_de_venta.ticket', compact('pago', 'sucursal'));
    }

    public function traer_grupos(Request $request)
    {

        $grupos = Alumno::find($request->id)->grupos;

        return response()->json($grupos);
    }

    public function traer_especialidades(Request $request)
    {

        $alumno = Alumno::with(['especialidades','grupos_activos.especialidad'])->find($request->id);
        $especialidades = $alumno->especialidades;
        $grupos_activos = $alumno->grupos_activos;

        return response()->json([
            'especialidades' => $especialidades,
            'grupos_activos' => $grupos_activos,
        ]);
    }

    public function crear_documentos_adelantados($especialidad, $alumno, $venta_fiscal, $monto, $pago){

        // dd($especialidad->forma_pago);
         # SE VERIFICA SEGUN LA MODALIDAD EN LA QUE SE ENCUENTRE EL ALUMNO
         
         if ($especialidad->pivot->forma_pago == 'semanal') {

           

            $ultimo_pago = $alumno->documentos()
                ->where('id_especialidad', '=', $especialidad->id)
                ->where('tipo', config('alumnos.concepto.colegiatura'))
                ->where('status',config('pagos.status.Pagado'))
                ->orderBy('fecha_limite', 'desc')
                ->first();

            # SI NO HAY UN PAGO PREVIO, ENTONCES AGREGO EL SIGUIENTE MES
            if (empty($ultimo_pago)) {
                #SE VALIDA SI ES SU PRIMER PAGO PARA OBTENER LA FECHA DE INICIO DEL GRUPO
                $fecha = new Date($especialidad->pivot->fecha_inicio);
            } else {
                # SE OBTIENE EL ULTIMO REGISTRO Y SE AGREGA LA SIGUIENTE SEMANA CON RESPECTO AL ULTIMO RECIB
                $fecha = new Date(now()->week($ultimo_pago->semana)->setYear($ultimo_pago->anio)->addWeek());
            }

             // REVISAR SI TIENE APOYO 
             $especial = 1;
             $apoyo_especial = $alumno ->apoyos_especiales->where('id_especialidad', $especialidad->id)->filter(function($apoyo)use($fecha){
                 if($apoyo->fecha_inicio){
                     return $apoyo->fecha_inicio->lte($fecha) && $apoyo->fecha_final->gte($fecha);
                 }else{
                     return false;
                 }
             })->first();
 
             // SI TIENE APOYO ESPECIAL SE TOMA EL MONTO DEL PRECIO ESPECIAL SI NO EL DE LA ESPECIALIDAD PACTADO.
             if($apoyo_especial){
                 $precio_semanal = $apoyo_especial->precio;
                 $especial = 1;
             }else{
                 $precio_semanal = $especialidad->pivot->monto ?? 0;
             }

            $saldo = ($monto > $precio_semanal) ? 0:  $precio_semanal - $monto;
            $abonar = ($monto > $precio_semanal) ? $precio_semanal : $monto; 

            if($monto > $precio_semanal){
                $monto = $monto - $precio_semanal;
            }else{
                $monto = 0;
            }

            // dd('Monto den:'.$monto);

            $data = [
                'modalidad'     => 'semanal',
                'semana'        => $fecha->week,
                'anio'          => $fecha->year,
                'concepto'      => config('alumnos.concepto.colegiatura').' de semana #'.$fecha->weekOfYear.' del '.$fecha->year,
                'fecha_limite'  => $fecha->clone()->endOfWeek(),
                'monto'         => $precio_semanal,
                'saldo'         => $saldo,
                'status'        => ($saldo == 0) ? config('pagos.status.Pagado') : config('pagos.status.Pendiente'),
                'especial'      => $especial
            ];

        } else {
            $ultimo_pago = $alumno->documentos()
                ->where('status',config('pagos.status.Pagado'))
                ->where('id_especialidad', '=', $especialidad->id)
                ->where('tipo', config('alumnos.concepto.colegiatura'))
                ->where('modalidad', 'mensual')
                ->orderBy('anio', 'desc')
                ->orderBy('mes', 'desc')
                ->first();

            # SI NO HAY PAGOS REGISTRADOS, ENTONCES AGREGO EL MES ACTUAL
            if (empty($ultimo_pago)) {
                $fecha = new Date($especialidad->pivot->fecha_inicio);
            } else {
                # SE OBTIENE EL ULTIMO REGISTRO Y SE AGREGA EL SIGUIENTE MES CON RESPECTO AL ULTIMO RECIBO
                $fecha =  new Date(now()->setMonth($ultimo_pago->mes)->setYear($ultimo_pago->anio)->startOfMonth()->addMonth());
            }

            # VERIFICAR SI SE PAGA COMPLETAMENTE
            $especial = 0;
            $apoyo_especial = $alumno ->apoyos_especiales->where('id_especialidad', $especialidad->id)->filter(function($apoyo)use($fecha){
                if($apoyo->fecha_inicio){
                    return $apoyo->fecha_inicio->lte($fecha) && $apoyo->fecha_final->gte($fecha);
                }else{
                    return false;
                }
            })->first();

            // SI TIENE APOYO ESPECIAL SE TOMA EL MONTO DEL PRECIO ESPECIAL SI NO EL DE LA ESPECIALIDAD PACTADO.
            if($apoyo_especial){
                $mensualidad_pronto_pago = $apoyo_especial->precio;
                $especial = 1;
            }else{
                $grupo = $alumno->grupos()->whereHas('especialidad', function($q)use($especialidad){
                    return $q->where('id_especialidad','=',$especialidad->id);
                })->where('alumnos_grupos.status','Inscrito')->first();

                $mensualidad_pronto_pago = $this->calcular_precio_mensual($grupo, $alumno, $especialidad, $fecha)['monto'];
                // dd($mensualidad_pronto_pago);
            }

            $saldo = ($monto > $mensualidad_pronto_pago) ? 0:  $mensualidad_pronto_pago - $monto;
            $abonar = ($monto > $mensualidad_pronto_pago) ? $mensualidad_pronto_pago : $monto; 

            if($monto > $saldo){
                $monto = $monto-$mensualidad_pronto_pago;
            }else{
                $monto = 0;
            }


            $data = [
                'modalidad'     => 'mensual',
                'mes'           => $fecha->month,
                'anio'          => $fecha->year,
                'fecha_limite'  => $fecha->clone()->endOfMonth(),
                'concepto'      => config('alumnos.concepto.colegiatura') . ' ' . $fecha->format('F \d\e\l Y'),
                'monto'         => $mensualidad_pronto_pago,
                'saldo'         => $saldo,
                'status'        => ($saldo == 0) ? config('pagos.status.Pagado') : config('pagos.status.Pendiente'),
                'especial'      => $especial
            ];
        }

        $fields = [
            'id_alumno'     => $alumno->id,
            'id_especialidad'      => $especialidad->id,
            'tipo'          => config('alumnos.concepto.colegiatura'),
        ];
        # CREAR DOCUMENTO DE PAGO
        $documento = Documento::create(array_merge($data, $fields));
        $sucursal = session('sucursal');

        $documento->abonos()->create([
            'id_sucursal'       => $sucursal->id,
            'id_pago'       => $pago->id,
            'id_documento'    => $documento->id,
            'id_especialidad'    => $especialidad->id,
            'monto'             => $abonar,
            'venta_fiscal'      => $venta_fiscal,
        ]);

        return $monto;

    }

    private function calcular_precio_mensual($grupo, $alumno, $especialidad, $fecha_inicio)
    {
        $apoyo_especial = $alumno ->apoyos_especiales->where('id_especialidad', $especialidad->id)->filter(function($apoyo)use($fecha_inicio){
            if($apoyo->fecha_inicio){
                return $apoyo->fecha_inicio->lte($fecha_inicio) && $apoyo->fecha_final->gte($fecha_inicio);
            }else{
                return false;
            }
            
        })->first();

        if (empty($apoyo_especial)) {

            $dia = $fecha_inicio->day;

            # SI EL DIA ACTUAL ES ENTRE 1-6, ENTONCES SE ASIGNA EL PRECIO DE PRONTO PAGO
            if (in_array($dia, range(1, 6))) {
                
                return [
                    'monto'=> (!$especialidad->pivot)?$especialidad->precio_mensualidad_pronto_pago:$especialidad->pivot->monto_pronto_pago,
                    'especial'=>0,
                ];
            }else{
                #SE CALCULA DE ACUERDO A LAS CLASES RESTANTES QUE TENGA EN EL MES
                #SE DEBEN CALCULAR DE ACUERDO A LAS SEMANAS RESTANTES DONDE CUMPLA CON TODAS SUS CLASES
                #PAGA EL NUMERO DE SEMANAS COMPLETAS POR PAGO SEMANAL. 
                    
                    if(!$grupo){
                        // SE OBTIENE EL PRIMER GRUPO DE ESE ALUMNO EN ESA ESPECIALIDAD
                        $grupo = $alumno->grupos->where('id_especialidad','=',$especialidad->id)->first();
                    }
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

                    $precio = $semanas * $especialidad->getPrecioColegiaturaSemanalFecha($fecha_inicio);
                    
                    return [
                        'monto'=>$precio ?? 0,
                        'especial'=>1,
                    ];

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

}
