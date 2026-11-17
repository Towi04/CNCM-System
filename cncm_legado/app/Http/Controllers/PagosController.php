<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pago;

class PagosController extends Controller
{
    
    public function actualizar_informacion(Request $request){
        $alumnos = Pago::find($request->pk);
        $alumnos[$request->name] = $request->value;
        $alumnos->save();


    }
}
