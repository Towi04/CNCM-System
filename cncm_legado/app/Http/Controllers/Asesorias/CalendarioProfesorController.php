<?php

namespace App\Http\Controllers\Asesorias;

use Carbon\Carbon;
use App\Models\Asesoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

class CalendarioProfesorController extends Controller
{
    public function index()
    {
        return view('asesorias.calendario_profesor.index');
    }

    public function traer_asesorias(Request $request)
    {
        $fecha_inicio = $request->start;
        $fecha_final = $request->end;

        $fecha_final = Carbon::parse($fecha_final)->subDays(1)->toDateTimeString();

        $query = Asesoria::query()
            ->where('id_profesor', '=', auth()->id())
            ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                return $q->where('id_alumno', '=', $id_alumno);
            })
            ->when($request->input('id_sucursal'), function ($q, $id_sucursal) {
                return $q->where('id_sucursal', '=', $id_sucursal);
            })
            ->when($request->input('status'), function ($q, $status) {
                return $q->where('status', '=', $status);
            })
            ->whereBetween('created_at', [$fecha_inicio, $fecha_final])
            // ->whereIn('status', array_keys(config('asesorias.status.keys',[])))
            ->has('profesor')
            ->with(['profesor.horarios', 'alumno']);

        return response()->json([
            'asesorias' => $query->get(),
        ]);
    }

    public function status_asesoria(Request $request)
    {
        $request->validate([
            'status'        => ['required',Rule::in(array_keys( config('asesorias.status.values',[]) ) )],
            'notas'         => 'nullable',
            'id_asesoria'   => 'required',
        ]);

        $asesoria = Asesoria::findOrFail($request->input('id_asesoria'));

        $asesoria->update([
            'status'    => $request->input('status'),
            'nota'      => $request->input('notas') ?? ''
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Asesoria actualizada correctamente'
        ]);
    }
}
