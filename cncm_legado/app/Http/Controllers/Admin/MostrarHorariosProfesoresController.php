<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\HorarioProfesor;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Yajra\DataTables\Facades\DataTables;

class MostrarHorariosProfesoresController extends Controller
{
    public function index()
    {

        $ids_profesores = HorarioProfesor::toBase()->select('id_profesor')->distinct()->pluck('id_profesor')->toArray();

        $profesores = User::toBase()
            ->select(DB::raw("id,CONCAT(nombres,' ',apellido_paterno,' ',apellido_materno) as nom"))
            ->whereIn('id',$ids_profesores)
            ->pluck('nom','id')
            ->prepend('Todos los profesores','');

        return view('admin.mostrar_horarios.index',compact('profesores'));
    }

    public function datatables(Request $request)
    {
        $query = HorarioProfesor::query()->select('horarios_profesores.*')
            ->when($request->input('id_sucursal'),function($q,$id_sucursal){
                $q->where('id_sucursal',$id_sucursal);
            })
            ->with(['profesor']);

        return DataTables::eloquent($query)
            ->addColumn('full_name',function($model){
                return $model->profesor->full_name;
            })
            ->rawColumns([])
            ->make(true);
    }
}
