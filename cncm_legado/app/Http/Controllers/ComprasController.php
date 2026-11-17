<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Compra;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Auth;

class ComprasController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id_producto)
    {
        $producto = Producto::find($id_producto);
        return view('admin.productos.compras.index', compact('producto'));
    }

    public function datatables(Request $request)
    {
        $query = Compra::with(['usuario'])
            ->select(['id', 'cantidad', 'fecha', 'id_producto','id_usuario'])
            ->when($request->input('id_producto'),function($q,$id_producto){
                $q->where('id_producto',$id_producto);
            });;

        $datatables = DataTables::eloquent($query)
        ->editColumn('usuario.nombres', function($model){
            return $model->usuario->fullname;
        })
        ->addColumn('existencias', function($model){
            return $model->existencias;
        })
            ->addColumn('buttons', 'admin.productos.compras.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);

        return $datatables;
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        $producto = Producto::find($id);

        return view('admin.productos.compras.create',[
            'producto' => $producto
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
            'id_producto'        => 'required',
            'cantidad'   => 'required',
            'fecha'     => 'required',
        ];

        $this->validate($request, $rules);

        $compra = Compra::create($request->except('_token'));

        $compra->id_usuario = Auth::id();
        $compra->save();


        return redirect()
            ->route('admin.compras.index', $request->id_producto)
            ->with(['message' => 'Se creó el compra con éxito']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Compra  $compra
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $compra = Compra::find($id);
        $producto = $compra->producto;
        return view('admin.productos.compras.edit', [
            'compra'      => $compra,
            'producto' => $producto
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Compra  $compra
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'fecha'        => 'required',
            'cantidad'   => 'nullable',
        ];

        $this->validate($request, $rules);

        $compra = Compra::find($id);
        // dd($compra);

        $compra->fill($request->except('_token'));
        $compra->save();

        return redirect()
            ->route('admin.compras.index', $compra->id_producto)
            ->with(['message' => 'Se actualizó el compra correctamente']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Compra  $compra
     * @return \Illuminate\Http\Response
     */
    public function destroy(Compra $compra, Request $request)
    {
        $compra->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Compra eliminado",
                'id'      => $compra->id
            ]);
        }

        return redirect()
            ->route('admin.compras.index')
            ->with(['message' => "Compra eliminado"]);
    }
}
