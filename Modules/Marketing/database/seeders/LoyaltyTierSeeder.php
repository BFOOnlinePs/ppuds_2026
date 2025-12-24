<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Entities\LoyaltyTier;
use Modules\Marketing\Enums\LoyaltyTierKey;

class LoyaltyTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LoyaltyTier::firstOrCreate(
            ['key' => LoyaltyTierKey::BRONZE->value],
            [
                'min_points' => 0,
                'created_by' => 1,
                'ar' => [
                    'name' => 'برونزي',
                    'description' => 'مستوى البداية لجميع الأعضاء الجدد.'
                ],
                'en' => [
                    'name' => 'Bronze',
                    'description' => 'The starting tier for all new members.'
                ]
            ]
        );

        // المستوى 2: فضي
        LoyaltyTier::firstOrCreate(
            ['key' => LoyaltyTierKey::SILVER->value],
            [
                'min_points' => 1000,
                'created_by' => 1,
                'ar' => [
                    'name' => 'فضي',
                    'description' => 'للأعضاء النشطين. احصل على مكافآت إضافية.'
                ],
                'en' => [
                    'name' => 'Silver',
                    'description' => 'For active members. Get extra rewards.'
                ]
            ]
        );

        // المستوى 3: ذهبي
        LoyaltyTier::firstOrCreate(
            ['key' => LoyaltyTierKey::GOLD->value],
            [
                'min_points' => 5000,
                'created_by' => 1,
                'ar' => [
                    'name' => 'ذهبي',
                    'description' => 'للعملاء القيمين. مزايا حصرية وخصومات أفضل.'
                ],
                'en' => [
                    'name' => 'Gold',
                    'description' => 'For valued customers. Exclusive benefits and better discounts.'
                ]
            ]
        );

        // المستوى 4: بلاتيني
        LoyaltyTier::firstOrCreate(
            ['key' => LoyaltyTierKey::PLATINUM->value],
            [
                'min_points' => 15000,
                'created_by' => 1,
                'ar' => [
                    'name' => 'بلاتيني',
                    'description' => 'للعملاء المميزين (Premium). أولوية في الخدمة وعروض خاصة.'
                ],
                'en' => [
                    'name' => 'Platinum',
                    'description' => 'For premium customers. Priority service and special offers.'
                ]
            ]
        );

        // المستوى 5: ماسي (الأعلى)
        LoyaltyTier::firstOrCreate(
            ['key' => LoyaltyTierKey::DIAMOND->value],
            [
                'min_points' => 50000,
                'created_by' => 1,
                'ar' => [
                    'name' => 'ماسي',
                    'description' => 'مستوى الـ VIP. أعلى المزايا، ودعوات خاصة، وهدايا فريدة.'
                ],
                'en' => [
                    'name' => 'Diamond',
                    'description' => 'The VIP tier. Highest benefits, special invites, and unique gifts.'
                ]
            ]
        );
    }
}
