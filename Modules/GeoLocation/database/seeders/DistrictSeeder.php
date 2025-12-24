<?php

namespace Modules\GeoLocation\database\seeders;

use Illuminate\Database\Seeder;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\DistrictTranslation;
use Modules\GeoLocation\Enums\DistrictType;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $districts = [
            // أحياء رام الله
            [
                'city_name' => 'Ramallah',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Al Masyoun'],
                            'ar' => ['name' => 'المصيون']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.9100,
                        'longitude' => 35.2050
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Al Tireh'],
                            'ar' => ['name' => 'التيرة']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.9150,
                        'longitude' => 35.2100
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Downtown Ramallah'],
                            'ar' => ['name' => 'وسط البلد']
                        ],
                        'type' => DistrictType::DISTRICT->value,
                        'latitude' => 31.9025,
                        'longitude' => 35.2030
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Al Ersal'],
                            'ar' => ['name' => 'الارسال']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.9200,
                        'longitude' => 35.2080
                    ]
                ]
            ],

            // أحياء القدس
            [
                'city_name' => 'Jerusalem',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Old City'],
                            'ar' => ['name' => 'البلدة القديمة']
                        ],
                        'type' => DistrictType::QUARTER->value,
                        'latitude' => 31.7767,
                        'longitude' => 35.2345
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Sheikh Jarrah'],
                            'ar' => ['name' => 'الشيخ جراح']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.7900,
                        'longitude' => 35.2200
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Silwan'],
                            'ar' => ['name' => 'سلوان']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.7700,
                        'longitude' => 35.2400
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'At-Tur'],
                            'ar' => ['name' => 'الطور']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.7850,
                        'longitude' => 35.2450
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Ras al-Amud'],
                            'ar' => ['name' => 'رأس العامود']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.7750,
                        'longitude' => 35.2500
                    ]
                ]
            ],

            // أحياء نابلس
            [
                'city_name' => 'Nablus',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Old City Nablus'],
                            'ar' => ['name' => 'البلدة القديمة نابلس']
                        ],
                        'type' => DistrictType::QUARTER->value,
                        'latitude' => 32.2200,
                        'longitude' => 35.2550
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Ras al-Ein'],
                            'ar' => ['name' => 'رأس العين']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 32.2250,
                        'longitude' => 35.2500
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Al Juneid'],
                            'ar' => ['name' => 'الجنيد']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 32.2300,
                        'longitude' => 35.2600
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Rafidia'],
                            'ar' => ['name' => 'رفيديا']
                        ],
                        'type' => DistrictType::SUBURB->value,
                        'latitude' => 32.2100,
                        'longitude' => 35.2700
                    ]
                ]
            ],

            // أحياء الخليل
            [
                'city_name' => 'Hebron',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Old City Hebron'],
                            'ar' => ['name' => 'البلدة القديمة الخليل']
                        ],
                        'type' => DistrictType::QUARTER->value,
                        'latitude' => 31.5300,
                        'longitude' => 35.1000
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'H1 Area'],
                            'ar' => ['name' => 'منطقة H1']
                        ],
                        'type' => DistrictType::DISTRICT->value,
                        'latitude' => 31.5350,
                        'longitude' => 35.0950
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Wadi al-Tuffah'],
                            'ar' => ['name' => 'وادي التفاح']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.5250,
                        'longitude' => 35.1050
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Abu Sneineh'],
                            'ar' => ['name' => 'أبو سنينة']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.5400,
                        'longitude' => 35.0900
                    ]
                ]
            ],

            // أحياء بيت لحم
            [
                'city_name' => 'Bethlehem',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Manger Square'],
                            'ar' => ['name' => 'ساحة المهد']
                        ],
                        'type' => DistrictType::DISTRICT->value,
                        'latitude' => 31.7045,
                        'longitude' => 35.2020
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Beit Sahour Road'],
                            'ar' => ['name' => 'طريق بيت ساحور']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.7000,
                        'longitude' => 35.2100
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Al Madbasa'],
                            'ar' => ['name' => 'المدبسة']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.7100,
                        'longitude' => 35.1950
                    ]
                ]
            ],

            // أحياء جنين
            [
                'city_name' => 'Jenin',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Central Jenin'],
                            'ar' => ['name' => 'وسط جنين']
                        ],
                        'type' => DistrictType::DISTRICT->value,
                        'latitude' => 32.4600,
                        'longitude' => 35.3000
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Al Marah'],
                            'ar' => ['name' => 'المراح']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 32.4650,
                        'longitude' => 35.2950
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Jenin Industrial Zone'],
                            'ar' => ['name' => 'المنطقة الصناعية جنين']
                        ],
                        'type' => DistrictType::DISTRICT->value,
                        'latitude' => 32.4550,
                        'longitude' => 35.3050
                    ]
                ]
            ],

            // أحياء غزة
            [
                'city_name' => 'Gaza City',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Gaza Old City'],
                            'ar' => ['name' => 'البلدة القديمة غزة']
                        ],
                        'type' => DistrictType::QUARTER->value,
                        'latitude' => 31.5050,
                        'longitude' => 34.4650
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Rimal'],
                            'ar' => ['name' => 'الرمال']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.5100,
                        'longitude' => 34.4600
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Sheikh Ejlin'],
                            'ar' => ['name' => 'الشيخ عجلين']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.5000,
                        'longitude' => 34.4700
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Tal al-Hawa'],
                            'ar' => ['name' => 'تل الهوا']
                        ],
                        'type' => DistrictType::SUBURB->value,
                        'latitude' => 31.4950,
                        'longitude' => 34.4550
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Zeitoun'],
                            'ar' => ['name' => 'الزيتون']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.5200,
                        'longitude' => 34.4800
                    ]
                ]
            ],

            // أحياء خان يونس
            [
                'city_name' => 'Khan Yunis',
                'districts' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Khan Yunis Center'],
                            'ar' => ['name' => 'وسط خان يونس']
                        ],
                        'type' => DistrictType::DISTRICT->value,
                        'latitude' => 31.3450,
                        'longitude' => 34.3050
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Al Amal'],
                            'ar' => ['name' => 'الأمل']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.3500,
                        'longitude' => 34.3100
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Ma\'an'],
                            'ar' => ['name' => 'معن']
                        ],
                        'type' => DistrictType::NEIGHBORHOOD->value,
                        'latitude' => 31.3400,
                        'longitude' => 34.3000
                    ]
                ]
            ]
        ];

        // إدراج البيانات
        foreach ($districts as $cityData) {
            // البحث عن المدينة بالاسم الإنجليزي
            $city = City::whereHas('translations', function ($query) use ($cityData) {
                $query->where('locale', 'en')->where('name', $cityData['city_name']);
            })->first();

            if (!$city) {
                $this->command->warn("المدينة {$cityData['city_name']} غير موجودة");
                continue;
            }

            foreach ($cityData['districts'] as $districtData) {
                $translations = $districtData['translations'];
                unset($districtData['translations']);

                // إضافة معرف المدينة
                $districtData['city_id'] = $city->id;

                // إنشاء أو تحديث الحي
                $district = District::updateOrCreate([
                    'city_id' => $city->id,
                    'latitude' => $districtData['latitude'] ?? null,
                    'longitude' => $districtData['longitude'] ?? null,
                ], $districtData);

                // إدراج الترجمات
                foreach ($translations as $locale => $translation) {
                    DistrictTranslation::updateOrCreate([
                        'district_id' => $district->id,
                        'locale' => $locale,
                    ], $translation);
                }
            }
        }

        $totalDistricts = collect($districts)->sum(fn($city) => count($city['districts']));
        $this->command->info("تم إدراج {$totalDistricts} حي فلسطيني بنجاح!");
    }
}
