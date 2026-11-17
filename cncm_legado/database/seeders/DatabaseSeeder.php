<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            SucursalSeeders::class,
            RolesTableSeeder::class,
            RoleSucursalSeeder::class,
            PermissionsTableSeeder::class,
            UsersTableSeeder::class,
            UserSucursalSeeder::class,
            PermissionRoleSeeder::class,
        ]);
    }
}
