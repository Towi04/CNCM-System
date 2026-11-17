<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;
use Yajra\DataTables\Facades\DataTables;

class MateriasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        abort_unless(Auth::user()->can('listar_materias'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $especialidad = Especialidad::find($id);
        return view('materias.index', compact('id','especialidad'));
    }

    public function datatables(Request $request)
    {
        $query = Materia::with(['especialidad'])
        ->when($request->id_especialidad, function ($query, $id_especialidad){
            return $query->where('id_especialidad','=',$id_especialidad);
        });


        return DataTables::eloquent($query)
            ->addColumn('buttons', 'materias.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        abort_unless(Auth::user()->can('crear_materia'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $sucursal = optional(session('sucursal'));
        $especialidad = Especialidad::find($id);

        $max_orden = Materia::where('id_especialidad','=', $id)->max('orden');

        return view('materias.create',[
            'materia'           => new Materia,
            'especialidades'    => Especialidad::query()->pluck('nombre','id')->sort()->prepend('Selecciona una especialidad',''),
            'especialidad'    => $especialidad,
            'max_orden' => $max_orden+1
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'id_sucursal'       => 'required',
            'id_especialidad'   => 'required',
            'nombre'            => 'required',
            'fase'              => 'required',
            'orden'             => 'required',
            'semanas'             => 'nullable',
        ];

        $request->request->add([
            'id_sucursal'         => optional(session('sucursal'))->id,
        ]);

        $data = $request->validate($rules);

        Materia::create($data);

        return redirect()->route('materias.index',$request->id_especialidad)->with([
            'message' => 'Se agregó la materia con éxito',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Materia  $materia
     * @return \Illuminate\Http\Response
     */
    public function edit(Materia $materia)
    {
        abort_unless(Auth::user()->can('editar_materia'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $sucursal = optional(session('sucursal'));

        return view('materias.edit', [
            'materia'           => $materia,
            'especialidades'    => Especialidad::query()->pluck('nombre','id')->sort()->prepend('Selecciona una especialidad','')
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Materia  $materia
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Materia $materia)
    {
        $rules = [
            'id_sucursal'           => 'required',
            'id_especialidad'       => 'required',
            'nombre'                => 'required',
            'fase'                  => 'required',
            'orden'                 => 'required',
            'semanas'             => 'nullable',
        ];

        $request->request->add([
            'id_sucursal'         => optional(session('sucursal'))->id,
        ]);

        $data = $this->validate($request, $rules);
        $materia->fill($data);

        $materia->save();

        return redirect()->route('materias.index', $materia->id_especialidad)->with([
            'message' => 'Se actualizó la materia con éxito'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Materia  $materia
     * @return \Illuminate\Http\Response
     */
    public function destroy(Materia $materia, Request $request)
    {
        $materia->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'La materia fue eliminada con éxito',
            ]);
        }

        return redirect()->route('materias.index')->with([
            'message' => 'La materia fue eliminada con éxito'
        ]);
    }

    public function traer_materias_select2(Request $request)
    {
        $term  = $request->input('term');
        $page = $request->input('page', 1);

        $resultCount = 10;
        $offset = ($page - 1) * $resultCount;

        $results = Materia::query()
            ->where('nombre', 'like', "%{$term}%")
            ->when($request->input('id_especialidad'),function($q,$id_especialidad){
                $q->where('id_especialidad',$id_especialidad);
            })
            ->orderBy('nombre', 'asc')
            ->skip($offset)
            ->take($resultCount)
            ->get();

        $count = Materia::query()
            ->where('nombre', 'like', "%{$term}%")
            ->when($request->input('especialidad'),function($q,$especialidad){
                $q->where('especialidad',$especialidad);
            })
            ->when($request->input('id_sucursal'),function($q,$sucursal){
                $q->where('id_sucursal',$sucursal);
            })
            ->count();

        $endCount = $offset + $resultCount;
        $morePages = $count > $endCount;

        if ($request->ajax()) {
            return response()->json([
                'results'       => $results,
                'pagination'    => [
                    'more' => $morePages
                ]
            ]);
        }

        return redirect()->back();
    }
}
