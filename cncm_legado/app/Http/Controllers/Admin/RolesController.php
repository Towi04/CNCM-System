<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;

class RolesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        abort_unless(Auth::user()->can('gestionar_roles'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $roles = Role::get();

        return view('admin.roles.index', compact('roles'));
    }

    public function datatables()
    {
        $sucursal = session('sucursal');

        // $query = Role::query()->select(['id', 'name', 'display_name'])
        //     ->where('id_sucursal', optional($sucursal)->id);

        $query = Role::query()->select(['id', 'name', 'display_name']);

        return DataTables::eloquent($query)
            ->addColumn('buttons', 'admin.roles.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.roles.create');
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
            'display_name' => 'required|unique:roles,display_name',
        ];

        $this->validate($request, $rules);

        $name = str_replace(' ', '_', strtolower($request->input('display_name')));

        $sucursal = session('sucursal');

        Role::create([
            'name'          => $name,
            'display_name'  => $request->input('display_name'),
            'description'   => $request->input('description'),
            'id_sucursal'   => optional($sucursal)->id,
        ]);

        return redirect()->route('admin.roles.index')->with([
            'message' => 'Se agregó el rol con éxito'
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function edit(Role $role)
    {
        return view('admin.roles.edit', compact('role'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role)
    {
        $rules = [
            'display_name' => "required|unique:roles,display_name,{$role->id}",
        ];

        $this->validate($request, $rules);

        $name = str_replace(' ', '_', strtolower($request->input('display_name')));

        $sucursal = session('sucursal');

        $role->name = $name;
        $role->display_name = $request->input('display_name');
        $role->description = $request->input('description');
        $role->id_sucursal = optional($sucursal)->id;
        $role->save();

        return redirect()->route('admin.roles.index')->with([
            'message' => 'Se actualizó el rol con éxito'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role, Request $request)
    {
        $role->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Rol  eliminado',
            ]);
        }

        return redirect()->route('admin.roles.index')->with([
            'message' => "El rol {$role->display_name} se eliminó con éxito"
        ]);
    }
}
