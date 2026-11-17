<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class RoleSucursalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sucursal = Sucursal::findOrFail(1);
        $roleAdmin = Role::findOrFail(1);

        $roleAdmin->update([
            'id_sucursal' => $sucursal->id,
        ]);
    }
}
