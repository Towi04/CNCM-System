<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sucursal;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;

class SucursalesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        abort_unless(Auth::user()->can('gestionar_sucursales'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.sucursales.index');
    }

    public function datatables()
    {
        $query = Sucursal::query()->select(['id', 'nombre', 'direccion','municipio','estado','telefono','rfc']);

        return DataTables::eloquent($query)
            ->addColumn('buttons', 'admin.sucursales.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);
    }

    public function show(Sucursal $sucursal)
    {
        Session::put('sucursal',$sucursal);

        $user = Auth::user();
        $user->id_ultima_sucursal = $sucursal->id;
        $user->save();

        return redirect()->back();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.sucursales.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules =[
            'nombre' => 'required|unique:sucursales',
        ];

        $this->validate($request,$rules);

        Sucursal::create($request->all());

        return redirect()->route('admin.sucursales.index')->with([
            'message' => 'Se agregó la sucursal con éxito'
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Sucursal  $sucursal
     * @return \Illuminate\Http\Response
     */
    public function edit(Sucursal $sucursal)
    {
        return view('admin.sucursales.edit',compact('sucursal'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Sucursal $sucursal
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Sucursal $sucursal)
    {
        $rules = [
            'nombre' => "required|unique:sucursales,nombre,{$sucursal->id}",
        ];

        $this->validate($request,$rules);

        $sucursal->fill($request->all());

        $sucursal->save();

        Session::put('sucursal',$sucursal);

        return redirect()->route('admin.sucursales.index')->with([
            'message' => 'Se actualizó la sucursal con éxito'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Sucursal  $sucursal
     * @return \Illuminate\Http\Response
     */
    public function destroy(Sucursal $sucursal,Request $request)
    {
        $sucursal->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Sucursal eliminada correctamente',
            ]);
        }

        return redirect()->route('admin.sucursales.index')->with([
            'message' => "La sucursal {$sucursal-> display_name} se eliminó con éxito"
        ]);
    }

    public function asignar_sucursal()
    {
        $sucursales = Sucursal::get();

        return view('admin.sucursales.asignar',compact('sucursales'));
    }

    public function asociar_sucursal(Request $request)
    {
        $request->validate([
            'sucursales' => 'required'
        ]);

        $usuario = Auth::user();

        $lista_sucursales = $request->input('sucursales',[]);

        $usuario->sucursales()->sync($lista_sucursales);

        $sucursales = Sucursal::find($lista_sucursales);

        Session::put('sucursales',$sucursales);
        Session::put('sucursal',$sucursales->first());

        return redirect()->route('home');
    }
}
