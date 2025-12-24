<?php

namespace Modules\Items\database\seeders;

use Illuminate\Database\Seeder;

class ItemsDatabaseSeeder extends Seeder
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
