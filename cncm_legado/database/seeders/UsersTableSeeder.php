<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $usuarios = config('settings.users');

        foreach ($usuarios as $usuario) {
            $data = [
                ['nombres','=',$usuario['nombres'] ],
                ['apellido_paterno','=',$usuario['apellido_paterno'] ],
                ['apellido_materno','=',$usuario['apellido_materno'] ],
            ];
            if (!User::where($data)->exists()) {
                User::create($usuario);
            }
        }
    }
}
