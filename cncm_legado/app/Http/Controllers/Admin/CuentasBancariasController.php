<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\CuentaBancaria;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;

class CuentasBancariasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
    */
    public function index()
    {
        return view('admin.cuentas_bancarias.index');
    }

    public function datatables(Request $request)
    {
        $query = CuentaBancaria::query()
        ->when($request->input('id_sucursal'),function($q,$id_sucursal){
            $q->where('id_sucursal',$id_sucursal);
        });

        return DataTables::eloquent($query)
            ->addColumn('buttons', 'admin.cuentas_bancarias.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);
    }

    public function show(CuentaBancaria $cuentaBancaria)
    {
        abort_unless(Auth::user()->can('consultar_cuenta_bancaria'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.cuentas_bancarias.show',compact('cuentaBancaria'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort_unless(Auth::user()->can('crear_cuenta_bancaria'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.cuentas_bancarias.create',[
            'cuentaBancaria'    => new CuentaBancaria(),
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
            'nombre'                            => 'required',
            'banco'                             => 'nullable',
            'no_cuenta'                         => 'nullable',
            'id_sucursal'                       => 'required',
        ];

        $request->request->add([
            'id_sucursal'   => optional(session('sucursal'))->id,
        ]);

        $data = $request->validate($rules);

        CuentaBancaria::create($data);

        return redirect()->route('admin.cuentas-bancarias.index')->with([
            'message' => 'Se agregó la cuenta bancaria con éxito',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CuentaBancaria  $cuentaBancaria
     * @return \Illuminate\Http\Response
     */
    public function edit(CuentaBancaria $cuentaBancaria)
    {
        abort_unless(Auth::user()->can('editar_cuenta_bancaria'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.cuentas_bancarias.edit', [
            'cuentaBancaria'    => $cuentaBancaria,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CuentaBancaria  $cuentaBancaria
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CuentaBancaria $cuentaBancaria)
    {
        $rules = [
            'nombre'                            => 'required',
            'banco'                             => 'nullable',
            'no_cuenta'                         => 'nullable',
            'id_sucursal'                       => 'required',

        ];

        $request->request->add([
            'id_sucursal'   => optional(session('sucursal'))->id,
        ]);

        $data = $this->validate($request, $rules);
        $cuentaBancaria->fill($data);

        $cuentaBancaria->save();

        return redirect()->route('admin.cuentas-bancarias.index')->with([
            'message' => 'Se actualizó la cuenta bancaria con éxito'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CuentaBancaria  $cuentaBancaria
     * @return \Illuminate\Http\Response
     */
    public function destroy(CuentaBancaria $cuentaBancaria, Request $request)
    {
        $cuentaBancaria->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'La cuenta bancaria fue eliminada con éxito',
            ]);
        }

        return redirect()->route('admin.cuentas-bancarias.index')->with([
            'message' => 'La cuenta bancaria fue eliminada con éxito'
        ]);
    }
}
