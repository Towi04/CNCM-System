<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeders extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sucursales = config('settings.sucursales');

        foreach ($sucursales as $sucursal) {
            if (Sucursal::where('nombre',$sucursal['nombre'])->exists()) {
                Sucursal::where('nombre',$sucursal['nombre'])->update($sucursal);
            }else{
                Sucursal::create($sucursal);
            }
        }
    }
}
