<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Asesoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Notifications\NotificarAsesoriaAsignada;

class AgendarAsesoriaController extends Controller
{
    public function index()
    {
        return view('agendar_asesoria.index');
    }

    public function guardar_asesoria(Request $request)
    {
        $request->validate([
            'id_profesor'       => 'required',
            'id_alumno'         => 'required',
            'fecha'             => 'required',
            'hora_inicio'       => 'required',
            'hora_fin'          => 'required',
            'notas'             => 'nullable',
            'status'            => 'nullable',
        ]);

        $sucursal = optional(session('sucursal'));

        $request->request->add([
            'fecha_inicio'  => Carbon::parse("{$request->input('fecha')} {$request->input('hora_inicio')}"),
            'fecha_final'   => Carbon::parse("{$request->input('fecha')} {$request->input('hora_fin')}"),
            'status'        => $request->input('status') ?? config('asesorias.status.keys.espera_confirmacion','') ,
            'id_sucursal'   => $sucursal->id,
        ]);

        $asesoria = Asesoria::create($request->except('_token'));
        $profesor = $asesoria->profesor;

        $profesor->notify(new NotificarAsesoriaAsignada);


        if ($request->ajax()) {
            return response()->json([
                'success'   => true,
                'message'   => 'Asesoria creada correctamente',
                'asesoria'  => $asesoria
            ]);
        }

        return redirect()->route('agendar-asesoria.index')->with([
            'message' => 'Asesoria creada correctamente'
        ]);
    }

    public function actualizar_asesoria(Request $request, Asesoria $asesoria)
    {
        $request->validate([
            'id_profesor'       => 'required',
            'id_alumno'         => 'required',
            'fecha'             => 'required',
            'hora_inicio'       => 'required',
            'hora_fin'          => 'required',
            'notas'             => 'nullable',
            'status'            => 'nullable',
        ]);

        $request->request->add([
            'fecha_inicio' => Carbon::parse("{$request->input('fecha')} {$request->input('hora_inicio')}"),
            'fecha_final'  => Carbon::parse("{$request->input('fecha')} {$request->input('hora_fin')}"),
            'status'       => $request->input('status') ?? config('asesorias.status.keys.espera_confirmacion','') ,
        ]);

        $asesoria->fill($request->except('_token'));
        $asesoria->save();

        if ($request->ajax()) {
            return response()->json([
                'success'   => true,
                'message'   => 'Asesoria actualizada correctamente',
                'asesoria'  => $asesoria
            ]);
        }

        return redirect()->route('agendar-asesoria.index')->with([
            'message' => 'Asesoria actualizada correctamente'
        ]);
    }

    public function eliminar_asesoria(Request $request, Asesoria $asesoria)
    {
        $asesoria->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Asesoria eliminada correctamente',
            ]);
        }

        return redirect()->route('agendar-asesoria.index')->with([
            'message' => 'asesoria eliminada correctamente'
        ]);
    }

    public function traer_asesorias(Request $request)
    {
        $fecha_inicio = $request->start;
        $fecha_final = $request->end;

        $fecha_final = Carbon::parse($fecha_final)->subDays(1)->toDateTimeString();

        $query = Asesoria::query()
            ->when($request->input('id_profesor'), function ($q, $id_profesor) {
                return $q->where('id_profesor', '=', $id_profesor);
            })
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
            'id_asesoria'   => 'required',
        ]);

        $asesoria = Asesoria::findOrFail($request->input('id_asesoria'));

        $asesoria->update([
            'status'    => $request->input('status'),
            'nota'      => $request->input('nota') ?? ''
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Asesoria actualizada correctamente'
        ]);
    }

    public function horarios_profesor(Request $request)
    {
        $profesor = User::findOrFail($request->input('id_profesor'));
        $horarios = $profesor->horarios;

        $lista_horarios = $horarios->map(function($horario){
            return (object)[
                'id'            => $horario->id,
                'hora_inicio'   => $horario->hora_inicio,
                'hora_final'    => $horario->hora_final,
                'descripcion'   => "{$horario->hora_inicio} - {$horario->hora_final}",
                'dia'           => $horario->dia,
            ];
        });

        return response()->json([
            'success'   => true,
            'horarios'  => $lista_horarios,
        ]);

    }
}
