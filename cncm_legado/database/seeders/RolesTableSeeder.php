<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = config('settings.roles');

        foreach ($roles as $rol) {
            if (Role::where('name',$rol['name'])->exists()) {
                Role::where('name',$rol['name'])->update($rol);
            }else{
                Role::create($rol);
            }
        }
    }
}
