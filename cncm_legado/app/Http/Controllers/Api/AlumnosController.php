<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Alumno;

use \Carbon\Carbon;

class AlumnosController extends Controller
{
    public function validar_asistencia(Request $request){

        $alumno = Alumno::find($request->id_alumno);

        $grupos = $alumno->grupos;

        #Se valida asistencia de acuerdo a sus grupos 
        $asistencia = false;
        foreach($grupos as $grupo){
            foreach($grupos->days as $day){
                #SE VA A VALIDAR QUE LA FECHA ACTUAL COINCIDA CON LA DEL GRUPO
                $day_in_english = $this->traer_dia_ingles($day->dia);

            }
            
        }
        return response()->json($grupos);

    }

    public function traer_dia_ingles($dia){

        switch($dia){
            case 'lunes':
                return Carbon::MONDAY;
            case 'martes':
                return Carbon::TUESDAY;
            case 'miercoles':  
                return Carbon::WEDNESDAY;  
            case 'jueves':    
                return Carbon::THURSDAY;
            case 'viernes':
                return Carbon::FRIDAY;
            case 'sabado':
                return Carbon::SATURDAY;
            case 'domingo':             
                return Carbon::SUNDAY;   
        }

        
    }

}


?>