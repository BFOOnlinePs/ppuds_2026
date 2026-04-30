<?php

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Entities\AppVersion;

class AppVersionSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'platform' => 'android',
                'min_version' => '1.0.0',
                'latest_version' => '1.0.0',
                'store_url' => 'https://play.google.com/store/apps/details?id=com.abumaraqa.app',
                'maintenance_mode' => false,
                'message' => 'تحديث هام لتحسين الأداء',
            ],
            [
                'platform' => 'ios',
                'min_version' => '1.0.0',
                'latest_version' => '1.0.0',
                'store_url' => 'https://apps.apple.com/app/id123456',
                'maintenance_mode' => false,
                'message' => 'تحديث هام لتحسين الأداء',
            ],
        ];

        foreach ($data as $row) {
            AppVersion::firstOrCreate(
                ['platform' => $row['platform']],
                $row
            );
        }
    }
}
