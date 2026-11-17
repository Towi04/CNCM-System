<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;



class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         # > php artisan db:seed --class=PermissionsTableSeeder
        $permissions = config('settings.permissions');

        foreach ($permissions as $permission) {
            if (Permission::where('name', $permission['name'])->exists()) {
                Permission::where('name', $permission['name'])->update($permission);
            } else {
                Permission::create($permission);
            }
        }
    }
}
