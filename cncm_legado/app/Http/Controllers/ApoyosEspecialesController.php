<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApoyoEspecial;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class ApoyosEspecialesController extends Controller
{
    public function datatables(Request $request)
    {
        $query = ApoyoEspecial::query()
            ->when($request->input('id_sucursal'), function ($q, $id_sucursal) {
                $q->where('id_sucursal', $id_sucursal);
            })
            ->when($request->input('id_grupo'), function ($q, $id_grupo) {
                $q->where('id_grupo', $id_grupo);
            })
            ->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                $q->where('id_especialidad', $id_especialidad);
            })
            ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                $q->where('id_alumno', $id_alumno);
            });

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('fecha_final',function($model){
                return optional($model->fecha_final)->format('d/m/Y');
            })
            ->editColumn('fecha_inicio',function($model){
                return optional($model->fecha_inicio)->format('d/m/Y');
            })
            ->addColumn('buttons', 'apoyos_especiales.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $rules = [
            'id_sucursal'       => 'required',
            'id_especialidad'          => 'required',
            'id_alumno'         => 'required',
            'fecha_final'       => 'required',
            'fecha_inicio'       => 'required',
            'precio'            => 'required',
        ];

        $request->merge([
            'id_sucursal'   => optional(session('sucursal'))->id,
            'fecha_inicio'   => Carbon::parse($request->input('fecha_inicio')),
            'fecha_final'   => Carbon::parse($request->input('fecha_final')),
        ]);

        $data = $request->validate($rules);

        $existe_apoyo_especial = ApoyoEspecial::toBase()
            ->where('id_alumno', $request->input('id_alumno'))
            ->where('id_especialidad', $request->input('id_especialidad'))
            ->where(function($q)use($request){
                return $q->where(function($q2)use($request){
                            return $q2->where('fecha_inicio', '<', $request->input('fecha_inicio'))->where('fecha_final', '>', $request->input('fecha_inicio'));
                            })
                        ->orWhere(function($q2)use($request){
                                return $q2->where('fecha_inicio', '<', $request->input('fecha_final'))->where('fecha_final', '>', $request->input('fecha_final'));
                        });
            })
            ->orderBy('fecha_final')
            ->exists();

        // dd($existe_apoyo_especial);

        if($existe_apoyo_especial) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Existe un apoyo vigente',
                ],422);
            }

            return redirect()->back()->with([
                'message' => 'Existe un apoyo vigente'
            ]);
        }

        // dd($data);

        ApoyoEspecial::create($data);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'El apoyo creado correctamente',
            ]);
        }

        return redirect()->back()->with([
            'message' => 'Apoyo creado correctamente'
        ]);
    }

    public function destroy(ApoyoEspecial $apoyoEspecial, Request $request)
    {
        $apoyoEspecial->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'El apoyo fue eliminado con exito',
            ]);
        }

        return redirect()->back()->with([
            'message' => 'El apoyo fue eliminado con exito',
        ]);
    }
}
