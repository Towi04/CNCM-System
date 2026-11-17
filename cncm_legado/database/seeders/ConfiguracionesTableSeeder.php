<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Configuracion;

class ConfiguracionesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         # > php artisan db:seed --class=PermissionsTableSeeder
         $configuraciones = config('settings.configuraciones');

         foreach ($configuraciones as $configuracion) {
             if (Configuracion::where('nombre', $configuracion['nombre'])->exists()) {
                Configuracion::where('nombre', $permission['nombre'])->update($configuracion);
             } else {
                Configuracion::create($configuracion);
             }
         }
    }
}
