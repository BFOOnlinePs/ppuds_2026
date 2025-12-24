<?php

namespace Modules\Clinic\database\seeders;

use Illuminate\Database\Seeder;

class ClinicDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $this->call([
             PermissionSeeder::class
         ]);
    }
}
