<?php

return [
    'available_features' => [
        'ecommerce' => [
            'name' => 'ecommerce',
            'display_name' => 'Ecommerce',
            'description' => 'بناء وإدارة متجرك الإلكتروني بسهولة مع جميع الأدوات المطلوبة',
            'icon' => 'solar-shop-2-bold-duotone',
            'emoji' => '🛒',
            'color' => 'blue',
            'modules' => [
                'Core',
                'Items',
                'Customer',
                'GeoLocation'
            ],
            'features' => [
                'كتالوج منتجات شامل',
                'نظام طلبات متقدم',
                'إدارة العملاء',
                'بوابات دفع متعددة'
            ],
            'price' => 299,
            'setup_time' => '1-2 أسبوع'
        ],

        'clinics' => [
            'name' => 'clinic',
            'display_name' => 'Clinic',
            'description' => 'بناء وإدارة متجرك الإلكتروني بسهولة مع جميع الأدوات المطلوبة',
            'icon' => 'solar-map-point-hospital-bold-duotone',
            'emoji' => '🛒',
            'color' => 'blue',
            'modules' => [
                'Items',
                'Customers',
            ],
            'features' => [
                'كتالوج منتجات شامل',
                'نظام طلبات متقدم',
                'إدارة العملاء',
                'بوابات دفع متعددة'
            ],
            'price' => 299,
            'setup_time' => '1-2 أسبوع'
        ],
    ],

    'enabled_features' => [
        'ecommerce' => true,
        'clinics' => true,
    ],
];
