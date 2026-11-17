<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Pago;
use App\Models\Alumno;
use App\Models\Asistencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Jenssegers\Date\Date;
use Illuminate\Support\Carbon;
use App\Models\Configuracion;

class AsistenciasController extends Controller
{
    public function index(){
        return view('asistencias.index');
    }

    public function registrar_asistencia(Request $request){
        $alumno = Alumno::find($request->id_alumno);

        $asistencia = new Asistencia();
        $asistencia->id_alumno = $alumno->id;
        $asistencia->fecha = date('Y-m-d H:i:s');
        $asistencia->save();

        return response()->json([
            'alumno'=>$alumno,
            'asistencia'=>$asistencia
        ], 200);
    }

    public function eliminar_asistencia(Request $request){


        $asistencia = Asistencia::find($request->id);
        $asistencia->delete();

        return response()->json([

            'asistencia'=>$asistencia
        ], 200);
    }

    public function asistencias_personal(Request $request)
    {
        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);

        $tipo = $request->input('tipo') ?? 'dia';

        $sucursal = optional(session('sucursal'));

        if (isset($request->fecha)) {
            $fecha = Carbon::createFromFormat('d-m-Y', $request->input('fecha'));
        } else {
            $fecha = Carbon::today();
        }

        if ($tipo == 'dia') {
            $tipo = 'dia';

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDay();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDay();

            $asistencias = Asistencia::query()
                ->whereBetween('fecha', [$fecha->startOfDay()->format('Y-m-d H:i:s'), $fecha->endOfDay()->format('Y-m-d H:i:s')])
                ->whereNotNull('id_usuario')
                ->whereIn('id_usuario',function($query) use ($sucursal){
                    $query->select('id_usuario')->from('sucursales_usuarios')->where('id_sucursal',$sucursal->id);
                })
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'mes') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subMonth();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addMonth();

            $asistencias = Asistencia::query()
                ->whereBetween('fecha', [$fecha->startOfMonth()->format('Y-m-d H:i:s'), $fecha->endOfMonth()->format('Y-m-d H:i:s')])
                ->whereNotNull('id_usuario')
                ->whereIn('id_usuario',function($query) use ($sucursal){
                    $query->select('id_usuario')->from('sucursales_usuarios')->where('id_sucursal',$sucursal->id);
                })
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'semanal') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDays(7);
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDays(7);

            $asistencias = Asistencia::query()
                ->whereBetween('fecha', [$fecha->startOfWeek()->format('Y-m-d H:i:s'), $fecha->endOfWeek()->format('Y-m-d H:i:s')])
                ->whereNotNull('id_usuario')
                ->whereIn('id_usuario',function($query) use ($sucursal){
                    $query->select('id_usuario')->from('sucursales_usuarios')->where('id_sucursal',$sucursal->id);
                })
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'anual') {
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subYear();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addYear();

            $asistencias = Asistencia::query()
                ->whereBetween('fecha', [$fecha->startOfYear()->format('Y-m-d H:i:s'), $fecha->endOfYear()->format('Y-m-d H:i:s')])
                ->whereNotNull('id_usuario')
                ->whereIn('id_usuario',function($query) use ($sucursal){
                    $query->select('id_usuario')->from('sucursales_usuarios')->where('id_sucursal',$sucursal->id);
                })
                ->orderBy('fecha', 'desc');


            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }



        return view('reportes.asistencias_personal', compact(
            'asistencias',
            'fecha',
            'tipo',
            'fecha_antes',
            'fecha_despues',
        ));
    }
}
