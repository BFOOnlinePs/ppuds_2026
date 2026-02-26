<?php

return [

    // new-chat component (مكون محادثة جديدة)
    'chat' => [
        'labels' => [
            'heading' => 'محادثة جديدة',
            'you' => 'أنت',

        ],

        'inputs' => [
            'search' => [
                'label' => 'البحث في المحادثات',
                'placeholder' => 'بحث',
            ],
        ],

        'actions' => [
            'new_group' => [
                'label' => 'مجموعة جديدة',
            ],

        ],

        'messages' => [

            'empty_search_result' => 'لم يتم العثور على مستخدمين يطابقون بحثك.',
        ],
    ],

    // new-group component (مكون مجموعة جديدة)
    'group' => [
        'labels' => [
            'heading' => 'محادثة جديدة',
            'add_members' => 'إضافة أعضاء',

        ],

        'inputs' => [
            'name' => [
                'label' => 'اسم المجموعة',
                'placeholder' => 'أدخل الاسم',
            ],
            'description' => [
                'label' => 'الوصف',
                'placeholder' => 'اختياري',
            ],
            'search' => [
                'label' => 'بحث',
                'placeholder' => 'بحث',
            ],
            'photo' => [
                'label' => 'الصورة',
            ],
        ],

        'actions' => [
            'cancel' => [
                'label' => 'إلغاء',
            ],
            'next' => [
                'label' => 'التالي',
            ],
            'create' => [
                'label' => 'إنشاء',
            ],

        ],

        'messages' => [
            'members_limit_error' => 'لا يمكن أن يتجاوز عدد الأعضاء :count',
            'empty_search_result' => 'لم يتم العثور على مستخدمين يطابقون بحثك.',
        ],
    ],

];