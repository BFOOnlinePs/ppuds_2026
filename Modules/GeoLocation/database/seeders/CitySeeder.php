<?php

namespace Modules\GeoLocation\database\seeders;

use Illuminate\Database\Seeder;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\CityTranslation;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Enums\CityType;
use Modules\GeoLocation\Enums\CapitalType;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            // محافظة رام الله والبيرة
            [
                'governorate_code' => 'RB',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Ramallah'],
                            'ar' => ['name' => 'رام الله']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::BOTH->value,
                        'latitude' => 31.9073,
                        'longitude' => 35.2044,
                        'population' => 38998
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'al-Bireh'],
                            'ar' => ['name' => 'البيرة']
                        ],
                        'type' => CityType::CITY->value,
                        'latitude' => 31.9154,
                        'longitude' => 35.2140,
                        'population' => 39202
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Beituniya'],
                            'ar' => ['name' => 'بيتونيا']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 31.9167,
                        'longitude' => 35.1833,
                        'population' => 22581
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Beit Rima'],
                            'ar' => ['name' => 'بيت ريما']
                        ],
                        'type' => CityType::VILLAGE->value,
                        'latitude' => 32.0000,
                        'longitude' => 35.2000,
                        'population' => 4500
                    ]
                ]
            ],

            // محافظة القدس
            [
                'governorate_code' => 'JE',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Jerusalem'],
                            'ar' => ['name' => 'القدس']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.7683,
                        'longitude' => 35.2137,
                        'population' => 936425
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Abu Dis'],
                            'ar' => ['name' => 'أبو ديس']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 31.7726,
                        'longitude' => 35.2644,
                        'population' => 12753
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'al-Azariyah'],
                            'ar' => ['name' => 'العزارية']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 31.7750,
                        'longitude' => 35.2667,
                        'population' => 20845
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Beit Anan'],
                            'ar' => ['name' => 'بيت عنان']
                        ],
                        'type' => CityType::VILLAGE->value,
                        'latitude' => 31.8500,
                        'longitude' => 35.1500,
                        'population' => 3200
                    ]
                ]
            ],

            // محافظة نابلس
            [
                'governorate_code' => 'NB',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Nablus'],
                            'ar' => ['name' => 'نابلس']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 32.2211,
                        'longitude' => 35.2544,
                        'population' => 156906
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Asira ash-Shamaliya'],
                            'ar' => ['name' => 'عصيرة الشمالية']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 32.2667,
                        'longitude' => 35.2833,
                        'population' => 3312
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Balata Camp'],
                            'ar' => ['name' => 'مخيم بلاطة']
                        ],
                        'type' => CityType::REFUGEE_CAMP->value,
                        'latitude' => 32.2167,
                        'longitude' => 35.2667,
                        'population' => 27868
                    ]
                ]
            ],

            // محافظة الخليل
            [
                'governorate_code' => 'HB',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Hebron'],
                            'ar' => ['name' => 'الخليل']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.5326,
                        'longitude' => 35.0998,
                        'population' => 215452
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Dura'],
                            'ar' => ['name' => 'دورا']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 31.5083,
                        'longitude' => 35.0375,
                        'population' => 33893
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Yatta'],
                            'ar' => ['name' => 'يطا']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 31.4375,
                        'longitude' => 35.1000,
                        'population' => 64277
                    ]
                ]
            ],

            // محافظة بيت لحم
            [
                'governorate_code' => 'BL',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Bethlehem'],
                            'ar' => ['name' => 'بيت لحم']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.7054,
                        'longitude' => 35.2024,
                        'population' => 28591
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Beit Jala'],
                            'ar' => ['name' => 'بيت جالا']
                        ],
                        'type' => CityType::CITY->value,
                        'latitude' => 31.7167,
                        'longitude' => 35.1833,
                        'population' => 13758
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Beit Sahour'],
                            'ar' => ['name' => 'بيت ساحور']
                        ],
                        'type' => CityType::CITY->value,
                        'latitude' => 31.6924,
                        'longitude' => 35.2086,
                        'population' => 14410
                    ]
                ]
            ],

            // محافظة جنين
            [
                'governorate_code' => 'JN',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Jenin'],
                            'ar' => ['name' => 'جنين']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 32.4611,
                        'longitude' => 35.3000,
                        'population' => 49622
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Qabatiya'],
                            'ar' => ['name' => 'قباطية']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 32.4167,
                        'longitude' => 35.2833,
                        'population' => 19788
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Jenin Camp'],
                            'ar' => ['name' => 'مخيم جنين']
                        ],
                        'type' => CityType::REFUGEE_CAMP->value,
                        'latitude' => 32.4578,
                        'longitude' => 35.2992,
                        'population' => 18533
                    ]
                ]
            ],

            // محافظة طولكرم
            [
                'governorate_code' => 'TK',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Tulkarm'],
                            'ar' => ['name' => 'طولكرم']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 32.3078,
                        'longitude' => 35.0278,
                        'population' => 64532
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Anabta'],
                            'ar' => ['name' => 'عنبتا']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 32.3167,
                        'longitude' => 35.0500,
                        'population' => 8774
                    ]
                ]
            ],

            // محافظة قلقيلية
            [
                'governorate_code' => 'QL',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Qalqilya'],
                            'ar' => ['name' => 'قلقيلية']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 32.1889,
                        'longitude' => 34.9706,
                        'population' => 51683
                    ]
                ]
            ],

            // محافظة سلفيت
            [
                'governorate_code' => 'SF',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Salfit'],
                            'ar' => ['name' => 'سلفيت']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 32.0833,
                        'longitude' => 35.1833,
                        'population' => 9961
                    ]
                ]
            ],

            // محافظة أريحا والأغوار
            [
                'governorate_code' => 'JV',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Jericho'],
                            'ar' => ['name' => 'أريحا']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.8611,
                        'longitude' => 35.4544,
                        'population' => 21454
                    ]
                ]
            ],

            // محافظة طوباس
            [
                'governorate_code' => 'TB',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Tubas'],
                            'ar' => ['name' => 'طوباس']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 32.3208,
                        'longitude' => 35.3694,
                        'population' => 21982
                    ]
                ]
            ],

            // قطاع غزة - محافظة غزة
            [
                'governorate_code' => 'GZ',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Gaza City'],
                            'ar' => ['name' => 'مدينة غزة']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.5017,
                        'longitude' => 34.4668,
                        'population' => 590481
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'al-Shati Camp'],
                            'ar' => ['name' => 'مخيم الشاطئ']
                        ],
                        'type' => CityType::REFUGEE_CAMP->value,
                        'latitude' => 31.5333,
                        'longitude' => 34.4333,
                        'population' => 87560
                    ]
                ]
            ],

            // محافظة شمال غزة
            [
                'governorate_code' => 'NG',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Beit Lahia'],
                            'ar' => ['name' => 'بيت لاهيا']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.5467,
                        'longitude' => 34.5097,
                        'population' => 59540
                    ],
                    [
                        'translations' => [
                            'en' => ['name' => 'Beit Hanoun'],
                            'ar' => ['name' => 'بيت حانون']
                        ],
                        'type' => CityType::TOWN->value,
                        'latitude' => 31.5394,
                        'longitude' => 34.5361,
                        'population' => 32187
                    ]
                ]
            ],

            // محافظة دير البلح
            [
                'governorate_code' => 'DB',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Deir al-Balah'],
                            'ar' => ['name' => 'دير البلح']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.4167,
                        'longitude' => 34.3500,
                        'population' => 64071
                    ]
                ]
            ],

            // محافظة خان يونس
            [
                'governorate_code' => 'KY',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Khan Yunis'],
                            'ar' => ['name' => 'خان يونس']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.3467,
                        'longitude' => 34.3061,
                        'population' => 205922
                    ]
                ]
            ],

            // محافظة رفح
            [
                'governorate_code' => 'RF',
                'cities' => [
                    [
                        'translations' => [
                            'en' => ['name' => 'Rafah'],
                            'ar' => ['name' => 'رفح']
                        ],
                        'type' => CityType::CITY->value,
                        'is_capital' => true,
                        'capital_type' => CapitalType::GOVERNORATE->value,
                        'latitude' => 31.2989,
                        'longitude' => 34.2467,
                        'population' => 152950
                    ]
                ]
            ]
        ];

        // إدراج البيانات
        foreach ($cities as $governorateData) {
            // البحث عن المحافظة بالكود
            $governorate = Governorate::where('code', $governorateData['governorate_code'])->first();

            if (!$governorate) {
                $this->command->warn("المحافظة بالكود {$governorateData['governorate_code']} غير موجودة");
                continue;
            }

            foreach ($governorateData['cities'] as $cityData) {
                $translations = $cityData['translations'];
                unset($cityData['translations']);

                // إضافة معرف المحافظة
                $cityData['governorate_id'] = $governorate->id;

                // إنشاء أو تحديث المدينة باستخدام الاسم كمعرف فريد
                $city = City::updateOrCreate([
                    'governorate_id' => $governorate->id,
                    'latitude' => $cityData['latitude'] ?? null,
                    'longitude' => $cityData['longitude'] ?? null,
                ], $cityData);

                // إدراج الترجمات
                foreach ($translations as $locale => $translation) {
                    CityTranslation::updateOrCreate([
                        'city_id' => $city->id,
                        'locale' => $locale,
                    ], $translation);
                }
            }
        }

        $totalCities = collect($cities)->sum(fn($gov) => count($gov['cities']));
        $this->command->info("تم إدراج {$totalCities} مدينة فلسطينية بنجاح!");
    }
}
