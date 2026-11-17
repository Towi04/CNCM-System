<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        abort_unless(Auth::user()->can('gestionar_permisos'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));
        

        $roles = Role::get();
        $permisos = Permission::get();

        return view('admin.permisos.index', compact('roles','permisos'));
    }


    public function traer_permisos() {
        $roles = Role::select(['id','display_name'])
            ->with(['permissions' => function($query){
                $query->select(['id','display_name']);
            }])
            ->get();

        return response()->json(['roles'=> $roles]);
    }

    public function guardar_permiso(Request $request) {
        $role = Role::find($request->id_role);
        $permiso = Permission::find($request->id_permission);

        if ($role->permissions->where('id',$request->id_permission)->count() == 0) {
            $role->givePermissionTo($permiso);
        } else {
            $role->revokePermissionTo($permiso);
        }

        return $role->perms;
    }
}
