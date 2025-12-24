<?php

return [
    'mode'                     => '',
    'format'                   => 'A4',
    'default_font_size'        => '12',
    'default_font'             => 'sans-serif',
    'margin_left'              => 10,
    'margin_right'             => 10,
    'margin_top'               => 10,
    'margin_bottom'            => 10,
    'margin_header'            => 0,
    'margin_footer'            => 0,
    'orientation'              => 'P',
    'title'                    => 'Laravel mPDF',
    'subject'                  => '',
    'author'                   => '',
    'watermark'                => '',
    'show_watermark'           => false,
    'show_watermark_image'     => false,
    'watermark_font'           => 'sans-serif',
    'display_mode'             => 'fullpage',
    'watermark_text_alpha'     => 0.1,
    'watermark_image_path'     => '',
    'watermark_image_alpha'    => 0.2,
    'watermark_image_size'     => 'D',
    'watermark_image_position' => 'P',
    'custom_font_dir'          => public_path('fonts/'),
    'custom_font_data'         => [
        'tajawal' => [
            'R' => 'tajawal/Tajawal-Regular.ttf',
            'B' => 'tajawal/Tajawal-Bold.ttf',
            'L' => 'tajawal/Tajawal-Light.ttf',
            'useOTL' => 0xFF,    // <--- هذا السطر هو الحل (تفعيل OpenType)
            'useKashida' => 75,  // اختياري: لتحسين تباعد الأحرف العربية
        ],
    ],
    'auto_language_detection'  => false,
    'temp_dir'                 => storage_path('app'),
    'pdfa'                     => false,
    'pdfaauto'                 => false,
    'use_active_forms'         => false,
];
