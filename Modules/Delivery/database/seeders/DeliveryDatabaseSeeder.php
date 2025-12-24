<?php

namespace Modules\Delivery\database\seeders;

use Illuminate\Database\Seeder;

class DeliveryDatabaseSeeder extends Seeder
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
