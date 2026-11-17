<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use Illuminate\Database\Seeder;

class EspecialidadTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        # > php artisan db:seed --class=EspecialidadTableSeeder
        $especialidades = config('settings.especialidades');

        foreach ($especialidades as $especialidad) {
            if (Especialidad::where('nombre',$especialidad['nombre'])->exists()) {
                Especialidad::where('nombre',$especialidad['nombre'])->update($especialidad);
            }else{
                Especialidad::create($especialidad);
            }
        }
    }
}
