<?php

namespace Modules\Marketing\database\seeders;

use Illuminate\Database\Seeder;

class MarketingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $this->call([
             PermissionSeeder::class,
             LoyaltyTierSeeder::class
         ]);
    }
}
