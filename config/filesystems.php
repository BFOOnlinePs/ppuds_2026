<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/public/media'),
            'url' => env('APP_URL') . '/storage/media',
            'visibility' => 'public',
            'throw' => false,
        ],

        'items' => [
            'driver' => 'local',
            'root' => storage_path('app/public/items'),
            'url' => env('APP_URL') . '/storage/items',
            'visibility' => 'public',
            'throw' => false,
        ],

        'customers' => [
            'driver' => 'local',
            'root' => storage_path('app/public/customers'),
            'url' => env('APP_URL') . '/storage/customers',
            'visibility' => 'public',
            'throw' => false,
        ],

        'content' => [
            'driver' => 'local',
            'root' => storage_path('app/public/content'),
            'url' => env('APP_URL') . '/storage/content',
            'visibility' => 'public',
            'throw' => false,
        ],

        'banners' => [
            'driver' => 'local',
            'root' => storage_path('app/public/content/banners'),
            'url' => env('APP_URL') . '/storage/content/banners',
            'visibility' => 'public',
            'throw' => false,
        ],

        'offers' => [
            'driver' => 'local',
            'root' => storage_path('app/public/items/offers'),
            'url' => env('APP_URL') . '/storage/items/offers',
            'visibility' => 'public',
            'throw' => false,
        ],

        'addon_option' => [
            'driver' => 'local',
            'root' => storage_path('app/public/items/addon_option'),
            'url' => env('APP_URL') . '/storage/items/addon_option',
            'visibility' => 'public',
            'throw' => false,
        ],

        // PPU DS
        'companies' => [
            'driver' => 'local',
            'root' => storage_path('app/public/ppuds/companies'),
            'url' => env('APP_URL') . '/storage/ppuds/companies',
            'visibility' => 'public',
            'throw' => false,
        ],

        'student_reports' => [
            'driver' => 'local',
            'root' => storage_path('app/public/ppuds/student_reports'),
            'url' => env('APP_URL') . '/storage/ppuds/student_reports',
            'visibility' => 'public',
            'throw' => false,
        ],

        'student_profiles' => [
            'driver' => 'local',
            'root' => storage_path('app/public/ppuds/student_profiles'),
            'url' => env('APP_URL') . '/storage/ppuds/student_profiles',
            'visibility' => 'public',
            'throw' => false,
        ],

        'payments' => [
            'driver' => 'local',
            'root' => storage_path('app/public/ppuds/student_payments'),
            'url' => env('APP_URL') . '/storage/ppuds/student_payments',
            'visibility' => 'public',
            'throw' => false,
        ],

        'announcements' => [
            'driver' => 'local',
            'root' => storage_path('app/public/ppuds/announcements'),
            'url' => env('APP_URL') . '/storage/ppuds/announcements',
            'visibility' => 'public',
            'throw' => false,
        ],

        'leave_requests' => [
            'driver' => 'local',
            'root' => storage_path('app/public/ppuds/leave_requests'),
            'url' => env('APP_URL') . '/storage/ppuds/leave_requests',
            'visibility' => 'public',
            'throw' => false,
        ],

        'ppuds_notes' => [
            'driver' => 'local',
            'root' => storage_path('app/public/ppuds/ppuds_notes'),
            'url' => env('APP_URL') . '/storage/ppuds/ppuds_notes',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
