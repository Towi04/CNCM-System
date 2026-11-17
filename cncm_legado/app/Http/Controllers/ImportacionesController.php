<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\PagosImport;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class ImportacionesController extends Controller
{
    public function pagos_importar(){
        $sucursales = Sucursal::get()->pluck('nombre','id')->prepend('Selecciona una sucursal','');

         return view('importaciones.pagos',compact('sucursales'));
    }

    public function pagos_importar_store(Request $request){
        $archivo = $request->file('pagos');
        $id_sucursal = Session::get('sucursal');
        DB::beginTransaction();

        try {
           

            $import = new PagosImport($id_sucursal);
            $import->import($archivo);

            DB::commit();

            Session::flash('success','Se importo con éxito');
            return redirect()->back();

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            DB::rollback();
            return redirect()->back()->with(['error' => 'Error en la importación: El archivo no es válido' ])->withInput();
        }
    }
}
