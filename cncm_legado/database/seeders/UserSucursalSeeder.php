<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSucursalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sucursal = Sucursal::firstOrFail();
        $userAdmin = User::firstOrFail();

        $userAdmin->sucursales()->sync([$sucursal->id]);
    }
}
