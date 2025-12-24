<?php

namespace Modules\GeoLocation\database\seeders;

use Illuminate\Database\Seeder;

class GeoLocationDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $this->call([
             CountrySeeder::class,
             GovernorateSeeder::class,
             CitySeeder::class,
             DistrictSeeder::class,
             PermissionSeeder::class
         ]);
    }
}
