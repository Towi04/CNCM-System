<?php

namespace App\Http\Controllers\Asesorias;

use App\Http\Controllers\Controller;
use App\Models\HorarioProfesor;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class HorariosProfesoresController extends Controller
{
    public function index()
    {
        return view('asesorias.horario_profesor.index');
    }

    public function datatables(Request $request)
    {
        $query = HorarioProfesor::query()
            ->when($request->input('id_profesor'),function($q,$id_profesor){
                $q->where('id_profesor',$id_profesor);
            })
            ->when($request->input('id_sucursal'),function($q,$id_sucursal){
                $q->where('id_sucursal',$id_sucursal);
            });

        return DataTables::eloquent($query)
            ->addColumn('buttons', 'asesorias.horario_profesor.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);
    }

    public function create()
    {
        $horario_profesor = new HorarioProfesor();

        $dias = [];
        foreach(range(1,7) as $dia){
            $dayname = ucfirst(now()->day($dia)->dayName);
            $dias[$dayname] = $dayname;
        }

        return view('asesorias.horario_profesor.create',compact('horario_profesor','dias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'dia'           => 'required',
            'hora_inicio'   => 'required',
            'hora_final'    => 'required',
        ]);

        $sucursal = optional(session('sucursal'));

        $request->request->add([
            'id_sucursal'   => $sucursal->id,
            'id_profesor'   => auth()->id(),
        ]);

        HorarioProfesor::create($request->except('_token'));

        return redirect()->route('asesorias.horarios-profesores.index')
            ->with(['message' => 'Horario creado correctamente']);
    }

    public function edit(HorarioProfesor $horarioProfesor)
    {
        $dias = [];

        foreach(range(1,7) as $dia){
            $dayname = ucfirst(now()->day($dia)->dayName);
            $dias[$dayname] = $dayname;
        }

        return view('asesorias.horario_profesor.edit', [
            'horarioProfesor'           => $horarioProfesor,
            'dias'                      => $dias,
        ]);
    }

    public function update(Request $request, HorarioProfesor $horarioProfesor)
    {
        $request->validate([
            'dia'           => 'required',
            'hora_inicio'   => 'required',
            'hora_final'    => 'required',
        ]);

        $sucursal = optional(session('sucursal'));
        $request->request->add([
            'id_sucursal'   => $sucursal->id,
            'id_profesor'   => auth()->id(),
        ]);

        $horarioProfesor->update($request->except('_token'));

        return redirect()->route('asesorias.horarios-profesores.index')->with([
            'message' => 'Se actualizó el horario con exito'
        ]);
    }

    public function destroy(HorarioProfesor $horarioProfesor, Request $request)
    {
        $horarioProfesor->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'El horario fue eliminado con exito',
            ]);
        }

        return redirect()->route('materias.index')->with([
            'message' => 'El horario fue eliminado con exito'
        ]);
    }
}
