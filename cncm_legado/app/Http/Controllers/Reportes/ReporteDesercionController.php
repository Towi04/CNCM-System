<?php

namespace App\Http\Controllers\Reportes;

use App\Models\Grupo;
use App\Models\Alumno;
use App\Models\Especialidad;
use Jenssegers\Date\Date;
use Illuminate\Http\Request;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;

use PDF;

class ReporteDesercionController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);
        
        if($request->semana){
            $semana = $request->semana;
        }else{
            $fecha1 = \Carbon\Carbon::createFromFormat('Y-m-d', date('Y').'-'.date('m').'-'.date('d'));
            $semana = $semana = $fecha1->weekOfYear;
        }

        if($request->year){
            $year = $request->year;
        }else{
            $year = date('Y');
        }

        if($request->id_especialidad){
            $id_especialidad = $request->id_especialidad;
        }else{
            $id_especialidad =null;
        }


        $sucursal = optional(session('sucursal'));

        
        $fecha_inicio = \Carbon\Carbon::now();
        $fecha_inicio->setISODate($year,$semana);
        $fecha_inicio->startOfWeek();
                            
        $fecha_final = \Carbon\Carbon::now();
        $fecha_final->setISODate($year,$semana+1);
        $fecha_final->startOfWeek()->subDay();

        // $id_especialidad = $request->id_especialidad;

        $grupos = Grupo::with('deserciones')->whereIn('status',['Activo','Finalizado'])
        ->where('id_sucursal','=', $sucursal->id)
        ->where('id_especialidad','=', $id_especialidad)->get();

        $especialidades = Especialidad::get()->pluck('nombre','id');

        return view('reportes.desercion',compact('fecha_inicio','fecha_final', 'grupos','semana','year','especialidades'));



    }

    
}
