<?php

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\CurrencyTranslation;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            // العملات العربية
            [
                'code' => 'ILS',
                'symbol' => '₪',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 3.7000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Israeli New Shekel',
                        'plural_name' => 'Israeli New Shekels'
                    ],
                    'ar' => [
                        'name' => 'الشيكل الإسرائيلي',
                        'plural_name' => 'شواكل إسرائيلية'
                    ]
                ]
            ],
            [
                'code' => 'JOD',
                'symbol' => 'د.أ',
                'symbol_position' => 'after',
                'decimal_places' => 3,
                'exchange_rate' => 0.7090,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Jordanian Dinar',
                        'plural_name' => 'Jordanian Dinars'
                    ],
                    'ar' => [
                        'name' => 'الدينار الأردني',
                        'plural_name' => 'دنانير أردنية'
                    ]
                ]
            ],
            [
                'code' => 'SAR',
                'symbol' => 'ر.س',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 3.7500,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Saudi Riyal',
                        'plural_name' => 'Saudi Riyals'
                    ],
                    'ar' => [
                        'name' => 'الريال السعودي',
                        'plural_name' => 'ريالات سعودية'
                    ]
                ]
            ],
            [
                'code' => 'AED',
                'symbol' => 'د.إ',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 3.6725,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'UAE Dirham',
                        'plural_name' => 'UAE Dirhams'
                    ],
                    'ar' => [
                        'name' => 'الدرهم الإماراتي',
                        'plural_name' => 'دراهم إماراتية'
                    ]
                ]
            ],
            [
                'code' => 'KWD',
                'symbol' => 'د.ك',
                'symbol_position' => 'after',
                'decimal_places' => 3,
                'exchange_rate' => 0.3070,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Kuwaiti Dinar',
                        'plural_name' => 'Kuwaiti Dinars'
                    ],
                    'ar' => [
                        'name' => 'الدينار الكويتي',
                        'plural_name' => 'دنانير كويتية'
                    ]
                ]
            ],
            [
                'code' => 'QAR',
                'symbol' => 'ر.ق',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 3.6400,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Qatari Riyal',
                        'plural_name' => 'Qatari Riyals'
                    ],
                    'ar' => [
                        'name' => 'الريال القطري',
                        'plural_name' => 'ريالات قطرية'
                    ]
                ]
            ],
            [
                'code' => 'BHD',
                'symbol' => 'د.ب',
                'symbol_position' => 'after',
                'decimal_places' => 3,
                'exchange_rate' => 0.3770,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Bahraini Dinar',
                        'plural_name' => 'Bahraini Dinars'
                    ],
                    'ar' => [
                        'name' => 'الدينار البحريني',
                        'plural_name' => 'دنانير بحرينية'
                    ]
                ]
            ],
            [
                'code' => 'OMR',
                'symbol' => 'ر.ع',
                'symbol_position' => 'after',
                'decimal_places' => 3,
                'exchange_rate' => 0.3845,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Omani Rial',
                        'plural_name' => 'Omani Rials'
                    ],
                    'ar' => [
                        'name' => 'الريال العماني',
                        'plural_name' => 'ريالات عمانية'
                    ]
                ]
            ],
            [
                'code' => 'EGP',
                'symbol' => 'ج.م',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 48.9000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Egyptian Pound',
                        'plural_name' => 'Egyptian Pounds'
                    ],
                    'ar' => [
                        'name' => 'الجنيه المصري',
                        'plural_name' => 'جنيهات مصرية'
                    ]
                ]
            ],
            [
                'code' => 'LBP',
                'symbol' => 'ل.ل',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 89500.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Lebanese Pound',
                        'plural_name' => 'Lebanese Pounds'
                    ],
                    'ar' => [
                        'name' => 'الليرة اللبنانية',
                        'plural_name' => 'ليرات لبنانية'
                    ]
                ]
            ],
            [
                'code' => 'SYP',
                'symbol' => 'ل.س',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 13000.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Syrian Pound',
                        'plural_name' => 'Syrian Pounds'
                    ],
                    'ar' => [
                        'name' => 'الليرة السورية',
                        'plural_name' => 'ليرات سورية'
                    ]
                ]
            ],
            [
                'code' => 'IQD',
                'symbol' => 'د.ع',
                'symbol_position' => 'after',
                'decimal_places' => 3,
                'exchange_rate' => 1470.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Iraqi Dinar',
                        'plural_name' => 'Iraqi Dinars'
                    ],
                    'ar' => [
                        'name' => 'الدينار العراقي',
                        'plural_name' => 'دنانير عراقية'
                    ]
                ]
            ],
            [
                'code' => 'YER',
                'symbol' => 'ر.ي',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 1540.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Yemeni Rial',
                        'plural_name' => 'Yemeni Rials'
                    ],
                    'ar' => [
                        'name' => 'الريال اليمني',
                        'plural_name' => 'ريالات يمنية'
                    ]
                ]
            ],
            [
                'code' => 'LYD',
                'symbol' => 'د.ل',
                'symbol_position' => 'after',
                'decimal_places' => 3,
                'exchange_rate' => 4.8500,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Libyan Dinar',
                        'plural_name' => 'Libyan Dinars'
                    ],
                    'ar' => [
                        'name' => 'الدينار الليبي',
                        'plural_name' => 'دنانير ليبية'
                    ]
                ]
            ],
            [
                'code' => 'TND',
                'symbol' => 'د.ت',
                'symbol_position' => 'after',
                'decimal_places' => 3,
                'exchange_rate' => 3.1200,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Tunisian Dinar',
                        'plural_name' => 'Tunisian Dinars'
                    ],
                    'ar' => [
                        'name' => 'الدينار التونسي',
                        'plural_name' => 'دنانير تونسية'
                    ]
                ]
            ],
            [
                'code' => 'DZD',
                'symbol' => 'د.ج',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 135.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Algerian Dinar',
                        'plural_name' => 'Algerian Dinars'
                    ],
                    'ar' => [
                        'name' => 'الدينار الجزائري',
                        'plural_name' => 'دنانير جزائرية'
                    ]
                ]
            ],
            [
                'code' => 'MAD',
                'symbol' => 'د.م',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 10.1500,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Moroccan Dirham',
                        'plural_name' => 'Moroccan Dirhams'
                    ],
                    'ar' => [
                        'name' => 'الدرهم المغربي',
                        'plural_name' => 'دراهم مغربية'
                    ]
                ]
            ],
            [
                'code' => 'SDG',
                'symbol' => 'ج.س',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 1800.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Sudanese Pound',
                        'plural_name' => 'Sudanese Pounds'
                    ],
                    'ar' => [
                        'name' => 'الجنيه السوداني',
                        'plural_name' => 'جنيهات سودانية'
                    ]
                ]
            ],
            [
                'code' => 'SOS',
                'symbol' => 'ش.ص',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 570.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Somali Shilling',
                        'plural_name' => 'Somali Shillings'
                    ],
                    'ar' => [
                        'name' => 'الشلن الصومالي',
                        'plural_name' => 'شلنات صومالية'
                    ]
                ]
            ],
            [
                'code' => 'DJF',
                'symbol' => 'ف.ج',
                'symbol_position' => 'after',
                'decimal_places' => 0,
                'exchange_rate' => 177.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Djiboutian Franc',
                        'plural_name' => 'Djiboutian Francs'
                    ],
                    'ar' => [
                        'name' => 'الفرنك الجيبوتي',
                        'plural_name' => 'فرنكات جيبوتية'
                    ]
                ]
            ],
            [
                'code' => 'KMF',
                'symbol' => 'ف.ق',
                'symbol_position' => 'after',
                'decimal_places' => 0,
                'exchange_rate' => 450.0000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Comorian Franc',
                        'plural_name' => 'Comorian Francs'
                    ],
                    'ar' => [
                        'name' => 'الفرنك القمري',
                        'plural_name' => 'فرنكات قمرية'
                    ]
                ]
            ],
            [
                'code' => 'MRU',
                'symbol' => 'أ.م',
                'symbol_position' => 'after',
                'decimal_places' => 2,
                'exchange_rate' => 36.5000,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Mauritanian Ouguiya',
                        'plural_name' => 'Mauritanian Ouguiyas'
                    ],
                    'ar' => [
                        'name' => 'الأوقية الموريتانية',
                        'plural_name' => 'أوقيات موريتانية'
                    ]
                ]
            ],

            // العملات العالمية الرئيسية
            [
                'code' => 'USD',
                'symbol' => '$',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'exchange_rate' => 1.0000,
                'is_active' => true,
                'is_default' => true,
                'translations' => [
                    'en' => [
                        'name' => 'US Dollar',
                        'plural_name' => 'US Dollars'
                    ],
                    'ar' => [
                        'name' => 'الدولار الأمريكي',
                        'plural_name' => 'دولارات أمريكية'
                    ]
                ]
            ],
            [
                'code' => 'EUR',
                'symbol' => '€',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'exchange_rate' => 0.9200,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'Euro',
                        'plural_name' => 'Euros'
                    ],
                    'ar' => [
                        'name' => 'اليورو',
                        'plural_name' => 'يوروات'
                    ]
                ]
            ],
            [
                'code' => 'GBP',
                'symbol' => '£',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'exchange_rate' => 0.7900,
                'is_active' => true,
                'is_default' => false,
                'translations' => [
                    'en' => [
                        'name' => 'British Pound',
                        'plural_name' => 'British Pounds'
                    ],
                    'ar' => [
                        'name' => 'الجنيه الإسترليني',
                        'plural_name' => 'جنيهات إسترلينية'
                    ]
                ]
            ]
        ];

        // إدراج البيانات مع التحقق من عدم التكرار
        foreach ($currencies as $currencyData) {
            $translations = $currencyData['translations'];
            unset($currencyData['translations']);

            // إنشاء أو تحديث العملة
            $currency = Currency::updateOrCreate([
                'code' => $currencyData['code'],
            ], $currencyData);

            // إدراج الترجمات
            foreach ($translations as $locale => $translation) {
                CurrencyTranslation::updateOrCreate([
                    'currency_id' => $currency->id,
                    'locale' => $locale,
                ], $translation);
            }
        }

        $this->command->info('تم إدراج ' . count($currencies) . ' عملة بنجاح!');
    }
}
