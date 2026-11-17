<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Configuracion;

class ConfiguracionesController extends Controller
{
    public function index(){
        $configuraciones = Configuracion::get();

        return view('admin.configuraciones.index', compact('configuraciones'));
    }

    public function actualizar_informacion_xeditables(Request $request)
    {
        $config = Configuracion::find($request->pk);
        $config[$request->name] = $request->value;
        $config->save();

        return response()->json([
            'config' => $config
        ]);
    }
}
