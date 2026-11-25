<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            UserSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            SettingSeeder::class,
            BalanceSeeder::class,
            DemoRequestSeeder::class,
        ]);
    }
}
