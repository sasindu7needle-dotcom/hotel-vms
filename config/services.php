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

    'local_face' => [
        'python_path' => env('FACE_PYTHON_PATH', 'python'),
        'site_packages' => env('FACE_PYTHON_SITE_PACKAGES'),
        'script_path' => base_path('app/Support/verify_faces.py'),
        'detector_model' => env('FACE_DETECTOR_MODEL', resource_path('face-models/face_detection_yunet_2023mar.onnx')),
        'recognizer_model' => env('FACE_RECOGNIZER_MODEL', resource_path('face-models/face_recognition_sface_2021dec.onnx')),
        'cosine_threshold' => (float) env('FACE_MATCH_COSINE_THRESHOLD', 0.340),
        'timeout' => (int) env('FACE_PROCESS_TIMEOUT', 45),
    ],

];
