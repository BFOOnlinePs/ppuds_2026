<?php

return [

    /**-------------------------
     * Chat (الدردشة)
     *------------------------*/
    'labels' => [

        'you_replied_to_yourself' => 'قمت بالرد على نفسك',
        'participant_replied_to_you' => 'قام :sender بالرد عليك',
        'participant_replied_to_themself' => 'قام :sender بالرد على نفسه',
        'participant_replied_other_participant' => 'قام :sender بالرد على :receiver',
        'you' => 'أنت',
        'user' => 'مستخدم',
        'replying_to' => 'الرد على :participant',
        'replying_to_yourself' => 'الرد على نفسك',
        'attachment' => 'مرفق',
    ],

    'inputs' => [
        'message' => [
            'label' => 'الرسالة',
            'placeholder' => 'اكتب رسالة',
        ],
        'media' => [
            'label' => 'الوسائط',
            'placeholder' => 'الوسائط',
        ],
        'files' => [
            'label' => 'الملفات',
            'placeholder' => 'الملفات',
        ],
    ],

    'message_groups' => [
        'today' => 'اليوم',
        'yesterday' => 'أمس',

    ],

    'actions' => [
        'open_group_info' => [
            'label' => 'معلومات المجموعة',
        ],
        'open_chat_info' => [
            'label' => 'معلومات الدردشة',
        ],
        'close_chat' => [
            'label' => 'إغلاق الدردشة',
        ],
        'clear_chat' => [
            'label' => 'مسح سجل الدردشة',
            'confirmation_message' => 'هل أنت متأكد أنك تريد مسح سجل الدردشة؟ سيؤدي هذا إلى مسح الدردشة الخاصة بك فقط ولن يؤثر على المشاركين الآخرين.',
        ],
        'delete_chat' => [
            'label' => 'حذف الدردشة',
            'confirmation_message' => 'هل أنت متأكد أنك تريد حذف هذه الدردشة؟ سيؤدي هذا إلى إزالة الدردشة من جانبك فقط ولن يحذفها للمشاركين الآخرين.',
        ],

        'delete_for_everyone' => [
            'label' => 'حذف لدى الجميع',
            'confirmation_message' => 'هل أنت متأكد؟',
        ],
        'delete_for_me' => [
            'label' => 'حذف لدي',
            'confirmation_message' => 'هل أنت متأكد؟',
        ],
        'reply' => [
            'label' => 'رد',
        ],

        'exit_group' => [
            'label' => 'مغادرة المجموعة',
            'confirmation_message' => 'هل أنت متأكد أنك تريد مغادرة هذه المجموعة؟',
        ],
        'upload_file' => [
            'label' => 'ملف',
        ],
        'upload_media' => [
            'label' => 'الصور ومقاطع الفيديو',
        ],
    ],

    'messages' => [

        'cannot_exit_self_or_private_conversation' => 'لا يمكن الخروج من المحادثة الخاصة أو محادثة الذات',
        'owner_cannot_exit_conversation' => 'لا يمكن للمالك الخروج من المحادثة',
        'rate_limit' => 'محاولات كثيرة جداً! يرجى التمهل',
        'conversation_not_found' => 'لم يتم العثور على المحادثة.',
        'conversation_id_required' => 'مطلوب معرف المحادثة (ID)',
        'invalid_conversation_input' => 'إدخال محادثة غير صالح.',
    ],

    /**-------------------------
     * Info Component (مكون المعلومات)
     *------------------------*/

    'info' => [
        'heading' => [
            'label' => 'معلومات الدردشة',
        ],
        'actions' => [
            'delete_chat' => [
                'label' => 'حذف الدردشة',
                'confirmation_message' => 'هل أنت متأكد أنك تريد حذف هذه الدردشة؟ سيؤدي هذا إلى إزالة الدردشة من جانبك فقط ولن يحذفها للمشاركين الآخرين.',
            ],
        ],
        'messages' => [
            'invalid_conversation_type_error' => 'يُسمح فقط بالمحادثات الخاصة ومحادثات الذات',
        ],

    ],

    /**-------------------------
     * Group Folder (مجلد المجموعة)
     *------------------------*/

    'group' => [

        // Group info component
        'info' => [
            'heading' => [
                'label' => 'معلومات المجموعة',
            ],
            'labels' => [
                'members' => 'الأعضاء',
                'add_description' => 'إضافة وصف للمجموعة',
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
                'photo' => [
                    'label' => 'الصورة',
                ],
            ],
            'actions' => [
                'delete_group' => [
                    'label' => 'حذف المجموعة',
                    'confirmation_message' => 'هل أنت متأكد أنك تريد حذف هذه المجموعة؟',
                    'helper_text' => 'قبل أن تتمكن من حذف المجموعة، تحتاج إلى إزالة جميع أعضاء المجموعة أولاً.',
                ],
                'add_members' => [
                    'label' => 'إضافة أعضاء',
                ],
                'group_permissions' => [
                    'label' => 'صلاحيات المجموعة',
                ],
                'exit_group' => [
                    'label' => 'مغادرة المجموعة',
                    'confirmation_message' => 'هل أنت متأكد أنك تريد مغادرة المجموعة؟',

                ],
            ],
            'messages' => [
                'invalid_conversation_type_error' => 'يُسمح فقط بالمحادثات الجماعية',
            ],
        ],
        // Members component
        'members' => [
            'heading' => [
                'label' => 'الأعضاء',
            ],
            'inputs' => [
                'search' => [
                    'label' => 'بحث',
                    'placeholder' => 'البحث عن أعضاء',
                ],
            ],
            'labels' => [
                'members' => 'الأعضاء',
                'owner' => 'المالك',
                'admin' => 'مشرف',
                'no_members_found' => 'لم يتم العثور على أعضاء',
            ],
            'actions' => [
                'send_message_to_yourself' => [
                    'label' => 'مراسلة نفسك',

                ],
                'send_message_to_member' => [
                    'label' => 'مراسلة :member',

                ],
                'dismiss_admin' => [
                    'label' => 'إزالة الإشراف',
                    'confirmation_message' => 'هل أنت متأكد أنك تريد إزالة :member من الإشراف؟',
                ],
                'make_admin' => [
                    'label' => 'تعيين كمشرف',
                    'confirmation_message' => 'هل أنت متأكد أنك تريد تعيين :member كمشرف؟',
                ],
                'remove_from_group' => [
                    'label' => 'إزالة',
                    'confirmation_message' => 'هل أنت متأكد أنك تريد إزالة :member من هذه المجموعة؟',
                ],
                'load_more' => [
                    'label' => 'تحميل المزيد',
                ],

            ],
            'messages' => [
                'invalid_conversation_type_error' => 'يُسمح فقط بالمحادثات الجماعية',
            ],
        ],
        // add-Members component
        'add_members' => [
            'heading' => [
                'label' => 'إضافة أعضاء',
            ],
            'inputs' => [
                'search' => [
                    'label' => 'بحث',
                    'placeholder' => 'بحث',
                ],
            ],
            'labels' => [

            ],
            'actions' => [
                'save' => [
                    'label' => 'حفظ',

                ],

            ],
            'messages' => [
                'invalid_conversation_type_error' => 'يُسمح فقط بالمحادثات الجماعية',
                'members_limit_error' => 'لا يمكن أن يتجاوز عدد الأعضاء :count',
                'member_already_exists' => ' تمت إضافته بالفعل إلى المجموعة',
            ],
        ],
        // permissions component
        'permissions' => [
            'heading' => [
                'label' => 'الصلاحيات',
            ],
            'inputs' => [
                'search' => [
                    'label' => 'بحث',
                    'placeholder' => 'بحث',
                ],
            ],
            'labels' => [
                'members_can' => 'يمكن للأعضاء',

            ],
            'actions' => [
                'edit_group_information' => [
                    'label' => 'تعديل معلومات المجموعة',
                    'helper_text' => 'يشمل ذلك الاسم والرمز والوصف',
                ],
                'send_messages' => [
                    'label' => 'إرسال الرسائل',
                ],
                'add_other_members' => [
                    'label' => 'إضافة أعضاء آخرين',
                ],

            ],
            'messages' => [
            ],
        ],

    ],

];