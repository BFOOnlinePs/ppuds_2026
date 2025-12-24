<?php

namespace Modules\GeoLocation\database\seeders;

use Illuminate\Database\Seeder;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Entities\GovernorateTranslation;

class GovernorateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // البحث عن فلسطين
        $palestine = Country::where('code', 'PS')->first();

        if (!$palestine) {
            $this->command->error('فلسطين غير موجودة في جدول البلدان. يرجى تشغيل CountrySeeder أولاً.');
            return;
        }

        $governorates = [
            // الضفة الغربية
            [
                'code' => 'RB',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Ramallah and al-Bireh'],
                    'ar' => ['name' => 'رام الله والبيرة']
                ]
            ],
            [
                'code' => 'JE',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Jerusalem'],
                    'ar' => ['name' => 'القدس']
                ]
            ],
            [
                'code' => 'BL',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Bethlehem'],
                    'ar' => ['name' => 'بيت لحم']
                ]
            ],
            [
                'code' => 'HB',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Hebron'],
                    'ar' => ['name' => 'الخليل']
                ]
            ],
            [
                'code' => 'NB',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Nablus'],
                    'ar' => ['name' => 'نابلس']
                ]
            ],
            [
                'code' => 'JN',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Jenin'],
                    'ar' => ['name' => 'جنين']
                ]
            ],
            [
                'code' => 'TK',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Tulkarm'],
                    'ar' => ['name' => 'طولكرم']
                ]
            ],
            [
                'code' => 'QL',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Qalqilya'],
                    'ar' => ['name' => 'قلقيلية']
                ]
            ],
            [
                'code' => 'SF',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Salfit'],
                    'ar' => ['name' => 'سلفيت']
                ]
            ],
            [
                'code' => 'JV',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Jericho and the Jordan Valley'],
                    'ar' => ['name' => 'أريحا والأغوار']
                ]
            ],
            [
                'code' => 'TB',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Tubas'],
                    'ar' => ['name' => 'طوباس']
                ]
            ],

            // قطاع غزة
            [
                'code' => 'GZ',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Gaza'],
                    'ar' => ['name' => 'غزة']
                ]
            ],
            [
                'code' => 'NG',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'North Gaza'],
                    'ar' => ['name' => 'شمال غزة']
                ]
            ],
            [
                'code' => 'DB',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Deir al-Balah'],
                    'ar' => ['name' => 'دير البلح']
                ]
            ],
            [
                'code' => 'KY',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Khan Yunis'],
                    'ar' => ['name' => 'خان يونس']
                ]
            ],
            [
                'code' => 'RF',
                'country_id' => $palestine->id,
                'translations' => [
                    'en' => ['name' => 'Rafah'],
                    'ar' => ['name' => 'رفح']
                ]
            ]
        ];

        // إدراج البيانات مع التحقق من عدم التكرار
        foreach ($governorates as $governorateData) {
            $translations = $governorateData['translations'];
            unset($governorateData['translations']);

            // إنشاء أو تحديث المحافظة
            $governorate = Governorate::updateOrCreate([
                'code' => $governorateData['code'],
                'country_id' => $governorateData['country_id'],
            ], $governorateData);

            // إدراج الترجمات
            foreach ($translations as $locale => $translation) {
                GovernorateTranslation::updateOrCreate([
                    'governorate_id' => $governorate->id,
                    'locale' => $locale,
                ], $translation);
            }
        }

        $this->command->info('تم إدراج ' . count($governorates) . ' محافظة فلسطينية بنجاح!');
    }
}
