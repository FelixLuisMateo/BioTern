<?php

return [
    'enabled' => env('BIOMETRIC_ENABLED', true),
    
    'device' => env('BIOMETRIC_DEVICE', 'fingerprint'),
    
    'types' => [
        'fingerprint' => 'Fingerprint',
        'face' => 'Face Recognition',
        'iris' => 'Iris Scanning',
    ],

    'api_endpoint' => env('BIOMETRIC_API_ENDPOINT', 'http://localhost:8080'),
    
    'device_id' => env('BIOMETRIC_DEVICE_ID', 'default-device'),
    
    'retry_attempts' => 3,
    'retry_delay' => 2000, // milliseconds
    
    'timeout' => 30, // seconds
];