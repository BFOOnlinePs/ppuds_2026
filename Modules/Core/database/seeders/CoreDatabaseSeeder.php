<?php

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $this->call([
             AdminSeeder::class,
             PermissionSeeder::class,
             CurrencySeeder::class,
         ]);
    }
}
