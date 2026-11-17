<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        # > php artisan db:seed --class=PermissionRoleSeeder

        # NOTE: NECESARIO PARA LIMPIAR LA CACHE DEL PAQUETE SPATIE
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        # OBTIENE ROL ADMINISTRADOR
        $roleAdmin = Role::findOrFail(1);

        # OBTIENE TODOS LOS PERMISOS Y SE LOS ASIGNA
        $permissions = Permission::all();
        $roleAdmin->syncPermissions($permissions);

        # OBTIENE AL PRIMER USUARIO Y SE ASIGNA EL ROL DE ADMINISTRADOR
        $user = User::findOrFail(1);
        $user->assignRole($roleAdmin);
    }
}
