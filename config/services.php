<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google_vision' => [
        'api_key' => env('GOOGLE_VISION_API_KEY'),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS', storage_path('app/google-credentials.json')),
        'ca_bundle' => env('GOOGLE_VISION_CA_BUNDLE'),
    ],

    'google_translate' => [
        'api_key' => env('GOOGLE_TRANSLATE_API_KEY', env('GOOGLE_VISION_API_KEY')),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'ca_bundle' => env('GEMINI_CA_BUNDLE', env('GOOGLE_VISION_CA_BUNDLE')),
    ],

];
