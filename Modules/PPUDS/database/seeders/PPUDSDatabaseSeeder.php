<?php

namespace Modules\PPUDS\Database\Seeders;

use Illuminate\Database\Seeder;

class PPUDSDatabaseSeeder extends Seeder
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
