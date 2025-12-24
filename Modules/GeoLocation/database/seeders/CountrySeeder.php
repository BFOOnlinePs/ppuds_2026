<?php

namespace Modules\GeoLocation\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\Currency;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\CountryTranslation;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $arabCountries = [
            // بلاد الشام
            [
                'code' => 'PS',
                'phone_code' => '+970',
                'currency' => 'ILS',
                'translations' => [
                    'en' => ['name' => 'Palestine'],
                    'ar' => ['name' => 'فلسطين']
                ]
            ],
            [
                'code' => 'JO',
                'phone_code' => '+962',
                'currency' => 'JOD',
                'translations' => [
                    'en' => ['name' => 'Jordan'],
                    'ar' => ['name' => 'الأردن']
                ]
            ],
            [
                'code' => 'SY',
                'phone_code' => '+963',
                'currency' => 'SYP',
                'translations' => [
                    'en' => ['name' => 'Syria'],
                    'ar' => ['name' => 'سوريا']
                ]
            ],
            [
                'code' => 'LB',
                'phone_code' => '+961',
                'currency' => 'LBP',
                'translations' => [
                    'en' => ['name' => 'Lebanon'],
                    'ar' => ['name' => 'لبنان']
                ]
            ],

            // الخليج العربي
            [
                'code' => 'SA',
                'phone_code' => '+966',
                'currency' => 'SAR',
                'translations' => [
                    'en' => ['name' => 'Saudi Arabia'],
                    'ar' => ['name' => 'السعودية']
                ]
            ],
            [
                'code' => 'AE',
                'phone_code' => '+971',
                'currency' => 'AED',
                'translations' => [
                    'en' => ['name' => 'United Arab Emirates'],
                    'ar' => ['name' => 'الإمارات العربية المتحدة']
                ]
            ],
            [
                'code' => 'KW',
                'phone_code' => '+965',
                'currency' => 'KWD',
                'translations' => [
                    'en' => ['name' => 'Kuwait'],
                    'ar' => ['name' => 'الكويت']
                ]
            ],
            [
                'code' => 'QA',
                'phone_code' => '+974',
                'currency' => 'QAR',
                'translations' => [
                    'en' => ['name' => 'Qatar'],
                    'ar' => ['name' => 'قطر']
                ]
            ],
            [
                'code' => 'BH',
                'phone_code' => '+973',
                'currency' => 'BHD',
                'translations' => [
                    'en' => ['name' => 'Bahrain'],
                    'ar' => ['name' => 'البحرين']
                ]
            ],
            [
                'code' => 'OM',
                'phone_code' => '+968',
                'currency' => 'OMR',
                'translations' => [
                    'en' => ['name' => 'Oman'],
                    'ar' => ['name' => 'عُمان']
                ]
            ],

            // العراق واليمن
            [
                'code' => 'IQ',
                'phone_code' => '+964',
                'currency' => 'IQD',
                'translations' => [
                    'en' => ['name' => 'Iraq'],
                    'ar' => ['name' => 'العراق']
                ]
            ],
            [
                'code' => 'YE',
                'phone_code' => '+967',
                'currency' => 'YER',
                'translations' => [
                    'en' => ['name' => 'Yemen'],
                    'ar' => ['name' => 'اليمن']
                ]
            ],

            // شمال أفريقيا
            [
                'code' => 'EG',
                'phone_code' => '+20',
                'currency' => 'EGP',
                'translations' => [
                    'en' => ['name' => 'Egypt'],
                    'ar' => ['name' => 'مصر']
                ]
            ],
            [
                'code' => 'LY',
                'phone_code' => '+218',
                'currency' => 'LYD',
                'translations' => [
                    'en' => ['name' => 'Libya'],
                    'ar' => ['name' => 'ليبيا']
                ]
            ],
            [
                'code' => 'TN',
                'phone_code' => '+216',
                'currency' => 'TND',
                'translations' => [
                    'en' => ['name' => 'Tunisia'],
                    'ar' => ['name' => 'تونس']
                ]
            ],
            [
                'code' => 'DZ',
                'phone_code' => '+213',
                'currency' => 'DZD',
                'translations' => [
                    'en' => ['name' => 'Algeria'],
                    'ar' => ['name' => 'الجزائر']
                ]
            ],
            [
                'code' => 'MA',
                'phone_code' => '+212',
                'currency' => 'MAD',
                'translations' => [
                    'en' => ['name' => 'Morocco'],
                    'ar' => ['name' => 'المغرب']
                ]
            ],
            [
                'code' => 'SD',
                'phone_code' => '+249',
                'currency' => 'SDG',
                'translations' => [
                    'en' => ['name' => 'Sudan'],
                    'ar' => ['name' => 'السودان']
                ]
            ],

            // شرق أفريقيا
            [
                'code' => 'SO',
                'phone_code' => '+252',
                'currency' => 'SOS',
                'translations' => [
                    'en' => ['name' => 'Somalia'],
                    'ar' => ['name' => 'الصومال']
                ]
            ],
            [
                'code' => 'DJ',
                'phone_code' => '+253',
                'currency' => 'DJF',
                'translations' => [
                    'en' => ['name' => 'Djibouti'],
                    'ar' => ['name' => 'جيبوتي']
                ]
            ],

            // جزر القمر وموريتانيا
            [
                'code' => 'KM',
                'phone_code' => '+269',
                'currency' => 'KMF',
                'translations' => [
                    'en' => ['name' => 'Comoros'],
                    'ar' => ['name' => 'جزر القمر']
                ]
            ],
            [
                'code' => 'MR',
                'phone_code' => '+222',
                'currency' => 'MRU',
                'translations' => [
                    'en' => ['name' => 'Mauritania'],
                    'ar' => ['name' => 'موريتانيا']
                ]
            ]
        ];

        // إدراج البيانات مع التحقق من عدم التكرار
        foreach ($arabCountries as $countryData) {
            $translations = $countryData['translations'];
            $currencyCode = $countryData['currency'];
            unset($countryData['translations']);
            unset($countryData['currency']);

            // البحث عن العملة بالكود
            $currency = Currency::where('code', $currencyCode)->first();
            if ($currency) {
                $countryData['currency_id'] = $currency->id;
            } else {
                // إذا العملة مش موجودة، سجل تحذير واتركها null
                $this->command->warn("تحذير: العملة {$currencyCode} غير موجودة في جدول العملات للبلد {$countryData['code']}");
            }

            // استخدام updateOrCreate بدلاً من firstOrCreate
            $country = Country::updateOrCreate([
                'code' => $countryData['code'],
            ], $countryData);

            // استخدام object بدلاً من query منفصل
            foreach ($translations as $locale => $translation) {
                CountryTranslation::updateOrCreate([
                    'country_id' => $country->id,
                    'locale' => $locale,
                ], $translation);
            }
        }

        $this->command->info('تم إدراج ' . count($arabCountries) . ' دولة عربية بنجاح!');
    }
}
