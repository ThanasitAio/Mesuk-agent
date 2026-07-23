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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         | Shared payment slip storage - must point to the same directory
         | as Happy Realestate's "local" disk root so slips are accessible
         | from both systems.  Override HAPPYEST_STORAGE_PATH in .env for
         | production (absolute path to happyest/storage/app on the server).
         */
        'payment_storage' => [
            'driver' => 'local',
            'root'   => env('HAPPYEST_STORAGE_PATH', base_path('../happyest/storage/app/private')),
            'throw'  => false,
            'report' => false,
        ],

        /*
         | Shared PUBLIC storage - must point to the same directory as
         | Happy Realestate's "public" disk root (public/storage junction)
         | so files like agent avatars are web-accessible from both
         | systems. Override HAPPYEST_PUBLIC_STORAGE_PATH / HAPPYEST_APP_URL
         | in .env for production.
         */
        'happyest_public' => [
            'driver'     => 'local',
            'root'       => env('HAPPYEST_PUBLIC_STORAGE_PATH', base_path('../happyest/public/storage')),
            'url'        => rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/').'/storage',
            'visibility' => 'public',
            'throw'      => false,
            'report'     => false,
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
