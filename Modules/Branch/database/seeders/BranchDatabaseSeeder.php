<?php

namespace Modules\Branch\database\seeders;

use Illuminate\Database\Seeder;

class BranchDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $this->call([
             PermissionSeeder::class,
         ]);
    }
}
