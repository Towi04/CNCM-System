<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Jenssegers\Date\Date;

use App\Models\Documento; 
use App\Models\AbonoDocumento; 
use App\Models\Grupo;
use App\Models\Alumno;
use App\Models\User;
use App\Models\AlumnoGrupo;
use App\Models\AlumnoPago;
use App\Models\ApoyoInscripcion;
use App\Models\Alerta;
use App\Models\Precio;
use App\Models\AlumnoEspecialidad;
use App\Models\Especialidad;
use App\Models\Inscripcion;

use App\Services\PagoInscripcionDocumentosService;
use App\Services\PagoColegiaturaDocumentosService;


use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {   
        $sucursal = Session::get('sucursal');

        $usuarios_cumples = User::whereRaw("DATE_FORMAT(fecha_nacimiento,'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')")->whereHas('sucursales', function($q)use($sucursal){
            return $q->where('id','=',$sucursal->id);
        })->get();
        
        $alumnos_cumples = Alumno::whereRaw("DATE_FORMAT(fecha_nacimiento,'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')")->where('id_sucursal','=',$sucursal->id)->get();

        $alertas = Alerta::where('fecha','=',date('Y-m-d'))->where('id_sucursal','=',$sucursal->id)->get();

        return view('home', compact('usuarios_cumples','alumnos_cumples','alertas'));
    }

    public function ver_archivo($modulo, $id, $archivo = null)
    {
        $storage_path = storage_path();
        $public_path = public_path();

        if ($id === 'no') { //Si no se manda id, se busca en la carpeta temporal el archivo

            $url = "{$storage_path}/app/temp/{$archivo}";

            # verificamos si el archivo existe y lo retornamos
            if (!Storage::exists("temp/{$archivo}")) {
                # Cambiar por una imagen generica
                return response()->download("{$public_path}/no_image/{$modulo}.png");
            }

            return response()->download($url);
        } else {
            $url = "{$storage_path}/app/{$modulo}/{$id}/{$archivo}";

            # verificamos si el archivo existe y lo retornamos
            if (Storage::exists("{$modulo}/{$id}/{$archivo}") && $archivo != '') {

                if (Str::endsWith($url, '.pdf')) {
                    return Response::make(
                        file_get_contents($url),
                        200,
                        [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'inline;',
                        ]
                    );
                }

                if (Str::endsWith($url, '.txt')) {
                    return Response::make(
                        file_get_contents($url),
                        200,
                        [
                            'Content-Type' => 'text/html',
                            'Content-Disposition' => 'inline;',
                        ]
                    );
                }

                if (Str::endsWith($url, '.jpg') || Str::endsWith($url, '.png') || Str::endsWith($url, '.gif') || Str::endsWith($url, '.JPG') || Str::endsWith($url, '.PNG') || Str::endsWith($url, '.jpeg') ) {
                    return Response::make(
                        file_get_contents($url),
                        200,
                        [
                            'Content-Type' => 'image/jpeg',
                            'Content-Disposition' => 'inline;',
                        ]
                    );
                }


                return response()->download($url);

            } else {
                return response()->download("{$public_path}/no_image/{$modulo}.png");
            }
        }
    }

    public function generar_documentos(PagoInscripcionDocumentosService $pids){
        
        
        #borramos todos los documentos
        Documento::query()->delete();

        #obtenemos todos los grupos
        $grupos = Grupo::with('alumnos')->get();
        foreach($grupos as $grupo){
            #OBTENEMOS TODOS LOS ALUMNOS DEL GRUPO
            $alumnos = $grupo->alumnos;
            // dd($alumnos);
            foreach($alumnos as $alumno){
                $pids->setAlumno($alumno);
                $pids->inscripcion($grupo, $grupo->precio_inscripcion);
                $fecha_inicio = $grupo->fecha_inicio;
                $forma_pago = ($alumno->forma_pago) ? $alumno->forma_pago :'semanal';
                

                if($forma_pago == 'mensual'){
                    $this->generar_mensuales($grupo, $alumno );
                    
                }
                if($forma_pago == 'semanal'){
                    $this->generar_semanales($grupo, $alumno);
                }

            }

        }


        #borramos todos los documentos
        AbonoDocumento::query()->delete();
        #GENERAR ABONOS DE LA SUCURSAL DE CELAYA
        #2 CELAYA,3 IRAPUATO 4 SALAMANCA
        $this->generar_abonos(2);
        $this->generar_abonos(3);
        $this->generar_abonos(4);

    }

    public function generar_documentos_sucursal($id, PagoInscripcionDocumentosService $pids){
        

        #borramos todos los documentos
        Documento::with('alumno')->whereHas('alumno', function()use($id){
            return $q->where('id_sucursal',$id);
        })->delete();

        #obtenemos todos los grupos
        $grupos = Grupo::with('alumnos')->get();
        foreach($grupos as $grupo){
            #OBTENEMOS TODOS LOS ALUMNOS DEL GRUPO
            $alumnos = $grupo->alumnos;
            // dd($alumnos);
            foreach($alumnos as $alumno){
                $pids->setAlumno($alumno);
                $pids->inscripcion($grupo, $grupo->precio_inscripcion);
                $fecha_inicio = $grupo->fecha_inicio;
                $forma_pago = ($alumno->forma_pago) ? $alumno->forma_pago :'semanal';
                

                if($forma_pago == 'mensual'){
                    $this->generar_mensuales($grupo, $alumno );
                    
                }
                if($forma_pago == 'semanal'){
                    $this->generar_semanales($grupo, $alumno);
                }

            }

        }


        #borramos todos los documentos
        AbonoDocumento::query()->delete();
        #GENERAR ABONOS DE LA SUCURSAL DE CELAYA
        #2 CELAYA,3 IRAPUATO 4 SALAMANCA
        $this->generar_abonos(2);
        $this->generar_abonos(3);
        $this->generar_abonos(4);

    }

    public function generar_documentos_alumno($id, $id_especialidad = null,  PagoInscripcionDocumentosService $pids, PagoColegiaturaDocumentosService $pcds, Request $request){
        #borramos todos los documentos
        if(!$request->id_especialidad){
            $id_especialidad = $id_especialidad;
        }else{
            $id_especialidad = $request->id_especialidad;
        }

        


        Documento::with('alumno')->whereHas('alumno', function($q)use($id){
            return $q->where('id_alumno',$id);
        })->where('id_especialidad','=',$id_especialidad)->delete();

        $alumno = Alumno::with(['grupos','especialidades.precios'])->find($id);
        $sucursal = optional(session('sucursal'));

        try {
           
            // $grupo = $alumno->grupos->whereIn('pivot.status',['Inscrito','Pausa'])->where('id_especialidad',$especialidad->id)->first();
            $especialidad = $alumno->especialidades->where('id',$id_especialidad)->first();

            if ($especialidad) {

                $pids->setAlumno($alumno);
                $pcds->setAlumno($alumno);

                
                switch ($especialidad->pivot->forma_pago) {
                    case config('alumnos.forma_pago.mensual','mensual'):
                        
                        $pids->inscripcion_especial_boton($especialidad);
                        // GENERA DOCUMENTOS MENSUALES
                        $pcds->mensual($especialidad);
                        
                        $monto_pactado = $especialidad->monto;
                        $monto_pronto_pago_pactado = $especialidad->monto_pronto_pago;
                    break;
                    case config('alumnos.forma_pago.semanal','semanal'):
                        $pids->inscripcion_especial_boton($especialidad);
                        // GENERA DOCUMENTOS SEMANALES
                        $pcds->semanal($especialidad);
                        $monto_pactado = $especialidad->monto;
                        $monto_pronto_pago_pactado = 0;
                    break;
                }

            }
        } catch (\Throwable $th) {

            // dd($th->getTrace());

            return response()->json([
                'success'   => false,
                'message'   => 'Ocurrio el siguiente error:' .$th->getMessage().' en la línea '.$th->getLine().' en el archivo: '.$th->getFile(),
                // 'redirect'  => route('alumnos.show',$alumno),
                // 'pago'      => $request->has('id_grupo') ? Pago::first()->where('id_alumno',$alumno->id)->latest()->first() : ''
            ]);

            Session::flash('error','Ocurrio el siguiente error: '.$th);
            return redirect()->back();
            
        }

        Session::flash('success','Se generarón los documentos con éxito');
        return redirect()->back();


    }
    
    public function generar_abonos($id_sucursal){

       
        // dd('hola');
        #obtenemos todos los grupos de la sucursal
        $grupos = Grupo::with('alumnos')->where('id_sucursal','=',$id_sucursal)->get();
        // $grupos = Grupo::with('alumnos')->get();

        foreach($grupos as $grupo){

            foreach($grupo->alumnos as $alumno){
                $pagos = $alumno->pagos_caja;
                #Para cada pago realizado se van a crear los abnos a los documentos del mas antiguo al mas reciente
                foreach($pagos->sortBy(function($pa){
                    return $pa->fecha->format('Ymd');
                }) as $pago){

                    $monto_pago = $pago->monto;
                    $documentos = $alumno->documentos->where('saldo','>',0)->sortBy('fecha_limite')->values();

                    foreach($documentos as $documento){
                        # GENERO EL ABONO
                        #SE VA A VALIDAR SI FUE COLEGIATURA POR PRONTO PAGO
                        if($documento->tipo == 'Colegiatura'){
                            // validar fecha limite de pronto pago
                            if($alumno->forma_pago == 'mensual'){
                                $fecha_limite_pronto = Carbon::createFromFormat('Y-m-d',$documento->anio.'-'.$documento->mes.'-06');

                                if($pago->fecha->gte($fecha_limite_pronto) &&  $documento->monto == $grupo->precio_mensualidad_pronto_pago ){
                                    $documento->monto = $grupo->precio_mensualidad;
                                    $documento->saldo = $grupo->precio_mensualidad;
                                    $documento->save();
                                }
                            }
                            

                            
                        }

                        if($documento->saldo >= $monto_pago){
                            $monto = $monto_pago;
                            $monto_pago = 0;
                        }else{
                            $monto = $documento->saldo;
                            $monto_pago = $monto_pago - $documento->saldo;
                        }

                        $pago->abonos_documentos()->create([
                            'id_sucursal'       => $id_sucursal,
                            'id_documento'    => $documento->id,
                            'monto'             => $monto,
                            'venta_fiscal'      => '0',
                        ]);

                        $documento->saldo = $documento->saldo - $monto;
                        if($documento->saldo == 0){
                            $documento->status = 'Pagado';
                        }
                        $documento->save();
                        
                        if($monto_pago <= 0){
                            break;
                        }
                        

                    }



                }
            }
            
        }
        


    }

    public function generar_abonos_alumno($id_alumno){
   
        $alumno = Alumno::with('documentos.abonos')->find($id_alumno);
        
                $pagos = $alumno->pagos_caja;

                // SE BORRAN LOS ABONOS A LOS DOCUMENTOS DEL ALUMNO

                foreach($alumno->documentos as $documento){
                    // dd($documento);
                    $documento->abonos()->delete();
                    $documento->saldo = $documento->monto;
                    $documento->save();
                }

                
                #Para cada pago realizado se van a crear los abnos a los documentos del mas antiguo al mas reciente
                foreach($pagos->where('id_especialidad','!=',null)->sortBy(function($pago){
                    return $pago->fecha->format('Ymd');
                }) as $pago){


                    
                    $monto_pago = $pago->monto;
                    $documentos = $alumno->load('documentos')->documentos->where('id_especialidad','=',$pago->id_especialidad)->where('saldo','>',0)->sortBy('fecha_limite')->values();
                    

                    foreach($documentos->sortBy('fecha_limite') as $documento){
                       
                        # GENERO EL ABONO
                        #SE VA A VALIDAR SI FUE COLEGIATURA POR PRONTO PAGO
                        
                        $alumno_especialidad = AlumnoEspecialidad::with('especialidad')->where('id_alumno','=',$alumno->id)->where('id_especialidad','=',$documento->id_especialidad)->first();
                        

                        if($documento->tipo == 'Colegiatura'){
                            
                            if($alumno_especialidad->forma_pago == 'mensual'){
                                
                                // validar fecha limite de pronto pago
                                echo '<br>Documento:'.$documento->id. ' Año: '.$documento->anio.' Mes:'.$documento->mes;
                               
                                $fecha_limite_pronto = Carbon::createFromFormat('Y-m-d',$documento->anio.'-'.$documento->mes.'-07');
                                
                                // SE CONVIERTE PRONTO PAGO EN PAGO SIN PRONTO PAGO
                                if($pago->fecha->gte($fecha_limite_pronto) && $documento->especial == 0 && ($documento->saldo - $monto_pago <= 0 )){
                                    $dif = $alumno_especialidad->monto - $alumno_especialidad -> monto_pronto_pago;
                                    $documento->monto = $documento->monto + $dif;
                                    $documento->saldo = $documento->saldo + $dif;
                                    $documento->especial = 1;
                                    $documento->save();

                                }
                            }
                        }

                        if($documento->saldo >= $monto_pago){
                            $monto = $monto_pago;
                            $monto_pago = 0;
                        }else{
                            $monto = $documento->saldo;
                            $monto_pago = $monto_pago - $documento->saldo;
                        }

                        $abono = $pago->abonos_documentos()->create([
                            'id_sucursal'       => $alumno->id_sucursal,
                            'id_documento'    => $documento->id,
                            'monto'             => $monto,
                            'venta_fiscal'      => '0',
                        ]);

                        $documento->saldo = $documento->saldo - $monto;
                        if($documento->saldo == 0){
                            $documento->status = 'Pagado';
                        }
                        $documento->save();
                        
                        if($monto_pago <= 0){
                            break;
                        }
                        

                    }


                    if($monto_pago > 0){
                        // SE ACABARON LOS DOCUMENTOS Y SIGUE MONTO EN POSITIVO PARA ESE PAGO
                        // SE GENERAN LOS DOCUMENTOS ADELANTADOS
                        $especialidad = $pago->especialidad;

                        $mont_pactado = 1;

                        while($monto_pago > 0 && $mont_pactado > 0 ){
                            
                            $especialidad = $alumno->especialidades->where('id',$especialidad->id)->first();

                            if ($especialidad->pivot->forma_pago == 'semanal') {
                                $mont_pactado = $especialidad->pivot->monto;
                            }
    
                            if ($especialidad->pivot->forma_pago == 'mensual') {
                                $mont_pactado = $especialidad->pivot->monto_pronto_pago;
                            }

                            // dd($mont_pactado);
                            $monto_pago = $this->crear_documentos_adelantados($especialidad, $alumno, ($pago->folio)?0:1, $monto_pago,$pago);
                            // dd('Monto: '.$monto);
                        }

                    }



                }
            
            

        
        return redirect()->back();

    }

    // FUNCION PARA GENERAR DOCUMENTOS ADELANTADOS EN PROCESO ESPECIAL
    public function crear_documentos_adelantados($especialidad, $alumno, $venta_fiscal, $monto, $pago){

        // dd($especialidad->forma_pago);
         # SE VERIFICA SEGUN LA MODALIDAD EN LA QUE SE ENCUENTRE EL ALUMNO
         if ($especialidad->pivot->forma_pago == 'semanal') {

            $ultimo_pago = $alumno->documentos()
                ->where('id_especialidad', '=', $especialidad->id)
                ->where('tipo', config('alumnos.concepto.colegiatura'))
                ->where('status',config('pagos.status.Pagado'))
                // ->where('anio', now()->year)
                ->where('modalidad', 'semanal')
                ->orderBy('anio', 'desc')
                ->orderBy('semana', 'desc')
                ->first();

            # SI NO HAY UN PAGO PREVIO, ENTONCES AGREGO EL SIGUIENTE MES DE ACUERDO A LA FECHA DE INICIO DEL
            if (empty($ultimo_pago)) {
                $fecha = $especialidad->pivot->fecha_inicio;
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

            # SI NO HAY PAGOS REGISTRADOS ,ENTONCES AGREGO EL MES ACTUAL
            if (empty($ultimo_pago)) {
                $fecha =  new Date($especialidad->pivot->fecha_inicio);
            } else {
                # SE OBTIENE EL ULTIMO REGISTRO Y SE AGREGA EL SIGUIENTE MES CON RESPECTO AL ULTIMO RECIBO
                $fecha =  new Date(now()->setMonth($ultimo_pago->mes)->setYear($ultimo_pago->anio)->addMonth());
            }

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
                $mensualidad_pronto_pago = $especialidad->pivot->monto_pronto_pago ?? 0;
            }

            # VERIFICAR SI SE PAGA COMPLETAMENTE
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
       
        
        $documento->abonos()->create([
            'id_sucursal'       => $alumno->id_sucursal,
            'id_pago'       => $pago->id,
            'id_documento'    => $documento->id,
            'id_especialidad'    => $especialidad->id,
            'monto'             => $abonar,
            'venta_fiscal'      => $venta_fiscal,
        ]);

        return $monto;

    }


    public function info(){

        #ALUMNOS CON MAS DE 2 GRUPOS

        $alumnos = Alumno::with('grupos.especialidad')->where('id_sucursal','=',2)->has('grupos','>',1)->get();

        
        $table = '<h3>Sucursal Celaya</h3>';
        $table .= '<table>';
        foreach($alumnos as $alumno){
            $table .= '<tr>';
            $table .= "  <td style='background: #030050; color:white' >";
            $table .= $alumno->nuevo_numero_control.' '.$alumno->fullname;
            $table .= '  </td>';
            $table .= '</tr>';
            foreach($alumno->grupos as $grupo){
                $table .= '<tr>';
                $table .= "  <td >";
                $table .= $grupo->clave.' - '.$grupo->especialidad->nombre;
                $table .= '  </td>';
                $table .= '</tr>';
            }

        }
        $table .= '</table>';

        echo $table;

    }

    public function alumnos_grupos(){
        $sucursal  = Session::get('sucursal');

        $alumnos = Alumno::with('pagos_caja')->has('pagos_caja')->where('id_sucursal','=',$sucursal->id)->get();

        // echo "ALUMNOS CON GRUPOS DESFADASO DE SUCURSAL: {$sucursal->nombre}<br>";
        $table = '<table class="table table-bordered"  ><thead class="bg-primary text-white"><tr><th>ALUMNO</th><th>FECHA PRIMER PAGO</th><th>INICIO GRUPO</th><th>INICIO DEL ALUMNO EN EL GRUPO</th></tr></thead>';
        foreach($alumnos as $alumno){
            
            $fecha_primer_pago = $alumno->pagos_caja->sortBy('created_at')->first()->fecha;
            
            foreach($alumno->grupos as $grupo){
                if($grupo->fecha_inicio->diffInDays($fecha_primer_pago, false) > 30){
                    $table .= '<tr>';
                    $table .= ' <td>';
                    $table .= "     <a target='_blank' class='text-primary' href='".route('alumnos.show', $alumno->id)."'>{$alumno->fullname}</a>";
                    $table .= ' </td>';
                    $table .= ' <td>';
                    $table .= "     {$fecha_primer_pago->format('d-m-Y')}";
                    $table .= ' </td>';
                    $table .= ' <td>';
                    $table .= "     {$grupo->clave}: ".optional($grupo->fecha_inicio)->format('d-m-Y');
                    $table .= ' </td>';
                    $table .= " <td><a class='editable_fecha_inicio_grupo' data-pk='{$grupo->pivot->id}' data-name='fecha_inicio' data-url='".route('especiales.actualizar_informacion_grupos_alumnos')."' data-type='date' >";
                    $table .=  optional($grupo->pivot->fecha_inicio)->format('d-m-Y');
                    $table .= ' </a></td>';
                    $table .= '</tr>';
                } 
            }
            

        }
        $table .= '</table>';

         // echo "ALUMNOS CON GRUPOS DESFADASO DE SUCURSAL: {$sucursal->nombre}<br>";
         $table2 = '<table class="table table-bordered"  ><thead class="bg-primary text-white"><tr><th>ALUMNO</th><th>FECHA PRIMER PAGO</th><th>INICIO GRUPO</th><th>INICIO DEL ALUMNO EN EL GRUPO</th></tr></thead>';
         foreach($alumnos as $alumno){
             
             $fecha_primer_pago = $alumno->pagos_caja->sortBy('created_at')->first()->fecha;
             
             foreach($alumno->grupos as $grupo){
                 if($fecha_primer_pago->diffInDays($grupo->fecha_inicio, false) > 30){
                     $table2 .= '<tr>';
                     $table2 .= ' <td>';
                     $table2 .= "     <a target='_blank' class='text-primary' href='".route('alumnos.show', $alumno->id)."'>{$alumno->fullname}</a>";
                     $table2 .= ' </td>';
                     $table2 .= ' <td>';
                     $table2 .= "     {$fecha_primer_pago->format('d-m-Y')}";
                     $table2 .= ' </td>';
                     $table2 .= ' <td>';
                     $table2 .= "     {$grupo->clave}: ".optional($grupo->fecha_inicio)->format('d-m-Y');
                     $table2 .= ' </td>';
                     $table2 .= " <td><a class='editable_fecha_inicio_grupo' data-pk='{$grupo->pivot->id}' data-name='fecha_inicio' data-url='".route('especiales.actualizar_informacion_grupos_alumnos')."' data-type='date' >";
                     $table2 .=  optional($grupo->pivot->fecha_inicio)->format('d-m-Y');
                     $table2 .= ' </a></td>';
                     $table2 .= '</tr>';
                 } 
             }
             
 
         }
         $table2 .= '</table>';
 

        return view('especiales.alumnos_grupos', compact('table','table2'));

    }

    public function actualizar_informacion_grupos_alumnos(Request $request){
        $alumno_grupo = AlumnoGrupo::findOrFail($request->pk);
        $alumno_grupo[$request->name] = $request->value;
        $alumno_grupo->save();

    }

    public function crear_apoyos_inscripcion(){
        $pagos = AlumnoPago::where('monto_apoyo_inscripcion','>',0)->get();

        foreach($pagos as $pago){
            $apoyo = ApoyoInscripcion::create([
                'id_alumno' => $pago->id_alumno,
                'id_grupo'  => $pago->id_grupo,
                'apoyo'     => $pago->monto_apoyo_inscripcion,
                'id_usuario'=> Auth::id(),
            ]);

        }

        $documentos = Documento::where('monto_apoyo_inscripcion','>',0)->get();

        foreach($documentos as $documento){
            $apoyo = ApoyoInscripcion::create([
                'id_alumno' => $documento->id_alumno,
                'id_grupo'  => $documento->id_grupo,
                'apoyo'     => $documento->monto_apoyo_inscripcion,
                'id_usuario'=> Auth::id(),
            ]);
        }



    }

    public function actualizar_apoyos_inscripcion_monto_pago(){

        $apoyos = ApoyoInscripcion::with('grupo')->get();
        
        foreach($apoyos as $apoyo){
            $grupo = $apoyo->grupo;
            $apoyo->apoyo = $grupo->precio_inscripcion - $apoyo->apoyo;
            $apoyo->save();

        }



    }

    public function alumnos_sin_fecha_inicio(){
        $sucursal  = Session::get('sucursal');

        $alumnos = Alumno::with('pagos_caja')->has('pagos_caja')->where('id_sucursal','=',$sucursal->id)->whereHas('grupos', function($q){
            return $q->whereNull('alumnos_grupos.fecha_inicio');
        })->get();



        // echo "ALUMNOS CON GRUPOS DESFADASO DE SUCURSAL: {$sucursal->nombre}<br>";
        $table = '<table class="table table-bordered"  ><thead class="bg-primary text-white"><tr><th>#</th><th>ALUMNO</th><th>INICIO GRUPO</th><th>INICIO DEL ALUMNO EN EL GRUPO</th></tr></thead>';
        foreach($alumnos as $index => $alumno){
            
            foreach($alumno->grupos->filter(function($grupo){
                return $grupo->pivot->fecha_inicio == null;
            })  as $grupo){
                
                    $table .= '<tr>';
                    $table .= ' <td>';
                    $table .= $index+1;
                    $table .= ' </td>';
                    $table .= ' <td>';
                    $table .= "     <a target='_blank' class='text-primary' href='".route('alumnos.show', $alumno->id)."'>{$alumno->fullname}</a>";
                    $table .= ' </td>';
                   
                    $table .= ' <td>';
                    $table .= "     {$grupo->clave}: ".optional($grupo->fecha_inicio)->format('d-m-Y');
                    $table .= ' </td>';
                    $table .= " <td><a class='editable_fecha_inicio_grupo' data-pk='{$grupo->pivot->id}' data-name='fecha_inicio' data-url='".route('especiales.actualizar_informacion_grupos_alumnos')."' data-type='date'  data-placement='bottom'>";
                    $table .=  optional($grupo->pivot->fecha_inicio)->format('d-m-Y');
                    $table .= ' </a></td>';
                    $table .= '</tr>';
            
            }
            

        }
        $table .= '</table>'; 

        return view('especiales.alumnos_sin_fecha_inicio', compact('table'));

    }

    public function crear_precios_iniciales_grupos(){

        $grupos = Grupo::doesntHave('precios')->get();

        foreach($grupos as $grupo){

            #SE CREA LA INSCRIPCION INICIAL DEL GRUPO
            $precio = Precio::create([
                'tipo' => 'Inscripción',
                'id_grupo' => $grupo->id,
                'fecha_inicio' => '2022-01-01',
                'precio_pronto_pago' => null,
                'precio_normal' => ($grupo->precio_inscripcion)?$grupo->precio_inscripcion:0,
                'id_usuario' => Auth::id(),
            ]);

            #SE CREA LA COLEGIATURA SEMANAL INICIAL DEL GRUPO
            $precio = Precio::create([
                'tipo' => 'Precio Semanal',
                'id_grupo' => $grupo->id,
                'fecha_inicio' => '2022-01-01',
                'precio_pronto_pago' => null,
                'precio_normal' => ($grupo->precio_semanal)?$grupo->precio_semanal:0,
                'id_usuario' => Auth::id(),
            ]);


            #SE CREA LA COLEGIATURA MENSUAL INICIAL DEL GRUPO
            $precio = Precio::create([
                'tipo' => 'Precio Mensual',
                'id_grupo' => $grupo->id,
                'fecha_inicio' => '2022-01-01',
                'precio_pronto_pago' => $grupo->precio_mensualidad_pronto_pago,
                'precio_normal' => ($grupo->precio_mensualidad)?$grupo->precio_mensualidad:0,
                'id_usuario' => Auth::id(),
            ]);
        }

    }

    public function generar_registro_especalidades(){

        foreach (Alumno::with('grupos.especialidad.materias')->lazy() as $alumno) {
                
                foreach($alumno->grupos as $grupo){

                    $especialidad = $grupo->especialidad;
                    
                    if($especialidad->id){

                        $alumno_esp = AlumnoEspecialidad::where('id_alumno','=',$alumno->id)->where('id_especialidad','=',$especialidad->id)->count();

                        if($alumno_esp == 0){
                            echo '<br>Se registro especialidad del Alumno '.$alumno->id.' NC:'.$alumno->nuevo_numero_control.'  '.$alumno->fullname.' a: ';

                            if($grupo->pivot->fecha_inicio){
                                $fi = $grupo->pivot->fecha_inicio;
                            }else{
                                $fi =  $grupo->fecha_inicio;
                            }

                            $esp = AlumnoEspecialidad::create([
                                'id_alumno' => $alumno->id,
                                'id_especialidad' =>  $grupo->id_especialidad,
                                'fecha_inicio' => $fi->format('Y-m-d'),
                                'forma_pago' => ($alumno->forma_pago)?$alumno->forma_pago:'semanal',
                                'monto' => ($alumno->forma_pago == 'mensual' )?$grupo->precio_mensualidad : $grupo->precio_semanal,
                                'monto_pronto_pago' => $grupo->precio_mensualidad_pronto_pago,
                                'semanas_cursar' => $especialidad->materias->sum('semanas'),
                                'semanas_cursadas' => 0,
                                'semanas_pagadas' => 0,
                                'status' => 'Activo',
                            ]);
                            
                            
                            echo '<br>Especialidad: '. $especialidad->nombre.' .';
                        }
                        

                    }
                    

                    // dd($especialidad);
                }
        }



    }


    
    public function generar_especialidad_pagos(){

        foreach (Alumno::with(['especialidades','pagos_caja'])->has('especialidades','=',1)->lazy() as $alumno) {

            $especialidad = $alumno->especialidades->first();
            // ACTUALIZAMOS TODOS SUS PAGOS 
            $alumno->pagos_caja()->update([
                'id_especialidad' => $especialidad->id
            ]);


        }


    }


    public function invertir_apoyos_emmanuel(){

        try {
            DB::beginTransaction();
            foreach(ApoyoInscripcion::with(['alumno','grupo','especialidad'])->where('id_usuario','=',31)->where('created_at','<','2022-08-22')->whereNotNull('id_grupo')->lazy() as $apoyo){
            
                $grupo = $apoyo->grupo;
                $precio_inscripcion_grupo = $grupo->precio_inscripcion;
                $apoyo_invertido = $precio_inscripcion_grupo - $apoyo->apoyo;
                echo $apoyo->alumno->id.','.$apoyo->alumno->fullname.','.$apoyo->apoyo.','.$apoyo_invertido.'<br>';
                $apoyo->apoyo = $apoyo_invertido;
                $apoyo->save();
    

            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
        }
       

        

    }

    public function reporte_inscritos_enero(){

        foreach (Alumno::with(['especialidades','pagos_caja','grupos'])->lazy() as $alumno) {

            $especialidades = $alumno->especialidades;

            echo 'Alumno: '.$alumno->id.' - '.$alumno->fullname;

            foreach($especialidades as $especialidad){
                // SE REVISA SI ESTA EN EL REPORTE DE INSCRITOS
                echo '  Especialidad: '.$especialidad->id.' - '.$especialidad->nombre.' <br>';

                $inscrito = Inscripcion::where('id_alumno','=',$alumno->id)->where('id_especialidad','=',$especialidad->id)->first();

               
                // SI EXISTE SU REGISTRO DE INSCRIPCION EN EL REPORTE. SOLO SE ACTUALIZAN FECHAS Y ASESORES
                if($inscrito){
                    // SI NO TIENE FECHA DE INICIO EN EL GRUPO SE AGREGA DE ACUERDO A LA INSCRIPCIÓN
                    if(!$inscrito->fecha_inicio_grupo){

                        $grupo_pivot = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                        if($grupo_pivot){
                            $inscrito ->fecha_inicio_grupo = ($grupo_pivot->pivot->fecha_inicio)?$grupo_pivot->pivot->fecha_inicio:$grupo_pivot->fecha_inicio;
                            $inscrito->save();
                        }

                    }
                    
                   
                // SI NO TIENE FECHA DE INICIO EN LA ESPECIALIDAD SE AGREGA DE ACUERDO AL ASESOR DEL ALUMNO
                    if(!$inscrito->id_asesor){
                        $inscrito->id_asesor = ($alumno->asesor_educativo->id == 'CNCM' || $alumno->id_asesor_educativo == null)?53:$alumno->id_asesor_educativo;
                        $inscrito->save();
                    }
                }else {
                    // NO EXISTE SU REGISTRO DE INSCRIPCIÓN. SE GENERA EL REGISTRO
                    // SE ENCUENTRA EL PAGO DE LA INSCRIPCION

                    $documento = $alumno->documentos('id_especialidad','=',$especialidad->id)->where('tipo','=','Inscripción')->where('status','=','Pagado')->first();

                    

                    if($documento){
                        if($documento->abonos->count() > 0){
                            // SE VALIDA QUE EL PAGO NO ESTE ELIMINADO. DE SER ASI SE ACTUALIZAN DOCUMENTOS PARA AJUSTAR. 
                            if($documento->load('abonos.pago')->abonos->first()->pago->id){
                                $fecha_pago = $documento->load('abonos.pago')->abonos->first()->pago->fecha;
                            }else{
                                $this->generar_documentos_alumno($alumno->id, $especialidad->id, new PagoInscripcionDocumentosService(), new PagoColegiaturaDocumentosService, new Request() );
                                $this->generar_abonos_alumno($alumno->id);

                                $alumno = Alumno::with('documentos')->find($alumno->id);
                                $documento = $alumno->documentos('id_especialidad','=',$especialidad->id)->where('tipo','=','Inscripción')->where('status','=','Pagado')->first();
                                if($documento){
                                    if($fecha_pago = $documento->load('abonos.pago')->abonos->first()->pago){
                                        $fecha_pago = $documento->load('abonos.pago')->abonos->first()->pago->fecha;
                                    }else{
                                        $grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                                        $fecha_pago = ($especialidad->pivot->fecha_inicio)? $especialidad->pivot->fecha_inicio:$grupo->fecha_inicio;
                                    }
                                    
                                }else{
                                    $grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                                    $fecha_pago = ($especialidad->pivot->fecha_inicio)? $especialidad->pivot->fecha_inicio:$grupo->fecha_inicio;
                                }
                            }

                        }else{
                            $this->generar_documentos_alumno($alumno->id, $especialidad->id, new PagoInscripcionDocumentosService(), new PagoColegiaturaDocumentosService, new Request() );
                            $this->generar_abonos_alumno($alumno->id);
                              
                           

                            $alumno = Alumno::with('documentos')->find($alumno->id);
                            $documento = Documento::where('id_alumno','=', $alumno->id)->where('id_especialidad','=',$grupo->id_especialidad)->where('tipo','=','Inscripción')->where('status','=','Pagado')->first();
                            if($documento){
                                if($fecha_pago = $documento->load('abonos.pago')->abonos->first()){
                                    $fecha_pago = $documento->load('abonos.pago')->abonos->first()->pago->fecha;
                                }else{
                                    $grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                                    $fecha_pago = ($especialidad->pivot->fecha_inicio)? $especialidad->pivot->fecha_inicio:$grupo->fecha_inicio;
                                }
                            }else{
                                $grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                                $fecha_pago = ($especialidad->pivot->fecha_inicio)? $especialidad->pivot->fecha_inicio:$grupo->fecha_inicio;
                            }
                            

                        }
                        
                        


                    }else{

                        $grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->first();
                        $fecha_pago = ($especialidad->pivot->fecha_inicio)? $especialidad->pivot->fecha_inicio:$grupo->fecha_inicio;
                    }
                    
                    $grupo = $alumno->grupos->where('id_especialidad',$especialidad->id)->filter(function($grupo){
                        return $grupo->pivot->status == 'Inscrito' || $grupo->pivot->status == 'Pausa';
                    })->first();

                    $inscrito = Inscripcion::create([
                        'id_alumno'=>$alumno->id,
                        'id_grupo'=>$grupo->id,
                        'id_especialidad'=>$especialidad->id,
                        'id_asesor'=>($alumno->asesor_educativo->id == 'CNCM' || $alumno->id_asesor_educativo == null)?53:$alumno->id_asesor_educativo,
                        'id_sucursal'=>$alumno->id_sucursal,
                        'fecha'=> $fecha_pago,
                        'fecha_inicio_grupo'=>$grupo->pivot->fecha_inicio,
                    ]);

                    

                }
            }
            

        }


    }

    public function aplicacion_masiva_documentos(Request $request)
    {
        
        foreach (Alumno::with(['especialidades','pagos_caja','grupos'])
        ->when($request->id_alumno, function($q, $id_alumno){
            return $q->where('id','>=',$id_alumno);
        })->lazy() as $alumno) {

            $especialidades = $alumno->especialidades;

            echo '<br> Alumno: '.$alumno->id.' - '.$alumno->fullname;

            foreach($especialidades as $especialidad){
                // SE REVISA SI ESTA EN EL REPORTE DE INSCRITOS
                echo '  Especialidad: '.$especialidad->id.' - '.$especialidad->nombre.' ';

                $this->generar_documentos_alumno($alumno->id, $especialidad->id, new PagoInscripcionDocumentosService(), new PagoColegiaturaDocumentosService, new Request() );
                    
                }

                $this->generar_abonos_alumno($alumno->id);   
                
            }
            

    }


    /* 
    * PROCESO ESPECIAL QUE SE UTIILZOAO 25 DE ABRIL 2023 PARA CORREGIR LOS ALUMNOS QUE 
    * TENIAN SALDO NEGATIVO DEBIDO A QUE SE ESTABA GENERANDO EL PRONTO PAGO TODOS LOS DÍAS
    * Y AUMENTANDO DEMASIADO EL SALDO. 
    */
    public function generar_abonos_alumnos_negativos(){

        $doctos_negativos = Documento::where('saldo','<',0)->get();
        
        foreach ( Alumno::with(['especialidades','pagos_caja','grupos'])
        ->whereIn('id',$doctos_negativos->pluck('id_alumno'))->lazy() as $alumno) {

            $especialidades = $alumno->especialidades;

            echo '<br> Alumno: '.$alumno->id.' - '.$alumno->fullname;

            foreach($especialidades as $especialidad){
                // SE REVISA SI ESTA EN EL REPORTE DE INSCRITOS
                echo '  Especialidad: '.$especialidad->id.' - '.$especialidad->nombre.' ';

                $this->generar_documentos_alumno($alumno->id, $especialidad->id, new PagoInscripcionDocumentosService(), new PagoColegiaturaDocumentosService, new Request() );
                    
                }

                $this->generar_abonos_alumno($alumno->id);   
                
            }




    }


    

}
